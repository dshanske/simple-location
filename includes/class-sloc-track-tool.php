<?php
/**
 * Track Tool.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', array( 'Sloc_Track_Tool', 'init' ) );

/**
 * Tools → Location Track: builds a [sloc_map track="…"] shortcode from the location provider's record of where the
 * current user was between two times.
 *
 * Points inside venues whose posts are private or protected, such as home, are left out.
 *
 * @since 6.1.0
 */
class Sloc_Track_Tool {

	/**
	 * Admin page slug.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const PAGE = 'sloc-track';

	/**
	 * Registers the hooks.
	 *
	 * @since 6.1.0
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
	}

	/**
	 * Adds the page under Tools.
	 *
	 * @since 6.1.0
	 */
	public static function admin_menu() {
		add_management_page(
			__( 'Location Track', 'simple-location' ),
			__( 'Location Track', 'simple-location' ),
			'edit_posts',
			self::PAGE,
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * Returns the private places to leave out of tracks: venues whose posts are private or protected.
	 *
	 * @since 6.1.0
	 *
	 * @return array[] Arrays of latitude, longitude and radius in meters.
	 */
	public static function get_private_places() {
		$venues = get_posts(
			array(
				'post_type'   => 'venue',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Run only when the tool builds a track.
				'meta_query'  => array(
					array(
						'key'     => 'venue_post_visibility',
						'value'   => array( 'private', 'protected' ),
						'compare' => 'IN',
					),
				),
			)
		);
		$places = array();
		foreach ( $venues as $venue_id ) {
			$location = get_post_geodata( $venue_id, false );
			if ( ! is_array( $location ) || ! isset( $location['latitude'], $location['longitude'] ) ) {
				continue;
			}
			$radius   = get_post_meta( $venue_id, 'venue_radius', true );
			$places[] = array( (float) $location['latitude'], (float) $location['longitude'], is_numeric( $radius ) && $radius > 0 ? (float) $radius : 50.0 );
		}
		return $places;
	}

	/**
	 * Removes the points inside private places.
	 *
	 * @since 6.1.0
	 *
	 * @param array   $points List of array( latitude, longitude ) points.
	 * @param array[] $places Places, from get_private_places().
	 * @return array Points outside them, in order.
	 */
	public static function remove_private_points( $points, $places ) {
		if ( empty( $places ) ) {
			return array_values( $points );
		}
		return array_values(
			array_filter(
				$points,
				function ( $point ) use ( $places ) {
					foreach ( $places as $place ) {
						if ( geo_in_radius( $place[0], $place[1], $point[0], $point[1], $place[2] ) ) {
							return false;
						}
					}
					return true;
				}
			)
		);
	}

	/**
	 * Builds a track for the current user between two times.
	 *
	 * @since 6.1.0
	 *
	 * @param DateTimeInterface $start   Start.
	 * @param DateTimeInterface $end     End.
	 * @param string            $markers Markers: 'ends' or 'none'.
	 * @return array|WP_Error Array with 'found', 'private' and 'kept' point counts, 'polyline' and 'shortcode', or an error.
	 */
	public static function build( $start, $end, $markers = 'ends' ) {
		if ( $end <= $start ) {
			return new WP_Error( 'track_times', __( 'The end must be after the start.', 'simple-location' ) );
		}
		$provider = Loc_Config::geolocation_provider();
		if ( ! $provider instanceof Location_Provider || ! method_exists( $provider, 'query' ) ) {
			return new WP_Error( 'track_unsupported', __( 'The location provider can\'t give tracks. Compass, Dawarich and GeoPulse can.', 'simple-location' ) );
		}
		$provider->set_user( get_current_user_id() );
		$track = $provider->query( $start, $end );
		if ( is_wp_error( $track ) ) {
			return $track;
		}
		if ( null === $track ) {
			return new WP_Error( 'track_account', __( 'Set up your location provider account on your profile first.', 'simple-location' ) );
		}
		// Providers give longitude, latitude pairs.
		$points = array();
		foreach ( is_array( $track ) ? $track : array() as $point ) {
			if ( is_array( $point ) && isset( $point[0], $point[1] ) && is_numeric( $point[0] ) && is_numeric( $point[1] ) ) {
				$points[] = array( (float) $point[1], (float) $point[0] );
			}
		}
		if ( count( $points ) < 2 ) {
			return new WP_Error( 'track_empty', __( 'The location provider has no track between those times.', 'simple-location' ) );
		}
		$public = self::remove_private_points( $points, self::get_private_places() );
		if ( count( $public ) < 2 ) {
			return new WP_Error( 'track_private', __( 'The whole track is inside private places, so there is nothing to show.', 'simple-location' ) );
		}
		$kept     = Sloc_Track::simplify( $public );
		$polyline = Sloc_Track::encode( $kept );
		$markers  = ( 'none' === $markers ) ? 'none' : 'ends';
		return array(
			'found'     => count( $points ),
			'private'   => count( $points ) - count( $public ),
			'kept'      => count( $kept ),
			'polyline'  => $polyline,
			'shortcode' => sprintf( '[sloc_map track="%1$s"%2$s]', Sloc_Track::to_attribute( $polyline ), 'none' === $markers ? ' markers="none"' : '' ),
		);
	}

	/**
	 * Parses a time from the form, in the site's timezone.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Value of a datetime-local field, such as 2025-07-01T14:30.
	 * @return DateTimeImmutable|null Time, or null if it isn't one.
	 */
	public static function parse_time( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$time = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', trim( $value ), wp_timezone() );
		return $time ? $time : null;
	}

	/**
	 * Prints the page.
	 *
	 * @since 6.1.0
	 */
	public static function page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$start   = '';
		$end     = '';
		$markers = 'ends';
		$result  = null;
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The nonce is verified before the fields are used.
		if ( isset( $_POST['sloc_track_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sloc_track_nonce'] ) ), 'sloc_track' ) ) {
			$start   = isset( $_POST['sloc_track_start'] ) ? sanitize_text_field( wp_unslash( $_POST['sloc_track_start'] ) ) : '';
			$end     = isset( $_POST['sloc_track_end'] ) ? sanitize_text_field( wp_unslash( $_POST['sloc_track_end'] ) ) : '';
			$markers = ( isset( $_POST['sloc_track_markers'] ) && 'none' === $_POST['sloc_track_markers'] ) ? 'none' : 'ends';
			$from    = self::parse_time( $start );
			$to      = self::parse_time( $end );
			$result  = ( $from && $to ) ? self::build( $from, $to, $markers ) : new WP_Error( 'track_times', __( 'Enter a start and an end.', 'simple-location' ) );
		}
		// phpcs:enable
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Location Track', 'simple-location' ); ?></h1>
			<p><?php esc_html_e( 'Get your route between two times from your location provider, and make a map of it to paste into a post. Points inside venues whose posts are private or protected, such as home, are left out.', 'simple-location' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'sloc_track', 'sloc_track_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="sloc_track_start"><?php esc_html_e( 'Start', 'simple-location' ); ?></label></th>
						<td><input type="datetime-local" name="sloc_track_start" id="sloc_track_start" value="<?php echo esc_attr( $start ); ?>" required /></td>
					</tr>
					<tr>
						<th><label for="sloc_track_end"><?php esc_html_e( 'End', 'simple-location' ); ?></label></th>
						<td><input type="datetime-local" name="sloc_track_end" id="sloc_track_end" value="<?php echo esc_attr( $end ); ?>" required />
							<p class="description">
								<?php
								/* translators: %s: Timezone name. */
								echo esc_html( sprintf( __( 'Times are in the site\'s timezone, %s.', 'simple-location' ), wp_timezone_string() ) );
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="sloc_track_markers"><?php esc_html_e( 'Markers', 'simple-location' ); ?></label></th>
						<td>
							<select name="sloc_track_markers" id="sloc_track_markers">
								<option value="ends" <?php selected( $markers, 'ends' ); ?>><?php esc_html_e( 'At the start and end', 'simple-location' ); ?></option>
								<option value="none" <?php selected( $markers, 'none' ); ?>><?php esc_html_e( 'None', 'simple-location' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Get Track', 'simple-location' ) ); ?>
			</form>
			<?php
			if ( is_wp_error( $result ) ) {
				printf( '<div class="notice notice-error inline"><p>%s</p></div>', esc_html( $result->get_error_message() ) );
			} elseif ( is_array( $result ) ) {
				self::print_result( $result );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Prints a built track: its counts, a preview of the map exactly as the shortcode draws it, and the shortcode to copy.
	 *
	 * @since 6.1.0
	 *
	 * @param array $result Track, from build().
	 */
	protected static function print_result( $result ) {
		?>
		<h2><?php esc_html_e( 'Track', 'simple-location' ); ?></h2>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: Points found 2: Points in private places 3: Points kept */
					__( '%1$s points found, %2$s left out in private places, %3$s kept for the map.', 'simple-location' ),
					number_format_i18n( $result['found'] ),
					number_format_i18n( $result['private'] ),
					number_format_i18n( $result['kept'] )
				)
			);
			?>
		</p>
		<h3><?php esc_html_e( 'Preview', 'simple-location' ); ?></h3>
		<div class="sloc-track-preview">
			<?php
			$preview = do_shortcode( $result['shortcode'] );
			if ( '' === $preview ) {
				printf( '<p>%s</p>', esc_html__( 'Choose a map provider in Settings to see the map here.', 'simple-location' ) );
			} else {
				echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The shortcode's output is escaped.
			}
			?>
		</div>
		<p><label for="sloc_track_shortcode"><?php esc_html_e( 'Paste this into a post:', 'simple-location' ); ?></label></p>
		<textarea id="sloc_track_shortcode" class="large-text code" rows="3" readonly onfocus="this.select();"><?php echo esc_textarea( $result['shortcode'] ); ?></textarea>
		<p><button type="button" class="button hide-if-no-js" onclick="navigator.clipboard &amp;&amp; navigator.clipboard.writeText( document.getElementById( 'sloc_track_shortcode' ).value );"><?php esc_html_e( 'Copy', 'simple-location' ); ?></button></p>
		<?php
	}
}

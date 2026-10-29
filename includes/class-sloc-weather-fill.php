<?php
/**
 * Filling in weather readings from Open-Meteo.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', array( 'Sloc_Weather_Fill', 'init' ) );

/**
 * Fills in weather readings the weather provider didn't give, such as the conditions, snow or cloud cover a personal
 * weather station has no sensor for, from Open-Meteo.
 *
 * New weather is filled in as it is looked up, and Tools → Weather Fill fills in existing posts in the background.
 * Only readings the provider didn't give are filled; its own values, including 0, are kept. Coordinates sent to
 * Open-Meteo are rounded to two decimal places, about a kilometer, so an exact location, such as home, isn't sent.
 *
 * @since 6.2.0
 */
class Sloc_Weather_Fill {

	/**
	 * Option with the readings to fill in.
	 *
	 * @since 6.2.0
	 * @var string
	 */
	const OPTION = 'sloc_weather_fill';

	/**
	 * Option with the state of the backfill of existing posts.
	 *
	 * @since 6.2.0
	 * @var string
	 */
	const STATE_OPTION = 'sloc_weather_fill_state';

	/**
	 * Post meta set once a post has been checked by the backfill, to the time it was.
	 *
	 * @since 6.2.0
	 * @var string
	 */
	const CHECKED_META = '_sloc_weather_fill';

	/**
	 * Cron hook of a backfill batch.
	 *
	 * @since 6.2.0
	 * @var string
	 */
	const HOOK = 'sloc_weather_fill_batch';

	/**
	 * Posts checked per backfill batch.
	 *
	 * @since 6.2.0
	 * @var int
	 */
	const BATCH = 50;

	/**
	 * Longest time one request covers, in days.
	 *
	 * @since 6.2.0
	 * @var int
	 */
	const MAX_RANGE_DAYS = 31;

	/**
	 * Tools page slug.
	 *
	 * @since 6.2.0
	 * @var string
	 */
	const PAGE = 'sloc-weather-fill';

	/**
	 * Slug of the provider readings are filled from.
	 *
	 * @since 6.2.0
	 * @var string
	 */
	const SOURCE = 'openmeteo';

	/**
	 * Condition codes that only describe the sky or the wind, which a condition from Open-Meteo with precipitation,
	 * fog or a thunderstorm replaces. A station's estimate from its sunlight can't see those.
	 *
	 * @since 6.2.0
	 * @var int[]
	 */
	const SKY_CODES = array( 400, 800, 801, 802, 803, 804 );

	/**
	 * Registers the hooks and the setting.
	 *
	 * @since 6.2.0
	 */
	public static function init() {
		register_setting(
			'sloc_providers',
			self::OPTION,
			array(
				'type'              => 'array',
				'description'       => 'Weather readings to fill in from Open-Meteo',
				'show_in_rest'      => false,
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_readings' ),
			)
		);
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ), 11 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( self::HOOK, array( __CLASS__, 'run_batch' ) );
		add_action( 'admin_post_sloc_weather_fill_start', array( __CLASS__, 'handle_start' ) );
		add_action( 'admin_post_sloc_weather_fill_stop', array( __CLASS__, 'handle_stop' ) );
	}

	/**
	 * Returns the readings that can be filled in, and the weather properties of each.
	 *
	 * @since 6.2.0
	 *
	 * @return array[] Arrays with 'label' and 'keys', keyed by reading.
	 */
	public static function get_readings() {
		return array(
			'conditions' => array(
				'label' => __( 'Conditions (summary and icon)', 'simple-location' ),
				'keys'  => array( 'code', 'summary' ),
			),
			'snow'       => array(
				'label' => __( 'Snowfall', 'simple-location' ),
				'keys'  => array( 'snow' ),
			),
			'snowdepth'  => array(
				'label' => __( 'Snow depth', 'simple-location' ),
				'keys'  => array( 'snowdepth' ),
			),
			'cloudiness' => array(
				'label' => __( 'Cloud cover', 'simple-location' ),
				'keys'  => array( 'cloudiness' ),
			),
			'radiation'  => array(
				'label' => __( 'Solar radiation', 'simple-location' ),
				'keys'  => array( 'radiation' ),
			),
			'uv'         => array(
				'label' => __( 'UV index (only for about the last three months)', 'simple-location' ),
				'keys'  => array( 'uv' ),
			),
			'visibility' => array(
				'label' => __( 'Visibility (only for about the last three months)', 'simple-location' ),
				'keys'  => array( 'visibility' ),
			),
		);
	}

	/**
	 * Sanitizes the readings setting.
	 *
	 * @since 6.2.0
	 *
	 * @param mixed $value Readings.
	 * @return string[] Known readings.
	 */
	public static function sanitize_readings( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_values( array_intersect( array_keys( self::get_readings() ), array_map( 'strval', $value ) ) );
	}

	/**
	 * Returns the readings chosen in the setting.
	 *
	 * @since 6.2.0
	 *
	 * @return string[] Readings.
	 */
	public static function enabled_readings() {
		return self::sanitize_readings( get_option( self::OPTION, array() ) );
	}

	/**
	 * Returns Open-Meteo, set to a location rounded to about a kilometer.
	 *
	 * @since 6.2.0
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @return Weather_Provider_OpenMeteo|null The provider, or null if it isn't available or the location isn't valid.
	 */
	public static function source( $latitude, $longitude ) {
		if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
			return null;
		}
		$provider = Loc_Config::weather_provider( self::SOURCE );
		if ( ! $provider instanceof Weather_Provider_OpenMeteo ) {
			return null;
		}
		$provider = clone $provider;
		$provider->set( round( (float) $latitude, 2 ), round( (float) $longitude, 2 ) );
		return $provider;
	}

	/**
	 * Whether weather lacks any of the readings, or has a condition that only describes the sky.
	 *
	 * @since 6.2.0
	 *
	 * @param array    $weather  Weather.
	 * @param string[] $readings Readings to fill in.
	 * @return bool
	 */
	public static function needs( $weather, $readings ) {
		if ( ! is_array( $weather ) || self::SOURCE === ( $weather['provider'] ?? '' ) ) {
			return false;
		}
		$all = self::get_readings();
		foreach ( $readings as $reading ) {
			if ( 'conditions' === $reading ) {
				$code = $weather['code'] ?? null;
				if ( ! is_numeric( $code ) || in_array( (int) $code, self::SKY_CODES, true ) ) {
					return true;
				}
				continue;
			}
			foreach ( $all[ $reading ]['keys'] ?? array() as $key ) {
				if ( ! Sloc_Weather_Data::has_value( $weather[ $key ] ?? null ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Fills in weather from Open-Meteo's: only the chosen readings the weather lacks.
	 *
	 * A condition is taken when the weather has none, or when the weather's only describes the sky or the wind and
	 * Open-Meteo's has precipitation, fog or a thunderstorm. Filled weather gets 'supplement' set to Open-Meteo, for its
	 * credit.
	 *
	 * @since 6.2.0
	 *
	 * @param array    $weather  Weather from the weather provider.
	 * @param array    $extra    Conditions from Open-Meteo.
	 * @param string[] $readings Readings to fill in.
	 * @return array Weather, with what was filled in.
	 */
	public static function merge( $weather, $extra, $readings ) {
		if ( ! is_array( $weather ) || ! is_array( $extra ) || empty( $extra ) ) {
			return $weather;
		}
		$all     = self::get_readings();
		$changed = false;
		foreach ( $readings as $reading ) {
			if ( 'conditions' === $reading ) {
				$theirs = $extra['code'] ?? null;
				if ( ! is_numeric( $theirs ) ) {
					continue;
				}
				$ours = $weather['code'] ?? null;
				$sky  = is_numeric( $ours ) && in_array( (int) $ours, self::SKY_CODES, true );
				if ( ! is_numeric( $ours ) || ( $sky && ! in_array( (int) $theirs, self::SKY_CODES, true ) ) ) {
					$weather['code'] = (int) $theirs;
					if ( isset( $extra['summary'] ) ) {
						$weather['summary'] = $extra['summary'];
					}
					$changed = true;
				}
				continue;
			}
			foreach ( $all[ $reading ]['keys'] ?? array() as $key ) {
				if ( ! Sloc_Weather_Data::has_value( $weather[ $key ] ?? null ) && Sloc_Weather_Data::has_value( $extra[ $key ] ?? null ) ) {
					$weather[ $key ] = $extra[ $key ];
					$changed         = true;
				}
			}
		}
		if ( $changed ) {
			$weather['supplement'] = self::SOURCE;
		}
		return $weather;
	}

	/**
	 * Fills in weather just looked up, with the chosen readings from Open-Meteo. Hooked to the weather chain.
	 *
	 * @since 6.2.0
	 *
	 * @param array|WP_Error           $weather   Weather from the weather provider.
	 * @param float                    $latitude  Latitude.
	 * @param float                    $longitude Longitude.
	 * @param int|string|DateTime|null $time      Time of the weather. Default now.
	 * @return array|WP_Error The weather, filled in where it could be.
	 */
	public static function fill( $weather, $latitude, $longitude, $time = null ) {
		$readings = self::enabled_readings();
		if ( ! is_array( $weather ) || empty( $weather ) || empty( $readings ) || ! self::needs( $weather, $readings ) ) {
			return $weather;
		}
		$source = self::source( $latitude, $longitude );
		if ( ! $source ) {
			return $weather;
		}
		$extra = $source->get_conditions( $time );
		if ( is_wp_error( $extra ) || ! is_array( $extra ) ) {
			return $weather;
		}
		return self::merge( $weather, $extra, $readings );
	}

	/**
	 * Returns the query arguments of posts the backfill hasn't checked: with weather and a location.
	 *
	 * @since 6.2.0
	 *
	 * @param int $limit Most posts.
	 * @return array WP_Query arguments.
	 */
	protected static function query_args( $limit ) {
		return array(
			'post_type'      => 'any',
			'post_status'    => 'any',
			'posts_per_page' => (int) $limit,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Run in background batches.
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'geo_latitude',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'geo_longitude',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => self::CHECKED_META,
					'compare' => 'NOT EXISTS',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => 'weather_temperature',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'weather_code',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'weather_provider',
						'compare' => 'EXISTS',
					),
				),
			),
		);
	}

	/**
	 * Returns how many posts the backfill hasn't checked yet.
	 *
	 * @since 6.2.0
	 *
	 * @return int Posts.
	 */
	public static function remaining() {
		$args                  = self::query_args( 1 );
		$args['no_found_rows'] = false;
		$query                 = new WP_Query( $args );
		return (int) $query->found_posts;
	}

	/**
	 * Returns the state of the backfill.
	 *
	 * @since 6.2.0
	 *
	 * @return array {
	 *     @type string $status  'idle', 'running', 'stopped' or 'done'.
	 *     @type int    $checked Posts checked.
	 *     @type int    $filled  Posts with readings filled in.
	 *     @type string $error   Why the backfill stopped, if it did so by itself.
	 * }
	 */
	public static function get_state() {
		$state = get_option( self::STATE_OPTION, array() );
		return wp_parse_args(
			is_array( $state ) ? $state : array(),
			array(
				'status'  => 'idle',
				'checked' => 0,
				'filled'  => 0,
				'error'   => '',
			)
		);
	}

	/**
	 * Saves the state of the backfill.
	 *
	 * @since 6.2.0
	 *
	 * @param array $state State, as get_state() returns it.
	 */
	protected static function set_state( $state ) {
		update_option( self::STATE_OPTION, $state, false );
	}

	/**
	 * Starts the backfill.
	 *
	 * @since 6.2.0
	 *
	 * @param bool $recheck Whether to check again the posts checked by an earlier backfill, such as after choosing more
	 *                      readings.
	 */
	public static function start( $recheck = false ) {
		$state = self::get_state();
		if ( $recheck ) {
			delete_post_meta_by_key( self::CHECKED_META );
		}
		// Continuing after a stop keeps the counts.
		$continue = ! $recheck && 'stopped' === $state['status'];
		self::set_state(
			array(
				'status'  => 'running',
				'checked' => $continue ? (int) $state['checked'] : 0,
				'filled'  => $continue ? (int) $state['filled'] : 0,
				'error'   => '',
			)
		);
		wp_clear_scheduled_hook( self::HOOK );
		wp_schedule_single_event( time(), self::HOOK );
	}

	/**
	 * Stops the backfill. Posts checked so far stay checked, so starting again continues.
	 *
	 * @since 6.2.0
	 */
	public static function stop() {
		$state           = self::get_state();
		$state['status'] = 'stopped';
		self::set_state( $state );
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Splits posts at one place into runs that one request can cover: at most MAX_RANGE_DAYS long, and all either
	 * older than Open-Meteo's archive limit or newer.
	 *
	 * @since 6.2.0
	 *
	 * @param array[] $posts Posts, each with 'time', oldest first.
	 * @return array[] Runs of posts.
	 */
	public static function runs( $posts ) {
		$runs    = array();
		$current = array();
		$archive = null;
		foreach ( $posts as $post ) {
			$old = ( time() - $post['time'] > Weather_Provider_OpenMeteo::ARCHIVE_AFTER * DAY_IN_SECONDS );
			if ( $current && ( $old !== $archive || $post['time'] - $current[0]['time'] > self::MAX_RANGE_DAYS * DAY_IN_SECONDS ) ) {
				$runs[]  = $current;
				$current = array();
			}
			$current[] = $post;
			$archive   = $old;
		}
		if ( $current ) {
			$runs[] = $current;
		}
		return $runs;
	}

	/**
	 * Checks one batch of posts, filling in the chosen readings, and schedules the next until none are left.
	 *
	 * Posts at the same rounded place within a month share one request. If Open-Meteo refuses a request, such as for
	 * its daily limit, the backfill stops with the reason, and the posts not yet checked stay unchecked.
	 *
	 * @since 6.2.0
	 */
	public static function run_batch() {
		$state = self::get_state();
		if ( 'running' !== $state['status'] ) {
			return;
		}
		$readings = self::enabled_readings();
		if ( empty( $readings ) ) {
			$state['status'] = 'stopped';
			$state['error']  = __( 'No readings are chosen to fill in.', 'simple-location' );
			self::set_state( $state );
			return;
		}
		$ids = get_posts( self::query_args( self::BATCH ) );
		if ( empty( $ids ) ) {
			$state['status'] = 'done';
			self::set_state( $state );
			return;
		}

		$places = array();
		foreach ( $ids as $post_id ) {
			$latitude  = get_post_meta( $post_id, 'geo_latitude', true );
			$longitude = get_post_meta( $post_id, 'geo_longitude', true );
			$weather   = get_post_weatherdata( $post_id );
			$time      = get_post_timestamp( $post_id );
			if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) || ! $time || ! self::needs( $weather, $readings ) ) {
				update_post_meta( $post_id, self::CHECKED_META, time() );
				++$state['checked'];
				continue;
			}
			$key              = sprintf( '%.2f,%.2f', round( (float) $latitude, 2 ), round( (float) $longitude, 2 ) );
			$places[ $key ][] = array(
				'id'        => $post_id,
				'time'      => $time,
				'weather'   => $weather,
				'latitude'  => (float) $latitude,
				'longitude' => (float) $longitude,
			);
		}

		foreach ( $places as $posts ) {
			$source = self::source( $posts[0]['latitude'], $posts[0]['longitude'] );
			if ( ! $source ) {
				$state['status'] = 'stopped';
				$state['error']  = __( 'Open-Meteo is not available.', 'simple-location' );
				self::set_state( $state );
				return;
			}
			usort( $posts, fn( $a, $b ) => $a['time'] <=> $b['time'] );
			foreach ( self::runs( $posts ) as $run ) {
				$json = $source->fetch_range( $run[0]['time'], $run[ count( $run ) - 1 ]['time'] );
				if ( is_wp_error( $json ) ) {
					$state['status'] = 'stopped';
					$state['error']  = $json->get_error_message();
					self::set_state( $state );
					return;
				}
				foreach ( $run as $post ) {
					$filled = self::merge( $post['weather'], $source->conditions_at( $json, $post['time'] ), $readings );
					foreach ( $filled as $key => $value ) {
						if ( ! array_key_exists( $key, $post['weather'] ) || $post['weather'][ $key ] !== $value ) {
							set_post_weatherdata( $post['id'], $key, $value );
						}
					}
					if ( isset( $filled['supplement'] ) && ! isset( $post['weather']['supplement'] ) ) {
						++$state['filled'];
					}
					update_post_meta( $post['id'], self::CHECKED_META, time() );
					++$state['checked'];
				}
			}
		}

		if ( count( $ids ) >= self::BATCH ) {
			self::set_state( $state );
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::HOOK );
			return;
		}
		$state['status'] = 'done';
		self::set_state( $state );
	}

	/**
	 * Adds the readings field to the weather provider settings.
	 *
	 * @since 6.2.0
	 */
	public static function admin_init() {
		add_settings_field(
			self::OPTION,
			__( 'Fill In Missing Readings', 'simple-location' ),
			array( __CLASS__, 'readings_callback' ),
			'sloc_providers',
			'sloc_providers'
		);
	}

	/**
	 * Prints the readings field.
	 *
	 * @since 6.2.0
	 */
	public static function readings_callback() {
		$enabled = self::enabled_readings();
		echo '<fieldset>';
		foreach ( self::get_readings() as $reading => $info ) {
			printf(
				'<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label><br />',
				esc_attr( self::OPTION ),
				esc_attr( $reading ),
				checked( in_array( $reading, $enabled, true ), true, false ),
				esc_html( $info['label'] )
			);
		}
		echo '</fieldset>';
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Readings the weather provider doesn\'t give, such as those a weather station has no sensor for, are filled in from Open-Meteo, which is credited with the weather. The provider\'s own readings are kept. Locations are rounded to about a kilometer before they are sent. Tools → Weather Fill fills in existing posts.', 'simple-location' )
		);
	}

	/**
	 * Adds the page under Tools.
	 *
	 * @since 6.2.0
	 */
	public static function admin_menu() {
		add_management_page(
			__( 'Weather Fill', 'simple-location' ),
			__( 'Weather Fill', 'simple-location' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * Returns the URL of the Tools page.
	 *
	 * @since 6.2.0
	 *
	 * @return string URL.
	 */
	public static function page_url() {
		return admin_url( 'tools.php?page=' . self::PAGE );
	}

	/**
	 * Starts the backfill from the Tools page.
	 *
	 * @since 6.2.0
	 */
	public static function handle_start() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'simple-location' ), 403 );
		}
		check_admin_referer( 'sloc_weather_fill_start' );
		self::start( ! empty( $_POST['sloc_weather_fill_recheck'] ) );
		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * Stops the backfill from the Tools page.
	 *
	 * @since 6.2.0
	 */
	public static function handle_stop() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'simple-location' ), 403 );
		}
		check_admin_referer( 'sloc_weather_fill_stop' );
		self::stop();
		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * Prints the Tools page.
	 *
	 * @since 6.2.0
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$state    = self::get_state();
		$readings = self::enabled_readings();
		$all      = self::get_readings();
		$statuses = array(
			'idle'    => __( 'Not started', 'simple-location' ),
			'running' => __( 'Running in the background', 'simple-location' ),
			'stopped' => __( 'Stopped', 'simple-location' ),
			'done'    => __( 'Done', 'simple-location' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Weather Fill', 'simple-location' ); ?></h1>
			<p><?php esc_html_e( 'Fill in weather readings that posts are missing, such as the conditions, snow or cloud cover that a weather station has no sensor for, from Open-Meteo. Only missing readings are filled in. Locations are rounded to about a kilometer before they are sent.', 'simple-location' ); ?></p>
			<?php if ( empty( $readings ) ) { ?>
				<?php
				wp_admin_notice(
					sprintf(
						/* translators: %s: Link to the settings. */
						esc_html__( 'Choose the readings to fill in under Fill In Missing Readings in the %s first.', 'simple-location' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=simloc&tab=providers' ) ) . '">' . esc_html__( 'provider settings', 'simple-location' ) . '</a>'
					),
					array(
						'type'               => 'warning',
						'additional_classes' => array( 'inline' ),
					)
				);
				?>
			<?php } else { ?>
				<p>
					<?php
					/* translators: %s: List of readings. */
					echo esc_html( sprintf( __( 'Readings to fill in: %s.', 'simple-location' ), implode( ', ', array_map( fn( $reading ) => $all[ $reading ]['label'], $readings ) ) ) );
					?>
				</p>
			<?php } ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Status', 'simple-location' ); ?></th>
					<td><?php echo esc_html( $statuses[ $state['status'] ] ?? $state['status'] ); ?>
						<?php if ( '' !== $state['error'] ) { ?>
							<p class="description"><?php echo esc_html( $state['error'] ); ?></p>
						<?php } ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Checked', 'simple-location' ); ?></th>
					<td>
						<?php
						/* translators: 1: Posts checked 2: Posts filled in. */
						echo esc_html( sprintf( __( '%1$s posts checked, %2$s filled in', 'simple-location' ), number_format_i18n( $state['checked'] ), number_format_i18n( $state['filled'] ) ) );
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Left to check', 'simple-location' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( self::remaining() ) ); ?></td>
				</tr>
			</table>
			<?php if ( 'running' === $state['status'] ) { ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="sloc_weather_fill_stop" />
					<?php wp_nonce_field( 'sloc_weather_fill_stop' ); ?>
					<?php submit_button( __( 'Stop', 'simple-location' ), 'secondary' ); ?>
				</form>
			<?php } elseif ( ! empty( $readings ) ) { ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="sloc_weather_fill_start" />
					<?php wp_nonce_field( 'sloc_weather_fill_start' ); ?>
					<p><label><input type="checkbox" name="sloc_weather_fill_recheck" value="1" /> <?php esc_html_e( 'Also check again the posts checked before, such as after choosing more readings', 'simple-location' ); ?></label></p>
					<?php submit_button( 'stopped' === $state['status'] ? __( 'Continue', 'simple-location' ) : __( 'Start', 'simple-location' ) ); ?>
				</form>
			<?php } ?>
		</div>
		<?php
	}
}

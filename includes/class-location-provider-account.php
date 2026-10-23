<?php
/**
 * Base class for location providers that read a user's own location history service.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * A location provider that asks a service the user runs, such as Dawarich or GeoPulse, where they were.
 *
 * Each user enters their server's URL and API key on their profile. The key is stored as user meta and is never
 * printed back: the profile only says whether one is saved. Only the user, or someone who can edit them, can use
 * their account.
 *
 * Subclasses' constructors take the same arguments, since the profile hooks create them with `new static()`.
 *
 * @since 6.1.0
 *
 * @phpstan-consistent-constructor
 */
abstract class Location_Provider_Account extends Location_Provider {

	/**
	 * Constructor.
	 *
	 * Subclasses set the name and slug before calling it.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args Arguments. See Location_Provider::__construct().
	 */
	public function __construct( $args = array() ) {
		parent::__construct( $args );
		$this->background = true;
		if ( $this->is_active() ) {
			add_action( 'show_user_profile', array( static::class, 'user_profile' ), 13 );
			add_action( 'edit_user_profile', array( static::class, 'user_profile' ), 13 );
			add_action( 'personal_options_update', array( static::class, 'save_user_profile' ), 13 );
			add_action( 'edit_user_profile_update', array( static::class, 'save_user_profile' ), 13 );
		}
	}

	/**
	 * Returns the user meta key of an account setting.
	 *
	 * @since 6.1.0
	 *
	 * @param string $field 'url' or 'api'.
	 * @return string Meta key, such as dawarich_url.
	 */
	public function meta_key( $field ) {
		return $this->slug . '_' . $field;
	}

	/**
	 * Prints the account fields on a user's profile.
	 *
	 * @since 6.1.0
	 *
	 * @param WP_User $user User being edited.
	 */
	public static function user_profile( $user ) {
		if ( ! $user instanceof WP_User || ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		$provider = new static();
		$url_key  = $provider->meta_key( 'url' );
		$api_key  = $provider->meta_key( 'api' );
		$has_key  = '' !== (string) get_user_meta( $user->ID, $api_key, true );
		?>
		<h2><?php echo esc_html( $provider->get_name() ); ?></h2>
		<?php wp_nonce_field( $provider->slug . '_account', $provider->slug . '_account_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="<?php echo esc_attr( $url_key ); ?>">
					<?php
					/* translators: %s: Service name. */
					echo esc_html( sprintf( __( '%s URL', 'simple-location' ), $provider->get_name() ) );
					?>
				</label></th>
				<td>
					<input type="url" name="<?php echo esc_attr( $url_key ); ?>" id="<?php echo esc_attr( $url_key ); ?>" value="<?php echo esc_attr( get_user_meta( $user->ID, $url_key, true ) ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'The address of your server, such as https://location.example.com', 'simple-location' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="<?php echo esc_attr( $api_key ); ?>">
					<?php
					/* translators: %s: Service name. */
					echo esc_html( sprintf( __( '%s API Key', 'simple-location' ), $provider->get_name() ) );
					?>
				</label></th>
				<td>
					<input type="password" name="<?php echo esc_attr( $api_key ); ?>" id="<?php echo esc_attr( $api_key ); ?>" value="" autocomplete="off" class="regular-text" />
					<p class="description">
						<?php
						echo esc_html(
							$has_key
								? __( 'A key is saved. Enter a new key to replace it, or leave this empty to keep it.', 'simple-location' )
								: $provider->get_key_description()
						);
						?>
					</p>
					<?php if ( $has_key ) : ?>
						<label><input type="checkbox" name="<?php echo esc_attr( $api_key . '_remove' ); ?>" value="1" /> <?php esc_html_e( 'Remove the saved key', 'simple-location' ); ?></label>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Saves the account fields from a user's profile.
	 *
	 * @since 6.1.0
	 *
	 * @param int $user_id User ID.
	 */
	public static function save_user_profile( $user_id ) {
		$provider = new static();
		$nonce    = $provider->slug . '_account_nonce';
		// The nonce is only present when the profile showed the fields.
		if ( ! isset( $_POST[ $nonce ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ $nonce ] ) ), $provider->slug . '_account' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		$url_key = $provider->meta_key( 'url' );
		$api_key = $provider->meta_key( 'api' );

		$url = isset( $_POST[ $url_key ] ) ? esc_url_raw( trim( wp_unslash( $_POST[ $url_key ] ) ), array( 'http', 'https' ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by esc_url_raw().
		if ( '' === $url ) {
			delete_user_meta( $user_id, $url_key );
		} else {
			update_user_meta( $user_id, $url_key, untrailingslashit( $url ) );
		}

		if ( ! empty( $_POST[ $api_key . '_remove' ] ) ) {
			delete_user_meta( $user_id, $api_key );
		}
		$key = isset( $_POST[ $api_key ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $api_key ] ) ) ) : '';
		if ( '' !== $key ) {
			update_user_meta( $user_id, $api_key, $key );
		}
	}

	/**
	 * Returns the description of the API key field, saying where to create a key.
	 *
	 * @since 6.1.0
	 *
	 * @return string Description.
	 */
	abstract protected function get_key_description();

	/**
	 * Returns the user whose account to use.
	 *
	 * That is the user set with set_user(), or the current user if none was set. The service holds a person's
	 * location history, so another user's account is only used when the current user can edit that user, which is
	 * normally only administrators.
	 *
	 * @since 6.1.0
	 *
	 * @return int|WP_Error User ID, 0 if there is no user, or an error if the current user may not use that account.
	 */
	protected function account_user() {
		$current = get_current_user_id();
		if ( $this->user instanceof WP_User ) {
			$user_id = $this->user->ID;
		} elseif ( is_numeric( $this->user ) && 0 < (int) $this->user ) {
			$user_id = (int) $this->user;
		} else {
			$user_id = $current;
		}
		if ( ! $user_id ) {
			return 0;
		}
		if ( $current !== $user_id && ! current_user_can( 'edit_user', $user_id ) ) {
			return new WP_Error(
				$this->slug . '_not_allowed',
				/* translators: %s: Service name. */
				sprintf( __( 'You cannot look up another user\'s location in %s', 'simple-location' ), $this->get_name() ),
				array( 'user' => $user_id )
			);
		}
		return $user_id;
	}

	/**
	 * Returns the URL and API key of the account to use.
	 *
	 * @since 6.1.0
	 *
	 * @return array|WP_Error|null Array with 'url', without a trailing slash, and 'key'; an error if the current user
	 *                             may not use the account; or null without a user, URL or key.
	 */
	protected function get_account() {
		$user_id = $this->account_user();
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		if ( ! $user_id ) {
			return null;
		}
		$url = get_user_meta( $user_id, $this->meta_key( 'url' ), true );
		$key = get_user_meta( $user_id, $this->meta_key( 'api' ), true );
		// wp_safe_remote_get() refuses local and private addresses when the request is made.
		if ( ! is_string( $url ) || ! in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) || ! is_string( $key ) || '' === $key ) {
			return null;
		}
		return array(
			'url' => untrailingslashit( $url ),
			'key' => $key,
		);
	}

	/**
	 * Converts a time to UTC.
	 *
	 * @since 6.1.0
	 *
	 * @param DateTimeInterface|int|string $time A date and time, a Unix timestamp, or a string DateTime can parse.
	 * @return DateTimeImmutable|WP_Error The time in UTC, or an error if it can't be parsed.
	 */
	protected function utc_time( $time ) {
		try {
			if ( $time instanceof DateTime ) {
				$time = DateTimeImmutable::createFromMutable( $time );
			} elseif ( is_int( $time ) || ( is_string( $time ) && ctype_digit( $time ) ) ) {
				$time = new DateTimeImmutable( '@' . $time );
			} elseif ( is_string( $time ) ) {
				$time = new DateTimeImmutable( $time, wp_timezone() );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'invalid_time', __( 'Invalid time', 'simple-location' ), array( 'time' => $time ) );
		}
		if ( ! $time instanceof DateTimeImmutable ) {
			return new WP_Error( 'invalid_time', __( 'Invalid time', 'simple-location' ), array( 'time' => $time ) );
		}
		return $time->setTimezone( new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Sets the location from a point in the service's history.
	 *
	 * @since 6.1.0
	 *
	 * @param float|string $latitude          Latitude.
	 * @param float|string $longitude         Longitude.
	 * @param array        $optional {
	 *     Optional values; each may be missing or null.
	 *
	 *     @type float      $altitude          Altitude in meters.
	 *     @type float      $accuracy          Horizontal accuracy in meters.
	 *     @type float      $altitude_accuracy Vertical accuracy in meters.
	 *     @type float      $kmh               Speed in kilometers per hour.
	 *     @type float      $heading           Heading in degrees.
	 *     @type int|string $time              Unix timestamp or ISO 8601 time of the point.
	 * }
	 * @return bool True if the coordinates were valid and set.
	 */
	protected function set_point( $latitude, $longitude, $optional = array() ) {
		if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
			return false;
		}
		$latitude  = clean_coordinate( (float) $latitude, 'latitude' );
		$longitude = clean_coordinate( (float) $longitude, 'longitude' );
		if ( false === $latitude || false === $longitude ) {
			return false;
		}
		$this->latitude          = $latitude;
		$this->longitude         = $longitude;
		$number                  = function ( $key, $precision = 2 ) use ( $optional ) {
			return is_numeric( $optional[ $key ] ?? null ) ? round( (float) $optional[ $key ], $precision ) : null;
		};
		$this->altitude          = $number( 'altitude' );
		$this->accuracy          = $number( 'accuracy' );
		$this->altitude_accuracy = $number( 'altitude_accuracy' );
		$this->heading           = $number( 'heading', 0 );
		$kmh                     = $number( 'kmh' );
		$this->speed             = null === $kmh ? null : round( $kmh / 3.6, 2 );
		if ( isset( $optional['time'] ) && ( is_numeric( $optional['time'] ) || is_string( $optional['time'] ) ) ) {
			$time = $this->utc_time( is_numeric( $optional['time'] ) ? (int) $optional['time'] : $optional['time'] );
			if ( ! is_wp_error( $time ) ) {
				$this->time = $time->format( DATE_W3C );
			}
		}
		return true;
	}

	/**
	 * Returns a list of points as array( longitude, latitude ) pairs, skipping points without valid coordinates.
	 *
	 * @since 6.1.0
	 *
	 * @param array  $points    Points, each an array.
	 * @param string $latitude  Key of the latitude in each point.
	 * @param string $longitude Key of the longitude in each point.
	 * @return array List of array( longitude, latitude ) points.
	 */
	protected static function track( $points, $latitude, $longitude ) {
		$track = array();
		foreach ( $points as $point ) {
			if ( ! is_array( $point ) || ! is_numeric( $point[ $latitude ] ?? null ) || ! is_numeric( $point[ $longitude ] ?? null ) ) {
				continue;
			}
			$lng = clean_coordinate( (float) $point[ $longitude ], 'longitude' );
			$lat = clean_coordinate( (float) $point[ $latitude ], 'latitude' );
			if ( false !== $lng && false !== $lat ) {
				$track[] = array( $lng, $lat );
			}
		}
		return $track;
	}
}

<?php
/**
 * Weather Metadata
 *
 * Registers weather metadata and supplies functions to assist in manipulating it.
 *
 * @package Simple Location
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', array( 'Sloc_Weather_Data', 'init' ) );

/**
 * Handles Weather Functionality for WordPress objects.
 *
 * @since 1.0.0
 */
class Sloc_Weather_Data {
	use Weather_Info_Trait;

	/**
	 * Weather properties that can be stored, each as `weather_{property}` meta.
	 *
	 * @since 1.0.0
	 * @since 6.2.0 Adds voc_index and nox_index, the VOC and NOx indices of a Sensirion gas sensor.
	 * @since 6.2.0 Adds snowdepth, the snow on the ground, apart from snow, the snowfall, and snowrate.
	 * @since 6.2.0 Adds supplement, the provider that filled in readings; see Sloc_Weather_Fill.
	 * @var string[]
	 */
	public static $properties = array(
		'temperature',
		'humidity',
		'heatindex',
		'windchill',
		'dewpoint',
		'pressure',
		'cloudiness',
		'rain',
		'snow',
		'snowdepth',
		'visibility',
		'radiation',
		'illuminance',
		'uv',
		'aqi',
		'pm1_0',
		'pm2_5',
		'pm10_0',
		'co',
		'co2',
		'nh3',
		'o3',
		'pb',
		'so2',
		'voc_index',
		'nox_index',
		'windspeed',
		'winddegree',
		'windgust',
		'summary',
		'icon',
		'code',
		'provider',
		'supplement',
		'rainrate',
		'snowrate',
		'attribution',
		'attribution_url',
	);

	/**
	 * Properties that say where the weather came from, which aren't weather on their own.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const SOURCE_PROPERTIES = array( 'provider', 'supplement', 'attribution', 'attribution_url' );

	/**
	 * Credits to print in the page footer, keyed by provider slug.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	protected static $footer_credits = array();

	/**
	 * Weather Data Initialization Function.
	 *
	 * Meant to be attached to init hook enhancements.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		self::register_meta();
		add_action( 'simple_location_sidebox', array( __CLASS__, 'submitbox' ), 12, 3 );

		// Add Post Type Support for Weather
		add_post_type_support( 'post', 'weather' );
	}

	/**
	 * Adds the weather fields to the location metabox, when a weather provider is set.
	 *
	 * Hooked to `simple_location_sidebox`.
	 *
	 * @since 5.0.0
	 *
	 * @param WP_Screen|null     $screen Current admin screen.
	 * @param WP_Post|WP_Comment $object Post or comment being edited.
	 * @param array              $args   Metabox arguments.
	 */
	public static function submitbox( $screen, $object, $args ) {
		// Check to see whether or not there is a configured provider. If there is not, hide the option.
		$weather = Loc_Config::weather_provider();
		if ( ! $weather ) {
			return;
		}

		if ( ! empty( $screen->post_type ) && post_type_supports( $screen->post_type, 'weather' ) ) {
			load_template( plugin_dir_path( __DIR__ ) . 'templates/weather-metabox.php' );
		}
	}

	/**
	 * Registers Geo Metadata.
	 *
	 * @since 1.0.0
	 * @since 6.1.0 Registers the rain rate and a weather station's credit.
	 * @since 6.2.0 Registers the VOC and NOx indices, the snow depth and the snow rate.
	 */
	public static function register_meta() {
		// Legacy Weather Storage
		$args = array(
			'type'         => 'array',
			'description'  => 'Weather Data (Deprecated)',
			'single'       => true,
			'show_in_rest' => false,
		);
		register_meta( 'post', 'geo_weather', $args );
		register_meta( 'comment', 'geo_weather', $args );
		register_meta( 'user', 'geo_weather', $args );
		register_meta( 'term', 'geo_weather', $args );

		$numerics = array(
			// Weather Properties.
			'temperature' => __( 'Temperature', 'simple-location' ),
			'humidity'    => __( 'Humidity', 'simple-location' ),
			'heatindex'   => __( 'Heat Index', 'simple-location' ),
			'windchill'   => __( 'Wind Chill', 'simple-location' ),
			'dewpoint'    => __( 'Dewpoint', 'simple-location' ),
			'pressure'    => __( 'Atmospheric Pressure', 'simple-location' ),
			'cloudiness'  => __( 'Cloudiness', 'simple-location' ),
			'rain'        => __( 'Rainfall', 'simple-location' ),
			'rainrate'    => __( 'Rain Rate', 'simple-location' ),
			'snowrate'    => __( 'Snow Rate', 'simple-location' ),
			'snow'        => __( 'Snowfall', 'simple-location' ),
			'snowdepth'   => __( 'Snow Depth', 'simple-location' ),
			'visibility'  => __( 'Visibility', 'simple-location' ),
			'radiation'   => __( 'Radiation', 'simple-location' ),
			'illuminance' => __( 'Illuminance', 'simple-location' ),
			'uv'          => __( 'UV Index', 'simple-location' ),
			'aqi'         => __( 'Air Quality Index', 'simple-location' ),
			'pm1_0'       => __( 'Particulate Matter 1.0', 'simple-location' ),
			'pm2_5'       => __( 'Particulate Matter 2.5', 'simple-location' ),
			'pm10_0'      => __( 'Particulate Matter 10.0', 'simple-location' ),
			'co'          => __( 'Carbon Monoxide', 'simple-location' ),
			'co2'         => __( 'Carbon Dioxide', 'simple-location' ),
			'nh3'         => __( 'Ammonia', 'simple-location' ),
			'o3'          => __( 'Ozone', 'simple-location' ),
			'pb'          => __( 'Lead', 'simple-location' ),
			'so2'         => __( 'Sulfur Dioxide', 'simple-location' ),
			'voc_index'   => __( 'VOC Index', 'simple-location' ),
			'nox_index'   => __( 'NOx Index', 'simple-location' ),
			'windspeed'   => __( 'Wind Speed', 'simple-location' ),
			'winddegree'  => __( 'Wind Degree', 'simple-location' ),
			'windgust'    => __( 'Wind Gust', 'simple-location' ),
			'code'        => __( 'Weather Condition Code', 'simple-location' ),
		);

		foreach ( $numerics as $prop => $description ) {
			$args = array(
				'type'         => 'number',
				'description'  => $description,
				'single'       => true,
				'show_in_rest' => false,
			);
			foreach ( array( 'post', 'comment', 'user', 'term' ) as $type ) {
				register_meta( $type, 'weather_' . $prop, $args );
			}
		}

		$args = array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_summary' ),
			'type'              => 'string',
			'description'       => __( 'Weather Summary', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'weather_summary', $args );
		register_meta( 'comment', 'weather_summary', $args );
		register_meta( 'user', 'weather_summary', $args );
		register_meta( 'term', 'weather_summary', $args );

		$args = array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_icon' ),
			'type'              => 'string',
			'description'       => __( 'Weather Icon', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'weather_icon', $args );
		register_meta( 'comment', 'weather_icon', $args );
		register_meta( 'user', 'weather_icon', $args );
		register_meta( 'term', 'weather_icon', $args );

		$args = array(
			'sanitize_callback' => 'sanitize_key',
			'type'              => 'string',
			'description'       => __( 'Weather Provider', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		foreach ( array( 'post', 'comment', 'user', 'term' ) as $type ) {
			register_meta( $type, 'weather_provider', $args );
		}

		// The credit of a weather station, stored with its weather.
		$credits = array(
			'attribution'     => array( 'sanitize_text_field', __( 'Weather Credit', 'simple-location' ) ),
			'attribution_url' => array( 'esc_url_raw', __( 'Weather Credit Link', 'simple-location' ) ),
		);
		foreach ( $credits as $key => $credit ) {
			$args = array(
				'sanitize_callback' => $credit[0],
				'type'              => 'string',
				'description'       => $credit[1],
				'single'            => true,
				'show_in_rest'      => false,
			);
			foreach ( array( 'post', 'comment', 'user', 'term' ) as $type ) {
				register_meta( $type, 'weather_' . $key, $args );
			}
		}
	}

	/**
	 * Returns the credit for weather from a provider, to show with the weather.
	 *
	 * With the Weather Credits setting on footer, a credit whose provider allows it is printed once in the page footer
	 * instead, and an empty string is returned. Credits the provider requires next to its weather are always returned.
	 *
	 * @since 6.1.0
	 *
	 * @param string $provider Weather provider slug.
	 * @return string HTML, or an empty string without a credit to show here.
	 */
	public static function get_attribution_markup( $provider ) {
		$weather = ( is_string( $provider ) && '' !== $provider ) ? Loc_Config::weather_provider( $provider ) : null;
		if ( ! $weather instanceof Weather_Provider ) {
			return '';
		}
		$html = $weather->get_attribution_html();
		if ( '' === $html ) {
			return '';
		}
		$credit = $weather->get_attribution();
		return self::place_credit( $provider, $html, ! empty( $credit['adjacent'] ) );
	}

	/**
	 * Returns the credit to show with some weather: its station's own credit when it has one, otherwise its provider's.
	 *
	 * @since 6.1.0
	 *
	 * @param array $weather Weather data, with 'attribution' and 'attribution_url' for a station's credit, and 'provider'.
	 * @return string HTML, or an empty string without a credit to show here.
	 */
	public static function get_weather_credit_markup( $weather ) {
		if ( ! is_array( $weather ) ) {
			return '';
		}
		// The provider that filled in readings is credited too.
		$supplement = ( isset( $weather['supplement'] ) && is_string( $weather['supplement'] ) && ( $weather['provider'] ?? '' ) !== $weather['supplement'] )
			? self::get_attribution_markup( $weather['supplement'] )
			: '';
		$text       = isset( $weather['attribution'] ) && is_string( $weather['attribution'] ) ? trim( $weather['attribution'] ) : '';
		if ( '' === $text ) {
			return self::get_attribution_markup( $weather['provider'] ?? '' ) . $supplement;
		}
		$url  = isset( $weather['attribution_url'] ) && is_string( $weather['attribution_url'] ) ? esc_url( $weather['attribution_url'] ) : '';
		$html = '' === $url ? esc_html( $text ) : sprintf( '<a href="%1$s">%2$s</a>', $url, esc_html( $text ) );
		return self::place_credit( 'station:' . md5( $text . '|' . $url ), $html, false ) . $supplement;
	}

	/**
	 * Returns a credit to show with the weather, or keeps it for the footer when the Weather Credits setting says so.
	 *
	 * @since 6.1.0
	 *
	 * @param string $key      Key of the credit, so each is printed in the footer once.
	 * @param string $html     Credit HTML, escaped.
	 * @param bool   $adjacent Whether the credit must stay next to the weather.
	 * @return string HTML, or an empty string if the credit goes in the footer.
	 */
	protected static function place_credit( $key, $html, $adjacent ) {
		if ( ! $adjacent && 'footer' === Weather_Provider::sanitize_attribution( get_option( 'sloc_weather_attribution', 'next' ) ) ) {
			self::$footer_credits[ $key ] = $html;
			if ( ! has_action( 'wp_footer', array( __CLASS__, 'footer_credits' ) ) ) {
				add_action( 'wp_footer', array( __CLASS__, 'footer_credits' ) );
			}
			return '';
		}
		return sprintf( '<span class="sloc-weather-attribution">%s</span>', $html );
	}

	/**
	 * Prints the credits of the weather shown on the page, once, in the footer.
	 *
	 * @since 6.1.0
	 */
	public static function footer_credits() {
		if ( empty( self::$footer_credits ) ) {
			return;
		}
		printf(
			'<p class="sloc-weather-attribution">%1$s %2$s</p>',
			esc_html__( 'Weather:', 'simple-location' ),
			wp_kses( implode( ' · ', self::$footer_credits ), array( 'a' => array( 'href' => array() ) ) )
		);
		self::$footer_credits = array();
	}

	/**
	 * Sanitizes a stored weather summary.
	 *
	 * @since 6.0.0
	 *
	 * @param mixed $value Summary.
	 * @return string Summary as plain text.
	 */
	public static function sanitize_summary( $value ) {
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sanitizes a stored weather icon name.
	 *
	 * @since 6.0.0
	 *
	 * @param mixed $value Icon name, such as `wi-day-sunny`.
	 * @return string Icon name usable as a class.
	 */
	public static function sanitize_icon( $value ) {
		return sanitize_html_class( (string) $value );
	}


	/**
	 * Set weather on an Object.
	 *
	 * @param string $type    Object type.
	 * @param int    $id      Object ID.
	 * @param string $key     Weather data key to set, or empty to set every property in `$weather`.
	 * @param mixed  $weather The value for `$key`, or an array of details about the weather at a location (see the registered properties) when `$key` is empty.
	 * @return WP_Error|boolean Return success or WP_Error.
	 *
	 * @since 5.0.0
	 * @since 6.0.0 Values of 0 are saved instead of deleted.
	 */
	public static function set_object_weatherdata( $type, $id, $key, $weather ) {
		if ( ! $type || ! is_numeric( $id ) ) {
			return false;
		}

		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		if ( ! empty( $key ) && ! in_array( $key, static::$properties, true ) ) {
			return false;
		}

		/**
		 * Short-circuits the return value of a weatherdata field.
		 *
		 * The dynamic portion of the hook name, `$type`, refers to the object type
		 * (post, comment, term, user, or any other type with associated weather data).
		 * Returning a non-null value will effectively short-circuit the function.
		 *
		 * Possible filter names include:
		 *
		 *  - `get_post_weatherdata`
		 *  - `get_comment_weatherdata`
		 *  - `get_term_weatherdata`
		 *  - `get_user_weatherdata`
		 *
		 * @param mixed  $value     The value to return, either a single value or an array
		 *                          of values depending on the value of `$single`. Default null.
		 * @param int    $id ID of the object weather data is for.
		 * @param string $type Type of object data is for. Accepts 'post', 'comment', 'term', 'user',
		 *                          or any other object type.
		 * @param string $key  Weather data key.
		 * @param mixed  $weather Weather data value, or all values as an array when `$key` is empty.
		 */
		$check = apply_filters( "set_{$type}_weatherdata", null, $id, $type, $key, $weather ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.

		if ( ! is_null( $check ) ) {
			return $check;
		}

		if ( $key ) {
			return update_metadata( $type, $id, 'weather_' . $key, $weather );
		}

		$weather = wp_array_slice_assoc( $weather, static::$properties );

		foreach ( $weather as $prop => $value ) {
			if ( self::has_value( $value ) ) {
				update_metadata( $type, $id, 'weather_' . $prop, $value );
			} else {
				delete_metadata( $type, $id, 'weather_' . $prop );
			}
		}
		return true;
	}


	/**
	 * Delete a Single Piece of Weather Data.
	 *
	 * @param string $type Object type.
	 * @param int    $id   Object ID.
	 * @param string $key  Weather data key.
	 *
	 * @return WP_Error|boolean Return success or WP_Error.
	 *
	 * @since 5.0.0
	 */
	public static function delete_object_weatherdata( $type, $id, $key ) {
		if ( ! $type || ! is_numeric( $id ) ) {
			return false;
		}

		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		if ( ! empty( $key ) && ! in_array( $key, static::$properties, true ) ) {
			return false;
		}

		/**
		 * Short-circuits the deleting of a field
		 *
		 * The dynamic portion of the hook name, `$type`, refers to the object type
		 * (post, comment, term, user, or any other type with associated weather data).
		 * Returning a non-null value will effectively short-circuit the function.
		 *
		 * Possible filter names include:
		 *
		 *  - `delete_post_weatherdata`
		 *  - `delete_comment_weatherdata`
		 *  - `delete_term_weatherdata`
		 *  - `delete_user_weatherdata`
		 *
		 * @param mixed  $value     The value to return, either a single value or an array
		 *                          of values depending on the value of `$single`. Default null.
		 * @param string $type Type of object data is for. Accepts 'post', 'comment', 'term', 'user',
		 *                          or any other object type with an associated meta table.
		 * @param int    $id ID of the object weatherdata data is for.
		 * @param string $key  Geo data key.
		 */
		$check = apply_filters( "delete_{$type}_weatherdata", null, $type, $id, $key ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.

		if ( ! is_null( $check ) ) {
			return $check;
		}

		return delete_metadata( $type, $id, 'weather_' . $key );
	}


	/**
	 * Get Weather Meta Data on an Object.
	 *
	 * @param string $type Object type.
	 * @param int    $id Object ID.
	 * @param string $key Optional.
	 * @return array $weather
	 *
	 * @since 5.0.0
	 * @since 6.0.0 Values of 0 are returned.
	 */
	public static function get_object_weatherdata( $type, $id, $key = '' ) {
		if ( ! $type || ! is_numeric( $id ) ) {
			return false;
		}

		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		if ( ! empty( $key ) && ! in_array( $key, static::$properties, true ) ) {
			return false;
		}
		/**
		 * Short-circuits the return value of a weatherdata field.
		 *
		 * The dynamic portion of the hook name, `$type`, refers to the object type
		 * (post, comment, term, user, or any other type with associated weather data).
		 * Returning a non-null value will effectively short-circuit the function.
		 *
		 * Possible filter names include:
		 *
		 *  - `get_post_weatherdata`
		 *  - `get_comment_weatherdata`
		 *  - `get_term_weatherdata`
		 *  - `get_user_weatherdata`
		 *
		 * @param mixed  $value     The value to return, either a single value or an array
		 *                          of values depending on the value of `$single`. Default null.
		 * @param int    $object_id ID of the object weather data is for.
		 * @param string $key  Weather data key.
		 * @param string $object_type Type of object data is for. Accepts 'post', 'comment', 'term', 'user',
		 *                          or any other object type with an associated meta table.
		 */
		$check = apply_filters( "get_{$type}_weatherdata", null, $id, $key, $type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.

		if ( null !== $check ) {
			return $check;
		}

		self::migrate_weather( $type, $id );

		if ( $key ) {
			return get_metadata( $type, $id, 'weather_' . $key, true );
		}

		$weather = array();

		foreach ( static::$properties as $prop ) {
			$weather[ $prop ] = get_metadata( $type, $id, 'weather_' . $prop, true );
		}

		return array_filter( $weather, array( __CLASS__, 'has_value' ) );
	}

	/**
	 * Whether a weather value is set.
	 *
	 * Unlike empty(), this treats 0 and '0' as set, since they are real readings (0 °C, no wind, no rain).
	 *
	 * @since 6.0.0
	 *
	 * @param mixed $value Value.
	 * @return bool True unless the value is null, '', false or an empty array.
	 */
	public static function has_value( $value ) {
		return null !== $value && '' !== $value && false !== $value && array() !== $value;
	}

	/**
	 * Migrate Meta Data from Array to Individual Keys
	 *
	 * @param string $type Object type.
	 * @param int    $id Object ID.
	 * @return bool True if old weather was migrated.
	 *
	 * @since 5.0.0
	 * @since 6.0.0 Returns whether the weather was migrated.
	 */
	public static function migrate_weather( $type, $id ) {
		$weather = get_metadata( $type, $id, 'geo_weather', true );
		if ( ! $weather || ! is_array( $weather ) ) {
			return false;
		}

		if ( array_key_exists( 'wind', $weather ) ) {
			$w = array(
				'windgust'   => $weather['wind']['gust'] ?? null,
				'winddegree' => $weather['wind']['degree'] ?? null,
				'windspeed'  => $weather['wind']['speed'] ?? null,
			);
			$w = array_filter( $w, array( __CLASS__, 'has_value' ) );
			unset( $weather['wind'] );
			$weather = array_merge( $w, $weather );
		}

		if ( self::set_object_weatherdata( $type, $id, '', $weather ) ) {
			delete_metadata( $type, $id, 'geo_weather' );
			return true;
		}
		return false;
	}


	/**
	 * Moves snow depth stored as snowfall to snowdepth, for posts and comments.
	 *
	 * Before 6.2.0 the Meteostat provider stored its snow depth, in millimeters, as snowfall in centimeters, so ten
	 * times too large, and the Xweather provider stored its snow depth as snowfall. Weather from them, which says so in
	 * its provider, has its snow moved to snowdepth, and Meteostat's divided by ten. A snowdepth already set is kept.
	 *
	 * @since 6.2.0
	 *
	 * @param int $limit Optional. Most posts, and most comments, to process. Default -1, all.
	 * @return int The larger of the number of posts and of comments processed, so a full batch means there may be more.
	 */
	public static function migrate_snow_depth( $limit = -1 ) {
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => 'weather_snow',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => 'weather_provider',
				'value'   => array( 'meteostat', 'aeris' ),
				'compare' => 'IN',
			),
		);
		$posts    = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => (int) $limit,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- A one-time migration, in batches.
				'meta_query'     => $meta_query,
			)
		);
		$comments = get_comments(
			array(
				'number'     => ( $limit > 0 ) ? (int) $limit : '',
				'fields'     => 'ids',
				'status'     => 'all',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- A one-time migration, in batches.
				'meta_query' => $meta_query,
			)
		);
		foreach ( $posts as $post_id ) {
			self::move_snow_depth( 'post', (int) $post_id );
		}
		foreach ( $comments as $comment_id ) {
			self::move_snow_depth( 'comment', (int) $comment_id );
		}
		return max( count( $posts ), count( $comments ) );
	}

	/**
	 * Moves one object's snow depth from snowfall to snowdepth. See migrate_snow_depth().
	 *
	 * @since 6.2.0
	 *
	 * @param string $type Object type: post or comment.
	 * @param int    $id   Object ID.
	 */
	protected static function move_snow_depth( $type, $id ) {
		$snow     = get_metadata( $type, $id, 'weather_snow', true );
		$provider = get_metadata( $type, $id, 'weather_provider', true );
		$existing = get_metadata( $type, $id, 'weather_snowdepth', true );
		if ( is_numeric( $snow ) && ! is_numeric( $existing ) ) {
			$depth = ( 'meteostat' === $provider ) ? (float) $snow / 10 : (float) $snow;
			update_metadata( $type, $id, 'weather_snowdepth', round( $depth, 2 ) );
		}
		delete_metadata( $type, $id, 'weather_snow' );
	}

	/**
	 * Migrate Meta Data from Array to Individual Keys
	 *
	 * Covers posts of every type and status, and comments.
	 *
	 * @since 5.0.0
	 * @since 6.0.0 Covers every post type and status, takes a limit, and returns counts.
	 *
	 * @param int $limit Optional. Most posts, and most comments, to process. Default -1, all.
	 * @return array {
	 *     @type int $found    Posts and comments with old weather that were processed.
	 *     @type int $migrated How many of them were migrated.
	 * }
	 */
	public static function bulk_migrate_weather( $limit = -1 ) {
		$limit  = (int) $limit;
		$return = array(
			'found'    => 0,
			'migrated' => 0,
		);
		$posts  = get_posts(
			array(
				'fields'         => 'ids',
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => $limit,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Finds weather in the old format to migrate.
				'meta_key'       => 'geo_weather',
				'meta_compare'   => 'EXISTS',
			)
		);
		foreach ( $posts as $post ) {
			++$return['found'];
			if ( self::migrate_weather( 'post', $post ) ) {
				++$return['migrated'];
			}
		}

		$comments = get_comments(
			array(
				'fields'       => 'ids',
				'status'       => 'any',
				'number'       => max( 0, $limit ),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Finds weather in the old format to migrate.
				'meta_key'     => 'geo_weather',
				'meta_compare' => 'EXISTS',
			)
		);
		foreach ( $comments as $comment ) {
			++$return['found'];
			if ( self::migrate_weather( 'comment', $comment ) ) {
				++$return['migrated'];
			}
		}
		return $return;
	}

	/**
	 * Does this object have weather data.
	 *
	 * @param string $type Object type.
	 * @param int    $id   Object ID.
	 * @return boolean True if the object has weather data.
	 *
	 * @since 1.0.0
	 * @since 6.1.0 The weather's provider or credit alone isn't weather data.
	 */
	public static function has_weather( $type, $id ) {
		$weather = self::get_object_weatherdata( $type, $id );
		return is_array( $weather ) && ! empty( array_diff_key( $weather, array_flip( self::SOURCE_PROPERTIES ) ) );
	}

	/**
	 * Returns the weather markup for an object.
	 *
	 * @since 3.3.0
	 * @since 6.1.0 Followed by the weather provider's credit, unless the Weather Credits setting puts it in the footer.
	 * @since 6.2.0 Added the 'text' style.
	 * @since 6.2.0 The 'complete' style adds the air quality index, as get_the_aqi() shows it.
	 * @since 6.2.0 Enqueues the front-end stylesheet; see Simple_Location_Plugin::enqueue_styles().
	 *
	 * @param string     $type Object type.
	 * @param int        $id   Object ID.
	 * @param array|null $args {
	 *     Optional. Display arguments.
	 *
	 *     @type string   $style         'simple' (icon, temperature and summary), 'complete' (adds humidity,
	 *                                   cloudiness, visibility and the AQI), 'graphic' (icon only) or 'text' (plain text
	 *                                   from get_the_weather_text(), without markup; the other arguments are
	 *                                   ignored). Default 'simple'.
	 *     @type string   $description   Label. Default 'Weather: '.
	 *     @type string[] $wrapper-class Classes for the wrapper. Default 'sloc-weather'.
	 *     @type string   $wrapper-type  Wrapper element. Default 'p'.
	 * }
	 * @return string Weather markup, or an empty string without weather data.
	 */
	public static function get_the_weather( $type, $id, $args = null ) {
		Simple_Location_Plugin::enqueue_styles();
		$weather  = self::get_object_weatherdata( $type, $id );
		$defaults = array(
			'style'         => 'simple', // Options are simple, complete, graphic (only)
			'description'   => __( 'Weather: ', 'simple-location' ),
			'wrapper-class' => array( 'sloc-weather' ), // Class or classes to wrap the weather in
			'wrapper-type'  => 'p', // HTML type to wrap the weather in
		);
		$args     = wp_parse_args( $args, $defaults );
		if ( 'text' === $args['style'] ) {
			return self::get_the_weather_text( $type, $id );
		}
		// The provider alone isn't weather.
		if ( ! is_array( $weather ) || empty( array_diff_key( $weather, array_flip( self::SOURCE_PROPERTIES ) ) ) ) {
			return '';
		}

		if ( isset( $weather['code'] ) ) {
			$weather['icon']    = self::weather_condition_icons( $weather['code'] );
			$weather['summary'] = self::weather_condition_codes( $weather['code'] );
		}

		if ( empty( $weather['icon'] ) ) {
			$weather['icon'] = 'wi-thermometer';
		}

		$class    = implode( ' ', $args['wrapper-class'] );
		$return   = array( PHP_EOL );
		$return[] = Weather_Provider::get_icon( $weather['icon'], $weather['summary'] ?? null );
		if ( 'graphic' !== $args['style'] ) {
			$return[] = self::get_the_temperature( $weather ) . PHP_EOL;
			if ( ! empty( $weather['summary'] ) ) {
				$return[] = sprintf( '<span class="p-weather">%1$s</span>', $weather['summary'] );
			}
		}
		if ( 'complete' === $args['style'] ) {
			$return[] = self::get_weather_extras( $weather );
			$return[] = self::get_the_aqi( $type, $id );
		}
		if ( isset( $weather['station_id'] ) ) {
			if ( isset( $weather['name'] ) ) {
				$return[] = sprintf( '<p>%1$s</p>', $weather['name'] );
			}
		}
		$return[] = self::get_weather_credit_markup( $weather );
		return sprintf( '<%1$s class="%2$s">%3$s</%1$s>', $args['wrapper-type'], esc_attr( $class ), implode( PHP_EOL, array_filter( $return ) ) );
	}

	/**
	 * Returns the air quality index of an object, shown with its category's name and color.
	 *
	 * The category follows the US EPA scale; see Weather_Info_Trait::aqi_category(). The badge has the category's name
	 * as text, so it isn't shown by color alone.
	 *
	 * @since 6.2.0
	 * @since 6.2.0 Enqueues the front-end stylesheet; see Simple_Location_Plugin::enqueue_styles().
	 *
	 * @param string $type Object type.
	 * @param int    $id   Object ID.
	 * @param array  $args {
	 *     Optional. Display arguments.
	 *
	 *     @type string $style 'badge' (the index and category on the category's color) or 'text' (plain text, such as
	 *                         "AQI 72 Moderate", not escaped). Default 'badge'.
	 *     @type string $label Label before the index. Default 'AQI'.
	 * }
	 * @return string Markup or text, or an empty string without an air quality index.
	 */
	public static function get_the_aqi( $type, $id, $args = array() ) {
		Simple_Location_Plugin::enqueue_styles();
		$args     = wp_parse_args(
			$args,
			array(
				'style' => 'badge',
				'label' => __( 'AQI', 'simple-location' ),
			)
		);
		$aqi      = self::get_object_weatherdata( $type, $id, 'aqi' );
		$category = self::aqi_category( $aqi );
		if ( ! $category ) {
			return '';
		}
		$index = (int) round( (float) $aqi );
		if ( 'text' === $args['style'] ) {
			return trim( sprintf( '%1$s %2$d %3$s', $args['label'], $index, $category['name'] ) );
		}
		return sprintf(
			'<span class="sloc-aqi sloc-aqi-%1$s" style="background-color:%2$s;color:%3$s" title="%4$s"><span class="sloc-aqi-label">%5$s</span> <data class="p-aqi" value="%6$d">%6$d</data> <span class="sloc-aqi-category">%7$s</span></span>',
			esc_attr( $category['slug'] ),
			esc_attr( $category['color'] ),
			esc_attr( $category['text_color'] ),
			/* translators: 1: Air quality index 2: Its category, such as Moderate. */
			esc_attr( sprintf( __( 'Air Quality Index %1$d: %2$s', 'simple-location' ), $index, $category['name'] ) ),
			esc_html( $args['label'] ),
			$index,
			esc_html( $category['name'] )
		);
	}

	/**
	 * Returns the weather for an object as plain text: the temperature and the summary, such as "72°F Clear".
	 *
	 * For places that can't show the icon or markup, such as email and plain-text feeds. Like the other weather
	 * output it has no coordinates or station name, so it is the same for every location visibility.
	 *
	 * @since 6.2.0
	 *
	 * @param string $type Object type.
	 * @param int    $id   Object ID.
	 * @return string Plain text, not escaped, or an empty string without a temperature or summary.
	 */
	public static function get_the_weather_text( $type, $id ) {
		$weather = self::get_object_weatherdata( $type, $id );
		if ( ! is_array( $weather ) ) {
			return '';
		}
		if ( isset( $weather['code'] ) ) {
			$weather['summary'] = self::weather_condition_codes( $weather['code'] );
		}
		$text = array();
		if ( isset( $weather['temperature'] ) && is_numeric( $weather['temperature'] ) ) {
			$units = $weather['units'] ?? null;
			if ( ! $units ) {
				$units = get_query_var( 'sloc_units', get_option( 'sloc_measurements' ) );
			}
			$imperial = ( 'imperial' === $units );
			if ( $imperial ) {
				$weather = Weather_Provider::metric_to_imperial( $weather );
			}
			$names  = Weather_Provider::get_names( 'temperature', $imperial );
			$unit   = is_array( $names ) ? html_entity_decode( (string) $names['unit'], ENT_QUOTES, 'UTF-8' ) : '';
			$text[] = round( (float) $weather['temperature'] ) . $unit;
		}
		if ( ! empty( $weather['summary'] ) && is_string( $weather['summary'] ) ) {
			$text[] = trim( html_entity_decode( wp_strip_all_tags( $weather['summary'] ), ENT_QUOTES, 'UTF-8' ) );
		}
		$text = implode( ' ', array_filter( $text, 'strlen' ) );

		/**
		 * Filters the weather of an object as plain text.
		 *
		 * @since 6.2.0
		 *
		 * @param string $text    Plain text, such as "72°F Clear", or an empty string.
		 * @param array  $weather Weather data.
		 * @param string $type    Object type.
		 * @param int    $id      Object ID.
		 */
		return (string) apply_filters( 'sloc_weather_text', $text, $weather, $type, $id );
	}

	/**
	 * Returns the temperature markup, in the weather's units or the site's measurement setting.
	 *
	 * @since 3.7.1
	 *
	 * @param array $weather Weather data, in metric units.
	 * @return string|null Temperature markup, or null without a temperature.
	 */
	private static function get_the_temperature( $weather ) {
		if ( ! isset( $weather['temperature'] ) ) {
			return null;
		}
		$units = $weather['units'] ?? null;
		if ( ! $units ) {
			$units = get_query_var( 'sloc_units', get_option( 'sloc_measurements' ) );
		}
		if ( 'imperial' === $units ) {
			$weather = Weather_Provider::metric_to_imperial( $weather );
		}

		return Weather_Provider::markup_value(
			'temperature',
			$weather['temperature'],
			array(
				'container' => 'span',
				'round'     => true,
				'units'     => $units,
			)
		);
	}

	/**
	 * Returns a list of humidity, cloudiness and visibility, in the weather's units or the site's measurement setting.
	 *
	 * @since 3.7.1
	 *
	 * @param array $weather Weather data, in metric units.
	 * @return string List markup, or an empty string if none of them are set.
	 */
	private static function get_weather_extras( $weather ) {
		$units = $weather['units'] ?? null;
		if ( ! $units ) {
			$units = get_query_var( 'sloc_units', get_option( 'sloc_measurements' ) );
		}
		if ( 'imperial' === $units ) {
			$weather = Weather_Provider::metric_to_imperial( $weather );
		}

		$return = array();
		foreach ( array( 'humidity', 'cloudiness', 'visibility' ) as $param ) {
			if ( ! isset( $weather[ $param ] ) ) {
				continue;
			}
			$return[] = Weather_Provider::markup_value(
				$param,
				$weather[ $param ],
				array(
					'units' => $units,
				)
			);
		}
		if ( empty( $return ) ) {
			return '';
		}
		return '<ul>' . implode( '', $return ) . '</ul>';
	}

	/**
	 * Looks up the current weather at coordinates with the weather providers, in order.
	 *
	 * @since 3.5.0
	 * @since 6.1.0 Tries the fallback weather providers in order. See Sloc_Provider_Chain.
	 *
	 * @param float    $lat        Latitude.
	 * @param float    $lng        Longitude.
	 * @param int|null $cache_time Seconds to cache the result. Optional; defaults to the provider's setting.
	 * @return array|WP_Error Weather conditions, with an icon and summary when the provider gives a condition code.
	 */
	public static function get_weather_data( $lat, $lng, $cache_time = null ) {
		if ( ! Loc_Config::weather_provider() ) {
			return self::no_provider_error();
		}
		$weather = Sloc_Provider_Chain::weather( $lat, $lng, null, $cache_time );

		if ( is_array( $weather ) && isset( $weather['code'] ) ) {
			$weather['icon']    = self::weather_condition_icons( $weather['code'] );
			$weather['summary'] = self::weather_condition_codes( $weather['code'] );
		}
		return $weather;
	}

	/**
	 * Returns the error for a lookup with no weather provider set.
	 *
	 * @since 6.0.0
	 *
	 * @return WP_Error
	 */
	private static function no_provider_error() {
		return new WP_Error( 'no_provider', __( 'No Weather Provider Set', 'simple-location' ) );
	}

	/**
	 * Looks up the current weather at a user's location.
	 *
	 * @since 3.5.0
	 *
	 * @param int|WP_User $user       User ID or object.
	 * @param int|null    $cache_time Seconds to cache the result. Optional; defaults to the provider's setting.
	 * @return array|WP_Error|string Weather conditions, or an empty string if there is no user or user location.
	 */
	public static function get_weather_by_user( $user, $cache_time = null ) {
		if ( is_numeric( $user ) && 0 !== $user ) {
			$user = new WP_User( $user );
		}
		if ( ! $user instanceof WP_User ) {
			return '';
		}
		$loc = get_user_geodata( $user->ID );
		if ( ! is_array( $loc ) || ! isset( $loc['latitude'] ) ) {
			return '';
		}
		return self::get_weather_by_location( $loc['latitude'], $loc['longitude'], $cache_time );
	}

	/**
	 * Looks up the current weather at coordinates. Alias of get_weather_data().
	 *
	 * @since 3.5.0
	 *
	 * @param float    $lat        Latitude.
	 * @param float    $lng        Longitude.
	 * @param int|null $cache_time Seconds to cache the result. Optional; defaults to the provider's setting.
	 * @return array|WP_Error Weather conditions, with an icon and summary when the provider gives a condition code.
	 */
	public static function get_weather_by_location( $lat, $lng, $cache_time = null ) {
		return self::get_weather_data( $lat, $lng, $cache_time );
	}

	/**
	 * Looks up the current weather at a weather station.
	 *
	 * @since 3.5.0
	 * @since 6.1.0 Looks up with a copy of the provider, leaving the shared one as it was (#681).
	 *
	 * @param string      $station    Station ID.
	 * @param string|null $provider   Weather provider slug. Optional; defaults to the active provider.
	 * @param int|null    $cache_time Seconds to cache the result. Optional; defaults to the provider's setting.
	 * @return array|WP_Error Weather conditions, with an icon and summary when the provider gives a condition code.
	 */
	public static function get_weather_by_station( $station, $provider = null, $cache_time = null ) {
		$provider = Loc_Config::weather_provider( $provider );
		if ( ! $provider ) {
			return self::no_provider_error();
		}
		$provider = clone $provider;
		$provider->set( array( 'station_id' => $station ) );

		if ( is_numeric( $cache_time ) ) {
			$provider->set_cache_time( $cache_time );
		}

		$weather = $provider->get_conditions();
		if ( is_wp_error( $weather ) ) {
			return $weather;
		}

		if ( isset( $weather['code'] ) ) {
			$weather['icon']    = self::weather_condition_icons( $weather['code'] );
			$weather['summary'] = self::weather_condition_codes( $weather['code'] );
		}
		return $weather;
	}

	/**
	 * Generates Pulldown list of Weather Statuses.
	 *
	 * @since 5.0.0
	 *
	 * @param string  $code Condition code to be selected.
	 * @param boolean $echo Echo or Return.
	 * @return string|void The options, or nothing when they are echoed.
	 */
	public static function code_select( $code, $echo = false ) {
		$choices = self::weather_condition_codes( null );
		$return  = '';
		foreach ( $choices as $value => $text ) {
			$return .= sprintf( '<option value="%1s" %2s>%3s</option>', esc_attr( $value ), selected( $code, $value, false ), esc_html( $text ) );
		}
		if ( ! $echo ) {
			return $return;
		}
		echo wp_kses(
			$return,
			array(
				'option' => array(
					'selected' => array(),
					'value'    => array(),
				),
			)
		);
	}
}

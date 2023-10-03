<?php
/**
 * Weather Metadata
 *
 * Registers weather metadata and supplies functions to assist in manipulating it.
 *
 * @package Simple Location
 */

add_action( 'init', array( 'Sloc_Weather_Data', 'init' ) );

/**
 * Handles Weather Functionality for WordPress objects.
 *
 * @since 1.0.0
 */
class Sloc_Weather_Data {
	use Weather_Info_Trait;

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
		'windspeed',
		'winddegree',
		'windgust',
		'summary',
		'icon',
		'code',
	);

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
			'snow'        => __( 'Snowfall', 'simple-location' ),
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
			'windspeed'   => __( 'Wind Speed', 'simple-location' ),
			'winddegree'  => __( 'Wind Degree', 'simple-location' ),
			'windgust'    => __( 'Wind Gust', 'simple-location' ),
			'code'        => __( 'Weather Condition Code', 'simple-location' ),
		);

		foreach ( $numerics as $prop => $description ) {
			$args = array(
				'type'         => 'number',
				'description'  => 'Altitude',
				'single'       => true,
				'show_in_rest' => false,
			);
			foreach ( array( 'post', 'comment', 'user', 'term' ) as $type ) {
				register_meta( $type, 'weather_' . $prop, $args );
			}
		}

		$args = array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_text_field' ),
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
			'sanitize_callback' => array( __CLASS__, 'esc_attr' ),
			'type'              => 'string',
			'description'       => __( 'Weather Icon', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'weather_icon', $args );
		register_meta( 'comment', 'weather_icon', $args );
		register_meta( 'user', 'weather_icon', $args );
		register_meta( 'term', 'weather_icon', $args );
	}


	/**
	 * Set weather on an Object.
	 *
	 * @param string $type
	 * @param int    $id
	 * @param string $key
	 * @param array  $weather  An array of details about the weather at a location...see registered properties.
	 * @return WP_Error|boolean Return success or WP_Error.
	 *
	 * @since 5.0.0
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
		 * @param mixed $value Weather data value.
		 */
		$check = apply_filters( "set_{$type}_weatherdata", null, $id, $type, $key, $weather );

		if ( $key ) {
			return update_metadata( $type, $id, 'weather_' . $key, $weather );
		}

		$weather = wp_array_slice_assoc( $weather, static::$properties );

		foreach ( $weather as $prop => $value ) {
			if ( ! empty( $value ) ) {
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
	 * @param string $type
	 * @param int    $id
	 * @param string $key
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
		$check = apply_filters( "delete_{$type}_weatherdata", null, $type, $id, $key );

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
		$check = apply_filters( "get_{$type}_weatherdata", null, $id, $key, $type );

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

		return array_filter( $weather );
	}

	/**
	 * Migrate Meta Data from Array to Individual Keys
	 *
	 * @param string $type Object type.
	 * @param int    $id Object ID.
	 * @return array $weather
	 *
	 * @since 5.0.0
	 */
	public static function migrate_weather( $type, $id ) {
		$weather = get_metadata( $type, $id, 'geo_weather', true );
		if ( ! $weather ) {
			return;
		}

		if ( array_key_exists( 'wind', $weather ) ) {
			$w = array(
				'windgust'   => ifset( $weather['wind']['gust'] ),
				'winddegree' => ifset( $weather['wind']['degree'] ),
				'windspeed'  => ifset( $weather['wind']['speed'] ),
			);
			$w = array_filter( $w );
			unset( $weather['wind'] );
			$weather = array_merge( $w, $weather );
		}

		if ( self::set_object_weatherdata( $type, $id, '', $weather ) ) {
			delete_metadata( $type, $id, 'geo_weather' );
		}
	}


	/**
	 * Migrate Meta Data from Array to Individual Keys
	 *
	 * @return array $weather
	 *
	 * @since 5.0.0
	 */
	public static function bulk_migrate_weather() {
		$posts = get_posts(
			array(
				'fields'       => 'ids',
				'meta_key'     => 'geo_weather',
				'meta_compare' => 'EXISTS',
			)
		);
		foreach ( $posts as $post ) {
			self::migrate_weather( 'post', $post );
		}

		$comments = get_comments(
			array(
				'fields'       => 'ids',
				'meta_key'     => 'geo_weather',
				'meta_compare' => 'EXISTS',
			)
		);
		foreach ( $comments as $comment ) {
			self::migrate_weather( 'comment', $comment );
		}
	}

	/**
	 * Does this object have weather data.
	 *
	 * @param mixed $object Object type.
	 * @return WP_Error|boolean Return success or WP_Error.
	 *
	 * @since 1.0.0
	 */
	public static function has_weather( $type, $id ) {
		return is_array( self::get_object_weatherdata( $type, $id ) );
	}

	public static function get_the_weather( $type, $id, $args = null ) {
		$weather  = self::get_object_weatherdata( $type, $id );
		$defaults = array(
			'style'         => 'simple', // Options are simple, complete, graphic (only)
			'description'   => __( 'Weather: ', 'simple-location' ),
			'wrapper-class' => array( 'sloc-weather' ), // Class or classes to wrap the weather in
			'wrapper-type'  => 'p', // HTML type to wrap the weather in
		);
		$args     = wp_parse_args( $args, $defaults );
		if ( ! is_array( $weather ) || empty( $weather ) ) {
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
		$return[] = Weather_Provider::get_icon( $weather['icon'], ifset( $weather['summary'] ) );
		if ( 'graphic' !== $args['style'] ) {
			$return[] = self::get_the_temperature( $weather ) . PHP_EOL;
			if ( ! empty( $weather['summary'] ) ) {
				$return[] = sprintf( '<span class="p-weather">%1$s</span>', $weather['summary'] );
			}
		}
		if ( 'complete' === $args['style'] ) {
			$return[] = self::get_weather_extras( $weather );
		}
		if ( isset( $weather['station_id'] ) ) {
			if ( isset( $weather['name'] ) ) {
				$return[] = sprintf( '<p>%1$s</p>', $weather['name'] );
			}
		}
		return sprintf( '<%1$s class="%2$s">%3$s</%1$s>', $args['wrapper-type'], esc_attr( $class ), implode( PHP_EOL, array_filter( $return ) ) );
	}

	private static function get_the_temperature( $weather ) {
		if ( ! isset( $weather['temperature'] ) ) {
			return null;
		}
		$units = ifset( $weather['units'] );
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

	private static function get_weather_extras( $weather ) {
		$units = ifset( $weather['units'] );
		if ( ! $units ) {
			$units = get_query_var( 'sloc_units', get_option( 'sloc_measurements' ) );
		}
		if ( 'imperial' === $units ) {
			$weather = Weather_Provider::metric_to_imperial( $weather );
		}

		$return = array();
		foreach ( array( 'humidity', 'cloudiness', 'visibility' ) as $param ) {
			$return[] = Weather_Provider::markup_value(
				$param,
				$weather[ $param ],
				array(
					'units' => $units,
				)
			);
		}
		return '<ul>' . implode( '', $return ) . '</ul>';
	}

	public static function get_weather_data( $lat, $lng, $cache_time = null ) {
		$provider = Loc_Config::weather_provider();
		$provider->set( $lat, $lng );
		if ( is_numeric( $cache_time ) ) {
			$provider->set_cache_time( $cache_time );
		}

		$weather = $provider->get_conditions();

		if ( isset( $weather['code'] ) ) {
			$weather['icon']    = self::weather_condition_icons( $weather['code'] );
			$weather['summary'] = self::weather_condition_codes( $weather['code'] );
		}
		return $weather;
	}

	public static function get_weather_by_user( $user, $cache_time = null ) {
		if ( is_numeric( $user ) && 0 !== $user ) {
			$user = new WP_User( $user );
		}
		if ( ! $user instanceof WP_User ) {
			return '';
		}
		$loc = get_geodata( $user );
		if ( ! is_array( $loc ) || ! isset( $loc['latitude'] ) ) {
			return '';
		}
		return self::get_weather_by_location( $loc['latitude'], $loc['longitude'], $cache_time );
	}

	public static function get_weather_by_location( $lat, $lng, $cache_time = null ) {
		return self::get_weather_data( $lat, $lng, $cache_time );
	}

	public static function get_weather_by_station( $station, $provider = null, $cache_time = null ) {
		$provider = Loc_Config::weather_provider( $provider );
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
	 * @param string  $icon Icon to be Selected.
	 * @param boolean $echo Echo or Return.
	 * @return string Select Option. Optional.
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

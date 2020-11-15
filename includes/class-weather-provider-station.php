<?php

class Weather_Provider_Station extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		$this->name   = __( 'Custom Station Provider', 'simple-location' );
		$this->slug   = 'station';
		$this->region = false;
		$option       = get_option( 'sloc_weather_provider' );
		// if ( 'station' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		// }
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_stations', // option group.
			'sloc_stations', // option name.
			array(
				'type'         => 'string',
				'description'  => 'Stations',
				'show_in_rest' => false,
				'default'      => array(),
			)
		);
		register_setting(
			'sloc_providers', // option group
			'sloc_station_url', // option name
			array(
				'type'         => 'string',
				'description'  => 'Custom Station URL',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
	}

	public static function admin_init() {
		add_settings_section(
			'sloc_stations',
			__( 'Custom Station Data', 'simple-location' ),
			array( static::class, 'stations_section' ),
			'sloc_stations'
		);
		add_settings_field(
			'sloc_stations', // id.
			__( 'Stations', 'simple-location' ), // setting title.
			array( static::class, 'stations_callback' ), // display callback.
			'sloc_stations', // option group.
			'sloc_stations', // settings section.
			array()
		);
		add_settings_field(
			'sloc_station_url', // id
			__( 'Custom Station URL', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_stations', // settings page
			'sloc_stations', // settings sectiona
			array(
				'label_for' => 'sloc_station_url',
			)
		);
	}

	/**
	 * Callback for the stations settings.
	 *
	 * @since 4.0.0
	 */
	public static function stations_section() {
		esc_html_e(
			'Enter Station ID and URL for each station. The URL may contain a query string application key.',
			'simple-location'
		);
	}

	/**
	 * Echoes the form for the station settings.
	 *
	 * @param array $args Arguments.
	 */
	public static function stations_callback( $args ) {
		$name   = 'sloc_stations';
		$custom = get_option( $name );
		foreach ( $custom as $key => $value ) {
			$custom[ $key ] = array_filter( $value );
		}
		$custom = array_filter( $custom );
		esc_html_e( 'Enter ID and URL for this Station', 'simple-location' );
		printf( '<ul id="location-stations">' );
		if ( empty( $custom ) ) {
			self::station_inputs( '0' );

		} else {
			foreach ( $custom as $key => $value ) {
				self::station_inputs( $key, $value );
			}
		}
		printf( '</ul>' );
		printf( '<span class="button button-primary" id="add-location-stations-button">%s</span>', esc_html__( 'Add', 'simple-location' ) );
		printf( '<span class="button button-secondary" id="delete-location-stations-button">%s</span>', esc_html__( 'Remove', 'simple-location' ) );
	}

	/**
	 * If the array key exists return it, otherwise return empty string.
	 *
	 * @param array      $array Array.
	 * @param int|string $key Key.
	 * @return mixed The value or empty string.
	 */
	public static function key( $array, $key ) {
		if ( array_key_exists( $key, $array ) ) {
			return $array[ $key ];
		}
		return '';
	}

	/**
	 * Echoes an entry of the station form.
	 *
	 * @param int   $int Array key.
	 * @param array $value {
	 *  The fields of the zone.
	 *  @type string $name Zone Name.
	 *  @type float $latitude Latitude.
	 *  @type float $longitude Longitude.
	 *  @type foat $radius Radius.
	 * }
	 */
	private static function station_inputs( $int, $value = '' ) {
		$output = '<input type="text" name="%1$s[%2$s][%3$s]" id="%4$s" value="%5$s" placeholder="%6$s" />';
		$name   = 'sloc_stations';
		echo '<li>';
		// phpcs:disable
		printf( $output, $name, $int, 'id', esc_attr( $name ), esc_attr( self::key( $value, 'id' ) ), esc_html__( 'Station ID', 'simple-location' ) );
		printf( $output, $name, $int, 'url', esc_attr( $name ), esc_url( self::key( $value, 'url' ) ), esc_html__( 'URL', 'simple-location' ) );
		// phpcs:enable
		echo '</li>';
	}

	public function set( $lat, $lng = null, $alt = null ) {
		if ( ! $lng && is_array( $lat ) ) {
			if ( isset( $lat['station_id'] ) ) {
				$this->station_id = trim( $lat['station_id'] );
			}
		}
			parent::set( $lat, $lng, $alt );
	}

	/**
	 * Does This Provider Offer Station Data.
	 *
	 * @return boolean If supports station data return true.
	 */
	public function is_station() {
		return true;
	}

	/**
	 * Return array of current conditions
	 *
	 * @return array Current Conditions in Array
	 */
	public function get_conditions( $time = null ) {
		$return = array();
		if ( ! empty( $this->station_id ) ) {
			return self::get_station_data();
		}
		return new WP_Error( 'failed', __( 'Failure', 'simple-location' ) );
	}


	/*
	 * Get Station Data.
	 *
	 * The JSON from the API return must match the current conditions return used by the plugin and specified in the parent class.
	 *
	 */
	public function get_station_data() {
		$return   = array();
		$stations = get_option( 'sloc_stations' );
		$endpoint = null;
		foreach ( $stations as $station ) {
			if ( $this->station_id === trim( $station['id'] ) ) {
				$endpoint = trim( $station['url'] );
				break;
			}
		}
		if ( ! $endpoint ) {
			return $return;
		}
		$return = $this->fetch_json( $endpoint, array() );
		if ( ! is_wp_error( $return ) ) {
			$return = array_filter( $this->extra_data( $return ) );
		}
		return $return;
	}

	private function icon_map( $id ) {
		return null;
	}

}

register_sloc_provider( new Weather_Provider_Station() );

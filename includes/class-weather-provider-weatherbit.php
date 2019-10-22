<?php

class Weather_Provider_Weatherbit extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'Weatherbit', 'simple-location' );
		$this->slug = 'weatherbit';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_weatherbit_api' );
		}
		$args['cache_key'] = '';

		add_action( 'init', array( get_called_class(), 'init' ) );
		add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_weatherbit_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'Weatherbit API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);

	}

	public static function admin_init() {
		add_settings_field(
			'weatherbitapi', // id
			__( 'Weatherbit API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_weatherbit_api',
			)
		);
	}

	public function is_station() {
		return false;
	}

	/**
	 * Return array of current conditions
	 *
	 * @return array Current Conditions in Array
	 */
	public function get_conditions() {
		/* if ( empty( $this->api ) ) {
			return array();
		} */
		$return = array();
		if ( $this->latitude && $this->longitude ) {
			/* if ( $this->cache_key ) {
				$conditions = get_transient( $this->cache_key . '_' . md5( $this->latitude . ',' . $this->longitude ) );
				if ( $conditions ) {
					return $conditions;
				}
			} */
			$data = array(
				'key' => $this->api,
				'lat'      => $this->latitude,
				'lon'      => $this->longitude,
				'units'      => 'M',

			);
			$url  = 'https://api.weatherbit.io/v2.0/current';
			$url  = add_query_arg( $data, $url );
			error_log( $url );
			$args = array(
				'headers'             => array(
					'Accept' => 'application/json',
				),
				'timeout'             => 10,
				'limit_response_size' => 1048576,
				'redirection'         => 1,
				// Use an explicit user-agent for Simple Location
				'user-agent'          => 'Simple Location for WordPress',
			);
			$response = wp_remote_get( $url, $args );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response, true );
			if ( WP_DEBUG ) {
				$return['raw'] = $response;
			}
			if ( ! isset( $response['data'] ) ) {
				return $return;
			}
			$response              = $response['data'][0];
			$return['temperature'] = ifset( $response['temp'] );
			$return['humidity'] = ifset( $response['rh'] );
			$return['pressure'] = ifset( $response['pres'] );
			$return['cloudiness'] = ifset( $response['clouds'] );

			$return['wind']           = array();
			$return['wind']['speed']  = ifset( $response['wind_spd'] );
			$return['wind']['degree'] = ifset( $response['wind_dir'] );
			$return['wind']           = array_filter( $return['wind'] );
			$return['rain']           = ifset( $response['precip'] );
			$return['visibility']     = ifset( $response['vis'] );
			$return['airquality']     = ifset( $response['aqi'] );
			$return['uv']     = ifset( $response['uv'] );
			$return['snow']     = ifset( $response['snow'] );
			$return['summary']   = ifset( $response['weather']['description'] );
			$return['icon']           = $this->icon_map( $response['weather']['code'], ifset( $response['pod'] ) === 'd' );
			$calc              = new Astronomical_Calculator( $this->latitude, $this->longitude, $this->altitude );
			$return['sunrise'] = $calc->get_iso8601( null );
			$return['sunset']  = $calc->get_iso8601( null, 'sunset' );

			if ( $this->cache_key ) {
				set_transient( $this->cache_key . '_' . md5( $this->latitude . ',' . $this->longitude ), $return, $this->cache_time );
			}
			return array_filter( $return );
		}
		return false;
	}

	private function icon_map( $id, $is_day ) {
		$id = (int) $id;
		switch ( $id ) {
			case 113:
				return $is_day ? 'wi-day-sunny' : 'wi-night-clear';
			case 116:
				return $is_day ? 'wi-day-cloudy' : 'wi-night-partly-cloudy';
			case 119:
				return $is_day ? 'wi-cloudy' : 'wi-night-cloudy';
			case 122:
				return $is_day ? 'wi-day-sunny-overcast' : 'wi-night-alt-cloudy';
			default:
				return '';
		}
	}

}

register_sloc_provider( new Weather_Provider_Weatherbit() );

<?php

class Weather_Provider_Weatherstack extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'Weatherstack', 'simple-location' );
		$this->slug = 'weatherstack';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_weatherstack_api' );
		}
		$args['cache_key'] = '';

		$this->region = false;
		$option       = get_option( 'sloc_weather_provider' );
		if ( 'weatherstack' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_weatherstack_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'Weatherstack API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);

	}

	public static function admin_init() {
		add_settings_field(
			'weatherstackapi', // id
			__( 'Weatherstack API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_weatherstack_api',
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
	public function get_conditions( $time = null ) {
		if ( empty( $this->api ) ) {
			return array();
		}
		$return = array();
		if ( $this->latitude && $this->longitude ) {
			if ( $this->cache_key ) {
				$conditions = get_transient( $this->cache_key . '_' . md5( $this->latitude . ',' . $this->longitude ) );
				if ( $conditions ) {
					return $conditions;
				}
			}
			$data = array(
				'access_key' => $this->api,
				'query'      => $this->latitude . ',' . $this->longitude,
				'units'      => 'm',
			);
			$url  = 'http://api.weatherstack.com/current';
			$url  = add_query_arg( $data, $url );
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
			if ( ! isset( $response['current'] ) ) {
				return $return;
			}
			$response              = $response['current'];
			$return['temperature'] = ifset_round( $response['temperature'], 2 );
			if ( isset( $response['humidity'] ) ) {
				$return['humidity'] = round( $response['humidity'], 2 );
			}
			$return['pressure'] = ifset( $response['pressure'] );
			if ( isset( $response['cloudcover'] ) ) {
				$return['cloudiness'] = $response['cloudcover'];
			}

			$return['wind']           = array();
			$return['wind']['speed']  = ifset_round( $response['wind_speed'] );
			$return['wind']['degree'] = ifset_round( $response['wind_degree'] );
			$return['wind']           = array_filter( $return['wind'] );
			$return['rain']           = ifset_round( $response['precip'], 2 );
			$return['visibility']     = ifset_round( $response['visibility'], 2 );
			$summary                  = ifset( $response['weather_descriptions'] );
			$summary                  = is_array( $summary ) ? implode( ' ', $summary ) : '';
			$return['summary']        = $summary;
			$return['uv']             = ifset( $response['uv_index'] );
			$return['icon']           = $this->icon_map( $response['weather_code'], ifset( $response['is_day'] ) );

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

register_sloc_provider( new Weather_Provider_Weatherstack() );

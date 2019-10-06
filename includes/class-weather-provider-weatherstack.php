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
		parent::__construct( $args );
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
			$return['temperature'] = ifset( $response['temperature'] );
			if ( isset( $response['humidity'] ) ) {
				$return['humidity'] = $response['humidity'];
			}
			$return['pressure'] = ifset( $response['pressure'] );
			if ( isset( $response['cloudcover'] ) ) {
				$return['cloudiness'] = $response['cloudcover'];
			}

			$return['wind']           = array();
			$return['wind']['speed']  = ifset( $response['wind_speed'] );
			$return['wind']['degree'] = ifset( $response['wind_degree'] );
			$return['wind']           = array_filter( $return['wind'] );
			$return['rain']           = ifset( $response['precip'] );
			$return['visibility']     = ifset( $response['visibility'] );
			$summary                  = ifset( $response['weather_descriptions'] );
			$summary                  = is_array( $summary ) ? implode( ' ', $summary ) : '';
			$return['summary']        = $summary;
			$return['icon']           = $this->icon_map( $response['weather_code'], ifset( $response['is_day'] ) );
			$timezone                 = Loc_Timezone::timezone_for_location( $this->latitude, $this->longitude );
			$return['sunrise']        = sloc_sunrise( $this->latitude, $this->longitude, $timezone );
			$return['sunset']         = sloc_sunset( $this->latitude, $this->longitude, $timezone );

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

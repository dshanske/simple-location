<?php

class Weather_Provider_NWSUS extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'National Weather Service(US)', 'simple-location' );
		$args['cache_key'] = '';
		parent::__construct( $args );
	}

        public function set( $lat, $lng = null ) {
                if ( ! $lng && is_array( $lat ) ) {
                        if ( isset( $lat['station_id'] ) ) {
                                $this->station_id = $lat['station_id'];
                        }
                }
                parent::set( $lat, $lng );
        }

	public function is_station() {
		return true;
	}

	/**
	 * Return array of current conditions
	 *
	 * @return array Current Conditions in Array
	 */
	public function get_conditions() {
		$return = array();
		if ( $this->station_id && ! $this->latitude ) {
			return $this->get_station_data();
		}
		return array( 'blah' );

		/*if ( $this->latitude && $this->longitude ) {
			if ( $this->cache_key ) {
				$conditions = get_transient( $this->cache_key . '_' . md5( $this->latitude . ',' . $this->longitude ) );
				if ( $conditions ) {
					return $conditions;
				}
			}
			$data = array(
				'units'   => 'si',
				'exclude' => 'minutely,hourly,daily,alerts,flags',
				'lang'    => get_bloginfo( 'language' ),
			);
			$url  = sprintf( '');
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
			if ( ! isset( $response['currently'] ) ) {
				return $return;
			}
			$current               = ifset( $response['currently'] );
			$return['temperature'] = ifset( $current['temperature'] );
			if ( isset( $current['humidity'] ) ) {
				$return['humidity'] = $current['humidity'] * 100;
			}
			$return['pressure'] = ifset( $current['pressure'] );
			if ( isset( $current['cloudCover'] ) ) {
				$return['cloudiness'] = $current['cloudCover'] * 100;
			}
			$return['wind']           = array();
			$return['wind']['speed']  = ifset( $current['windSpeed'] );
			$return['wind']['degree'] = ifset( $current['windBearing'] );
			$return['wind']           = array_filter( $return['wind'] );
			$return['rain']           = ifset( $current['precipIntensity'] );
			$return['snow']           = ifset( $current['precipAccumulation'] );
			$return['summary']        = ifset( $current['summary'] );
			$return['icon']           = $this->icon_map( $current['icon'] );
			if ( isset( $current['visibility'] ) ) {
				$return['visibility'] = $current['visibility'] * 1000;
			}
			$timezone          = Loc_Timezone::timezone_for_location( $this->latitude, $this->longitude );
			$return['sunrise'] = sloc_sunrise( $time->latitude, $this->longitude, $timezone );
			$return['sunset']  = sloc_sunset( $this->latitude, $this->longitude, $timezone );

			if ( $this->cache_key ) {
				set_transient( $this->cache_key . '_' . md5( $this->latitude . ',' . $this->longitude ), $return, $this->cache_time );
			}
			return array_filter( $return );
		} */
		return false;
	}

	private function get_value( $properties, $key ) {
		if ( isset( $properties[ $key ] ) ) {
			return $properties[ $key ]['value'];
		}
		return null;
	}

	public function get_station_data() {
		$return = array( 'station_id' => $this->station_id );

		$url  = sprintf( 'https://api.weather.gov/stations/%1$s/observations/latest', $this->station_id );
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
		$properties = ifset( $response['properties'] );
		if ( isset( $response['geometry'] ) ) {
			$return['latitude'] = $response['geometry']['coordinates'][0];
			$return['longitude'] = $response['geometry']['coordinates'][1];
		}
		if ( empty( $properties ) ) {
			return array();
		}
		$return['altitude'] = self::get_value( $properties, 'elevation' );
		$return['temperature'] = self::get_value( $properties, 'temperature' );
		$return['humidity'] = self::get_value( $properties, 'relativeHumidity' );
		$return['rain'] = self::get_value( $properties, 'precipitationLastHour' );
		$return['visibility'] = self::get_value( $properties, 'visibility' );
		$wind = array();
		$wind['degree'] = self::get_value( $properties, 'windDirection' );
		$wind['speed'] = self::get_value( $properties, 'windSpeed' );
		$wind = array_filter( $wind );
		if ( ! empty( $wind ) ) {
			$return['wind'] = $wind;
		}
		$return['pressure'] = self::get_value( $properties, 'barometricPressure' );
		$return['summary'] = ifset( $properties['textDescription'] );
		$url = sprintf( 'https://api.weather.gov/stations/%1$s', $this->station_id );
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return array_filter( $return );
		}
		$response = wp_remote_retrieve_body( $response );
		$response = json_decode( $response, true );
		if ( isset( $response['properties'] ) ) {
			$return['name'] = ifset( $response['properties']['name'] );
			$return['timezone'] = ifset( $response['properties']['timeZone'] );
		}
			
		return array_filter( $return );
	}


	private function icon_map( $id ) {
		switch ( $id ) {
			case 'clear-day':
				return 'wi-day-sunny';
			case 'clear-night':
				return 'wi-night-clear';
			case 'rain':
				return 'wi-rain';
			case 'snow':
				return 'wi-snow';
			case 'sleet':
				return 'wi-sleet';
			case 'wind':
				return 'wi-windy';
			case 'fog':
				return 'wi-fog';
			case 'cloudy':
				return 'wi-cloudy';
			case 'partly-cloudy-day':
				return 'wi-day-cloudy';
			case 'partly-cloudy-night':
				return 'wi-night-cloudy';
			case 'hail':
				return 'wi-hail';
			case 'thunderstorm':
				return 'wi-thunderstorm';
			case 'tornado':
				return 'wi-tornado';
			default:
				return '';
		}
	}

}

register_sloc_provider( new Weather_Provider_NWSUS() );

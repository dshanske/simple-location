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
		$this->name   = __( 'National Weather Service(US)', 'simple-location' );
		$this->slug   = 'nwsus';
		$this->region = 'US';
		parent::__construct( $args );
	}

	public function set( $lat, $lng = null, $alt = null ) {
		if ( ! $lng && is_array( $lat ) ) {
			if ( isset( $lat['station_id'] ) ) {
				$this->station_id = $lat['station_id'];
			}
		}
			parent::set( $lat, $lng, $alt );
	}

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
		if ( $this->station_id && ! $this->latitude ) {
			return $this->get_station_data();
		}
		if ( $this->latitude && $this->longitude ) {
			if ( $this->cache_key ) {
				$conditions = get_transient( $this->cache_key . '_' . md5( $this->latitude . ',' . $this->longitude ) );
				if ( $conditions ) {
					return $conditions;
				}
			}
			$url  = sprintf( 'https://api.weather.gov/points/%1$s,%2$s', $this->latitude, $this->longitude );
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
			if ( ! isset( $response['properties'] ) ) {
				return false;
			}
			$url      = $response['properties']['observationStations'];
			$response = wp_remote_get( $url, $args );
			if ( is_wp_error( $response ) ) {
				return false;
			}
			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response, true );
			if ( ! isset( $response['features'] ) ) {
				return false;
			}
			$sitelist = $response['features'];
			foreach ( $sitelist as $key => $value ) {
				$sitelist[ $key ]['distance'] = round( WP_Geo_Data::gc_distance( $this->latitude, $this->longitude, $value['geometry']['coordinates'][1], $value['geometry']['coordinates'][0] ) );
			}
			usort(
				$sitelist,
				function( $a, $b ) {
					return $a['distance'] > $b['distance'];
				}
			);

			$this->station_id = $sitelist[0]['properties']['stationIdentifier'];
			return self::get_station_data();
		}
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
			$return['latitude']  = $response['geometry']['coordinates'][1];
			$return['longitude'] = $response['geometry']['coordinates'][0];
			if ( ! empty( $this->latitude ) ) {
				$return['distance'] = round( WP_Geo_Data::gc_distance( $this->latitude, $this->longitude, $return['latitude'], $return['longitude'] ) );
			}
		}
		if ( empty( $properties ) ) {
			return array();
		}
		$return['altitude']    = self::get_value( $properties, 'elevation' );
		$return['temperature'] = self::get_value( $properties, 'temperature' );
		$return['humidity']    = self::get_value( $properties, 'relativeHumidity' );
		$return['rain']        = self::get_value( $properties, 'precipitationLastHour' );
		$return['visibility']  = self::get_value( $properties, 'visibility' );
		$wind                  = array();
		$wind['degree']        = self::get_value( $properties, 'windDirection' );
		$wind['speed']         = self::get_value( $properties, 'windSpeed' );
		$wind                  = array_filter( $wind );
		if ( ! empty( $wind ) ) {
			$return['wind'] = $wind;
		}
		$return['pressure'] = round( self::get_value( $properties, 'barometricPressure' ) / 1000, 2 ); // Convert Pa to hPa/mBar
		$return['summary']  = ifset( $properties['textDescription'] );
		if ( isset( $return['summary'] ) ) {
			$return['icon'] = self::icon_map( $return['summary'] );
		}
		$url      = sprintf( 'https://api.weather.gov/stations/%1$s', $this->station_id );
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return array_filter( $return );
		}
		$response = wp_remote_retrieve_body( $response );
		$response = json_decode( $response, true );
		if ( isset( $response['properties'] ) ) {
			$return['name']     = ifset( $response['properties']['name'] );
			$return['timezone'] = ifset( $response['properties']['timeZone'] );
		}

		return array_filter( $return );
	}


	private function icon_map( $id ) {
		switch ( $id ) {
			case 'Cloudy':
				return 'wi-cloudy';
			case 'A few clouds':
				return 'wi-cloud';
			case 'Partly Cloudy':
				return 'wi-day-cloudy';
			case 'Mostly Cloudy':
				return 'wi-cloudy';
			case 'Overcast':
				return 'wi-cloudy';
			case 'Fair/clear and windy':
				return 'wi-windy';
			case 'A few clouds and windy':
				return 'wi-cloudy-windy';
			case 'Partly cloudy and windy':
				return 'wi-cloudy-windy';
			case 'Mostly cloudy and windy':
				return 'wi-cloudy-windy';
			case 'Overcast and windy':
				return 'wi-cloudy-windy';
			case 'Snow':
				return 'wi-snow';
			case 'Rain/snow':
				return 'wi-snow';
			case 'Rain/sleet':
			case 'Rain/sleet':
			case 'Freezing rain':
			case 'Rain/freezing rain':
			case 'Freezing rain/snow':
			case 'Sleet':
				return 'wi-sleet';
			case 'Rain':
			case 'Rain showers (high cloud cover)':
			case 'Rain showers (low cloud cover)':
				return 'wi-rain';
			case 'Thunderstorm (high cloud cover)':
			case 'Thunderstorm (medium cloud cover)':
			case 'Thunderstorm (low cloud cover)':
				return 'wi-thunderstorm';
			case 'Tornado':
				return 'wi-tornado';
			case 'Hurricane conditions':
				return 'wi-hurricane';
			case 'Tropical storm conditions':
				return 'wi-storm-showers';
			case 'Dust':
				return 'wi-dust';
			case 'Smoke':
				return 'wi-smoke';
			case 'Haze':
				return 'wi-day-haze';
			case 'Hot':
				return 'wi-hot';
			case 'Cold':
			case 'Blizzard':
				return 'wi-snow';
			case 'Fog/mist':
				return 'wi-fog';
			default:
				return '';
		}
	}

}

register_sloc_provider( new Weather_Provider_NWSUS() );

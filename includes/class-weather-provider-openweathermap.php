<?php

class Weather_Provider_OpenWeatherMap extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'OpenWeatherMap', 'simple-location' );
		$this->slug = 'openweathermap';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_openweathermap_api' );
		}
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

	/**
	 * Return array of current conditions
	 *
	 * @return array Current Conditions in Array
	 */
	public function get_conditions() {
		if ( empty( $this->api ) ) {
			return array();
		}
		$data = array(
			'appid' => $this->api,
			'units' => 'metric',
		);
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
			$url         = 'https://api.openweathermap.org/data/2.5/weather?';
			$data['lat'] = $this->latitude;
			$data['lon'] = $this->longitude;
			$url         = add_query_arg( $data, $url );
			$args        = array(
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
			if ( isset( $response['main'] ) ) {
				$return['temperature'] = $response['main']['temp'];
				$return['humidity']    = $response['main']['humidity'];
				$return['pressure']    = $response['main']['pressure'];
			}
			if ( isset( $response['clouds'] ) ) {
				$return['cloudiness'] = $response['clouds']['all'];
			}
			if ( isset( $response['wind'] ) ) {
				$return['wind']           = array();
				$return['wind']['speed']  = $response['wind']['speed'];
				$return['wind']['degree'] = ifset( $response['wind']['deg'] );
			}
			if ( isset( $response['weather'] ) ) {
				if ( wp_is_numeric_array( $response['weather'] ) ) {
					$response['weather'] = $response['weather'][0];
				}
				$return['summary'] = $response['weather']['description'];
				$return['icon']    = $this->icon_map( (int) $response['weather']['id'] );
			}
			if ( isset( $response['rain'] ) ) {
				$return['rain'] = $response['rain']['1h'];
			}
			if ( isset( $response['snow'] ) ) {
				$return['snow'] = $response['snow']['1h'];
			}
			if ( isset( $response['visibility'] ) ) {
				$return['visibility'] = $response['visibility'];
			}
			$timezone          = Loc_Timezone::timezone_for_location( $this->latitude, $this->longitude );
			$return['sunrise'] = sloc_sunrise( $this->latitude, $this->longitude, $timezone );
			$return['sunset']  = sloc_sunset( $this->latitude, $this->longitude, $timezone );

			if ( $this->cache_key ) {
				set_transient( $this->cache_key . '_' . md5( $this->latitude . ',' . $this->longitude ), $return, $this->cache_time );
			}
			return array_filter( $return );
		}
	}

	public function get_station_data() {
		$data = array(
			'appid' => $this->api,
			'units' => 'metric',
		);
		if ( $this->station_id ) {
			if ( $this->cache_key ) {
				$conditions = get_transient( $this->cache_key . '_' . md5( $this->station_id ) );
				if ( $conditions ) {
					return $conditions;
				}
			}

			$url                = 'http://api.openweathermap.org/data/3.0/measurements?';
			$data['station_id'] = $this->station_id;
			$data['type']       = 'h';
			// An hour ago
			$data['from']  = current_time( 'timestamp' ) - 3600;
			$data['to']    = current_time( 'timestamp' );
			$data['limit'] = '1';
			$url           = add_query_arg( $data, $url );
			$return        = array();
			$response      = wp_remote_get( $url );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response, true );
			if ( WP_DEBUG ) {
				$return['raw'] = $response;
			}
			if ( wp_is_numeric_array( $response ) ) {
				$response = $response[0];
			}
			if ( isset( $response['temp'] ) ) {
				$return['temperature'] = $response['temp']['average'];
			}
			if ( isset( $response['humidity'] ) ) {
				$return['humidity'] = $response['humidity']['average'];
			}
			if ( isset( $response['wind'] ) ) {
				$return['wind']           = array();
				$return['wind']['speed']  = $response['wind']['speed'];
				$return['wind']['degree'] = $response['wind']['deg'];
			}
			if ( isset( $response['pressure'] ) ) {
				$return['pressure'] = $response['pressure']['average'];
			}
			if ( $this->cache_key ) {
				set_transient( $this->cache_key . '_' . md5( $this->station_id ), $return, $this->cache_time );
			}
			return array_filter( $return );
		}
		return false;
	}

	private function icon_map( $id ) {
		if ( in_array( $id, array( 200, 201, 202, 230, 231, 232 ), true ) ) {
			return 'wi-thunderstorm';
		}
		if ( in_array( $id, array( 210, 211, 212, 221 ), true ) ) {
			return 'wi-lightning';
		}
		if ( in_array( $id, array( 300, 301, 321, 500 ), true ) ) {
			return 'wi-sprinkle';
		}
		if ( in_array( $id, array( 302, 311, 312, 314, 501, 502, 503, 504 ), true ) ) {
			return 'wi-rain';
		}
		if ( in_array( $id, array( 310, 511, 611, 612, 615, 616, 620 ), true ) ) {
			return 'wi-rain-mix';
		}
		if ( in_array( $id, array( 313, 520, 521, 522, 701 ), true ) ) {
			return 'wi-showers';
		}
		if ( in_array( $id, array( 531, 901 ), true ) ) {
			return 'wi-storm-showers';
		}
		if ( in_array( $id, array( 600, 601, 621, 622 ), true ) ) {
			return 'wi-snow';
		}
		if ( in_array( $id, array( 602 ), true ) ) {
			return 'wi-sleet';
		}

		if ( in_array( $id, array( 711 ), true ) ) {
			return 'wi-smoke';
		}
		if ( in_array( $id, array( 721 ), true ) ) {
			return 'wi-day-haze';
		}
		if ( in_array( $id, array( 731, 761 ), true ) ) {
			return 'wi-dust';
		}
		if ( in_array( $id, array( 741 ), true ) ) {
			return 'wi-fog';
		}
		if ( in_array( $id, array( 771, 801, 802, 803 ), true ) ) {
			return 'wi-cloudy-gusts';
		}
		if ( in_array( $id, array( 781, 900 ), true ) ) {
			return 'wi-tornado';
		}
		if ( in_array( $id, array( 800 ), true ) ) {
			return 'wi-day-sunny';
		}
		if ( in_array( $id, array( 804 ), true ) ) {
			return 'wi-cloudy';
		}
		if ( in_array( $id, array( 902, 962 ), true ) ) {
			return 'wi-hurricane';
		}
		if ( in_array( $id, array( 903 ), true ) ) {
			return 'wi-snowflake-cold';
		}
		if ( in_array( $id, array( 904 ), true ) ) {
			return 'wi-hot';
		}
		if ( in_array( $id, array( 905 ), true ) ) {
			return 'wi-windy';
		}
		if ( in_array( $id, array( 906 ), true ) ) {
			return 'wi-day-hail';
		}
		if ( in_array( $id, array( 957 ), true ) ) {
			return 'wi-strong-wind';
		}
		if ( in_array( $id, array( 762 ), true ) ) {
			return 'wi-volcano';
		}
		if ( in_array( $id, array( 751 ), true ) ) {
			return 'wi-sandstorm';
		}
		return '';
	}

}

register_sloc_provider( new Weather_Provider_OpenWeatherMap() );

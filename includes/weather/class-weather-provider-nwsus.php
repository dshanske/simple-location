<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

/**
 * Weather Provider using National Weather Service(US) API.
 *
 * @since 1.0.0
 */
class Weather_Provider_NWSUS extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'National Weather Service(US)', 'simple-location' );
		$this->url         = 'https://www.weather.gov/documentation/services-web-api';
		$this->description = __( 'Provided by the US National Weather service, and therefore limited to the United States. No API key or limit, other than rate limiting.', 'simple-location' );
		$this->slug        = 'nwsus';
		$this->region      = 'US';
		parent::__construct( $args );
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
	 * @param int $time Time. Optional.
	 * @return array Current Conditions in Array
	 */
	public function get_conditions( $time = null ) {
		$return   = array();
		$datetime = $this->datetime( $time );

		if ( HOUR_IN_SECONDS < abs( $datetime->getTimestamp() - time() ) ) {
			return array(
				'time'     => $time,
				'datetime' => $datetime,
			);
		}
		if ( $this->station_id && ! $this->latitude ) {
			return $this->get_station_data();
		}
		if ( $this->latitude && $this->longitude ) {
			$conditions = $this->get_cache();
			if ( $conditions ) {
				return $conditions;
			}
			$url  = sprintf( 'https://api.weather.gov/points/%1$s,%2$s', $this->latitude, $this->longitude );
			$args = array(
				'headers'             => array(
					'Accept' => 'application/json',
				),
				'timeout'             => 10,
				'limit_response_size' => 1048576,
				'redirection'         => 1,
				// Use an explicit user-agent for Simple Location.
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
				$sitelist[ $key ]['distance'] = round( geo_distance( $this->latitude, $this->longitude, $value['geometry']['coordinates'][1], $value['geometry']['coordinates'][0] ) );
			}
			usort(
				$sitelist,
				function ( $a, $b ) {
					return $a['distance'] > $b['distance'];
				}
			);

			$this->station_id = $sitelist[0]['properties']['stationIdentifier'];
			$return           = self::get_station_data();
			if ( $return ) {
				// Set the Caching Based on the Latitude and Longitude as that is what Was Called.
				$this->station_id = null;
				$this->set_cache( $return );
				return $return;
			}
		}
		return false;
	}

	/**
	 * Return a value property inside a key.
	 *
	 * @param array  $properties Properies.
	 * @param string $key Key.
	 * @return mixed Value.
	 */
	private function get_value( $properties, $key ) {
		if ( isset( $properties[ $key ] ) ) {
			return $properties[ $key ]['value'];
		}
		return null;
	}

	/**
	 * Return info on the current station.
	 *
	 * @return array Info on Site.
	 */
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
			// Use an explicit user-agent for Simple Location.
			'user-agent'          => 'Simple Location for WordPress',
		);

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response   = wp_remote_retrieve_body( $response );
		$response   = json_decode( $response, true );
		$properties = ifset( $response['properties'] );
		if ( isset( $response['geometry'] ) ) {
			$return['latitude']  = $response['geometry']['coordinates'][1];
			$return['longitude'] = $response['geometry']['coordinates'][0];
			if ( ! empty( $this->latitude ) ) {
				$return['distance'] = round( geo_distance( $this->latitude, $this->longitude, $return['latitude'], $return['longitude'] ) );
			}
		}
		if ( empty( $properties ) ) {
			return array();
		}
		$return['altitude']    = self::get_value( $properties, 'elevation' );
		$return['temperature'] = self::get_value( $properties, 'temperature' );
		$return['windchill']   = self::get_value( $properties, 'windChill' );
		$return['dewpoint']    = self::get_value( $properties, 'dewpoint' );
		$return['heatindex']   = self::get_value( $properties, 'heatIndex' );
		$return['humidity']    = self::get_value( $properties, 'relativeHumidity' );
		$return['rain']        = self::m_to_mm( self::get_value( $properties, 'precipitationLastHour' ) );
		$return['visibility']  = self::get_value( $properties, 'visibility' );
		$return['winddegree']  = self::get_value( $properties, 'windDirection' );
		$return['windspeed']   = self::kmh_to_ms( self::get_value( $properties, 'windSpeed' ) );
		$return['windgust']    = self::get_value( $properties, 'windGust' );
		$return['pressure']    = round( self::get_value( $properties, 'barometricPressure' ) / 100, 2 );

		$return['summary'] = ifset( $properties['textDescription'] );

		if ( ! empty( $properties['presentWeather'] ) ) {
			$return['code'] = self::phenomenon_code_map( $properties['presentWeather'] );
		}
		if ( ! isset( $return['code'] ) ) {
			$return['code'] = self::cloud_code_map( $properties['cloudLayers'] );
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
		$return = array_filter( $this->extra_data( $return ) );
		$this->set_cache( $return );

		if ( WP_DEBUG ) {
			$return['raw'] = $properties;
		}
		return $return;
	}


	/**
	 * Return the condition code based on cloudlayer.
	 *
	 * @param array $layers Cloud
	 * @return string Weather Code.
	 */
	private function cloud_code_map( $layers ) {
		$code = end( $layers )['amount'];
		/* cloudLayers use MetarSkyCoverage codes: OVC, BKN, SCT, FEW, SKC, CLR, VV */
		switch ( $code ) {
			case 'OVC':
				return 804;
			case 'BKN':
				return 803;
			case 'SCT':
				return 802;
			case 'FEW':
				return 801;
			case 'SKC':
			case 'CLR':
				return 800;
			case 'VV':
				return 804;
			default:
				return null;
		}
	}

	/**
	 * Return the condition code based on presentWeather.
	 *
	 * @param string $id Weather type ID.
	 * @return string Icon ID.
	 */
	private function phenomenon_code_map( $weather ) {
		if ( ! is_array( $weather ) ) {
			return null;
		}

		/*
		MetarPhenomenon{
		description:

		An object representing a decoded METAR phenomenon string.
		intensity*  string
		nullable: trueEnum:
		[ light, heavy ]
		modifier*   string
		nullable: trueEnum:
		[ patches, blowing, low_drifting, freezing, shallow, partial, showers ]
		weather*    stringEnum:
		[ fog_mist, dust_storm, dust, drizzle, funnel_cloud, fog, smoke, hail, snow_pellets, haze, ice_crystals, ice_pellets, dust_whirls, spray, rain, sand, snow_grains, snow, squalls, sand_storm, thunderstorms, unknown, volcanic_ash ]
		rawString*  string
		inVicinity  boolean */

		if ( 1 === count( $weather ) ) {
			$weather = end( $weather );
		} else {
			$codes = wp_list_pluck( $weather, 'weather' );
			if ( empty( array_diff( $codes, array( 'thunderstorms', 'rain' ) ) ) ) {
				return 201;
			}
		}

		$condition = ifset( $weather['weather'] );

		switch ( $condition ) {
			case 'thunderstorms':
				if ( ! isset( $weather['intensity'] ) ) {
					return 211;
				}
				if ( 'light' === $weather['intensity'] ) {
					return 210;
				}
				if ( 'heavy' === $weather['intensity'] ) {
					return 212;
				}
			case 'fog':
			case 'fog_mist':
				return 741;
			case 'dust':
			case 'dust_storm':
			case 'dust_whirls':
				return 761;
			case 'drizzle':
				if ( ! isset( $weather['intensity'] ) ) {
					return 301;
				}
				if ( 'light' === $weather['intensity'] ) {
					return 300;
				}
				if ( 'heavy' === $weather['intensity'] ) {
					return 302;
				}
			case 'smoke':
				return 711;
			case 'hail':
				return 624;
			case 'snow_pellets':
				return 611;
			case 'haze':
				return 721;
			case 'ice_crystals':
			case 'ice_pellets':
			case 'spray':
			case 'rain':
				if ( ! isset( $weather['intensity'] ) ) {
					return 521;
				}
				if ( 'light' === $weather['intensity'] ) {
					return 520;
				}
				if ( 'heavy' === $weather['intensity'] ) {
					return 522;
				}
			case 'sand':
			case 'sand_storm':
				return 751;
			case 'snow_grains':
			case 'snow':
				if ( ! isset( $weather['intensity'] ) ) {
					return 601;
				}
				if ( 'light' === $weather['intensity'] ) {
					return 600;
				}
				if ( 'heavy' === $weather['intensity'] ) {
					return 602;
				}
			case 'squalls':
				return 771;
			case 'volcanic_ash':
				return 762;
			case 'funnel_cloud':
			case 'unknown':
			default:
				return null;
		}
	}
}

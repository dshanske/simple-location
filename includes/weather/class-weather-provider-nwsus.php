<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

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
	 * @since 1.0.0
	 * @since 6.0.0 Uses the fallback weather provider when a location lookup finds no observation.
	 * @since 6.0.0 A time more than an hour from now uses the fallback provider, or returns a `historic_unsupported` error.
	 *
	 * @param int $time Time. Optional.
	 * @return array|WP_Error|false Current conditions, the fallback provider's conditions when NWS has none for the location, or false without a location.
	 */
	public function get_conditions( $time = null ) {
		$return   = array();
		$datetime = $this->datetime( $time );

		if ( HOUR_IN_SECONDS < abs( $datetime->getTimestamp() - time() ) ) {
			return $this->get_unsupported_time_conditions( $time );
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
				return self::get_fallback_conditions( $time );
			}
			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response, true );
			// NWS has no grid point outside the US.
			if ( ! isset( $response['properties']['observationStations'] ) ) {
				return self::get_fallback_conditions( $time );
			}
			$url      = $response['properties']['observationStations'];
			$response = wp_remote_get( $url, $args );
			if ( is_wp_error( $response ) ) {
				return self::get_fallback_conditions( $time );
			}
			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response, true );
			if ( empty( $response['features'] ) || ! is_array( $response['features'] ) ) {
				return self::get_fallback_conditions( $time );
			}
			$sitelist = $response['features'];
			foreach ( $sitelist as $key => $value ) {
				$sitelist[ $key ]['distance'] = round( geo_distance( $this->latitude, $this->longitude, $value['geometry']['coordinates'][1], $value['geometry']['coordinates'][0] ) );
			}
			usort(
				$sitelist,
				function ( $a, $b ) {
					return $a['distance'] <=> $b['distance'];
				}
			);

			$this->station_id = $sitelist[0]['properties']['stationIdentifier'];
			$return           = self::get_station_data();
			// Set the Caching Based on the Latitude and Longitude as that is what Was Called.
			$this->station_id = null;
			if ( ! is_wp_error( $return ) && ! empty( $return ) ) {
				$this->set_cache( $return );
				return $return;
			}
			return self::get_fallback_conditions( $time );
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
		if ( isset( $properties[ $key ]['value'] ) ) {
			return $properties[ $key ]['value'];
		}
		return null;
	}

	/**
	 * Return a value property converted from the unit NWS reports it in.
	 *
	 * @since 6.0.0
	 *
	 * @param array  $properties  Properties.
	 * @param string $key         Key.
	 * @param array  $conversions Callbacks to convert the value, keyed by the WMO unit code they convert from.
	 * @return mixed Value, converted when its unit has a conversion.
	 */
	private function get_quantity( $properties, $key, $conversions ) {
		$value = self::get_value( $properties, $key );
		$unit  = isset( $properties[ $key ]['unitCode'] ) ? $properties[ $key ]['unitCode'] : '';
		if ( isset( $conversions[ $unit ] ) ) {
			return call_user_func( $conversions[ $unit ], $value );
		}
		return $value;
	}

	/**
	 * Return info on the current station.
	 *
	 * @since 6.1.0 Includes the cloud cover, from the cloud layers.
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
		$properties = $response['properties'] ?? null;
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
		$return['rain']        = self::get_quantity( $properties, 'precipitationLastHour', array( 'wmoUnit:m' => array( $this, 'm_to_mm' ) ) );
		$return['visibility']  = self::get_value( $properties, 'visibility' );
		$return['winddegree']  = self::get_value( $properties, 'windDirection' );
		$return['windspeed']   = self::get_quantity( $properties, 'windSpeed', array( 'wmoUnit:km_h-1' => array( $this, 'kmh_to_ms' ) ) );
		$return['windgust']    = self::get_quantity( $properties, 'windGust', array( 'wmoUnit:km_h-1' => array( $this, 'kmh_to_ms' ) ) );
		$pressure              = self::get_value( $properties, 'barometricPressure' );
		$return['pressure']    = is_numeric( $pressure ) ? round( $pressure / 100, 2 ) : null;

		$return['summary']    = $properties['textDescription'] ?? null;
		$return['cloudiness'] = self::cloud_cover( $properties['cloudLayers'] ?? null );

		if ( ! empty( $properties['presentWeather'] ) ) {
			$return['code'] = self::phenomenon_code_map( $properties['presentWeather'] );
		}
		if ( ! isset( $return['code'] ) ) {
			$return['code'] = self::cloud_code_map( $properties['cloudLayers'] ?? null );
		}

		$url      = sprintf( 'https://api.weather.gov/stations/%1$s', $this->station_id );
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return self::filter_weather( $return );
		}
		$response = wp_remote_retrieve_body( $response );
		$response = json_decode( $response, true );
		if ( isset( $response['properties'] ) ) {
			$return['name']     = $response['properties']['name'] ?? null;
			$return['timezone'] = $response['properties']['timeZone'] ?? null;
		}
		$return = self::filter_weather( $this->extra_data( $return ) );
		$this->set_cache( $return );

		if ( WP_DEBUG ) {
			$return['raw'] = $properties;
		}
		return $return;
	}


	/**
	 * Returns the cloud cover of an observation's cloud layers.
	 *
	 * Each layer's METAR amount covers the layers below it too, so the largest amount is the sky's cover. Amounts in
	 * eighths of the sky (oktas) are given the middle of their range.
	 *
	 * @since 6.1.0
	 *
	 * @param array|null $layers Cloud layers, each with an 'amount' such as SCT.
	 * @return int|null Cloud cover in percent, or null without layers with a known amount.
	 */
	public static function cloud_cover( $layers ) {
		if ( empty( $layers ) || ! is_array( $layers ) ) {
			return null;
		}
		// FEW is 1-2 oktas, SCT 3-4, BKN 5-7; VV is a sky hidden by fog or other obscuration.
		$cover = array(
			'SKC' => 0,
			'CLR' => 0,
			'FEW' => 19,
			'SCT' => 44,
			'BKN' => 75,
			'OVC' => 100,
			'VV'  => 100,
		);
		$found = null;
		foreach ( $layers as $layer ) {
			$amount = is_array( $layer ) ? ( $layer['amount'] ?? null ) : null;
			if ( is_string( $amount ) && isset( $cover[ $amount ] ) ) {
				$found = max( (int) $found, $cover[ $amount ] );
			}
		}
		return $found;
	}

	/**
	 * Return the condition code based on cloudlayer.
	 *
	 * @since 6.0.0 Returns null when there are no cloud layers.
	 *
	 * @param array $layers Cloud layers.
	 * @return int|null Weather Code.
	 */
	private function cloud_code_map( $layers ) {
		if ( empty( $layers ) || ! is_array( $layers ) ) {
			return null;
		}
		$layer = end( $layers );
		$code  = $layer['amount'] ?? null;
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
	 * @param array $weather Decoded METAR phenomena.
	 * @return int|null Condition code, or null if there is no matching phenomenon.
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

		$condition = $weather['weather'] ?? '';

		switch ( $condition ) {
			case 'thunderstorms':
				if ( array_key_exists( 'intensity', $weather ) ) {
					if ( 'light' === $weather['intensity'] ) {
						return 210;
					}
					if ( 'heavy' === $weather['intensity'] ) {
						return 212;
					}
				}
				return 211;
			case 'fog':
			case 'fog_mist':
				return 741;
			case 'dust':
			case 'dust_storm':
			case 'dust_whirls':
				return 761;
			case 'drizzle':
				if ( array_key_exists( 'intensity', $weather ) ) {
					if ( 'light' === $weather['intensity'] ) {
						return 300;
					}
					if ( 'heavy' === $weather['intensity'] ) {
						return 302;
					}
				}
				return 301;
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
				if ( array_key_exists( 'intensity', $weather ) ) {
					if ( 'light' === $weather['intensity'] ) {
						return 520;
					}
					if ( 'heavy' === $weather['intensity'] ) {
						return 522;
					}
				}
				return 521;
			case 'sand':
			case 'sand_storm':
				return 751;
			case 'snow_grains':
			case 'snow':
				if ( array_key_exists( 'intensity', $weather ) ) {
					if ( 'light' === $weather['intensity'] ) {
						return 600;
					}
					if ( 'heavy' === $weather['intensity'] ) {
						return 602;
					}
				}
				return 601;
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

	// No credit: the National Weather Service's data is public domain. Add one with the sloc_weather_credit filter.
}

<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Weather Provider using Met Office UK API.
 *
 * @since 1.0.0
 */
class Weather_Provider_MetOffice extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Met Office(UK)', 'simple-location' );
		$this->slug        = 'metofficeuk';
		$this->url         = 'https://www.metoffice.gov.uk/services/data/datapoint';
		$this->description = __( 'Limited to data for the UK only, and requires a free API key for unlimited access. Historical data is not available.', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_metoffice_api' );
		}
		$this->region = 'GB';
		parent::__construct( $args );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'Met Office API', 'simple-location' ), 'sloc_metoffice_api' );
	}

	/**
	 * Init Function To Add Settings Fields.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'Met Office', 'simple-location' ), 'sloc_metoffice_api' );
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
	 * Retrieves a list of Met Office sites.
	 *
	 * @return array List of sites.
	 */
	public function get_sitelist() {
		$response = get_transient( 'metoffice_sites' );
		if ( false !== $response ) {
			return $response;
		}
		$url  = 'http://datapoint.metoffice.gov.uk/public/data/val/wxobs/all/json/sitelist?key=' . $this->api;
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
		if ( ! isset( $response['Locations'] ) ) {
			return false;
		}
		$response = $response['Locations']['Location'];
		set_transient( 'metoffice_sites', $response, WEEK_IN_SECONDS );
		return $response;
	}

	/**
	 * Return array of current conditions
	 *
	 * @since 6.0.0 A time more than an hour from now uses the fallback provider, or returns a `historic_unsupported` error.
	 *
	 * @param int $time Time. Optional.
	 * @return array|WP_Error Current Conditions in Array, or an error.
	 */
	public function get_conditions( $time = null ) {
		$return   = array();
		$datetime = $this->datetime( $time );

		if ( HOUR_IN_SECONDS < abs( $datetime->getTimestamp() - time() ) ) {
			return $this->get_unsupported_time_conditions( $time );
		}
		if ( ! empty( $this->station_id ) ) {
			return self::get_station_data();
		}
		if ( $this->latitude && $this->longitude ) {
			$conditions = $this->get_cache();
			if ( $conditions ) {
				return $conditions;
			}

			$sitelist = $this->get_sitelist();
			foreach ( $sitelist as $key => $value ) {
				$sitelist[ $key ]['distance'] = round( geo_distance( $this->latitude, $this->longitude, $value['latitude'], $value['longitude'] ) );
			}
			usort(
				$sitelist,
				function ( $a, $b ) {
					return $a['distance'] <=> $b['distance'];
				}
			);
			if ( 100000 > $sitelist[0]['distance'] ) {
				$this->station_id = $sitelist[0]['id'];
				$return           = self::get_station_data();

				$this->station_id = null;
				$this->set_cache( $return );
				return $return;
			}
			return self::get_fallback_conditions( $time );
		}
		return new WP_Error( 'failed', __( 'Failure', 'simple-location' ) );
	}

	/**
	 * Converts code into weather description.
	 *
	 * @param int $type code.
	 * @return string description.
	 */
	public function weather_type( $type ) {
		$types = array(
			0  => 'clear night',
			1  => 'sunny day',
			2  => 'partly cloudy (night)',
			3  => 'partly cloudy (day)',
			4  => 'not used',
			5  => 'mist',
			6  => 'fog',
			7  => 'cloudy',
			8  => 'overcast',
			9  => 'light rain shower (night)',
			10 => 'light rain shower (day)',
			11 => 'drizzle',
			12 => 'light rain',
			13 => 'heavy rain shower (night)',
			14 => 'heavy rain shower (day)',
			15 => 'heavy rain',
			16 => 'sleet shower (night)',
			17 => 'sleet shower (day)',
			18 => 'sleet',
			19 => 'hail shower (night)',
			20 => 'hail shower (day)',
			21 => 'hail',
			22 => 'light snow shower (night)',
			23 => 'light snow shower (day)',
			24 => 'light snow',
			25 => 'heavy snow shower (night)',
			26 => 'heavy snow shower (day)',
			27 => 'heavy snow',
			28 => 'thunder shower (night)',
			29 => 'thunder shower (day)',
			30 => 'thunder',
		);
		if ( array_key_exists( intval( $type ), $types ) ) {
			return $types[ intval( $types ) ];
		}
		return __( 'not available', 'simple-location' );
	}

	/**
	 * Converts a Met Office weather type to a condition code.
	 *
	 * @param int $type Met Office weather type.
	 * @return int|string Condition code.
	 */
	public static function code_map( $type ) {
		$types = array(
			0  => 800,
			1  => 800,
			2  => 802,
			3  => 802,
			4  => '',
			5  => 701,
			6  => 741,
			7  => 803,
			8  => 804,
			9  => 520,
			10 => 520,
			11 => 301,
			12 => 500,
			13 => 522,
			14 => 522,
			15 => 504,
			16 => 613,
			17 => 613,
			18 => 611,
			19 => 624,
			20 => 624,
			21 => 624,
			22 => 620,
			23 => 620,
			24 => 600,
			25 => 622,
			26 => 622,
			27 => 602,
			28 => 201,
			29 => 201,
			30 => 211,
		);
		if ( array_key_exists( intval( $type ), $types ) ) {
			return $types[ intval( $types ) ];
		}
		return '';
	}


	/**
	 * Return info on the current station.
	 *
	 * @return array Info on Site.
	 */
	public function station() {
		$list = $this->get_sitelist();
		if ( ! is_array( $list ) ) {
			return array();
		}
		foreach ( $list as $site ) {
			if ( $site['id'] === $this->station_id ) {
				return $site;
			}
		}
		return array();
	}

	/**
	 * Return current conditions at the station.
	 *
	 * @return array Current Conditions.
	 */
	public function get_station_data() {
		$conditions = $this->get_cache();
		if ( $conditions ) {
			return $conditions;
		}

		$return = array(
			'station_id'   => $this->station_id,
			'station_data' => $this->station(),
		);

		$return['distance'] = round( geo_distance( $this->latitude, $this->longitude, $return['station_data']['latitude'], $return['station_data']['longitude'] ) );

		$url  = sprintf( 'http://datapoint.metoffice.gov.uk/public/data/val/wxobs/all/json/%1$s?res=hourly&key=%2$s', $this->station_id, $this->api );
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
		if ( WP_DEBUG ) {
			$return['raw'] = $response;
		}
		if ( ! isset( $response['SiteRep'] ) ) {
			return $return;
		}

		$response = $response['SiteRep']['DV']['Location'];
		if ( isset( $response['lat'] ) ) {
			$return['latitude']  = $response['lat'];
			$return['longitude'] = $response['lon'];
			$return['altitude']  = $response['elevation'];
		}
		$response = end( $response['Period'] );
		if ( wp_is_numeric_array( $response['Rep'] ) ) {
			$properties = end( $response['Rep'] );
		} else {
			$properties = $response['Rep'];
		}

		$return['temperature'] = $properties['T'] ?? null;
		$return['dewpoint']    = $properties['Dp'] ?? null;
		$return['humidity']    = $properties['H'] ?? null;
		$return['visibility']  = $properties['V'] ?? null;
		$return['pressure']    = $properties['P'] ?? null;
		$return['windspeed']   = self::mph_to_mps( $properties['S'] ?? null );
		$return['windgust']    = self::mph_to_mps( $properties['G'] ?? null );
		$return['summary']     = $this->weather_type( $properties['W'] ?? '' );
		$return['code']        = $this->code_map( $properties['W'] ?? '' );
		$return                = self::filter_weather( $this->extra_data( $return ) );

		$this->set_cache( $return );

		return $return;
	}
}

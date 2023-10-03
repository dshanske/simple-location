<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

/**
 * Weather Provider using WeatherBit API.
 *
 * @since 1.0.0
 */
class Weather_Provider_Weatherbit extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Weatherbit', 'simple-location' );
		$this->slug        = 'weatherbit';
		$this->url         = 'https://www.weatherbit.io';
		$this->description = __( 'Offers 500 calls per day free with an API key. Paid plan does include historical data.', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_weatherbit_api' );
		}
		$args['cache_key'] = '';

		$this->region = false;
		parent::__construct( $args );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'WeatherBit', 'simple-location' ), 'sloc_weatherbit_api' );
	}

	/**
	 * Init Function To Add Settings fields.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'WeatherBit', 'simple-location' ), 'sloc_weatherbit_api' );
	}

	/**
	 * Does This Provider Offer Station Data.
	 *
	 * @return boolean If supports station data return true.
	 */
	public function is_station() {
		return false;
	}

	/**
	 * Return array of current conditions
	 *
	 * @param int $time Time. Optional.
	 * @return array Current Conditions in Array.
	 */
	public function get_conditions( $time = null ) {
		if ( empty( $this->api ) ) {
			return array();
		}

		$datetime = $this->datetime( $time );

		if ( HOUR_IN_SECONDS < abs( $datetime->getTimestamp() - time() ) ) {
			return array(
				'time'     => $time,
				'datetime' => $datetime,
			);
		}

		$return = array();
		if ( $this->latitude && $this->longitude ) {
			$conditions = $this->get_cache();
			if ( $conditions ) {
				return $conditions;
			}

			$data = array(
				'key'   => $this->api,
				'lat'   => $this->latitude,
				'lon'   => $this->longitude,
				'units' => 'M',

			);
			$url  = 'https://api.weatherbit.io/v2.0/current';
			$url  = add_query_arg( $data, $url );
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
			if ( ! isset( $response['data'] ) ) {
				return $return;
			}
			$response              = $response['data'][0];
			$return['temperature'] = ifset_round( $response['temp'], 1 );
			$return['humidity']    = ifset_round( $response['rh'], 1 );
			$return['dewpoint']    = ifset_round( $response['dewpt'], 1 );
			$return['pressure']    = ifset_round( $response['pres'], 2 );
			$return['cloudiness']  = ifset_round( $response['clouds'] );

			$return['windspeed']  = ifset_round( $response['wind_spd'], 2 );
			$return['winddegree'] = ifset_round( $response['wind_dir'], 1 );
			$return['rain']       = ifset_round( $response['precip'], 2 );
			$return['visibility'] = self::km_to_meters( ifset_round( $response['vis'], 2 ) );
			$return['aqi']        = ifset_round( $response['aqi'], 2 );
			$return['uv']         = ifset_round( $response['uv'] );
			$return['radiation']  = ifset_round( $response['solar_rad'], 2 );
			$return['snow']       = ifset_round( $response['snow'], 2 );
			$return['summary']    = ifset( $response['weather']['description'] );
			$return['code']       = ifset( $response['weather']['code'] );

			$return = array_filter( $this->extra_data( $return, $time ) );

			$this->set_cache( $return );
			return $return;
		}
		return false;
	}
}

<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

/**
 * Weather Provider using WeatherStack API.
 *
 * @since 1.0.0
 */
class Weather_Provider_Weatherstack extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Weatherstack', 'simple-location' );
		$this->slug        = 'weatherstack';
		$this->url         = 'https://weatherstack.com';
		$this->description = __( 'Offers a free account, but at only 250 calls/month and no historical data.', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_weatherstack_api' );
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
		self::register_settings_api( __( 'WeatherStack', 'simple-location' ), 'sloc_weatherstack_api' );
	}

	/**
	 * Init Function To Add Settings Fields.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'Weatherstack', 'simple-location' ), 'sloc_weatherstack_api' );
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
	 * @return array Current Conditions in Array
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
			if ( ! isset( $response['current'] ) ) {
				return $return;
			}
			$response              = $response['current'];
			$return['temperature'] = ifset_round( $response['temperature'], 2 );
			if ( isset( $response['humidity'] ) ) {
				$return['humidity'] = round( $response['humidity'], 2 );
			}
			$return['pressure'] = ifset( $response['pressure'] );

			$return['windspeed']  = self::kmh_to_ms( ifset_round( $response['wind_speed'] ) );
			$return['winddegree'] = ifset_round( $response['wind_degree'] );
			$return['rain']       = ifset_round( $response['precip'], 2 );
			$return['cloudiness'] = ifset( $response['cloudcover'] );
			$return['visibility'] = self::km_to_meters( ifset_round( $response['visibility'], 2 ) );
			$summary              = ifset( $response['weather_descriptions'] );
			$summary              = is_array( $summary ) ? implode( ' ', $summary ) : '';
			$return['summary']    = $summary;
			$return['uv']         = ifset( $response['uv_index'] );
			$return['code']       = $this->code_map( $response['weather_code'] );

			$return = array_filter( $this->extra_data( $return, $time ) );
			$this->set_cache( $return );

			return $return;
		}
		return false;
	}

	/**
	 * Return array of station data.
	 *
	 * @param string  $id Weather type ID.
	 * @param boolean $is_day Is It Daytime.
	 * @return string Icon ID.
	 */
	private function code_map( $id ) {
		switch ( $id ) {
			case 113:
				return 800;
			case 116:
				return 801;
			case 119:
				return 803;
			case 122:
				return 804;
			case 143:
				return 701;
			case 227:
				return 625;
			case 230:
				return 602;
			case 248:
			case 260:
				return 741;
			case 263:
				return 300;
			case 266:
				return 310;
			case 281:
				return 309;
			case 284:
				return 309;
			case 293:
			case 296:
				return 500;
			case 299:
			case 302:
				return 503;
			case 305:
			case 308:
				return 504;
			case 311:
				return 511;
			default:
				return '';
		}
	}
}

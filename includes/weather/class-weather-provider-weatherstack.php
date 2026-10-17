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
	 * @since 4.0.0
	 * @since 6.0.0 Calls the Weatherstack API (it called Weatherbit's), over HTTP if the plan doesn't allow HTTPS.
	 * @since 6.0.0 A time more than an hour from now uses the fallback provider, or returns a `historic_unsupported` error.
	 *
	 * @param int $time Time. Optional.
	 * @return array|WP_Error Current Conditions in Array, or an error from the API.
	 */
	public function get_conditions( $time = null ) {
		if ( empty( $this->api ) ) {
			return array();
		}

		$datetime = $this->datetime( $time );

		if ( HOUR_IN_SECONDS < abs( $datetime->getTimestamp() - time() ) ) {
			return $this->get_unsupported_time_conditions( $time );
		}

		$return = array();
		if ( $this->latitude && $this->longitude ) {
			$conditions = $this->get_cache();
			if ( $conditions ) {
				return $conditions;
			}

			$query    = array(
				'access_key' => $this->api,
				'query'      => $this->latitude . ',' . $this->longitude,
				'units'      => 'm',
			);
			$response = $this->fetch_json( 'https://api.weatherstack.com/current', $query );
			// The free plan doesn't allow HTTPS.
			if ( is_array( $response ) && isset( $response['error']['code'] ) && 105 === (int) $response['error']['code'] ) {
				$response = $this->fetch_json( 'http://api.weatherstack.com/current', $query );
			}
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			if ( isset( $response['error'] ) ) {
				$error = is_array( $response['error'] ) ? $response['error'] : array();
				return new WP_Error(
					'weatherstack_error',
					isset( $error['info'] ) ? $error['info'] : __( 'Weatherstack returned an error', 'simple-location' ),
					array( 'code' => isset( $error['code'] ) ? $error['code'] : null )
				);
			}
			if ( WP_DEBUG ) {
				$return['raw'] = $response;
			}
			if ( ! isset( $response['current'] ) || ! is_array( $response['current'] ) ) {
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
			$return['code']       = $this->code_map( ifset( $response['weather_code'] ) );

			$return = self::filter_weather( $this->extra_data( $return, $time ) );
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

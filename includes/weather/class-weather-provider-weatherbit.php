<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

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
	 * Returns whether the provider has weather for a time: only current weather, within an hour of now.
	 *
	 * @since 6.1.0
	 *
	 * @param int|string|DateTime|null $time Time, or null for now.
	 * @return bool Whether to try the provider for this time.
	 */
	public function supports_time( $time = null ) {
		return $this->is_current( $time );
	}

	/**
	 * Return array of current conditions
	 *
	 * @since 6.0.0 A time more than an hour from now uses the fallback provider, or returns a `historic_unsupported` error.
	 * @since 6.2.0 Requests go through fetch_json(); a refused request, such as with a bad key, is an error.
	 *
	 * @param int $time Time. Optional.
	 * @return array|WP_Error Current Conditions in Array, or an error.
	 */
	public function get_conditions( $time = null ) {
		if ( empty( $this->api ) ) {
			return $this->missing_api_key_error();
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

			$data = array(
				'key'   => $this->api,
				'lat'   => $this->latitude,
				'lon'   => $this->longitude,
				'units' => 'M',

			);
			$response = $this->fetch_json( 'https://api.weatherbit.io/v2.0/current', $data );
			// Weatherbit answers 204, with nothing, when it has no data; that is no weather rather than a failure.
			if ( is_wp_error( $response ) && 'not_json_response' === $response->get_error_code() && '' === trim( (string) ( $response->get_error_data()['body'] ?? '' ) ) ) {
				return $return;
			}
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			if ( WP_DEBUG ) {
				$return['raw'] = $response;
			}
			if ( ! isset( $response['data'] ) ) {
				return $return;
			}
			$response              = $response['data'][0];
			$return['temperature'] = self::round_value( $response['temp'] ?? null, 1 );
			$return['humidity']    = self::round_value( $response['rh'] ?? null, 1 );
			$return['dewpoint']    = self::round_value( $response['dewpt'] ?? null, 1 );
			$return['pressure']    = self::round_value( $response['pres'] ?? null, 2 );
			$return['cloudiness']  = self::round_value( $response['clouds'] ?? null );

			$return['windspeed']  = self::round_value( $response['wind_spd'] ?? null, 2 );
			$return['winddegree'] = self::round_value( $response['wind_dir'] ?? null, 1 );
			$return['rain']       = self::round_value( $response['precip'] ?? null, 2 );
			$return['visibility'] = self::km_to_meters( self::round_value( $response['vis'] ?? null, 2 ) );
			$return['aqi']        = self::round_value( $response['aqi'] ?? null, 2 );
			$return['uv']         = self::round_value( $response['uv'] ?? null );
			$return['radiation']  = self::round_value( $response['solar_rad'] ?? null, 2 );
			$return['snow']       = self::round_value( $response['snow'] ?? null, 2 );
			$return['summary']    = $response['weather']['description'] ?? null;
			$return['code']       = $response['weather']['code'] ?? null;

			$return = self::filter_weather( $this->extra_data( $return, $time ) );

			$this->set_cache( $return );
			return $return;
		}
		return false;
	}

	/**
	 * Returns the credit for the provider's weather.
	 *
	 * Weatherbit requires a visible link on its free and hobby plans. Sites on a paid plan can remove it with the sloc_weather_credit filter.
	 *
	 * @since 6.1.0
	 *
	 * @return array Credit. See Weather_Provider::attribution().
	 */
	protected function attribution() {
		return array(
			'text'     => __( 'Weather data by Weatherbit.io', 'simple-location' ),
			'url'      => 'https://www.weatherbit.io/',
			'adjacent' => false,
		);
	}
}

<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Weather Provider using OpenWeatherMap API.
 *
 * @since 1.0.0
 */
class Weather_Provider_OpenWeatherMap extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'OpenWeatherMap', 'simple-location' );
		$this->slug        = 'openweathermap';
		$this->url         = 'https://openweathermap.org';
		$this->description = __( 'Free account offers 1000 calls per day, requires an API key and credit card for overage', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_openweathermap_api' );
		}

		$this->region = false;
		parent::__construct( $args );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'OpenWeatherMap API Key', 'simple-location' ), 'sloc_openweathermap_api' );
	}

	/**
	 * Init Function To Add Settings Fields.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'OpenWeatherMap', 'simple-location' ), 'sloc_openweathermap_api' );
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
	 * @since 6.1.0 Returns an error when OpenWeatherMap refuses the request (#692), or no_results without current conditions.
	 *              Wind speed is rounded to one decimal place.
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
		$data = array(
			'appid' => $this->api,
			'units' => 'metric',
		);
		if ( ! empty( $this->station_id ) && empty( $this->latitude ) ) {
			return $this->get_station_data();
		}
		if ( $this->latitude && $this->longitude ) {
			$conditions = $this->get_cache();
			if ( $conditions ) {
				return $conditions;
			}

			$data['lat'] = $this->latitude;
			$data['lon'] = $this->longitude;
			$response    = $this->fetch_json( 'https://api.openweathermap.org/data/3.0/onecall', $data );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			if ( ! isset( $response['current'] ) ) {
				return $this->no_results_error();
			}
			$return = array();
			if ( WP_DEBUG ) {
				$return['raw'] = $response;
			}
			$current = $response['current'];

			$return['temperature'] = round( $current['temp'], 1 );
			$return['dewpoint']    = round( $current['dew_point'], 1 );

			$return['humidity']   = round( $current['humidity'], 1 );
			$return['pressure']   = round( $current['pressure'], 1 );
			$return['cloudiness'] = $current['clouds'];
			$return['visibility'] = $current['visibility'];
			$return['uv']         = $current['uvi'];

			if ( isset( $current['rain'] ) ) {
				$return['rain'] = round( $current['rain']['1h'], 2 );
			}
			if ( isset( $current['snow'] ) ) {
				$return['snow'] = round( $current['snow']['1h'], 2 );
			}

			$return['windspeed']  = round( $current['wind_speed'], 1 );
			$return['winddegree'] = round( $current['wind_deg'], 1 );
			$return['windgust']   = self::round_value( $current['wind_gust'] ?? null, 1 );

			if ( isset( $current['weather'] ) ) {
				if ( wp_is_numeric_array( $current['weather'] ) ) {
					$current['weather'] = $current['weather'][0];
				}
				$return['code']    = (int) $current['weather']['id'];
				$return['summary'] = $current['weather']['description'];
			}

			$return = self::filter_weather( $this->extra_data( $return ) );

			$this->set_cache( $return );

			return $return;
		}
		// Without a location or a station there are no conditions.
		return array();
	}

	/**
	 * Return info on the current station.
	 *
	 * @since 6.1.0 Requests over HTTPS with fetch_json(), returns wind as windspeed and winddegree, and returns errors (#348).
	 *
	 * @return array|WP_Error Conditions, or an error, such as no_results without a measurement in the last hour.
	 */
	public function get_station_data() {
		if ( empty( $this->station_id ) ) {
			return new WP_Error( 'unable_to_retrieve', __( 'Unable to Retrieve', 'simple-location' ) );
		}
		$conditions = $this->get_cache();
		if ( $conditions ) {
			return $conditions;
		}
		$response = $this->fetch_json(
			'https://api.openweathermap.org/data/3.0/measurements',
			array(
				'appid'      => $this->api,
				'units'      => 'metric',
				'station_id' => $this->station_id,
				'type'       => 'h',
				// An hour ago.
				'from'       => time() - HOUR_IN_SECONDS,
				'to'         => time(),
				'limit'      => 1,
			)
		);
		// A station with no measurements in the last hour returns an empty list.
		if ( is_wp_error( $response ) && 'not_json_response' === $response->get_error_code() && '[]' === trim( (string) ( $response->get_error_data()['body'] ?? '' ) ) ) {
			return $this->no_results_error();
		}
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( wp_is_numeric_array( $response ) ) {
			$response = $response[0];
		}
		if ( ! is_array( $response ) ) {
			return $this->no_results_error();
		}
		$return = array(
			'temperature' => self::round_value( $response['temp']['average'] ?? null, 1 ),
			'humidity'    => self::round_value( $response['humidity']['average'] ?? null, 1 ),
			'pressure'    => self::round_value( $response['pressure']['average'] ?? null, 1 ),
			'windspeed'   => self::round_value( $response['wind']['speed'] ?? null, 1 ),
			'winddegree'  => self::round_value( $response['wind']['deg'] ?? null, 1 ),
		);
		if ( WP_DEBUG ) {
			$return['raw'] = $response;
		}
		$return = self::filter_weather( $return );
		$this->set_cache( $return );

		return $return;
	}

	/**
	 * Returns the credit for the provider's weather.
	 *
	 * OpenWeather requires a visible credit on its Free to Professional plans. Sites on a plan that doesn't can remove it with the sloc_weather_credit filter.
	 *
	 * @since 6.1.0
	 *
	 * @return array Credit. See Weather_Provider::attribution().
	 */
	protected function attribution() {
		return array(
			'text'     => __( 'Weather data provided by OpenWeather', 'simple-location' ),
			'url'      => 'https://openweathermap.org/',
			'adjacent' => false,
		);
	}
}

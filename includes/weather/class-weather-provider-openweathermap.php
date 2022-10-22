<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

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
		$this->description = __( 'Free account offers 1 millions calls per month, requires an API key', 'simple-location' );
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

			$url         = 'https://api.openweathermap.org/data/2.5/onecall?';
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
				// Use an explicit user-agent for Simple Location.
				'user-agent'          => 'Simple Location for WordPress',
			);

			$return = array();

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

			$return['windspeed']  = round( $current['wind_speed'] );
			$return['winddegree'] = round( $current['wind_deg'], 1 );
			$return['windgust']   = ifset_round( $current['wind_gust'], 1 );

			if ( isset( $current['weather'] ) ) {
				if ( wp_is_numeric_array( $current['weather'] ) ) {
					$current['weather'] = $current['weather'][0];
				}
				$return['code']    = (int) $current['weather']['id'];
				$return['summary'] = $current['weather']['description'];
			}

			$return = array_filter( $this->extra_data( $return ) );

			$this->set_cache( $return );

			return $return;
		}
	}

	/**
	 * Return info on the current station.
	 *
	 * @return array Info on Site.
	 */
	public function get_station_data() {
		$data = array(
			'appid' => $this->api,
			'units' => 'metric',
		);
		if ( ! empty( $this->station_id ) ) {
			$conditions = $this->get_cache();
			if ( $conditions ) {
				return $conditions;
			}

			$url                = 'http://api.openweathermap.org/data/3.0/measurements?';
			$data['station_id'] = $this->station_id;
			$data['type']       = 'h';
			// An hour ago.
			$data['from']  = time() - 3600;
			$data['to']    = time();
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
				$return['temperature'] = round( $response['temp']['average'], 1 );
			}
			if ( isset( $response['humidity'] ) ) {
				$return['humidity'] = round( $response['humidity']['average'], 1 );
			}
			if ( isset( $response['wind'] ) ) {
				$return['wind']           = array();
				$return['wind']['speed']  = round( $response['wind']['speed'], 1 );
				$return['wind']['degree'] = round( $response['wind']['deg'], 1 );
			}
			if ( isset( $response['pressure'] ) ) {
				$return['pressure'] = round( $response['pressure']['average'], 1 );
			}
			$return = array_filter( $return );
			$this->set_cache( $return );

			return $return;
		}
		return new WP_Error( 'unable_to_retrieve', __( 'Unable to Retrieve', 'simple-location' ) );
	}
}

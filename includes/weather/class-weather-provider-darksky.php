<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

/**
 * Weather Provider using DarkSky API.
 *
 * @since 1.0.0
 */
class Weather_Provider_DarkSky extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Dark Sky', 'simple-location' );
		$this->slug        = 'darksky';
		$this->url         = 'https://darksky.net';
		$this->description = __( 'The Dark Sky API will continue to function until March 31st, 2023, but no new signups are permitted. Apple has announced the replacement for Dark Sky will be called Apple WeatherKit, but requires an Apple Developer program membership. Dark Sky will be removed when the API ceases to be available', 'simple-location' );
		$this->region      = false;
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_darksky_api' );
		}

		parent::__construct( $args );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'DarkSky API', 'simple-location' ), 'sloc_darksky_api' );
	}

	/**
	 * Admin Load of Settings Fields.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'Dark Sky', 'simple-location' ), 'sloc_darksky_api' );
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
	 * @param int $time Time to retrieve weather. Optional.
	 * @return array Current Conditions in Array
	 */
	public function get_conditions( $time = null ) {
		if ( empty( $this->api ) ) {
			return array();
		}
		$return = array();
		if ( $this->latitude && $this->longitude ) {
			$data = array(
				'units'   => 'si',
				'exclude' => 'minutely,hourly,daily,alerts,flags',
				'lang'    => get_bloginfo( 'language' ),
			);

			$datetime = $this->datetime( $time );

			$url = sprintf( 'https://api.darksky.net/forecast/%1$s/%2$s,%3$s,%4$s', $this->api, $this->latitude, $this->longitude, $datetime->getTimestamp() );
			$url = add_query_arg( $data, $url );

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

			$response = $this->fetch_json( $url, $args );

			if ( WP_DEBUG ) {
				$return['raw'] = $response;
			}
			if ( ! isset( $response['currently'] ) ) {
				return $return;
			}
			$current               = ifset( $response['currently'] );
			$return['temperature'] = ifset_round( $current['temperature'], 1 );
			if ( isset( $current['humidity'] ) ) {
				$return['humidity'] = round( $current['humidity'] * 100, 1 );
			}
			$return['pressure'] = ifset_round( $current['pressure'], 1 );
			if ( isset( $current['cloudCover'] ) ) {
				$return['cloudiness'] = round( $current['cloudCover'] * 100, 1 );
			}
			$return['wind']           = array();
			$return['wind']['speed']  = ifset_round( $current['windSpeed'] );
			$return['wind']['degree'] = ifset_round( $current['windBearing'], 1 );
			$return['wind']['gust']   = ifset_round( $current['windGuest'], 1 );
			$return['uvi']            = ifset( $current['uvIndex'] );
			$return['wind']           = array_filter( $return['wind'] );
			$return['rain']           = ifset_round( $current['precipIntensity'], 2 );
			$return['snow']           = ifset_round( $current['precipAccumulation'], 2 );
			$return['summary']        = ifset( $current['summary'] );
			$return['icon']           = $this->icon_map( ifset( $current['icon'] ) );
			if ( isset( $current['visibility'] ) ) {
				$return['visibility'] = round( $current['visibility'] * 1000, 1 );
			}

			$return = $this->extra_data( $return, $datetime );

			return array_filter( $return );
		}
		return false;
	}

	/**
	 * Return array of station data.
	 *
	 * @param string $id Weather type ID.
	 * @return string Icon ID.
	 */
	private function icon_map( $id ) {
		switch ( $id ) {
			case 'clear-day':
				return 'wi-day-sunny';
			case 'clear-night':
				return 'wi-night-clear';
			case 'rain':
				return 'wi-rain';
			case 'snow':
				return 'wi-snow';
			case 'sleet':
				return 'wi-sleet';
			case 'wind':
				return 'wi-windy';
			case 'fog':
				return 'wi-fog';
			case 'cloudy':
				return 'wi-cloudy';
			case 'partly-cloudy-day':
				return 'wi-day-cloudy';
			case 'partly-cloudy-night':
				return 'wi-night-cloudy';
			case 'hail':
				return 'wi-hail';
			case 'thunderstorm':
				return 'wi-thunderstorm';
			case 'tornado':
				return 'wi-tornado';
			default:
				return '';
		}
	}

}

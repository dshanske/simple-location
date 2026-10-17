<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

/**
 * Weather Provider using Pirate Weather API.
 *
 * @since 1.0.0
 */
class Weather_Provider_PirateWeather extends Weather_Provider {

	/**
	 * Constructor
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Pirate Weather', 'simple-location' );
		$this->slug        = 'pirateweather';
		$this->url         = 'https://pirateweather.net';
		$this->description = __( 'Pirate Weather is a service that reads public weather forecasts. It is offered for free by the developer, but donations are appreciated. Historic weather data is limited.', 'simple-location' );
		$this->region      = false;
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_pirateweather_api' );
		}

		parent::__construct( $args );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'PirateWeather API Key', 'simple-location' ), 'sloc_pirateweather_api' );
	}

	/**
	 * Admin Load of Settings Fields.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'Pirate Weather', 'simple-location' ), 'sloc_pirateweather_api' );
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
	 * @since 5.0.26 Times more than an hour ago are requested from the Time Machine API.
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
				'lang'    => substr( get_bloginfo( 'language' ), 0, 2 ),
			);

			$datetime = $this->datetime( $time );

			if ( $time ) {
				// Past weather comes from the Time Machine host; the forecast host serves current and future times.
				$host = ( HOUR_IN_SECONDS < time() - $datetime->getTimestamp() ) ? 'timemachine' : 'api';
				$url  = sprintf( 'https://%1$s.pirateweather.net/forecast/%2$s/%3$s,%4$s,%5$s', $host, $this->api, $this->latitude, $this->longitude, $datetime->getTimestamp() );
			} else {
				$url = sprintf( 'https://api.pirateweather.net/forecast/%1$s/%2$s,%3$s', $this->api, $this->latitude, $this->longitude );
			}

			$response = $this->fetch_json( $url, $data );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

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
			$return['windspeed']  = ifset_round( $current['windSpeed'] );
			$return['winddegree'] = ifset_round( $current['windBearing'], 1 );
			$return['windgust']   = ifset_round( $current['windGust'], 1 );
			$return['uv']         = ifset_round( $current['uvIndex'], 1 );
			$return['rain']       = ifset_round( $current['precipIntensity'], 2 );
			$return['snow']       = ifset_round( $current['precipAccumulation'], 2 );
			$return['summary']    = ifset( $current['summary'] );
			$return['code']       = $this->code_map( ifset( $current['icon'] ) );
			if ( isset( $current['visibility'] ) ) {
				$return['visibility'] = round( $current['visibility'] * 1000, 1 );
			}

			$return = $this->extra_data( $return, $time );

			return self::filter_weather( $return );
		}
		return false;
	}

	/**
	 * Return array of station data.
	 *
	 * @param string $id Weather type icon.
	 * @return string code id.
	 */
	private function code_map( $id ) {
		switch ( $id ) {
			case 'clear-day':
			case 'clear-night':
				return 800;
			case 'rain':
				return 500;
			case 'snow':
				return 601;
			case 'sleet':
				return 611;
			case 'wind':
				return '';
			case 'fog':
				return 741;
			case 'cloudy':
				return 804;
			case 'partly-cloudy-day':
			case 'partly-cloudy-night':
				return 803;
			case 'hail':
				return;
			case 'thunderstorm':
				return 211;
			case 'tornado':
				return 781;
			default:
				return '';
		}
	}
}

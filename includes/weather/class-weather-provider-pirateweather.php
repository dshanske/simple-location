<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

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
	 * @since 6.0.0 Times more than an hour ago are requested from the Time Machine API.
	 * @since 6.1.0 Includes the dew point.
	 *
	 * @param int $time Time to retrieve weather. Optional.
	 * @return array Current Conditions in Array
	 */
	public function get_conditions( $time = null ) {
		if ( empty( $this->api ) ) {
			return $this->missing_api_key_error();
		}
		$return = array();
		if ( $this->latitude && $this->longitude ) {
			$data = array(
				'units'   => 'si',
				'exclude' => 'minutely,hourly,daily,alerts,flags', // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- A Pirate Weather API parameter, not a query argument.
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
			$current               = $response['currently'] ?? null;
			$return['temperature'] = self::round_value( $current['temperature'] ?? null, 1 );
			$return['apptemp']     = self::round_value( $current['apparentTemperature'] ?? null, 1 );
			if ( isset( $current['humidity'] ) ) {
				$return['humidity'] = round( $current['humidity'] * 100, 1 );
			}
			$return['dewpoint'] = self::round_value( $current['dewPoint'] ?? null, 1 );
			$return['pressure'] = self::round_value( $current['pressure'] ?? null, 1 );
			if ( isset( $current['cloudCover'] ) ) {
				$return['cloudiness'] = round( $current['cloudCover'] * 100, 1 );
			}
			$return['windspeed']  = self::round_value( $current['windSpeed'] ?? null );
			$return['winddegree'] = self::round_value( $current['windBearing'] ?? null, 1 );
			$return['windgust']   = self::round_value( $current['windGust'] ?? null, 1 );
			$return['uv']         = self::round_value( $current['uvIndex'] ?? null, 1 );
			$return['summary']    = $current['summary'] ?? null;
			$return['code']       = $this->code_map( $current['icon'] ?? '' );
			if ( isset( $current['visibility'] ) ) {
				$return['visibility'] = round( $current['visibility'] * 1000, 1 );
			}
			$return = array_merge( $return, $this->precipitation( $current ) );

			$return = $this->extra_data( $return, $time );

			return self::filter_weather( $return );
		}
		return false;
	}

	/**
	 * Return rain or snow from the precipitation rate.
	 *
	 * The rate is always the liquid water equivalent in mm/h. Snow is stored as depth, using the
	 * 10:1 ratio Pirate Weather uses for current conditions; sleet, ice and mixed precipitation
	 * are stored as rain.
	 *
	 * @since 6.0.0
	 *
	 * @param array $current Current conditions.
	 * @return array Rain or snow in mm, or an empty array without a rate.
	 */
	private function precipitation( $current ) {
		if ( ! isset( $current['precipIntensity'] ) || ! is_numeric( $current['precipIntensity'] ) ) {
			return array();
		}
		$intensity = round( (float) $current['precipIntensity'], 2 );
		if ( 'snow' === ( $current['precipType'] ?? null ) ) {
			return array( 'snow' => round( $intensity * 10, 2 ) );
		}
		return array( 'rain' => $intensity );
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

	// No credit: no attribution requirement was found in Pirate Weather's documentation (checked 2026-10-22). Add one with the sloc_weather_credit filter.
}

<?php
/**
 * Weather Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Weather Provider using Open-Meteo, from the public API, the commercial API or a self-hosted server.
 *
 * Current conditions come from the forecast API. Past times come from the forecast API's hourly data for the
 * last ARCHIVE_AFTER days, and from the Historical Weather API before that.
 *
 * @since 6.1.0
 */
class Weather_Provider_OpenMeteo extends Weather_Provider {

	/**
	 * Days back from now that the forecast API's hourly data is used for; older times use the archive.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const ARCHIVE_AFTER = 90;

	/**
	 * Variables asked for, as current conditions or for an hour. The archive returns null for those it doesn't have.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const VARIABLES = array(
		'temperature_2m',
		'apparent_temperature',
		'relative_humidity_2m',
		'dew_point_2m',
		'rain',
		'showers',
		'snowfall',
		'snow_depth',
		'weather_code',
		'cloud_cover',
		'pressure_msl',
		'wind_speed_10m',
		'wind_direction_10m',
		'wind_gusts_10m',
		'visibility',
		'uv_index',
		'shortwave_radiation',
	);

	/**
	 * Server URL of a self-hosted Open-Meteo, without a trailing slash, or an empty string for Open-Meteo's own API.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $server = '';

	/**
	 * Constructor
	 *
	 * @since 6.1.0
	 *
	 * @param array $args {
	 *     Arguments. See Weather_Provider::__construct() for the others.
	 *
	 *     @type string $api    Commercial API key. Default the sloc_openmeteo_api setting. Without one, the free API is used.
	 *     @type string $server Self-hosted server URL. Default the sloc_openmeteo_url setting.
	 * }
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Open-Meteo', 'simple-location' );
		$this->url         = 'https://open-meteo.com/';
		$this->description = __( 'Open-Meteo is an open source weather API with current and historical weather. Its free API needs no key for non-commercial use, up to 10,000 requests a day; commercial use needs an API key. You can also run your own server. Weather data by Open-Meteo.com is licensed CC BY 4.0.', 'simple-location' );
		$this->slug        = 'openmeteo';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_openmeteo_api' );
		}
		$server       = self::sanitize_url( $args['server'] ?? get_option( 'sloc_openmeteo_url' ) );
		$this->server = untrailingslashit( $server );
		parent::__construct( $args );
	}

	/**
	 * Sanitizes the server URL setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Server URL.
	 * @return string HTTP or HTTPS URL, or an empty string for Open-Meteo's own API.
	 */
	public static function sanitize_url( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return (string) esc_url_raw( trim( $value ), array( 'http', 'https' ) );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 6.1.0
	 */
	public static function init() {
		self::register_settings_api( __( 'Open-Meteo', 'simple-location' ), 'sloc_openmeteo_api' );
		register_setting(
			'sloc_api',
			'sloc_openmeteo_url',
			array(
				'type'              => 'string',
				'description'       => 'Open-Meteo Server URL',
				'show_in_rest'      => false,
				'default'           => '',
				'sanitize_callback' => array( __CLASS__, 'sanitize_url' ),
			)
		);
	}

	/**
	 * Admin Init to Add Settings Field.
	 *
	 * @since 6.1.0
	 */
	public static function admin_init() {
		add_settings_field(
			'sloc_openmeteo_api',
			__( 'Open-Meteo API Key', 'simple-location' ),
			array( 'Loc_Config', 'string_callback' ),
			'sloc_api',
			'sloc_api',
			array(
				'label_for'   => 'sloc_openmeteo_api',
				'type'        => 'password',
				'description' => __( 'Optional. Only needed for commercial use. Leave empty to use the free API.', 'simple-location' ),
			)
		);
		add_settings_field(
			'sloc_openmeteo_url',
			__( 'Open-Meteo Server URL', 'simple-location' ),
			array( 'Loc_Config', 'string_callback' ),
			'sloc_api',
			'sloc_api',
			array(
				'label_for'   => 'sloc_openmeteo_url',
				'type'        => 'url',
				'description' => __( 'Optional. The address of your own Open-Meteo server. Leave empty to use Open-Meteo\'s API.', 'simple-location' ),
			)
		);
	}

	/**
	 * Does This Provider Offer Station Data.
	 *
	 * @since 6.1.0
	 *
	 * @return boolean False, as Open-Meteo's weather is modelled for a location.
	 */
	public function is_station() {
		return false;
	}

	/**
	 * Returns the URL of one of Open-Meteo's APIs.
	 *
	 * @since 6.1.0
	 *
	 * @param string $api 'forecast' or 'archive'.
	 * @return string URL.
	 */
	protected function get_endpoint( $api ) {
		if ( '' !== $this->server ) {
			return $this->server . '/v1/' . $api;
		}
		// Commercial keys use the customer hosts.
		$host = ( 'archive' === $api ) ? 'archive-api.open-meteo.com' : 'api.open-meteo.com';
		if ( ! empty( $this->api ) ) {
			$host = 'customer-' . $host;
		}
		return 'https://' . $host . '/v1/' . $api;
	}

	/**
	 * Return array of current conditions, or the conditions at a time.
	 *
	 * @since 6.1.0
	 *
	 * @param string|int|DateTime|null $time Time. Optional. Default now.
	 * @return array|WP_Error Conditions, or an error.
	 */
	public function get_conditions( $time = null ) {
		if ( ! is_numeric( $this->latitude ) || ! is_numeric( $this->longitude ) ) {
			return array();
		}
		if ( is_null( $time ) || 0 === $time ) {
			$time = time();
		}
		$timestamp = $this->datetime( $time )->getTimestamp();

		$conditions = $this->get_cache( $time );
		if ( $conditions ) {
			return $conditions;
		}

		$args = array(
			'latitude'        => $this->latitude,
			'longitude'       => $this->longitude,
			'wind_speed_unit' => 'ms',
			'timeformat'      => 'unixtime',
		);
		if ( ! empty( $this->api ) ) {
			$args['apikey'] = $this->api;
		}

		$current = ( HOUR_IN_SECONDS >= abs( $timestamp - time() ) );
		if ( $current ) {
			$args['current'] = implode( ',', self::VARIABLES );
			$url             = $this->get_endpoint( 'forecast' );
		} else {
			// The nearest hour, and the UTC day it is in.
			$hour               = (int) ( round( $timestamp / HOUR_IN_SECONDS ) * HOUR_IN_SECONDS );
			$args['hourly']     = implode( ',', self::VARIABLES );
			$args['start_date'] = gmdate( 'Y-m-d', $hour );
			$args['end_date']   = $args['start_date'];
			$url                = $this->get_endpoint( ( time() - $hour > self::ARCHIVE_AFTER * DAY_IN_SECONDS ) ? 'archive' : 'forecast' );
		}

		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return self::api_error( $json );
		}

		if ( $current ) {
			$values = ( isset( $json['current'] ) && is_array( $json['current'] ) ) ? $json['current'] : array();
		} else {
			$values = self::hour_values( $json, $hour );
		}
		if ( ! isset( $values['temperature_2m'] ) && ! isset( $values['weather_code'] ) ) {
			return new WP_Error( 'no_results', __( 'Open-Meteo has no weather for this time and place', 'simple-location' ), array( 'status' => 404 ) );
		}

		$return = $this->convert_data( $values );
		$return = $this->extra_data( $return, $timestamp );

		$this->set_cache( $return, $time );

		if ( WP_DEBUG ) {
			$return['raw'] = $values;
		}

		return $return;
	}

	/**
	 * Fetches the hourly weather between two times, in one request, at the provider's location.
	 *
	 * Used to fill in readings for many posts at one place: each post then takes its hour with conditions_at(). The
	 * archive is used when the whole range is older than ARCHIVE_AFTER days, and the forecast API's hourly data
	 * otherwise.
	 *
	 * @since 6.2.0
	 *
	 * @param int $start Unix timestamp of the first hour.
	 * @param int $end   Unix timestamp of the last hour.
	 * @return array|WP_Error Hourly response, for conditions_at(), or an error.
	 */
	public function fetch_range( $start, $end ) {
		if ( ! is_numeric( $this->latitude ) || ! is_numeric( $this->longitude ) ) {
			return new WP_Error( 'no_location', __( 'A location is needed for the weather', 'simple-location' ) );
		}
		$args = array(
			'latitude'        => $this->latitude,
			'longitude'       => $this->longitude,
			'wind_speed_unit' => 'ms',
			'timeformat'      => 'unixtime',
			'hourly'          => implode( ',', self::VARIABLES ),
			'start_date'      => gmdate( 'Y-m-d', (int) $start ),
			'end_date'        => gmdate( 'Y-m-d', (int) $end ),
		);
		if ( ! empty( $this->api ) ) {
			$args['apikey'] = $this->api;
		}
		$api  = ( time() - (int) $end > self::ARCHIVE_AFTER * DAY_IN_SECONDS ) ? 'archive' : 'forecast';
		$json = $this->fetch_json( $this->get_endpoint( $api ), $args );
		return is_wp_error( $json ) ? self::api_error( $json ) : $json;
	}

	/**
	 * Returns the conditions of the hour nearest a time, from a response of fetch_range().
	 *
	 * Only what Open-Meteo gives, without the values extra_data() works out, such as a condition from the sunlight.
	 *
	 * @since 6.2.0
	 *
	 * @param array $json      Response from fetch_range().
	 * @param int   $timestamp Unix timestamp.
	 * @return array Conditions, or an empty array if the response doesn't have that hour.
	 */
	public function conditions_at( $json, $timestamp ) {
		$values = self::hour_values( $json, (int) ( round( (int) $timestamp / HOUR_IN_SECONDS ) * HOUR_IN_SECONDS ) );
		return empty( $values ) ? array() : $this->convert_data( $values );
	}

	/**
	 * Returns Open-Meteo's explanation of a refused request, when the response has one.
	 *
	 * @since 6.1.0
	 *
	 * @param WP_Error $error Error from fetch_json().
	 * @return WP_Error Error with Open-Meteo's reason, or the error unchanged.
	 */
	protected static function api_error( $error ) {
		$data = $error->get_error_data();
		$body = is_array( $data ) ? json_decode( $data['body'] ?? '', true ) : null;
		if ( is_array( $body ) && ! empty( $body['reason'] ) && is_string( $body['reason'] ) ) {
			return new WP_Error( 'openmeteo_error', $body['reason'], array( 'status' => $data['status'] ?? 0 ) );
		}
		return $error;
	}

	/**
	 * Returns the values of one hour from an hourly response.
	 *
	 * @since 6.1.0
	 *
	 * @param array $json Response, with hourly values in arrays and their times as Unix timestamps.
	 * @param int   $hour Timestamp of the hour.
	 * @return array Values keyed by variable, or an empty array if the response doesn't have the hour.
	 */
	protected static function hour_values( $json, $hour ) {
		$hourly = ( isset( $json['hourly'] ) && is_array( $json['hourly'] ) ) ? $json['hourly'] : array();
		$times  = ( isset( $hourly['time'] ) && is_array( $hourly['time'] ) ) ? array_map( 'intval', $hourly['time'] ) : array();
		$index  = array_search( $hour, $times, true );
		if ( false === $index ) {
			return array();
		}
		$values = array();
		foreach ( self::VARIABLES as $variable ) {
			$values[ $variable ] = ( isset( $hourly[ $variable ] ) && is_array( $hourly[ $variable ] ) ) ? ( $hourly[ $variable ][ $index ] ?? null ) : null;
		}
		return $values;
	}

	/**
	 * Convert Data into common format
	 *
	 * Wind is asked for in meters per second; Open-Meteo gives snowfall in centimeters and snow depth in meters.
	 *
	 * @since 6.1.0
	 * @since 6.2.0 Includes the snow depth.
	 * @since 6.3.0 Includes the apparent temperature.
	 *
	 * @param array $json Values keyed by Open-Meteo variable.
	 * @return array Conditions.
	 */
	public function convert_data( $json ) {
		$return                = array();
		$return['temperature'] = self::round_value( $json['temperature_2m'] ?? null, 1 );
		$return['apptemp']     = self::round_value( $json['apparent_temperature'] ?? null, 1 );
		$return['dewpoint']    = self::round_value( $json['dew_point_2m'] ?? null, 1 );
		$return['humidity']    = self::round_value( $json['relative_humidity_2m'] ?? null, 1 );
		$return['pressure']    = self::round_value( $json['pressure_msl'] ?? null, 1 );
		$return['cloudiness']  = $json['cloud_cover'] ?? null;
		$return['windspeed']   = self::round_value( $json['wind_speed_10m'] ?? null, 1 );
		$return['windgust']    = self::round_value( $json['wind_gusts_10m'] ?? null, 1 );
		$return['winddegree']  = self::round_value( $json['wind_direction_10m'] ?? null, 1 );
		$return['visibility']  = self::round_value( $json['visibility'] ?? null );
		$return['uv']          = self::round_value( $json['uv_index'] ?? null, 1 );
		$return['radiation']   = self::round_value( $json['shortwave_radiation'] ?? null, 2 );

		// Showers are rain from convective clouds, which Open-Meteo counts apart from other rain.
		if ( is_numeric( $json['rain'] ?? null ) || is_numeric( $json['showers'] ?? null ) ) {
			$return['rain'] = round( (float) ( $json['rain'] ?? 0 ) + (float) ( $json['showers'] ?? 0 ), 2 );
		}
		$return['snow'] = self::cm_to_mm( $json['snowfall'] ?? null );
		if ( is_numeric( $json['snow_depth'] ?? null ) ) {
			$return['snowdepth'] = round( (float) $json['snow_depth'] * 1000, 1 );
		}

		if ( is_numeric( $json['weather_code'] ?? null ) ) {
			$wmo               = (int) $json['weather_code'];
			$return['code']    = self::code_map( $wmo );
			$return['summary'] = self::wmo_summary( $wmo );
		}

		return self::filter_weather( $return );
	}

	/**
	 * Returns the description of a WMO weather interpretation code, as Open-Meteo documents them.
	 *
	 * @since 6.1.0
	 *
	 * @param int $code WMO code.
	 * @return string Description, or an empty string for an unknown code.
	 */
	public static function wmo_summary( $code ) {
		$summaries = array(
			0  => __( 'Clear sky', 'simple-location' ),
			1  => __( 'Mainly clear', 'simple-location' ),
			2  => __( 'Partly cloudy', 'simple-location' ),
			3  => __( 'Overcast', 'simple-location' ),
			45 => __( 'Fog', 'simple-location' ),
			48 => __( 'Depositing rime fog', 'simple-location' ),
			51 => __( 'Light drizzle', 'simple-location' ),
			53 => __( 'Moderate drizzle', 'simple-location' ),
			55 => __( 'Dense drizzle', 'simple-location' ),
			56 => __( 'Light freezing drizzle', 'simple-location' ),
			57 => __( 'Dense freezing drizzle', 'simple-location' ),
			61 => __( 'Slight rain', 'simple-location' ),
			63 => __( 'Moderate rain', 'simple-location' ),
			65 => __( 'Heavy rain', 'simple-location' ),
			66 => __( 'Light freezing rain', 'simple-location' ),
			67 => __( 'Heavy freezing rain', 'simple-location' ),
			71 => __( 'Slight snow fall', 'simple-location' ),
			73 => __( 'Moderate snow fall', 'simple-location' ),
			75 => __( 'Heavy snow fall', 'simple-location' ),
			77 => __( 'Snow grains', 'simple-location' ),
			80 => __( 'Slight rain showers', 'simple-location' ),
			81 => __( 'Moderate rain showers', 'simple-location' ),
			82 => __( 'Violent rain showers', 'simple-location' ),
			85 => __( 'Slight snow showers', 'simple-location' ),
			86 => __( 'Heavy snow showers', 'simple-location' ),
			95 => __( 'Thunderstorm', 'simple-location' ),
			96 => __( 'Thunderstorm with slight hail', 'simple-location' ),
			99 => __( 'Thunderstorm with heavy hail', 'simple-location' ),
		);
		return $summaries[ $code ] ?? '';
	}

	/**
	 * Converts a WMO weather interpretation code to a condition code.
	 *
	 * @since 6.1.0
	 *
	 * @param int $code WMO code.
	 * @return int|string Condition code, or an empty string for an unknown code.
	 */
	public static function code_map( $code ) {
		$codes = array(
			0  => 800,
			1  => 801,
			2  => 802,
			3  => 804,
			45 => 741,
			48 => 741,
			51 => 300,
			53 => 301,
			55 => 302,
			56 => 309,
			57 => 309,
			61 => 500,
			63 => 503,
			65 => 504,
			66 => 511,
			67 => 511,
			71 => 600,
			73 => 601,
			75 => 602,
			77 => 600,
			80 => 520,
			81 => 521,
			82 => 522,
			85 => 620,
			86 => 622,
			95 => 211,
			96 => 233,
			99 => 233,
		);
		return $codes[ $code ] ?? '';
	}

	/**
	 * Returns the credit for the provider's weather.
	 *
	 * Open-Meteo's licence requires a link next to wherever its data is shown.
	 *
	 * @since 6.1.0
	 *
	 * @return array Credit. See Weather_Provider::attribution().
	 */
	protected function attribution() {
		return array(
			'text'     => __( 'Weather data by Open-Meteo.com', 'simple-location' ),
			'url'      => 'https://open-meteo.com/',
			'adjacent' => true,
		);
	}
}

<?php
/**
 * Base Provider Class.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Abstract Class to Provide Basic Functionality for Providers.
 *
 * @since 1.0.0
 */
abstract class Sloc_Provider {

	/**
	 * Provider Slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $slug;

	/**
	 * Provider Name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $name;

	/**
	 * Provider Description.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $description;

	/**
	 * Provider URL.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * Provider API Key.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $api;

	/**
	 * Username if Applicable.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	protected $user;

	/**
	 * Region.
	 *
	 * If null applies to all regions.
	 * Can also be a string or array of strings representing two letter country codes.
	 *
	 * @since 4.0.7
	 * @var string|array|null
	 */
	protected $region;

	/**
	 * Latitude.
	 *
	 * @since 1.0.0
	 * @var float
	 */
	protected $latitude;

	/**
	 * Longitude.
	 *
	 * @since 1.0.0
	 * @var float
	 */
	protected $longitude;

	/**
	 * Altitude.
	 *
	 *  Denotes the height of the position, specified in meters above the [WGS84] ellipsoid. If the implementation cannot provide altitude information, the value of this attribute must be null.
	 *
	 * @since 1.0.0
	 * @var float
	 */
	protected $altitude;

	/**
	 * Constructor for the Abstract Class.
	 *
	 * The default version of this just sets the parameters.
	 *
	 * @param array $args {
	 *  Arguments.
	 *  @type string $api API Key.
	 *  @type float $latitude Latitude.
	 *  @type float $longitude Longitude.
	 *  @type float $altitude Altitude.
	 */
	public function __construct( $args = array() ) {
		$defaults  = array(
			'api'       => null,
			'latitude'  => null,
			'longitude' => null,
			'altitude'  => null,
		);
		$r         = wp_parse_args( $args, $defaults );
		$this->api = $r['api'];
		$this->set( $r['latitude'], $r['longitude'] );
	}


	/**
	 * Fetches JSON from a remote endpoint.
	 *
	 * Errors for a failed or non-JSON response have a short message, with the HTTP status and the start of the
	 * response as plain text, and keep the full response in their data as 'body'. API keys and other credentials
	 * sent with the request are removed from both, because some services echo the request back.
	 *
	 * @param string $url URL to fetch.
	 * @param array  $query Query parameters.
	 * @param array  $headers Headers.
	 * @return WP_Error|array Either the associated array response or error.
	 *
	 * @since 4.0.6
	 * @since 6.0.0 Error messages are short and plain text, without credentials; the response is in the error data.
	 * @since 6.0.0 JSON that isn't an object or array, such as `true`, is an error.
	 */
	public function fetch_json( $url, $query, $headers = null ) {
		$fetch = add_query_arg( $query, $url );
		$args  = array(
			'headers'             => array(
				'Accept' => 'application/json',
			),
			'timeout'             => 10,
			'limit_response_size' => 1048576,
			'redirection'         => 1,
			// Use an explicit user-agent for Simple Location.
			'user-agent'          => 'Simple Location for WordPress',
		);

		if ( is_array( $headers ) ) {
			$args['headers'] = array_merge( $args['headers'], $headers );
		}

		/* Disable development sites for now */
		$response = wp_safe_remote_get( $fetch, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 ) {
			$body    = $this->redact( $body, $query );
			$excerpt = self::excerpt( $body );
			return new WP_Error(
				'invalid_response',
				'' === $excerpt
					/* translators: %d: HTTP status code. */
					? sprintf( __( 'The service returned HTTP status %d.', 'simple-location' ), $code )
					/* translators: 1: HTTP status code 2: Start of the response. */
					: sprintf( __( 'The service returned HTTP status %1$d: %2$s', 'simple-location' ), $code, $excerpt ),
				array(
					'status' => $code,
					'body'   => $body,
				)
			);
		}
		$json = json_decode( $body, true );
		if ( empty( $json ) ) {
			$body    = $this->redact( $body, $query );
			$excerpt = self::excerpt( $body );
			return new WP_Error(
				'not_json_response',
				'' === $excerpt
					? __( 'The service did not return JSON.', 'simple-location' )
					/* translators: %s: Start of the response. */
					: sprintf( __( 'The service did not return JSON: %s', 'simple-location' ), $excerpt ),
				array(
					'type' => wp_remote_retrieve_header( $response, 'Content-Type' ),
					'body' => $body,
				)
			);
		}
		if ( ! is_array( $json ) ) {
			return new WP_Error(
				'unexpected_response',
				__( 'The service returned a response the plugin could not use.', 'simple-location' ),
				array(
					'type' => wp_remote_retrieve_header( $response, 'Content-Type' ),
					'body' => $this->redact( $body, $query ),
				)
			);
		}
		return $json;
	}

	/**
	 * Removes credentials from text returned by a service.
	 *
	 * Removes the provider's API key, and the values of request parameters that hold credentials, such as key,
	 * token, secret, client_id or appid, in plain and URL-encoded form.
	 *
	 * @since 6.0.0
	 *
	 * @param string $text  Text.
	 * @param array  $query Request query parameters.
	 * @return string Text without credentials.
	 */
	protected function redact( $text, $query = array() ) {
		$secrets = array();
		if ( is_string( $this->api ) && '' !== $this->api ) {
			$secrets[] = $this->api;
		}
		if ( is_array( $query ) ) {
			foreach ( $query as $name => $value ) {
				if ( is_scalar( $value ) && '' !== (string) $value && preg_match( '/key|token|secret|client_id|appid|password|signature/i', (string) $name ) ) {
					$secrets[] = (string) $value;
				}
			}
		}
		foreach ( array_unique( $secrets ) as $secret ) {
			$text = str_replace( array( $secret, rawurlencode( $secret ), urlencode( $secret ) ), '[redacted]', $text ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- Both encodings may appear in echoed URLs.
		}
		return $text;
	}

	/**
	 * Returns the start of a service's response as one line of plain text.
	 *
	 * @since 6.0.0
	 *
	 * @param string $text Response body.
	 * @return string Up to 200 characters.
	 */
	protected static function excerpt( $text ) {
		// Tags become spaces, so words on either side of them stay apart.
		$text = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', ' ', (string) $text );
		$text = preg_replace( '/<[^>]*>/', ' ', (string) $text );
		$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		if ( mb_strlen( $text ) > 200 ) {
			$text = rtrim( mb_substr( $text, 0, 199 ) ) . '…';
		}
		return $text;
	}

	/**
	 * Given a list of keys returns the first matching one.
	 *
	 * @param array $array Array of associative data.
	 * @param array $keys List of keys to search for.
	 * @return mixed|null Return either null or the value of the first key found.
	 *
	 * @since 4.0.0
	 */
	public static function ifnot( $array, $keys ) {
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $array ) && ! empty( $array[ $key ] ) ) {
				return $array[ $key ];
			}
		}
		return null;
	}

	/**
	 * Returns the name property.
	 *
	 * @return string $name Returns name.
	 *
	 * @since 1.0.0
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Returns the url property.
	 *
	 * @return string $name Returns name.
	 *
	 * @since 5.0.0
	 */
	public function get_url() {
		return $this->url;
	}


	/**
	 * Returns the desciption property.
	 *
	 * @return string $description Returns description.
	 *
	 * @since 1.0.0
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Is Provider Active
	 */
	abstract public function is_active();

	/**
	 * Returns the slug property.
	 *
	 * @return string $slug Slug.
	 *
	 * @since 1.0.0
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Sanitizes API Keys and Similar.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $string Value.
	 * @return string|false Trimmed value without slashes, or false if it isn't a string.
	 */
	public static function sanitize_api_key( $string ) {
		if ( ! is_string( $string ) ) {
			return false;
		}

		return stripslashes( trim( $string ) );
	}

	/**
	 * Adds a password field for a credential setting to the API settings page.
	 *
	 * @since 5.0.0
	 *
	 * @param string      $name     Name of the service.
	 * @param string      $property Option name.
	 * @param string|null $type     Kind of setting, for the title. Default 'API Key'.
	 */
	public static function add_settings_parameter( $name, $property, $type = null ) {
		if ( ! $type ) {
			$type = __( 'API Key', 'simple-location' );
		}

		add_settings_field(
			$property, // ID.
			// translators: 1. Name of Service 2. Type of Property
			sprintf( __( '%1$s %2$s', 'simple-location' ), $name, $type ), // Setting title.
			array( 'Loc_Config', 'string_callback' ), // Display callback.
			'sloc_api', // Settings page.
			'sloc_api', // Settings section.
			array(
				'label_for' => $property,
				'type'      => 'password',
			)
		);
	}

	/**
	 * Adds a URL field for a setting to the API settings page.
	 *
	 * @since 5.0.0
	 *
	 * @param string      $name     Name of the service.
	 * @param string      $property Option name.
	 * @param string|null $type     Kind of setting, for the title. Default 'Service URL'.
	 */
	public static function add_settings_url_parameter( $name, $property, $type = null ) {
		if ( ! $type ) {
			$type = __( 'Service URL', 'simple-location' );
		}

		add_settings_field(
			$property, // ID.
			// translators: 1. Name of Service 2. Type of Property
			sprintf( __( '%1$s %2$s', 'simple-location' ), $name, $type ), // Setting title.
			array( 'Loc_Config', 'string_callback' ), // Display callback.
			'sloc_api', // Settings page.
			'sloc_api', // Settings section.
			array(
				'label_for' => $property,
				'type'      => 'url',
			)
		);
	}



	/**
	 * Registers a setting for credential based information, such as an API key.
	 *
	 * @since 5.0.0
	 *
	 * @param string      $name     Name of the service.
	 * @param string      $property Option name.
	 * @param string|null $type     Kind of setting, for the title. Default 'API Key'.
	 */
	public static function register_settings_api( $name, $property, $type = null ) {
		if ( ! $type ) {
			$type = __( 'API Key', 'simple-location' );
		}

		$args = array(
			'type'              => 'string',
			// translators: 1. Name of Service 2. Type of Property
			'description'       => sprintf( __( '%1$s %2$s', 'simple-location' ), $name, $type ),
			'show_in_rest'      => false,
			'default'           => '',
			'sanitize_callback' => array( __CLASS__, 'sanitize_api_key' ),
		);

		register_setting(
			'sloc_api', // Option group.
			$property, // Option name.
			$args
		);
	}

	/**
	 * Set and Validate Coordinates.
	 *
	 * @param array|float $lat Latitude or array of all three properties.
	 * @param float       $lng Longitude. Optional if first property is an array.
	 * @param float       $alt Altitude. Optional.
	 * @return boolean Return False if Validation Failed
	 *
	 * @since 6.0.0 Invalid or out-of-range coordinates return false and leave the coordinates unchanged.
	 */
	public function set( $lat, $lng = null, $alt = null ) {
		if ( null === $lng && is_array( $lat ) ) {
			if ( ! isset( $lat['latitude'] ) || ! isset( $lat['longitude'] ) ) {
				return false;
			}
			$alt = isset( $lat['altitude'] ) ? $lat['altitude'] : null;
			$lng = $lat['longitude'];
			$lat = $lat['latitude'];
		}
		// Validate inputs.
		$latitude  = clean_coordinate( $lat, 'latitude' );
		$longitude = clean_coordinate( $lng, 'longitude' );
		if ( false === $latitude || false === $longitude ) {
			return false;
		}
		$this->latitude  = $latitude;
		$this->longitude = $longitude;
		if ( is_numeric( $alt ) ) {
			$this->altitude = floatval( $alt );
		}
		return true;
	}

	/**
	 * Get Coordinates.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Values of 0 are kept.
	 *
	 * @return array|boolean Array with Latitude and Longitude false if null
	 */
	public function get() {
		$return              = array();
		$return['latitude']  = $this->latitude;
		$return['longitude'] = $this->longitude;
		$return['altitude']  = $this->altitude;
		$return              = self::filter_unset( $return );
		if ( ! empty( $return ) ) {
			return $return;
		}
		return false;
	}

	/**
	 * Removes unset values from an array.
	 *
	 * Unlike array_filter() without a callback, this keeps 0, 0.0 and '0', which are
	 * real values for coordinates, altitude, heading and speed.
	 *
	 * @since 6.0.0
	 *
	 * @param array $values Values to filter.
	 * @return array Values that are not null, an empty string or an empty array.
	 */
	protected static function filter_unset( $values ) {
		return array_filter(
			$values,
			fn( $value ) => null !== $value && '' !== $value && array() !== $value
		);
	}

	/**
	 * Converts millimeters to inches.
	 *
	 * @since 6.0.0 Returns null for a value that isn't a number, instead of 0.
	 *
	 * @param float $mm Millimeters.
	 * @return float|null Inches, or null if the value isn't a number.
	 */
	public static function mm_to_inches( $mm ) {
		if ( ! is_numeric( $mm ) ) {
			return null;
		}
		return $mm / 25.4;
	}

	/**
	 * Converts inches to millimeters.
	 *
	 * @since 6.0.0 Returns null for a value that isn't a number, instead of 0.
	 *
	 * @param float $inch Inches.
	 * @return float|null Millimeters, or null if the value isn't a number.
	 */
	public static function inches_to_mm( $inch ) {
		if ( ! is_numeric( $inch ) ) {
			return null;
		}
		return round( $inch * 25.4, 2 );
	}


	/**
	 * Converts feet to meters.
	 *
	 * @since 6.0.0 Returns null for a value that isn't a number, instead of 0.
	 *
	 * @param float $feet Feet.
	 * @return float|null Meters, or null if the value isn't a number.
	 */
	public static function feet_to_meters( $feet ) {
		if ( ! is_numeric( $feet ) ) {
			return null;
		}
		return round( $feet / 3.2808399, 2 );
	}


	/**
	 * Converts meters to feet.
	 *
	 * @since 6.0.0 Returns null for a value that isn't a number, instead of 0.
	 *
	 * @param float $meters Meters.
	 * @return float|null Feet, or null if the value isn't a number.
	 */
	public static function meters_to_feet( $meters ) {
		if ( ! is_numeric( $meters ) ) {
			return null;
		}
		return round( $meters * 3.2808399, 2 );
	}

	/**
	 * Converts meters to miles.
	 *
	 * @since 6.0.0 Returns null for a value that isn't a number, instead of 0.
	 *
	 * @param float $meters Meters.
	 * @return float|null Miles, or null if the value isn't a number.
	 */
	public static function meters_to_miles( $meters ) {
		if ( ! is_numeric( $meters ) ) {
			return null;
		}
		return round( $meters / 1609, 2 );
	}

	/**
	 * Converts miles to meters.
	 *
	 * @since 6.0.0 Returns null for a value that isn't a number, instead of 0.
	 *
	 * @param float $miles Miles.
	 * @return float|null Meters, or null if the value isn't a number.
	 */
	public static function miles_to_meters( $miles ) {
		if ( ! is_numeric( $miles ) ) {
			return null;
		}
		return round( $miles * 1609, 2 );
	}

	/**
	 * Converts kilometers to meters.
	 *
	 * @since 6.0.0 Returns null for a value that isn't a number, instead of 0.
	 *
	 * @param float $km Kilometers.
	 * @return float|null Meters, or null if the value isn't a number.
	 */
	public static function km_to_meters( $km ) {
		if ( ! is_numeric( $km ) ) {
			return null;
		}
		return round( $km * 1000, 2 );
	}

	/**
	 * Converts km/h to m/s
	 *
	 * @since 6.0.0 Returns null for a value that isn't a number, instead of 0.
	 *
	 * @param float $kmh km/h.
	 * @return float|null m/s, or null if the value isn't a number.
	 */
	public static function kmh_to_ms( $kmh ) {
		if ( ! is_numeric( $kmh ) ) {
			return null;
		}
		return round( $kmh / 3.6, 2 );
	}

	/**
	 * Converts m to mm.
	 *
	 * @since 6.0.0 Returns null for a value that isn't a number, instead of 0.
	 *
	 * @param float $m meters.
	 * @return float|null millmeters, or null if the value isn't a number.
	 */
	public static function m_to_mm( $m ) {
		if ( ! is_numeric( $m ) ) {
			return null;
		}
		return round( $m * 1000, 2 );
	}

	/**
	 * Converts cm to mm.
	 *
	 * @since 6.0.0 Returns null for a value that isn't a number, instead of the value itself.
	 *
	 * @param float $cm centimeters.
	 * @return float|null millmeters, or null if the value isn't a number.
	 */
	public static function cm_to_mm( $cm ) {
		if ( ! is_numeric( $cm ) ) {
			return null;
		}
		return round( $cm * 10, 2 );
	}

	/**
	 * Converts miles per hour to meters per second.
	 *
	 * @since 6.0.0 Returns null for a value that isn't a number, instead of 0.
	 *
	 * @param float $miles Miles per hour.
	 * @return float|null Meters per second, or null if the value isn't a number.
	 */
	public static function miph_to_mps( $miles ) {
		if ( ! is_numeric( $miles ) ) {
			return null;
		}
		return round( $miles * 0.44704, 2 );
	}

	/**
	 * Converts meters per second to miles per hour
	 *
	 * @since 6.0.0 Returns null for a value that isn't a number, instead of 0.
	 *
	 * @param float $mps Meters per second.
	 * @return float|null Miles per hour, or null if the value isn't a number.
	 */
	public static function mps_to_miph( $mps ) {
		if ( ! is_numeric( $mps ) ) {
			return null;
		}
		return round( $mps * 2.237, 2 );
	}



	/**
	 * Converts meters per hour to meters per second.
	 *
	 * @since 6.0.0 Returns null for a value that isn't a number, instead of 0.
	 *
	 * @param float $mph Meters per hour.
	 * @return float|null Meters per second, or null if the value isn't a number.
	 */
	public static function mph_to_mps( $mph ) {
		if ( ! is_numeric( $mph ) ) {
			return null;
		}
		return round( $mph / 3600, 2 );
	}

	/**
	 * Converts degrees to compass direction.
	 *
	 * @param float $degrees Degrees.
	 * @return string Compass Direction.
	 */
	public static function degrees_to_direction( $degrees ) {
		$directions = array( 'N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW', 'N' );
		return $directions[ round( $degrees / 22.5 ) ];
	}

	/**
	 * Takes a time input and tries to convert it into a DateTime object.
	 *
	 * @param mixed $time Time.
	 * @return DateTime Date Time object.
	 */
	public static function datetime( $time ) {
		if ( is_numeric( $time ) && 0 !== $time ) {
			$datetime = new DateTime();
			$datetime->setTimestamp( $time );
			$datetime->setTimezone( wp_timezone() );
			return $datetime;
		}
		if ( is_string( $time ) ) {
			return new DateTime( $time );
		}
		if ( $time instanceof DateTime ) {
			return $time;
		}
		return new DateTime();
	}
}

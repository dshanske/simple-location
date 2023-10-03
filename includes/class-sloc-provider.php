<?php
/**
 * Base Provider Class.
 *
 * @package Simple_Location
 */

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
	 * @param string $url URL to fetch.
	 * @param array  $query Query parameters.
	 * @param array  $headers Headers.
	 * @return WP_Error|array Either the associated array response or error.
	 *
	 * @since 4.0.6
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

		$response = wp_remote_get( $fetch, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( ( $code / 100 ) !== 2 ) {
			return new WP_Error( 'invalid_response', $body, array( 'status' => $code ) );
		}
		$json = json_decode( $body, true );
		if ( empty( $json ) ) {
			return new WP_Error( 'not_json_response', $body, array( 'type' => wp_remote_retrieve_header( $response, 'Content-Type' ) ) );
		}
		return $json;
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
	 * Sanitizes API Keys and Similar
	 */
	public static function sanitize_api_key( $string ) {
		if ( ! is_string( $string ) ) {
			return false;
		}

		return stripslashes( trim( $string ) );
	}

	/**
	 * Adds a Setting Field for a Parameter of Type String
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
	 * Adds a Setting Field for a Parameter of Type URL
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
	 * Registers a Setting Field for Credential Based Information
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
	 */
	public function set( $lat, $lng = null, $alt = null ) {
		if ( ! $lng && is_array( $lat ) ) {
			if ( isset( $lat['latitude'] ) && isset( $lat['longitude'] ) ) {
				$this->latitude  = clean_coordinate( $lat['latitude'] );
				$this->longitude = clean_coordinate( $lat['longitude'] );
				if ( isset( $lat['altitude'] ) && is_numeric( $lat['altitude'] ) ) {
					$this->altitude = floatval( $lat['altitude'] );
				}
				return true;
			} else {
				return false;
			}
		}
		// Validate inputs.
		if ( ( ! is_numeric( $lat ) ) && ( ! is_numeric( $lng ) ) ) {
			return false;
		}
		$this->latitude  = clean_coordinate( $lat );
		$this->longitude = clean_coordinate( $lng );
		if ( is_numeric( $alt ) ) {
			$this->altitude = floatval( $alt );
		}
		return true;
	}

	/**
	 * Get Coordinates.
	 *
	 * @return array|boolean Array with Latitude and Longitude false if null
	 */
	public function get() {
		$return              = array();
		$return['latitude']  = $this->latitude;
		$return['longitude'] = $this->longitude;
		$return['altitude']  = $this->altitude;
		$return              = array_filter( $return );
		if ( ! empty( $return ) ) {
			return $return;
		}
		return false;
	}

	/**
	 * Converts millimeters to inches.
	 *
	 * @param float $mm Millimeters.
	 * @return float Inches.
	 */
	public static function mm_to_inches( $mm ) {
		return floatval( $mm ) / 25.4;
	}

	/**
	 * Converts inches to millimeters.
	 *
	 * @param float $inch Inches.
	 * @return float Millimeters.
	 */
	public static function inches_to_mm( $inch ) {
		return round( $inch * 25.4, 2 );
	}


	/**
	 * Converts feet to meters.
	 *
	 * @param float $feet Feet.
	 * @return float Meters.
	 */
	public static function feet_to_meters( $feet ) {
		return round( $feet / 3.2808399, 2 );
	}


	/**
	 * Converts meters to feet.
	 *
	 * @param float $meters Meters.
	 * @return float Feet.
	 */
	public static function meters_to_feet( $meters ) {
		return round( $meters * 3.2808399, 2 );
	}

	/**
	 * Converts meters to miles.
	 *
	 * @param float $meters Meters.
	 * @return float Miles.
	 */
	public static function meters_to_miles( $meters ) {
		return round( $meters / 1609, 2 );
	}

	/**
	 * Converts miles to meters.
	 *
	 * @param float $miles Miles.
	 * @return float Meters.
	 */
	public static function miles_to_meters( $miles ) {
		return round( $miles * 1609, 2 );
	}

	/**
	 * Converts kilometers to meters.
	 *
	 * @param float $km Kilometers.
	 * @return float Meters.
	 */
	public static function km_to_meters( $km ) {
		return round( $km * 1000, 2 );
	}

	/**
	 * Converts km/h to m/s
	 *
	 * @param float $kmh km/h.
	 * @return float m/s.
	 */
	public static function kmh_to_ms( $kmh ) {
		return round( $kmh / 3.6, 2 );
	}

	/**
	 * Converts m to mm.
	 *
	 * @param float $m meters.
	 * @return float millmeters.
	 */
	public static function m_to_mm( $m ) {
		return round( $m * 1000, 2 );
	}

	/**
	 * Converts cm to mm.
	 *
	 * @param float $cm centimeters.
	 * @return float millmeters.
	 */
	public static function cm_to_mm( $cm ) {
		if ( ! is_numeric( $cm ) ) {
			return( $cm );
		}
		return round( $cm * 10, 2 );
	}

	/**
	 * Converts miles per hour to meters per second.
	 *
	 * @param float $miles Miles per hour.
	 * @return float Meters per second.
	 */
	public static function miph_to_mps( $miles ) {
		return round( $miles * 0.44704, 2 );
	}

	/**
	 * Converts meters per second to miles per hour
	 *
	 * @param float $mps Meters per second.
	 * @rerturn float Miles per hour.
	 */
	public static function mps_to_miph( $mps ) {
		return round( $mps * 2.237, 2 );
	}



	/**
	 * Converts meters per hour to meters per second.
	 *
	 * @param float $mph Meters per hour.
	 * @return float Meters per second.
	 */
	public static function mph_to_mps( $mph ) {
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

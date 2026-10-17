<?php
/**
 * Base Location Provider Class.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns location from an external location provider.
 *
 * Uses properties from https://www.w3.org/TR/geolocation-API/
 *
 * @since 1.0.0
 */
abstract class Location_Provider extends Sloc_Provider {


	/**
	 * User name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $user;

	/**
	 * Accuracy. AKA as Horizontal Accuracy.
	 *
	 * The accuracy level of the latitude and longitude coordinates. It is specified in meters. Must be a non-negative real number.
	 *
	 * @since 1.0.0
	 * @var double
	 */
	protected $accuracy;

	/**
	 * Altitude Accuracy. AKA Verticial Accuracy.
	 *
	 * Specified in meters. If not available, must be null. If available, must be a non-negative real number.
	 *
	 * @since 1.0.0
	 * @var double
	 */
	protected $altitude_accuracy;

	/**
	 * Heading.
	 *
	 * The direction of travel and is specified in degrees, where 0° ≤ heading < 360°, counting clockwise relative to the true north.
	 * If the implementation cannot provide heading information, the value of this attribute must be null. If stationary (i.e. the value of the speed attribute is 0), then the value of the heading attribute must be NaN.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	protected $heading;

	/**
	 * Speed.
	 *
	 * Magnitude of the horizontal component of the current velocity and is specified in meters per second. If not available, must be null.
	 * Otherwise, the value must be a non-negative real number.
	 *
	 * @since 1.0.0
	 * @var float
	 */
	protected $speed;

	/**
	 * Time.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $time = null;

	/**
	 * Activity.
	 *
	 * String representation of the current activity.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $activity = null;


	/**
	 * Annotation.
	 *
	 * Any annotations on the location.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $annotation = '';


	/**
	 * Extra parameters passed.
	 *
	 * Any extra data provided by the provider.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected $other = array();

	/**
	 * Support for Whether this Provider Allows for Background Updates.
	 *
	 * If a provider does not allow background updates information may be stale.
	 *
	 * @since 1.0.0
	 * @var boolean
	 */
	protected $background = false;

	/**
	 * Constructor for the Abstract Class.
	 *
	 * The default version of this just sets the parameters.
	 *
	 * @param array $args {
	 *  Arguments.
	 *  @type string $api API Key.
	 *  @type string $user Username.
	 */
	public function __construct( $args = array() ) {
		$defaults   = array(
			'api'  => null,
			'user' => '',
		);
		$defaults   = apply_filters( 'sloc_location_provider_defaults', $defaults );
		$r          = wp_parse_args( $args, $defaults );
		$this->user = $r['user'];
		$this->api  = $r['api'];
	}

	/**
	 * Is Provider Active
	 *
	 * @since 6.0.0 Reads the sloc_geolocation_provider setting, where the chosen provider is saved.
	 */
	public function is_active() {
		$option = get_option( 'sloc_geolocation_provider' );
		return ( $this->slug === $option );
	}

	/**
	 * Get Coordinates.
	 *
	 * @since 6.0.0 Values of 0 are kept.
	 * @since 6.0.0 Every activity in get_activity_list() with a matching icon gets one.
	 *
	 * @return array|boolean Array with all properties, false if null.
	 */
	public function get() {
		$return                      = array();
		$return['latitude']          = $this->latitude;
		$return['longitude']         = $this->longitude;
		$return['altitude']          = $this->altitude;
		$return['accuracy']          = $this->accuracy;
		$return['altitude_accuracy'] = $this->altitude_accuracy;
		$return['heading']           = $this->heading;
		$return['speed']             = $this->speed;
		$return['time']              = $this->time;
		$return['zoom']              = self::derive_zoom();
		$return['activity']          = $this->activity;

		$icons = array(
			'car'     => 'fa-car',
			'bus'     => 'fa-bus',
			'train'   => 'fa-train',
			'subway'  => 'fa-subway',
			'tram'    => 'fa-tram',
			'plane'   => 'fa-plane',
			'walking' => 'fa-walking',
			'running' => 'fa-running',
			'taxi'    => 'fa-taxi',
			'horse'   => 'fa-horse',
			'bike'    => 'fa-biking',
		);
		if ( is_string( $this->activity ) && isset( $icons[ $this->activity ] ) ) {
			$return['icon'] = $icons[ $this->activity ];
		}
		$return['annotation'] = $this->annotation;
		$return['other']      = $this->other;
		$return               = self::filter_unset( $return );
		if ( ! empty( $return ) ) {
			return $return;
		}
		return false;
	}

	/**
	 * Derive Zoom based on Accuracy levels.
	 *
	 * @return int Derived Zoom or Default Zoom.
	 */
	public function derive_zoom() {
		if ( $this->altitude > 1000 ) {
			return 9;
		}
		if ( 0 < $this->accuracy ) {
			$return = round( log( 591657550.5 / ( $this->accuracy * 45 ), 2 ) ) + 1;
			if ( $return > 20 ) {
				return 20;
			}
			return $return;
		}
		return get_option( 'sloc_zoom' );
	}

	/**
	 * Get Coordinates in Geo URI.
	 *
	 * @since 6.0.0 Coordinates and altitude of 0 are kept.
	 *
	 * @return string|boolean GEOURI false if null.
	 */
	public function get_geouri() {
		if ( ! is_numeric( $this->latitude ) || ! is_numeric( $this->longitude ) ) {
			return false;
		}
		$coords = array( $this->latitude, $this->longitude );
		if ( is_numeric( $this->altitude ) ) {
			$coords[] = $this->altitude;
		}
		$return = 'geo:' . implode( ',', $coords );
		if ( ! empty( $this->accuracy ) ) {
			$return .= ';u=' . $this->accuracy;
		}
		return $return;
	}

	/**
	 * Get Coordinates in GeoJSON.
	 *
	 * @since 6.0.0 Coordinates and altitude of 0 are kept.
	 *
	 * @return array|boolean Array in GeoJSON format false if null.
	 */
	public function get_geojson() {
		if ( ! is_numeric( $this->latitude ) || ! is_numeric( $this->longitude ) ) {
			return false;
		}
		$coords = array( $this->longitude, $this->latitude );
		if ( is_numeric( $this->altitude ) ) {
			$coords[] = $this->altitude;
		}
		$properties = array();
		foreach ( array() as $property ) {
			if ( ! empty( $this->$property ) ) {
				$properties[ $property ] = $this->$property;
			}
		}
		$properties = array_filter( $properties );
		return array(
			'type'       => 'Feature',
			'geometry'   => array(
				'type'        => 'Point',
				'coordinates' => $coords,
			),
			'properties' => $properties,
		);
	}


	/**
	 * Get Coordinates in H-Geo MF2 Format.
	 *
	 * @since 6.0.0 Values of 0 are kept.
	 *
	 * @return array|boolean Array with h-geo mf2 false if null.
	 */
	public function get_mf2() {
		$properties              = array();
		$properties['latitude']  = $this->latitude;
		$properties['longitude'] = $this->longitude;
		$properties['altitude']  = $this->altitude;
		$properties['heading']   = $this->heading;
		$properties['speed']     = $this->speed;
		$properties['name']      = $this->annotation; // If there is an annotation set that as the name.
		$properties              = self::filter_unset( $properties );
		if ( empty( $properties ) ) {
			return false;
		}
		foreach ( $properties as $key => $value ) {
			$properties[ $key ] = array( $value );
		}
		return array(
			'type'       => array( 'h-geo' ),
			'properties' => $properties,
		);
	}

	/**
	 * Set User name.
	 *
	 * @param string $user Username.
	 */
	public function set_user( $user ) {
		$this->user = $user;
		$this->reset();
	}

	/**
	 * Clears the location found by the last lookup.
	 *
	 * Providers are shared, so each lookup must start empty; otherwise a lookup that finds nothing
	 * would return the previous lookup's location, possibly another user's.
	 * Settings such as the user and the API key are kept.
	 *
	 * @since 6.0.0
	 */
	protected function reset() {
		$this->latitude          = null;
		$this->longitude         = null;
		$this->altitude          = null;
		$this->accuracy          = null;
		$this->altitude_accuracy = null;
		$this->heading           = null;
		$this->speed             = null;
		$this->time              = null;
		$this->activity          = null;
		$this->annotation        = '';
		$this->other             = array();
	}


	/**
	 * Return background property.
	 *
	 * @return boolean Return whether this allows background updates.
	 */
	public function background() {
		return $this->background;
	}


	/**
	 * Get Coordinates in H-Geo MF2 Format.
	 *
	 * Implementations should call reset() first, so a lookup that finds nothing doesn't keep the previous location.
	 *
	 * @param string|int|DateTime $time An ISO8601 time string, unix timestamp, or DateTime.
	 * @param array               $args Optional arguments to be passed.
	 * @return array|boolean Array with h-geo mf2 false if null
	 */
	abstract public function retrieve( $time = null, $args = array() );

	/**
	 * Returns a list of activity settings and their prettified strings.
	 *
	 * @return array Associative array with options as the key and strings as the values.
	 */
	public function get_activity_list() {
		return array(
			'unknown' => __( 'Unknown Activity', 'simple-location' ),
			'still'   => __( 'Still', 'simple-location' ),
			'car'     => __( 'Car', 'simple-location' ),
			'bus'     => __( 'Bus', 'simple-location' ),
			'train'   => __( 'Train', 'simple-location' ),
			'subway'  => __( 'Subway', 'simple-location' ),
			'tram'    => __( 'Tram/Trolley/Light Rail', 'simple-location' ),
			'plane'   => __( 'Plane', 'simple-location' ),
			'walking' => __( 'Walking', 'simple-location' ),
			'running' => __( 'Running', 'simple-location' ),
			'taxi'    => __( 'Taxi', 'simple-location' ),
			'horse'   => __( 'Horse', 'simple-location' ),
			'bike'    => __( 'Bike', 'simple-location' ),

		);
	}

	/**
	 * Converts knots to meters.
	 *
	 * @param float $knots Knots.
	 * @return float $meters Meters.
	 */
	public static function knots_to_meters( $knots ) {
		return round( $knots * 0.51444444 );
	}
}

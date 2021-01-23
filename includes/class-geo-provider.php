<?php
/**
 * Base Reverse Geolocation Provider Class.
 *
 * @package Simple_Location
 */

/**
 * Retrieves Location Information.
 *
 * @since 1.0.0
 */
abstract class Geo_Provider extends Sloc_Provider {

	 /**
	  * Reverse Zoom Level.
	  *
	  * @since 1.0.0
	  * @var int
	  */
	protected $reverse_zoom;

	 /**
	  * Username if Applicable.
	  *
	  * @since 1.0.0
	  * @var int
	  */
	protected $user;

	 /**
	  * Timezone.
	  *
	  * @since 1.0.0
	  * @var string
	  */
	protected $timezone;

	 /**
	  * Offset.
	  *
	  * @since 1.0.0
	  * @var string
	  */
	protected $offset;

	 /**
	  * Offset in Seconds.
	  *
	  * @since 1.0.0
	  * @var int
	  */
	protected $offset_seconds;

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
	 *  @type string $address Formatted Address String
	 *  @type int $reverse_zoom Reverse Zoom. Default 18.
	 *  @type string $user User name.
	 */
	public function __construct( $args = array() ) {
		$defaults           = array(
			'api'          => null,
			'latitude'     => null,
			'longitude'    => null,
			'altitude'     => null,
			'reverse_zoom' => 18,
			'user'         => '',
		);
		$defaults           = apply_filters( 'sloc_geo_provider_defaults', $defaults );
		$r                  = wp_parse_args( $args, $defaults );
		$this->reverse_zoom = $r['reverse_zoom'];
		$this->user         = $r['user'];
		$this->api          = $r['api'];
		$this->set( $r['latitude'], $r['longitude'] );
	}

	/**
	 * Returns elevation.
	 *
	 * @return float $elevation Elevation.
	 *
	 * @since 1.0.0
	 */
	abstract public function elevation();

	/**
	 * Return an address.
	 *
	 * @return array $reverse microformats2 address elements in an array.
	 */
	abstract public function reverse_lookup();

	/**
	 * Geocode address.
	 *
	 * @param  string $address String representation of location.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	abstract public function geocode( $address );

	/**
	 * Generate Display Name for a Reverse Address Lookup.
	 *
	 * @param array $reverse Array of MF2 Address Properties.
	 * @return string|boolean Return Display Name or False if Failed.
	 */
	protected function display_name( $reverse ) {
		if ( ! is_array( $reverse ) ) {
			return false;
		}
		if ( isset( $reverse['display_name'] ) ) {
			return apply_filters( 'location_display_name', $reverse['display_name'], $reverse );
		}
		$text   = array();
		$text[] = ifset( $reverse['name'] );
		if ( ! array_key_exists( 'street-address', $reverse ) ) {
			$text[] = ifset( $reverse['extended-address'] );
		}
		$text[] = ifset( $reverse['locality'] );
		$text[] = ifset( $reverse['region'] );
		$text[] = ifset( $reverse['country-name'] );
		$text   = array_filter( $text );
		$return = join( ', ', $text );
		return apply_filters( 'location_display_name', $return, $reverse );
	}

	/**
	 * Turn Country Code into Country Name.
	 *
	 * @param string $code Country Code.
	 * @return string|boolean Country Name or false is failed.
	 */
	protected function country_name( $code ) {
		$file  = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/countries.json';
		$codes = json_decode( file_get_contents( $file ), true );
		if ( array_key_exists( $code, $codes ) ) {
			return $codes[ $code ];
		}
		return false;
	}

	/**
	 * Return Timezone Data for a Set of Coordinates.
	 *
	 * @return array|boolean Return Timezone Data or False if Failed
	 */
	protected function timezone() {
		$timezone = Loc_Timezone::timezone_for_location( $this->latitude, $this->longitude );
		if ( $timezone ) {
			$return             = array();
			$return['timezone'] = $timezone->name;
			$return['offset']   = $timezone->offset;
			$return['seconds']  = $timezone->seconds;
			return $return;
		}
		return false;
	}
}

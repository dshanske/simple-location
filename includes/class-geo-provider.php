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
	use Geolocation_Trait;

	/**
	 * Reverse Zoom Level.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	protected $reverse_zoom;

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

		if ( $this->is_active() && method_exists( $this, 'admin_init' ) ) {
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'init', array( $this, 'init' ) );
		}
	}

	/**
	 * Is Provider Active
	 */
	public function is_active() {
		$option = get_option( 'sloc_geo_provider' );
		return ( $this->slug === $option );
	}

	/**
	 * Returns elevation from provider.
	 *
	 * @return float $elevation Elevation.
	 *
	 * @since 1.0.0
	 */
	public function elevation() {
		$provider = Loc_Config::elevation_provider();
		if ( ! $provider ) {
			return 0;
		}

		$provider->set( $this->latitude, $this->longitude );
		return $provider->elevation();
	}

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
	public function display_name( $reverse ) {
		if ( ! is_array( $reverse ) ) {
			return false;
		}
		$reverse = array_filter( $reverse );
		if ( isset( $reverse['display_name'] ) ) {
			return apply_filters( 'location_display_name', $reverse['display_name'], $reverse );
		}
		$text = array();
		if ( array_key_exists( 'name', $reverse ) ) {
			$text[] = $reverse['name'];
		} elseif ( ! array_key_exists( 'street-address', $reverse ) ) {
			$text[] = ifset( $reverse['extended-address'] );
		} else {
			$text[] = ifset( $reverse['street-address'] );
		}

		$text[] = ifset( $reverse['locality'] );
		$text   = array_filter( $text );
		if ( empty( $text ) ) {
			$text[] = ifset( $reverse['region'] );
		} elseif ( array_key_exists( 'region-code', $reverse ) ) {
				$text[] = $reverse['region-code'];
		} else {
			$text[] = ifset( $reverse['region'] );
		}
		if ( array_key_exists( 'country-code', $reverse ) ) {
			if ( get_option( 'sloc_country' ) !== $reverse['country-code'] ) {
				$text[] = $reverse['country-code'];
			}
		} else {
			$text[] = ifset( $reverse['country-name'] );
		}
		$text   = array_filter( $text );
		$return = join( ', ', $text );
		return apply_filters( 'location_display_name', $return, $reverse );
	}
}

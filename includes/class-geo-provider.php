<?php
/**
 * Base Reverse Geolocation Provider Class.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retrieves Location Information.
 *
 * @since 1.0.0
 */
abstract class Geo_Provider extends Sloc_Provider {
	use GeoLocation_Trait;

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
		$defaults = array(
			'api'          => null,
			'latitude'     => null,
			'longitude'    => null,
			'altitude'     => null,
			'reverse_zoom' => 18,
			'user'         => '',
		);
		/**
		 * Filters the default arguments of geocoding providers. Map providers apply it too.
		 *
		 * @since 3.1.0
		 *
		 * @param array $defaults Default arguments: api, latitude, longitude, altitude, reverse_zoom and user, plus the map size, zoom, location and style for map providers.
		 */
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
	 *
	 * @since 1.0.0
	 * @since 6.1.0 Active anywhere in the geocoding chain.
	 */
	public function is_active() {
		return in_array( $this->slug, Sloc_Provider_Chain::configured( 'geo' ), true );
	}

	/**
	 * Returns elevation from provider.
	 *
	 * @return float|WP_Error $elevation Elevation, or an error.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Returns an error instead of 0 (sea level) when no elevation provider is set.
	 * @since 6.1.0 Tries the elevation providers in order. See Sloc_Provider_Chain.
	 */
	public function elevation() {
		return Sloc_Provider_Chain::elevation( $this->latitude, $this->longitude );
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
	 * Returns whether two place names are the same, ignoring case and surrounding spaces, such as a city and its region.
	 *
	 * @since 6.1.0
	 *
	 * @param string $name  Name.
	 * @param string $other Other name.
	 * @return bool Whether they are the same.
	 */
	public static function same_name( $name, $other ) {
		$name  = trim( (string) $name );
		$other = trim( (string) $other );
		return '' !== $name && 0 === strcasecmp( remove_accents( $name ), remove_accents( $other ) );
	}

	/**
	 * Generate Display Name for a Reverse Address Lookup.
	 *
	 * @since 1.0.0
	 * @since 6.1.0 A region with the same name as the locality, such as Berlin, and repeated names are left out (#240).
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
			/**
			 * Filters the display name of a location from a reverse lookup.
			 *
			 * @since 3.0.0
			 *
			 * @param string $display_name Display name.
			 * @param array  $reverse      Address properties from the lookup.
			 */
			return apply_filters( 'location_display_name', $reverse['display_name'], $reverse ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.
		}
		$text = array();
		if ( array_key_exists( 'name', $reverse ) ) {
			$text[] = $reverse['name'];
		} elseif ( ! array_key_exists( 'street-address', $reverse ) ) {
			$text[] = $reverse['extended-address'] ?? '';
		} else {
			$text[] = $reverse['street-address'] ?? '';
		}

		$text[] = $reverse['locality'] ?? '';
		$text   = array_filter( $text );
		if ( empty( $text ) ) {
			$text[] = $reverse['region'] ?? '';
		} elseif ( self::same_name( $reverse['locality'] ?? '', $reverse['region'] ?? '' ) ) {
			// A city that is its own region, such as Berlin, is named once.
			$text[] = '';
		} elseif ( array_key_exists( 'region-code', $reverse ) ) {
				$text[] = $reverse['region-code'];
		} else {
			$text[] = $reverse['region'] ?? '';
		}
		if ( array_key_exists( 'country-code', $reverse ) ) {
			if ( get_option( 'sloc_country' ) !== $reverse['country-code'] ) {
				$text[] = $reverse['country-code'];
			}
		} else {
			$text[] = $reverse['country-name'] ?? '';
		}
		$text = array_filter( $text );
		// Leave out a name that repeats the one before it, such as a venue named for its city.
		$names = array();
		foreach ( $text as $name ) {
			if ( empty( $names ) || ! self::same_name( $name, end( $names ) ) ) {
				$names[] = $name;
			}
		}
		$return = join( ', ', $names );
		/** This filter is documented in includes/class-geo-provider.php */
		return apply_filters( 'location_display_name', $return, $reverse ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.
	}
}

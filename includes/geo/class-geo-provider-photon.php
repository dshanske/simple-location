<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Geocoding and reverse geocoding using Photon, https://github.com/komoot/photon.
 *
 * Photon is an open source geocoder for OpenStreetMap data. komoot runs a public server, and Photon is much lighter to
 * run yourself than Nominatim.
 *
 * @since 6.1.0
 */
class Geo_Provider_Photon extends Geo_Provider {

	/**
	 * The public Photon server, run by komoot, used without a server URL setting.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const PUBLIC_URL = 'https://photon.komoot.io';

	/**
	 * Languages the public server returns names in. Without one of these, names are in the local language.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const LANGUAGES = array( 'de', 'en', 'fr' );

	/**
	 * Server URL, without a trailing slash.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $server = self::PUBLIC_URL;

	/**
	 * Constructor.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args {
	 *     Arguments. See Geo_Provider::__construct() for the others.
	 *
	 *     @type string $server Server URL. Default the sloc_photon_url setting, or the public server.
	 * }
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Photon', 'simple-location' );
		$this->slug        = 'photon';
		$this->url         = 'https://photon.komoot.io/';
		$this->description = __( 'Photon is an open source geocoder for OpenStreetMap data. komoot\'s public server needs no key and is for reasonable use; heavy use is throttled or banned. You can also run your own server, which is lighter than running Nominatim.', 'simple-location' );

		$server = self::sanitize_url( $args['server'] ?? get_option( 'sloc_photon_url' ) );
		if ( '' !== $server ) {
			$this->server = untrailingslashit( $server );
		}

		parent::__construct( $args );
	}

	/**
	 * Sanitizes the server URL setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Server URL.
	 * @return string HTTP or HTTPS URL, or an empty string for the public server.
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
		register_setting(
			'sloc_api',
			'sloc_photon_url',
			array(
				'type'              => 'string',
				'description'       => 'Photon Server URL',
				'show_in_rest'      => false,
				'default'           => '',
				'sanitize_callback' => array( __CLASS__, 'sanitize_url' ),
			)
		);
	}

	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 6.1.0
	 */
	public static function admin_init() {
		add_settings_field(
			'sloc_photon_url',
			__( 'Photon Server URL', 'simple-location' ),
			array( 'Loc_Config', 'string_callback' ),
			'sloc_api',
			'sloc_api',
			array(
				'label_for'   => 'sloc_photon_url',
				'type'        => 'url',
				'description' => __( 'Optional. The address of your own Photon server. Leave empty to use komoot\'s public server.', 'simple-location' ),
			)
		);
	}

	/**
	 * Returns the query arguments every request shares: one result, in the site's language if Photon has it.
	 *
	 * @since 6.1.0
	 *
	 * @return array Query arguments.
	 */
	protected function query() {
		$query    = array( 'limit' => 1 );
		$language = strtolower( substr( (string) get_bloginfo( 'language' ), 0, 2 ) );
		if ( in_array( $language, self::LANGUAGES, true ) ) {
			$query['lang'] = $language;
		}
		return $query;
	}

	/**
	 * Return an address.
	 *
	 * @since 6.1.0
	 *
	 * @return array|WP_Error Microformats2 address elements, or an error, such as no_results.
	 */
	public function reverse_lookup() {
		$json = $this->fetch_json(
			$this->server . '/reverse',
			array_merge(
				$this->query(),
				array(
					'lat' => $this->latitude,
					'lon' => $this->longitude,
				)
			)
		);
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$feature = self::first_feature( $json );
		if ( ! $feature ) {
			return new WP_Error( 'no_results', __( 'No Results Found', 'simple-location' ), array( 'status' => 404 ) );
		}
		return $this->address_to_mf( $feature['properties'] );
	}

	/**
	 * Geocode address.
	 *
	 * @since 6.1.0
	 *
	 * @param string $address String representation of location.
	 * @return array|WP_Error Microformats2 address elements with the result's coordinates, or an error, such as no_results.
	 */
	public function geocode( $address ) {
		$json = $this->fetch_json( $this->server . '/api', array_merge( $this->query(), array( 'q' => $address ) ) );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$feature = self::first_feature( $json );
		if ( ! $feature || ! isset( $feature['geometry']['coordinates'][1] ) ) {
			return new WP_Error( 'no_results', __( 'No results found', 'simple-location' ), array( 'status' => 404 ) );
		}

		// Parse on a copy set to the result's location, so the address gets its coordinates and timezone.
		$coordinates = $feature['geometry']['coordinates'];
		$result      = clone $this;
		$result->set( $coordinates[1], $coordinates[0] );
		return $result->address_to_mf( $feature['properties'] );
	}

	/**
	 * Returns the first feature of a response, if it has one with properties.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $json Response.
	 * @return array|null Feature, or null.
	 */
	protected static function first_feature( $json ) {
		$feature = $json['features'][0] ?? null;
		return ( is_array( $feature ) && isset( $feature['properties'] ) && is_array( $feature['properties'] ) ) ? $feature : null;
	}

	/**
	 * Converts a feature's properties to microformats2 address properties.
	 *
	 * Photon names each feature with its type: house for an address or a place with one, street, locality, district,
	 * city, county, state, country, or other.
	 *
	 * @since 6.1.0
	 *
	 * @param array $properties Feature properties.
	 * @return array Microformats2 address elements.
	 */
	protected function address_to_mf( $properties ) {
		$type         = $properties['type'] ?? '';
		$country_code = strtoupper( (string) ( $properties['countrycode'] ?? '' ) );
		$name         = $properties['name'] ?? null;
		$number       = $properties['housenumber'] ?? '';
		$street       = $properties['street'] ?? '';
		if ( 'street' === $type && '' === $street ) {
			$street = (string) $name;
		}
		$street_address = self::house_number( $country_code ) ? $number . ' ' . $street : $street . ' ' . $number;

		$region = $properties['state'] ?? null;
		$addr   = array(
			// Only a place has its own name; other features are named for the street, town or region they are.
			'name'             => in_array( $type, array( 'house', 'other' ), true ) ? $name : null,
			'street-number'    => $number,
			'street'           => $street,
			'street-address'   => trim( $street_address ),
			'extended-address' => $properties['district'] ?? $properties['locality'] ?? null,
			'locality'         => $properties['city'] ?? ( 'city' === $type ? $name : null ),
			'region'           => $region,
			'region-code'      => empty( $region ) ? null : self::region_code( $region, $country_code ),
			'country-name'     => $properties['country'] ?? self::country_name( $country_code ),
			'postal-code'      => $properties['postcode'] ?? null,
			'country-code'     => $country_code,
			'latitude'         => $this->latitude,
			'longitude'        => $this->longitude,
			'altitude'         => $this->altitude,
			'raw'              => $properties,
		);
		$addr                 = array_filter( $addr );
		$addr['display-name'] = $this->display_name( $addr );
		$tz                   = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		return $addr;
	}
}

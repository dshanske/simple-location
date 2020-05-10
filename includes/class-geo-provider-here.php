<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

/**
 * Reverse Geolocation using HERE API.
 *
 * @since 1.0.0
 */
class Geo_Provider_Here extends Geo_Provider {

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
		$this->name = __( 'HERE', 'simple-location' );
		$this->slug = 'here';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_here_api' );
		}

		$option = get_option( 'sloc_geo_provider' );
		if ( 'here' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		parent::__construct( $args );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		register_setting(
			'sloc_providers', // Option group.
			'sloc_here_api', // Option name.
			array(
				'type'         => 'string',
				'description'  => 'HERE Maps API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
	}

	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		add_settings_field(
			'hereapi', // ID.
			__( 'HERE API Key', 'simple-location' ), // Setting title.
			array( 'Loc_Config', 'string_callback' ), // Display callback.
			'sloc_providers', // Settings page.
			'sloc_api', // Settings section.
			array(
				'label_for' => 'sloc_here_api',
			)
		);
	}

	/**
	 * Returns elevation, but HERE has no elevation API.
	 *
	 * @return float $elevation Elevation.
	 *
	 * @since 1.0.0
	 */
	public function elevation() {
		return 0;
	}

	/**
	 * Return an address.
	 *
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Bing', 'simple-location' ) );
		}
		$args = array(
			'apiKey' => $this->api,
			'at'     => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),

		);
		$url  = 'https://revgeocode.search.hereapi.com/v1/revgeocode';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( ! isset( $json['items'] ) || empty( $json['items'] ) ) {
			return new WP_Error( 'invalid_response', __( 'No results', 'simple-location' ) );
		}
		$json              = $json['items'][0]['address'];
		$addr              = $this->address_to_mf2( $json );
		$addr['latitude']  = $this->latitude;
		$addr['longitude'] = $this->longitude;
	}

	/**
	 * Convert address properties to mf2
	 *
	 * @param  array $json Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	private function address_to_mf2( $json ) {
		$addr['display-name']   = $json['label'];
		$addr['street-address'] = ifset( $json['street'] );
		$addr['locality']       = ifset( $json['city'] );
		$addr['region']         = ifset( $json['state'] );
		$addr['country-name']   = ifset( $json['countryName'] );
		$addr['country-code']   = ifset( $json['countryCode'] );
		$addr['postal-code']    = ifset( $json['postalCode'] );

		$tz = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		if ( WP_DEBUG ) {
			$addr['raw'] = $json;
		}
		return $addr;
	}

	/**
	 * Geocode address.
	 *
	 * @param  string $address String representation of location.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function geocode( $address ) {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Bing', 'simple-location' ) );
		}
		$args = array(
			'apiKey' => $this->api,
			'q'      => $address,

		);
		$url  = 'https://revgeocode.search.hereapi.com/v1/geocode';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( ! isset( $json['items'] ) || empty( $json['items'] ) ) {
			return new WP_Error( 'invalid_response', __( 'No results', 'simple-location' ) );
		}
		$json   = $json['items'][0];
		$return = $this->address_to_mf2( $json['address'] );
		if ( isset( $json['position'] ) ) {
			$return['latitude']  = $json['position']['lat'];
			$return['longitude'] = $json['position']['lng'];
		}
		return $return;
	}

}

register_sloc_provider( new Geo_Provider_Here() );

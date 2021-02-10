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
			'lang'   => get_bloginfo( 'language' ),
		);
		$url  = 'https://revgeocode.search.hereapi.com/v1/revgeocode';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( ! isset( $json['items'] ) || empty( $json['items'] ) ) {
			return new WP_Error( 'invalid_response', __( 'No results', 'simple-location' ) );
		}
		$json              = $json['items'][0];
		$addr              = $this->address_to_mf2( $json );
		$addr['latitude']  = $this->latitude;
		$addr['longitude'] = $this->longitude;
		return array_filter( $addr );
	}

	/**
	 * Convert address properties to mf2
	 *
	 * @param  array $json Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	private function address_to_mf2( $json ) {
		$addr = array();
		if ( array_key_exists( 'address', $json ) ) {
			$location              = $json['address'];
			$number                = self::ifnot(
				$location,
				array(
					'houseNumber',
				)
			);
			$street               .= self::ifnot(
				$location,
				array(
					'street',
				)
			);
			$addr['street']        = $street;
			$addr['street_number'] = $number;

			$addr['name']             = ifset( $json['title'] );
			$addr['street-address']   = $street;
			$addr['extended-address'] = self::ifnot(
				$location,
				array(
					'district',
				)
			);
			$addr['country-name']     = ifset( $location['countryName'] );
			$addr['country-code']     = self::country_code_iso3( ifset( $location['countryCode'] ) );
			$addr['locality']         = ifset( $location['city'] );
			$addr['region']           = ifset( $location['state'] );
			if ( array_key_exists( 'stateCode', $location ) ) {
				$addr['region-code'] = $location['stateCode'];
			} else {
				$addr['region-code'] = self::region_code( $addr['region'], $addr['country-code'] );
			}
			$addr['postal-code'] = ifset( $location['postalCode'] );

			// Adjust position of house number/name based on country practice.
			if ( self::house_number( $country_code ) ) {
				$addr['street-address'] = $number . ' ' . $street;
			} else {
				$addr['street-address'] = $street . ' ' . $number;
			}
		}

		if ( ! array_key_exists( 'display-name', $addr ) ) {
			$addr['display-name'] = $this->display_name( $addr );
		}

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
			'lang'   => get_bloginfo( 'language' ),

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
		$return = $this->address_to_mf2( $json );
		if ( isset( $json['position'] ) ) {
			$return['latitude']  = $json['position']['lat'];
			$return['longitude'] = $json['position']['lng'];
		}
		return $return;
	}

}

register_sloc_provider( new Geo_Provider_Here() );

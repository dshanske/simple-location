<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

/**
 * Reverse Geolocation using Bing API.
 *
 * @since 1.0.0
 */
class Geo_Provider_Bing extends Geo_Provider {



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
		$this->name = __( 'Bing', 'simple-location' );
		$this->slug = 'bing';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_bing_api' );
		}

		$option = get_option( 'sloc_geo_provider' );
		if ( 'bing' === $option ) {
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
			'sloc_bing_api', // Option name.
			array(
				'type'         => 'string',
				'description'  => 'Bing Maps API Key',
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
			'bingapi', // ID.
			__( 'Bing API Key', 'simple-location' ), // Setting title.
			array( 'Loc_Config', 'string_callback' ), // Display callback.
			'sloc_providers', // Settings page.
			'sloc_api', // Settings section.
			array(
				'label_for' => 'sloc_bing_api',
			)
		);
	}

	/**
	 * Returns elevation.
	 *
	 * @return float $elevation Elevation.
	 *
	 * @since 1.0.0
	 */
	public function elevation() {
		if ( empty( $this->api ) ) {
			return null;
		}
		$args = array(
			'points' => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
			'key'    => $this->api,
		);
		$url  = 'http://dev.virtualearth.net/REST/v1/Elevation/List';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( isset( $json['error_message'] ) ) {
				return new WP_Error( $json['status'], $json['error_message'] );
		}
		if ( ! isset( $json['resourceSets'] ) ) {
			return null;
		}
			$json = $json['resourceSets'][0]['resources'][0];
		if ( ! isset( $json['elevations'] ) ) {
			return null;
		}
			return round( $json['elevations'][0], 2 );
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
			'key'    => $this->api,
			'incl'   => 'ciso2',
			'inclnb' => '1',
		);
		$url  = sprintf( 'https://dev.virtualearth.net/REST/v1/Locations/%1$s,%2$s', $this->latitude, $this->longitude );
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( isset( $json['resourceSets'] ) ) {
			$json = $json['resourceSets'][0];
			if ( isset( $json['resources'] ) && is_array( $json['resources'] ) ) {
				$json = $json['resources'][0];
			}
		}

		$return              = $this->address_to_mf2( $json );
		$return['latitude']  = $this->latitude;
		$return['longitude'] = $this->longitude;
		return $return;
	}


	/**
	 * Convert address properties to mf2
	 *
	 * @param  array $json Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	private function address_to_mf2( $json ) {
		$addr                     = array();
		$addr['name']             = self::ifnot(
			$json,
			array(
				'landmark',
			)
		);
		$addr['street-address']   = ifset( $json['address']['addressLine'] );
		$addr['extended-address'] = ifset( $json['address']['neighborhood'] );
		$addr['locality']         = ifset( $json['address']['locality'] );
		$addr['country-name']     = ifset( $json['address']['countryRegion'] );
		$addr['country-code']     = ifset( $json['address']['countryRegionIso2'] );
		$addr['region-code']      = self::ifnot(
			$json['address'],
			array(
				'adminDistrict',
				'adminDistrict2',
			)
		);
		$addr['region']           = self::region_name( $addr['region-code'], $addr['country-code'] );

		if ( empty( $addr['region'] ) ) {
			$addr['region']      = self::ifnot(
				$json['address'],
				array(
					'adminDistrict',
					'adminDistrict2',
				)
			);
			$addr['region-code'] = self::region_code( $addr['region'], $addr['country-code'] );
		}
		$addr['postal-code'] = ifset( $json['address']['postalCode'] );
		$addr['label']       = ifset( $json['address']['landmark'] );

		$tz = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}

		if ( ! array_key_exists( 'display-name', $addr ) ) {
			$addr['display-name'] = $this->display_name( $addr );
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
			return null;
		}
		$args = array(
			'q'      => $address,
			'inclnb' => 1,
			'key'    => $this->api,
		);
		$url  = 'http://dev.virtualearth.net/REST/v1/Locations/';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( isset( $json['error_message'] ) ) {
				return new WP_Error( $json['status'], $json['error_message'] );
		}
		if ( isset( $json['resourceSets'] ) ) {
			$json = $json['resourceSets'][0];
			if ( isset( $json['resources'] ) && is_array( $json['resources'] ) ) {
				$json = $json['resources'][0];
			}
		}

		$return              = $this->address_to_mf2( $json );
		$return['latitude']  = ifset( $json['point']['coordinates'][0] );
		$return['longitude'] = ifset( $json['point']['coordinates'][1] );

		return array_filter( $return );
	}

}

register_sloc_provider( new Geo_Provider_Bing() );

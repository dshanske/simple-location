<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

/**
 * Reverse Geolocation using Google API.
 *
 * @since 1.0.0
 */
class Geo_Provider_Google extends Geo_Provider {


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
		$this->name = __( 'Google', 'simple-location' );
		$this->slug = 'google';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_google_api' );
		}

		$option = get_option( 'sloc_geo_provider' );
		if ( 'google' === $option ) {
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
			add_action( 'init', array( get_called_class(), 'init' ) );
		}
		parent::__construct( $args );
	}

	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		add_settings_field(
			'googleapi', // ID.
			__( 'Google Maps API Key', 'simple-location' ), // Setting title.
			array( 'Loc_Config', 'string_callback' ), // Display callback.
			'sloc_providers', // Settings page.
			'sloc_api', // Settings section.
			array(
				'label_for' => 'sloc_google_api',
			)
		);
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		register_setting(
			'sloc_providers', // Option group.
			'sloc_google_api', // Option name.
			array(
				'type'         => 'string',
				'description'  => 'Google Maps API Key',
				'show_in_rest' => false,
				'default'      => '',
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
			'locations' => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
			'key'       => $this->api,
		);
		$url  = 'https://maps.googleapis.com/maps/api/elevation/json';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( isset( $json['error_message'] ) ) {
			return new WP_Error( $json['status'], $json['error_message'] );
		}
		if ( ! isset( $json['results'] ) ) {
			return null;
		}
		return round( $json['results'][0]['elevation'], 2 );
	}

	/**
	 * Return an address.
	 *
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Google', 'simple-location' ) );
		}
		$args = array(
			'latlng' => $this->latitude . ',' . $this->longitude,
			// 'language'      => get_bloginfo( 'language' ),
			// 'location_type' => 'ROOFTOP|RANGE_INTERPOLATED',
			// 'result_type'   => 'street_address',
			'key'    => $this->api,
		);
		$url  = 'https://maps.googleapis.com/maps/api/geocode/json?';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$addr              = $this->address_to_mf2( $json );
		$addr['latitude']  = $this->latitude;
		$addr['longitude'] = $this->longitude;
		return $addr;
	}

	/**
	 * Convert address properties to mf2
	 *
	 * @param  array $data Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	private function address_to_mf2( $data ) {
		$addr = array();
		if ( WP_DEBUG ) {
			$addr['raw'] = $data;
		}

		$addr['display-name'] = ifset( $data['formatted_address'] );
		$addr['plus-code']    = ifset( $data['plus_code']['global_code'] );
		$result               = ifset( $data['results'][0] );
		if ( isset( $result['address_components'] ) ) {
			foreach ( $result['address_components'] as $component ) {
				if ( in_array( 'administrative_area_level_1', $component['types'], true ) ) {
					$addr['region'] = $component['long_name'];
				}
				if ( in_array( 'country', $component['types'], true ) ) {
					$addr['country-name'] = $component['long_name'];
					$addr['country-code'] = $component['short_name'];
				}
				if ( in_array( 'neighborhood', $component['types'], true ) ) {
					$addr['extended-address'] = $component['long_name'];
				}
				if ( in_array( 'locality', $component['types'], true ) ) {
					$addr['locality'] = $component['long_name'];
				}
				if ( in_array( 'street_number', $component['types'], true ) ) {
					$addr['street-address'] = $component['long_name'];
				}
				if ( in_array( 'route', $component['types'], true ) ) {
					if ( isset( $addr['street-address'] ) ) {
						$addr['street-address'] .= ' ' . $component['long_name'];
					} else {
						$addr['street-address'] = $component['long_name'];
					}
				}
				if ( in_array( 'postal_code', $component['types'], true ) ) {
					$addr['postal-code'] = $component['long_name'];
				}
			}
		}

		$tz = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		if ( ! array_key_exists( 'display-name', $addr ) ) {
			$addr['display-name'] = $this->display_name( $addr );
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
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Google', 'simple-location' ) );
		}
		$args = array(
			'key'     => $this->api,
			'address' => $address,

		);
		$url  = 'https://maps.googleapis.com/maps/api/geocode/json';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( wp_is_numeric_array( $json['results'] ) ) {
			$json = $json['results'][0];
		}
		$return = $this->address_to_mf2( $json );
		if ( isset( $json['geometry'] ) ) {
			$return['latitude']  = $json['geometry']['location']['lat'];
			$return['longitude'] = $json['geometry']['location']['lng'];
		}
		return $return;
	}
}

register_sloc_provider( new Geo_Provider_Google() );

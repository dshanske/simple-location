<?php
// Bing Geocode API Provider
class Geo_Provider_Bing extends Geo_Provider {

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


	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_bing_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'Bing Maps API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
	}

	public static function admin_init() {
		add_settings_field(
			'bingapi', // id
			__( 'Bing API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_bing_api',
			)
		);
	}

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



	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Bing', 'simple-location' ) );
		}
		$args = array(
			'key' => $this->api,
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

		$addr                   = array(
			'latitude'  => $this->latitude,
			'longitude' => $this->longitude,
		);
		$addr['display-name']   = $json['name'];
		$addr['street-address'] = ifset( $json['address']['addressLine'] );
		$addr['locality']       = ifset( $json['address']['locality'] );
		$addr['region']         = ifset( $json['address']['adminDistrict'] );
		$addr['country-name']   = ifset( $json['address']['countryRegion'] );
		$addr['postal-code']    = ifset( $json['address']['postalCode'] );
		$addr['label']          = ifset( $json['address']['landmark'] );

		$tz = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		if ( WP_DEBUG ) {
			$addr['raw'] = $json;
		}
		return $addr;
	}
}

register_sloc_provider( new Geo_Provider_Bing() );

<?php
// HERE Geocode API Provider
class Geo_Provider_Here extends Geo_Provider {

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

	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_here_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'HERE Maps API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
	}

	public static function admin_init() {
		add_settings_field(
			'hereapi', // id
			__( 'HERE API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_here_api',
			)
		);
	}

	public function elevation() {
		return 0;
	}




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
		$json                   = $json['items'][0]['address'];
		$addr                   = array(
			'latitude'  => $this->latitude,
			'longitude' => $this->longitude,
		);
		$addr['display-name']   = $json['label'];
		$addr['street-address'] = ifset( $json['street'] );
		$addr['locality']       = ifset( $json['city'] );
		$addr['region']         = ifset( $json['state'] );
		$addr['country-name']   = ifset( $json['countryName'] );
		$addr['country-code']   = ifset( $json['countryCode'] );
		$addr['postal-code']    = ifset( $json['postalCode'] );
		$addr['label']          = ifset( $json['Label'] );

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

register_sloc_provider( new Geo_Provider_Here() );

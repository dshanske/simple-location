<?php
// MapQuest Nominatim API Provider
class Geo_Provider_Mapquest extends Geo_Provider {

	public function __construct( $args = array() ) {
		$this->name = __( 'Open Search(Nominatim) via Mapquest', 'simple-location' );
		$this->slug = 'mapquest';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_mapquest_api' );
		}

		add_action( 'init', array( get_called_class(), 'init' ) );
		add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_mapquest_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'Mapquest API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
	}

	public static function admin_init() {
		add_settings_field(
			'mapquestapi', // id
			__( 'MapQuest API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_mapquest_api',
			)
		);
	}

	public function elevation() {
		if ( empty( $this->api ) ) {
			return null;
		}
		$query = add_query_arg(
			array(
				'latLngCollection' => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
				'key'              => $this->api,
			),
			'https://open.mapquestapi.com/elevation/v1/profile'
		);
		$args  = array(
			'headers'             => array(
				'Accept' => 'application/json',
			),
			'timeout'             => 10,
			'limit_response_size' => 1048576,
			'redirection'         => 1,
			// Use an explicit user-agent for Simple Location
			'user-agent'          => 'Simple Location for WordPress',
		);

			$response = wp_remote_get( $query, $args );
		if ( is_wp_error( $response ) ) {
				return $response;
		}
			$code = wp_remote_retrieve_response_code( $response );
		if ( ( $code / 100 ) !== 2 ) {
				return new WP_Error( 'invalid_response', wp_remote_retrieve_body( $response ), array( 'status' => $code ) );
		}
			$json = json_decode( $response['body'], true );
		if ( isset( $json['error_message'] ) ) {
				return new WP_Error( $json['status'], $json['error_message'] );
		}
		if ( ! isset( $json['elevationProfile'] ) ) {
			return null;
		}
			return $json['elevationProfile'][0]['height'];
	}


	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API Key for Mapquest', 'simple-location' ) );
		}
		$query = add_query_arg(
			array(
				'format'          => 'json',
				'extratags'       => '1',
				'addressdetails'  => '1',
				'lat'             => $this->latitude,
				'lon'             => $this->longitude,
				'zoom'            => $this->reverse_zoom,
				'accept-language' => get_bloginfo( 'language' ),
				'key'             => $this->api,
			),
			'https://open.mapquestapi.com/nominatim/v1/reverse.php'
		);
		$args  = array(
			'headers'             => array(
				'Accept' => 'application/json',
			),
			'timeout'             => 10,
			'limit_response_size' => 1048576,
			'redirection'         => 1,
			// Use an explicit user-agent for Simple Location
			'user-agent'          => 'Simple Location for WordPress',
		);

		$response = wp_remote_get( $query, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( ( $code / 100 ) !== 2 ) {
			return new WP_Error( 'invalid_response', wp_remote_retrieve_body( $response ), array( 'status' => $code ) );
		}
		$json    = json_decode( $response['body'], true );
		$address = $json['address'];
		if ( 'us' === $address['country_code'] ) {
			$region = ifset( $address['state'] ) ?: ifset( $address['county'] );
		} else {
			$region = ifset( $address['county'] ) ?: ifset( $address['state'] );
		}
		$street  = ifset( $address['house_number'], '' ) . ' ';
		$street .= ifset( $address['road'] ) ?: ifset( $address['highway'] ) ?: ifset( $address['footway'] ) ?: '';
		$addr    = array(
			'name'             => ifset( $address['attraction'] ) ?: ifset( $address['building'] ) ?: ifset( $address['hotel'] ) ?: ifset( $address['address29'] ) ?: ifset( $address['address26'] ) ?: null,
			'street-address'   => $street,
			'extended-address' => ifset( $address['boro'] ) ?: ifset( $address['neighbourhood'] ) ?: ifset( $address['suburb'] ) ?: null,
			'locality'         => ifset( $address['hamlet'] ) ?: ifset( $address['village'] ) ?: ifset( $address['town'] ) ?: ifset( $address['city'] ) ?: null,
			'region'           => $region,
			'country-name'     => ifset( $address['country'] ) ?: null,
			'postal-code'      => ifset( $address['postcode'] ) ?: null,
			'country-code'     => strtoupper( $address['country_code'] ) ?: null,
			'latitude'         => $this->latitude,
			'longitude'        => $this->longitude,
			'raw'              => $address,
		);
		if ( is_null( $addr['country-name'] ) ) {
			$codes                = json_decode( wp_remote_retrieve_body( wp_remote_get( 'http://country.io/names.json' ) ), true );
			$addr['country-name'] = $codes[ $addr['country-code'] ];
		}
				$addr         = array_filter( $addr );
		$addr['display-name'] = $this->display_name( $addr );
		$tz                   = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		return $addr;
	}

}

register_sloc_provider( new Geo_Provider_Mapquest() );

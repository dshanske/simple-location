<?php
// Bing Geocode API Provider
class Geo_Provider_Bing extends Geo_Provider {

	public function __construct( $args = array() ) {
		$this->name = __( 'Bing', 'simple-location' );
		$this->slug = 'bing';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_bing_api' );
		}

		parent::__construct( $args );
	}

	public function elevation() {
		if ( empty( $this->api ) ) {
			return null;
		}
		$query = add_query_arg(
			array(
				'points' => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
				'key'    => $this->api,
			),
			'http://dev.virtualearth.net/REST/v1/Elevation/List'
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
		if ( ! isset( $json['resourceSets'] ) ) {
			return null;
		}
			$json = $json['resourceSets'][0]['resources'][0];
		if ( ! isset( $json['elevations'] ) ) {
			return null;
		}
			return $json['elevations'][0];
	}



	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Bing', 'simple-location' ) );
		}
		$query = add_query_arg(
			array(
				'key' => $this->api,
			),
			sprintf( 'https://dev.virtualearth.net/REST/v1/Locations/%1$s,%2$s', $this->latitude, $this->longitude )
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

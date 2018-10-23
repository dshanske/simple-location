<?php
// Google Geocode API Provider
class Geo_Provider_Google extends Geo_Provider {

	public function __construct( $args = array() ) {
		$this->name = __( 'Google', 'simple-location' );
		$this->slug = 'google';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_google_api' );
		}

		parent::__construct( $args );
	}

	public function reverse_lookup() {
		$query = add_query_arg(
			array(
				'latlng'        => $this->latitude . ',' . $this->longitude,
				'language'      => get_bloginfo( 'language' ),
				'location_type' => 'ROOFTOP',
				'result_type'   => 'street_address',
				'key'           => $this->api,
			),
			'https://maps.googleapis.com/maps/api/geocode/json?'
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
		if ( isset( $json['results'] ) ) {
			$data = $json['results'][0];
		} else {
			return array();
		}
		$addr                 = array( 'raw' => $json );
		$addr['display-name'] = ifset( $data['formatted_address'] );
		foreach ( $data['address_components'] as $component ) {
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
			if ( in_array( 'administrative_area_level_2', $component['types'], true ) ) {
				$addr['locality'] = $component['long_name'];
			}
			if ( in_array( 'route', $component['type'], true ) ) {
				$addr['street-address'] .= $component['long_name'];
			}
		}

		$tz = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		return $addr;
	}
}

register_sloc_provider( new Geo_Provider_Google() );

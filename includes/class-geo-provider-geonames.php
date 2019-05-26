<?php
// Geonames Geocode API Provider
class Geo_Provider_Geonames extends Geo_Provider {

	public function __construct( $args = array() ) {
		$this->name = __( 'Geonames', 'simple-location' );
		$this->slug = 'geonames';
		if ( ! isset( $args['user'] ) ) {
			$args['user'] = get_option( 'sloc_geonames_user' );
		}

		parent::__construct( $args );
	}

	public function elevation() {
		return null;
	}



	public function reverse_lookup() {
		if ( ! $this->user ) {
			return null;
		}
		$query = add_query_arg(
			array(
				'username' => $this->user,
				'lat'      => $this->latitude,
				'lng'      => $this->longitude,
			),
			'https://secure.geonames.org/findNearbyPlaceNameJSON'
		);
		error_log( $query );
		$args = array(
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
		$json = $json['geonames'][0];
		$addr = array(
			'latitude'  => $this->latitude,
			'longitude' => $this->longitude,
		);

		$addr['street-address'] = ifset( $json['toponymName'] );
		$addr['locality']       = ifset( $json['adminName1'] );
		// $addr['region']         = ifset( $json[] );
		$addr['country-name'] = ifset( $json['countryName'] );
		$display              = array();
		foreach ( array( 'street-address', 'locality', 'country-name' ) as $prop ) {
			$display[] = ifset( $addr[ $prop ] );
		}
		$addr['display-name'] = implode( ', ', $display );
		$tz                   = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		if ( WP_DEBUG ) {
			$addr['raw'] = $json;
		}
		return $addr;
	}
}

register_sloc_provider( new Geo_Provider_Geonames() );

<?php

class Location_Provider_Compass extends Location_Provider {

	public function __construct( $args = array() ) {
		$this->name = __( 'Compass', 'simple-location' );
		$this->slug = 'compass';
		parent::__construct( $args );
		$this->api        = get_option( 'sloc_compass_api' );
		$this->background = true;
	}

	public function retrieve() {
		$compass  = get_option( 'sloc_compass_url' );
		$url      = sprintf( '%1$s/api/last/?token=%2$s', $compass, $this->api );
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
			$response = wp_remote_get( $url, $args );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response, true );
			if ( ! isset( $response['data'] ) ) {
				return false;
			}
			$response = $response['data'];
			if ( ! isset( $response['geometry'] ) || ! isset( $response['properties'] ) ) {
				return false;
			}
			$coord           = $response['geometry']['coordinates'];
			$this->longitude = $coord[0];
			$this->latitude  = $coord[1];
			$this->altitude  = isset( $coord[2] ) ? $coord[2] : null;
			$properties      = $response['properties'];
			$this->accuracy  = isset( $properties['accuracy'] ) ? $properties['accuracy'] : null;
	}


}

register_sloc_provider( new Location_Provider_Compass() );

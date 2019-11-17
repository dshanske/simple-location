<?php

class Location_Provider_Compass extends Location_Provider {

	public function __construct( $args = array() ) {
		$this->name = __( 'Compass', 'simple-location' );
		$this->slug = 'compass';
		parent::__construct( $args );
		$this->background = true;
		add_filter( 'user_contactmethods', array( get_called_class(), 'user_contactmethods' ), 12 );
	}

	public static function user_contactmethods( $profile_fields ) {
		$profile_fields['compass_api'] = __( 'Compass API Key', 'simple-location' );
		$profile_fields['compass_url'] = __( 'Compass URL', 'simple-location' );
		return $profile_fields;
	}


	public function retrieve( $time = null ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		$compass = get_user_meta( $user_id, 'compass_url', true );
		if ( ! $compass ) {
			return;
		}
		$api = get_user_meta( $user_id, 'compass_api', true );
		if ( ! $api ) {
			return;
		}
		$url  = sprintf( '%1$s/api/last/?token=%2$s', $compass, $api );
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
		if ( $time ) {
			$this->time = $time;
			$url        = add_query_arg( 'before', $time, $url );
		}
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
		$this->altitude  = isset( $coord[2] ) ? round( $coord[2], 2 ) : null;
		$properties      = $response['properties'];
		$this->heading   = array_key_exists( 'heading', $properties ) ? $properties['heading'] : null;
		$this->speed     = array_key_exists( 'speed', $properties ) ? $properties['speed'] : null;
		$this->accuracy  = self::ifnot(
			$properties,
			array(
				'accuracy',
				'horizontal_accuracy',
			)
		);
	}


}

register_sloc_provider( new Location_Provider_Compass() );

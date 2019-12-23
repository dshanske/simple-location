<?php

class Location_Provider_Airport extends Location_Provider {

	public function __construct( $args = array() ) {
		$this->name       = __( 'Set Location from Airport Code', 'simple-location' );
		$this->slug       = 'airport';
		$this->background = false;
		parent::__construct( $args );
	}

	public function retrieve( $time = null, $args = array() ) {
		if ( ! array_key_exists( 'address', $args ) ) {
			return new WP_Error( 'empty', __( 'No code passed through', 'simple-location' ) );
		}
		$code = trim( $args['address'] );
		if ( 3 !== strlen( $code ) ) {
			return new WP_Error( 'empty', __( 'Something was passed but not a 3 letter code', 'simple-location' ) );
		}
		$airport          = Airport_Location::get( $code );
		$this->latitude   = $airport['latitude'];
		$this->longitude  = $airport['longitude'];
		$this->altitude   = $airport['elevation'];
		$this->annotation = $airport['name'];
	}

}

register_sloc_provider( new Location_Provider_Airport() );

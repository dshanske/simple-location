<?php

class Location_Provider_Dummy extends Location_Provider {

	public function __construct( $args = array() ) {
		$this->name = __( 'Set Location from Author Profile', 'homeassistant' );
		$this->slug = 'dummy';
		parent::__construct( $args );
	}

	public function retrieve() {
		$location = WP_Geo_Data::get_geodata( get_user_by( 'ID', $this->user ) );
		if ( ! $location ) {
			return null;
		}
		$properties = array( 'longitude', 'latitude', 'altitude', 'heading', 'speed' );
		foreach ( $properties as $property ) {
			$this->$property = ifset( $location[ $property ] );
		}
	}


}

register_sloc_provider( new Location_Provider_Dummy() );

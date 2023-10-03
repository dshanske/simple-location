<?php
/**
 * Location Provider.
 *
 * @package Simple_Location
 */

/**
 * Location Provider that always returns the location of a specific user.
 *
 * @since 1.0.0
 */
class Location_Provider_Dummy extends Location_Provider {

	public function __construct( $args = array() ) {
		$this->name        = __( 'Set Location from Author Profile', 'simple-location' );
		$this->description = __( 'This will always set the location of posts to the location set in the profile of the author', 'simple-location' );
		$this->slug        = 'dummy';
		$this->background  = true;
		parent::__construct( $args );
	}

	public function retrieve( $time = null, $args = array() ) {
		$location = get_user_geodata( $this->user );
		if ( ! $location ) {
			return null;
		}
		$properties = array( 'longitude', 'latitude', 'altitude', 'heading', 'speed' );
		foreach ( $properties as $property ) {
			$this->$property = ifset( $location[ $property ] );
		}
	}
}

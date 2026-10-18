<?php
/**
 * Location Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Location Provider that always returns the location of a specific user.
 *
 * @since 1.0.0
 */
class Location_Provider_Dummy extends Location_Provider {

	/**
	 * Constructor.
	 *
	 * @since 3.5.2
	 *
	 * @param array $args Arguments. See Location_Provider::__construct().
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Set Location from Author Profile', 'simple-location' );
		$this->description = __( 'This will always set the location of posts to the location set in the profile of the author', 'simple-location' );
		$this->slug        = 'dummy';
		$this->background  = true;
		parent::__construct( $args );
	}

	/**
	 * Sets the location to the one in the user's profile.
	 *
	 * @since 3.5.2
	 *
	 * @param string|int|DateTime $time Unused; the profile has only the current location.
	 * @param array               $args Unused.
	 * @return null Always null. Without a profile location, no location is set.
	 */
	public function retrieve( $time = null, $args = array() ) {
		$this->reset();
		$location = get_user_geodata( $this->user );
		if ( ! $location ) {
			return null;
		}
		$properties = array( 'longitude', 'latitude', 'altitude', 'heading', 'speed' );
		foreach ( $properties as $property ) {
			$this->$property = $location[ $property ] ?? null;
		}
		return null;
	}
}

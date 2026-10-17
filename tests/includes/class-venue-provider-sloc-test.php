<?php
/**
 * Fake venue provider for tests.
 *
 * @package Simple_Location
 */

/**
 * Fake venue provider.
 */
class Venue_Provider_Sloc_Test extends Venue_Provider {

	/**
	 * Constructor.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name = 'Test Venue';
		$this->slug = 'sloc_test';
		parent::__construct( $args );
	}

	/**
	 * Returns a venue at the current coordinates.
	 *
	 * @return array
	 */
	public function reverse_lookup() {
		return array(
			'items' => array(
				array(
					'name'      => 'Test Cafe',
					'latitude'  => $this->latitude,
					'longitude' => $this->longitude,
				),
			),
		);
	}
}

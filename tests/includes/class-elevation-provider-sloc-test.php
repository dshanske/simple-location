<?php
/**
 * Fake elevation provider for tests.
 *
 * @package Simple_Location
 */

/**
 * Fake elevation provider.
 */
class Elevation_Provider_Sloc_Test extends Elevation_Provider {

	/**
	 * Constructor.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name = 'Test Elevation';
		$this->slug = 'sloc_test';
		parent::__construct( $args );
	}

	/**
	 * Returns a fixed elevation.
	 *
	 * @return float
	 */
	public function elevation() {
		return 123.0;
	}
}

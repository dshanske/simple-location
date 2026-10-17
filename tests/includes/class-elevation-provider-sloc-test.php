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
	 * Elevation to return: a number or a WP_Error.
	 *
	 * @var float|WP_Error
	 */
	public static $elevation = 123.0;

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
	 * Returns the canned elevation.
	 *
	 * @return float|WP_Error
	 */
	public function elevation() {
		return static::$elevation;
	}
}

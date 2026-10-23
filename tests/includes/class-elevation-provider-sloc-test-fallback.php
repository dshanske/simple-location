<?php
/**
 * Second fake elevation provider for tests, used as a fallback provider.
 *
 * @package Simple_Location
 */

/**
 * A second fake elevation provider, with its own canned elevation.
 */
class Elevation_Provider_Sloc_Test_Fallback extends Elevation_Provider_Sloc_Test {

	/**
	 * Elevation to return: a number or a WP_Error.
	 *
	 * @var float|WP_Error
	 */
	public static $elevation = 456.0;

	/**
	 * Constructor.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( $args );
		$this->name = 'Test Elevation Fallback';
		$this->slug = 'sloc_test_fallback';
	}
}

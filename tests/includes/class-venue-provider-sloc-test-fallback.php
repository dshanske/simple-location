<?php
/**
 * Fake fallback venue provider for tests.
 *
 * @package Simple_Location
 */

/**
 * Fake fallback venue provider, with its own responses.
 */
class Venue_Provider_Sloc_Test_Fallback extends Venue_Provider_Sloc_Test {

	/**
	 * Value returned by reverse_lookup() in place of the default venue, if set.
	 *
	 * @var array|WP_Error|null
	 */
	public static $result = null;

	/**
	 * Number of lookups.
	 *
	 * @var int
	 */
	public static $calls = 0;

	/**
	 * Constructor.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( $args );
		$this->name = 'Test Venue Fallback';
		$this->slug = 'sloc_test_fallback';
	}
}

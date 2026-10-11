<?php
/**
 * Second fake weather provider for tests, used as the fallback provider.
 *
 * @package Simple_Location
 */

/**
 * A second fake weather provider, used as the fallback provider.
 */
class Weather_Provider_Sloc_Test_Fallback extends Weather_Provider_Sloc_Test {

	/**
	 * Constructor.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( $args );
		$this->slug = 'sloc_test_fallback';
	}
}

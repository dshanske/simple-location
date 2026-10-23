<?php
/**
 * Second fake geo provider for tests, used as a fallback provider.
 *
 * @package Simple_Location
 */

/**
 * A second fake geo provider, with its own canned results.
 */
class Geo_Provider_Sloc_Test_Fallback extends Geo_Provider_Sloc_Test {

	/**
	 * Reverse lookup result to return: an address array or a WP_Error.
	 *
	 * @var array|WP_Error
	 */
	public static $reverse = array();

	/**
	 * Geocode result to return: a location array or a WP_Error.
	 *
	 * @var array|WP_Error
	 */
	public static $geocode = array();

	/**
	 * Coordinates of the last reverse lookup.
	 *
	 * @var array
	 */
	public static $last = array();

	/**
	 * Constructor.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( $args );
		$this->name = 'Test Geo Fallback';
		$this->slug = 'sloc_test_fallback';
	}

	/**
	 * Restore the default responses.
	 */
	public static function reset() {
		static::$reverse = array( 'display-name' => 'Fallback Address' );
		static::$geocode = array(
			'latitude'  => 1.5,
			'longitude' => 2.5,
		);
		static::$last    = array();
	}
}

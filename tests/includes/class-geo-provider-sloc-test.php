<?php
/**
 * Fake reverse geocoding provider for tests.
 *
 * @package Simple_Location
 */

/**
 * Fake reverse geocoding provider.
 */
class Geo_Provider_Sloc_Test extends Geo_Provider {

	/**
	 * Value returned by reverse_lookup().
	 *
	 * @var array|WP_Error
	 */
	public static $reverse = array();

	/**
	 * Value returned by geocode().
	 *
	 * @var array|WP_Error
	 */
	public static $geocode = array();

	/**
	 * Coordinates passed to the last lookup.
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
		$this->name = 'Test Geo';
		$this->slug = 'sloc_test';
		parent::__construct( $args );
	}

	/**
	 * Restore the default responses.
	 */
	public static function reset() {
		static::$reverse = array(
			'display-name' => 'Test Address',
			'country-code' => 'US',
		);
		static::$geocode = array(
			'latitude'  => 45.01,
			'longitude' => -75.44,
		);
		static::$last    = array();
	}

	/**
	 * Returns the canned reverse lookup.
	 *
	 * @return array|WP_Error
	 */
	public function reverse_lookup() {
		static::$last = array( $this->latitude, $this->longitude );
		return static::$reverse;
	}

	/**
	 * Returns the canned geocode result.
	 *
	 * @param string $address Address.
	 * @return array|WP_Error
	 */
	public function geocode( $address ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Required by the parent signature.
		return static::$geocode;
	}

	/**
	 * Returns a fixed elevation.
	 *
	 * @return float
	 */
	public function elevation() {
		return 50.0;
	}
}

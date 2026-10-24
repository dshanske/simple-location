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
	 * Restore the default response.
	 */
	public static function reset() {
		static::$result = null;
		static::$calls  = 0;
	}

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
		++static::$calls;
		if ( null !== static::$result ) {
			return static::$result;
		}
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

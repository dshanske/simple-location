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
	 * Storage level returned by storage().
	 *
	 * @var string
	 */
	public static $storage = Venue_Provider::STORAGE_FULL;

	/**
	 * Seconds returned by cache_time(). Not cached by default, so tests can change the result.
	 *
	 * @var int
	 */
	public static $cache_time = 0;

	/**
	 * Restore the default response.
	 */
	public static function reset() {
		static::$result     = null;
		static::$calls      = 0;
		static::$storage    = Venue_Provider::STORAGE_FULL;
		static::$cache_time = 0;
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
	 * Returns the storage level set for the test.
	 *
	 * @return string
	 */
	public function storage() {
		return static::$storage;
	}

	/**
	 * Returns the cache time set for the test.
	 *
	 * @return int
	 */
	public function cache_time() {
		return static::$cache_time;
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

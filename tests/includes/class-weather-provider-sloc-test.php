<?php
/**
 * Fake weather provider for tests.
 *
 * @package Simple_Location
 */

/**
 * Fake weather provider.
 */
class Weather_Provider_Sloc_Test extends Weather_Provider {

	/**
	 * Value returned by get_conditions().
	 *
	 * @var array|WP_Error
	 */
	public static $conditions = array();

	/**
	 * Time passed to the last get_conditions() call.
	 *
	 * @var mixed
	 */
	public static $time;

	/**
	 * Constructor.
	 *
	 * @param array $args Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name = 'Test Weather';
		$this->slug = 'sloc_test';
		parent::__construct( $args );
	}

	/**
	 * Restore the default responses.
	 */
	public static function reset() {
		static::$conditions = array(
			'temperature' => 20,
			'summary'     => 'Clear',
		);
		static::$time       = null;
	}

	/**
	 * Station ID currently set.
	 *
	 * @return string
	 */
	public function get_station_id() {
		return $this->station_id;
	}

	/**
	 * Returns the canned conditions.
	 *
	 * @param mixed $time Time.
	 * @return array|WP_Error
	 */
	public function get_conditions( $time = null ) {
		static::$time = $time;
		return static::$conditions;
	}
}

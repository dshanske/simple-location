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
	 * Values returned by get_conditions() for a provider slug, in place of $conditions.
	 *
	 * @var array
	 */
	public static $responses = array();

	/**
	 * Slugs of the providers get_conditions() was called on, in order.
	 *
	 * @var string[]
	 */
	public static $calls = array();

	/**
	 * Location of the last get_conditions() call.
	 *
	 * @var array|false|null
	 */
	public static $location;

	/**
	 * Station of the last get_conditions() call.
	 *
	 * @var string|null
	 */
	public static $station;

	/**
	 * Whether a provider slug has weather for any time. Default true.
	 *
	 * @var bool[]
	 */
	public static $supports = array();

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
		static::$responses  = array();
		static::$calls      = array();
		static::$location   = null;
		static::$station    = null;
		static::$supports   = array();
	}

	/**
	 * Sets the countries the provider covers.
	 *
	 * @param string|string[]|false $region Two-letter country codes, or false for everywhere.
	 */
	public function set_region( $region ) {
		$this->region = $region;
	}

	/**
	 * Whether the provider has weather for a time, from $supports.
	 *
	 * @param mixed $time Time.
	 * @return bool
	 */
	public function supports_time( $time = null ) {
		return static::$supports[ $this->slug ] ?? true;
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
		static::$time     = $time;
		static::$calls[]  = $this->slug;
		static::$location = $this->get();
		static::$station  = $this->station_id;
		return array_key_exists( $this->slug, static::$responses ) ? static::$responses[ $this->slug ] : static::$conditions;
	}
}

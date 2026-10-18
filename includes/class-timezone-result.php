<?php
/**
 * Timezone Result
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * A timezone, with its offset and local time at a given time.
 *
 * @since 3.0.1
 */
class Timezone_Result {
	/**
	 * The timezone.
	 *
	 * @since 3.0.1
	 * @var DateTimeZone|null
	 */
	public $timezone = null;

	/**
	 * The time, in the timezone.
	 *
	 * @since 3.0.1
	 * @var DateTime
	 */
	private $now;

	/**
	 * Timezone identifier.
	 *
	 * @since 3.0.1
	 * @var string
	 */
	private $name;

	/**
	 * Constructor.
	 *
	 * @since 3.0.1
	 *
	 * @param string       $timezone Timezone identifier.
	 * @param string|false $date     Time, in a format DateTime accepts. Optional; defaults to now.
	 */
	public function __construct( $timezone, $date = false ) {
		if ( $date ) {
			$this->now = new DateTime( $date );
		} else {
			$this->now = new DateTime();
		}
		$this->timezone = new DateTimeZone( $timezone );
		$this->now->setTimeZone( $this->timezone );
		$this->name = $timezone;
	}

	/**
	 * Returns a property.
	 *
	 * @since 3.0.1
	 *
	 * @param string $key 'offset' (such as +05:30), 'seconds' (the offset in seconds), 'localtime' (ISO 8601) or 'name'.
	 * @return string|int|null The value, or null for another key.
	 */
	public function __get( $key ) {
		switch ( $key ) {
			case 'offset':
				return $this->now->format( 'P' );
			case 'seconds':
				return (int) $this->now->format( 'Z' );
			case 'localtime':
				return $this->now->format( 'c' );
			case 'name':
				return $this->name;
		}
	}

	/**
	 * Returns the timezone identifier.
	 *
	 * @since 3.0.1
	 *
	 * @return string Timezone identifier, or the site timezone without one.
	 */
	public function __toString() {
		if ( is_string( $this->name ) ) {
			return $this->name;
		}
		return wp_timezone_string();
	}
}

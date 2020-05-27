<?php
/**
 * Astronomical Calculator Class.
 *
 * @package Simple_Location
 */

/**
 * Astronomical Calculator Class.
 *
 * Uses PHP Functionality and Formulas to Calculate Sunset Accurately based on Location and Elevation
 */
class Astronomical_Calculator {


	 /**
	  * Zenith. Adjusted based on elevation.
	  *
	  * @since 4.0.0
	  * @var float
	  */
	protected $zenith;

	 /**
	  * Latitude.
	  *
	  * @since 4.0.0
	  * @var float
	  */
	protected $latitude;

	 /**
	  * Longitude.
	  *
	  * @since 4.0.0
	  * @var float
	  */
	protected $longitude;

	 /**
	  * Elevation in meters.
	  *
	  * @since 4.0.0
	  * @var float
	  */
	protected $elevation;

	 /**
	  * Timezone.
	  *
	  * @since 4.0.0
	  * @var DateTimeZone
	  */
	protected $timezone;



	/**
	 * Constructor class.
	 *
	 * Instantiates the class with the location, zenith, and timezone.

	 * @param float $latitude Latitude.
	 * @param float $longitude Longitude.
	 * @param int   $elevation Optional. Elevation in meters.
	 *
	 * @since 4.0.0
	 */
	public function __construct( $latitude, $longitude, $elevation = null ) {
		$this->latitude  = $latitude;
		$this->longitude = $longitude;
		$this->elevation = intval( $elevation );
		$this->zenith    = $this->get_zenith();
		$this->timezone  = new DateTimeZone( Loc_Timezone::timezone_for_location( $latitude, $longitude ) );
	}


	/**
	 * Returns sunrise or sunset as a timestamp based on the day input.
	 *
	 * Uses built in PHP functions to return sunrise or sunset.

	 * @param int    $timestamp Unix timestamp reflecting day being checked.
	 * @param string $type Can either be 'sunrise' or 'sunset'. Default sunrise.
	 * @return int $timestamp Unix timestamp reflecting sunrise or sunset as requested.
	 *
	 * @since 4.0.0
	 */
	public function get_timestamp( $timestamp, $type = 'sunrise' ) {
		if ( ! $timestamp ) {
			$timestamp = time();
		}
		switch ( $type ) {
			case 'sunset':
				$function = 'date_sunset';
				break;
			default:
				$function = 'date_sunrise';
		}
		return call_user_func( $function, $timestamp, SUNFUNCS_RET_TIMESTAMP, $this->latitude, $this->longitude, self::get_zenith( $this->elevation ) );
	}


	/**
	 * Returns a DateTime object adjusting into the calculated timezone.
	 *
	 * Takes a timestamp and the derived timezone and returns an object.

	 * @param int $timestamp Unix timestamp.
	 * @return DateTime $datetime DateTime object with proper timezone set.
	 *
	 * @since 4.0.0
	 */
	private function timestamp_to_datetime( $timestamp ) {
		$datetime = new DateTime();
		$datetime->setTimestamp( $timestamp );
		$datetime->setTimezone( $this->timezone );
		return $datetime;
	}


	/**
	 * Returns a formatted string adjusted into the calculated timezone.
	 *
	 * Takes a timestamp and the derived timezone and returns a formatted string.

	 * @param int    $timestamp Unix timestamp.
	 * @param string $format Date time format string.
	 * @param string $type Can either be 'sunrise' or 'sunset'. Default sunrise.
	 * @return string $date formatted date string.
	 *
	 * @since 4.0.0
	 */
	public function get_formatted( $timestamp, $format = '', $type = 'sunrise' ) {
		return wp_date( $format, $this->get_timestamp( $timestamp, $type ), $this->timezone );
	}


	/** Returns a form adjusted into the calculated timezone.
	 *
	 * Takes a timestamp and the derived timezone and returns a formatted string.

	 * @param int    $timestamp Unix timestamp.
	 * @param string $type Can either be 'sunrise' or 'sunset'. Default sunrise.
	 * @return DateTime $datetime DateTime object with proper timezone set.
	 *
	 * @since 4.0.0
	 */
	public function get_datetime( $timestamp, $type = 'sunrise' ) {
		return $this->timestamp_to_datetime( $this->get_timestamp( $timestamp, $type ) );
	}


	/** Returns an ISO8601 formatted string adjusted into the calculated timezone.
	 *
	 * Takes a timestamp and the derived timezone and returns an ISO8601-formatted string.
	 *
	 * @param int    $timestamp Unix timestamp.
	 * @param string $type Can either be 'sunrise' or 'sunset'. Default sunrise.
	 * @return string $date ISO8601 formatted string
	 *
	 * @since 4.0.0
	 */
	public function get_iso8601( $timestamp, $type = 'sunrise' ) {
		$datetime = $this->get_datetime( $timestamp, $type );
		return $datetime->format( DATE_W3C );
	}

	/** Calculates the Zenith Based on Elevation
	 *
	 * Adjusts the Zenith Based on Elevation.
	 *
	 * @return float $zenith Zenith calculated.
	 *
	 * @since 4.0.0
	 */
	private function get_zenith() {
		$zenith = 90.583333; // default zenith.
		if ( 0 < $this->elevation ) {
			$adjustment = 0.0347 * sqrt( $this->elevation );
			$zenith     = $zenith + $adjustment;
		}
		return $zenith;
	}

	/** Calculates if a number is between two other numbers.
	 *
	 * Used to calculate the is_daytime parameter.
	 *
	 * @param int $number Number to check.
	 * @param int $from Lower boundary.
	 * @param int $to Upper boundary.
	 * @return boolean $between True if between the two numbers.
	 *
	 * @since 4.0.0
	 */
	public static function between( $number, $from, $to ) {
		return $number > $from && $number < $to;
	}


	/** Determines if it is daytime or not.
	 *
	 * Calculates if the provided time is daytime or not.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return boolean $is_daytime Return true if daytime.
	 *
	 * @since 4.0.0
	 */
	public function is_daytime( $timestamp = null ) {
		if ( ! $timestamp ) {
			$timestamp = null;
		}
		$sunrise = $this->get_timestamp( $timestamp, 'sunrise' );
		$sunset  = $this->get_timestamp( $timestamp, 'sunset' );
		if ( self::between( $timestamp, $sunrise, $sunset ) ) {
			return true;
		}
		return false;
	}
}

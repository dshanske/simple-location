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

	/** Returns current moonphase data.
	 *
	 * Placeholder. Would like to redo this to something more exacting.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return array Moon Phase.
	 *
	 * @since 4.1.5
	 */
	public function get_moon_phase( $timestamp = null ) {
		// The duration in days of a lunar cycle
		 $lunardays = 29.53058770576;
		 // Seconds in lunar cycle
		 $lunarsecs = $lunardays * ( 24 * 60 * 60 );
		 // Date time of first new moon in year 2000
		 $new2000 = 947182440;

		// Calculate seconds between date and new moon 2000
		$totalsecs = $timestamp - $new2000;

		 // Calculate modulus to drop completed cycles
		 // Note: for real numbers use fmod() instead of % operator
		 $currentsecs = fmod( $totalsecs, $lunarsecs );

		/*
		 Array with start and end of each phase
		 * In this array 'new', 'first-quarter', 'full' and
		 * 'third-quarter' each get a duration of 2 days.
		 */
		$phases = array(
			array( 'new', 0, 1 ),
			array( 'waxing-crescent', 1, 6.38264692644 ),
			array( 'first-quarter', 6.38264692644, 8.38264692644 ),
			array( 'waxing-gibbous', 8.38264692644, 13.76529385288 ),
			array( 'full', 13.76529385288, 15.76529385288 ),
			array( 'waning-gibbous', 15.76529385288, 21.14794077932 ),
			array( 'third-quarter', 21.14794077932, 23.14794077932 ),
			array( 'waning-crescent', 23.14794077932, 28.53058770576 ),
			array( 'new', 28.53058770576, 29.53058770576 ),
		);

		 // If negative number (date before new moon 2000) add $lunarsecs
		if ( $currentsecs < 0 ) {
			$currentsecs += $lunarsecs;
		}

		 // Calculate the fraction of the moon cycle
		 $currentfrac = $currentsecs / $lunarsecs;

		// Calculate days in current cycle (moon age)
		 $currentdays = $currentfrac * $lunardays;

		// Find current phase in the array
		for ( $i = 0; $i < 9; $i++ ) {
			if ( ( $currentdays >= $phases[ $i ][1] ) && ( $currentdays <= $phases[ $i ][2] ) ) {
				$thephase = $phases[ $i ][0];
				break;
			}
		}

		 $phasedata = array(
			 'new'             => array(
				 'name' => 'new-moon',
				 'text' => __( 'New', 'simple-location' ),
				 'icon' => 'wi-moon-new',
			 ),
			 'waxing-crescent' => array(
				 'name' => 'waxing-crescent-moon',
				 'text' => __( 'Waxing Crescent', 'simple-location' ),
				 'icon' => 'wi-moon-waxing-crescent-6',
			 ),
			 'first-quarter'   => array(
				 'name' => 'first-quarter-moon',
				 'text' => __( 'First Quarter', 'simple-location' ),
				 'icon' => 'wi-moon-first-quarter',
			 ),

			 'full'            => array(
				 'name' => 'full-moon',
				 'text' => __( 'Full Moon', 'simple-location' ),
				 'icon' => 'wi-moon-full',
			 ),
			 'waning-gibbous'  => array(
				 'name' => 'waning-gibbous-moon',
				 'text' => __( 'Waning Gibbous', 'simple-location' ),
				 'icon' => 'wi-moon-waning-gibbous-1',
			 ),
			 'third-quarter'  => array(
				 'name' => 'third-quarter-moon',
				 'text' => __( 'Third Quarter', 'simple-location' ),
				 'icon' => 'wi-moon-third-quarter',
			 ),
		 );

		 return $phasedata[ $thephase ];
	}

}

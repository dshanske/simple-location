<?php
/**
 * Astronomical Calculator Class.
 *
 * @package Simple_Location
 */

define( 'PI', M_PI );
define( 'RAD', PI / 180 );
define( 'E', RAD * 23.4397 ); // obliquity of the Earth.

/**
 * Astronomical Calculator Class.
 *
 * Uses PHP Functionality and Formulas to Calculate Sunset Accurately based on Location and Elevation
 * Moon calculations derived from https://github.com/gregseth/suncalc-php
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
	public function __construct( $latitude, $longitude, $elevation = 0 ) {
		$this->latitude  = floatval( $latitude );
		$this->longitude = floatval( $longitude );
		$this->elevation = floatval( $elevation );
		$this->zenith    = $this->get_zenith();
		$this->timezone  = Loc_Timezone::timezone_for_location( $latitude, $longitude );
		if ( $this->timezone instanceof Timezone_Result ) {
			$this->timezone = $this->timezone->timezone;
		} elseif ( is_null( $this->timezone ) ) {
			$this->timezone = wp_timezone();
		}
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
		if ( ! is_numeric( $this->latitude ) && ! is_numeric( $this->longitude ) ) {
			return null;
		}
		if ( ! $timestamp ) {
			$timestamp = time();
		}

		if ( ! is_numeric( $timestamp ) && is_string( $timestamp ) ) {
			$timestamp = new DateTime( $timestamp );
		}

		if ( $timestamp instanceof DateTime ) {
			$timestamp = $timestamp->getTimestamp();
		}

		$times = date_sun_info( $timestamp, $this->latitude, $this->longitude );
		if ( ! $times ) {
			return false;
		}

		switch ( $type ) {
			case 'sunset':
				return $times['sunset'];
				$function = 'date_sunset';
				break;
			case 'moonset':
				$moon = $this->get_moon_times( $timestamp );
				return $moon['moonset'];
			case 'moonrise':
				$moon = $this->get_moon_times( $timestamp );
				return $moon['moonrise'];
			default:
				return $times['sunrise'];
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
		if ( is_numeric( $this->elevation ) && 0 < $this->elevation ) {
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
	 * @return string 'true' or 'false'
	 *
	 * @since 4.0.0
	 */
	public function is_daytime( $timestamp = null ) {
		if ( ! $timestamp ) {
			$timestamp = time();
		}
		$sunrise = $this->get_timestamp( $timestamp, 'sunrise' );
		$sunset  = $this->get_timestamp( $timestamp, 'sunset' );
		if ( self::between( $timestamp, $sunrise, $sunset ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Convert a timestamp to Julian.
	 *
	 * @param int $timestamp UNIX Timestamp.
	 * @return int Julian Days since EPOCH.
	 */
	public function to_julian( $timestamp ) {
		return ( $timestamp / DAY_IN_SECONDS ) + 2440587.5;
	}

	/**
	 * Convert a timestamp to number of days since year 2000.
	 *
	 * @param int $timestamp Unix Time Stamp.
	 * @return int Julian days since J2000.
	 */
	public function to_days( $timestamp ) {
		return $this->to_julian( $timestamp ) - 2451545;
	}


	/**
	 * Calculate Solar Mean Anomaly.
	 *
	 * @param int $days Julian Day since J2000.
	 * @return float Declination.
	 */
	public function solar_mean_anomaly( $days ) {
		return RAD * ( 357.5291 + 0.98560028 * $days );
	}


	/**
	 * Calculate Right Ascension.
	 *
	 * @param float $lng Longitude.
	 * @param float $lat Latitude.
	 * @return float Right Ascension.
	 */
	public function right_ascension( $lng, $lat ) {
		return atan2( sin( $lng ) * cos( E ) - tan( $lat ) * sin( E ), cos( $lng ) );
	}


	/**
	 * Calculate Declination.
	 *
	 * @param float $lng Longitude.
	 * @param float $lat Latitude.
	 * @return float Declination.
	 */
	public function declination( $lng, $lat ) {
		return asin( sin( $lat ) * cos( E ) + cos( $lat ) * sin( E ) * sin( $lng ) );
	}

	/**
	 * Calculate Ecliptic Longitude of the Sun.
	 *
	 * @param float $anomaly Mean Anomaly.
	 * @return float Ecliptic Longitude.
	 */
	public function ecliptic_longitude( $anomaly ) {
		$center     = RAD * ( 1.9148 * sin( $anomaly ) + 0.02 * sin( 2 * $anomaly ) + 0.0003 * sin( 3 * $anomaly ) ); // equation of center.
		$perihelion = RAD * 102.9372; // perihelion of the Earth.

		return $anomaly + $center + $perihelion + PI;
	}

	/**
	 * Geocentric Ecliptical Coordinates of the sun.
	 *
	 * @param int $days Julian Days.
	 * @return array Coordinates of the Sun on the Julian Day.
	 */
	public function sun_coords( $days ) {
		$anomaly = $this->solar_mean_anomaly( $days );
		$lng     = $this->ecliptic_longitude( $anomaly );

		return array(
			'dec' => $this->declination( $lng, 0 ),
			'ra'  => $this->right_ascension( $lng, 0 ),
		);
	}

	/**
	 * Geocentric Ecliptical Coordinates of the moon.
	 *
	 * @param int $days Julian Days.
	 * @return array Coordinates of the Moon on the Julian Day.
	 */
	protected function moon_coords( $days ) {
		$lng     = RAD * ( 218.316 + 13.176396 * $days ); // Ecliptic longitude.
		$anomaly = RAD * ( 134.963 + 13.064993 * $days ); // Mean anomaly.
		$f       = RAD * ( 93.272 + 13.229350 * $days );  // Mean distance.

		$lng      = $lng + RAD * 6.289 * sin( $anomaly ); // Convert ecliptic longitude to longitude.
		$lat      = RAD * 5.128 * sin( $f );     // Latitude.
		$distance = 385001 - 20905 * cos( $anomaly );  // Distance to the moon in km.

		return array(
			'dec'  => $this->declination( $lng, $lat ),
			'ra'   => $this->right_ascension( $lng, $lat ),
			'dist' => $distance,
		);
	}

	/** Returns current moon illumination data.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return array Moon Phase Information.
	 *
	 * @since 4.1.6
	 */
	public function get_moon_illumination( $timestamp = null ) {
		if ( is_null( $timestamp ) ) {
			$timestamp = time();
		}
		$days = $this->to_days( $timestamp );
		$sun  = $this->sun_coords( $days );
		$moon = $this->moon_coords( $days );

		$sdist = 149598000; // distance from Earth to Sun in km.

		$phi   = acos( sin( $sun['dec'] ) * sin( $moon['dec'] ) + cos( $sun['dec'] ) * cos( $moon['dec'] ) * cos( $sun['ra'] - $moon['ra'] ) );
		$inc   = atan2( $sdist * sin( $phi ), $moon['dist'] - $sdist * cos( $phi ) );
		$angle = atan2( cos( $sun['dec'] ) * sin( $sun['ra'] - $moon['ra'] ), sin( $sun['dec'] ) * cos( $moon['dec'] ) - cos( $sun['dec'] ) * sin( $moon['dec'] ) * cos( $sun['ra'] - $moon['ra'] ) );

		return array_merge(
			$moon,
			array(
				'fraction' => ( 1 + cos( $inc ) ) / 2,
				'phase'    => 0.5 + 0.5 * $inc * ( $angle < 0 ? -1 : 1 ) / PI,
				'angle'    => $angle,
			)
		);
	}



	/** Returns current moon data.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return array Moon Data.
	 *
	 * @since 4.1.6
	 */
	public function get_moon_data( $timestamp = null ) {
		if ( is_null( $timestamp ) ) {
			$timestamp = time();
		}
		$moon = $this->get_moon_illumination( $timestamp );
		if ( 0 === $moon['phase'] ) {
			$return = array(
				'name' => 'new-moon',
				'text' => __( 'New', 'simple-location' ),
				'icon' => 'wi-moon-alt-new',
			);
		} elseif ( 0 < $moon['phase'] && 0.25 > $moon['phase'] ) {
			$return = array(
				'name' => 'waxing-crescent-moon',
				'text' => __( 'Waxing Crescent', 'simple-location' ),
				'icon' => 'wi-moon-alt-waxing-crescent-6',
			);
		} elseif ( 0.25 < $moon['phase'] && 0.5 > $moon['phase'] ) {
			$return = array(
				'name' => 'first-quarter-moon',
				'text' => __( 'First Quarter', 'simple-location' ),
				'icon' => 'wi-moon-alt-first-quarter',
			);
		} elseif ( 0.5 === $moon['phase'] ) {
			$return = array(
				'name' => 'full-moon',
				'text' => __( 'Full Moon', 'simple-location' ),
				'icon' => 'wi-moon-alt-full',
			);
		} elseif ( 0.5 < $moon['phase'] && 0.75 > $moon['phase'] ) {
			$return = array(
				'name' => 'waning-gibbous-moon',
				'text' => __( 'Waning Gibbous', 'simple-location' ),
				'icon' => 'wi-moon-alt-waning-gibbous-1',
			);
		} elseif ( 0.75 === $moon['phase'] ) {
			$return = array(
				'name' => 'third-quarter-moon',
				'text' => __( 'Third Quarter', 'simple-location' ),
				'icon' => 'wi-moon-alt-third-quarter',
			);
		} elseif ( 0.75 < $moon['phase'] && 1 > $moon['phase'] ) {
			$return = array(
				'name' => 'waning-crescent-moon',
				'text' => __( 'Waning Crescent', 'simple-location' ),
				'icon' => 'wi-moon-alt-waning-crescent-1',
			);
		}
		return array_merge( $moon, $return );
	}


	/** Returns current moon data.
	 *
	 * @param int   $timestamp Unix timestamp.
	 * @param float $hours Hours to adjust.
	 * @return int Adds hours to a timestamp.
	 *
	 * @since 4.1.6
	 */
	public function hours_later( $timestamp, $hours ) {
		return $timestamp + round( $hours * 3600 );
	}


	/** Returns the azimuth.
	 *
	 * @param float $sidereal_time Sidereal Time.
	 * @param float $phi Geographic Latitude.
	 * @param float $dec Declination.
	 * @return float Azimith
	 *
	 * @since 4.1.6
	 */
	public function azimuth( $sidereal_time, $phi, $dec ) {
		return atan2( sin( $sidereal_time ), cos( $sidereal_time ) * sin( $phi ) - tan( $dec ) * cos( $phi ) );
	}


	/** Returns the altitude.
	 *
	 * @param float $sidereal_time Sidereal Time.
	 * @param float $phi Geographic Latitude.
	 * @param float $dec Declination.
	 * @return float Altitude.
	 *
	 * @since 4.1.6
	 */
	public function altitude( $sidereal_time, $phi, $dec ) {
		return asin( sin( $phi ) * sin( $dec ) + cos( $phi ) * cos( $dec ) * cos( $sidereal_time ) );
	}


	/** Returns the azimuth.
	 *
	 * @param float $d Julian Days since J2000.
	 * @param float $lw Geographic Longitude.
	 * @return float Sidereal Time.
	 *
	 * @since 4.1.6
	 */
	public function sidereal_time( $d, $lw ) {
		return RAD * ( 280.16 + 360.9856235 * $d ) - $lw;
	}


	/** Returns current moon position.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return array Moon Position Data.
	 *
	 * @since 4.1.6
	 */
	public function get_moon_position( $timestamp = null ) {
		$lw   = RAD * -$this->longitude; // Geographic Longitude.
		$phi  = RAD * $this->latitude; // Geographic Latitude.
		$days = $this->to_days( $timestamp );

		$coords        = $this->moon_coords( $days );
		$sidereal_time = $this->sidereal_time( $days, $lw ) - $coords['ra']; // Sidereal Time.
		$altitude      = $this->altitude( $sidereal_time, $phi, $coords['dec'] );

		// altitude correction for refraction.
		$altitude = $altitude + RAD * 0.017 / tan( $altitude + RAD * 10.26 / ( $altitude + RAD * 5.10 ) );

		return array(
			'azimuth'  => $this->azimuth( $sidereal_time, $phi, $coords['dec'] ),
			'altitude' => $altitude,
			'dist'     => $coords['dist'],
		);
	}

	/** Returns current moon times.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return array Moon times.
	 *
	 * @since 4.1.6
	 */
	public function get_moon_times( $timestamp = null ) {
		$datetime = new DateTime();
		$datetime->setTimezone( new DateTimeZone( 'UTC' ) );
		$datetime->setTimestamp( $timestamp );
		$datetime->setTime( 0, 0, 0 );
		$timestamp = $datetime->getTimestamp();

		$hc   = 0.133 * RAD;
		$h0   = $this->get_moon_position( $timestamp )['altitude'] - $hc;
		$rise = 0;
		$set  = 0;

		// go in 2-hour chunks, each time seeing if a 3-point quadratic curve crosses zero (which means rise or set).
		for ( $i = 1; $i <= 24; $i += 2 ) {
			$h1 = $this->get_moon_position( $this->hours_later( $timestamp, $i ), $this->latitude, $this->longitude )['altitude'] - $hc;
			$h2 = $this->get_moon_position( $this->hours_later( $timestamp, $i + 1 ), $this->latitude, $this->longitude )['altitude'] - $hc;

			$a     = ( $h0 + $h2 ) / 2 - $h1;
			$b     = ( $h2 - $h0 ) / 2;
			$xe    = -$b / ( 2 * $a );
			$ye    = ( $a * $xe + $b ) * $xe + $h1;
			$d     = $b * $b - 4 * $a * $h1;
			$roots = 0;

			if ( $d >= 0 ) {
				$dx     = sqrt( $d ) / ( abs( $a ) * 2 );
				$x1     = $xe - $dx;
					$x2 = $xe + $dx;
				if ( abs( $x1 ) <= 1 ) {
					++$roots;
				}
				if ( abs( $x2 ) <= 1 ) {
					++$roots;
				}
				if ( $x1 < -1 ) {
					$x1 = $x2;
				}
			}

			if ( 1 === $roots ) {
				if ( $h0 < 0 ) {
					$rise = $i + $x1;
				} else {
					$set = $i + $x1;
				}
			} elseif ( 2 === $roots ) {
				$rise = $i + ( $ye < 0 ? $x2 : $x1 );
				$set  = $i + ( $ye < 0 ? $x1 : $x2 );
			}

			if ( 0 !== $rise && 0 !== $set ) {
				break;
			}

			$h0 = $h2;
		}

		$result = array(
			'moonrise' => 0,
			'moonset'  => 0,
		);

		if ( 0 !== $rise ) {
			$result['moonrise'] = $this->hours_later( $timestamp, $rise );
		}
		if ( 0 !== $set ) {
			$result['moonset'] = $this->hours_later( $timestamp, $set );
		}

		if ( 0 === $rise && 0 === $set ) {
			$result[ $ye > 0 ? 'alwaysUp' : 'alwaysDown' ] = true;
		}

		return $result;
	}

	/**
	 *  Calculates Clear Sky Radiation
	 *
	 * @param float $ra RA.
	 * @return float Clear Sky Radiation.
	 */
	public function clear_sky_radiation( $ra ) {
		return ( 0.75 + 0.00002 * $this->elevation ) * $ra;
	}

	/**
	 *  Calculates Sun Radiation.
	 *
	 * @param float $interval The time interval over which the radiation is to be calculated in hours.
	 * @return float The Average Solar Radiation over the time interval in MJ/m^2/hr.
	 */
	public function sun_radiation( $interval ) {
		// Solar constant in MJ/m^2/hr.
		$gsc = 4.92;

		$delta = 0.409 * sin( 2.0 * pi() * ( gmdate( 'z' ) + 1 ) / 365 - 1.39 );

		$earth_distance = 1.0 + 0.033 * cos( 2.0 * pi() * ( gmdate( 'z' ) + 1 ) / 365.0 );

		$tod_utc     = (int) gmdate( 'H' ) + (int) gmdate( 'M' ) / 60.0 + (int) gmdate( 'S' ) / 3600.0;
		$start_utc   = $tod_utc - $interval;
		$stop_utc    = $tod_utc;
		$start_omega = self::hour_angle( $start_utc );
		$stop_omega  = self::hour_angle( $stop_utc );

		$latitude_radians = deg2rad( $this->latitude );

		$part1 = ( $stop_omega - $start_omega ) * sin( $latitude_radians ) * sin( $delta );
		$part2 = cos( $latitude_radians ) * cos( $delta ) * ( sin( $stop_omega ) - sin( $start_omega ) );

		$ra = ( 12.0 / pi() ) * $gsc * $earth_distance * ( $part1 + $part2 );

		if ( $ra < 0 ) {
				$ra = 0;
		}
		return $ra;
	}


	/**
	 *  Solar Hour Angle at a Given Time in Radians.
	 *
	 * @param int $t_utc The Time in UTC.
	 * @return float Hour Angle in Radians.
	 */
	public function hour_angle( $t_utc ) {
		$b     = 2 * pi() * ( ( gmdate( 'z' ) + 1 ) - 81 ) / 364.0;
		$sc    = 0.1645 * sin( 2 * $b ) - 0.1255 * cos( $b ) - 0.025 * sin( $b );
		$omega = ( pi() / 12.0 ) * ( $t_utc + $this->longitude / 15.0 + $sc - 12 );
		if ( $omega < 0 ) {
			$omega += 2.0 * pi();
		}
		return $omega;
	}


	/**
	 *  Uses mean wm/2 for the last hour to estimate cloudiness.
	 *
	 * @param float $wm2 WM/2.
	 * @param int   $humidity Humidity.
	 * @return int Cloudiness as a percentage.
	 */
	public function cloudiness( $wm2, $humidity ) {
		$clear = self::clear_sky_radiation( $this->sun_radiation( 1.0 ) );
		$mean  = $wm2 * 0.0036;
		if ( $clear ) {
			// Return Cloudiness as a percentage number.
			return round( $mean / $clear * 100 );
		} else {
			// If it is nighttime you cannot tell how cloudy it is this way, therefore estimate based on humidity.
			if ( $humidity > 80 ) {
				// Humid - Lots of Clouds.
				return 70;
			} elseif ( $humidity > 40 ) {
				// Somewhat humid. Modest cloud clover.
				return 50;
			} else {
				// Low humidity no clouds.
				return 20;
			}
		}
	}
}

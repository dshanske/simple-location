<?php
/**
 * calculates sunrise sunset etc
 */
class Astronomical_Calculator {

	protected $earth_radius;
	protected $refraction;
	protected $solar_radius;
	protected $zenith;
	protected $latitude;
	protected $longitude;
	protected $elevation;
	protected $timezone;

	public function __construct( $latitude, $longitude, $elevation = null ) {
		$this->latitude  = $latitude;
		$this->longitude = $longitude;
		$this->elevation = intval( $elevation );
		$this->zenith    = $this->get_zenith();
		$this->timezone  = new DateTimeZone( Loc_Timezone::timezone_for_location( $latitude, $longitude ) );
	}

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

	private function timestamp_to_datetime( $timestamp ) {
		$datetime = new DateTime();
		$datetime->setTimestamp( $timestamp );
		$datetime->setTimezone( $this->timezone );
		return $datetime;
	}

	public function get_formatted( $timestamp, $format = '', $type = 'sunrise' ) {
		return wp_date( $format, $this->get_timestamp( $timestamp, $type ), $this->timezone );
	}

	public function get_datetime( $timestamp, $type = 'sunrise' ) {
		return $this->timestamp_to_datetime( $this->get_timestamp( $timestamp, $type ) );
	}

	public function get_iso8601( $timestamp, $type = 'sunrise' ) {
		$datetime = $this->get_datetime( $timestamp, $type );
		return $datetime->format( DATE_W3C );
	}

	private function get_zenith() {
		$zenith = 90.583333; // default zenith
		if ( 0 < $this->elevation ) {
			$adjustment = 0.0347 * sqrt( $this->elevation );
			$zenith     = $zenith + $adjustment;
		}
		return $zenith;
	}

	public static function between( $number, $from, $to ) {
		return $number > $from && $number < $to;
	}

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

<?php
/**
 * Global Functions.
 *
 * @package Simple_Location
 */

if ( ! function_exists( 'wp_exif_gps_convert' ) ) {
	/**
	 * Convert the EXIF geographical longitude and latitude from degrees, minutes
	 * and seconds to degrees format.
	 * This is based on a Trac Ticket - https://core.trac.wordpress.org/ticket/9257
	 * closed due privacy concerns. Updated to match location storage for this just in case
	 * and to use their function over my original one.
	 *
	 * @param array|string $coordinate The coordinate to convert to degrees format.
	 * @return float|false Coordinate in degrees format or false if failure
	 */
	function wp_exif_gps_convert( $coordinate ) {
		if ( is_array( $coordinate ) ) {
			@list( $degree, $minute, $second ) = $coordinate;
			$float                             = wp_exif_frac2dec( $degree ) + ( wp_exif_frac2dec( $minute ) / 60 ) + ( wp_exif_frac2dec( $second ) / 3600 );

			return ( ( is_float( $float ) || ( is_int( $float ) && $degree === $float ) ) && ( abs( $float ) <= 180 ) ) ? $float : 999;
		}
		return false;
	}
}

if ( ! function_exists( 'wp_exif_datetime' ) ) {
	/**
	 * Convert the exif date format to a datetime object
	 *
	 * @param string              $str EXIF string.
	 * @param string|DateTimeZone $timezone A timezone or offset string. Default is the WordPress timezone.
	 * @return DateTime
	 */
	function wp_exif_datetime( $str, $timezone = null ) {
		if ( is_string( $timezone ) ) {
			$timezone = timezone_open( $timezone );
		}

		if ( ! $timezone instanceof DateTimeZone ) {
			$timezone = wp_timezone();
		}
		$datetime = new DateTime( $str, $timezone );
		return $datetime;
	}
}

/**
 * Convert decimal location to a textual representation
 *
 * @param float        $latitude Latitude.
 * @param float        $longitude Longitude.
 * @param float|string $altitude Altitude. Optional.

 * @return string Textual Representation of Location.
 */
function dec_to_dms( $latitude, $longitude, $altitude = '' ) {
	$latitudedirection  = $latitude < 0 ? 'S' : 'N';
	$longitudedirection = $longitude < 0 ? 'W' : 'E';

	$latitudenotation  = $latitude < 0 ? '-' : '';
	$longitudenotation = $longitude < 0 ? '-' : '';

	$latitudeindegrees  = floor( abs( $latitude ) );
	$longitudeindegrees = floor( abs( $longitude ) );

	$latitudedecimal  = abs( $latitude ) - $latitudeindegrees;
	$longitudedecimal = abs( $longitude ) - $longitudeindegrees;

	$_precision       = 3;
	$latitudeminutes  = round( $latitudedecimal * 60, $_precision );
	$longitudeminutes = round( $longitudedecimal * 60, $_precision );
	if ( ! empty( $altitude ) && is_numeric( $altitude ) ) {
		$altitudedisplay = sprintf( '%1$s%2$s', $altitude, __( 'm', 'simple-location' ) );
	} else {
		$altitudedisplay = '';
	}
	return sprintf(
		'%s%s° %s %s %s%s° %s %s%s',
		$latitudenotation,
		$latitudeindegrees,
		$latitudeminutes,
		$latitudedirection,
		$longitudenotation,
		$longitudeindegrees,
		$longitudeminutes,
		$longitudedirection,
		$altitudedisplay
	);
}

if ( ! function_exists( 'ifset' ) ) {

	/**
	 * Compat for the null coaslescing operator.
	 *
	 * Returns $var if set otherwise $default.
	 *
	 * @param mixed $var A variable.
	 * @param mixed $default Return if $var is not set. Defaults to false.
	 * @return mixed $return The returned value.
	 */
	function ifset( &$var, $default = false ) {
		return isset( $var ) ? $var : $default;
	}
}

if ( ! function_exists( 'ifset_round' ) ) {
	/**
	 * Returns if set and round.
	 *
	 * Returns $var, rounding it if it is a float if set otherwise $default.
	 *
	 * @param mixed $var A variable.
	 * @param mixed $precision Rounds floats to a precision. Defaults to 0.
	 * @param mixed $default Returned if var is not set. Defaults to false.
	 * @return mixed $return The returned value.
	 */
	function ifset_round( &$var, $precision = 0, $default = false ) {
		$return = ifset( $var, $default );
		if ( is_float( $return ) ) {
			return round( $return, $precision );
		}
		return $return;
	}
}


if ( ! function_exists( 'array_key_return' ) ) {

	/**
	 * Returns $key in $array if set otherwise $default.
	 *
	 * @param string|number $key Key.
	 * @param array         $array An array.
	 * @param mixed         $default Return if $var is not set. Defaults to false.
	 * @return mixed $return The returned value.
	 */
	function array_key_return( $key, &$array, $default = false ) {
		if ( ! is_array( $array ) ) {
			return $default;
		}
		return array_key_exists( $key, $array ) ? $array[ $key ] : $default;
	}
}

if ( ! function_exists( 'sanitize_float' ) ) {
	/**
	 * Sanitize Floats.
	 *
	 * @param float $input Float input.
	 * @return $input Sanitized Float Input.
	 *
	 * @since 1.0.0
	 */
	function sanitize_float( $input ) {
		return filter_var( $input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
	}
}

/**
 * Register a provider.
 *
 * @param Sloc_Provider $object Sloc Provider.
 * @return boolean If successful return true.
 *
 * @since 1.0.0
 */
function register_sloc_provider( $object ) {
	return Loc_Config::register_provider( $object );
}


/**
 * Returns an object from an Object ID and object type.
 *
 * @param object $object Object.
 * @param string $object_type Post, comment, user, or term.
 *
 * @since 4.5.0
 */
function sloc_get_object_from_id( $object, $object_type ) {
	if ( ! is_object( $object ) ) {
		return null;
	}
	switch ( $object_type ) {
		case 'post':
			return get_post( $object->ID );
		case 'comment':
			return get_comment( $object->comment_ID );
		case 'user':
			return get_user_by( 'id', $object->ID );
		case 'term':
			return get_term( $object->term_id );
		default:
			return null;
	}
}

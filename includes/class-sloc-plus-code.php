<?php
/**
 * Plus Codes (Open Location Codes).
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Encodes and decodes Plus Codes, the short codes for places that Google Maps shows, such as 87F6XR2P+WH.
 *
 * Implements the Open Location Code specification (https://github.com/google/open-location-code), using its integer
 * arithmetic, so codes match the reference implementations. A full code, such as 87F6XR2P+WH, names an area anywhere. A
 * short code, such as XR2P+WH, leaves off the first digits and is used with a nearby place, such as
 * "XR2P+WH Philadelphia".
 *
 * @since 6.1.0
 */
class Sloc_Plus_Code {

	/**
	 * The digits, in order of value.
	 *
	 * @var string
	 */
	const ALPHABET = '23456789CFGHJMPQRVWX';

	/**
	 * Separates the first eight digits from the rest.
	 *
	 * @var string
	 */
	const SEPARATOR = '+';

	/**
	 * Position of the separator in a full code.
	 *
	 * @var int
	 */
	const SEPARATOR_POSITION = 8;

	/**
	 * Pads a code with fewer than eight digits before the separator.
	 *
	 * @var string
	 */
	const PADDING = '0';

	/**
	 * Number of digits encoded as latitude and longitude pairs.
	 *
	 * @var int
	 */
	const PAIR_CODE_LENGTH = 10;

	/**
	 * Most digits a code has.
	 *
	 * @var int
	 */
	const MAX_DIGIT_COUNT = 15;

	/**
	 * Columns in each grid step after the pairs.
	 *
	 * @var int
	 */
	const GRID_COLUMNS = 4;

	/**
	 * Rows in each grid step after the pairs.
	 *
	 * @var int
	 */
	const GRID_ROWS = 5;

	/**
	 * Steps per degree after the pairs: 20 ^ 3.
	 *
	 * @var int
	 */
	const PAIR_PRECISION = 8000;

	/**
	 * Value of the first pair digit, in pair steps: 20 ^ 4.
	 *
	 * @var int
	 */
	const PAIR_FIRST_PLACE_VALUE = 160000;

	/**
	 * Latitude steps per degree at the most digits: PAIR_PRECISION * 5 ^ 5.
	 *
	 * @var int
	 */
	const FINAL_LAT_PRECISION = 25000000;

	/**
	 * Longitude steps per degree at the most digits: PAIR_PRECISION * 4 ^ 5.
	 *
	 * @var int
	 */
	const FINAL_LNG_PRECISION = 8192000;

	/**
	 * Returns whether a code is a valid full or short code.
	 *
	 * @param mixed $code Code.
	 * @return bool Whether it is valid.
	 */
	public static function is_valid( $code ) {
		// A separator alone isn't a code.
		if ( ! is_string( $code ) || strlen( $code ) < 2 ) {
			return false;
		}
		$code      = strtoupper( $code );
		$separator = strpos( $code, self::SEPARATOR );
		// Exactly one separator, at an even position no later than the eighth.
		if ( false === $separator || strrpos( $code, self::SEPARATOR ) !== $separator || $separator > self::SEPARATOR_POSITION || 1 === $separator % 2 ) {
			return false;
		}
		// After the separator, no digits or at least two.
		if ( 1 === strlen( $code ) - $separator - 1 ) {
			return false;
		}
		$padding = strpos( $code, self::PADDING );
		if ( false !== $padding ) {
			// Padding is only in full codes, never first, an even number of zeros ending at the separator.
			if ( self::SEPARATOR_POSITION !== $separator || 0 === $padding || 1 === $padding % 2 ) {
				return false;
			}
			if ( ! preg_match( '/^[^0]+0+\+$/', $code ) || 1 === ( $separator - $padding ) % 2 ) {
				return false;
			}
		}
		return 1 === preg_match( '/^[' . self::ALPHABET . self::PADDING . ']*\+[' . self::ALPHABET . ']*$/', $code );
	}

	/**
	 * Returns whether a code is a valid short code, which needs a nearby place to find where it is.
	 *
	 * @param mixed $code Code.
	 * @return bool Whether it is a short code.
	 */
	public static function is_short( $code ) {
		return self::is_valid( $code ) && strpos( $code, self::SEPARATOR ) < self::SEPARATOR_POSITION;
	}

	/**
	 * Returns whether a code is a valid full code, which names an area on its own.
	 *
	 * @param mixed $code Code.
	 * @return bool Whether it is a full code.
	 */
	public static function is_full( $code ) {
		if ( ! self::is_valid( $code ) || self::is_short( $code ) ) {
			return false;
		}
		$code = strtoupper( $code );
		// The first digits can't be beyond the poles or the antimeridian.
		if ( strpos( self::ALPHABET, $code[0] ) * 20 >= 180 ) {
			return false;
		}
		return strpos( self::ALPHABET, $code[1] ) * 20 < 360;
	}

	/**
	 * Encodes a location as a full code.
	 *
	 * @param float $latitude    Latitude, clipped to -90 to 90.
	 * @param float $longitude   Longitude, normalized to -180 to 180.
	 * @param int   $code_length Number of digits: 2, 4, 6, 8, or 10 to 15. Default 10, an area of about 14 by 14 meters.
	 * @return string|WP_Error Code, or an error for a length that can't be used.
	 */
	public static function encode( $latitude, $longitude, $code_length = self::PAIR_CODE_LENGTH ) {
		$code_length = min( (int) $code_length, self::MAX_DIGIT_COUNT );
		if ( $code_length < 2 || ( $code_length < self::PAIR_CODE_LENGTH && 1 === $code_length % 2 ) ) {
			return new WP_Error( 'invalid_code_length', __( 'A Plus Code has 2, 4, 6, 8, or 10 to 15 digits.', 'simple-location' ) );
		}

		// To whole steps of the smallest area, rounded first so a value such as 0.1 isn't floored below itself.
		$lat_value = (int) floor( round( ( (float) $latitude + 90 ) * self::FINAL_LAT_PRECISION, 6 ) );
		$lat_value = max( 0, min( $lat_value, 180 * self::FINAL_LAT_PRECISION - 1 ) );
		$lng_range = 360 * self::FINAL_LNG_PRECISION;
		$lng_value = (int) floor( round( ( (float) $longitude + 180 ) * self::FINAL_LNG_PRECISION, 6 ) );
		$lng_value = ( ( $lng_value % $lng_range ) + $lng_range ) % $lng_range;

		$code = '';
		if ( $code_length > self::PAIR_CODE_LENGTH ) {
			for ( $i = 0; $i < self::MAX_DIGIT_COUNT - self::PAIR_CODE_LENGTH; $i++ ) {
				$code      = self::ALPHABET[ ( $lat_value % self::GRID_ROWS ) * self::GRID_COLUMNS + ( $lng_value % self::GRID_COLUMNS ) ] . $code;
				$lat_value = intdiv( $lat_value, self::GRID_ROWS );
				$lng_value = intdiv( $lng_value, self::GRID_COLUMNS );
			}
		} else {
			$lat_value = intdiv( $lat_value, self::FINAL_LAT_PRECISION / self::PAIR_PRECISION );
			$lng_value = intdiv( $lng_value, self::FINAL_LNG_PRECISION / self::PAIR_PRECISION );
		}
		for ( $i = 0; $i < self::PAIR_CODE_LENGTH / 2; $i++ ) {
			$code      = self::ALPHABET[ $lat_value % 20 ] . self::ALPHABET[ $lng_value % 20 ] . $code;
			$lat_value = intdiv( $lat_value, 20 );
			$lng_value = intdiv( $lng_value, 20 );
		}
		$code = substr( $code, 0, self::SEPARATOR_POSITION ) . self::SEPARATOR . substr( $code, self::SEPARATOR_POSITION );
		if ( $code_length >= self::SEPARATOR_POSITION ) {
			return substr( $code, 0, $code_length + 1 );
		}
		return substr( $code, 0, $code_length ) . str_repeat( self::PADDING, self::SEPARATOR_POSITION - $code_length ) . self::SEPARATOR;
	}

	/**
	 * Decodes a full code into the area it names.
	 *
	 * @param string $code Full code.
	 * @return array|WP_Error {
	 *     The area, or an error if the code isn't a full code.
	 *
	 *     @type float $latitude      Latitude of the center.
	 *     @type float $longitude     Longitude of the center.
	 *     @type float $latitude_lo   Southern edge.
	 *     @type float $longitude_lo  Western edge.
	 *     @type float $latitude_hi   Northern edge.
	 *     @type float $longitude_hi  Eastern edge.
	 *     @type int   $code_length   Number of digits.
	 * }
	 */
	public static function decode( $code ) {
		if ( ! self::is_full( $code ) ) {
			return new WP_Error( 'invalid_plus_code', __( 'This is not a full Plus Code.', 'simple-location' ) );
		}
		$digits = substr( str_replace( array( self::SEPARATOR, self::PADDING ), '', strtoupper( $code ) ), 0, self::MAX_DIGIT_COUNT );
		$length = strlen( $digits );

		$lat         = -90 * self::PAIR_PRECISION;
		$lng         = -180 * self::PAIR_PRECISION;
		$place_value = self::PAIR_FIRST_PLACE_VALUE;
		$pairs       = min( $length, self::PAIR_CODE_LENGTH );
		for ( $i = 0; $i < $pairs; $i += 2 ) {
			$lat += strpos( self::ALPHABET, $digits[ $i ] ) * $place_value;
			$lng += strpos( self::ALPHABET, $digits[ $i + 1 ] ) * $place_value;
			if ( $i < $pairs - 2 ) {
				$place_value = intdiv( $place_value, 20 );
			}
		}
		$lat_precision = $place_value / self::PAIR_PRECISION;
		$lng_precision = $place_value / self::PAIR_PRECISION;

		$grid_lat = 0;
		$grid_lng = 0;
		if ( $length > self::PAIR_CODE_LENGTH ) {
			$row_value    = 625; // 5 ^ 4.
			$column_value = 256; // 4 ^ 4.
			for ( $i = self::PAIR_CODE_LENGTH; $i < $length; $i++ ) {
				$digit     = strpos( self::ALPHABET, $digits[ $i ] );
				$grid_lat += intdiv( $digit, self::GRID_COLUMNS ) * $row_value;
				$grid_lng += ( $digit % self::GRID_COLUMNS ) * $column_value;
				if ( $i < $length - 1 ) {
					$row_value    = intdiv( $row_value, self::GRID_ROWS );
					$column_value = intdiv( $column_value, self::GRID_COLUMNS );
				}
			}
			$lat_precision = $row_value / self::FINAL_LAT_PRECISION;
			$lng_precision = $column_value / self::FINAL_LNG_PRECISION;
		}

		$latitude_lo  = $lat / self::PAIR_PRECISION + $grid_lat / self::FINAL_LAT_PRECISION;
		$longitude_lo = $lng / self::PAIR_PRECISION + $grid_lng / self::FINAL_LNG_PRECISION;
		$latitude_hi  = $latitude_lo + $lat_precision;
		$longitude_hi = $longitude_lo + $lng_precision;
		return array(
			'latitude'     => round( min( $latitude_lo + $lat_precision / 2, 90 ), 14 ),
			'longitude'    => round( min( $longitude_lo + $lng_precision / 2, 180 ), 14 ),
			'latitude_lo'  => round( $latitude_lo, 14 ),
			'longitude_lo' => round( $longitude_lo, 14 ),
			'latitude_hi'  => round( $latitude_hi, 14 ),
			'longitude_hi' => round( $longitude_hi, 14 ),
			'code_length'  => $length,
		);
	}

	/**
	 * Recovers the full code of a short code from a nearby location: the nearest place the short code could be.
	 *
	 * @param string $code      Short code, or a full code, which is returned as it is.
	 * @param float  $latitude  Latitude of a nearby location.
	 * @param float  $longitude Longitude of a nearby location.
	 * @return string|WP_Error Full code, or an error if the code isn't valid.
	 */
	public static function recover_nearest( $code, $latitude, $longitude ) {
		if ( self::is_full( $code ) ) {
			return strtoupper( $code );
		}
		if ( ! self::is_short( $code ) ) {
			return new WP_Error( 'invalid_plus_code', __( 'This is not a valid Plus Code.', 'simple-location' ) );
		}
		$latitude  = max( -90, min( 90, (float) $latitude ) );
		$longitude = fmod( fmod( (float) $longitude + 180, 360 ) + 360, 360 ) - 180;

		$code           = strtoupper( $code );
		$padding_length = self::SEPARATOR_POSITION - strpos( $code, self::SEPARATOR );
		// The size of the area the missing digits name.
		$resolution = pow( 20, 2 - ( $padding_length / 2 ) );
		$half       = $resolution / 2;

		$area      = self::decode( substr( self::encode( $latitude, $longitude ), 0, $padding_length ) . $code );
		$center_la = $area['latitude'];
		$center_lo = $area['longitude'];
		// Move to the neighboring area when that is nearer, but not past a pole.
		if ( $latitude + $half < $center_la && $center_la - $resolution >= -90 ) {
			$center_la -= $resolution;
		} elseif ( $latitude - $half > $center_la && $center_la + $resolution <= 90 ) {
			$center_la += $resolution;
		}
		if ( $longitude + $half < $center_lo ) {
			$center_lo -= $resolution;
		} elseif ( $longitude - $half > $center_lo ) {
			$center_lo += $resolution;
		}
		return self::encode( $center_la, $center_lo, $area['code_length'] );
	}

	/**
	 * Finds where a Plus Code in text is.
	 *
	 * A full code is decoded on its own. A short code is recovered from the place written after it, looked up with the
	 * geocoding providers, or else from the location given.
	 *
	 * @param string     $text      Text with a Plus Code, such as "87F6XR2P+WH" or "XR2P+WH Philadelphia, PA".
	 * @param float|null $latitude  Latitude of a nearby location, for a short code without a place. Optional.
	 * @param float|null $longitude Longitude of a nearby location. Optional.
	 * @return array|WP_Error The area, as decode() returns it, with 'plus_code', the full code; or an error.
	 */
	public static function locate( $text, $latitude = null, $longitude = null ) {
		$parsed = self::parse( $text );
		if ( ! $parsed ) {
			return new WP_Error( 'invalid_plus_code', __( 'This is not a valid Plus Code.', 'simple-location' ), array( 'status' => 400 ) );
		}
		$code = $parsed['code'];
		if ( self::is_short( $code ) ) {
			if ( '' !== $parsed['place'] ) {
				$place = Sloc_Provider_Chain::geocode( $parsed['place'] );
				if ( is_wp_error( $place ) ) {
					return $place;
				}
				$latitude  = $place['latitude'] ?? null;
				$longitude = $place['longitude'] ?? null;
			}
			if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
				return new WP_Error(
					'plus_code_needs_place',
					/* translators: %s: Short Plus Code. */
					sprintf( __( '%s is a short Plus Code. Add the town or city after it, such as "XR2P+WH Philadelphia".', 'simple-location' ), $code ),
					array( 'status' => 400 )
				);
			}
			$code = self::recover_nearest( $code, $latitude, $longitude );
		}
		$area = self::decode( $code );
		if ( is_wp_error( $area ) ) {
			return $area;
		}
		$area['plus_code'] = $code;
		return $area;
	}

	/**
	 * Finds a Plus Code in text, with the place written after a short code.
	 *
	 * @param mixed $text Text, such as "87F6XR2P+WH" or "XR2P+WH Philadelphia, PA".
	 * @return array|false Array with 'code', uppercase, and 'place', the rest of the text, or false without a code.
	 */
	public static function parse( $text ) {
		if ( ! is_string( $text ) ) {
			return false;
		}
		$text = trim( $text );
		if ( ! preg_match( '/^([' . self::ALPHABET . self::PADDING . ']{2,8}\+[' . self::ALPHABET . ']*)(?=[\s,]|$)[\s,]*(.*)$/iu', $text, $matches ) ) {
			return false;
		}
		if ( ! self::is_valid( $matches[1] ) ) {
			return false;
		}
		return array(
			'code'  => strtoupper( $matches[1] ),
			'place' => trim( $matches[2] ),
		);
	}
}

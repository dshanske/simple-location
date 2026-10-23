<?php
/**
 * Tracks.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Helpers for tracks: lists of points along a route, stored as Google encoded polylines.
 *
 * @since 6.1.0
 */
class Sloc_Track {

	/**
	 * Most points a track map draws. Longer tracks are simplified to this many, which fits every provider's URL limit.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const MAX_POINTS = 200;

	/**
	 * Decodes an encoded polyline into a track.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $encoded Encoded polyline, in latitude, longitude order, with five decimal places.
	 * @return array List of array( latitude, longitude ) points, skipping invalid ones.
	 */
	public static function decode( $encoded ) {
		if ( ! is_string( $encoded ) ) {
			return array();
		}
		$encoded = trim( $encoded );
		// Encoded polylines only use the characters from ? to ~.
		if ( '' === $encoded || ! preg_match( '/^[\x3F-\x7E]+$/', $encoded ) ) {
			return array();
		}
		$points = array();
		foreach ( array_chunk( Sloc_Polyline::decode( $encoded ), 2 ) as $pair ) {
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			$latitude  = clean_coordinate( (float) $pair[0], 'latitude' );
			$longitude = clean_coordinate( (float) $pair[1], 'longitude' );
			if ( false !== $latitude && false !== $longitude ) {
				$points[] = array( $latitude, $longitude );
			}
		}
		return $points;
	}

	/**
	 * Encodes a track as a polyline.
	 *
	 * @since 6.1.0
	 *
	 * @param array $points List of array( latitude, longitude ) points.
	 * @return string Encoded polyline.
	 */
	public static function encode( $points ) {
		return Sloc_Polyline::encode( array_values( $points ) );
	}

	/**
	 * Simplifies a track to at most a number of points, keeping its shape, with the Douglas-Peucker algorithm.
	 *
	 * The tolerance grows until the track is short enough. The first and last points are always kept.
	 *
	 * @since 6.1.0
	 *
	 * @param array $points List of array( latitude, longitude ) points.
	 * @param int   $max    Optional. Most points to keep. Default MAX_POINTS.
	 * @return array Simplified list of points.
	 */
	public static function simplify( $points, $max = self::MAX_POINTS ) {
		$points = array_values( $points );
		$max    = max( 2, (int) $max );
		if ( count( $points ) <= $max ) {
			return $points;
		}
		// About a meter, doubling until the track fits.
		$tolerance = 0.00001;
		do {
			$simplified = self::douglas_peucker( $points, $tolerance );
			$kept       = count( $simplified );
			$tolerance *= 2;
		} while ( $kept > $max );
		return $simplified;
	}

	/**
	 * Keeps the points that are further than a tolerance from the line between their neighbors that are kept.
	 *
	 * @since 6.1.0
	 *
	 * @param array $points    List of array( latitude, longitude ) points.
	 * @param float $tolerance Distance, in degrees.
	 * @return array Points kept, in order.
	 */
	protected static function douglas_peucker( $points, $tolerance ) {
		$count = count( $points );
		$keep  = array_fill( 0, $count, false );

		$keep[0]            = true;
		$keep[ $count - 1 ] = true;
		// A stack instead of recursion, so long tracks can't exhaust the stack.
		$stack = array( array( 0, $count - 1 ) );
		while ( $stack ) {
			list( $first, $last ) = array_pop( $stack );
			$furthest             = 0;
			$index                = 0;
			for ( $i = $first + 1; $i < $last; $i++ ) {
				$distance = self::distance_to_segment( $points[ $i ], $points[ $first ], $points[ $last ] );
				if ( $distance > $furthest ) {
					$furthest = $distance;
					$index    = $i;
				}
			}
			if ( $furthest > $tolerance ) {
				$keep[ $index ] = true;
				$stack[]        = array( $first, $index );
				$stack[]        = array( $index, $last );
			}
		}
		return array_values( array_intersect_key( $points, array_filter( $keep ) ) );
	}

	/**
	 * Returns the distance from a point to a segment, in degrees, treating latitude and longitude as flat.
	 *
	 * @since 6.1.0
	 *
	 * @param float[] $point Point.
	 * @param float[] $start Start of the segment.
	 * @param float[] $end   End of the segment.
	 * @return float Distance.
	 */
	protected static function distance_to_segment( $point, $start, $end ) {
		$dx     = $end[1] - $start[1];
		$dy     = $end[0] - $start[0];
		$length = $dx * $dx + $dy * $dy;
		$t      = ( 0.0 === (float) $length ) ? 0 : max( 0, min( 1, ( ( $point[1] - $start[1] ) * $dx + ( $point[0] - $start[0] ) * $dy ) / $length ) );
		$x      = $start[1] + $t * $dx;
		$y      = $start[0] + $t * $dy;
		return sqrt( ( $point[1] - $x ) ** 2 + ( $point[0] - $y ) ** 2 );
	}
}

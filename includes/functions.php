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
function sloc_get_object_from_id( $object_type, $id ) {
	switch ( $object_type ) {
		case 'post':
			return get_post( $id );
		case 'comment':
			return get_comment( $id );
		case 'user':
			return get_user_by( 'id', $id );
		case 'term':
			return get_term( $id );
		default:
			return null;
	}
}

/**
 * Returns an object id from an $object
 *
 * @param object $object Object.
 *
 * @since 5.0.0
 */
function sloc_get_id_from_object( $object ) {
	if ( ! is_object( $object ) ) {
		return null;
	}

	if ( $object instanceof WP_Post ) {
		return $object->ID;
	} elseif ( $object instanceof WP_Comment ) {
		return $object->comment_ID;
	} elseif ( $object instanceof WP_User ) {
		return $object->ID;
	} elseif ( $object instanceof WP_Term ) {
		return $object->term_id;
	}
	return null;
}

/**
 * Returns an object type from an $object
 *
 * @param object $object Object.
 *
 * @since 5.0.0
 */
function sloc_get_type_from_object( $object ) {
	if ( ! is_object( $object ) ) {
		return null;
	}

	if ( $object instanceof WP_Post ) {
		return 'post';
	} elseif ( $object instanceof WP_Comment ) {
		return 'comment';
	} elseif ( $object instanceof WP_User ) {
		return 'user';
	} elseif ( $object instanceof WP_Term ) {
		return 'term';
	}
	return null;
}


/**
 * Calculates the distance in meters between two coordinates.
 *
 * Returns the distance between lat/lng1 and lat/lng2.
 *
 * @param float $lat1 Latitude 1.
 * @param float $lng1 Longitude 1.
 * @param float $lat2 Latitude 2.
 * @param float $lng2 Longitude 2.
 * @return float $meters Distance in meters between the two points.
 *
 * @since 1.0.0
 */
function geo_distance( $lat1, $lng1, $lat2, $lng2 ) {
		$lat1 = floatval( $lat1 );
		$lng1 = floatval( $lng1 );
		$lat2 = floatval( $lat2 );
		$lng2 = floatval( $lng2 );
		return ( 6378100 * acos( cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * cos( deg2rad( $lng2 ) - deg2rad( $lng1 ) ) + sin( deg2rad( $lat1 ) ) * sin( deg2rad( $lat2 ) ) ) );
}

/**
 * Returns a bounding box around a location
 *
 * @param float $lat1 Latitude 1.
 * @param float $lng1 Longitude 1.
 * @param float $lat2 Latitude 2.
 * @param float $lng2 Longitude 2.
 * @return float $meters Distance in meters between the two points.
 *
 * @since 1.0.0
 */
function geo_radius_box( $lat, $lng, $radius = 50 ) {
	$lat = floatval( $lat );
	$lng = floatval( $lng );
	$r   = $radius / 6378100;
	return array(
		$lat - rad2deg( $r / cos( deg2rad( $lat ) ) ),
		$lng - rad2deg( $r / cos( deg2rad( $lng ) ) ),
		$lat + rad2deg( $r ),
		$lng + rad2deg( $r ),
	);
}


/**
 * Advises if the two points are within a radius.
 *
 * Returns if the distance is less than meters specified.
 *
 * @param float $lat1 Latitude 1.
 * @param float $lng1 Longitude 1.
 * @param float $lat2 Latitude 2.
 * @param float $lng2 Longitude 2.
 * @param int   $meters Meters.
 * @return boolean $radius Are the two points within $meters of center.
 *
 * @since 1.0.0
 */
function geo_in_radius( $lat1, $lng1, $lat2, $lng2, $meters = 50 ) {
	if ( ! is_numeric( $meters ) ) {
		$meters = 50;
	}
	return ( geo_distance( $lat1, $lng1, $lat2, $lng2 ) <= $meters );
}


/**
 * Calculates the bounding box of a set of coordinates.
 *
 * @param array   $locations An array of lat,lng.
 * @param boolean $flip Whether to put lng first.
 * @return array An array of coordinates, min and max.
 *
 * @since 1.0.0
 */
function geo_bounding_box( $locations, $flip = false ) {
	$lats = array();
	$lngs = array();
	foreach ( $locations as $location ) {
		$lats[] = $location[0];
		$lngs[] = $location[1];
	}
	if ( ! $flip ) {
		return array(
			min( $lats ),
			min( $lngs ),
			max( $lats ),
			max( $lngs ),
		);
	} else {
		return array(
			min( $lngs ),
			min( $lats ),
			max( $lngs ),
			max( $lats ),
		);
	}
}


/**
 * Calculate the area of a triangle.
 *
 * @param array $a First point.
 * @param array $b Middle point.
 * @param array $c Last point.
 *
 * @return float
 */
function area_of_triangle( $a, $b, $c ) {
	list( $ax, $ay ) = $a;
	list( $bx, $by ) = $b;
	list( $cx, $cy ) = $c;
	$area            = $ax * ( $by - $cy );
	$area           += $bx * ( $cy - $ay );
	$area           += $cx * ( $ay - $by );
	return abs( $area / 2 );
}


/**
 * Reduce points with Visvalingam-Whyatt algorithm.
 *
 * @param array $points Points.
 * @param int   $target Desired count of points.
 *
 * @return array Reduced set of points.
 */
function sloc_simplify_vw( $points, $target ) {
	// Refuse to reduce if points are less than target.
	if ( count( $points ) <= $target ) {
		return $points;
	}
	$kill = count( $points ) - $target;
	while ( $kill-- > 0 ) {
		$idx      = 1;
		$min_area = area_of_triangle( $points[0], $points[1], $points[2] );
		foreach ( range( 2, array_key_last_index( $points, -2 ) ) as $segment ) {
			$area = area_of_triangle(
				$points[ $segment - 1 ],
				$points[ $segment ],
				$points[ $segment + 1 ]
			);
			if ( $area < $min_area ) {
				$min_area = $area;
				$idx      = $segment;
			}
		}
		array_splice( $points, $idx, 1 );
	}

	return $points;
}

/**
 * Reduce points with Ramer–Douglas–Peucker algorithm.
 *
 * @param array $points Points.
 * @param int   $tolerance Tolerance.
 *
 * @return array Reduced set of points.
 */
function sloc_simplify_rdp( $points, $tolerance ) {
	// if this is a multilinestring, then we call ourselves one each segment individually, collect the list, and return that list of simplified lists.
	if ( is_array( $points[0][0] ) ) {
		$multi = array();
		foreach ( $points as $subvertices ) {
			$multi[] = sloc_simplify_rdp( $subvertices, $tolerance );
		}
		return $multi;
	}
	$tolerance2 = $tolerance * $tolerance;

	// okay, so this is a single linestring and we simplify it individually.
	return sloc_segment_rdp( $points, $tolerance2 );
}



/**
 * Reduce single linestring with Ramer–Douglas–Peucker algorithm.
 *
 * @param array $segment Single line segment.
 * @param int   $tolerance_squared Tolerance Squared.
 *
 * @return array Reduced set of points.
 */
function sloc_segment_rdp( $segment, $tolerance_squared ) {
	if ( count( $segment ) <= 2 ) {
		return $segment; // segment is too small to simplify, hand it back as-is.
	}

	/*
	 * Find the maximum distance (squared) between this line $segment and each vertex.
	 * distance is solved as described at UCSD page linked above.
	 * cheat: vertical lines (directly north-south) have no slope so we fudge it with a very tiny nudge to one vertex; can't imagine any units where this will matter.
	 */
	$startx = (float) $segment[0][0];
	$starty = (float) $segment[0][1];
	$endx   = (float) $segment[ count( $segment ) - 1 ][0];
	$endy   = (float) $segment[ count( $segment ) - 1 ][1];

	if ( $endx === $startx ) {
		$startx += 0.00001;
	}

	$m = ( $endy - $starty ) / ( $endx - $startx ); // slope, as in y = mx + b.
	$b = $starty - ( $m * $startx );              // y-intercept, as in y = mx + b.

	$max_distance_squared = 0;
	$max_distance_index   = null;
	for ( $i = 1, $l = count( $segment ); $i <= $l - 2; $i++ ) {
		$x1 = $segment[ $i ][0];
		$y1 = $segment[ $i ][1];

		$closestx = ( ( $m * $y1 ) + ( $x1 ) - ( $m * $b ) ) / ( ( $m * $m ) + 1 );
		$closesty = ( $m * $closestx ) + $b;
		$distsqr  = ( $closestx - $x1 ) * ( $closestx - $x1 ) + ( $closesty - $y1 ) * ( $closesty - $y1 );

		if ( $distsqr > $max_distance_squared ) {
			$max_distance_squared = $distsqr;
			$max_distance_index   = $i;
		}
	}

	/*
	 * Cleanup and disposition.
	 * if the max distance is below tolerance, we can bail, giving a straight line between the start vertex and end vertex.
	 * (all points are so close to the straight line).
	 */

	if ( $max_distance_squared <= $tolerance_squared ) {
		return array( $segment[0], $segment[ count( $segment ) - 1 ] );
	}

	/*
	 * But if we got here then a vertex falls outside the tolerance.
	 * split the line segment into two smaller segments at that "maximum error vertex" and simplify those.
	 */
	$slice1 = array_slice( $segment, 0, $max_distance_index );
	$slice2 = array_slice( $segment, $max_distance_index );
	$segs1  = sloc_segment_rdp( $slice1, $tolerance_squared );
	$segs2  = sloc_segment_rdp( $slice2, $tolerance_squared );
	return array_merge( $segs1, $segs2 );
}


if ( ! function_exists( 'clean_coordinate' ) ) {
	/**
	 * Sanitize and round coordinates.
	 *
	 * @param string $coordinate Coordinate.
	 * @return float|false $coordinate Sanitized, rounded and converted coordinate. False if not valid coordinate
	 *
	 * @since 1.0.0
	 */
	function clean_coordinate( $coordinate ) {
		$coordinate = trim( $coordinate );
		$pattern    = '/^(\-)?(\d{1,3})\.(\d{1,15})/';
		preg_match( $pattern, $coordinate, $matches );
		if ( wp_is_numeric_array( $matches ) ) {
			return round( (float) $matches[0], 7 );
		}
		return false;
	}
}


/**
 * Get a list of post IDs in the current query.
 *
 * This gets a list of all the IDs in the current query.
 *
 * @return $post_ids array of post ids.
 *
 * @since 1.0.0
 */
function sloc_query_id_list() {
	global $wp_query;
	$post_ids = wp_list_pluck( $wp_query->posts, 'ID' );
	return $post_ids;
}


/*
Wrapper around get_post_datetime that adjusts if timezone property is available
*/
function sloc_get_post_datetime( $post = null, $field = 'date', $source = 'local' ) {
	$datetime = get_post_datetime( $post, $field, $source );
	$timezone = get_post_geodata( $post, 'timezone' );
	if ( ! $timezone ) {
		return $datetime;
	}

	return $datetime->setTimezone( new DateTimeZone( $timezone ) );
}


/*
Wrapper around get_comment_datetime that adjusts if timezone property is available
*/
function sloc_get_comment_datetime( $comment = null ) {
	$datetime = get_comment_datetime( $comment );
	$timezone = get_post_geodata( $comment, 'timezone' );
	if ( ! $timezone ) {
		return $datetime;
	}

	return $datetime->setTimezone( new DateTimeZone( $timezone ) );
}

function get_object_permalink( $type, $id ) {
	switch ( $type ) {
		case 'post':
			return get_permalink( $id );
		case 'comment':
			return get_comment_link( $id );
		case 'user':
			return get_author_posts_url( $id );
		case 'term':
			return get_term_link( $id );
		default:
			return false;
	}
}

/**
 * Retrieve post published or modified time as a `DateTimeImmutable` object instance.
 *
 * @param int|WP_Post $attachment  WP_Post object or ID. Default is global `$post` object.
 * @param string      $field Optional. Accepts 'created', 'date' or 'modified'. Created is based on media metadata whereas the rest are based on post fields.
 * @return DateTimeImmutable|false Time object on success, false on failure.
 */
function sloc_get_attachment_datetime( $attachment, $field = 'created' ) {
		$attachment = get_post( $attachment );
	if ( ! $attachment ) {
		return false;
	}
	if ( 'attachment' !== $attachment->post_type ) {
		return false;
	}

	if ( ! in_array( $field, array( 'created', 'date', 'modified' ) ) ) {
		$field = 'created';
	}

	if ( 'created' === $field ) {
		$data = get_post_meta( $attachment->ID, '_wp_attachment_metadata', true );
		if ( ! $data ) {
			return false;
		}
		$data    = $data['image_meta'];
		$created = 0;

		if ( array_key_exists( 'created', $data ) ) {
			$created = $data['created'];
		} elseif ( array_key_exists( 'created_timestamp', $data ) ) {
			$created = $data['created_timestamp'];
		}

		if ( is_numeric( $created ) ) {
			if ( 0 === $created ) {
				return false;
			} else {
				$datetime = new DateTime();
				$datetime->setTimestamp( intval( $created ) );
				$datetime->setTimezone( wp_timezone() );
				return DateTimeImmutable::createFromMutable( $datetime );
			}
		} elseif ( is_string( $created ) ) {
			return new DateTimeImmutable( $created );
		} else {
			return false;
		}
	}

		$time = ( 'modified' === $field ) ? $attachment->post_modified : $attachment->post_date;
	if ( empty( $time ) || '0000-00-00 00:00:00' === $time ) {
		return false;
	}
		return date_create_immutable_from_format( 'Y-m-d H:i:s', $time, wp_timezone() );
}

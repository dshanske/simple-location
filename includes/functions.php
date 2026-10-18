<?php
/**
 * Global Functions.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

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
	function wp_exif_gps_convert( $coordinate ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.
		if ( is_array( $coordinate ) ) {
			// Missing minutes or seconds count as zero.
			list( $degree, $minute, $second ) = array_pad( array_values( $coordinate ), 3, 0 );
			$float                            = wp_exif_frac2dec( $degree ) + ( wp_exif_frac2dec( $minute ) / 60 ) + ( wp_exif_frac2dec( $second ) / 3600 );

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
	function wp_exif_datetime( $str, $timezone = null ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.
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
 * Degrees and decimal minutes with a direction letter, e.g. 33° 30′ S 70° 30′ W.
 *
 * @since 6.0.0 Uses the direction letter without a minus sign, marks the minutes, and separates the altitude.
 *
 * @param float        $latitude Latitude.
 * @param float        $longitude Longitude.
 * @param float|string $altitude Altitude. Optional.
 * @return string Textual Representation of Location.
 */
function dec_to_dms( $latitude, $longitude, $altitude = '' ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.
	$latitudedirection  = $latitude < 0 ? 'S' : 'N';
	$longitudedirection = $longitude < 0 ? 'W' : 'E';

	$latitudeindegrees  = floor( abs( $latitude ) );
	$longitudeindegrees = floor( abs( $longitude ) );

	$latitudedecimal  = abs( $latitude ) - $latitudeindegrees;
	$longitudedecimal = abs( $longitude ) - $longitudeindegrees;

	$_precision       = 3;
	$latitudeminutes  = round( $latitudedecimal * 60, $_precision );
	$longitudeminutes = round( $longitudedecimal * 60, $_precision );
	if ( ! empty( $altitude ) && is_numeric( $altitude ) ) {
		$altitudedisplay = sprintf( ' %1$s%2$s', $altitude, __( 'm', 'simple-location' ) );
	} else {
		$altitudedisplay = '';
	}
	return sprintf(
		'%s° %s′ %s %s° %s′ %s%s',
		$latitudeindegrees,
		$latitudeminutes,
		$latitudedirection,
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
	 * Because $var is passed by reference, reading a missing array key with this
	 * function adds that key to the array with a null value.
	 *
	 * @deprecated 6.0.0 Use the null coalescing operator, `$var ?? $default`.
	 *
	 * @param mixed $var A variable.
	 * @param mixed $default Return if $var is not set. Defaults to false.
	 * @return mixed $return The returned value.
	 */
	function ifset( &$var, $default = false ) {
		_deprecated_function( __FUNCTION__, '6.0.0', 'the ?? operator' );
		return isset( $var ) ? $var : $default;
	}
}

if ( ! function_exists( 'ifset_round' ) ) {
	/**
	 * Returns if set and round.
	 *
	 * Returns $var, rounding it if it is a float if set otherwise $default.
	 *
	 * Because $var is passed by reference, reading a missing array key with this
	 * function adds that key to the array with a null value.
	 *
	 * @deprecated 6.0.0 Use sloc_round_value() with the null coalescing operator,
	 *                   `sloc_round_value( $var ?? $default, $precision )`.
	 *
	 * @param mixed $var A variable.
	 * @param mixed $precision Rounds floats to a precision. Defaults to 0.
	 * @param mixed $default Returned if var is not set. Defaults to false.
	 * @return mixed $return The returned value.
	 */
	function ifset_round( &$var, $precision = 0, $default = false ) {
		_deprecated_function( __FUNCTION__, '6.0.0', 'sloc_round_value()' );
		$return = isset( $var ) ? $var : $default;
		if ( is_float( $return ) ) {
			return round( $return, $precision );
		}
		return $return;
	}
}

/**
 * Rounds a value if it is a number.
 *
 * Use with the null coalescing operator in place of ifset_round(), for example
 * `sloc_round_value( $data['speed'] ?? '', 2 )`.
 *
 * @since 6.0.0
 *
 * @param mixed $value     Value.
 * @param int   $precision Optional. Number of decimal places. Default 0.
 * @return mixed The rounded value as a float, or the value unchanged if it isn't a number.
 */
function sloc_round_value( $value, $precision = 0 ) {
	return is_numeric( $value ) ? round( (float) $value, $precision ) : $value;
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
	function array_key_return( $key, &$array, $default = false ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.
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
	function sanitize_float( $input ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.
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
function register_sloc_provider( $object ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.
	return Loc_Config::register_provider( $object );
}


/**
 * Returns an object from an Object ID and object type.
 *
 * @param string $object_type Post, comment, user, or term.
 * @param int    $id          Object ID.
 * @return WP_Post|WP_Comment|WP_User|WP_Term|WP_Error|false|null The object, or null for an unknown type.
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
function geo_distance( $lat1, $lng1, $lat2, $lng2 ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.
		$lat1 = floatval( $lat1 );
		$lng1 = floatval( $lng1 );
		$lat2 = floatval( $lat2 );
		$lng2 = floatval( $lng2 );
		return ( 6378100 * acos( cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * cos( deg2rad( $lng2 ) - deg2rad( $lng1 ) ) + sin( deg2rad( $lat1 ) ) * sin( deg2rad( $lat2 ) ) ) );
}

/**
 * Returns a bounding box around a location
 *
 * @since 1.0.0
 * @since 6.0.0 The box is centered on the location, with the longitude span widened by the latitude.
 *
 * @param float $lat    Latitude.
 * @param float $lng    Longitude.
 * @param float $radius Optional. Distance from the location to each side, in meters. Default 50.
 * @return float[] Minimum latitude, minimum longitude, maximum latitude, maximum longitude.
 */
function geo_radius_box( $lat, $lng, $radius = 50 ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.
	$lat  = floatval( $lat );
	$lng  = floatval( $lng );
	$r    = $radius / 6378100;
	$dlat = rad2deg( $r );
	// A degree of longitude shrinks towards the poles; cap the span there rather than dividing by zero.
	$dlng = rad2deg( $r / max( cos( deg2rad( $lat ) ), 0.000001 ) );
	return array(
		$lat - $dlat,
		$lng - $dlng,
		$lat + $dlat,
		$lng + $dlng,
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
function geo_in_radius( $lat1, $lng1, $lat2, $lng2, $meters = 50 ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.
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
function geo_bounding_box( $locations, $flip = false ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.
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
function area_of_triangle( $a, $b, $c ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.
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
	 * Sanitize, validate and round coordinates.
	 *
	 * Whole numbers are accepted. Values outside the valid range are rejected: ±90 for a latitude,
	 * and ±180 for a longitude or when the type is not given.
	 *
	 * @param string|int|float $coordinate Coordinate.
	 * @param string           $type       Optional. 'latitude' (or 'lat', 'geo_latitude') or 'longitude'
	 *                                     (or 'lng', 'lon', 'geo_longitude'). Meta keys are accepted
	 *                                     because this is also a meta sanitize callback. Default ''.
	 * @return float|false $coordinate Sanitized, rounded and converted coordinate. False if not valid coordinate
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Accepts whole numbers, checks the range, and added the `$type` parameter.
	 */
	function clean_coordinate( $coordinate, $type = '' ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.
		if ( is_int( $coordinate ) || is_float( $coordinate ) ) {
			$value = (float) $coordinate;
		} elseif ( is_string( $coordinate ) ) {
			// Leading digits only, so trailing text such as a direction letter is ignored.
			if ( ! preg_match( '/^[+-]?\d{1,3}(\.\d+)?(?![\d.])/', trim( $coordinate ), $matches ) ) {
				return false;
			}
			$value = (float) $matches[0];
		} else {
			return false;
		}

		$max = in_array( $type, array( 'latitude', 'lat', 'geo_latitude' ), true ) ? 90 : 180;
		if ( ! is_finite( $value ) || abs( $value ) > $max ) {
			return false;
		}
		return round( $value, 7 );
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


/**
 * Retrieves a post's date in the timezone stored with its location, if there is one.
 *
 * Wrapper around get_post_datetime().
 *
 * @since 5.0.0
 * @since 6.0.0 Ignores a stored timezone that isn't valid, instead of throwing.
 *
 * @param int|WP_Post|null $post   Post ID or object. Defaults to the current post.
 * @param string           $field  Published or modified time: 'date' or 'modified'. Default 'date'.
 * @param string           $source Local or UTC time: 'local' or 'gmt'. Default 'local'.
 * @return DateTimeImmutable|false Time object on success, false on failure.
 */
function sloc_get_post_datetime( $post = null, $field = 'date', $source = 'local' ) {
	$datetime = get_post_datetime( $post, $field, $source );
	if ( ! $datetime ) {
		return $datetime;
	}
	$timezone = Loc_Timezone::timezone_from_string( get_post_geodata( $post, 'timezone' ) );
	if ( ! $timezone ) {
		return $datetime;
	}

	return $datetime->setTimezone( $timezone );
}


/**
 * Retrieves a comment's date in the timezone of the post it is on, if the post has one.
 *
 * Comments are shown in their post's timezone, so a thread reads in one timezone.
 * Wrapper around get_comment_datetime().
 *
 * @since 5.0.0
 * @since 6.0.0 Uses the timezone of the comment's post; it looked up an unrelated post, or none. A timezone
 *              that isn't valid is ignored.
 *
 * @param int|WP_Comment|null $comment Comment ID or object. Defaults to the current comment.
 * @return DateTimeImmutable|false Time object on success, false on failure.
 */
function sloc_get_comment_datetime( $comment = null ) {
	$comment  = get_comment( $comment );
	$datetime = get_comment_datetime( $comment );
	if ( ! $datetime ) {
		return $datetime;
	}
	$timezone = Loc_Timezone::timezone_from_string( get_post_geodata( $comment->comment_post_ID, 'timezone' ) );
	if ( ! $timezone ) {
		return $datetime;
	}

	return $datetime->setTimezone( $timezone );
}

/**
 * Returns the URL of a post, comment, user (their posts) or term.
 *
 * @since 5.0.3
 *
 * @param string $type Object type.
 * @param int    $id   Object ID.
 * @return string|WP_Error|false The URL; false for an unknown type.
 */
function get_object_permalink( $type, $id ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.
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

	if ( ! in_array( $field, array( 'created', 'date', 'modified' ), true ) ) {
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

/**
 * Whether a local file exists, using the direct filesystem class.
 *
 * @since 5.0.24
 *
 * @param string $file Path to the file.
 * @return boolean Whether the file exists.
 */
function sloc_file_exists( $file ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$wpfile = new WP_Filesystem_Direct( false );
		return $wpfile->exists( $file );
}

/**
 * Reads a local file, using the direct filesystem class.
 *
 * @since 5.0.24
 *
 * @param string $file Path to the file.
 * @return string|false The file's contents, or false on failure.
 */
function sloc_get_contents( $file ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$wpfile = new WP_Filesystem_Direct( false );
		return $wpfile->get_contents( $file );
}

/**
 * Reads a local file into an array of lines, using the direct filesystem class.
 *
 * @since 5.0.24
 *
 * @param string $file Path to the file.
 * @return array|false The file's lines, or false on failure.
 */
function sloc_get_contents_array( $file ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$wpfile = new WP_Filesystem_Direct( false );
		return $wpfile->get_contents_array( $file );
}

/**
 * Returns a user.
 *
 * @since 5.0.24
 *
 * @param mixed $user_id User ID. Anything that isn't a number gives the current user.
 * @return WP_User|false The user, or false if there is none.
 */
function sloc_get_user( $user_id = null ) {
	if ( ! is_numeric( $user_id ) ) {
		$user_id = get_current_user_id();
	}
	return get_user_by( 'id', $user_id );
}

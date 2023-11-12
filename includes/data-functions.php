<?php
/**
 * Global Data Functions.
 *
 * Global Functions to Get/Set Location and Weather Data
 *
 * @package Simple_Location
 */

function set_post_geodata( $post, $key, $geodata ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}
	return Geo_Data::set_geodata( 'post', $post->ID, $key, $geodata );
}

function set_comment_geodata( $comment, $key, $geodata ) {
	$comment = get_comment( $comment );
	if ( ! $comment ) {
		return false;
	}
	return Geo_Data::set_geodata( 'comment', $comment->comment_ID, $key, $geodata );
}

function set_user_geodata( $id, $key, $geodata ) {
	return Geo_Data::set_geodata( 'user', $id, $key, $geodata );
}

function set_term_geodata( $id, $key, $geodata ) {
	return Geo_Data::set_geodata( 'term', $id, $key, $geodata );
}


/*
 * Wrapper around has_geodata for post IDs or objects
 *
 */
function has_post_location( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return array();
	}
	return Geo_Data::has_location( 'post', $post->ID );
}

/*
 * Wrapper around get_geodata for post IDs or objects
 *
 */
function get_post_geodata( $post = null, $key = '' ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return array();
	}
	return Geo_Data::get_geodata( 'post', $post->ID, $key );
}

function get_array_post_geodata( $posts ) {
	if ( ! wp_is_numeric_array( $posts ) ) {
		return array();
	}
	$return = array();
	foreach ( $posts as $post_id ) {
		$return[ $post_id ] = get_post_geodata( $post_id );
	}
	return $return;
}

/*
 * Returns whether the post occurred in the daytime or not and stores value.
 *
 */
function is_day_post( $post = null ) {
	$post = get_post( $post );
	$day  = get_post_geodata( $post, 'day' );
	if ( ! empty( $day ) ) {
		return $day;
	}
	$latitude  = get_post_geodata( $post, 'latitude' );
	$longitude = get_post_geodata( $post, 'longitude' );
	$altitude  = get_post_geodata( $post, 'altitude' );
	if ( ! $latitude || ! $longitude ) {
		return null;
	}
	$calc = new Astronomical_Calculator( $latitude, $longitude, $altitude );
	$day  = $calc->is_daytime( get_post_timestamp( $post ) );
	set_post_geodata( $post, 'day', $day ? 1 : 0 );
	return $day;
}

/*
 * Wrapper around get_geodata for comment IDs or objects
 *
 */
function get_comment_geodata( $comment = null, $key = '' ) {
	$comment = get_comment( $comment );
	return Geo_Data::get_geodata( 'comment', $comment->comment_ID, $key );
}


/*
 * Returns whether the comment occurred in the daytime or not and stores value.
 *
 */
function is_day_comment( $comment = null ) {
	$comment = get_comment( $comment );
	$day     = get_comment_geodata( $comment, 'day' );
	if ( ! empty( $day ) ) {
		return $day;
	}
	$latitude  = get_comment_geodata( $post, 'latitude' );
	$longitude = get_comment_geodata( $post, 'longitude' );
	$altitude  = get_comment_geodata( $post, 'altitude' );
	$calc      = new Astronomical_Calculator( $latitude, $longitude, $altitude );
	$day       = $calc->is_daytime( get_comment_timestamp( $comment ) );
	set_comment_geodata( $comment, 'day', $day );
	return $day;
}

/*
 * Wrapper around get_geodata for user IDs or objects
 *
 */
function get_user_geodata( $user_id, $key = '' ) {
	return Geo_Data::get_geodata( 'user', $user_id, $key );
}

/*
 * Wrapper around get_geodata for term IDs or objects
 *
 */
function get_term_geodata( $term_id, $key = '' ) {
	return Geo_Data::get_geodata( 'term', $term_id, $key );
}

function set_post_weatherdata( $post_id, $key, $weather ) {
	return Sloc_Weather_Data::set_object_weatherdata( 'post', $post_id, $key, $weather );
}

function set_comment_weatherdata( $comment_id, $key, $weather ) {
	return Sloc_Weather_Data::set_object_weatherdata( 'comment', $comment_id, $key, $weather );
}

function set_user_weatherdata( $user_id, $weather ) {
	return Sloc_Weather_Data::set_object_weatherdata( 'user', $user_id, $key, $weather );
}

function set_term_weatherdata( $term_id, $weather ) {
	return Sloc_Weather_Data::set_object_weatherdata( 'term', $term_id, $key, $weather );
}

function get_post_weatherdata( $post_id = null, $key = '' ) {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	return Sloc_Weather_Data::get_object_weatherdata( 'post', $post_id, $key );
}

function get_comment_weatherdata( $comment_id, $key = '' ) {
	return Sloc_Weather_Data::get_object_weatherdata( 'comment', $comment_id, $key );
}

function get_term_weatherdata( $term_id, $key = '' ) {
	return Sloc_Weather_Data::get_object_weatherdata( 'term', $term_id, $key );
}

function get_user_weatherdata( $user_id, $key = '' ) {
	return Sloc_Weather_Data::get_object_weatherdata( 'user', $user_id, $key );
}

function get_post_map( $post_id = null, $args = array() ) {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	return Geo_Data::get_map( 'post', $post_id, $args );
}

function get_user_map( $user_id, $args = array() ) {
	return Geo_Data::get_map( 'user', $user_id, $args );
}

function get_comment_map( $comment_id, $args = array() ) {
	return Geo_Data::get_map( 'comment', $comment_id, $args );
}

function get_term_map( $term_id, $args = array() ) {
	return Geo_Data::get_map( 'term', $term_id, $args );
}

/*
 * Legacy Function to be replaced.
 *
 */
function get_simple_location( $object = null, $args = array() ) {
	return Geo_Data::get_location( sloc_get_type_from_object( $object ), sloc_get_id_from_object( $object ), $args );
}

function get_post_location( $id = null, $args = array() ) {
	if ( ! $id ) {
		$id = get_the_ID();
	}
	return Geo_Data::get_location( 'post', $id, $args );
}

function get_comment_location( $id, $args = array() ) {
	return Geo_Data::get_location( 'comment', $id, $args );
}

function get_user_location( $id, $args = array() ) {
	return Geo_Data::get_location( 'user', $id, $args );
}

function get_term_location( $id, $args = array() ) {
	return Geo_Data::get_location( 'term', $id, $args );
}


/**
 * Get a list of all the posts with a public location
 *
 * In order to generate an archive map.
 *
 * @since 1.0.0
 */
function get_geo_archive_location_list() {
	global $wp_query;
	$locations = array();
	if ( empty( $wp_query->posts ) ) {
		return '';
	}
	foreach ( $wp_query->posts as $post ) {
		$location = get_post_geodata( $post->ID, false );
		if ( 'public' === $location['visibility'] && array_key_exists( 'latitude', $location ) ) {
			$locations[] = array_values(
				wp_array_slice_assoc(
					$location,
					array(
						'latitude',
						'longitude',
					)
				)
			);
		}
	}
	return $locations;
}

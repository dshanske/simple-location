<?php
/**
 * Global Data Functions.
 *
 * Global Functions to Get/Set Location and Weather Data
 *
 * @package Simple_Location
 */

function get_post_geo_visibility( $id = null ) {
	return Geo_Data::get_visibility( 'post', $id );
}

function get_comment_geo_visibility( $id = null ) {
	return Geo_Data::get_visibility( 'comment', $id );
}

function get_user_geo_visibility( $id = null ) {
	return Geo_Data::get_visibility( 'user', $id );
}

function get_term_geo_visibility( $id = null ) {
	return Geo_Data::get_visibility( 'term', $id );
}

function set_post_geo_visibility( $id, $status ) {
	return Geo_Data::set_visibility( 'post', $id, $status );
}

function set_comment_geo_visibility( $id, $status ) {
	return Geo_Data::set_visibility( 'comment', $id, $status );
}

function set_user_geo_visibility( $id, $status ) {
	return Geo_Data::set_visibility( 'user', $id, $status );
}

function set_term_geo_visibility( $id, $status ) {
	return Geo_Data::set_visibility( 'term', $id, $status );
}


function set_post_geodata( $id, $geodata ) {
	return Geo_Data::set_geodata( 'post', $id, $geodata );
}

function set_comment_geodata( $id, $geodata ) {
	return Geo_Data::set_geodata( 'comment', $id, $geodata );
}

function set_user_geodata( $id, $geodata ) {
	return Geo_Data::set_geodata( 'user', $id, $geodata );
}

function set_term_geodata( $id, $geodata ) {
	return Geo_Data::set_geodata( 'term', $id, $geodata );
}

/*
 * Wrapper around get_geodata for post IDs or objects
 *
 */
function get_post_geodata( $post_id = null, $full = true ) {
	if ( $post_id instanceof WP_Post ) {
		$post_id = $post_id->ID;
	} elseif ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	return Geo_Data::get_geodata( 'post', $post_id, $full );
}

/*
 * Wrapper around get_geodata for comment IDs or objects
 *
 */
function get_comment_geodata( $comment_id, $full = true ) {
	return Geo_Data::get_geodata( 'comment', $comment_id, $full );
}

/*
 * Wrapper around get_geodata for user IDs or objects
 *
 */
function get_user_geodata( $user_id, $full = true ) {
	return Geo_Data::get_geodata( 'user', $user_id, $full );
}

/*
 * Wrapper around get_geodata for term IDs or objects
 *
 */
function get_term_geodata( $term_id, $full = true ) {
	return Geo_Data::get_geodata( 'term', $term_id, $full );
}

function set_post_weather_data( $post_id, $weather ) {
	return Sloc_Weather_Data::set_object_weather_data( 'post', $post_id, $weather );
}

function set_comment_weather_data( $comment_id, $weather ) {
	return Sloc_Weather_Data::set_object_weather_data( 'comment', $comment_id, $weather );
}

function set_user_weather_data( $user_id, $weather ) {
	return Sloc_Weather_Data::set_object_weather_data( 'user', $user_id, $weather );
}

function set_term_weather_data( $term_id, $weather ) {
	return Sloc_Weather_Data::set_object_weather_data( 'term', $term_id, $weather );
}

function get_post_weather_data( $post_id ) {
	return Sloc_Weather_Data::get_object_weather_data( 'post', $post_id );
}

function get_comment_weather_data( $comment_id ) {
	return Sloc_Weather_Data::get_object_weather_data( 'comment', $comment_id );
}

function get_term_weather_data( $term_id ) {
	return Sloc_Weather_Data::get_object_weather_data( 'term', $term_id );
}

function get_user_weather_data( $user_id ) {
	return Sloc_Weather_Data::get_object_weather_data( 'user', $user_id );
}

function get_post_map( $post_id, $args = array() ) {
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

function get_simple_location( $object = null, $args = array() ) {
	Loc_View::get_location( $object, $args );
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


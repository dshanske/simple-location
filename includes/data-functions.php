<?php
/**
 * Global Data Functions.
 *
 * Global Functions to Get/Set Location and Weather Data
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- These functions are public API that predates the prefix rule; renaming them would break sites that use them. To be revisited with namespacing.

/**
 * Sets geo data on a post.
 *
 * @since 5.0.0
 *
 * @param int|WP_Post $post    Post ID or object.
 * @param string      $key     Geo data key to set, or empty to set every property in `$geodata`.
 * @param mixed       $geodata The value for `$key`, or an array of geo data when `$key` is empty.
 * @return WP_Error|boolean Return success or WP_Error.
 */
function set_post_geodata( $post, $key, $geodata ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}
	return Geo_Data::set_geodata( 'post', $post->ID, $key, $geodata );
}

/**
 * Sets geo data on a comment.
 *
 * @since 5.0.0
 *
 * @param int|WP_Comment $comment Comment ID or object.
 * @param string         $key     Geo data key to set, or empty to set every property in `$geodata`.
 * @param mixed          $geodata The value for `$key`, or an array of geo data when `$key` is empty.
 * @return WP_Error|boolean Return success or WP_Error.
 */
function set_comment_geodata( $comment, $key, $geodata ) {
	$comment = get_comment( $comment );
	if ( ! $comment ) {
		return false;
	}
	return Geo_Data::set_geodata( 'comment', $comment->comment_ID, $key, $geodata );
}

/**
 * Sets geo data on a user.
 *
 * @since 5.0.0
 *
 * @param int|WP_User $id      User ID or object.
 * @param string      $key     Geo data key to set, or empty to set every property in `$geodata`.
 * @param mixed       $geodata The value for `$key`, or an array of geo data when `$key` is empty.
 * @return WP_Error|boolean Return success or WP_Error.
 */
function set_user_geodata( $id, $key, $geodata ) {
	$user = sloc_get_user( $id );
	if ( ! $user ) {
		return false;
	}
	return Geo_Data::set_geodata( 'user', $user->ID, $key, $geodata );
}

/**
 * Sets geo data on a term.
 *
 * @since 5.0.0
 *
 * @param int    $id      Term ID.
 * @param string $key     Geo data key to set, or empty to set every property in `$geodata`.
 * @param mixed  $geodata The value for `$key`, or an array of geo data when `$key` is empty.
 * @return WP_Error|boolean Return success or WP_Error.
 */
function set_term_geodata( $id, $key, $geodata ) {
	return Geo_Data::set_geodata( 'term', $id, $key, $geodata );
}


/**
 * Whether a post has coordinates.
 *
 * @since 5.0.5
 *
 * @param int|WP_Post|null $post Post ID or object. Defaults to the current post.
 * @return boolean|array True if the post has coordinates; an empty array if there is no post.
 */
function has_post_location( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return array();
	}
	return Geo_Data::has_location( 'post', $post->ID );
}

/**
 * Gets geo data for a post.
 *
 * @since 5.0.0
 *
 * @param int|WP_Post|null $post Post ID or object. Defaults to the current post.
 * @param string           $key  Geo data key, or empty for all geo data.
 * @return mixed The value for `$key`, or an array of geo data; an empty array if there is no post.
 */
function get_post_geodata( $post = null, $key = '' ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return array();
	}
	return Geo_Data::get_geodata( 'post', $post->ID, $key );
}

/**
 * Gets geo data for several posts.
 *
 * @since 5.0.0
 *
 * @param int[] $posts Post IDs.
 * @return array Geo data keyed by post ID.
 */
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

/**
 * Whether a post was made in the daytime, calculating and storing the answer if it is not stored.
 *
 * @since 5.0.0
 *
 * @param int|WP_Post|null $post Post ID or object. Defaults to the current post.
 * @return boolean|null Whether it was daytime; null without coordinates, false without a post.
 */
function is_day_post( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}
	$day = get_post_geodata( $post, 'day' );
	if ( ! empty( $day ) ) {
		return $day;
	}
	$latitude  = get_post_geodata( $post, 'latitude' );
	$longitude = get_post_geodata( $post, 'longitude' );
	$altitude  = get_post_geodata( $post, 'altitude' );
	if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
		return null;
	}
	$calc = new Astronomical_Calculator( $latitude, $longitude, $altitude );
	$day  = $calc->is_daytime( get_post_timestamp( $post ) );
	set_post_geodata( $post, 'day', $day ? 1 : 0 );
	return $day;
}

/**
 * Gets geo data for a comment.
 *
 * @since 5.0.0
 *
 * @param int|WP_Comment|null $comment Comment ID or object. Defaults to the current comment.
 * @param string              $key     Geo data key, or empty for all geo data.
 * @return mixed The value for `$key`, or an array of geo data.
 */
function get_comment_geodata( $comment = null, $key = '' ) {
	$comment = get_comment( $comment );
	return Geo_Data::get_geodata( 'comment', $comment->comment_ID, $key );
}


/**
 * Whether a comment was made in the daytime, calculating and storing the answer if it is not stored.
 *
 * @since 5.0.0
 *
 * @param int|WP_Comment|null $comment Comment ID or object. Defaults to the current comment.
 * @return boolean Whether it was daytime.
 */
function is_day_comment( $comment = null ) {
	$comment = get_comment( $comment );
	$day     = get_comment_geodata( $comment, 'day' );
	if ( ! empty( $day ) ) {
		return $day;
	}
	$latitude  = get_comment_geodata( $comment, 'latitude' );
	$longitude = get_comment_geodata( $comment, 'longitude' );
	$altitude  = get_comment_geodata( $comment, 'altitude' );
	$calc      = new Astronomical_Calculator( $latitude, $longitude, $altitude );
	$day       = $calc->is_daytime( get_comment_timestamp( $comment ) );
	set_comment_geodata( $comment, 'day', $day );
	return $day;
}

/**
 * Gets geo data for a user.
 *
 * @since 5.0.0
 *
 * @param int|WP_User|null $user User ID or object. Defaults to the current user.
 * @param string           $key  Geo data key, or empty for all geo data.
 * @return mixed The value for `$key`, or an array of geo data; false if there is no user.
 */
function get_user_geodata( $user = null, $key = '' ) {
	$user = sloc_get_user( $user );
	if ( ! $user ) {
		return false;
	}
	return Geo_Data::get_geodata( 'user', $user->ID, $key );
}

/**
 * Gets geo data for a term.
 *
 * @since 5.0.0
 *
 * @param int    $term_id Term ID.
 * @param string $key     Geo data key, or empty for all geo data.
 * @return mixed The value for `$key`, or an array of geo data.
 */
function get_term_geodata( $term_id, $key = '' ) {
	return Geo_Data::get_geodata( 'term', $term_id, $key );
}

/**
 * Sets weather data on a post.
 *
 * @since 5.0.0
 *
 * @param int|WP_Post $post    Post ID or object.
 * @param string      $key     Weather data key to set, or empty to set every property in `$weather`.
 * @param mixed       $weather The value for `$key`, or an array of weather data when `$key` is empty.
 * @return WP_Error|boolean Return success or WP_Error.
 */
function set_post_weatherdata( $post, $key, $weather ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}
	return Sloc_Weather_Data::set_object_weatherdata( 'post', $post->ID, $key, $weather );
}

/**
 * Sets weather data on a comment.
 *
 * @since 5.0.0
 *
 * @param int    $comment_id Comment ID.
 * @param string $key        Weather data key to set, or empty to set every property in `$weather`.
 * @param mixed  $weather    The value for `$key`, or an array of weather data when `$key` is empty.
 * @return WP_Error|boolean Return success or WP_Error.
 */
function set_comment_weatherdata( $comment_id, $key, $weather ) {
	return Sloc_Weather_Data::set_object_weatherdata( 'comment', $comment_id, $key, $weather );
}

/**
 * Sets weather data on a user.
 *
 * @since 5.0.0
 *
 * @param int|WP_User $user    User ID or object.
 * @param string      $key     Weather data key to set, or empty to set every property in `$weather`.
 * @param mixed       $weather The value for `$key`, or an array of weather data when `$key` is empty.
 * @return WP_Error|boolean Return success or WP_Error.
 */
function set_user_weatherdata( $user, $key, $weather ) {
	$user = sloc_get_user( $user );
	if ( ! $user ) {
		return false;
	}
	return Sloc_Weather_Data::set_object_weatherdata( 'user', $user->ID, $key, $weather );
}

/**
 * Sets weather data on a term.
 *
 * @since 5.0.0
 *
 * @param int    $term_id Term ID.
 * @param string $key     Weather data key to set, or empty to set every property in `$weather`.
 * @param mixed  $weather The value for `$key`, or an array of weather data when `$key` is empty.
 * @return WP_Error|boolean Return success or WP_Error.
 */
function set_term_weatherdata( $term_id, $key, $weather ) {
	return Sloc_Weather_Data::set_object_weatherdata( 'term', $term_id, $key, $weather );
}

/**
 * Gets weather data for a post.
 *
 * @since 5.0.0
 *
 * @param int|WP_Post|null $post Post ID or object. Defaults to the current post.
 * @param string           $key  Weather data key, or empty for all weather data.
 * @return mixed The value for `$key`, or an array of weather data; false if there is no post.
 */
function get_post_weatherdata( $post = null, $key = '' ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}
	return Sloc_Weather_Data::get_object_weatherdata( 'post', $post->ID, $key );
}

/**
 * Gets weather data for a comment.
 *
 * @since 5.0.0
 *
 * @param int    $comment_id Comment ID.
 * @param string $key        Weather data key, or empty for all weather data.
 * @return mixed The value for `$key`, or an array of weather data.
 */
function get_comment_weatherdata( $comment_id, $key = '' ) {
	return Sloc_Weather_Data::get_object_weatherdata( 'comment', $comment_id, $key );
}

/**
 * Gets weather data for a term.
 *
 * @since 5.0.0
 *
 * @param int    $term_id Term ID.
 * @param string $key     Weather data key, or empty for all weather data.
 * @return mixed The value for `$key`, or an array of weather data.
 */
function get_term_weatherdata( $term_id, $key = '' ) {
	return Sloc_Weather_Data::get_object_weatherdata( 'term', $term_id, $key );
}

/**
 * Gets weather data for a user.
 *
 * @since 5.0.0
 *
 * @param int|WP_User $user User ID or object.
 * @param string      $key  Weather data key, or empty for all weather data.
 * @return mixed The value for `$key`, or an array of weather data; false if there is no user.
 */
function get_user_weatherdata( $user, $key = '' ) {
	$user = sloc_get_user( $user );
	if ( ! $user ) {
		return false;
	}
	return Sloc_Weather_Data::get_object_weatherdata( 'user', $user->ID, $key );
}

/**
 * Returns the weather of a post as plain text, such as "72°F Clear".
 *
 * @since 6.2.0
 *
 * @param int|WP_Post|null $post Optional. Post ID or object. Default the current post.
 * @return string Plain text, not escaped, or an empty string without weather.
 */
function get_post_weather_text( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	return Sloc_Weather_Data::get_the_weather_text( 'post', $post->ID );
}

/**
 * Returns the weather of a comment as plain text, such as "72°F Clear".
 *
 * @since 6.2.0
 *
 * @param int $comment_id Comment ID.
 * @return string Plain text, not escaped, or an empty string without weather.
 */
function get_comment_weather_text( $comment_id ) {
	return Sloc_Weather_Data::get_the_weather_text( 'comment', (int) $comment_id );
}

/**
 * Returns the map for a post with a public location.
 *
 * @since 5.0.0
 *
 * @param int|WP_Post|null $post Post ID or object. Defaults to the current post.
 * @param array            $args Arguments passed to the map provider. Optional.
 * @return string|false Map markup, or an empty string if there is no public location; false if there is no post.
 */
function get_post_map( $post = null, $args = array() ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}
	return Geo_Data::get_map( 'post', $post->ID, $args );
}

/**
 * Returns the map for a user with a public location.
 *
 * @since 5.0.0
 *
 * @param int|WP_User $user User ID or object.
 * @param array       $args Arguments passed to the map provider. Optional.
 * @return string|false Map markup, or an empty string if there is no public location; false if there is no user.
 */
function get_user_map( $user, $args = array() ) {
	$user = sloc_get_user( $user );
	if ( ! $user ) {
		return false;
	}
	return Geo_Data::get_map( 'user', $user->ID, $args );
}

/**
 * Returns the map for a comment with a public location.
 *
 * @since 5.0.0
 *
 * @param int   $comment_id Comment ID.
 * @param array $args       Arguments passed to the map provider. Optional.
 * @return string|false Map markup, or an empty string if there is no public location.
 */
function get_comment_map( $comment_id, $args = array() ) {
	return Geo_Data::get_map( 'comment', $comment_id, $args );
}

/**
 * Returns the map for a term with a public location.
 *
 * @since 5.0.0
 *
 * @param int   $term_id Term ID.
 * @param array $args    Arguments passed to the map provider. Optional.
 * @return string|false Map markup, or an empty string if there is no public location.
 */
function get_term_map( $term_id, $args = array() ) {
	return Geo_Data::get_map( 'term', $term_id, $args );
}

/**
 * Returns the location for any object.
 *
 * Legacy function; use get_post_location() and the other object functions instead.
 *
 * @since 3.2.0
 *
 * @param mixed $object Post, comment, user or term, as an object or ID. Defaults to the current post.
 * @param array $args   Display arguments, as for Geo_Data::get_location(). Optional.
 * @return string Location markup.
 */
function get_simple_location( $object = null, $args = array() ) {
	return Geo_Data::get_location( sloc_get_type_from_object( $object ), sloc_get_id_from_object( $object ), $args );
}

/**
 * Returns the location markup for a post.
 *
 * @since 5.0.0
 *
 * @param int|WP_Post|null $post Post ID or object. Defaults to the current post.
 * @param array            $args Display arguments, as for Geo_Data::get_location(). Optional.
 * @return string|false Location markup, or an empty string if there is none to show; false if there is no post.
 */
function get_post_location( $post = null, $args = array() ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}
	return Geo_Data::get_location( 'post', $post->ID, $args );
}

/**
 * Returns the location markup for a comment.
 *
 * @since 5.0.0
 *
 * @param int   $id   Comment ID.
 * @param array $args Display arguments, as for Geo_Data::get_location(). Optional.
 * @return string|false Location markup, or an empty string if there is none to show.
 */
function get_comment_location( $id, $args = array() ) {
	return Geo_Data::get_location( 'comment', $id, $args );
}

/**
 * Returns the location markup for a user.
 *
 * @since 5.0.0
 *
 * @param int|WP_User $user User ID or object.
 * @param array       $args Display arguments, as for Geo_Data::get_location(). Optional.
 * @return string|false Location markup, or an empty string if there is none to show; false if there is no user.
 */
function get_user_location( $user, $args = array() ) {
	$user = sloc_get_user( $user );
	if ( ! $user ) {
		return false;
	}
	return Geo_Data::get_location( 'user', $user->ID, $args );
}

/**
 * Returns the location markup for a term.
 *
 * @since 5.0.0
 *
 * @param int   $id   Term ID.
 * @param array $args Display arguments, as for Geo_Data::get_location(). Optional.
 * @return string|false Location markup, or an empty string if there is none to show.
 */
function get_term_location( $id, $args = array() ) {
	return Geo_Data::get_location( 'term', $id, $args );
}


/**
 * Returns the public locations of the posts in the main query, keyed by post ID.
 *
 * @since 6.1.0
 * @since 6.1.0 Skips posts without a location without a warning.
 *
 * @return array Array( latitude, longitude ) points keyed by post ID, in the query's order.
 */
function sloc_get_geo_archive_locations() {
	global $wp_query;
	$locations = array();
	if ( empty( $wp_query->posts ) ) {
		return $locations;
	}
	foreach ( $wp_query->posts as $post ) {
		$location = get_post_geodata( $post->ID, false );
		// Posts without a location have no geodata.
		if ( is_array( $location ) && isset( $location['visibility'] ) && 'public' === $location['visibility'] && array_key_exists( 'latitude', $location ) ) {
			$locations[ $post->ID ] = array_values(
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

/**
 * Get a list of all the posts with a public location
 *
 * In order to generate an archive map.
 *
 * @since 1.0.0
 * @since 6.1.0 Uses sloc_get_geo_archive_locations().
 *
 * @return array|string List of array( latitude, longitude ) points, or an empty string if the query has no posts.
 */
function get_geo_archive_location_list() {
	global $wp_query;
	if ( empty( $wp_query->posts ) ) {
		return '';
	}
	return array_values( sloc_get_geo_archive_locations() );
}

/**
 * Returns the IDs of the posts in get_geo_archive_location_list(), in the same order.
 *
 * Archive map markers are numbered in that order, so the IDs say which post each marker is.
 *
 * @since 6.1.0
 *
 * @return int[] Post IDs.
 */
function get_geo_archive_post_list() {
	return array_keys( sloc_get_geo_archive_locations() );
}

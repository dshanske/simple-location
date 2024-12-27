<?php
/**
 * Compatibility Functions.
 *
 * @package Simple_Location
 */

if ( ! function_exists( 'get_comment_datetime' ) ) {
	/**
	 * Retrieve comment published time as a `DateTimeImmutable` object instance.
	 *
	 * The object will be set to the timezone from WordPress settings.
	 *
	 * Modified version of the get_post_datetime function from WordPress 5.3
	 *
	 * @param int|WP_Comment $comment  Optional. WP_Comment object or ID. Default is global `$comment` object.
	 * @return DateTimeImmutable|false Time object on success, false on failure.
	 */
	function get_comment_datetime( $comment = null ) {
		$comment = get_comment( $comment );
		if ( ! $comment ) {
			return false;
		}
		$time = $comment->comment_date;
		if ( empty( $time ) || '0000-00-00 00:00:00' === $time ) {
			return false;
		}
		return date_create_immutable_from_format( 'Y-m-d H:i:s', $time, wp_timezone() );
	}
}

if ( ! function_exists( 'get_comment_timestamp' ) ) {
	/**
	 * Retrieve comment published time as a Unix timestamp.
	 *
	 * Note that this function returns a true Unix timestamp, not summed with timezone offset
	 * like older WP functions.
	 *
	 * Based on get_post_timestamp function introduced in WordPress 5.3
	 *
	 * @param int|WP_Comment $comment  Optional. WP_Comment object or ID. Default is global `$comment` object.
	 * @return int|false Unix timestamp on success, false on failure.
	 */
	function get_comment_timestamp( $comment = null ) {
		$datetime = get_comment_datetime( $comment );
		if ( false === $datetime ) {
			return false;
		}
		return $datetime->getTimestamp();
	}
}

if ( ! function_exists( 'array_key_last_index' ) ) {
	function array_key_last_index( array $array, $index = -1 ) {
		if ( ! empty( $array ) ) {
			return key( array_slice( $array, $index, 1, true ) );
		}
	}
}

if ( ! function_exists( 'str_contains' ) ) {
	/*
	 * Returns whether haystick contains needle.
	 * Polyfill for PHP8.0 function
	 * @param string $haystack String.
	 * @param string $needle String to find within haystack.
	 * @return boolean whether it was found.
	*/
	function str_contains( $haystack, $needle ) {
			return '' === $needle || false !== strpos( $haystack, $needle );
	}
}

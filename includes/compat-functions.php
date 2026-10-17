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

if ( ! function_exists( 'wp_trigger_error' ) ) {
	/**
	 * Generates a user-level error/warning/notice/deprecation message.
	 *
	 * Generates the message when `WP_DEBUG` is true.
	 *
	 * Polyfill for WordPress 6.4 (ClassicPress has it since 2.2). Remove when minimum WordPress >= 6.4.
	 * WordPress 6.2 and 6.3 have no `WP_Exception`, so an `E_USER_ERROR` throws an `Exception` there.
	 *
	 * @since 5.0.26
	 *
	 * @param string $function_name The function that triggered the error.
	 * @param string $message       The message explaining the error.
	 *                              The message can contain allowed HTML 'a' (with href), 'code',
	 *                              'br', 'em', and 'strong' tags and http or https protocols.
	 *                              If it contains other HTML tags or protocols, the message should be escaped
	 *                              before passing to this function to avoid being stripped {@see wp_kses()}.
	 * @param int    $error_level   Optional. The designated error type for this error.
	 *                              Only works with E_USER family of constants. Default E_USER_NOTICE.
	 * @throws Exception For an `E_USER_ERROR`.
	 */
	function wp_trigger_error( $function_name, $message, $error_level = E_USER_NOTICE ) {

		// Bail out if WP_DEBUG is not turned on.
		if ( ! WP_DEBUG ) {
			return;
		}

		/** This action is documented in wp-includes/functions.php */
		do_action( 'wp_trigger_error_run', $function_name, $message, $error_level );

		if ( ! empty( $function_name ) ) {
			$message = sprintf( '%s(): %s', $function_name, $message );
		}

		$message = wp_kses(
			$message,
			array(
				'a'      => array( 'href' => true ),
				'br'     => array(),
				'code'   => array(),
				'em'     => array(),
				'strong' => array(),
			),
			array( 'http', 'https' )
		);

		if ( E_USER_ERROR === $error_level ) {
			$exception = class_exists( 'WP_Exception' ) ? 'WP_Exception' : 'Exception';
			throw new $exception( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		trigger_error( $message, $error_level ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

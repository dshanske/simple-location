<?php
/**
 * Compatibility Functions.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Polyfills keep core's function names, and array_key_last_index() is public API that predates the prefix rule. To be revisited with namespacing.

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
	/**
	 * Returns the key at a position counted from the end of an array.
	 *
	 * @since 4.1.12
	 *
	 * @param array $array The array.
	 * @param int   $index Position from the end. Default -1, the last key.
	 * @return int|string|null The key, or null for an empty array.
	 */
	function array_key_last_index( array $array, $index = -1 ) {
		if ( ! empty( $array ) ) {
			return key( array_slice( $array, $index, 1, true ) );
		}
	}
}

if ( ! function_exists( 'wp_get_admin_notice' ) ) {
	/**
	 * Creates and returns the markup for an admin notice.
	 *
	 * Polyfill for WordPress 6.4 (ClassicPress has it). Remove when minimum WordPress >= 6.4.
	 *
	 * @since 6.2.0
	 *
	 * @param string $message The message.
	 * @param array  $args {
	 *     Optional. An array of arguments for the admin notice. Default empty array.
	 *
	 *     @type string   $type               Optional. The type of admin notice.
	 *                                        For example, 'error', 'success', 'warning', 'info'.
	 *                                        Default empty string.
	 *     @type bool     $dismissible        Optional. Whether the admin notice is dismissible. Default false.
	 *     @type string   $id                 Optional. The value of the admin notice's ID attribute. Default empty string.
	 *     @type string[] $additional_classes Optional. A string array of class names. Default empty array.
	 *     @type string[] $attributes         Optional. Additional attributes for the notice div. Default empty array.
	 *     @type bool     $paragraph_wrap     Optional. Whether to wrap the message in paragraph tags. Default true.
	 * }
	 * @return string The markup for an admin notice.
	 */
	function wp_get_admin_notice( $message, $args = array() ) {
		$defaults = array(
			'type'               => '',
			'dismissible'        => false,
			'id'                 => '',
			'additional_classes' => array(),
			'attributes'         => array(),
			'paragraph_wrap'     => true,
		);

		$args = wp_parse_args( $args, $defaults );

		/** This filter is documented in wp-includes/functions.php of WordPress 6.4. */
		$args       = apply_filters( 'wp_admin_notice_args', $args, $message ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's filter, applied as the core function does.
		$id         = '';
		$classes    = 'notice';
		$attributes = '';

		if ( is_string( $args['id'] ) ) {
			$trimmed_id = trim( $args['id'] );

			if ( '' !== $trimmed_id ) {
				$id = 'id="' . $trimmed_id . '" ';
			}
		}

		if ( is_string( $args['type'] ) ) {
			$type = trim( $args['type'] );

			if ( str_contains( $type, ' ' ) ) {
				_doing_it_wrong(
					__FUNCTION__,
					sprintf(
						/* translators: %s: The "type" key. */
						esc_html__( 'The %s key must be a string without spaces.', 'simple-location' ),
						'<code>type</code>'
					),
					'6.4.0'
				);
			}

			if ( '' !== $type ) {
				$classes .= ' notice-' . $type;
			}
		}

		if ( true === $args['dismissible'] ) {
			$classes .= ' is-dismissible';
		}

		if ( is_array( $args['additional_classes'] ) && ! empty( $args['additional_classes'] ) ) {
			$classes .= ' ' . implode( ' ', $args['additional_classes'] );
		}

		if ( is_array( $args['attributes'] ) && ! empty( $args['attributes'] ) ) {
			$attributes = '';
			foreach ( $args['attributes'] as $attr => $val ) {
				if ( is_bool( $val ) ) {
					$attributes .= $val ? ' ' . $attr : '';
				} elseif ( is_int( $attr ) ) {
					$attributes .= ' ' . esc_attr( trim( $val ) );
				} elseif ( $val ) {
					$attributes .= ' ' . $attr . '="' . esc_attr( trim( $val ) ) . '"';
				}
			}
		}

		if ( false !== $args['paragraph_wrap'] ) {
			$message = "<p>$message</p>";
		}

		$markup = sprintf( '<div %1$sclass="%2$s"%3$s>%4$s</div>', $id, $classes, $attributes, $message );

		/** This filter is documented in wp-includes/functions.php of WordPress 6.4. */
		return apply_filters( 'wp_admin_notice_markup', $markup, $message, $args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's filter, applied as the core function does.
	}
}

if ( ! function_exists( 'wp_admin_notice' ) ) {
	/**
	 * Outputs an admin notice.
	 *
	 * Polyfill for WordPress 6.4 (ClassicPress has it). Remove when minimum WordPress >= 6.4.
	 *
	 * @since 6.2.0
	 *
	 * @param string $message The message to output.
	 * @param array  $args    Optional. Arguments for the admin notice; see wp_get_admin_notice(). Default empty array.
	 */
	function wp_admin_notice( $message, $args = array() ) {
		/** This action is documented in wp-includes/functions.php of WordPress 6.4. */
		do_action( 'wp_admin_notice', $message, $args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's action, fired as the core function does.

		echo wp_kses_post( wp_get_admin_notice( $message, $args ) );
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
	 * @since 6.0.0
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
		do_action( 'wp_trigger_error_run', $function_name, $message, $error_level ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's action, fired as the core function does.

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

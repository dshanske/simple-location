<?php
/**
 * Compatibility Functions.
 *
 * @package Simple_Location
 */

if ( ! function_exists( 'current_datetime' ) ) {
	/**
	 * Retrieves the current time as an object with the timezone from settings.
	 *
	 * @since 5.3.0 - Backported to Simple Location and DateTime used for pre PHP 5.5 compatibility for new
	 *
	 * @return DateTimeImmutable Date and time object.
	 */
	function current_datetime() {
		return new DateTimeImmutable( 'now', wp_timezone() );
	}
}

if ( ! function_exists( 'get_post_timestamp' ) ) {
	/**
	 * Retrieve post published or modified time as a Unix timestamp.
	 *
	 * Note that this function returns a true Unix timestamp, not summed with timezone offset
	 * like older WP functions.
	 *
	 * @since 5.3.0 - backported to Simple Location
	 *
	 * @param int|WP_Post $post  Optional. WP_Post object or ID. Default is global `$post` object.
	 * @param string      $field Optional. Post field to use. Accepts 'date' or 'modified'.
	 * @return int|false Unix timestamp on success, false on failure.
	 */
	function get_post_timestamp( $post = null, $field = 'date' ) {
		$datetime = get_post_datetime( $post, $field );
		if ( false === $datetime ) {
			return false;
		}
		return $datetime->getTimestamp();
	}
}


if ( ! function_exists( 'get_post_datetime' ) ) {
	/**
	 * Retrieve post published or modified time as a `DateTimeImmutable` object instance.
	 *
	 * The object will be set to the timezone from WordPress settings.
	 *
	 * @since 5.3.0 - backported to Simple Location
	 *
	 * @param int|WP_Post $post  Optional. WP_Post object or ID. Default is global `$post` object.
	 * @param string      $field Optional. Post field to use. Accepts 'date' or 'modified'.
	 * @return DateTimeImmutable|false Time object on success, false on failure.
	 */
	function get_post_datetime( $post = null, $field = 'date' ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return false;
		}
		$time = ( 'modified' === $field ) ? $post->post_modified : $post->post_date;
		if ( empty( $time ) || '0000-00-00 00:00:00' === $time ) {
			return false;
		}
		return date_create_immutable_from_format( 'Y-m-d H:i:s', $time, wp_timezone() );
	}
}

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



if ( ! function_exists( 'wp_timezone_string' ) ) {
	/**
	 * Retrieves the timezone from site settings as a string.
	 *
	 * Uses the `timezone_string` option to get a proper timezone if available,
	 * otherwise falls back to an offset.
	 *
	 * @since 5.3.0 - backported into Simple Location
	 *
	 * @return string PHP timezone string or a ±HH:MM offset.
	 */
	function wp_timezone_string() {
		$timezone_string = get_option( 'timezone_string' );
		if ( $timezone_string ) {
			return $timezone_string;
		}
		$offset    = (float) get_option( 'gmt_offset' );
		$hours     = (int) $offset;
		$minutes   = ( $offset - $hours );
		$sign      = ( $offset < 0 ) ? '-' : '+';
		$abs_hour  = abs( $hours );
		$abs_mins  = abs( $minutes * 60 );
		$tz_offset = sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );
		return $tz_offset;
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	/**
	 * Retrieves the timezone from site settings as a `DateTimeZone` object.
	 *
	 * Timezone can be based on a PHP timezone string or a ±HH:MM offset.
	 *
	 * @since 5.3.0 - backported into Simple Location
	 *
	 * @return DateTimeZone Timezone object.
	 */
	function wp_timezone() {
		return new DateTimeZone( wp_timezone_string() );
	}
}


if ( ! function_exists( 'wp_date' ) ) {
	/**
	 * Retrieves the date, in localized format.
	 *
	 * This is a newer function, intended to replace `date_i18n()` without legacy quirks in it.
	 *
	 * Note that, unlike `date_i18n()`, this function accepts a true Unix timestamp, not summed
	 * with timezone offset.
	 *
	 * @since 5.3.0 - backported to Simple Location
	 *
	 * @param string       $format    PHP date format.
	 * @param int          $timestamp Optional. Unix timestamp. Defaults to current time.
	 * @param DateTimeZone $timezone  Optional. Timezone to output result in. Defaults to timezone
	 *                                from site settings.
	 * @return string|false The date, translated if locale specifies it. False on invalid timestamp input.
	 */
	function wp_date( $format, $timestamp = null, $timezone = null ) {
		global $wp_locale;
		if ( null === $timestamp ) {
			$timestamp = time();
		} elseif ( ! is_numeric( $timestamp ) ) {
			return false;
		}
		if ( ! $timezone ) {
			$timezone = wp_timezone();
		}
		$datetime = date_create( '@' . $timestamp );
		$datetime->setTimezone( $timezone );
		if ( empty( $wp_locale->month ) || empty( $wp_locale->weekday ) ) {
			$date = $datetime->format( $format );
		} else {
			// We need to unpack shorthand `r` format because it has parts that might be localized.
			$format        = preg_replace( '/(?<!\\\\)r/', DATE_RFC2822, $format );
			$new_format    = '';
			$format_length = strlen( $format );
			$month         = $wp_locale->get_month( $datetime->format( 'm' ) );
			$weekday       = $wp_locale->get_weekday( $datetime->format( 'w' ) );
			for ( $i = 0; $i < $format_length; $i ++ ) {
				switch ( $format[ $i ] ) {
					case 'D':
						$new_format .= backslashit( $wp_locale->get_weekday_abbrev( $weekday ) );
						break;
					case 'F':
						$new_format .= backslashit( $month );
						break;
					case 'l':
						$new_format .= backslashit( $weekday );
						break;
					case 'M':
						$new_format .= backslashit( $wp_locale->get_month_abbrev( $month ) );
						break;
					case 'a':
						$new_format .= backslashit( $wp_locale->get_meridiem( $datetime->format( 'a' ) ) );
						break;
					case 'A':
						$new_format .= backslashit( $wp_locale->get_meridiem( $datetime->format( 'A' ) ) );
						break;
					case '\\':
						$new_format .= $format[ $i ];
						// If character follows a slash, we add it without translating.
						if ( $i < $format_length ) {
							$new_format .= $format[ ++$i ];
						}
						break;
					default:
						$new_format .= $format[ $i ];
						break;
				}
			}
			$date = $datetime->format( $new_format );
			$date = wp_maybe_decline_date( $date );
		}
		/**
		 * Filters the date formatted based on the locale.
		 *
		 * @since 5.3.0 but backported to Simple Location
		 *
		 * @param string       $date      Formatted date string.
		 * @param string       $format    Format to display the date.
		 * @param int          $timestamp Unix timestamp.
		 * @param DateTimeZone $timezone  Timezone.
		 */
		$date = apply_filters( 'wp_date', $date, $format, $timestamp, $timezone );
		return $date;
	}
}

if ( ! function_exists( 'array_key_last' ) ) {
	function array_key_last( array $array ) {
		if ( ! empty( $array ) ) {
			return key( array_slice( $array, -1, 1, true ) );
		}
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



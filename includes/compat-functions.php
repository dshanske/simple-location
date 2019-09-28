<?php

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


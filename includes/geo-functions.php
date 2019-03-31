<?php

function sloc_sunrise( $latitude, $longitude, $timezone, $timestamp = null ) {
	if ( ! $timestamp ) {
		$timestamp = time();
	}
	if ( ! $timezone ) {
		$timezone = get_option( 'timezone_string' );
	}
	$sunrise  = date_sunrise( $timestamp, SUNFUNCS_RET_TIMESTAMP, $latitude, $longitude );
	$datetime = new DateTime();
	$datetime->setTimestamp( $sunrise );
	$datetime->setTimezone( new DateTimeZone( $timezone ) );
	return $datetime->format( DATE_W3C );
}

function sloc_sunset( $latitude, $longitude, $timezone, $timestamp = null ) {
	if ( ! $timestamp ) {
		$timestamp = time();
	}
	$sunset   = date_sunset( $timestamp, SUNFUNCS_RET_TIMESTAMP, $latitude, $longitude );
	$datetime = new DateTime();
	$datetime->setTimestamp( $sunset );
	$datetime->setTimezone( new DateTimeZone( $timezone ) );
	return $datetime->format( DATE_W3C );
}

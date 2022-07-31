<?php
/**
 * Trait for Google Maps API
 *
 * @package Simple_Location
 */

/**
 * Implements reproducable code for the Google Maps API.
 *
 * @since 4.6.0
 */
trait Sloc_API_Google {
	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'Google', 'simple-location' ), 'sloc_google_api' );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'Google Maps', 'simple-location' ), 'sloc_google_api' ); 
	}

}

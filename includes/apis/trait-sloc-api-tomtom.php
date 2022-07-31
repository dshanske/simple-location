<?php
/**
 * Trait for TomTom API
 *
 * @package Simple_Location
 */

/**
 * Implements reproducable code for the TomTom API.
 *
 * @since 4.6.0
 */
trait Sloc_API_TomTom {
	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'TomTom', 'simple-location' ), 'sloc_tomtom_api' );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'TomTom', 'simple-location' ), 'sloc_tomtom_api' ); 
	}

}

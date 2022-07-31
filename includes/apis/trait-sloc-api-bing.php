<?php
/**
 * Trait for Bing Maps API
 *
 * @package Simple_Location
 */

/**
 * Implements reproducable code for the Bing Maps API.
 *
 * @since 4.6.0
 */
trait Sloc_API_Bing {
	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'Bing', 'simple-location' ), 'sloc_bing_api' );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'Bing', 'simple-location' ), 'sloc_bing_api' ); 
	}

}

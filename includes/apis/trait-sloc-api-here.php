<?php
/**
 * Trait for Here API
 *
 * @package Simple_Location
 */

/**
 * Implements reproducable code for the HERE API.
 *
 * @since 4.6.0
 */
trait Sloc_API_Here {
	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'HERE', 'simple-location' ), 'sloc_here_api' );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'HERE', 'simple-location' ), 'sloc_here_api' ); 
	}

}

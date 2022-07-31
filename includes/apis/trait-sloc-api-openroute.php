<?php
/**
 * Trait for OpenRoute API
 *
 * @package Simple_Location
 */

/**
 * Implements reproducable code for the OpenRoute API.
 *
 * @since 4.6.0
 */
trait Sloc_API_OpenRoute {
	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'OpenRoute', 'simple-location' ), 'sloc_openroute_api' );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'OpenRoute', 'simple-location' ), 'sloc_openroute_api' );
	}
}

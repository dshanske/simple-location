<?php
/**
 * Trait for GeoNames API
 *
 * @package Simple_Location
 */

/**
 * Implements reproducable code for the Bing Maps API.
 *
 * @since 4.6.0
 */
trait Sloc_API_Geonames {
	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'Geonames', 'simple-location' ), 'sloc_geonames_user', __( 'User', 'simple-location' ) );
	}


	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'GeoNames', 'simple-location' ), 'sloc_geonames_user', __( 'User', 'simple-location' ) );
	}
}

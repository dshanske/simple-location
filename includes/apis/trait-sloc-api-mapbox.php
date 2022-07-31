<?php
/**
 * Trait for Mapbox API
 *
 * @package Simple_Location
 */

/**
 * Implements reproducable code for the Bing Maps API.
 *
 * @since 4.6.0
 */
trait Sloc_API_Mapbox {
	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'Mapbox', 'simple-location' ), 'sloc_mapbox_api' );
		self::add_settings_parameter( __( 'Mapbox User', 'simple-location' ), 'sloc_mapbox_user', __( 'Username', 'simple-location' ) );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'Mapbox', 'simple-location' ), 'sloc_mapbox_api' ); 
		self::register_settings_api( __( 'Mapbox User', 'simple-location' ), 'sloc_mapbox_user' ); 
	}

}

<?php
/**
 * Trait for MapQuest API
 *
 * @package Simple_Location
 */

/**
 * Implements reproducable code for the MapQuest API.
 *
 * @since 4.6.0
 */
trait Sloc_API_MapQuest {
	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'MapQuest', 'simple-location' ), 'sloc_mapquest_api' );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'MapQuest', 'simple-location' ), 'sloc_mapquest_api' ); 
	}

}

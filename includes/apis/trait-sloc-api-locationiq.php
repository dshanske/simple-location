<?php
/**
 * Trait for LocationIQ API
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Implements reproducable code for the Bing Maps API.
 *
 * @since 4.6.0
 */
trait Sloc_API_LocationIQ {
	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 * @since 6.0.0 Adds the map API key field for the map provider.
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'LocationIQ', 'simple-location' ), 'sloc_locationiq_api' );
		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			self::add_map_api_setting( __( 'LocationIQ', 'simple-location' ), 'locationiq' );
		}
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 * @since 6.0.0 Registers the map API key setting for the map provider.
	 */
	public static function init() {
		self::register_settings_api( __( 'LocationIQ', 'simple-location' ), 'sloc_locationiq_api' );
		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			self::register_map_api_setting( __( 'LocationIQ', 'simple-location' ), 'locationiq' );
		}
	}
}

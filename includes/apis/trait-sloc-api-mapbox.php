<?php
/**
 * Trait for Mapbox API
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

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
	 * @since 6.1.0 Adds the image format field for the map provider.
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'Mapbox', 'simple-location' ), 'sloc_mapbox_api' );
		self::add_settings_parameter( __( 'Mapbox User', 'simple-location' ), 'sloc_mapbox_user', __( 'Username', 'simple-location' ) );
		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			add_settings_field(
				'mapboxstyle', // id
				__( 'Mapbox Style', 'simple-location' ),
				array( 'Loc_Config', 'style_callback' ),
				'simloc',
				'sloc_map',
				array(
					'label_for' => 'sloc_mapbox_style',
					'provider'  => new Map_Provider_Mapbox(),

				)
			);

			self::add_format_setting( __( 'Mapbox', 'simple-location' ), 'mapbox' );
		}
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 * @since 6.1.0 Registers the image format setting for the map provider.
	 */
	public static function init() {
		self::register_settings_api( __( 'Mapbox', 'simple-location' ), 'sloc_mapbox_api' );
		self::register_settings_api( __( 'Mapbox User', 'simple-location' ), 'sloc_mapbox_user' );

		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			register_setting(
				'simloc',
				'sloc_mapbox_style',
				array(
					'type'         => 'string',
					'description'  => 'Mapbox Style',
					'show_in_rest' => false,
					'default'      => 'streets-v10',
				)
			);

			self::register_format_setting( __( 'Mapbox', 'simple-location' ), 'mapbox' );
		}
	}
}

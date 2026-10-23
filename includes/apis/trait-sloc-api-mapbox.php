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
	 * @since 6.1.0 Adds the image format and map API key fields for the map provider.
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'Mapbox', 'simple-location' ), 'sloc_mapbox_api' );
		self::add_settings_parameter( __( 'Mapbox User', 'simple-location' ), 'sloc_mapbox_user', __( 'Username', 'simple-location' ) );
		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			self::add_map_api_setting(
				__( 'Mapbox', 'simple-location' ),
				'mapbox',
				__( 'Optional. A public token (starting pk.) for map images, which show it to every visitor, so restrict it to this site\'s URL. Leave empty to use the Mapbox API key, unless that is a secret token (starting sk.), which is only used to list your styles and never shown.', 'simple-location' )
			);

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
	 * @since 6.1.0 Registers the image format and map API key settings for the map provider.
	 * @since 6.1.0 The style setting is empty by default, to use the Map Style setting.
	 */
	public static function init() {
		self::register_settings_api( __( 'Mapbox', 'simple-location' ), 'sloc_mapbox_api' );
		self::register_settings_api( __( 'Mapbox User', 'simple-location' ), 'sloc_mapbox_user' );

		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			self::register_map_api_setting( __( 'Mapbox', 'simple-location' ), 'mapbox' );

			register_setting(
				'simloc',
				'sloc_mapbox_style',
				array(
					'type'         => 'string',
					'description'  => 'Mapbox Style',
					'show_in_rest' => false,
					'default'      => '',
				)
			);

			self::register_format_setting( __( 'Mapbox', 'simple-location' ), 'mapbox' );
		}
	}
}

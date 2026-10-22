<?php
/**
 * Trait for GeoApify API
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Implements reproducable code for the Bing Maps API.
 *
 * @since 4.6.0
 */
trait Sloc_API_GeoApify {
	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 * @since 6.1.0 Adds the image format field for the map provider.
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'GeoApify', 'simple-location' ), 'sloc_geoapify_api' );

		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			add_settings_field(
				'geoapifystyle', // id
				__( 'Geoapify Style', 'simple-location' ),
				array( 'Loc_Config', 'style_callback' ),
				'simloc',
				'sloc_map',
				array(
					'label_for' => 'sloc_geoapify_style',
					'provider'  => new Map_Provider_Geoapify(),
				)
			);

			self::add_format_setting( __( 'Geoapify', 'simple-location' ), 'geoapify' );
		}
	}


	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 * @since 6.1.0 Registers the image format setting for the map provider.
	 */
	public static function init() {
		self::register_settings_api( __( 'GeoApify', 'simple-location' ), 'sloc_geoapify_api' );

		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			register_setting(
				'simloc',
				'sloc_geoapify_style',
				array(
					'type'         => 'string',
					'description'  => 'Geoapify Map Style',
					'show_in_rest' => false,
					'default'      => 'osm-carto',
				)
			);

			self::register_format_setting( __( 'Geoapify', 'simple-location' ), 'geoapify' );
		}
	}
}

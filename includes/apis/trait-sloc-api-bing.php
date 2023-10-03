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
		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			add_settings_field(
				'bingstyle', // id
				__( 'Bing Style', 'simple-location' ),
				array( 'Loc_Config', 'style_callback' ),
				'simloc',
				'sloc_map',
				array(
					'label_for' => 'sloc_bing_style',
					'provider'  => new Map_Provider_Bing(),
				)
			);
		}
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		self::register_settings_api( __( 'Bing', 'simple-location' ), 'sloc_bing_api' );
		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			register_setting(
				'simloc',
				'sloc_bing_style',
				array(
					'type'         => 'string',
					'description'  => 'Bing Map Style',
					'show_in_rest' => false,
					'default'      => 'CanvasLight',
				)
			);
		}
	}
}

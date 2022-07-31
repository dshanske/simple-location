<?php
/**
 * Trait for TomTom API
 *
 * @package Simple_Location
 */

/**
 * Implements reproducable code for the TomTom API.
 *
 * @since 4.6.0
 */
trait Sloc_API_TomTom {
	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'TomTom', 'simple-location' ), 'sloc_tomtom_api' );
		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {

			add_settings_field(
				'tomtomstyle', // id
				__( 'TomTom Style', 'simple-location' ),
				array( 'Loc_Config', 'style_callback' ),
				'simloc',
				'sloc_map',
				array(
					'label_for' => 'sloc_tomtom_style',
					'provider'  => new Map_Provider_TomTom(),
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
		self::register_settings_api( __( 'TomTom', 'simple-location' ), 'sloc_tomtom_api' );
		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {

			register_setting(
				'simloc',
				'sloc_tomtom_style',
				array(
					'type'         => 'string',
					'description'  => 'TomTom Map Style',
					'show_in_rest' => false,
					'default'      => 'main',
				)
			);
		}
	}
}

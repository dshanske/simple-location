<?php
/**
 * Trait for Stadia Maps API
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Implements reproducable code for the Stadia Maps API.
 *
 * @since 6.1.0
 */
trait Sloc_API_Stadia {
	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 6.1.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'Stadia Maps', 'simple-location' ), 'sloc_stadia_api' );

		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			add_settings_field(
				'stadiastyle', // id
				__( 'Stadia Maps Style', 'simple-location' ),
				array( 'Loc_Config', 'style_callback' ),
				'simloc',
				'sloc_map',
				array(
					'label_for' => 'sloc_stadia_style',
					'provider'  => new Map_Provider_Stadia(),
				)
			);

			self::add_format_setting( __( 'Stadia Maps', 'simple-location' ), 'stadia' );
		}
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 6.1.0
	 * @since 6.1.0 The style setting is empty by default, to use the Map Style setting.
	 */
	public static function init() {
		self::register_settings_api( __( 'Stadia Maps', 'simple-location' ), 'sloc_stadia_api' );

		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			register_setting(
				'simloc',
				'sloc_stadia_style',
				array(
					'type'         => 'string',
					'description'  => 'Stadia Maps Style',
					'show_in_rest' => false,
					'default'      => '',
				)
			);

			self::register_format_setting( __( 'Stadia Maps', 'simple-location' ), 'stadia' );
		}
	}
}

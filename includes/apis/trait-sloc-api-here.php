<?php
/**
 * Trait for Here API
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Implements reproducable code for the HERE API.
 *
 * @since 4.6.0
 */
trait Sloc_API_Here {
	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 * @since 6.0.0 Adds the map API key field for the map provider.
	 * @since 6.1.0 Adds the image format field for the map provider.
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'HERE', 'simple-location' ), 'sloc_here_api' );
		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			self::add_map_api_setting( __( 'HERE', 'simple-location' ), 'here' );

			add_settings_field(
				'herestyle', // id
				__( 'HERE Style', 'simple-location' ),
				array( 'Loc_Config', 'style_callback' ),
				'simloc',
				'sloc_map',
				array(
					'label_for' => 'sloc_here_style',
					'provider'  => new Map_Provider_Here(),
				)
			);

			self::add_format_setting( __( 'HERE', 'simple-location' ), 'here' );
		}
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 * @since 6.0.0 Registers the map API key setting for the map provider.
	 * @since 6.1.0 Registers the image format setting for the map provider.
	 * @since 6.1.0 The style setting is empty by default, to use the Map Style setting.
	 * @since 6.1.2 The style setting is sanitized.
	 */
	public static function init() {
		self::register_settings_api( __( 'HERE', 'simple-location' ), 'sloc_here_api' );
		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			self::register_map_api_setting( __( 'HERE', 'simple-location' ), 'here' );

			register_setting(
				'simloc',
				'sloc_here_style',
				array(
					'type'              => 'string',
					'description'       => 'HERE Style',
					'show_in_rest'      => false,
					'default'           => '',
					'sanitize_callback' => array( 'Map_Provider', 'sanitize_style' ),
				)
			);

			self::register_format_setting( __( 'HERE', 'simple-location' ), 'here' );
		}
	}
}

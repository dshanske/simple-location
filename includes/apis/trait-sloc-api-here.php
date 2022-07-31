<?php
/**
 * Trait for Here API
 *
 * @package Simple_Location
 */

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
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'HERE', 'simple-location' ), 'sloc_here_api' );
		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {

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

			add_settings_field(
				'heretype', // id
				__( 'HERE Map Scheme Type', 'simple-location' ),
				array( get_called_class(), 'type_callback' ),
				'simloc',
				'sloc_map',
				array(
					'label_for' => 'sloc_here_type',
					'provider'  => new Map_Provider_Here(),
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
		self::register_settings_api( __( 'HERE', 'simple-location' ), 'sloc_here_api' );
		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {

			register_setting(
				'simloc',
				'sloc_here_style',
				array(
					'type'         => 'string',
					'description'  => 'HERE Style',
					'show_in_rest' => false,
					'default'      => 'alps',
				)
			);

			register_setting(
				'simloc',
				'sloc_here_type',
				array(
					'type'         => 'string',
					'description'  => 'HERE Map Scheme Type',
					'show_in_rest' => false,
					'default'      => 0,
				)
			);
		}
	}
}

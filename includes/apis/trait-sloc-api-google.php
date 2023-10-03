<?php
/**
 * Trait for Google Maps API
 *
 * @package Simple_Location
 */

/**
 * Implements reproducable code for the Google Maps API.
 *
 * @since 4.6.0
 */
trait Sloc_API_Google {
	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'Google', 'simple-location' ), 'sloc_google_api' );
		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			add_settings_field(
				'googlestyle', // id
				__( 'Google Style', 'simple-location' ),
				array( 'Loc_Config', 'style_callback' ),
				'simloc',
				'sloc_map',
				array(
					'label_for' => 'sloc_google_style',
					'provider'  => new Map_Provider_Google(),
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
		self::register_settings_api( __( 'Google Maps', 'simple-location' ), 'sloc_google_api' );
		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			register_setting(
				'simloc',
				'sloc_google_style',
				array(
					'type'         => 'string',
					'description'  => 'Google Map Style',
					'show_in_rest' => false,
					'default'      => 'roadmap',
				)
			);
		}
	}
}

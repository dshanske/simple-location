<?php
/**
 * Trait for MapQuest API
 *
 * @package Simple_Location
 */

/**
 * Implements reproducable code for the MapQuest API.
 *
 * @since 4.6.0
 */
trait Sloc_API_MapQuest {
	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'MapQuest', 'simple-location' ), 'sloc_mapquest_api' );

		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			add_settings_field(
				'mapqueststyle', // id
				__( 'MapQuest Style', 'simple-location' ),
				array( 'Loc_Config', 'style_callback' ),
				'simloc',
				'sloc_map',
				array(
					'label_for' => 'sloc_mapquest_style',
					'provider'  => new Map_Provider_Mapquest(),
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
		self::register_settings_api( __( 'MapQuest', 'simple-location' ), 'sloc_mapquest_api' );
		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			register_setting(
				'simloc',
				'sloc_mapquest_style',
				array(
					'type'         => 'string',
					'description'  => 'Mapquest Map Style',
					'show_in_rest' => false,
					'default'      => 'map',
				)
			);
		}
	}
}

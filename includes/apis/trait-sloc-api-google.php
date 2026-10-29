<?php
/**
 * Trait for Google Maps API
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

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
	 * @since 6.0.0 Adds the map API key field for the map provider.
	 * @since 6.0.0 Adds the URL signing secret field for the map provider.
	 * @since 6.1.0 Adds the image format field for the map provider.
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'Google', 'simple-location' ), 'sloc_google_api' );
		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			self::add_map_api_setting( __( 'Google', 'simple-location' ), 'google' );
			add_settings_field(
				'sloc_google_map_secret',
				__( 'Google Maps URL Signing Secret', 'simple-location' ),
				array( 'Loc_Config', 'string_callback' ),
				'sloc_api',
				'sloc_api',
				array(
					'label_for'   => 'sloc_google_map_secret',
					'type'        => 'password',
					'description' => __( 'Optional. Signed map URLs can\'t be changed to request other maps, even though the key is visible. Copy the secret from the Secret Generator on the Google Cloud console\'s Credentials page, in the same project as the map key. Then set the Maps Static API quota "Unsigned requests per day" to 0, so Google refuses unsigned requests.', 'simple-location' ),
				)
			);
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

			self::add_format_setting( __( 'Google', 'simple-location' ), 'google' );
		}
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 * @since 6.0.0 Registers the map API key setting for the map provider.
	 * @since 6.0.0 Registers the URL signing secret setting for the map provider.
	 * @since 6.1.0 Registers the image format setting for the map provider.
	 * @since 6.1.0 The style setting is empty by default, to use the Map Style setting.
	 * @since 6.2.0 The style setting is sanitized.
	 */
	public static function init() {
		self::register_settings_api( __( 'Google Maps', 'simple-location' ), 'sloc_google_api' );
		if ( 'Map_Provider' === get_parent_class( get_called_class() ) ) {
			self::register_map_api_setting( __( 'Google', 'simple-location' ), 'google' );
			self::register_settings_api( __( 'Google Maps', 'simple-location' ), 'sloc_google_map_secret', __( 'URL Signing Secret', 'simple-location' ) );
			register_setting(
				'simloc',
				'sloc_google_style',
				array(
					'type'              => 'string',
					'description'       => 'Google Map Style',
					'show_in_rest'      => false,
					'default'           => '',
					'sanitize_callback' => array( 'Map_Provider', 'sanitize_style' ),
				)
			);

			self::register_format_setting( __( 'Google', 'simple-location' ), 'google' );
		}
	}
}

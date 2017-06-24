<?php

add_filter( 'admin_init', array( 'Loc_Config', 'admin_init' ), 10 );
add_filter( 'init', array( 'Loc_Config', 'init' ), 10 );

class Loc_Config {

	/**
	 * Add Settings to the Discussions Page
	 */
	public static function init() {
		register_setting(
			'media', // settings page
			'sloc_default_map_provider', // option name
			array(
				'type' => 'string',
				'description' => 'Default Map Provider',
				'show_in_rest' => false,
				'default' => 'OSM',
			)
		);
		register_setting(
			'media', // settings page
			'sloc_default_reverse_provider', // option name
			array(
				'type' => 'string',
				'description' => 'Default Map Provider',
				'show_in_rest' => false,
				'default' => 'OSM',
			)
		);
		register_setting(
			'media', // settings page
			'sloc_google_api', // option name
			array(
				'type' => 'string',
				'description' => 'Google Maps API Key',
				'show_in_rest' => false,
				'default' => '',
			)
		);
		register_setting(
			'media', // settings page
			'sloc_mapbox_api', // option name
			array(
				'type' => 'string',
				'description' => 'Mapbox Static Maps API Key',
				'show_in_rest' => false,
				'default' => '',
			)
		);
		register_setting(
			'media', // settings page
			'sloc_height', // option name
			array(
				'type' => 'number',
				'description' => 'Simple Location Map Height',
				'show_in_rest' => true,
				'default' => 350,
			)
		);
		register_setting(
			'media', // settings page
			'sloc_width', // option name
			array(
				'type' => 'number',
				'description' => 'Simple Location Map Width',
				'show_in_rest' => true,
				'default' => 350,
			)
		);
		register_setting(
			'media', // settings page
			'sloc_zoom', // option name
			array(
				'type' => 'number',
				'description' => 'Simple Location Map Zoom',
				'show_in_rest' => true,
				'default' => 14,
			)
		);
	}

	public static function admin_init() {
		add_settings_section(
			'sloc',
			'Simple Location Map Settings',
			array( 'Loc_Config', 'sloc_settings' ),
			'media'
		);
		add_settings_field(
			'sloc_default_map_provider', // id
			'Default Map Provider', // setting title
			array( 'Loc_Config', 'map_provider_callback' ), // display callback
			'media', // settings page
			'sloc', // settings section
			array( 'name' => 'sloc_default_map_provider' )
		);
		add_settings_field(
			'googleapi', // id
			'Google Maps API Key', // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'media', // settings page
			'sloc', // settings section
			array( 'name' => 'sloc_google_api' )
		);
		add_settings_field(
			'mapboxapi', // id
			'Mapbox API Key', // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'media', // settings page
			'sloc', // settings section
			array( 'name' => 'sloc_mapbox_api' )
		);
		add_settings_field(
			'height', // id
			'Map Height', // setting title
			array( 'Loc_Config', 'number_callback' ), // display callback
			'media', // settings page
			'sloc', // settings section
			array( 'name' => 'sloc_height' )
		);
		add_settings_field(
			'width', // id
			'Map Width', // setting title
			array( 'Loc_Config', 'number_callback' ), // display callback
			'media', // settings page
			'sloc', // settings section
			array( 'name' => 'sloc_width' )
		);
		add_settings_field(
			'zoom', // id
			'Map Zoom', // setting title
			array( 'Loc_Config', 'number_callback' ), // display callback
			'media', // settings page
			'sloc', // settings section
			array( 'name' => 'sloc_zoom' )
		);
	}

	public static function checkbox_callback(array $args) {
		$name = $args['name'];
		$checked = get_option( $name );
		echo "<input name='" . $name . "' type='hidden' value='0' />";
		echo "<input name='" . $name . "' type='checkbox' value='1' " . checked( 1, $checked, false ) . ' /> ';
	}

	public static function number_callback(array $args) {
		$name = $args['name'];
		$text = get_option( $name );
		echo "<input name='" . $name . "' type='number' min='0' step='1' size='4' class='small-text' value='" . $text . "' /> ";
	}

	public static function string_callback(array $args) {
		$name = $args['name'];
		$text = get_option( $name );
		echo "<input name='" . $name . "' size='50' class='regular-text' type='string' value='" . $text . "' /> ";
	}

	public static function map_provider_callback(array $args) {
		$name = $args['name'];
		$text = get_option( $name );
		echo '<select name="' . $name . '">';
		echo '<option value="OSM" '  . selected( $text, 'OSM' ) .  '>' . __( 'OpenStreetMap/MapBox', 'simple-location' ) . '</option>';
		echo '<option value="Google" '  . selected( $text, 'Google' ) .  '>' . __( 'Google Maps', 'simple-location' ) . '</option>';
		echo '</select><br /><br />';
	}

	public static function sloc_settings() {
		_e( 'Default Settings for Map Generation for the Simple Location plugin. API keys are required for map display services.', 'simple-location' );
	}

	public static function default_map_provider() {
		$option = get_option( 'sloc_default_map_provider' );
		switch ( $option ) {
			case 'Google':
				$map = new Geo_Provider_Google();
				break;
			default:
				$map = new Geo_Provider_OSM();
		}

		return apply_filters( 'sloc_default_map_provider', $map );
	}

	public static function default_reverse_provider() {
		$option = get_option( 'sloc_default_reverse_provider' );
		switch ( $option ) {
			case 'Google':
				$map = new Geo_Provider_Google();
				break;
			default:
				$map = new Geo_Provider_OSM();
		}
		return apply_filters( 'sloc_default_reverse_provider', $map );
	}
}

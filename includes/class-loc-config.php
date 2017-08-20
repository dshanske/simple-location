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
			'sloc_bing_api', // option name
			array(
				'type' => 'string',
				'description' => 'Bing Maps API Key',
				'show_in_rest' => false,
				'default' => '',
			)
		);
		register_setting(
			'media',
			'sloc_mapbox_user',
			array(
				'type' => 'string',
				'description' => 'Mapbox User',
				'show_in_rest' => false,
				'default' => 'mapbox',
			)
		);
		register_setting(
			'media',
			'sloc_mapbox_style',
			array(
				'type' => 'string',
				'description' => 'Mapbox Style',
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
		register_setting(
			'media', // settings page
			'geo_public', // option name
			array(
				'type' => 'boolean',
				'description' => 'Default Setting for Geodata',
				'show_in_rest' => true,
				'default' => SLOC_PUBLIC,
				// WordPress Geodata defaults to public but this allows a global override for new posts
			)
		);
	}

	public static function admin_init() {
		add_settings_section(
			'sloc',
			__( 'Simple Location Map Settings', 'simple-location' ),
			array( 'Loc_Config', 'sloc_settings' ),
			'media'
		);
		add_settings_field(
			'sloc_default_map_provider', // id
			__( 'Default Map Provider', 'simple-location' ), // setting title
			array( 'Loc_Config', 'map_provider_callback' ), // display callback
			'media', // settings page
			'sloc', // settings section
			array( 'name' => 'sloc_default_map_provider' )
		);
		add_settings_field(
			'geo_public', // id
			__( 'Show Location By Default', 'simple-location' ), // setting title
			array( 'Loc_Config', 'checkbox_callback' ), // display callback
			'media', // settings page
			'sloc', // settings section
			array( 'name' => 'geo_public' )
		);
		add_settings_field(
			'googleapi', // id
			__( 'Google Maps API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'media', // settings page
			'sloc', // settings section
			array( 'name' => 'sloc_google_api' )
		);
		add_settings_field(
			'bingapi', // id
			__( 'Bing API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'media', // settings page
			'sloc', // settings section
			array( 'name' => 'sloc_bing_api' )
		);
		add_settings_field(
			'mapboxapi', // id
			__( 'Mapbox API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'media', // settings page
			'sloc', // settings section
			array( 'name' => 'sloc_mapbox_api' )
		);
		add_settings_field(
			'mapboxuser', // id
			__( 'Mapbox User', 'simple-location' ),
			array( 'Loc_Config', 'string_callback' ),
			'media',
			'sloc',
			array( 'name' => 'sloc_mapbox_user' )
		);
		add_settings_field(
			'mapboxstyle', // id
			__( 'Mapbox Style', 'simple-location' ),
			array( 'Loc_Config', 'style_callback' ),
			'media',
			'sloc',
			array( 'name' => 'sloc_mapbox_style' )
		);
		add_settings_field(
			'width', // id
			__( 'Default Map Width', 'simple-location' ), // setting title
			array( 'Loc_Config', 'number_callback' ), // display callback
			'media', // settings page
			'sloc', // settings section
			array( 'name' => 'sloc_width' )
		);
		add_settings_field(
			'height', // id
			__( 'Default Map Height', 'simple-location' ), // setting title
			array( 'Loc_Config', 'number_callback' ), // display callback
			'media', // settings page
			'sloc', // settings section
			array( 'name' => 'sloc_height' )
		);
		add_settings_field(
			'zoom', // id
			__( 'Default Map Zoom', 'simple-location' ), // setting title
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
		echo '<option value="Bing" '  . selected( $text, 'Bing' ) .  '>' . __( 'Bing Maps', 'simple-location' ) . '</option>';
		echo '</select><br /><br />';
	}

	public static function style_callback ( array $args ) {
		$name = $args['name'];
		$provider = self::default_map_provider();
		$styles = $provider->get_styles();
		if ( is_wp_error( $styles ) ) {
			echo $styles->get_error_message();
			return;
		}
		$text = get_option( $name );

		echo '<select name="' . $name . '">';
		foreach( $styles as $key => $value ) {
			echo '<option value="' . $key . '" '  . selected( $text, $key ) .'>' . $value . '</option>';
		}
		echo '</select><br /><br />';
	}

	public static function sloc_settings() {
		_e( 'Default Settings for Map Generation for the Simple Location plugin. API keys are required for map display services.', 'simple-location' );
	}

	public static function default_map_provider( $args = array() ) {
		$option = get_option( 'sloc_default_map_provider' );
		switch ( $option ) {
			case 'Google':
				$map = new Geo_Provider_Google( $args );
				break;
			case 'Bing':
				$map = new Geo_Provider_Bing( $args );
				break;
			default:
				$map = new Geo_Provider_OSM( $args );
		}

		return apply_filters( 'sloc_default_map_provider', $map, $args );
	}

	public static function default_reverse_provider( $args = array() ) {
		$option = get_option( 'sloc_default_reverse_provider' );
		switch ( $option ) {
			case 'Google':
				$map = new Geo_Provider_Google( $args );
				break;
			default:
				$map = new Geo_Provider_OSM( $args );
		}
		return apply_filters( 'sloc_default_reverse_provider', $map, $args );
	}
}

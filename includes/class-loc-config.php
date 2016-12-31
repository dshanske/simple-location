<?php

// On Activation, add terms
register_activation_hook( __FILE__, 'sloc_defaults' );

function sloc_defaults() {
	if ( ! get_option( 'sloc_options' ) ) {
		$option = array(
		'height' => '350',
		'width' => '350',
		'zoom' => '14',
		);
		update_option( 'sloc_options', $option );
	}
}

add_filter( 'admin_init', array( 'loc_config', 'admin_init' ), 10, 4 );

class loc_config {

	/**
	 * Add Settings to the Discussions Page
	 */
	public static function admin_init() {
		register_setting(
			'writing', // settings page
			'sloc_google_api', // option name
			array( 
				'type' => 'string',
				'description' => 'Google Maps API Key',
				'show_in_rest' => false,
				'default' => ''
			)
		);
		register_setting(
			'writing', // settings page
			'sloc_mapbox_api', // option name
			array( 
				'type' => 'string',
				'description' => 'Mapbox Static Maps API Key',
				'show_in_rest' => false,
				'default' => ''
			)
		);
		register_setting(
			'writing', // settings page
			'sloc_height', // option name
			array( 
				'type' => 'number',
				'description' => 'Simple Location Map Height',
				'show_in_rest' => true,
				'default' => 350
			)
		);
		register_setting(
			'writing', // settings page
			'sloc_width', // option name
			array( 
				'type' => 'number',
				'description' => 'Simple Location Map Width',
				'show_in_rest' => true,
				'default' => 350
			)
		);
		register_setting(
			'writing', // settings page
			'sloc_zoom', // option name
			array( 
				'type' => 'number',
				'description' => 'Simple Location Map Zoom',
				'show_in_rest' => true,
				'default' => 14
			)
		);
		add_settings_field(
			'googleapi', // id
			'Google Maps API Key', // setting title
			array( 'loc_config', 'string_callback' ), // display callback
			'writing', // settings page
			'default', // settings section
			array( 'name' => 'sloc_google_api' )
		);
		add_settings_field(
			'mapboxapi', // id
			'Mapbox API Key', // setting title
			array( 'loc_config', 'string_callback' ), // display callback
			'writing', // settings page
			'default', // settings section
			array( 'name' => 'sloc_mapbox_api' )
		);
		add_settings_field(
			'height', // id
			'Map Height', // setting title
			array( 'loc_config', 'number_callback' ), // display callback
			'writing', // settings page
			'default', // settings section
			array( 'name' => 'sloc_height' )
		);
		add_settings_field(
			'width', // id
			'Map Width', // setting title
			array( 'loc_config', 'number_callback' ), // display callback
			'writing', // settings page
			'default', // settings section
			array( 'name' => 'sloc_width' )
		);
		add_settings_field(
			'zoom', // id
			'Map Zoom', // setting title
			array( 'loc_config', 'number_callback' ), // display callback
			'writing', // settings page
			'default', // settings section
			array( 'name' => 'sloc_zoom' )
		);
	}

	public static function checkbox_callback(array $args) {
		$name = $args['name'];
		$checked = get_option( $name);
		echo "<input name='" . $name . "' type='hidden' value='0' />";
		echo "<input name='" . $name . "' type='checkbox' value='1' " . checked( 1, $checked, false ) . ' /> ';
	}

	public static function number_callback(array $args) {
		$name = $args['name'];
		$text = get_option( $name );
		echo "<input name='" . $name . "' type='number' value='" . $text . "' /> ";
	}

	public static function string_callback(array $args) {
		$name = $args['name'];
		$text = get_option( $name );
		echo "<input name='" . $name . "' type='string' value='" . $text . "' /> ";
	}
}

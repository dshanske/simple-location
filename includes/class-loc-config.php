<?php

add_filter( 'admin_init', array( 'Loc_Config', 'admin_init' ), 10 );
add_filter( 'init', array( 'Loc_Config', 'init' ), 10 );
add_action( 'admin_menu', array( 'Loc_Config', 'admin_menu' ), 10 );

class Loc_Config {

	/**
	 * Add Settings to the Discussions Page
	 */
	public static function init() {
		register_setting(
			'simloc', // settings page
			'sloc_default_map_provider', // option name
			array(
				'type'         => 'string',
				'description'  => 'Default Map Provider',
				'show_in_rest' => false,
				'default'      => 'OSM',
			)
		);
		register_setting(
			'simloc', // settings page
			'sloc_default_reverse_provider', // option name
			array(
				'type'         => 'string',
				'description'  => 'Default Map Provider',
				'show_in_rest' => false,
				'default'      => 'OSM',
			)
		);
		register_setting(
			'simloc', // settings page
			'sloc_default_weather_provider', // option name
			array(
				'type'         => 'string',
				'description'  => 'Default Weather Provider',
				'show_in_rest' => false,
				'default'      => 'OpenWeatherMap',
			)
		);
		register_setting(
			'simloc', // settings page
			'sloc_google_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'Google Maps API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
		register_setting(
			'simloc', // settings page
			'sloc_mapbox_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'Mapbox Static Maps API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
		register_setting(
			'simloc', // settings page
			'sloc_bing_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'Bing Maps API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
		register_setting(
			'simloc', // settings page
			'sloc_openweathermap_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'OpenWeatherMap API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
		register_setting(
			'simloc',
			'sloc_openweathermap_id',
			array(
				'type'         => 'string',
				'description'  => 'OpenWeatherMap Station ID',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
		register_setting(
			'simloc',
			'sloc_mapbox_user',
			array(
				'type'         => 'string',
				'description'  => 'Mapbox User',
				'show_in_rest' => false,
				'default'      => 'mapbox',
			)
		);
		register_setting(
			'simloc',
			'sloc_mapbox_style',
			array(
				'type'         => 'string',
				'description'  => 'Mapbox Style',
				'show_in_rest' => false,
				'default'      => 'streets-v10',
			)
		);
		register_setting(
			'simloc',
			'sloc_bing_style',
			array(
				'type'         => 'string',
				'description'  => 'Bing Map Style',
				'show_in_rest' => false,
				'default'      => 'CanvasLight',
			)
		);
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

		register_setting(
			'simloc', // settings page
			'sloc_height', // option name
			array(
				'type'         => 'number',
				'description'  => 'Simple Location Map Height',
				'show_in_rest' => true,
				'default'      => 350,
			)
		);
		register_setting(
			'simloc', // settings page
			'sloc_width', // option name
			array(
				'type'         => 'number',
				'description'  => 'Simple Location Map Width',
				'show_in_rest' => true,
				'default'      => 350,
			)
		);
		register_setting(
			'simloc', // settings page
			'sloc_zoom', // option name
			array(
				'type'         => 'number',
				'description'  => 'Simple Location Map Zoom',
				'show_in_rest' => true,
				'default'      => 14,
			)
		);
		register_setting(
			'simloc', // settings page
			'geo_public', // option name
			array(
				'type'         => 'boolean',
				'description'  => 'Default Setting for Geodata',
				'show_in_rest' => true,
				'default'      => SLOC_PUBLIC,
				// WordPress Geodata defaults to public but this allows a global override for new posts
			)
		);
		register_setting(
			'simloc', // settings page
			'sloc_last_report', // option name
			array(
				'type'         => 'boolean',
				'description'  => 'Update Authors Last Reported Location on New Post',
				'show_in_rest' => true,
				'default'      => true,
				// If this is true then each time a post is made with location properties it will update the user location
			)
		);

		register_setting(
			'simloc', // settings page
			'sloc_measurements', // option name
			array(
				'type'         => 'string',
				'description'  => 'Units to Display',
				'show_in_rest' => true,
				'default'      => self::temp_unit_default(),
			)
		);
	}

	public static function temp_unit_default() {
		// I cannot foresee every need for imperial but can cover US
		if ( 'en_US' === get_locale() ) {
			return 'imperial';
		}
		return 'metric';
	}

	public static function admin_menu() {
		// If the IndieWeb Plugin is installed use its menu.
		if ( class_exists( 'IndieWeb_Plugin' ) ) {
			add_submenu_page(
				'indieweb',
				__( 'Simple Location', 'simple-location' ), // page title
				__( 'Location', 'simple-location' ), // menu title
				'manage_options', // access capability
				'simloc',
				array( 'Loc_Config', 'simloc_options' )
			);
		} else {
			add_options_page(
				'',
				'Simple Location',
				'manage_options',
				'simloc',
				array( 'Loc_Config', 'simloc_options' )
			);
		}
	}

	public static function simloc_options() {
?>
		<div class="wrap">
			<h2><?php _e( 'Simple Location', 'simple-location' ); ?> </h2>
		<p>
			<?php esc_html_e( 'API Keys and Settings for Simple Location', 'simple-location' ); ?>
		</p><hr />
		<form method="post" action="options.php">
			<?php
				settings_fields( 'simloc' );
				do_settings_sections( 'simloc' );
				submit_button();
			?>
		</form>
		</div>
<?php
	}

	public static function admin_init() {
		add_settings_section(
			'sloc_map',
			__( 'Map Settings', 'simple-location' ),
			array( 'Loc_Config', 'sloc_map_settings' ),
			'simloc'
		);
		add_settings_field(
			'sloc_default_map_provider', // id
			__( 'Default Map Provider', 'simple-location' ), // setting title
			array( 'Loc_Config', 'map_provider_callback' ), // display callback
			'simloc', // settings page
			'sloc_map', // settings section
			array(
				'label_for' => 'sloc_default_map_provider',
			)
		);
		add_settings_field(
			'geo_public', // id
			__( 'Show Location By Default', 'simple-location' ), // setting title
			array( 'Loc_Config', 'checkbox_callback' ), // display callback
			'simloc', // settings page
			'sloc_map', // settings section
			array(
				'label_for' => 'geo_public',
			)
		);
		add_settings_field(
			'sloc_last_report', // id
			__( 'Update Author Last Reported Location on New Post', 'simple-location' ), // setting title
			array( 'Loc_Config', 'checkbox_callback' ), // display callback
			'simloc', // settings page
			'sloc_map', // settings section
			array(
				'label_for' => 'sloc_last_report',
			)
		);
		add_settings_field(
			'width', // id
			__( 'Default Map Width', 'simple-location' ), // setting title
			array( 'Loc_Config', 'number_callback' ), // display callback
			'simloc', // settings page
			'sloc_map', // settings section
			array(
				'label_for' => 'sloc_width',
			)
		);
		add_settings_field(
			'height', // id
			__( 'Default Map Height', 'simple-location' ), // setting title
			array( 'Loc_Config', 'number_callback' ), // display callback
			'simloc', // settings page
			'sloc_map', // settings section
			array(
				'label_for' => 'sloc_height',
			)
		);
		add_settings_field(
			'zoom', // id
			__( 'Default Map Zoom', 'simple-location' ), // setting title
			array( 'Loc_Config', 'number_callback' ), // display callback
			'simloc', // settings page
			'sloc_map', // settings section
			array(
				'label_for' => 'sloc_zoom',
			)
		);

		$map_provider     = get_option( 'sloc_default_map_provider' );
		$weather_provider = get_option( 'sloc_default_weather_provider' );

		add_settings_field(
			'googleapi', // id
			__( 'Google Maps API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'simloc', // settings page
			'sloc_map', // settings section
			array(
				'label_for' => 'sloc_google_api',
				'class'     => ( 'Google' === $map_provider ) ? '' : 'hidden',
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
				'provider'  => new Geo_Provider_Google(),
				'class'     => ( 'Google' === $map_provider ) ? '' : 'hidden',
			)
		);
		add_settings_field(
			'bingapi', // id
			__( 'Bing API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'simloc', // settings page
			'sloc_map', // settings section
			array(
				'label_for' => 'sloc_bing_api',
				'class'     => ( 'Bing' === $map_provider ) ? '' : 'hidden',

			)
		);
		add_settings_field(
			'bingstyle', // id
			__( 'Bing Style', 'simple-location' ),
			array( 'Loc_Config', 'style_callback' ),
			'simloc',
			'sloc_map',
			array(
				'label_for' => 'sloc_bing_style',
				'provider'  => new Geo_Provider_Bing(),
				'class'     => ( 'Bing' === $map_provider ) ? '' : 'hidden',
			)
		);
		add_settings_field(
			'mapboxapi', // id
			__( 'Mapbox API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'simloc', // settings page
			'sloc_map', // settings section
			array(
				'label_for' => 'sloc_mapbox_api',
				'class'     => ( 'OSM' === $map_provider ) ? '' : 'hidden',

			)
		);
		add_settings_field(
			'mapboxuser', // id
			__( 'Mapbox User', 'simple-location' ),
			array( 'Loc_Config', 'string_callback' ),
			'simloc',
			'sloc_map',
			array(
				'label_for' => 'sloc_mapbox_user',
				'class'     => ( 'OSM' === $map_provider ) ? '' : 'hidden',

			)
		);
		add_settings_field(
			'mapboxstyle', // id
			__( 'Mapbox Style', 'simple-location' ),
			array( 'Loc_Config', 'style_callback' ),
			'simloc',
			'sloc_map',
			array(
				'label_for' => 'sloc_mapbox_style',
				'provider'  => new Geo_Provider_OSM(),
				'class'     => ( 'OSM' === $map_provider ) ? '' : 'hidden',

			)
		);
		add_settings_section(
			'sloc_weather',
			__( 'Weather Settings', 'simple-location' ),
			array( 'Loc_Config', 'sloc_weather_settings' ),
			'simloc'
		);
		add_settings_field(
			'sloc_measurements', // id
			__( 'Unit of Measure', 'simple-location' ), // setting title
			array( 'Loc_Config', 'measure_callback' ), // display callback
			'simloc', // settings page
			'sloc_weather', // settings section
			array(
				'label_for' => 'sloc_measurements',
			)
		);
		add_settings_field(
			'sloc_default_weather_provider', // id
			__( 'Default Weather Provider', 'simple-location' ), // setting title
			array( 'Loc_Config', 'weather_provider_callback' ), // display callback
			'simloc', // settings page
			'sloc_weather', // settings section
			array(
				'label_for' => 'sloc_default_weather_provider',
			)
		);
		add_settings_field(
			'openweatherapi', // id
			__( 'OpenWeatherMap API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'simloc', // settings page
			'sloc_weather', // settings section
			array(
				'label_for' => 'sloc_openweathermap_api',
				'class'     => ( 'OpenWeatherMap' === $weather_provider ) ? '' : 'hidden',

			)
		);
		add_settings_field(
			'openweatherid', // id
			__( 'OpenWeatherMap Station ID', 'simple-location' ),
			array( 'Loc_Config', 'string_callback' ),
			'simloc',
			'sloc_weather',
			array(
				'label_for' => 'sloc_openweathermap_id',
				'class'     => ( 'OpenWeatherMap' === $weather_provider ) ? '' : 'hidden',

			)
		);

	}

	public static function checkbox_callback( array $args ) {
		$name    = $args['label_for'];
		$checked = get_option( $name );
		printf( '<input name="%1s" type="hidden" value="0" />', $name );
		printf( '<input name="%1s" type="checkbox" value="1" %2s />', $name, checked( 1, $checked, false ) );
	}

	public static function number_callback( array $args ) {
		$name = $args['label_for'];
		printf( '<input name="%1s" type="number" min="0" step="1" size="4" class="small-text" value="%2s" />', $name, get_option( $name ) );
	}

	public static function string_callback( array $args ) {
		$name = $args['label_for'];
		printf( '<input name="%1s" size="50" class="regular-text" type="string" value="%2s" />', $name, get_option( $name ) );
	}

	public static function map_provider_callback( array $args ) {
		$name = $args['label_for'];
		$text = get_option( $name );
		echo '<select name="' . $name . '">';
		echo '<option value="OSM" ' . selected( $text, 'OSM' ) . '>' . __( 'OpenStreetMap/MapBox', 'simple-location' ) . '</option>';
		echo '<option value="Google" ' . selected( $text, 'Google' ) . '>' . __( 'Google Maps', 'simple-location' ) . '</option>';
		echo '<option value="Bing" ' . selected( $text, 'Bing' ) . '>' . __( 'Bing Maps', 'simple-location' ) . '</option>';
		echo '</select><br /><br />';
	}


	public static function weather_provider_callback( array $args ) {
		$name = $args['label_for'];
		$text = get_option( $name );
		echo '<select name="' . $name . '">';
		echo '<option value="OpenWeatherMap" ' . selected( $text, 'OpenWeatherMap' ) . '>' . __( 'OpenWeatherMap', 'simple-location' ) . '</option>';
		echo '</select><br /><br />';
	}

	public static function measure_callback( array $args ) {
		$name = $args['label_for'];
		$text = get_option( $name );
		echo '<select name="' . $name . '">';
		echo '<option value="metric" ' . selected( $text, 'metric' ) . '>' . __( 'Metric', 'simple-location' ) . '</option>';
		echo '<option value="imperial" ' . selected( $text, 'imperial' ) . '>' . __( 'Imperial', 'simple-location' ) . '</option>';
		echo '</select><br /><br />';
	}


	public static function style_callback( array $args ) {
		$name     = $args['label_for'];
		$provider = $args['provider'];
		$styles   = $provider->get_styles();
		if ( is_wp_error( $styles ) ) {
			echo $styles->get_error_message();
			return;
		}
		$text = get_option( $name );

		echo '<select name="' . $name . '">';
		foreach ( $styles as $key => $value ) {
			echo '<option value="' . $key . '" ' . selected( $text, $key ) . '>' . $value . '</option>';
		}
		echo '</select><br /><br />';
	}

	public static function sloc_map_settings() {
		_e( 'API keys are required for map display services.', 'simple-location' );
	}

	public static function sloc_weather_settings() {
		_e( 'API keys are required for most weather services.', 'simple-location' );
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

	public static function default_weather_provider( $args = array() ) {
		$option = get_option( 'sloc_default_weather_provider' );
		switch ( $option ) {
			default:
				$weather = new Weather_Provider_OpenWeatherMap( $args );
		}
		return apply_filters( 'sloc_default_weather_provider', $weather, $args );
	}


}

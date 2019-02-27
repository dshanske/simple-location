<?php

add_filter( 'admin_init', array( 'Loc_Config', 'admin_init' ), 10 );
add_filter( 'plugins_loaded', array( 'Loc_Config', 'init' ), 11 );
add_action( 'admin_menu', array( 'Loc_Config', 'admin_menu' ), 10 );

class Loc_Config {

	private static $maps     = array(); // Store Map Providers
	private static $geo      = array(); // Reverse Lookup Provider
	private static $location = array(); // Geolocation Provider
	private static $weather  = array(); // Weather Provider
	/**
	 * Add Settings to the Discussions Page
	 */
	public static function init() {
		register_setting(
			'simloc', // settings page
			'sloc_map_provider', // option name
			array(
				'type'         => 'string',
				'description'  => 'Map Provider',
				'show_in_rest' => false,
				'default'      => 'wikimedia',
			)
		);
		register_setting(
			'simloc', // settings page
			'sloc_geo_provider', // option name
			array(
				'type'         => 'string',
				'description'  => 'Geo Lookup Provider',
				'show_in_rest' => false,
				'default'      => 'nominatim',
			)
		);
		register_setting(
			'simloc', // settings page
			'sloc_geolocation_provider', // option name
			array(
				'type'         => 'string',
				'description'  => 'Geolocation Provider',
				'show_in_rest' => false,
				'default'      => 'HTML5',
			)
		);
		register_setting(
			'simloc', // settings page
			'sloc_weather_provider', // option name
			array(
				'type'         => 'string',
				'description'  => 'Weather Provider',
				'show_in_rest' => false,
				'default'      => 'openweathermap',
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
			'sloc_here_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'HERE Maps API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
		register_setting(
			'simloc', // settings page
			'sloc_here_appid', // option name
			array(
				'type'         => 'string',
				'description'  => 'Here Maps APP ID',
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
			'sloc_mapquest_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'Mapquest API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
		register_setting(
			'simloc', // settings page
			'sloc_darksky_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'DarkSky API Key',
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
			'simloc',
			'sloc_mapquest_style',
			array(
				'type'         => 'string',
				'description'  => 'Mapquest Map Style',
				'show_in_rest' => false,
				'default'      => 'map',
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
			'sloc_altitude', // option name
			array(
				'type'         => 'number',
				'description'  => 'Simple Location Height After Which Altitude would be displayed(in meters)',
				'show_in_rest' => true,
				'default'      => 500,
			)
		);

		register_setting(
			'simloc', // settings page
			'geo_public', // option name
			array(
				'type'         => 'boolean',
				'description'  => 'Default Setting for Geodata',
				'show_in_rest' => true,
				'default'      => 1,
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
				'default'      => self::measurement_default(),
			)
		);
	}

	public static function register_provider( $object ) {
		if ( ! $object instanceof Sloc_Provider ) {
			return false;
		}
		if ( $object instanceof Geo_Provider ) {
			static::$geo[ $object->get_slug() ] = $object;
		} elseif ( $object instanceof Map_Provider ) {
			static::$maps[ $object->get_slug() ] = $object;
		} elseif ( $object instanceof Location_Provider ) {
			static::$location[ $object->get_slug() ] = $object;
		} elseif ( $object instanceof Weather_Provider ) {
			static::$weather[ $object->get_slug() ] = $object;
		}
		return true;
	}

	public static function measurement_default() {
		// I cannot foresee every need for imperial but can cover US
		if ( 'en_US' === get_locale() ) {
			return 'imperial';
		}
		return 'si';
	}

	public static function admin_menu() {
		// If the IndieWeb Plugin is installed use its menu.
		if ( class_exists( 'IndieWeb_Plugin' ) ) {
			$hook = add_submenu_page(
				'indieweb',
				__( 'Simple Location', 'simple-location' ), // page title
				__( 'Location', 'simple-location' ), // menu title
				'manage_options', // access capability
				'simloc',
				array( 'Loc_Config', 'simloc_options' )
			);
		} else {
			$hook = add_options_page(
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
			<h2><?php esc_html_e( 'Simple Location', 'simple-location' ); ?> </h2>
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
		$map_provider     = get_option( 'sloc_map_provider' );
		$weather_provider = get_option( 'sloc_weather_provider' );
		$geo_provider     = get_option( 'sloc_geo_provider' );

		add_settings_section(
			'sloc_general',
			__( 'General Settings', 'simple-location' ),
			array( 'Loc_Config', 'sloc_general_settings' ),
			'simloc'
		);
		add_settings_field(
			'geo_public', // id
			__( 'Default Display for Location', 'simple-location' ), // setting title
			array( 'Loc_Config', 'provider_callback' ), // display callback
			'simloc', // settings page
			'sloc_general', // settings section
			array(
				'label_for' => 'geo_public',
				'providers' => self::geo_public(),
			)
		);
		add_settings_field(
			'sloc_last_report', // id
			__( 'Update Author Last Reported Location on New Post', 'simple-location' ), // setting title
			array( 'Loc_Config', 'checkbox_callback' ), // display callback
			'simloc', // settings page
			'sloc_general', // settings section
			array(
				'label_for' => 'sloc_last_report',
			)
		);
		add_settings_field(
			'sloc_measurements', // id
			__( 'Unit of Measure', 'simple-location' ), // setting title
			array( 'Loc_Config', 'measure_callback' ), // display callback
			'simloc', // settings page
			'sloc_general', // settings section
			array(
				'label_for' => 'sloc_measurements',
			)
		);
		add_settings_section(
			'sloc_providers',
			__( 'Provider Settings', 'simple-location' ),
			array( 'Loc_Config', 'sloc_provider_settings' ),
			'simloc'
		);

		add_settings_field(
			'sloc_map_provider', // id
			__( 'Map Provider', 'simple-location' ), // setting title
			array( 'Loc_Config', 'provider_callback' ), // display callback
			'simloc', // settings page
			'sloc_providers', // settings section
			array(
				'label_for'   => 'sloc_map_provider',
				'description' => __( 'Provides Static Map Images', 'simple-location' ),
				'providers'   => self::map_providers(),
			)
		);
		add_settings_field(
			'sloc_geo_provider', // id
			__( 'Geo Provider', 'simple-location' ), // setting title
			array( 'Loc_Config', 'provider_callback' ), // display callback
			'simloc', // settings page
			'sloc_providers', // settings section
			array(
				'label_for'   => 'sloc_geo_provider',
				'description' => __( 'Looking up an address from coordinates or vice versa', 'simple-location' ),
				'providers'   => self::geo_providers(),
			)
		);
		add_settings_field(
			'sloc_geolocation_provider', // id
			__( 'Geolocation Provider', 'simple-location' ), // setting title
			array( 'Loc_Config', 'provider_callback' ), // display callback
			'simloc', // settings page
			'sloc_providers', // settings section
			array(
				'label_for'   => 'sloc_geolocation_provider',
				'description' => __( 'By default this uses your browser to lookup your location but you can alternatively tap into a service to get your current location, perhaps from your phone', 'simple-location' ),
				'providers'   => self::geolocation_providers(),
			)
		);
		add_settings_field(
			'sloc_weather_provider', // id
			__( 'Weather Provider', 'simple-location' ), // setting title
			array( 'Loc_Config', 'provider_callback' ), // display callback
			'simloc', // settings page
			'sloc_providers', // settings section
			array(
				'label_for'   => 'sloc_weather_provider',
				'description' => __( 'Retrieves Weather Data about a Location', 'simple-location' ),
				'providers'   => self::weather_providers(),
			)
		);

		add_settings_section(
			'sloc_map',
			__( 'Map Settings', 'simple-location' ),
			array( 'Loc_Config', 'sloc_map_settings' ),
			'simloc'
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

		add_settings_field(
			'altitude', // id
			__( 'Altitude will Display if Above This Height(in meters)', 'simple-location' ), // setting title
			array( 'Loc_Config', 'number_callback' ), // display callback
			'simloc', // settings page
			'sloc_map', // settings section
			array(
				'label_for' => 'sloc_altitude',
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
				'class'     => ( 'google' === $map_provider ) ? '' : 'hidden',
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
				'provider'  => new Map_Provider_Bing(),
				'class'     => ( 'bing' === $map_provider ) ? '' : 'hidden',
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
				'class'     => ( 'mapbox' === $map_provider ) ? '' : 'hidden',

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
				'provider'  => new Map_Provider_Mapbox(),
				'class'     => ( 'mapbox' === $map_provider ) ? '' : 'hidden',

			)
		);

		add_settings_field(
			'mapqueststyle', // id
			__( 'MapQuest Style', 'simple-location' ),
			array( 'Loc_Config', 'style_callback' ),
			'simloc',
			'sloc_map',
			array(
				'label_for' => 'sloc_mapquest_style',
				'provider'  => new Map_Provider_Mapquest(),
				'class'     => ( 'mapquest' === $map_provider ) ? '' : 'hidden',
			)
		);

		add_settings_section(
			'sloc_weather',
			__( 'Weather Settings', 'simple-location' ),
			array( 'Loc_Config', 'sloc_weather_settings' ),
			'simloc'
		);
		add_settings_section(
			'sloc_providers',
			__( 'Providers', 'simple-location' ),
			array( 'Loc_Config', 'sloc_provider_settings' ),
			'simloc'
		);

		add_settings_section(
			'sloc_api',
			__( 'API Keys', 'simple-location' ),
			array( 'Loc_Config', 'sloc_api_settings' ),
			'simloc'
		);

		add_settings_field(
			'hereapi', // id
			__( 'HERE API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'simloc', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_here_api',
				'class'     => ( 'here' === $map_provider ) ? '' : 'hidden',
			)
		);
		add_settings_field(
			'hereapp', // id
			__( 'HERE Application ID', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'simloc', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_here_appid',
				'class'     => ( 'here' === $map_provider ) ? '' : 'hidden',
			)
		);

		add_settings_field(
			'mapquestapi', // id
			__( 'MapQuest API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'simloc', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_mapquest_api',
				'class'     => ( 'mapquest' === $map_provider || 'mapquest' === $geo_provider ) ? '' : 'hidden',
			)
		);
		add_settings_field(
			'googleapi', // id
			__( 'Google Maps API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'simloc', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_google_api',
				'class'     => ( 'google' === $map_provider || 'google' === $geo_provider ) ? '' : 'hidden',
			)
		);
		add_settings_field(
			'bingapi', // id
			__( 'Bing API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'simloc', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_bing_api',
				'class'     => ( 'bing' === $map_provider || 'bing' === $geo_provider ) ? '' : 'hidden',

			)
		);

		add_settings_field(
			'mapboxapi', // id
			__( 'Mapbox API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'simloc', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_mapbox_api',
				'class'     => ( 'mapbox' === $map_provider ) ? '' : 'hidden',

			)
		);
		add_settings_field(
			'sloc_darksky_api', // id
			__( 'Dark Sky API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'simloc', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_darksky_api',
				'class'     => ( 'darksky' === $weather_provider ) ? '' : 'hidden',

			)
		);
		add_settings_field(
			'openweatherapi', // id
			__( 'OpenWeatherMap API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'simloc', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_openweathermap_api',
				'class'     => ( 'openweathermap' === $weather_provider ) ? '' : 'hidden',

			)
		);

	}

	public static function checkbox_callback( array $args ) {
		$name    = $args['label_for'];
		$checked = get_option( $name );
		printf( '<input name="%1s" type="hidden" value="0" />', $name ); // phpcs:ignore
		printf( '<input name="%1s" type="checkbox" value="1" %2s />', $name, checked( 1, $checked, false ) ); // phpcs:ignore
	}

	public static function number_callback( array $args ) {
		$name = $args['label_for'];
		printf( '<input name="%1s" type="number" min="0" step="1" size="4" class="small-text" value="%2s" />', $name, get_option( $name ) ); // phpcs:ignore
	}

	public static function string_callback( array $args ) {
		$name = $args['label_for'];
		if ( ! isset( $args['type'] ) ) {
			$args['type'] = 'text';
		}
		printf( '<input name="%1s" size="50" autocomplete="off" class="regular-text" type="%2s" value="%3s" />', $name, esc_attR( $args['type'] ), get_option( $name ) ); // phpcs:ignore
	}

	public static function provider_callback( $args ) {
		$name        = $args['label_for'];
		$description = ifset( $args['description'], '' );
		$text        = get_option( $name );
		$providers   = $args['providers'];
		if ( count( $providers ) > 1 ) {
			printf( '<select name="%1$s">', esc_attr( $name ) );
			foreach ( $providers as $key => $value ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', $key, selected( $text, $key ), $value ); // phpcs:ignore
			}
			echo '</select>';
			echo '<p class="description">' . esc_html( $description ) . '</p>';
			echo '<br /><br />';
		} else {
			printf( '<input name="%1$s" type="radio" id="%1$s" value="%2$s" checked /><span>%3$s</span>', esc_attr( $name ), esc_attr( key( $providers ) ), esc_html( reset( $providers ) ) );
		}
	}

	public static function geo_public() {
		return WP_Geo_Data::geo_public();
	}

	public static function map_providers() {
		$return = array();
		foreach ( static::$maps as $map ) {
			$return[ $map->get_slug() ] = esc_html( $map->get_name() );
		}
		return $return;
	}

	public static function geo_providers() {
		$return = array();
		foreach ( static::$geo as $g ) {
			$return[ $g->get_slug() ] = esc_html( $g->get_name() );
		}
		return $return;
	}

	public static function geolocation_providers() {
		$return = array(
			'HTML5' => __( 'HTML5 Browser Geolocation (requires HTTPS)', 'simple-location' ),
		);
		foreach ( static::$location as $location ) {
			$return[ $location->get_slug() ] = esc_html( $location->get_name() );
		}
		return $return;
	}


	public static function weather_providers( $station = false ) {
		$return = array();
		foreach ( static::$weather as $weather ) {
			if ( ! $station ) {
				$return[ $weather->get_slug() ] = esc_html( $weather->get_name() );
			} elseif ( $weather->is_station() ) {
				$return[ $weather->get_slug() ] = esc_html( $weather->get_name() );
			}
		}
		return $return;
	}

	public static function measure_callback( array $args ) {
		$text = get_option( 'sloc_measurements' );
		echo '<select name="sloc_measurements">';
		printf( '<option value="si" %1$s >%2$s</option>', selected( $text, 'si', false ), __( 'International(SI)', 'simple-location' ) ); // phpcs:ignore
		printf( '<option value="imperial" %1$s >%2$s</option>', selected( $text, 'imperial', false ), __( 'Imperial', 'simple-location' ) ); // phpcs:ignore
		echo '</select><br /><br />';
	}


	public static function style_callback( array $args ) {
		$name     = $args['label_for'];
		$provider = $args['provider'];
		$styles   = $provider->get_styles();
		if ( is_wp_error( $styles ) ) {
			echo esc_html( $styles->get_error_message() );
			return;
		}
		$text = get_option( $name );

		echo '<select name="' . esc_attr( $name ) . '">';
		foreach ( $styles as $key => $value ) {
			echo '<option value="' . $key . '" ' . selected( $text, $key ) . '>' . esc_html( $value ) . '</option>'; // phpcs:ignore
		}
		echo '</select><br /><br />';
	}

	public static function sloc_general_settings() {
	}

	public static function sloc_provider_settings() {
		esc_html_e( 'Simple Location Depends on External Services', 'simple-location' );
	}


	public static function sloc_map_settings() {
	}

	public static function sloc_weather_settings() {
	}

	public static function sloc_api_settings() {
		esc_html_e( 'API keys are required for most services.', 'simple-location' );
	}



	public static function map_provider() {
		$option = get_option( 'sloc_map_provider' );
		if ( isset( static::$maps[ $option ] ) ) {
			return static::$maps[ $option ];
		}
		return null;
	}

	public static function geo_provider() {
		$option = get_option( 'sloc_geo_provider' );
		if ( isset( static::$geo[ $option ] ) ) {
			return static::$geo[ $option ];
		}
		return null;
	}

	public static function geolocation_provider( $provider = null ) {
		if ( ! $provider ) {
			$provider = get_option( 'sloc_geolocation_provider' );
		}
		if ( 'HTML5' === $provider ) {
			return null;
		}
		if ( isset( static::$location [ $provider ] ) ) {
			return static::$location[ $provider ];
		}
		return null;
	}

	public static function weather_provider( $provider = null ) {
		if ( ! $provider ) {
			$provider = get_option( 'sloc_weather_provider' );
		}
		if ( isset( static::$weather[ $provider ] ) ) {
			return static::$weather[ $provider ];
		}
		return null;
	}
}

function register_sloc_provider( $object ) {
	return Loc_Config::register_provider( $object );
}

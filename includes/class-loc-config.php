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
		self::register_general_settings();
		self::register_provider_settings();
		self::register_map_settings();
	}

	public static function register_general_settings() {
		register_setting(
			'simloc', // option group
			'geo_public', // option name
			array(
				'type'         => 'number',
				'description'  => 'Default Setting for Geodata',
				'show_in_rest' => true,
				'default'      => 1,
				// WordPress Geodata defaults to public but this allows a global override for new posts
			)
		);
		register_setting(
			'simloc', // option group
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
			'simloc', // option group
			'sloc_auto_micropub', // option name
			array(
				'type'         => 'boolean',
				'description'  => 'Add Location from Geolocation Provider Automatically on Micropub Posts',
				'show_in_rest' => true,
				'default'      => false,
				// If this is true then each time a post is made without location it will add it set automatically to private
			)
		);

		register_setting(
			'simloc', // option group
			'sloc_map_display', // option name
			array(
				'type'         => 'boolean',
				'description'  => 'Show Maps on Home and Archive Pages. Only on single if false',
				'show_in_rest' => true,
				'default'      => false,
			)
		);

		register_setting(
			'simloc', // option group
			'sloc_measurements', // option name
			array(
				'type'         => 'string',
				'description'  => 'Units to Display',
				'show_in_rest' => true,
				'default'      => self::measurement_default(),
			)
		);
	}

	public static function register_provider_settings() {
		register_setting(
			'sloc_providers', // option group
			'sloc_map_provider', // option name
			array(
				'type'         => 'string',
				'description'  => 'Map Provider',
				'show_in_rest' => false,
				'default'      => 'wikimedia',
			)
		);
		register_setting(
			'sloc_providers', // option group
			'sloc_geo_provider', // option name
			array(
				'type'         => 'string',
				'description'  => 'Geo Lookup Provider',
				'show_in_rest' => false,
				'default'      => 'nominatim',
			)
		);
		register_setting(
			'sloc_providers', // option group
			'sloc_geolocation_provider', // option name
			array(
				'type'         => 'string',
				'description'  => 'Geolocation Provider',
				'show_in_rest' => false,
				'default'      => 'HTML5',
			)
		);
		register_setting(
			'sloc_providers', // option group
			'sloc_weather_provider', // option name
			array(
				'type'         => 'string',
				'description'  => 'Weather Provider',
				'show_in_rest' => false,
				'default'      => 'openweathermap',
			)
		);
	}

	public static function register_map_settings() {
		global $content_width;
		if ( $content_width && $content_width > 1 ) {
			$width = $content_width;
		} else {
			$width = 1024;
		}
		register_setting(
			'simloc', // option group
			'sloc_width', // option name
			array(
				'type'         => 'number',
				'description'  => 'Simple Location Map Width',
				'show_in_rest' => true,
				'default'      => $width,
			)
		);
		register_setting(
			'simloc', // option group
			'sloc_aspect', // option name
			array(
				'type'         => 'number',
				'description'  => 'Simple Location Map Aspect Ratio',
				'show_in_rest' => true,
				'default'      => self::get_default_aspect_ratio(),
			)
		);
		register_setting(
			'simloc', // option group
			'sloc_zoom', // option name
			array(
				'type'         => 'number',
				'description'  => 'Simple Location Map Zoom',
				'show_in_rest' => true,
				'default'      => 14,
			)
		);
		register_setting(
			'simloc', // option group
			'sloc_altitude', // option name
			array(
				'type'         => 'number',
				'description'  => 'Simple Location Height After Which Altitude would be displayed(in meters)',
				'show_in_rest' => true,
				'default'      => 500,
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
		$posts = get_posts(
			array(
				'meta_query' => array(
					array(
						'key'     => 'geo_latitude',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => 'geo_address',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => 'geo_public',
						'compare' => 'EXISTS',
					),
				),
				'fields'     => 'ids',
			)
		);
		foreach ( $posts as $post_id ) {
			delete_post_meta( $post_id, 'geo_public' );
		}
		$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';

		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'Simple Location', 'simple-location' ); ?> </h2>
		<h2 class="nav-tab-wrapper">

		<?php self::tab_link( 'general', __( 'General', 'simple-location' ), $active_tab ); ?>
		<?php self::tab_link( 'providers', __( 'Providers', 'simple-location' ), $active_tab ); ?>
		<?php self::tab_link( 'zones', __( 'Zones', 'simple-location' ), $active_tab ); ?>
		<?php
		if ( WP_DEBUG ) {
			self::tab_link( 'debug', __( 'Debug', 'simple-location' ), $active_tab );
		}
		?>
		</h2>
		<hr />
		<?php
		if ( 'debug' === $active_tab ) {
			?>
			 
			<p> <?php esc_html_e( 'Test the raw responses from the lookup features. This feature only appears if you have WP_DEBUG enabled.', 'simple-location' ); ?> </p>
			<?php
			load_template( plugin_dir_path( __DIR__ ) . 'templates/geocode-form.php' );
			load_template( plugin_dir_path( __DIR__ ) . 'templates/weather-form.php' );
			?>
			</div>
			<?php
			return;
		}
		?>
		<form method="post" action="options.php">
			<?php
			switch ( $active_tab ) {
				case 'providers':
					settings_fields( 'sloc_providers' );
					do_settings_sections( 'sloc_providers' );
					break;
				case 'zones':
					settings_fields( 'sloc_zones' );
					do_settings_sections( 'sloc_zones' );
					break;
				default:
					settings_fields( 'simloc' );
					do_settings_sections( 'simloc' );
			}
				submit_button();
			?>
		</form>
		</div>
		<?php
	}

	public static function tab_link( $tab, $name, $active = 'general' ) {
		$url    = add_query_arg( 'tab', $tab, menu_page_url( 'simloc', false ) );
		$active = ( $active === $tab ) ? ' nav-tab-active' : '';
		printf( '<a href="%1$s" class="nav-tab%2$s">%3$s</a>', esc_url( $url ), esc_attr( $active ), esc_html( $name ) );
	}
	public static function admin_init() {
		$map_provider      = get_option( 'sloc_map_provider' );
		$weather_provider  = get_option( 'sloc_weather_provider' );
		$geo_provider      = get_option( 'sloc_geo_provider' );
		$location_provider = get_option( 'sloc_geolocation_provider' );

		add_settings_section(
			'sloc_general',
			__( 'General Settings', 'simple-location' ),
			array( 'Loc_Config', 'sloc_general_settings' ),
			'simloc'
		);
		add_settings_field(
			'geo_public', // id
			__( 'Default Visibility for Location', 'simple-location' ), // setting title
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
			__( 'When Making a New Post, update the Author with Posts Location', 'simple-location' ), // setting title
			array( 'Loc_Config', 'checkbox_callback' ), // display callback
			'simloc', // settings page
			'sloc_general', // settings section
			array(
				'label_for' => 'sloc_last_report',
			)
		);

		add_settings_field(
			'sloc_map_display', // id
			__( 'Show Maps on Home and Archive Pages Not Just Single Posts', 'simple-location' ), // setting title
			array( 'Loc_Config', 'checkbox_callback' ), // display callback
			'simloc', // settings page
			'sloc_general', // settings section
			array(
				'label_for' => 'sloc_map_display',
			)
		);

		add_settings_field(
			'sloc_auto_micropub', // id
			__( 'Automatically lookup location from supported geolocation provider for Micropub posts', 'simple-location' ), // setting title
			array( 'Loc_Config', 'checkbox_callback' ), // display callback
			'simloc', // settings page
			'sloc_general', // settings section
			array(
				'label_for' => 'sloc_auto_micropub',
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
			'sloc_providers'
		);

		add_settings_field(
			'sloc_map_provider', // id
			__( 'Map Provider', 'simple-location' ), // setting title
			array( 'Loc_Config', 'provider_callback' ), // display callback
			'sloc_providers', // option group
			'sloc_providers', // settings section
			array(
				'label_for'   => 'sloc_map_provider',
				'description' => __( 'Provides maps to display on posts and pages', 'simple-location' ),
				'providers'   => self::map_providers(),
			)
		);
		add_settings_field(
			'sloc_geo_provider', // id
			__( 'Geo Provider', 'simple-location' ), // setting title
			array( 'Loc_Config', 'provider_callback' ), // display callback
			'sloc_providers', // option group
			'sloc_providers', // settings section
			array(
				'label_for'   => 'sloc_geo_provider',
				'description' => __( 'Services that Look up an address from coordinates or vice versa', 'simple-location' ),
				'providers'   => self::geo_providers(),
			)
		);
		add_settings_field(
			'sloc_geolocation_provider', // id
			__( 'Geolocation Provider', 'simple-location' ), // setting title
			array( 'Loc_Config', 'provider_callback' ), // display callback
			'sloc_providers', // option group
			'sloc_providers', // settings section
			array(
				'label_for'   => 'sloc_geolocation_provider',
				'description' => __( 'Services that allow your site to figure out your location', 'simple-location' ),
				'providers'   => self::geolocation_providers(),
			)
		);
		add_settings_field(
			'sloc_weather_provider', // id
			__( 'Weather Provider', 'simple-location' ), // setting title
			array( 'Loc_Config', 'provider_callback' ), // display callback
			'sloc_providers', // option group
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
			'aspect', // id
			__( 'Default Map Aspect Ratio', 'simple-location' ), // setting title
			array( 'Loc_Config', 'values_callback' ), // display callback
			'simloc', // settings page
			'sloc_map', // settings section
			array(
				'label_for' => 'sloc_aspect',
				'list'      => self::get_default_aspect_ratio(),
			)
		);

		add_settings_field(
			'zoom', // id
			__( 'Default Map Zoom Level', 'simple-location' ), // setting title
			array( 'Loc_Config', 'values_callback' ), // display callback
			'simloc', // settings page
			'sloc_map', // settings section
			array(
				'label_for' => 'sloc_zoom',
				'list'      => self::get_zoom_levels(),
			)
		);

		add_settings_field(
			'altitude', // id
			__( 'Altitude will Display if Above This Height(in meters)', 'simple-location' ), // setting title
			array( 'Loc_Config', 'number_callback' ), // display callback
			'simloc', // settings page
			'sloc_general', // settings section
			array(
				'label_for' => 'sloc_altitude',
			)
		);

		add_settings_section(
			'sloc_providers',
			__( 'Providers', 'simple-location' ),
			array( 'Loc_Config', 'sloc_provider_settings' ),
			'sloc_providers'
		);

		add_settings_section(
			'sloc_api',
			__( 'API Keys', 'simple-location' ),
			array( 'Loc_Config', 'sloc_api_settings' ),
			'sloc_providers'
		);
	}

	public static function get_zoom_levels() {
		return array(
			'20' => __( 'Building', 'simple-location' ),
			'18' => __( 'Block', 'simple-location' ),
			'16' => __( 'Street', 'simple-location' ),
			'14' => __( 'Village', 'simple-location' ),
			'12' => __( 'Town', 'simple-location' ),
			'10' => __( 'Metropolitan Area', 'simple-location' ),
			'8'  => __( 'State', 'simple-location' ),
			'6'  => __( 'Large European Country', 'simple-location' ),
			'3'  => __( 'Largest Country', 'simple-location' ),
			'2'  => __( 'Subcontinent', 'simple-location' ),
		);
	}

	public static function get_default_aspect_ratio() {
		return apply_filters(
			'default_sloc_aspect_ratios',
			array(
				'1.77777777778' => __( 'Widescreen', 'simple-location' ),
				'1'             => __( 'Square', 'simple-location' ),
				'1.333333333'   => __( 'Medium Format', 'simple-location' ),
				'3'             => __( 'Panorama', 'simple-location' ),
				'12'            => __( 'Circle-Vision 360', 'simple-location' ),
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
			'HTML5' => __( 'Ask your Web Browser for Your Location(requires HTTPS)', 'simple-location' ),
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
		self::select_callback( $name, $text, $styles );
	}

	public static function values_callback( array $args ) {
		$name = $args['label_for'];
		$list = $args['list'];
		if ( is_wp_error( $list ) ) {
			echo esc_html( $list->get_error_message() );
			return;
		}
		$text = get_option( $name );
		self::select_callback( $name, $text, $list );
	}

	public static function select_callback( $name, $text, $values ) {
		echo '<select name="' . esc_attr( $name ) . '">';
		foreach ( $values as $key => $value ) {
			echo '<option value="' . $key . '" ' . selected( $text, $key ) . '>' . esc_html( $value ) . '</option>'; // phpcs:ignore
		}
		echo '</select><br /><br />';
	}

	public static function sloc_general_settings() {
	}

	public static function sloc_provider_settings() {
		?>
		<h4><?php esc_html_e( 'Simple Location Depends on Third Party Services', 'simple-location' ); ?></h4>
		<p><?php esc_html_e( 'Many of these services require you to sign up for an account and provide an API key below. Many have free and paid tiers. For this reason, the plugin offers multiple providers. At this moment, Nominatim, Wikimedia Maps, and the US National Weather Service can be used without API keys. Geonames requires an account, but is otherwise free to use. If you are uncertain of which to try, start with the defaults.', 'simple-location' ); ?></p>
		<?php
	}


	public static function sloc_map_settings() {
		esc_html_e( 'These settings dictate the display of maps', 'simple-location' );
	}

	public static function sloc_api_settings() {
		esc_html_e( 'In order for a specific service to work, you must add their API key below', 'simple-location' );
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

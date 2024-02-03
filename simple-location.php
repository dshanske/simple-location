<?php
/**
 * Plugin Name: Simple Location
 * Plugin URI: https://wordpress.org/plugins/simple-location/
 * Description: Adds Location to WordPress
 * Version: 5.0.23
 * Requires at least: 4.9
 * Requires PHP: 7.0
 * Requires CP: 1.4.3
 * Author: David Shanske
 * Author URI: https://david.shanske.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-location
 * Domain Path:  /languages
 *
 * @package Simple_Location
 */

add_action( 'plugins_loaded', array( 'Simple_Location_Plugin', 'init' ) );

// Activation and Deactivation Hooks.
register_activation_hook( __FILE__, array( 'Simple_Location_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Simple_Location_Plugin', 'deactivate' ) );
add_action( 'upgrader_process_complete', array( 'Simple_Location_Plugin', 'upgrader_process_complete' ), 10, 2 );

if ( ! defined( 'SLOC_PER_PAGE' ) ) {
			define( 'SLOC_PER_PAGE', 100 );
}


/**
 * Simple Location Base Class.
 *
 * Loads plugin.
 *
 * @since 1.0.0
 */
class Simple_Location_Plugin {
	/**
	 * Version number
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public static $version;

	/**
	 * Plugin Path
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public static $path;


	/**
	 * Plugin Activation Function.
	 *
	 * Triggered on Plugin Activation to add rewrite rules.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-geo-base.php';
		Geo_Base::rewrite();
		flush_rewrite_rules();
	}


	/**
	 * Plugin Deactivation Function.
	 *
	 * Triggered on Plugin Deactivation to flush rewrite rules.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Upgrade Trigger.
	 * Triggered on Plugin Upgrade to Flush Rewrite Rules
	 *
	 * @param WP_Upgrader $upgrade_object WP_Upgrader object.
	 * @param array       $options Array of bulk item update data.
	 */
	public static function upgrader_process_complete( $upgrade_object, $options ) {
		$current_plugin_path_name = plugin_basename( __FILE__ );
		if ( ( 'update' === $options['action'] ) && ( 'plugin' === $options['type'] ) ) {
			foreach ( $options['plugins'] as $each_plugin ) {
				if ( $each_plugin === $current_plugin_path_name ) {
					flush_rewrite_rules();
				}
			}
		}
	}


	/**
	 * Load files.
	 *
	 * Checks for the existence of and loads files.

	 * @param array  $files An array of filenames.
	 * @param string $dir The directory the files can be found in, relative to the current directory.
	 *
	 * @since 4.0.0
	 */
	public static function load( $files, $dir = 'includes/' ) {
		$dir = trailingslashit( $dir );
		if ( empty( $files ) ) {
			return;
		}
		$path = plugin_dir_path( __FILE__ ) . $dir;
		foreach ( $files as $file ) {
			if ( file_exists( $path . $file ) ) {
				require_once $path . $file;
			} else {
				error_log( $path . $file );
			}
		}
	}


	/**
	 * Filename to Classname Function.
	 *
	 * @param string $filename.
	 *
	 * @since 4.6.0
	 */
	public static function filename_to_classname( $filename ) {
		$class = str_replace( 'class-', '', $filename );
		$class = str_replace( '.php', '', $class );
		$class = ucwords( $class, '-' );
		$class = str_replace( '-', '_', $class );
		if ( class_exists( $class ) ) {
			return $class;
		}
		return false;
	}

	/**
	 * Load and register files.
	 *
	 * Checks for the existence of and loads files, then registers them as providers.

	 * @param array  $files An array of filenames.
	 * @param string $dir The directory the files can be found in, relative to the current directory.
	 *
	 * @since 4.6.0
	 */
	public static function register_providers( $files, $dir = 'includes/' ) {
		$dir = trailingslashit( $dir );
		if ( empty( $files ) ) {
			return;
		}
		$path = plugin_dir_path( __FILE__ ) . $dir;
		foreach ( $files as $file ) {
			if ( file_exists( $path . $file ) ) {
				require_once $path . $file;
				if ( str_contains( $file, 'provider' ) ) {
					$class = self::filename_to_classname( $file );
					if ( $class ) {
						register_sloc_provider( new $class() );
					} else {
						error_log( 'Cannot register ' . $class );
					}
				}
			} else {
				error_log( $path . $file );
			}
		}
	}




	/**
	 * Plugin Initializaton Function.
	 *
	 * Meant to be attached to plugins_loaded hook.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		self::$version = get_file_data( __FILE__, array( 'Version' => 'Version' ) )['Version'];
		self::$path    = plugin_dir_path( __FILE__ );

		load_plugin_textdomain( 'simple-location', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		// Load stylesheets.
		add_action( 'wp_enqueue_scripts', array( static::class, 'style_load' ) );

		// Settings Link.
		add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( static::class, 'settings_link' ) );

		// Add Privacy Policy.
		add_action( 'admin_init', array( static::class, 'privacy_declaration' ) );

		// Core Load Files.
		$core = array(
			'functions.php', // Global Functions.
			'class-geo-base.php', // Register Geo Base Functions that Modify Core Functionality
			'class-geo-data.php', // Register Geo Metadata Functions.
			'trait-weather-info.php', // Weather Information Such as Icons.
			'class-weather-data.php', // Register Weather Metadata Functions.
			'trait-geolocation-trait.php', // Geolocation Trait.
			'data-functions.php', // Global Data Functions to retrieve and store data.
			'class-sloc-media-metadata.php', // Media Metadata Functions.
			'class-post-venue.php', // Venue CPT.
			'class-location-taxonomy.php', // Venue Taxonomy.
			'class-sloc-provider.php', // Base Provider Class.
			'class-map-provider.php', // Map Provider Class.
			'class-geo-provider.php', // Geo Provider Class.
			'class-elevation-provider.php', // Elevation Provider Class.
			'class-venue-provider.php', // Venue Provider Class.
			'class-weather-provider.php', // Weather Provider Class.
			'class-location-provider.php', // Location Provider Class.
			'class-rest-geo.php', // REST endpoint for Geo.
			'class-loc-config.php', // Configuration and Settings Page.
			'class-timezone-result.php',
			'class-astronomical-calculator.php', // Calculates sunrise sunset etc.
			'class-location-plugins.php',
			'class-loc-timezone.php',
			'class-airport-location.php',
			'compat-functions.php',
		);

		// Load Core Files.
		self::load( $core );

		// Load Widgets.

		$widgets = array(
			'class-sloc-weather-widget.php', // Weather Widget.
			'class-sloc-station-widget.php', // Weather Station Widget.
			'class-sloc-airport-widget.php', // Airport Weather Widget.
			'class-sloc-lastseen-widget.php', // Last Location Seen Widget.
		);

		self::load( $widgets, 'includes/widgets/' );

		add_action( 'widgets_init', array( static::class, 'widgets_init' ) );

		$libraries = array(
			'class-sloc-polyline.php', // Polyline Encoding Library.
		);
		self::load( $libraries, 'lib/' );

		// Load API Traits. These are common code for the providers that offer different types of data.
		$traits = array(
			'trait-sloc-api-google.php', // Google Maps API Traits.
			'trait-sloc-api-bing.php', // Bing Maps API Traits.
			'trait-sloc-api-here.php', // HERE API Traits.
			'trait-sloc-api-locationiq.php', // LocationIQ API Traits.
			'trait-sloc-api-mapquest.php', // MapQuest API Traits.
			'trait-sloc-api-openroute.php', // OpenRoute API Traits.
			'trait-sloc-api-geoapify.php', // GeoApify API Traits.
			'trait-sloc-api-geonames.php', // GeoNames API Traits.
			'trait-sloc-api-mapbox.php', // MapBox API Traits.
			'trait-sloc-api-tomtom.php', // TomTom API Traits.
		);

		self::load( $traits, 'includes/apis/' );

		// Load Location Providers.
		$providers = array(
			'class-location-provider-dummy.php', // Dummy Location Provider.
			'class-location-provider-airport.php', // Airport Location Provider.
			'class-location-provider-address.php', // Address Lookup Location Provider.
			'class-location-provider-compass.php', // Compass https://github.com/aaronpk/Compass Location Provder.
		);

		self::register_providers( $providers, 'includes/location/' );

		// Load Weather Providers.
		$providers = array(
			'class-weather-provider-openweathermap.php', // Open Weather Map.
			'class-weather-provider-nwsus.php', // National Weather Service (US).
			'class-weather-provider-weatherstack.php', // weatherstack.com.
			'class-weather-provider-weatherbit.php', // weatherbit.com.
			'class-weather-provider-here.php', // HERE.
			'class-weather-provider-metoffice.php', // Met Office.
			'class-weather-provider-aeris.php', // Aeris Weather.
			'class-weather-provider-visualcrossing.php', // Visual Crossing.
			'class-weather-provider-meteostat.php', // Meteostat.
			'class-weather-provider-pirateweather.php', // Pirate Weather.
			'class-weather-provider-station.php', // Custom Station Weather Provider.
		);

		self::register_providers( $providers, 'includes/weather/' );

		// Load Map Providers.
		$providers = array(
			'class-map-provider-mapbox.php', // MapBox.
			'class-map-provider-google.php', // Google.
			'class-map-provider-bing.php', // Bing.
			'class-map-provider-mapquest.php', // MapQuest.
			'class-map-provider-here.php', // HERE.
			'class-map-provider-locationiq.php', // LocationIQ.
			'class-map-provider-geoapify.php', // Geoapify.
			'class-map-provider-tomtom.php', // Tom Tom.
			'class-map-provider-staticmap.php', // Custom Provider.
		);

		self::register_providers( $providers, 'includes/map/' );

		// Load Geo Providers.
		$providers = array(
			'class-geo-provider-nominatim.php', // Nominatim.
			'class-geo-provider-pelias.php', // Pelias.
			'class-geo-provider-mapquest.php', // MapQuest.
			'class-geo-provider-google.php', // Google.
			'class-geo-provider-geoapify.php', // Geoapify.
			'class-geo-provider-here.php', // HERE.
			'class-geo-provider-bing.php', // Bing.
			'class-geo-provider-locationiq.php', // LocationIQ.
			'class-geo-provider-geonames.php', // Geonames.
			'class-geo-provider-openroute.php', // OpenRoute.
		);
		self::register_providers( $providers, 'includes/geo/' );

		// Load Elevation Providers.
		$providers = array(
			'class-elevation-provider-bing.php', // Bing.
			'class-elevation-provider-geonames.php', // Geonames.
			'class-elevation-provider-google.php', // Google.
			'class-elevation-provider-mapquest.php', // MapQuest.
			'class-elevation-provider-openmapquest.php', // MapQuest Nominatim.
			'class-elevation-provider-openroute.php', // OpenRoute.
		);
		self::register_providers( $providers, 'includes/elevation/' );

		// Load Venue Providers.
		$providers = array(
			'class-venue-provider-nominatim.php', // Nominatim.
			'class-venue-provider-google.php', // Google.
			'class-venue-provider-locationiq.php',
		);
		self::register_providers( $providers, 'includes/venue/' );
	}

	/**
	 * Widgets Initializaton Function.
	 *
	 * Registers Widgets.
	 *
	 * @since 1.0.0
	 */
	public static function widgets_init() {
		register_widget( 'Sloc_Weather_Widget' );
		register_widget( 'Sloc_Station_Widget' );
		register_widget( 'Sloc_Airport_Widget' );
		register_widget( 'Sloc_Lastseen_Widget' );
	}

	/** Adds link to Plugin Page for Options Page.
	 *
	 * @param array $links Array of Existing Links.
	 * @return array Modified Links.
	 */
	public static function settings_link( $links ) {
		$settings_link = '<a href="admin.php?page=simloc">' . __( 'Settings', 'simple-location' ) . '</a>';
		$links[]       = $settings_link;
		return $links;
	}

	/**
	 * Stylesheet Load Function.
	 *
	 * Meant to be attached to plugins_loaded hook.
	 *
	 * @since 1.0.0
	 */
	public static function style_load() {
		wp_enqueue_style( 'simple-location', plugin_dir_url( __FILE__ ) . 'css/location.min.css', array(), self::$version );
	}

	/**
	 * Privacy Declaration.
	 *
	 * Adds a privacy policy declaration to the WordPress Privacy system.
	 *
	 * @since 1.0.0
	 */
	public static function privacy_declaration() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			$content = __(
				'Location and weather data is optionally stored for all posts, attachments, and comments. Location data is extracted from uploaded images along with other metadata. This
				data can be removed prior to uploading if tyou do not wish this to be stored. There are options to display this information or hide it.',
				'simple-location'
			);
			wp_add_privacy_policy_content(
				'Simple Location',
				wp_kses_post( wpautop( $content, false ) )
			);
		}
	}

	/**
	 * Generic Filter Set of HTML paramaters for KSES
	 *
	 * @return return array Array of HTML elements.
	 */
	public static function kses_clean() {
		return array(
			'a'          => array(
				'class' => array(),
				'href'  => array(),
				'name'  => array(),
			),
			'abbr'       => array(),
			'b'          => array(),
			'br'         => array(),
			'code'       => array(),
			'ins'        => array(),
			'del'        => array(),
			'em'         => array(),
			'i'          => array(),
			'q'          => array(),
			'strike'     => array(),
			'strong'     => array(),
			'time'       => array(
				'datetime' => array(),
			),
			'blockquote' => array(),
			'pre'        => array(),
			'p'          => array(
				'class' => array(),
				'id'    => array(),
			),
			'h1'         => array(
				'class' => array(),
			),
			'h2'         => array(
				'class' => array(),
			),
			'h3'         => array(
				'class' => array(),
			),
			'h4'         => array(
				'class' => array(),
			),
			'h5'         => array(
				'class' => array(),
			),
			'h6'         => array(
				'class' => array(),
			),
			'ul'         => array(
				'class'       => array(),
				'id'          => array(),
				'title'       => array(),
				'aria-label'  => array(),
				'aria-hidden' => array(),

			),
			'li'         => array(
				'class'       => array(),
				'id'          => array(),
				'class'       => array(),
				'id'          => array(),
				'title'       => array(),
				'aria-label'  => array(),
				'aria-hidden' => array(),
			),
			'ol'         => array(),
			'span'       => array(
				'class'       => array(),
				'id'          => array(),
				'title'       => array(),
				'aria-label'  => array(),
				'aria-hidden' => array(),
				'data-prefix' => array(),
				'data-icon'   => array(),
			),
			'section'    => array(
				'class' => array(),
				'id'    => array(),
			),
			'img'        => array(
				'src'    => array(),
				'class'  => array(),
				'id'     => array(),
				'alt'    => array(),
				'title'  => array(),
				'width'  => array(),
				'height' => array(),
				'srcset' => array(),
			),
			'figure'     => array(),
			'figcaption' => array(),
			'picture'    => array(
				'srcset' => array(),
				'type'   => array(),
			),
			'svg'        => array(
				'version'     => array(),
				'viewbox'     => array(),
				'id'          => array(),
				'x'           => array(),
				'y'           => array(),
				'xmlns'       => array(),
				'xmlns:xlink' => array(),
				'xml:space'   => array(),
				'style'       => array(),
				'aria-hidden' => array(),
				'focusable'   => array(),
				'class'       => array(),
				'role'        => array(),
				'height'      => array(),
				'width'       => array(),
				'fill'        => array(),

			),
			'div'        => array(
				'class' => array(),
				'id'    => array(),
			),
			'g'          => array(
				'id'           => array(),
				'stroke'       => array(),
				'stroke-width' => array(),
				'fill-rule'    => array(),
				'fill'         => array(),
			),
			'path'       => array(
				'd'    => array(),
				'fill' => array(),
			),
		);
	}
}

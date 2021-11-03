<?php
/**
 * Plugin Name: Simple Location
 * Plugin URI: https://wordpress.org/plugins/simple-location/
 * Description: Adds Location to WordPress
 * Author: David Shanske
 * Author URI: https://david.shanske.com
 * Requires at least: 4.9.9
 * Requires PHP: 5.6
 * Text Domain: simple-location
 * Domain Path:  /languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Version: 4.4.13
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
	public static $version = '4.4.13';


	/**
	 * Plugin Activation Function.
	 *
	 * Triggered on Plugin Activation to add rewrite rules.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-geo-data.php';
		WP_Geo_Data::rewrite();
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
	 * Plugin Initializaton Function.
	 *
	 * Meant to be attached to plugins_loaded hook.
	 *
	 * @since 1.0.0
	 */
	public static function init() {

		load_plugin_textdomain( 'simple-location', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		// Load stylesheets.
		add_action( 'wp_enqueue_scripts', array( static::class, 'style_load' ) );

		// Settings Link.
		add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( static::class, 'settings_link' ) );

		// Add Privacy Policy.
		add_action( 'admin_init', array( static::class, 'privacy_declaration' ) );

		// Core Load Files.
		$core = array(
			'class-geo-data.php', // Register Metadata Functions.
			'class-venue-taxonomy.php', // Venue Taxonomy.
			'class-location-taxonomy.php', // Venue Taxonomy.
			'class-sloc-provider.php', // Base Provider Class.
			'class-map-provider.php', // Map Provider Class.
			'class-geo-provider.php', // Geo Provider Class.
			'class-weather-provider.php', // Weather Provider Class.
			'class-location-provider.php', // Location Provider Class.
			'class-sloc-weather-widget.php', // Weather Widget.
			'class-sloc-station-widget.php', // Weather Station Widget.
			'class-sloc-airport-widget.php', // Airport Weather Widget.
			'class-sloc-lastseen-widget.php', // Last Location Seen Widget.
			'class-rest-geo.php', // REST endpoint for Geo.
			'class-loc-config.php', // Configuration and Settings Page.
			'class-loc-metabox.php', // Location Metabox.
			'class-loc-view.php', // Location View functionality.
			'class-timezone-result.php',
			'class-astronomical-calculator.php', // Calculates sunrise sunset etc.
			'class-location-plugins.php',
			'class-location-zones.php',
			'class-loc-timezone.php',
			'class-airport-location.php',
			'compat-functions.php',
		);

		// Load Core Files.
		self::load( $core );
		add_action( 'widgets_init', array( static::class, 'widgets_init' ) );

		$libraries = array(
			'class-sloc-polyline.php', // Polyline Encoding Library.
		);
		self::load( $libraries, 'lib/' );

		// Load Providers.
		$providers = array(
			'class-location-provider-dummy.php', // Dummy Location Provider.
			'class-location-provider-airport.php', // Airport Location Provider.
			'class-location-provider-address.php', // Address Lookup Location Provider.
			'class-location-provider-compass.php', // Compass https://github.com/aaronpk/Compass Location Provder.
			'class-weather-provider-openweathermap.php', // Open Weather Map.
			'class-weather-provider-darksky.php', // Dark Sky.
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
			'class-map-provider-mapbox.php', // MapBox.
			'class-map-provider-google.php', // Google.
			'class-map-provider-bing.php', // Bing.
			'class-map-provider-mapquest.php', // MapQuest.
			'class-map-provider-here.php', // HERE.
			'class-map-provider-locationiq.php', // LocationIQ.
			'class-map-provider-geoapify.php', // Geoapify.
			'class-map-provider-yandex.php', // Yandex.
			'class-map-provider-tomtom.php', // Tom Tom.
			'class-map-provider-staticmap.php', // Custom Provider.
			'class-geo-provider-nominatim.php', // Nominatim.
			'class-geo-provider-mapquest.php', // MapQuest.
			'class-geo-provider-openmapquest.php', // MapQuest Nominatim.
			'class-geo-provider-google.php', // Google.
			'class-geo-provider-here.php', // HERE.
			'class-geo-provider-bing.php', // Bing.
			'class-geo-provider-locationiq.php', // LocationIQ.
			'class-geo-provider-geonames.php', // Geonames.
			'class-geo-provider-pelias.php', // Pelias.
			'class-geo-provider-openroute.php', // OpenRoute.
		);
		self::load( $providers );
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
				'id' => array(),
			),
			'h1'         => array(),
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'h5'         => array(),
			'h6'         => array(),
			'ul'         => array(),
			'li'         => array(),
			'ol'         => array(),
			'span'       => array(),
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
				'version' => array(),
				'viewbox' => array(),
				'id' => array()
			),
		);
	}

}


if ( ! function_exists( 'ifset' ) ) {

	/**
	 * Compat for the null coaslescing operator.
	 *
	 * Returns $var if set otherwise $default.
	 *
	 * @param mixed $var A variable.
	 * @param mixed $default Return if $var is not set. Defaults to false.
	 * @return mixed $return The returned value.
	 */
	function ifset( &$var, $default = false ) {
		return isset( $var ) ? $var : $default;
	}
}

if ( ! function_exists( 'array_key_return' ) ) {

	/**
	 * Returns $key in $array if set otherwise $default.
	 *
	 * @param string|number $key Key.
	 * @param array         $array An array.
	 * @param mixed         $default Return if $var is not set. Defaults to false.
	 * @return mixed $return The returned value.
	 */
	function array_key_return( $key, &$array, $default = false ) {
		if ( ! is_array( $array ) ) {
			return $default;
		}
		return array_key_exists( $key, $array ) ? $array[ $key ] : $default;
	}
}

if ( ! function_exists( 'ifset_round' ) ) {
	/**
	 * Returns if set and round.
	 *
	 * Returns $var, rounding it if it is a float if set otherwise $default.
	 *
	 * @param mixed $var A variable.
	 * @param mixed $precision Rounds floats to a precision. Defaults to 0.
	 * @param mixed $default Returned if var is not set. Defaults to false.
	 * @return mixed $return The returned value.
	 */
	function ifset_round( &$var, $precision = 0, $default = false ) {
		$return = ifset( $var, $default );
		if ( is_float( $return ) ) {
			return round( $return, $precision );
		}
		return $return;
	}
}


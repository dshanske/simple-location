<?php
/**
 * Plugin Name: Simple Location
 * Plugin URI: https://wordpress.org/plugins/simple-location/
 * Description: Adds Location to Wordpress
 * Version: 3.2.1
 * Author: David Shanske
 * Author URI: https://david.shanske.com
 * Text Domain: simple-location
 * Domain Path:  /languages
 */

define( "SLOC_PUBLIC", 1 );

add_action( 'plugins_loaded', array( 'Simple_Location_Plugin', 'init' ) );

// Activation and Deactivation Hooks
register_activation_hook( __FILE__, array( 'Simple_Location_Plugin', 'activate') );
register_deactivation_hook( __FILE__, array( 'Simple_Location_Plugin', 'deactivate') );


class Simple_Location_Plugin {
	public static $version = '3.2.1';

	public static function activate() {
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-geo-data.php' );
		WP_Geo_Data::rewrite();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public static function init() {

		load_plugin_textdomain( 'simple-location', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		// Load stylesheets.
		add_action( 'wp_enqueue_scripts', array( 'Simple_Location_Plugin', 'style_load' ) );

		// Settings Link
		add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'Simple_Location_Plugin', 'settings_link' ) );

		// Register Metadata Functions
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-geo-data.php' );

		// Venue Taxonomy
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-venue-taxonomy.php' );

		// Map Provider Class
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-geo-provider.php' );

		// Map Providers
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-geo-provider-osm.php' );
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-geo-provider-google.php' );
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-geo-provider-bing.php' );

		// API Endpoint under construction
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-rest-geo.php' );
		$geo_api = new REST_Geo();

		// Configuration Functions
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-loc-config.php' );

		// Add Location Post Meta
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-loc-metabox.php' );

		// Add Location Display Functions
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-loc-view.php' );

		// Timezone Functions
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-timezone-result.php' );
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-loc-timezone.php' );
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-post-timezone.php' );

	}
		
	/** Adds link to Plugin Page for Options Page.
	 *
	 * @param array $links Array of Existing Links.
	 * @return array Modified Links.
	 */
	public static function settings_link( $links ) {
		$settings_link = '<a href="options-media.php">' . __( 'Map Settings', 'simple-location' ) . '</a>';
		$links[] = $settings_link;
		return $links;
		}

	/**
	 * Loads the Stylesheet for the Plugin.
	 */
	public static function style_load() {
		wp_enqueue_style( 'simple-location', plugin_dir_url( __FILE__ ) . 'css/location.min.css', array(), self::$version );
	}
}


if ( ! function_exists( 'ifset' ) ) {
	function ifset(&$var, $default = false) {
		return isset( $var ) ? $var : $default;
	}
}

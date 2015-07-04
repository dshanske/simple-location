<?php
/**
 * Plugin Name: Simple Location
 * Plugin URI: https://wordpress.org/plugins/simple-location/
 * Description: Adds Location to Wordpress Pages and Posts. 
 * Version: 2.0.1
 * Author: David Shanske
 * Author URI: https://david.shanske.com
 */

define ("SIMPLE_LOCATION_VERSION", "2.0.1");

// Map Provider Interface
require_once( plugin_dir_path( __FILE__ ) . 'includes/interface-map-provider.php');

// Map Providers
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-osm-static.php');
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-google-map-static.php');

// Configuration Functions
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-loc-config.php');

// Add Location Post Meta
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-loc-postmeta.php');

// Add Location Display Functions
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-loc-view.php');

// Timezone Display Functions
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-post-timezone.php');

if (!function_exists('ifset') ) {
  function ifset(&$var, $default = false) {
      return isset($var) ? $var : $default;
  }
}

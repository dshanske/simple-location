<?php
/**
 * Plugin Name: Simple Location
 * Plugin URI: https://www.github.com/dshanske/simple-location
 * Description: Adds Location to Wordpress Pages and Posts. 
 * Version: 1.0.0
 * Author: David Shanske
 * Author URI: https://david.shanske.com
 */

// Configuration Functions
require_once( plugin_dir_path( __FILE__ ) . '/location-config.php');

// Add Location Post Meta
require_once( plugin_dir_path( __FILE__ ) . '/loc-postmeta.php');

// Add Location Display Functions
require_once( plugin_dir_path( __FILE__ ) . '/location-view.php');

// Nominatim Functions 
require_once( plugin_dir_path( __FILE__ ) . '/nominatim.php');

function sloc_enqueue() {
  wp_register_script( 'simple-location', plugins_url( 'js/location.js', __FILE__ ) );
  wp_enqueue_script('simple-location');
}

add_action('admin_init', 'sloc_enqueue');


function sloc_clean_coordinate($coordinate) {
  $pattern = '/^(\-)?(\d{1,3})\.(\d{1,15})/';
  preg_match($pattern, $coordinate, $matches);
  return $matches[0];
}

// If the Theme Has Not Declared Location Support
// Add the Location Display to the Content Filter
function sloc_content_location() {
  add_filter( 'the_content', 'sloc_content_map', 20);
  if (!current_theme_supports('simple-location')) {
    add_filter( 'the_content', 'sloc_location_content', 20 );
  }
}

add_action('init', 'sloc_content_location');


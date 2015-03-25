<?php
/**
 * Plugin Name: Simple Location
 * Plugin URI: http://tiny.n9n.us
 * Description: Adds Location to Wordpress Pages and Posts. 
 * Version: 0.1.0
 * Author: David Shanske
 * Author URI: https://david.shanske.com
 * License: CC0
 */

// Add Location Post Meta
require_once( plugin_dir_path( __FILE__ ) . '/loc-postmeta.php');

// Add Location Display Functions
require_once( plugin_dir_path( __FILE__ ) . '/location-view.php');

// Nominatim Functions 
require_once( plugin_dir_path( __FILE__ ) . '/nominatim.php');

// Add Tags to Pages to support Venues
function simloc_init() {
  register_taxonomy_for_object_type('post_tag', 'page');
}

add_action('init', 'simloc_init');

function simloc_admin_init() {
  wp_register_script( 'simple-location', plugins_url( 'js/location.js', __FILE__ ) );
  wp_enqueue_script('simple-location');
}

add_action('admin_init', 'simloc_admin_init');

function clean_coordinate($coordinate) {
  $pattern = '/^(\-)?(\d{1,3})\.(\d{1,15})/';
  preg_match($pattern, $coordinate, $matches);
  return $matches[0];
}

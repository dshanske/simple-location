<?php
/**
 * Plugin Name: Simple Location
 * Plugin URI: http://tiny.n9n.us
 * Description: Adds Location to Wordpress Pages and Posts. 
 * Version: 0.01
 * Author: David Shanske
 * Author URI: https://david.shanske.com
 * License: CC0
 */

// Add Location Post Meta
require_once( plugin_dir_path( __FILE__ ) . '/loc-postmeta.php');

// Add Location Display Functions
require_once( plugin_dir_path( __FILE__ ) . '/location-view.php');


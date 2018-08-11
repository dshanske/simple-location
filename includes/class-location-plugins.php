<?php

/**
 * Simple Location Plugins Class
 *
 * Custom Functions for Specific Other Pugins
 *
 * @package Simple Location
 */
class Location_Plugins {
	public static function init() {
		add_action( 'after_micropub', array( 'Location_Plugins', 'micropub_set_weather' ), 9, 2 );
	}

} // End Class Kind_Plugins



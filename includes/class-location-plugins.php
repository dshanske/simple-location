<?php

/**
 * Simple Location Plugins Class
 *
 * Custom Functions for Specific Other Pugins
 *
 * @package Simple Location
 */
class Location_Plugins {
	public function __construct() {
		add_action( 'after_micropub', array( 'Location_Plugins', 'micropub_set_location' ), 10, 2 );
	}

	public static function micropub_set_location( $input, $args ) {
		if ( ! isset( $args['meta_input'] ) ) {
			return;
		}
		$meta = $args['meta_input'];
		// If there is already a description set
		if ( array_key_exists( 'geo_address', $meta ) && ! empty( $meta['geo_address'] ) ) {
			return;
		}
		if ( ! isset( $meta['geo_latitude'] ) || ! isset( $meta['geo_longitude'] ) ) {
			return;
		}

		$reverse = Loc_Config::geo_provider();
		$reverse->set( $meta['geo_latitude'], $meta['geo_longitude'] );
		$reverse_adr = $reverse->reverse_lookup();
		if ( is_wp_error( $reverse_adr ) ) {
			return $reverse_adr;
		}
		if ( isset( $meta['geo_altitude'] ) && 0 !== $meta['altitude'] && 'NaN' !== $meta['altitude'] ) {
			unset( $meta['geo_altitude'] );
		}
		if ( ! isset( $meta['geo_altitude'] ) ) {
			update_post_meta( $args['ID'], 'geo_altitude', $reverse->elevation() );
		}
		if ( isset( $reverse_adr['display-name'] ) ) {
			update_post_meta( $args['ID'], 'geo_address', $reverse_adr['display-name'] );
		}
		$weather = Loc_Config::weather_provider();
		$weather->set( $meta['geo_latitude'], $meta['geo_longitude'] );
		$conditions = $weather->get_conditions();
		if ( ! empty( $conditions ) ) {
			update_post_meta( $args['ID'], 'geo_weather', $conditions );
		}
	}

} // End Class Kind_Plugins

new Location_Plugins();

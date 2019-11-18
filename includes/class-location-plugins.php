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
		add_filter( 'before_micropub', array( 'Location_Plugins', 'micropub_lookup_location' ), 10 );
		add_action( 'after_micropub', array( 'Location_Plugins', 'micropub_set_location' ), 10, 2 );
		add_filter( 'micropub_query', array( 'Location_Plugins', 'micropub_query_geo' ), 10, 2 );
	}

	public static function micropub_lookup_location( $input ) {
		if ( ! isset( $input['properties'] ) ) {
			return $input;
		}
		$properties = $input['properties'];
		if ( isset( $properties['checkin'] ) || isset( $properties['location'] ) ) {
			return $input;
		}
		if ( ! get_option( 'sloc_auto_micropub' ) ) {
			return $input;
		}
		$geolocation = Loc_Config::geolocation_provider();
		if ( ! is_object( $geolocation ) ) {
			return $input;
		}
		if ( ! $geolocation->background() ) {
			return $input;
		}
		$published = null;
		if ( isset( $properties['published'] ) ) {
			$published = $properties['published'][0];
			$now       = time();
		}
		$geolocation->set_user( get_current_user_id() );
		$geolocation->retrieve( $published );
		$return = $geolocation->get_mf2();
		if ( $return ) {
			$input['properties']['location']            = array( $return );
			$input['properties']['location-visibility'] = array( 'private' );
		}
		return $input;
	}

	public static function micropub_set_location( $input, $args ) {
		if ( ! isset( $args['meta_input'] ) ) {
			return;
		}
		$meta = $args['meta_input'];
		// If there is already a description set
		if ( ! array_key_exists( 'geo_address', $meta ) || empty( $meta['geo_address'] ) ) {
			if ( isset( $meta['geo_latitude'] ) && isset( $meta['geo_longitude'] ) ) {
				$reverse = Loc_Config::geo_provider();
				$reverse->set( $meta['geo_latitude'], $meta['geo_longitude'] );
				$reverse_adr = $reverse->reverse_lookup();
				if ( isset( $reverse_adr['display-name'] ) ) {
					update_post_meta( $args['ID'], 'geo_address', $reverse_adr['display-name'] );
				}
				if ( isset( $meta['geo_altitude'] ) && 0 !== $meta['altitude'] && 'NaN' !== $meta['altitude'] ) {
					unset( $meta['geo_altitude'] );
				}
				if ( ! isset( $meta['geo_altitude'] ) ) {
					update_post_meta( $args['ID'], 'geo_altitude', $reverse->elevation() );
				}
			}
		}
		if ( isset( $meta['geo_longitude'] ) && $meta['geo_latitude'] ) {
			if ( ! isset( $input['properties']['location-visibility'] ) ) {
				$zone = Location_Zones::in_zone( $meta['geo_latitude'], $meta['geo_longitude'] );
				if ( ! empty( $zone ) ) {
					update_post_meta( $args['ID'], 'geo_address', $zone );
					WP_Geo_Data::set_visibility( 'post', $args['ID'], 'protected' );
					update_post_meta( $args['ID'], 'geo_zone', $zone );
				} else {
					WP_Geo_Data::set_visibility( 'post', $args['ID'], 'public' );
				}
			}
			// If altitude is above 1000m always show the higher zoom level
			if ( isset( $meta['geo_altitude'] ) && 1000 < $meta['geo_altitude'] ) {
				update_post_meta( $args['ID'], 'geo_zoom', 9 );
			} elseif ( isset( $meta['geo_accuracy'] ) ) {
				update_post_meta( $args['ID'], 'geo_zoom', round( log( 591657550.5 / ( $meta['geo_accuracy'] * 45 ), 2 ) ) + 1 );
			}
			$current = true;
			if ( isset( $input['properties']['published'] ) ) {
				$published = new DateTime( $input['properties']['published'][0] );
				$now       = new DateTime();
				$diff      = abs( $now->getTimestamp() - $published->getTimestamp() );
				if ( $diff > HOUR_IN_SECONDS ) {
					$current = false;
				}
			}
			if ( $current ) {
				$weather = Loc_Config::weather_provider();
				$weather->set( $meta['geo_latitude'], $meta['geo_longitude'] );
				$conditions = $weather->get_conditions();
				if ( ! empty( $conditions ) ) {
					// if debug mode is on remove the raw data from storage
					unset( $conditions['raw'] );
					update_post_meta( $args['ID'], 'geo_weather', $conditions );
				}
			}
		}
	}

	public static function micropub_query_geo( $resp, $input ) {
		// Only modify geo
		if ( 'geo' !== $input['q'] ) {
			return $resp;
		}
		if ( isset( $input['uri'] ) && 'geo:' === substr( $input['uri'], 0, 4 ) ) {
			// Geo URI format:
			// http://en.wikipedia.org/wiki/Geo_URI#Example
			// e.g. geo:37.786971,-122.399677;u=35
			$geo          = explode( ':', substr( urldecode( $input['uri'] ), 4 ) );
			$geo          = explode( ';', $geo[0] );
			$coords       = explode( ',', $geo[0] );
			$input['lat'] = trim( $coords[0] );
			$input['lon'] = trim( $coords[1] );
		}
		if ( ! isset( $input['lat'] ) && ! isset( $input['lon'] ) ) {
			return $input;
		}
		$reverse = Loc_Config::geo_provider();
		$reverse->set( $input['lat'], $input['lon'] );
		$zone = Location_Zones::in_zone( $input['lat'], $input['lon'] );
		if ( empty( $zone ) ) {
			$reverse_adr = $reverse->reverse_lookup();
		} else {
			$reverse_adr = array( 'display-name' => $zone );
		}
		return array(
			'venues' => array(),
			'geo'    => array(
				'label'      => ifset( $reverse_adr['display-name'] ),
				'latitude'   => $input['lat'],
				'longitude'  => $input['lon'],
				'visibility' => empty( $zone ) ? 'public' : 'protected',
			),
		);
	}

} // End Class Kind_Plugins

new Location_Plugins();

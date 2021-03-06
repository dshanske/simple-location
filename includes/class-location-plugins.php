<?php
/**
 * Extra Plugin Configuration.
 *
 * @package Simple_Location
 */

/**
 * Simple Location Plugins Class
 *
 * Custom Functions for Specific Other Pugins
 *
 * @package Simple Location
 */
class Location_Plugins {

	/**
	 * Constructor that adds filters.
	 */
	public function __construct() {
		add_filter( 'before_micropub', array( 'Location_Plugins', 'micropub_lookup_location' ), 10 );
		add_action( 'after_micropub', array( 'Location_Plugins', 'micropub_set_location' ), 10, 2 );
		add_filter( 'micropub_query', array( 'Location_Plugins', 'micropub_query_geo' ), 10, 2 );
	}

	/**
	 * Fires before the Micropub Request is complete to add additional location information.
	 *
	 * @param array $input The Micropub Input.
	 * @return array The Updated properties.
	 */
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

	/**
	 * Fires after the Micropub Request is complete to add additional location information.
	 *
	 * @param array $input The Micropub Input.
	 * @param array $args Micropub Arguments.
	 */
	public static function micropub_set_location( $input, $args ) {
		if ( ! isset( $args['meta_input'] ) ) {
			return;
		}
		$properties = $input['properties'];
		$meta       = $args['meta_input'];
		if ( isset( $meta['geo_longitude'] ) && $meta['geo_latitude'] ) {
			if ( ! isset( $properties['location-visibility'] ) ) {
				$zone = Location_Zones::in_zone( $meta['geo_latitude'], $meta['geo_longitude'] );
				if ( ! empty( $zone ) ) {
					$meta['geo_address'] = $zone;
					update_post_meta( $args['ID'], 'geo_address', $zone );
					WP_Geo_Data::set_visibility( 'post', $args['ID'], 'protected' );
					update_post_meta( $args['ID'], 'geo_zone', $zone );
				} else {
					WP_Geo_Data::set_visibility( 'post', $args['ID'], 'public' ); // This is on the basis that if you are sending coordinates from Micropub you want to display them unless otherwise said.
				}
			}
			// If altitude is above 1000m always show the higher zoom level.
			if ( isset( $meta['geo_altitude'] ) && 1000 < $meta['geo_altitude'] ) {
				update_post_meta( $args['ID'], 'geo_zoom', 9 );
			} elseif ( isset( $meta['geo_accuracy'] ) ) {
				update_post_meta( $args['ID'], 'geo_zoom', round( log( 591657550.5 / ( $meta['geo_accuracy'] * 45 ), 2 ) ) + 1 );
			}
			$weather = Loc_Config::weather_provider();
			$weather->set( $meta['geo_latitude'], $meta['geo_longitude'] );

			if ( isset( $properties['published'] ) ) {
				$published = new DateTime( $properties['published'][0] );
			} else {
				$published = new DateTime();
			}

			$conditions = $weather->get_conditions( $published->getTimestamp() );
			if ( ! empty( $conditions ) || ! is_wp_error( $conditions ) ) {
				// if debug mode is on remove the raw data from storage.
				unset( $conditions['raw'] );
				update_post_meta( $args['ID'], 'geo_weather', $conditions );
			}
		}

		// If there are no location based properties then exit this.
		if ( ! isset( $properties['checkin'] ) && ! isset( $properties['location'] ) && ! isset( $properties['latitude'] ) && ! isset( $properties['longitude'] ) ) {
			return;
		}

		if ( isset( $properties['location'] ) && ! wp_is_numeric_array( $properties['location'] ) ) {
			$location = $properties['location']['properties'];
			if ( isset( $properties['checkin'] ) && ! wp_is_numeric_array( $properties['checkin'] ) ) {
				$location = array_merge( $location, $properties['checkin']['properties'] );
			}
		} else {
			if ( isset( $properties['checkin'] ) ) {
				$location = $properties['checkin']['properties'];
			} elseif ( isset( $properties['latitude'] ) && isset( $properties['longitude'] ) ) {
				$location = array(
					'latitude'  => $properties['latitude'],
					'longitude' => $properties['longitude'],
				);
			} elseif ( isset( $meta['geo_latitude'] ) && isset( $meta['geo_longitude'] ) ) {
				$location = array(
					'latitude'  => $meta['geo_latitude'],
					'longitude' => $meta['geo_longitude'],
				);
			} else {
				return;
			}
		}

		// Strip out anything that might not be relevant to an address.
		$location = wp_array_slice_assoc( $location, array( 'street-address', 'extended-address', 'post-office-box', 'locality', 'region', 'postal-code', 'country-name', 'country-code', 'region-code', 'latitude', 'longitude', 'altitude', 'name', 'label' ) );
		foreach ( $location as $key => $value ) {
			if ( is_array( $value ) && 1 === count( $value ) ) {
				$location[ $key ] = array_shift( $value );
			}
		}
		$location = Location_Taxonomy::normalize_address( $location );

		$term        = Location_Taxonomy::get_location_taxonomy( $args['ID'] );
		$reverse     = Loc_Config::geo_provider();
		$reverse_adr = null;

		if ( ! empty( array_intersect( array_keys( $location ), array( 'region', 'country-name' ) ) ) ) {
			if ( ! $term ) {
				$term = Location_Taxonomy::get_location( $location, true );
			}
			Location_Taxonomy::set_location( $args['ID'], $term );
		} elseif ( isset( $location['latitude'] ) && isset( $location['longitude'] ) ) {
			$reverse->set( $location['latitude'], $location['longitude'] );
			$reverse_adr = $reverse->reverse_lookup();
			if ( isset( $meta['geo_altitude'] ) && 0 !== $meta['altitude'] && 'NaN' !== $meta['altitude'] ) {
				unset( $meta['geo_altitude'] );
			}
			if ( ! isset( $meta['geo_altitude'] ) && isset( $meta['geo_latitude'] ) && isset( $meta['geo_longitude'] ) ) {
				update_post_meta( $args['ID'], 'geo_altitude', $reverse->elevation() );
			}
			if ( ! $term ) {
				$term = Location_Taxonomy::get_location( $reverse_adr, true );
				Location_Taxonomy::set_location( $args['ID'], $term );
			}
			if ( ! array_key_exists( 'geo_address', $meta ) || empty( $meta['geo_address'] ) ) {
				if ( isset( $reverse_adr['display-name'] ) ) {
					update_post_meta( $args['ID'], 'geo_address', $reverse_adr['display-name'] );
				}
			}
		}
	}

	/**
	 * Adds geo queries to the Micropub query handler.
	 *
	 * @param array $resp The response before filtering.
	 * @param array $input The Micropub Input.
	 * @return array Either the unmodified response or the geo response.
	 */
	public static function micropub_query_geo( $resp, $input ) {
		// Only modify geo.
		if ( 'geo' !== $input['q'] ) {
			return $resp;
		}
		if ( isset( $input['uri'] ) && 'geo:' === substr( $input['uri'], 0, 4 ) ) {
			/*
			 *  Geo URI format:
			 * http://en.wikipedia.org/wiki/Geo_URI#Example
			 * e.g. geo:37.786971,-122.399677;u=35
			 */
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

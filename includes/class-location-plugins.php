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
		add_filter( 'webmention_handler_mf2_set_properties', array( 'Location_Plugins', 'webmention_handler_mf2_set_properties' ), 10, 2 );
	}

	public static function array_get( $array, $key, $default = array(), $index = true ) {
		$return = $default;
		if ( is_array( $array ) && isset( $array[ $key ] ) ) {
			$return = $array[ $key ];
		}
		if ( $index && wp_is_numeric_array( $return ) && ! empty( $return ) ) {
			$return = $return[0];
		}
		return $return;
	}

	/**
	 *
	 *
	 */
	public static function webmention_handler_mf2_set_properties( $meta, $handler ) {
		$item = $handler->get_webmention_item();
		if ( ! $item ) {
			return $meta;
		}
		$mf_array = $item->get_raw();
		if ( empty( $mf_array ) ) {
			return $meta;
		}

		if ( ! array_key_exists( 'properties', $mf_array ) ) {
			return $meta;
		}

		$location = ifset( $mf_array['properties']['location'] );
		if ( wp_is_numeric_array( $location ) ) {
			$location = $location[0];
			$props    = $location['properties'];
			if ( is_array( $location ) ) {
				$props = $location['properties'];
				if ( isset( $props['geo'] ) ) {
					if ( array_key_exists( 'label', $props ) ) {
						$meta['geo_address'] = static::get_first_array_item( $props['label'] );
					}
					$props = $handler->get_first_array_item( $props['geo'] );
					$props = $props['properties'];
				} else {
					$parts = array(
						static::array_get( $props, 'name', array(), true ),
						static::array_get( $props, 'street-address', array(), true ),
						static::array_get( $props, 'locality', array(), true ),
						static::array_get( $props, 'region', array(), true ),
						static::array_get( $props, 'postal-code', array(), true ),
						static::array_get( $props, 'country-name', array(), true ),

					);
					$parts = array_filter( $parts );
					if ( ! empty( $parts ) ) {
						$meta['geo_address'] = implode(
							', ',
							array_filter(
								$parts,
								function ( $v ) {
									return $v;
								}
							)
						);
					}
				}
				foreach ( array( 'latitude', 'longitude', 'altitude', 'accuracy' ) as $property ) {
					if ( array_key_exists( $property, $props ) ) {
						$meta[ 'geo_' . $property ] = $props[ $property ][0];
					}
				}
			} elseif ( 'http' !== substr( $location, 0, 4 ) ) {
				$meta['geo_address'] = $location;
			}
		}
		return $meta;
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

		// If there are no location based properties then exit this.
		if ( ! isset( $properties['checkin'] ) && ! isset( $properties['location'] ) && ! isset( $properties['latitude'] ) && ! isset( $properties['longitude'] ) && ! isset( $meta['geo_latitude'] ) && ! isset( $meta['geo_longitude'] ) ) {
			return;
		}

		if ( isset( $properties['published'] ) ) {
			$published = new DateTime( $properties['published'][0] );
		} else {
			$published = new DateTime( 'now', wp_timezone() );
		}

		if ( isset( $meta['geo_longitude'] ) && $meta['geo_latitude'] ) {
			// Always assume a checkin is to a building level option
			if ( isset( $properties['checkin'] ) ) {
				set_post_geodata( $args['ID'], 'zoom', 18 );
				// If altitude is above 1000m always show the higher zoom level.
			} elseif ( isset( $meta['geo_altitude'] ) && 1000 < $meta['geo_altitude'] ) {
				set_post_geodata( $args['ID'], 'zoom', 9 );
			} elseif ( isset( $meta['geo_accuracy'] ) ) {
				set_post_geodata( $args['ID'], 'zoom', round( log( 591657550.5 / ( $meta['geo_accuracy'] * 45 ), 2 ) ) + 1 );
			}

			is_day_post( $args['ID'] ); // Set whether this is day or not.
			$weather = Loc_Config::weather_provider();
			if ( $weather ) {
				$weather->set( $meta['geo_latitude'], $meta['geo_longitude'] );
				$conditions = $weather->get_conditions( $published->getTimestamp() );
				if ( ! empty( $conditions ) || ! is_wp_error( $conditions ) ) {
					// if debug mode is on remove the raw data from storage.
					unset( $conditions['raw'] );
					set_post_weatherdata( $args['ID'], '', $conditions );
				}
			}

			$venue = Post_Venue::at_venue( $meta['geo_latitude'], $meta['geo_longitude'] );
			if ( false !== $venue ) {
				update_post_meta( $args['ID'], 'venue_id', $venue );
			}

			if ( ! isset( $properties['location-visibility'] ) && false !== $venue ) {
				set_post_geodata( $args['ID'], 'visibility', 'protected' );
				// Set the Address to Null
				set_post_geodata( $args['ID'], 'address', '' );
			} else {
				set_post_geodata( $args['ID'], 'visibility', 'public' ); // This is on the basis that if you are sending coordinates from Micropub you want to display them unless otherwise said.
			}
		}


		if ( isset( $properties['location'] ) && ! wp_is_numeric_array( $properties['location'] ) ) {
			$location = $properties['location']['properties'];
			if ( isset( $properties['checkin'] ) && ! wp_is_numeric_array( $properties['checkin'] ) ) {
				$location = array_merge( $location, $properties['checkin']['properties'] );
			}
		} elseif ( isset( $properties['checkin'] ) ) {
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

		// Strip out anything that might not be relevant to an address.
		$location = wp_array_slice_assoc( $location, array( 'street-address', 'extended-address', 'post-office-box', 'locality', 'region', 'postal-code', 'country-name', 'country-code', 'region-code', 'region', 'latitude', 'longitude', 'altitude', 'name', 'label', 'url' ) );
		foreach ( $location as $key => $value ) {
			if ( is_array( $value ) && 1 === count( $value ) ) {
				$location[ $key ] = array_shift( $value );
			}
		}
		$location = Location_Taxonomy::normalize_address( $location );

		$term        = Location_Taxonomy::get_location_taxonomy( $args['ID'] );
		$reverse     = Loc_Config::geo_provider();
		$reverse_adr = null;

		// If this is a checkin.
		if ( isset( $properties['checkin'] ) ) {
			$venue = Post_Venue::add_new_venue( $location );
			if ( ! is_wp_error( $venue ) ) {
				Post_Venue::set_post_venue( $args['ID'], $venue );
				set_post_geodata( $args['ID'], 'visibility', get_post_geodata( $venue, 'visibility' ) );
				update_post_meta(
					$args['ID'],
					'mf2_checkin',
					array(
						'type'       => array( 'h-card' ),
						'properties' => array(
							'url'  => array( get_permalink( $venue ) ),
							'name' => array( get_the_title( $venue ) ),
						),
					)
				);
				if ( ! $term ) {
					$term = Location_Taxonomy::get_post_location( $venue );
					if ( $term ) {
						Location_Taxonomy::set_location( $id, $term );
					}
				}
			}
		}

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

		$venues      = array();
		$reverse_adr = array();
		$reverse     = Loc_Config::geo_provider();
		if ( $reverse ) {
			$reverse->set( $input['lat'], $input['lon'] );
			$reverse_adr = $reverse->reverse_lookup();
		}
		$venue = Loc_Config::venue_provider( $provider );
		if ( $venue ) {
			$venue->set( $params );
			$venues = $venue->reverse_lookup();
		}
		return array(
			'venues' => $venues,
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

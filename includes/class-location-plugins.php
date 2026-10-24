<?php
/**
 * Extra Plugin Configuration.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

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
		add_filter( 'activitypub_post_location', array( 'Location_Plugins', 'activitypub_post_location' ), 10, 3 );
		add_action( 'activitypub_before_get_content', array( 'Location_Plugins', 'activitypub_before_get_content' ) );
		add_filter( 'activitypub_the_content', array( 'Location_Plugins', 'activitypub_the_content' ), 1 );
	}

	/**
	 * Applies the post's location visibility to the Place the ActivityPub plugin federates.
	 *
	 * The ActivityPub plugin reads the geo meta itself and only withholds a location when geo_public is '0', so a
	 * location that is private by default, protected, or inherited would be federated with its coordinates.
	 * A private location is removed; a protected location keeps its name but loses its coordinates.
	 *
	 * @since 6.0.0
	 *
	 * @param array|null $place   ActivityStreams Place object.
	 * @param WP_Post    $post    Post being federated.
	 * @param int        $post_id Post ID.
	 * @return array|null Place object, or null for no location.
	 */
	public static function activitypub_post_location( $place, $post, $post_id ) {
		if ( ! is_array( $place ) ) {
			return $place;
		}
		$visibility = get_post_geodata( $post_id, 'visibility' );
		if ( 'public' === $visibility ) {
			return $place;
		}
		if ( 'protected' === $visibility && ! empty( $place['name'] ) ) {
			unset( $place['latitude'], $place['longitude'], $place['altitude'], $place['accuracy'], $place['radius'] );
			return $place;
		}
		return null;
	}

	/**
	 * Hides private and protected locations while the ActivityPub plugin renders post content.
	 *
	 * The content is rendered as whoever triggered it, often the logged-in author saving the post, who would
	 * otherwise see a hidden location marked as hidden and send it to every follower.
	 *
	 * @since 6.0.0
	 */
	public static function activitypub_before_get_content() {
		add_filter( 'sloc_show_hidden_location', array( 'Location_Plugins', 'hide_hidden_location' ), 99 );
	}

	/**
	 * Restores hidden location display once the ActivityPub plugin has rendered post content.
	 *
	 * @since 6.0.0
	 *
	 * @param string $content Content rendered for ActivityPub.
	 * @return string Unchanged content.
	 */
	public static function activitypub_the_content( $content ) {
		remove_filter( 'sloc_show_hidden_location', array( 'Location_Plugins', 'hide_hidden_location' ), 99 );
		return $content;
	}

	/**
	 * Filter callback that never shows a hidden location.
	 *
	 * @since 6.0.0
	 *
	 * @return bool False.
	 */
	public static function hide_hidden_location() {
		return false;
	}

	/**
	 * Returns the location visibility a Micropub request asked for.
	 *
	 * The location-visibility property is experimental in Micropub. Only public, private and protected are accepted;
	 * like the Micropub plugin, the last value wins.
	 *
	 * @since 6.0.0
	 *
	 * @param array $properties Micropub properties.
	 * @return string|false public, private or protected, or false if none or an unknown value was sent.
	 */
	protected static function requested_visibility( $properties ) {
		if ( ! is_array( $properties ) || ! isset( $properties['location-visibility'] ) ) {
			return false;
		}
		$visibility = $properties['location-visibility'];
		if ( is_array( $visibility ) ) {
			$visibility = end( $visibility );
		}
		if ( in_array( $visibility, array( 'public', 'private', 'protected' ), true ) ) {
			return $visibility;
		}
		return false;
	}

	/**
	 * Returns a value from an array, or the first item of a list value.
	 *
	 * @since 5.0.11
	 *
	 * @param array   $array   The array.
	 * @param string  $key     Key.
	 * @param mixed   $default Value without the key. Default empty array.
	 * @param boolean $index   Whether to return the first item of a list. Default true.
	 * @return mixed The value.
	 */
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
	 * Adds location properties to webmention returns.
	 *
	 * @since 6.0.0 A location that is a string, or a location or h-geo without properties, no longer causes errors.
	 * @since 6.0.0 Coordinates are only kept if both are valid and in range, and altitude and accuracy only if numeric
	 *              (and accuracy only if not negative).
	 *
	 * @param array  $meta    Comment meta.
	 * @param object $handler Webmention handler.
	 * @return array Comment meta.
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

		$location = $mf_array['properties']['location'] ?? null;
		if ( ! wp_is_numeric_array( $location ) ) {
			return $meta;
		}
		$location = $location[0];

		// A plain-text location is an address; a URL can't be used.
		if ( is_string( $location ) ) {
			if ( 'http' !== substr( $location, 0, 4 ) ) {
				$meta['geo_address'] = $location;
			}
			return $meta;
		}
		if ( ! is_array( $location ) || ! isset( $location['properties'] ) || ! is_array( $location['properties'] ) ) {
			return $meta;
		}

		$props = $location['properties'];
		if ( isset( $props['geo'] ) ) {
			$label = array_key_exists( 'label', $props ) ? $handler->get_first_array_item( $props['label'] ) : null;
			if ( is_string( $label ) ) {
				$meta['geo_address'] = $label;
			}
			$geo = $handler->get_first_array_item( $props['geo'] );
			if ( ! is_array( $geo ) || ! isset( $geo['properties'] ) || ! is_array( $geo['properties'] ) ) {
				return $meta;
			}
			$props = $geo['properties'];
		} else {
			$parts = array(
				static::array_get( $props, 'name', array(), true ),
				static::array_get( $props, 'street-address', array(), true ),
				static::array_get( $props, 'locality', array(), true ),
				static::array_get( $props, 'region', array(), true ),
				static::array_get( $props, 'postal-code', array(), true ),
				static::array_get( $props, 'country-name', array(), true ),
			);
			$parts = array_filter( $parts, 'is_string' );
			$parts = array_filter( $parts );
			if ( ! empty( $parts ) ) {
				$meta['geo_address'] = implode( ', ', $parts );
			}
		}
		// The source is untrusted, so only keep coordinates that are valid and in range.
		$latitude  = clean_coordinate( static::array_get( $props, 'latitude', null, true ), 'latitude' );
		$longitude = clean_coordinate( static::array_get( $props, 'longitude', null, true ), 'longitude' );
		if ( false === $latitude || false === $longitude ) {
			return $meta;
		}
		$meta['geo_latitude']  = $latitude;
		$meta['geo_longitude'] = $longitude;
		foreach ( array( 'altitude', 'accuracy' ) as $property ) {
			$value = static::array_get( $props, $property, null, true );
			if ( ! is_numeric( $value ) || ! is_finite( (float) $value ) ) {
				continue;
			}
			// Accuracy is a radius in meters, so it can't be negative.
			if ( 'accuracy' === $property && 0 > (float) $value ) {
				continue;
			}
			$meta[ 'geo_' . $property ] = (float) $value;
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
	 * @since 6.1.0 A venue's visibility for posts made there is used, unless the client asked for one.
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

		if ( isset( $meta['geo_latitude'], $meta['geo_longitude'] ) ) {
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
			$conditions = Sloc_Provider_Chain::weather( $meta['geo_latitude'], $meta['geo_longitude'], $published->getTimestamp() );
			if ( ! empty( $conditions ) && ! is_wp_error( $conditions ) ) {
				// if debug mode is on remove the raw data from storage.
				unset( $conditions['raw'] );
				set_post_weatherdata( $args['ID'], '', $conditions );
			}

			$venue = Post_Venue::at_venue( $meta['geo_latitude'], $meta['geo_longitude'] );
			if ( false !== $venue ) {
				update_post_meta( $args['ID'], 'venue_id', $venue );
			}

			$requested = self::requested_visibility( $properties );
			if ( $requested ) {
				// The client asked for a visibility, so use it.
				set_post_geodata( $args['ID'], 'visibility', $requested );
			} elseif ( false !== $venue ) {
				// The venue's visibility for posts made there, or only the venue's name.
				$visibility = Post_Venue::get_post_visibility( $venue );
				set_post_geodata( $args['ID'], 'visibility', '' === $visibility ? 'protected' : $visibility );
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
				$visibility = Post_Venue::get_post_visibility( $venue );
				set_post_geodata( $args['ID'], 'visibility', '' === $visibility ? get_post_geodata( $venue, 'visibility' ) : $visibility );
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
						Location_Taxonomy::set_location( $args['ID'], $term );
					}
				}
			}
		}

		if ( ! empty( array_intersect( array_keys( $location ), array( 'region', 'country-name' ) ) ) ) {
			if ( ! $term ) {
				$term = Location_Taxonomy::get_location( $location, true );
			}
			Location_Taxonomy::set_location( $args['ID'], $term );
		} elseif ( isset( $location['latitude'] ) && isset( $location['longitude'] ) && is_object( $reverse ) ) {
			$reverse_adr = Sloc_Provider_Chain::reverse_lookup( $location['latitude'], $location['longitude'] );
			if ( ! is_array( $reverse_adr ) ) {
				// The lookup failed; keep what the client sent.
				$reverse_adr = array();
			}
			// Keep an altitude the client sent; look it up when there is none, or it's 0 or NaN, which some clients send instead.
			if ( isset( $meta['geo_altitude'] ) && ( ! is_numeric( $meta['geo_altitude'] ) || 0.0 === (float) $meta['geo_altitude'] ) ) {
				unset( $meta['geo_altitude'] );
			}
			if ( ! isset( $meta['geo_altitude'] ) && isset( $meta['geo_latitude'] ) && isset( $meta['geo_longitude'] ) ) {
				$elevation = Sloc_Provider_Chain::elevation( $meta['geo_latitude'], $meta['geo_longitude'] );
				// Store only a number, never an error from the elevation lookup.
				if ( is_numeric( $elevation ) ) {
					update_post_meta( $args['ID'], 'geo_altitude', $elevation );
				}
			}
			if ( ! $term && ! empty( $reverse_adr ) ) {
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
		if ( ! isset( $input['q'] ) || 'geo' !== $input['q'] ) {
			return $resp;
		}
		if ( isset( $input['uri'] ) && 'geo:' === substr( $input['uri'], 0, 4 ) ) {
			/*
			 *  Geo URI format:
			 * http://en.wikipedia.org/wiki/Geo_URI#Example
			 * e.g. geo:37.786971,-122.399677;u=35
			 */
			$geo    = explode( ':', substr( urldecode( $input['uri'] ), 4 ) );
			$geo    = explode( ';', $geo[0] );
			$coords = explode( ',', $geo[0] );
			if ( 2 <= count( $coords ) ) {
				$input['lat'] = trim( $coords[0] );
				$input['lon'] = trim( $coords[1] );
			}
		}
		if ( ! isset( $input['lat'], $input['lon'] ) || ! is_numeric( $input['lat'] ) || ! is_numeric( $input['lon'] ) ) {
			return $resp;
		}
		$lat = round( (float) $input['lat'], 7 );
		$lon = round( (float) $input['lon'], 7 );

		$venues      = array();
		$reverse_adr = array();
		if ( Loc_Config::geo_provider() ) {
			$reverse_adr = Sloc_Provider_Chain::reverse_lookup( $lat, $lon );
		}
		$venue = Loc_Config::venue_provider();
		if ( is_object( $venue ) ) {
			$venue->set( $lat, $lon );
			$venues = $venue->nearby();
		}

		// Use the site default, but at a private venue show the place without the coordinates (see Geo_Data::get_attachment_visibility()).
		$visibility = Geo_Data::get_default_visibility();
		$venue_id   = Post_Venue::at_venue( $lat, $lon );
		if ( $venue_id && 'public' === $visibility && 'private' === get_post_geodata( $venue_id, 'visibility' ) ) {
			$visibility = 'protected';
		}

		return array(
			'venues' => is_array( $venues ) ? $venues : array(),
			'geo'    => array(
				'label'      => is_array( $reverse_adr ) && isset( $reverse_adr['display-name'] ) ? $reverse_adr['display-name'] : null,
				'latitude'   => $lat,
				'longitude'  => $lon,
				'visibility' => $visibility,
			),
		);
	}
} // End Class Kind_Plugins

new Location_Plugins();

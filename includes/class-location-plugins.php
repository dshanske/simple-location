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
		add_filter( 'micropub_query', array( 'Location_Plugins', 'micropub_query_places' ), 10, 2 );
		add_filter( 'micropub_query', array( 'Location_Plugins', 'micropub_query_geo' ), 10, 2 );
		add_filter( 'micropub_query', array( 'Location_Plugins', 'micropub_query_source' ), 10, 2 );
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
	 * @since 6.4.0 A check-in at a venue keeps the visibility the client asked for, with or without coordinates (#841).
	 * @since 6.3.0 A check-in or location h-card is matched to a venue by its page on the site, then its identifier;
	 *              a private venue only for a user who can edit it.
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
		$location = wp_array_slice_assoc( $location, array( 'street-address', 'extended-address', 'post-office-box', 'locality', 'region', 'postal-code', 'country-name', 'country-code', 'region-code', 'region', 'latitude', 'longitude', 'altitude', 'name', 'label', 'url', 'uid', 'provider' ) );
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
			// A check-in names the place; an address label names it when there is no name.
			if ( empty( $location['name'] ) && ! empty( $location['label'] ) ) {
				$location['name'] = $location['label'];
			}
			// The venue the check-in is at, by its page, identifier or name nearby, or a new one.
			$venue = Post_Venue::choose_place( $location );
			if ( ! is_wp_error( $venue ) ) {
				Post_Venue::set_post_venue( $args['ID'], $venue );
				// The visibility the client asked for, or the venue's for posts made there, or the venue's own.
				$visibility = self::requested_visibility( $properties );
				$visibility = $visibility ? $visibility : Post_Venue::get_post_visibility( $venue );
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
		} elseif ( isset( $location['url'] ) || isset( $location['uid'] ) ) {
			// A location that is a venue, by its page or identifier, such as a place from q=places.
			$venue = Post_Venue::find_place( $location );
			if ( $venue ) {
				Post_Venue::set_post_venue( $args['ID'], $venue );
				$visibility = self::requested_visibility( $properties );
				$visibility = $visibility ? $visibility : Post_Venue::get_post_visibility( $venue );
				set_post_geodata( $args['ID'], 'visibility', '' === $visibility ? 'protected' : $visibility );
				$term = $term ? $term : Location_Taxonomy::get_post_location( $args['ID'] );
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
	 * Answers the experimental Micropub places query, and lists it in the configuration query.
	 *
	 * `q=places` takes the location as `lat` and `lon`, or as a geo URI in `uri`, and optionally `limit`, the most
	 * places; `offset`, how many places at the start of the list to skip, as proposed for list queries in
	 * https://github.com/indieweb/micropub-extensions/issues/36; `radius`, the search radius in meters, which
	 * providers that don't take one ignore; `filter`, text the places' names should match, as proposed in
	 * https://github.com/indieweb/micropub-extensions/issues/34; and `local`, to list only the site's venues. It
	 * answers with the places as mf2 h-cards under `places`; see Post_Venue::places().
	 *
	 * @since 6.3.0 Replaces the experimental `q=geo` query.
	 * @since 6.4.0 Takes `offset`, `radius` and `filter`.
	 *
	 * @param array|WP_Error $resp  The response before filtering.
	 * @param array          $input The Micropub input.
	 * @return array|WP_Error The response, the places, or an error without a location.
	 */
	public static function micropub_query_places( $resp, $input ) {
		$query = $input['q'] ?? null;
		if ( 'config' === $query && is_array( $resp ) ) {
			$resp['q'] = is_array( $resp['q'] ?? null ) ? $resp['q'] : array();
			if ( ! in_array( 'places', $resp['q'], true ) ) {
				$resp['q'][] = 'places';
			}
			return $resp;
		}
		if ( 'places' !== $query ) {
			return $resp;
		}
		$coords = self::micropub_coordinates( $input );
		if ( ! $coords ) {
			return new WP_Error( 'invalid_request', __( 'The places query needs a location, as lat and lon or a geo URI.', 'simple-location' ), array( 'status' => 400 ) );
		}
		$args = array(
			'local' => is_scalar( $input['local'] ?? null ) && rest_sanitize_boolean( $input['local'] ),
		);
		foreach ( array( 'limit', 'offset', 'radius' ) as $key ) {
			if ( is_scalar( $input[ $key ] ?? null ) && is_numeric( $input[ $key ] ) ) {
				$args[ $key ] = $input[ $key ];
			}
		}
		if ( is_string( $input['filter'] ?? null ) ) {
			$args['filter'] = $input['filter'];
		}
		return array(
			'places' => Post_Venue::places( $coords[0], $coords[1], $args ),
		);
	}

	/**
	 * Adds a venue's details to the Micropub source query for its page.
	 *
	 * The Micropub plugin answers `q=source&url=` for a venue with little more than its name and content. This makes
	 * the answer an h-card with the venue's details; see Post_Venue::venue_card(). It also answers for a venue's page
	 * that the Micropub plugin doesn't find, such as one with plain permalinks. `properties` limits the properties, as
	 * it does for other posts. A venue the user may not see is left to the Micropub plugin's answer.
	 *
	 * @since 6.4.0
	 *
	 * @param mixed $resp  The response before filtering: an array, or the Micropub plugin's error.
	 * @param array $input The Micropub input.
	 * @return mixed The response, with the venue's details for a venue.
	 */
	public static function micropub_query_source( $resp, $input ) {
		if ( 'source' !== ( $input['q'] ?? null ) || ! is_string( $input['url'] ?? null ) || is_wp_error( $resp ) ) {
			return $resp;
		}
		$card = Post_Venue::venue_card( Post_Venue::url_to_postid( $input['url'] ) );
		if ( ! $card ) {
			return $resp;
		}
		$resp = is_array( $resp ) ? $resp : array();

		$properties = array_merge( is_array( $resp['properties'] ?? null ) ? $resp['properties'] : array(), $card['properties'] );
		$wanted     = $input['properties'] ?? null;
		if ( null !== $wanted ) {
			$wanted = array_filter( (array) $wanted, 'is_string' );
			return array( 'properties' => array_intersect_key( $properties, array_flip( $wanted ) ) );
		}
		$resp['type']       = array( 'h-card' );
		$resp['properties'] = $properties;
		if ( ! empty( $card['children'] ) ) {
			$resp['children'] = $card['children'];
		}
		return $resp;
	}

	/**
	 * Answers the experimental Micropub geo query, and lists it in the configuration query.
	 *
	 * `q=geo` takes a location as one of: `url`, a post or venue on the site, whose location the user may see; `airport`,
	 * an airport's three-letter IATA code; `plus_code`, a full Plus Code, or a short one with the place after it; or `lat`
	 * and `lon`, or a geo URI in `uri`. Optionally `width`, `height`, `zoom` and `style` set the static map. It answers
	 * with `geo`: `latitude`, `longitude`, `plus-code` (a full code of 10 digits), `elevation` in meters, `timezone`,
	 * `localtime` (ISO 8601), `address` as an mf2 h-card, and `map`, the URL of a static map image on the site, which
	 * never holds a provider's API key. A value that can't be found is left out.
	 *
	 * The coordinates, Plus Code, timezone, local time and an airport's details come from the site. The address, an
	 * elevation that isn't known, the map, and the place after a short Plus Code ask the providers, so they need a user who can edit posts, as an IndieAuth token
	 * with the create or draft scope can.
	 *
	 * @since 6.4.0 Replaces the experimental `q=geo` query removed in 6.3.0, without its venues; see `q=places`.
	 *
	 * @param array|WP_Error $resp  The response before filtering.
	 * @param array          $input The Micropub input.
	 * @return array|WP_Error The response, the location, or an error without a location.
	 */
	public static function micropub_query_geo( $resp, $input ) {
		$query = $input['q'] ?? null;
		if ( 'config' === $query && is_array( $resp ) ) {
			$resp['q'] = is_array( $resp['q'] ?? null ) ? $resp['q'] : array();
			if ( ! in_array( 'geo', $resp['q'], true ) ) {
				$resp['q'][] = 'geo';
			}
			return $resp;
		}
		if ( 'geo' !== $query ) {
			return $resp;
		}
		$location = self::micropub_location( $input );
		if ( is_wp_error( $location ) ) {
			return $location;
		}
		$lat     = $location['latitude'];
		$lon     = $location['longitude'];
		$lookups = current_user_can( 'edit_posts' );

		$code = Sloc_Plus_Code::encode( $lat, $lon );
		$geo  = array(
			'latitude'  => $lat,
			'longitude' => $lon,
			'plus-code' => is_string( $code ) ? $code : null,
			'elevation' => $location['altitude'] ?? null,
		);
		if ( null === $geo['elevation'] && $lookups && Loc_Config::elevation_provider() ) {
			$elevation        = Sloc_Provider_Chain::elevation( $lat, $lon );
			$geo['elevation'] = is_numeric( $elevation ) ? round( (float) $elevation, 1 ) : null;
		}

		$timezone = Loc_Timezone::timezone_for_location( $lat, $lon );
		if ( $timezone instanceof Timezone_Result ) {
			$geo['timezone']  = $timezone->name;
			$geo['localtime'] = $timezone->localtime;
		}

		$address = $location['address'] ?? array();
		if ( $lookups && Loc_Config::geo_provider() ) {
			$found = Sloc_Provider_Chain::reverse_lookup( $lat, $lon );
			if ( is_array( $found ) && ! empty( $found ) ) {
				// What the source knows, such as an airport's name, comes first.
				$address = array_merge( $found, array_filter( $address ) );
			}
		}
		$geo['address'] = self::micropub_address( $address );

		if ( $lookups ) {
			$geo['map'] = self::micropub_map( $location, $input );
		}

		return array( 'geo' => array_filter( $geo, fn( $value ) => null !== $value && '' !== $value && array() !== $value ) );
	}

	/**
	 * Returns the location a Micropub geo query asks about, from a URL on the site, an airport or coordinates.
	 *
	 * @since 6.4.0
	 *
	 * @param array $input The Micropub input, with `url`, `airport`, `plus_code`, or `lat` and `lon` or `uri`; the first
	 *                     given is used.
	 * @return array|WP_Error 'latitude', 'longitude', and where known 'altitude', 'address' (h-card properties),
	 *                        'map_zoom' and 'map_style'; or an error.
	 */
	public static function micropub_location( $input ) {
		if ( array_key_exists( 'url', $input ) ) {
			$post_id = is_string( $input['url'] ) ? Post_Venue::url_to_postid( $input['url'] ) : 0;
			$loc     = $post_id ? REST_Geo::object_location( array( 'post_id' => $post_id ) ) : null;
			if ( ! is_array( $loc ) ) {
				// Whether the post exists isn't revealed when its location can't be seen.
				return new WP_Error( 'invalid_request', __( 'There is no location for this URL.', 'simple-location' ), array( 'status' => 400 ) );
			}
			return array(
				'latitude'  => round( (float) $loc['latitude'], 7 ),
				'longitude' => round( (float) $loc['longitude'], 7 ),
				'altitude'  => is_numeric( $loc['altitude'] ?? null ) ? (float) $loc['altitude'] : null,
				'address'   => array( 'label' => is_string( $loc['address'] ?? null ) ? $loc['address'] : '' ),
				'map_zoom'  => $loc['map_zoom'] ?? null,
				'map_style' => $loc['map_style'] ?? '',
			);
		}
		if ( array_key_exists( 'airport', $input ) ) {
			$code    = is_string( $input['airport'] ) ? strtoupper( trim( $input['airport'] ) ) : '';
			$airport = preg_match( '/^[A-Z]{3}$/', $code ) ? Airport_Location::get( $code, 'iata_code' ) : null;
			if ( ! is_array( $airport ) || ! is_numeric( $airport['latitude'] ?? null ) || ! is_numeric( $airport['longitude'] ?? null ) ) {
				return new WP_Error( 'invalid_request', __( 'This airport code was not found.', 'simple-location' ), array( 'status' => 400 ) );
			}
			return array(
				'latitude'  => round( (float) $airport['latitude'], 7 ),
				'longitude' => round( (float) $airport['longitude'], 7 ),
				'altitude'  => is_numeric( $airport['elevation'] ?? null ) ? (float) $airport['elevation'] : null,
				'address'   => array(
					'name'         => is_string( $airport['name'] ?? null ) ? $airport['name'] : '',
					'locality'     => is_string( $airport['municipality'] ?? null ) ? $airport['municipality'] : '',
					'country-code' => is_string( $airport['iso_country'] ?? null ) ? $airport['iso_country'] : '',
				),
			);
		}
		if ( array_key_exists( 'plus_code', $input ) ) {
			$parsed = Sloc_Plus_Code::parse( $input['plus_code'] );
			if ( ! $parsed ) {
				return new WP_Error( 'invalid_request', __( 'This is not a valid Plus Code.', 'simple-location' ), array( 'status' => 400 ) );
			}
			// A short code's place is found by the geo provider.
			if ( ! Sloc_Plus_Code::is_full( $parsed['code'] ) && ! current_user_can( 'edit_posts' ) ) {
				return new WP_Error( 'invalid_request', __( 'Send a full Plus Code, such as 87F6XR2P+WH.', 'simple-location' ), array( 'status' => 400 ) );
			}
			$area = Sloc_Plus_Code::locate( $input['plus_code'] );
			if ( is_wp_error( $area ) ) {
				return new WP_Error( 'invalid_request', $area->get_error_message(), array( 'status' => 400 ) );
			}
			return array(
				'latitude'  => round( (float) $area['latitude'], 7 ),
				'longitude' => round( (float) $area['longitude'], 7 ),
			);
		}
		$coords = self::micropub_coordinates( $input );
		if ( ! $coords ) {
			return new WP_Error( 'invalid_request', __( 'The geo query needs a location: url, airport, plus_code, lat and lon, or a geo URI.', 'simple-location' ), array( 'status' => 400 ) );
		}
		return array(
			'latitude'  => $coords[0],
			'longitude' => $coords[1],
		);
	}

	/**
	 * Returns an address as an mf2 h-card, with only the address properties.
	 *
	 * @since 6.4.0
	 *
	 * @param array $address Address properties, as a geo provider's reverse_lookup() returns them; 'display-name'
	 *                       becomes 'label' where there is none.
	 * @return array|null mf2 h-card, or null without any address.
	 */
	protected static function micropub_address( $address ) {
		if ( ! is_array( $address ) ) {
			return null;
		}
		if ( empty( $address['label'] ) && ! empty( $address['display-name'] ) ) {
			$address['label'] = $address['display-name'];
		}
		$keys = array( 'name', 'label', 'street-address', 'extended-address', 'locality', 'region', 'postal-code', 'country-name', 'country-code' );
		$card = array_filter(
			array_intersect_key( $address, array_flip( $keys ) ),
			fn( $value ) => is_string( $value ) && '' !== trim( $value )
		);
		if ( empty( $card ) ) {
			return null;
		}
		return Post_Venue::jf2_to_mf2( array_merge( array( 'type' => 'card' ), $card ) );
	}

	/**
	 * Returns the URL of a static map of a location, served from the site.
	 *
	 * @since 6.4.0
	 *
	 * @param array $location Location, as micropub_location() returns it.
	 * @param array $input    The Micropub input, with optional `width`, `height`, `zoom` and `style`.
	 * @return string|null Signed URL of the map image route, which never holds the provider's API key; or null without
	 *                     a map provider.
	 */
	protected static function micropub_map( $location, $input ) {
		$map = Loc_Config::map_provider();
		if ( ! $map instanceof Map_Provider ) {
			return null;
		}
		$args = array(
			'latitude'     => $location['latitude'],
			'longitude'    => $location['longitude'],
			'style_family' => $location['map_style'] ?? '',
		);
		if ( ! empty( $location['map_zoom'] ) ) {
			$args['map_zoom'] = $location['map_zoom'];
		}
		foreach ( array(
			'width'  => 'width',
			'height' => 'height',
			'zoom'   => 'map_zoom',
		) as $key => $arg ) {
			if ( is_scalar( $input[ $key ] ?? null ) && is_numeric( $input[ $key ] ) && 0 < (int) $input[ $key ] ) {
				$args[ $arg ] = (int) $input[ $key ];
			}
		}
		if ( is_string( $input['style'] ?? null ) ) {
			$args['style_family'] = $input['style'];
		}
		$args['style_family'] = Map_Provider::sanitize_map_style( $args['style_family'] );
		// A copy, so the request leaves the shared provider as it was.
		$map = clone $map;
		$map->set( $args );
		return Sloc_Map_Cache::get_url( $map, null, true );
	}

	/**
	 * Returns the location a Micropub query asks about.
	 *
	 * @since 6.3.0
	 *
	 * @param array $input The Micropub input, with `lat` and `lon`, or a geo URI in `uri` such as geo:37.786971,-122.399677;u=35.
	 * @return float[]|false Latitude and longitude, rounded to 7 places, or false without a valid location.
	 */
	public static function micropub_coordinates( $input ) {
		$lat = $input['lat'] ?? null;
		$lon = $input['lon'] ?? null;
		if ( is_string( $input['uri'] ?? null ) && 'geo:' === substr( $input['uri'], 0, 4 ) ) {
			// See https://en.wikipedia.org/wiki/Geo_URI_scheme.
			$geo    = explode( ';', substr( urldecode( $input['uri'] ), 4 ) );
			$coords = explode( ',', $geo[0] );
			if ( 2 <= count( $coords ) ) {
				$lat = trim( $coords[0] );
				$lon = trim( $coords[1] );
			}
		}
		if ( ! is_scalar( $lat ) || ! is_scalar( $lon ) || ! is_numeric( $lat ) || ! is_numeric( $lon ) ) {
			return false;
		}
		$lat = clean_coordinate( $lat, 'latitude' );
		$lon = clean_coordinate( $lon, 'longitude' );
		if ( false === $lat || false === $lon ) {
			return false;
		}
		return array( round( (float) $lat, 7 ), round( (float) $lon, 7 ) );
	}
} // End Class Kind_Plugins

new Location_Plugins();

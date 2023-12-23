<?php
/**
 * Geographical Metadata
 *
 * Registers geographic metadata and supplies functions to assist in manipulating it.
 *
 * @package Simple Location
 */

add_action( 'init', array( 'Geo_Data', 'init' ), 1 );

/**
 * Handles Geo Functionality for WordPress objects.
 *
 * @since 1.0.0
 */
class Geo_Data {


	public static $properties = array(
		'latitude',  // Decimal Latitude
		'longitude', // Decimal Longitude
		'altitude',  // Altitude in meters.
		'address',  // Textual Display of Location
		'zoom', // Zoom for Map Display
		'speed',  // Speed in meters.
		'heading', // If set, between 0 and 360 degrees.
		'visibility', // Can either be public, private, or protected.
		'timezone', // Timezone String
		'icon', // Icon representing location
		'day', // Boolean identifying if it is daytime or not
	);

	/**
	 * Geo Data Initialization Function.
	 *
	 * Meant to be attached to init hook. Sets up all the geodata enhancements.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		self::register_meta();

		add_action( 'pre_get_posts', array( __CLASS__, 'remove_maps_pagination' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_location_posts' ) );
		add_action( 'pre_get_comments', array( __CLASS__, 'pre_get_comments' ) );

		add_filter( 'get_comment_text', array( __CLASS__, 'location_comment' ), 12, 2 );
		add_filter( 'the_content', array( __CLASS__, 'content_map' ), 11 );
		if ( ! current_theme_supports( 'simple-location' ) ) {
			add_filter( 'the_content', array( __CLASS__, 'location_content' ), 12 );
		}
	}


	/**
	 * Removes the Pagination from the Map Archive Page.
	 *
	 * Filter query variables.
	 *
	 * @param WP_Query $query Query Object.
	 *
	 * @since 4.0.0
	 */
	public static function remove_maps_pagination( $query ) {
		if ( ! array_key_exists( 'map', $query->query_vars ) || ! $query->is_main_query() ) {
			return;
		}
		$query->set( 'meta_query', array( self::filter_geo_query( 'map' ) ) );
		$query->set( 'posts_per_page', SLOC_PER_PAGE );
		$query->set( 'order', 'ASC' );
	}

	/**
	 * Retrieves default visibility option
	 */
	public static function get_default_visibility() {
		$status = (int) get_option( 'geo_public' );
		switch ( (int) $status ) {
			case 0:
				return 'private';
			case 1:
				return 'public';
			case 2:
				return 'protected';
			default:
				return false;
		}
	}

	/**
	 * Return meta query arguments based on input.
	 *
	 * @param array $geo WP_Query arguments.
	 *
	 * @since 1.0.0
	 */
	public static function filter_geo_query( $geo ) {
		$args   = array(
			'relation' => 'OR',
			array(
				'key'     => 'geo_longitude',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => 'geo_address',
				'compare' => 'EXISTS',
			),
		);
		$public = array(
			'key'     => 'geo_public',
			'type'    => 'numeric',
			'compare' => '=',
		);
		switch ( $geo ) {
			case 'all':
				return $args;
			case 'private':
				$public['value'] = (int) 0;
				return $public;
			case 'public':
			case 'map':
				$public['value'] = (int) 1;
				return $public;
			case 'text':
			case 'description':
			case 'protected':
				$public['value'] = (int) 2;
				return $public;
			default:
				return array();
		}
	}


	/**
	 * Filters Location in Posts.
	 *
	 * @param WP_Query $query Query Object.
	 *
	 * @since 1.0.0
	 */
	public static function filter_location_posts( $query ) {
		if ( ! array_key_exists( 'geo', $query->query_vars ) || ! $query->is_main_query() ) {
			return;
		}

		$geo  = $query->get( 'geo' );
		$args = self::filter_geo_query( $geo );
		if ( ! empty( $args ) ) {
			$query->set( 'meta_query', array( $args ) );
		}
	}


	/**
	 * Filters Location in Comments.
	 *
	 * @param WP_Query $query Query Object.
	 *
	 * @since 1.0.0
	 */
	public static function pre_get_comments( $query ) {
		if ( ! isset( $_REQUEST['geo'] ) ) {
			return;
		}
		$geo  = sanitize_text_field( $_REQUEST['geo'] );
		$args = self::filter_geo_query( $geo );
		if ( ! empty( $args ) ) {
			$query->query_vars['meta_query'] = array( $args );
			$query->meta_query->parse_query_vars( $query->query_vars );
		}
	}


	/**
	 * Delete a Single Piece of GeoData.
	 *
	 * @param string $type
	 * @param int    $id
	 * @param array  $geodata {
	 *   An array of details about a location.
	 *
	 * }
	 * @return WP_Error|boolean Return success or WP_Error.
	 *
	 * @since 1.0.0
	 */
	public static function delete_geodata( $type, $id, $key ) {
		if ( ! $type || ! is_numeric( $id ) ) {
			return false;
		}

		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		if ( ! empty( $key ) && ! in_array( $key, static::$properties, true ) ) {
			return false;
		}

		/**
		 * Short-circuits the deleting of a field
		 *
		 * The dynamic portion of the hook name, `$type`, refers to the object type
		 * (post, comment, term, user, or any other type with associated geo data).
		 * Returning a non-null value will effectively short-circuit the function.
		 *
		 * Possible filter names include:
		 *
		 *  - `delete_post_geodata`
		 *  - `delete_comment_geodata`
		 *  - `delete_term_geodata`
		 *  - `delete_user_geodata`
		 *
		 * @param mixed  $value     The value to return, either a single value or an array
		 *                          of values depending on the value of `$single`. Default null.
		 * @param string $type Type of object data is for. Accepts 'post', 'comment', 'term', 'user',
		 *                          or any other object type with an associated meta table.
		 * @param int    $id ID of the object geodata data is for.
		 * @param string $key  Geo data key.
		 */
		$check = apply_filters( "delete_{$type}_geodata", null, $type, $id, $key );

		return delete_metadata( $type, $id, 'geo_' . $key );
	}

	/**
	 * Set GeoData.
	 *
	 * @param string $type
	 * @param int    $id
	 * @param array  $geodata {
	 *   An array of details about a location.
	 *
	 * }
	 * @return WP_Error|boolean Return success or WP_Error.
	 *
	 * @since 1.0.0
	 */
	public static function set_geodata( $type, $id, $key, $geodata ) {
		if ( ! $type || ! is_numeric( $id ) ) {
			return false;
		}

		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}

		if ( 'map_zoom' === $key ) {
			$key = 'zoom';
		}

		if ( ! empty( $key ) && ! in_array( $key, static::$properties, true ) ) {
			return false;
		}

		/**
		 * Short-circuits the setting of a geodata field.
		 *
		 * The dynamic portion of the hook name, `$type`, refers to the object type
		 * (post, comment, term, user, or any other type with associated geo data).
		 * Returning a non-null value will effectively short-circuit the function.
		 *
		 * Possible filter names include:
		 *
		 *  - `set_post_geodata`
		 *  - `set_comment_geodata`
		 *  - `set_term_geodata`
		 *  - `set_user_geodata`
		 *
		 * @param mixed  $value     The value to return, either a single value or an array
		 *                          of values depending on the value of `$single`. Default null.
		 * @param int    $id ID of the object weather data is for.
		 * @param string $type Type of object data is for. Accepts 'post', 'comment', 'term', 'user',
		 *                          or any other object type with an associated meta table.
		 * @param string $key  Geo data key.
		 * @param string $geodata Value.
		 */
		$check = apply_filters( "set_{$type}_geodata", null, $id, $key, $type, $geodata );

		if ( null !== $check ) {
			return $check;
		}

		if ( 'visibility' === $key ) {
			switch ( $geodata ) {
				case '0':
				case 'private':
					$geodata = '0';
					break;
				case '1':
				case 'public':
					$geodata = '1';
					break;
				case '2':
				case 'protected':
					$geodata = '2';
					break;
				default:
					delete_metadata( $type, $id, 'geo_public' );
					return false;
			}
			return update_metadata( $type, $id, 'geo_public', $geodata );
		}

		if ( $key ) {
			return update_metadata( $type, $id, 'geo_' . $key, $geodata );
		}

		if ( empty( $key ) && ( is_array( $geodata ) ) ) {
			if ( array_key_exists( 'map_zoom', $geodata ) ) {
				$geodata['zoom'] = $geodata['map_zoom'];
			}
			$geodata = wp_array_slice_assoc( $geodata, static::$properties );
		}

		if ( isset( $geodata['visibility'] ) ) {
			self::set_geodata( $type, $id, 'visibility', $geodata['visibility'] );
			unset( $geodata['visibility'] );
		}
		foreach ( $geodata as $key => $value ) {
			if ( ! empty( $value ) ) {
				update_metadata( $type, $id, 'geo_' . $key, $value );
			} else {
				delete_metadata( $type, $id, 'geo_' . $key );
			}
		}

		return true;
	}

	/**
	 * Get Latitude and Longitude on an Object.
	 *
	 * @param string $type Object Type
	 * @param int    $id Object ID
	 * @return array The first index is the latitude, the second the longitude, the third the altitude(optional).
	 *
	 * @since 5.0.0
	 */
	public static function get_geopoint( $type, $id ) {
		$latitude  = self::get_geodata( $type, $id, 'latitude' );
		$longitude = self::get_geodata( $type, $id, 'longitude' );
		$altitude  = self::get_geodata( $type, $id, 'altitude' );

		if ( ! $latitude || ! $longitude ) {
			return false;
		}

		if ( $altitude ) {
			return array( $latitude, $longitude, $altitude );
		}

		return array( $latitude, $longitude );
	}

	/**
	 * Does this object have location data.
	 *
	 * @param mixed $object Object type.
	 * @return WP_Error|boolean Return success or WP_Error.
	 *
	 * @since 1.0.0
	 */
	public static function has_location( $type, $id ) {
		return is_array( self::get_geopoint( $type, $id ) );
	}

	/**
	 * Get a GeoURI for an Object.
	 *
	 * @param string $type Object Type
	 * @param int    $id Object ID
	 * @return string|boolean Geo URI or false if no location.
	 *
	 * @since 5.0.0
	 */
	public static function get_geouri( $type, $id ) {
		$point = self::get_geopoint( $type, $id );
		return 'geo:' . implode( ',', $point );
	}


	/**
	 * Parse a GEO URI into properties
	 */
	public static function parse_geo_uri( $uri ) {
		if ( ! is_string( $uri ) ) {
			return $uri;
		}
		// Ensure this is a geo uri
		if ( 'geo:' !== substr( $uri, 0, 4 ) ) {
			return $uri;
		}
		$properties = array();
		// Geo URI format:
		// http://en.wikipedia.org/wiki/Geo_URI#Example
		// https://indieweb.org/Micropub#h-entry
		//
		// e.g. geo:37.786971,-122.399677;u=35
		$geo                     = str_replace( 'geo:', '', urldecode( $uri ) );
		$geo                     = explode( ';', $geo );
		$coords                  = explode( ',', $geo[0] );
		$properties['latitude']  = trim( $coords[0] );
		$properties['longitude'] = trim( $coords[1] );
		// Geo URI optionally allows for altitude to be stored as a third csv
		if ( isset( $coords[2] ) ) {
			$properties['altitude'] = trim( $coords[2] );
		}
		// Store additional parameters
		array_shift( $geo ); // Remove coordinates to check for other parameters
		foreach ( $geo as $g ) {
			$g = explode( '=', $g );
			if ( 'u' === $g[0] ) {
				$g[0] = 'accuracy';
			}
			if ( 'name' === $g[0] ) {
				$g[0] = 'address';
			}
			$properties[ $g[0] ] = $g[1];
		}
		return $properties;
	}

	/**
	 * Get GeoData on an Object.
	 *
	 * @param mixed  $object Can be WP_Comment, WP_User, WP_Post, WP_Term, or int which will be considered a post id.
	 * @param string $key Key. Optional. If empty returns all GeoData.
	 * @return array $geodata {
	 *  An array of details about a location.
	 *
	 *  @type float $latitude Decimal Latitude.
	 *  @type float $longitude Decimal Longitude.
	 *  @type float $altitude Altitude in Meters.
	 *  @type string $address Textual Description of location.
	 *  @type int $map_zoom Zoom for Map Display.
	 *  @type float $speed Speed in Meters.
	 *  @type float $heading If set, between 0 and 360 degrees.
	 *  @type string $wikipedia_link URL of the Airport Homepage
	 *  @type string $visibility Can be either public, private, or protected.
	 *  @type string $timezone Timezone string.
	 *  @type array $weather Array of Weather Properties.
	 * }
	 *
	 * @since 1.0.0
	 */
	public static function get_geodata( $type, $id, $key = '' ) {
		if ( ! $type || ! is_numeric( $id ) ) {
			return false;
		}

		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		if ( ! empty( $key ) && ! in_array( $key, static::$properties, true ) ) {
			return false;
		}

		/**
		 * Short-circuits the return value of a geodata field.
		 *
		 * The dynamic portion of the hook name, `$type`, refers to the object type
		 * (post, comment, term, user, or any other type with associated geo data).
		 * Returning a non-null value will effectively short-circuit the function.
		 *
		 * Possible filter names include:
		 *
		 *  - `get_post_geodata`
		 *  - `get_comment_geodata`
		 *  - `get_term_geodata`
		 *  - `get_user_geodata`
		 *
		 * @param mixed  $value     The value to return, either a single value or an array
		 *                          of values.
		 * @param int    $id ID of the object geo data is for.
		 * @param string $key  Geo data key.
		 * @param string $type Type of object data is for. Accepts 'post', 'comment', 'term', 'user',
		 *                          or any other object type with an associated meta table.
		 */
		$check = apply_filters( "get_{$type}_geodata", null, $id, $key, $type );

		if ( null !== $check ) {
			return $check;
		}

		if ( 'visibility' === $key ) {
			$visibility = get_metadata( $type, $id, 'geo_public', true );
			if ( empty( $visibility ) ) {
				return self::get_default_visibility();
			}
			switch ( (int) $visibility ) {
				case 0:
					return 'private';
				case 1:
					return 'public';
				case 2:
					return 'protected';
				default:
					return self::get_default_visibility();
			}
		}

		if ( ! empty( $key ) ) {
			return get_metadata( $type, $id, 'geo_' . $key, true );
		}

		$geodata = array();

		$properties = static::$properties;
		unset( $properties['visibility'] );
		unset( $properties['map_zoom'] );

		foreach ( static::$properties as $prop ) {
			$geodata[ $prop ] = get_metadata( $type, $id, 'geo_' . $prop, true );
		}

		$geodata['visibility'] = self::get_geodata( $type, $id, 'visibility' );
		$geodata['map_zoom']   = get_metadata( $type, $id, 'geo_zoom', true );

		if ( empty( $geodata['longitude'] ) && empty( $geodata['address'] ) ) {
			return null;
		}

		if ( empty( $geodata['icon'] ) ) {
			$geodata['icon'] = self::get_default_icon();
		}

		return array_filter( $geodata );
	}

	private static function register_terms_meta( $taxonomies, $meta_key, $args ) {
		if ( empty( $taxonomies ) ) {
			register_term_meta( '', $meta_key, $args );
		}

		if ( is_string( $taxonomies ) ) {
			$taxonomy = array( $taxonomies );
		}
		foreach ( $taxonomies as $taxonomy ) {
			register_term_meta( $taxonomy, $meta_key, $args );
		}
	}

	public static function bulk_edit_lookup_location( $post_id ) {
		$update  = false;
		$post    = get_post( $post_id );
		$geodata = get_post_geodata( $post_id );
		if ( ! $geodata ) {
			$update      = true;
			$geolocation = Loc_Config::geolocation_provider();
			if ( is_object( $geolocation ) && $geolocation->background() && $post->post_author ) {
				$geolocation->set_user( $post->post_author );
				$geolocation->retrieve( get_post_datetime( $post ) );
				$geodata = $geolocation->get();
				if ( is_wp_error( $geodata ) ) {
					return $geodata;
				}
				if ( ! empty( $geodata ) ) {
					$geodata['visibility'] = get_post_geodata( $post, 'visibility' );
					// Determine if we need to look up the location again.
					$term = Location_Taxonomy::get_location_taxonomy( $post );
					if ( empty( $term ) || ! array_key_exists( 'address', $geodata ) ) {
						$reverse = Loc_Config::geo_provider();
						$reverse->set( $geodata['latitude'], $geodata['longitude'] );
						$reverse_adr = $reverse->reverse_lookup();
						if ( ! is_wp_error( $reverse_adr ) ) {
							$update = true;
							$term   = Location_Taxonomy::get_location( $reverse_adr );
							Location_Taxonomy::set_location( $post_id, $term );
							$venue = Post_Venue::at_venue( $geodata['latitude'], $geodata['longitude'] );
							if ( $venue ) {
								update_post_meta( $post_id, 'venue_id', $venue );
							} elseif ( ! array_key_exists( 'address', $geodata ) && array_key_exists( 'display-name', $reverse_adr ) ) {
								$geodata['address'] = $reverse_adr['display-name'];
							}
						}
					}
					if ( true === $update ) {
						set_post_geodata( $post, '', $geodata );
					}
				}
			}
		}
		// If this is a checkin.
		$checkin = get_post_meta( $post_id, 'mf2_checkin', true );
		if ( $checkin && is_array( $checkin ) ) {
			$checkin = Location_Taxonomy::normalize_address( $checkin );
			$checkin = array_merge( $geodata, $checkin );
			$venue   = Post_Venue::add_new_venue( $checkin );
			if ( is_numeric( $venue ) ) {
				$update = true;
				Post_Venue::set_post_venue( $post_id, $venue );
				set_post_geodata( $post_id, 'visibility', get_post_geodata( $venue, 'visibility' ) );
				update_post_meta(
					$post_id,
					'mf2_checkin',
					array(
						'type'       => array( 'h-card' ),
						'properties' => array(
							'url'  => array( get_permalink( $venue ) ),
							'name' => array( get_the_title( $venue ) ),
							'uid'  => array( $venue ),
						),
					)
				);
			}
		}

		$weather = get_post_weatherdata( $post_id );
		if ( empty( $weather ) && ( 'post' === get_post_type( $post_id ) ) ) {
			$weather = Loc_Config::weather_provider();
			if ( $weather ) {
				$weather->set( $geodata['latitude'], $geodata['longitude'] );
				$conditions = $weather->get_conditions( get_post_timestamp( $post ) );
				if ( ! empty( $conditions ) && ! is_wp_error( $conditions ) ) {
					$update = true;
					set_post_weatherdata( $post_id, null, $conditions );
				}
			}
		}
		return ( true === $update ) ? $post_id : false;
	}

		/**
		 * Registers Geo Metadata.
		 *
		 * @since 1.0.0
		 */
	public static function register_meta() {
		$taxonomies = apply_filters( 'sloc_geo_taxonomies', array( 'venue' ) );
		$args       = array(
			'sanitize_callback' => 'clean_coordinate',
			'type'              => 'number',
			'description'       => __( 'Latitude', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'geo_latitude', $args );
		register_meta( 'comment', 'geo_latitude', $args );
		register_meta( 'user', 'geo_latitude', $args );
		self::register_terms_meta( $taxonomies, 'geo_latitude', $args );

		$args = array(
			'sanitize_callback' => 'clean_coordinate',
			'type'              => 'number',
			'description'       => __( 'Longitude', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'geo_longitude', $args );
		register_meta( 'comment', 'geo_longitude', $args );
		register_meta( 'user', 'geo_longitude', $args );
		self::register_terms_meta( $taxonomies, 'geo_longitude', $args );

		$args = array(
			'type'         => 'string',
			'description'  => __( 'Timezone of Location', 'simple-location' ),
			'single'       => true,
			'show_in_rest' => false,
		);
		register_meta( 'post', 'geo_timezone', $args );
		register_meta( 'comment', 'geo_timezone', $args );
		register_meta( 'user', 'geo_timezone', $args );
		self::register_terms_meta( $taxonomies, 'geo_timezone', $args );

		$args = array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_address' ),
			'type'              => 'string',
			'description'       => __( 'Geodata Address', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'geo_address', $args );
		register_meta( 'comment', 'geo_address', $args );
		register_meta( 'user', 'geo_address', $args );
		self::register_terms_meta( $taxonomies, 'geo_address', $args );

		$args = array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_address' ),
			'type'              => 'number',
			'description'       => __( 'Geodata Public', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'geo_public', $args );
		register_meta( 'comment', 'geo_public', $args );
		register_meta( 'user', 'geo_public', $args );
		self::register_terms_meta( $taxonomies, 'geo_public', $args );

		$args = array(
			'sanitize_callback' => array( __CLASS__, 'esc_attr' ),
			'type'              => 'string',
			'description'       => __( 'Geodata Icon', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'geo_icon', $args );
		register_meta( 'comment', 'geo_icon', $args );
		register_meta( 'user', 'geo_icon', $args );
		self::register_terms_meta( $taxonomies, 'geo_icon', $args );

		// This parameter only applies to datestamped content like posts or comments.
		$args = array(
			'type'         => 'number',
			'description'  => __( 'Is it Day Time', 'simple-location' ),
			'single'       => true,
			'show_in_rest' => false,
		);
		register_meta( 'post', 'geo_day', $args );
		register_meta( 'comment', 'geo_day', $args );

		// Numeric Geo Properties
		$numerics = array(
			'altitude' => __( 'Altitude', 'simple-location' ),
			'zoom'     => __( 'Geodata Zoom for Map Display', 'simple-location' ),
			/*
			 * Officially 0 is private 1 is public and absence or non-zero is assumed public.
			 * Therefore any non-zero number could be used to specify different display options.
			 */
			'public'   => __( 'Geodata Public', 'simple-location' ),
		);
		foreach ( $numerics as $prop => $description ) {
			$args = array(
				'type'         => 'number',
				'description'  => $description,
				'single'       => true,
				'show_in_rest' => false,
			);
			foreach ( array( 'post', 'comment', 'user' ) as $type ) {
				register_meta( $type, 'geo_' . $prop, $args );
			}
			self::register_terms_meta( $taxonomies, 'geo_' . $prop, $args );
		}
	}

	public static function display_altitude( $altitude ) {
		$aunits = get_query_var( 'sloc_units', get_option( 'sloc_measurements' ) );
		switch ( $aunits ) {
			case 'imperial':
				$altitude = round( $altitude * 3.281 );
				$aunits   = 'ft';
				break;
			default:
				$aunits = 'm';
		}
		return $altitude . $aunits;
	}

		// Return marked up coordinates
	public static function get_the_geo( $loc, $display = false ) {
		$string = $display ? '<span class="p-%1$s">%2$f</span>' : '<data class="p-%1$s" value="%2$f"></data>';
		$return = '';
		foreach ( array( 'latitude', 'longitude', 'altitude' ) as $value ) {
			if ( isset( $loc[ $value ] ) ) {
				$return .= sprintf( $string, $value, $loc[ $value ] );
			}
		}
		return $return;
	}

	public static function get_map( $type, $id, $args = array() ) {
		$loc = self::get_geodata( $type, $id );
		if ( is_array( $loc ) && ( 'public' === $loc['visibility'] ) ) {
			$map = Loc_Config::map_provider();
			if ( ! $map instanceof Map_Provider ) {
				return '';
			}
			if ( isset( $loc['latitude'] ) && ( isset( $loc['longitude'] ) ) ) {
				$loc = array_merge( $loc, $args );
				$map->set( $loc );
				return $map->get_the_map();
			}
		}
		return '';
	}

	public static function show_map() {
		if ( get_option( 'sloc_map_display' ) ) {
			return true;
		} else {
			return is_single();
		}
	}

	public static function content_map( $content ) {
		if ( self::show_map() ) {
			$content .= self::get_map( 'post', get_the_ID() );
		}
		return $content;
	}

	public static function location_content( $content ) {
		$loc = self::get_location( 'post', get_the_ID() );
		if ( ! empty( $loc ) ) {
			$content .= $loc;
		}
		return $content;
	}

	public static function location_comment( $comment_text, $comment ) {
		$loc = self::get_location(
			'comment',
			$comment->comment_ID,
			array(
				'text' => false,
				'icon' => false,
			)
		);
		if ( ! empty( $loc ) ) {
			$comment_text .= PHP_EOL . $loc . PHP_EOL;
		}
		return $comment_text;
	}

		/**
		 * Returns the default icon.
		 *
		 * @return string Default Icon.
		 */
	public static function get_default_icon() {
		return 'fa-location-arrow';
	}

		/**
		 * Returns list of available icons.
		 *
		 * @return array List of Icon Options.
		 */
	public static function get_iconlist() {
		return array(
			'fa-location-arrow'            => __( 'Location Arrow', 'simple-location' ),
			'fa-compass'                   => __( 'Compass', 'simple-location' ),
			'fa-map'                       => __( 'Map', 'simple-location' ),
			'fa-map-marker'                => __( 'Map Marker', 'simple-location' ),
			'fa-passport'                  => __( 'Passport', 'simple-location' ),
			'fa-home'                      => __( 'Home', 'simple-location' ),
			'fa-globe-asia'                => __( 'Globe - Asia', 'simple-location' ),
			'fa-globe-americas'            => __( 'Globe - Americas', 'simple-location' ),
			'fa-globe-europe'              => __( 'Globe - Europe', 'simple-location' ),
			'fa-globe-africa'              => __( 'Globe - Africa', 'simple-location' ),
			'fa-plane'                     => __( 'Plane', 'simple-location' ),
			'fa-train'                     => __( 'Train', 'simple-location' ),
			'fa-taxi'                      => __( 'Taxi', 'simple-location' ),
			'fa-tram'                      => __( 'Tram', 'simple-location' ),
			'fa-biking'                    => __( 'Biking', 'simple-location' ),
			'fa-bus'                       => __( 'Bus', 'simple-location' ),
			'fa-bus-alt'                   => __( 'Bus (Alt)', 'simple-location' ),
			'fa-car'                       => __( 'Car', 'simple-location' ),
			'fa-helicopter'                => __( 'Helicopter', 'simple-location' ),
			'fa-horse'                     => __( 'Horse', 'simple-location' ),
			'fa-ship'                      => __( 'Ship', 'simple-location' ),
			'fa-running'                   => __( 'Running', 'simple-location' ),
			'fa-shuttlevan'                => __( 'Shuttle Van', 'simple-location' ),
			'fa-subway'                    => __( 'Subway', 'simple-location' ),
			'fa-suitcase'                  => __( 'Suitcase', 'simple-location' ),
			'fa-suitcase-rolling'          => __( 'Suitcase - Rolling', 'simple-location' ),
			'fa-walking'                   => __( 'Walking', 'simple-location' ),
			'fa-running'                   => __( 'Running', 'simple-location' ),
			'americanairlines'             => __( 'American Airlines', 'simple-location' ),
			's7airlines'                   => __( 'S7 Airlines', 'simple-location' ),
			'unitedairlines'               => __( 'United Airlines', 'simple-location' ),
			'pegasusairlines'              => __( 'Pegasus Airlines', 'simple-location' ),
			'ethiopianairlines'            => __( 'Ethiopian Airlines', 'simple-location' ),
			'southwestairlines'            => __( 'Southwest Airlines', 'simple-location' ),
			'lotpolishairlines'            => __( 'Lot Polish Airlines', 'simple-location' ),
			'chinaeasternairlines'         => __( 'China Eastern Airlines', 'simple-location' ),
			'chinasouthernairlines'        => __( 'China Southern Airlines', 'simple-location' ),
			'aerlingus'                    => __( 'Aer Lingus', 'simple-location' ),
			'aeroflot'                     => __( 'Aeroflot', 'simple-location' ),
			'aeromexico'                   => __( 'Aeromexico', 'simple-location' ),
			'aircanada'                    => __( 'Air Canada', 'simple-location' ),
			'airchina'                     => __( 'Air China', 'simple-location' ),
			'airfrance'                    => __( 'Air France', 'simple-location' ),
			'airasia'                      => __( 'Air Asia', 'simple-location' ),
			'airbus'                       => __( 'Airbus', 'simple-location' ),
			'boeing'                       => __( 'Boeing', 'simple-location' ),
			'emirates'                     => __( 'Emirates', 'simple-location' ),
			'etihadairways'                => __( 'Etihad Airways', 'simple-location' ),
			'qatarairways'                 => __( 'Qatar Airways', 'simple-location' ),
			'ryanair'                      => __( 'Ryanair', 'simple-location' ),
			'sanfranciscomunicipalrailway' => __( 'San Francisco Municipal Railway', 'simple-location' ),
			'shanghaimetro'                => __( 'Shanghai Metro', 'simple-location' ),
			'turkishairlines'              => __( 'Turkish Airlines', 'simple-location' ),
			'wizzair'                      => __( 'Wizz Air', 'simple-location' ),
			'alitalia'                     => __( 'Alitalia', 'simple-location' ),
			'ana'                          => __( 'ANA', 'simple-location' ),
			'delta'                        => __( 'Delta', 'simple-location' ),
			'easyjet'                      => __( 'easyJet', 'simple-location' ),
			'lufthansa'                    => __( 'Lufthansa', 'simple-location' ),
			'britishairways'               => __( 'British Airways', 'simple-location' ),
		);
	}

		/**
		 * Generates Pulldown list of Icons.
		 *
		 * @param string  $icon Icon to be Selected.
		 * @param boolean $echo Echo or Return.
		 * @return string Select Option. Optional.
		 */
	public static function icon_select( $icon, $echo = false ) {
		$choices = self::get_iconlist();
		if ( ! $icon ) {
			$icon = self::get_default_icon();
		}
		$return = '';
		foreach ( $choices as $value => $text ) {
			$return .= sprintf( '<option value="%1s" %2s>%3s</option>', esc_attr( $value ), selected( $icon, $value, false ), esc_html( $text ) );
		}
		if ( ! $echo ) {
			return $return;
		}
		echo wp_kses(
			$return,
			array(
				'option' => array(
					'selected' => array(),
					'value'    => array(),
				),
			)
		);
	}

		/**
		 * Return the marked up icon standardized to the fonts.
		 *
		 * @param string $icon Name of Icon.
		 * @param string $summary Description of Icon. Optional.
		 * @return string marked up icon
		 */
	public static function get_icon( $icon = null, $summary = null ) {
		if ( is_null( $icon ) ) {
			$icon = self::get_default_icon();
		}
		if ( 'none' === $icon ) {
			return '';
		}
		if ( ! $summary ) {
			$list    = self::get_iconlist();
			$summary = array_key_exists( $icon, $list ) ? $list[ $icon ] : $icon;
		}
		$svg = sprintf( '%1$ssvgs/%2$s.svg', plugin_dir_path( __DIR__ ), $icon );
		if ( file_exists( $svg ) ) {
			$svg = file_get_contents( $svg );
		}
		if ( $svg ) {
			return PHP_EOL . sprintf( '<span class="sloc-location-icon sloc-icon-%1$s" style="display: inline-block; max-height: 1em; margin-right: 0.1em;" aria-hidden="true" aria-label="%2$s" title="%2$s" >%3$s</span>', esc_attr( $icon ), esc_attr( $summary ), $svg );
		}
		return '';
	}

	public static function get_location( $type, $id, $args = array() ) {
		$loc = self::get_geodata( $type, $id );
		if ( ! is_array( $loc ) ) {
			return '';
		}
		if ( Geo_Base::current_user_can_read( sloc_get_object_from_id( $type, $id ) ) && 'public' !== $loc['visibility'] ) {
			$loc['visibility'] = 'public';
			if ( isset( $loc['address'] ) ) {
				/* translators: Prefaces the address 1. with the private status */
				$loc['address'] = sprintf( __( 'Hidden: %1$s', 'simple-location' ), $loc['address'] );
			}
		}
		if ( 'private' === $loc['visibility'] ) {
			return '';
		}
		$defaults = array(
			'height'        => null,
			'width'         => null,
			'map_zoom'      => null,
			'mapboxstyle'   => null,
			'mapboxuser'    => null,
			'weather'       => true,
			'altitude'      => true, // Adds altitude when above certain level
			'taxonomy'      => get_option( 'sloc_taxonomy_display' ), // Show taxonomy instead of address field.
			'link'          => true, // Link to venue if displaying venue, link to taxonomy archive if showing taxonomy, link to map if showing address field, affected by visibility.
			'object_link'   => false, // If this is set to true, then the link will be to the object link, overriding the link attribute
			'icon'          => true, // Show Location Icon
			'text'          => false, // Show Description
			'markup'        => true, // Mark up with Microformats
			'description'   => __( 'Location: ', 'simple-location' ), // This text prefaces the location
			'wrapper-class' => array( 'sloc-display' ), // Class or classes to wrap the entire location in
			'wrapper-type'  => 'div', // HTML type to wrap the entire location in
		);
		$default  = apply_filters( 'simple_location_display_defaults', $defaults );
		$args     = wp_parse_args( $args, $defaults );
		$args     = array_merge( $loc, $args );
		$map      = Loc_Config::map_provider();
		if ( is_null( $map ) ) {
			$url = '';
		} else {
			$map->set( $loc );
			$url = $map->get_the_map_url();
		}

		$wrap  = '<%1$s class="%2$s">%3$s</%1$s>';
		$class = is_array( $args['wrapper-class'] ) ? $args['wrapper-class'] : explode( ' ', $args['wrapper-class'] );

		if ( $args['markup'] ) {
			$class[] = 'p-location';
			$class[] = 'h-adr';
		}
		$c = array( PHP_EOL );

		if ( $args['text'] ) {
			$c[] = $args['description'];
		}

		if ( ( 'post' === $type ) && ( in_array( get_post_type( $id ), get_post_types_by_support( 'geo-location' ) ) ) ) {
			$term  = Location_Taxonomy::get_post_location( $id );
			$venue = Post_Venue::get_post_venue( $id );
			// If by some chance there is a venue but no term set the location to the venue location.
			if ( $term && ! $venue ) {
				$term = Location_Taxonomy::get_post_location( $venue );
				if ( $term ) {
					Location_Taxonomy::set_location( $id, $term );
				}
			}	
		} else {
			$term  = false;
			$venue = false;
		}

		if ( 'public' === $args['visibility'] ) {
			if ( $args['markup'] ) {
				$c[] = self::get_the_geo( $loc );
			}
			if ( isset( $loc['altitude'] ) ) {
				if ( get_option( 'sloc_altitude' ) < (int) $loc['altitude'] ) {
					$loc['altitude'] = self::display_altitude( $loc['altitude'] );
				} else {
					unset( $loc['altitude'] );
				}
			}

			if ( $venue ) {
				$loc['address'] = get_the_title( $venue );
				if ( $term ) {
					$loc['address'] .= ' - ';
					$loc['address'] .= Location_Taxonomy::display_name( $term, array( 'links' => false ) );
				}
				$url = get_permalink( $venue );
			} elseif ( $args['taxonomy'] && $term ) {
				$loc['address'] = Location_Taxonomy::display_name( $term, array( 'links' => false ) );
				$url            = get_term_link( $term );
			} elseif ( empty( $loc['address'] ) ) {
				if ( ! array_key_exists( 'latitude', $loc ) ) {
					$loc['address'] = '';
				} else {
					$loc['address'] = dec_to_dms( $loc['latitude'], $loc['longitude'], ifset( $loc['altitude'] ) );
				}
			}

			if ( $args['object_link'] ) {
				$url = get_object_permalink( $type, $id );
			}

			if ( isset( $loc['altitude'] ) && $args['altitude'] ) {
				$loc['address'] .= sprintf( '(%1$s)', $loc['altitude'] );
			}
			$adclass = $args['markup'] ? 'p-label' : '';
			if ( $args['link'] && $url ) {
				$c[] = sprintf( '<a class="%1$s" href="%2$s">%3$s</a>', $adclass, $url, $loc['address'] );
			} else {
				$c[] = sprintf( '<span class="%1$s">%2$s</span>', $adclass, $loc['address'] );
			}
			// Else this is protected.
		} else {
			$url = false;
			if ( $venue ) {
				$loc['address'] = get_the_title( $venue );
			}
			if ( $args['taxonomy'] && $term ) {
				$loc['address'] = Location_Taxonomy::display_name( $term, array( 'links' => false ) );
				$url            = get_term_link( $term );
			} elseif ( isset( $loc['address'] ) ) {
				$c[] = $loc['address'];
			}

			if ( $args['object_link'] ) {
				$url = get_object_permalink( $type, $id );
			}
		}

		if ( $args['icon'] ) {
			array_unshift( $c, self::get_icon( $loc['icon'] ) );
		}
		if ( $args['weather'] ) {
			$c[] = Sloc_Weather_Data::get_the_weather( $type, $id );
		}

		$return = implode( PHP_EOL, $c );
		return sprintf( '<%1s class="%2$s">%3$s</%1$s>', $args['wrapper-type'], implode( ' ', $class ), $return );
	}
}

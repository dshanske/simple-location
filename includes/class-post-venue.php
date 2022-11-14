<?php
/**
 * Venue Custiomer Post Type
 *
 * Registers a custom post type and sets its behavior.
 */

// Register Vanue Taxonomy.
add_action( 'init', array( 'Post_Venue', 'register' ), 1 );

class Post_Venue {
	/**
	 * Register the meta for venues.
	 */
	public static function register_meta() {
		register_meta(
			'post',
			'venue_url',
			array(
				'object_subtype'    => 'venue',
				'type'              => 'string',
				'description'       => __( 'URL of Venue', 'simple-location' ),
				'single'            => true,
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			)
		);

		register_meta(
			'post',
			'venue_alternate',
			array(
				'object_subtype' => 'venue',
				'type'           => 'array',
				'description'    => __( 'Alternate URLs for Venue', 'simple-location' ),
				'single'         => true,
				'show_in_rest'   => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'   => 'string',
							'format' => 'uri',
						),
					),
				),
			)
		);

		register_meta(
			'post',
			'venue_street_address',
			array(
				'object_subtype'    => 'venue',
				'type'              => 'string',
				'description'       => __( 'Street Address of Venue', 'simple-location' ),
				'single'            => true,
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => false,
			)
		);

		register_meta(
			'post',
			'venue_id',
			array(
				'object_subtype' => 'post',
				'type'           => 'number',
				'description'    => __( 'Venue Post ID', 'simple-location' ),
				'single'         => true,
				'show_in_rest'   => true,
			)
		);

		register_meta(
			'post',
			'venue_id',
			array(
				'object_subtype' => 'attachment',
				'type'           => 'number',
				'description'    => __( 'Venue Post ID', 'simple-location' ),
				'single'         => true,
				'show_in_rest'   => true,
			)
		);

		register_meta(
			'post',
			'venue_radius',
			array(
				'object_subtype' => 'venue',
				'type'           => 'number',
				'description'    => __( 'Radius around the Venue in meters', 'simple-location' ),
				'single'         => true,
				'show_in_rest'   => true,
				'default'        => 50,
			)
		);
	}

	/**
	 * Register the custom post type for venues.
	 */
	public static function register() {
		$labels = array(
			'name'                  => __( 'Venues', 'simple-location' ),
			'singular_name'         => __( 'Venue', 'simple-location' ),
			'add_new'               => __( 'Add New', 'simple-location' ),
			'edit_item'             => __( 'Edit Venue', 'simple-location' ),
			'new_item'              => __( 'New Venue', 'simple-location' ),
			'view_item'             => __( 'View Venue', 'simple-location' ),
			'view_items'            => __( 'View Venues', 'simple-location' ),
			'search_items'          => __( 'Search Venues', 'simple-location' ),
			'not_found'             => __( 'No Venues Found', 'simple-location' ),
			'not_found_in_trash'    => __( 'No venues found in Trash', 'simple-location' ),
			'all_items'             => __( 'All Venues', 'simple-location' ),
			'insert_into_item'      => __( 'Insert into Venue', 'simple-location' ),
			'uploaded_to_this_item' => __( 'Uploaded to this venue', 'simple-location' ),
			'featured_image'        => __( 'Venue Image', 'simple-location' ),
			'set_featured_image'    => __( 'Set Venue Image', 'simple-location' ),
			'remove_featured_image' => __( 'Remove Venue Image', 'simple-location' ),
			'use_featured_image'    => __( 'Use Venue Image', 'simple-location' ),
		);

		register_post_type(
			'venue',
			array(
				'label'            => __( 'Venues', 'simple-location' ),
				'labels'           => $labels,
				'description'      => __( 'Represents a place', 'simple-location' ),
				'public'           => true,
				'show_in_rest'     => true,
				'menu_icon'        => 'dashicons-location',
				'supports'         => array( 'title', 'thumbnail', 'geo-location', 'editor', 'custom-fields' ),
				'taxonomies'       => array( 'location' ),
				'has_archive'      => false,
				'delete_with_user' => false,
			)
		);

		self::register_meta();

		$labels = array(
			'name'                       => _x( 'Types', 'taxonomy general name', 'simple-location' ),
			'singular_name'              => _x( 'Type', 'taxonomy singular name', 'simple-location' ),
			'search_items'               => _x( 'Search Types', 'search locations', 'simple-location' ),
			'popular_items'              => _x( 'Popular Types', 'popular locations', 'simple-location' ),
			'all_items'                  => _x( 'All Type', 'all taxonomy items', 'simple-location' ),
			'edit_item'                  => _x( 'Edit Type', 'edit taxonomy item', 'simple-location' ),
			'view_item'                  => _x( 'View Type', 'view taxonomy item', 'simple-location' ),
			'update_item'                => _x( 'Update Type', 'update taxonomy item', 'simple-location' ),
			'add_new_item'               => _x( 'Add New Type', 'add taxonomy item', 'simple-location' ),
			'new_item_name'              => _x( 'New Type', 'new taxonomy item', 'simple-location' ),
			'separate_items_with_commas' => _x( 'Separate types with commas', 'separate kinds with commas', 'simple-location' ),
			'add_or_remove_items'        => _x( 'Add or remove type', 'add or remove items', 'simple-location' ),
			'choose_from_most_used'      => _x( 'Choose from the most used type', 'choose most used', 'simple-location' ),
			'not found'                  => _x( 'No types found', 'no types found', 'simple-location' ),
			'no_terms'                   => _x( 'No types', 'no types', 'simple-location' ),
			'name_field_description'     => __( 'The name of the venut type', 'simple-location' ),
			'desc_field_description'     => __( 'Will display on location archive pages if set', 'simple-location' ),
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Allows for categorization of venues', 'simple-location' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_in_rest'       => false,
			'show_tagcloud'      => true,
			'show_in_quick_edit' => false,
			'show_admin_column'  => true,
		);
		register_taxonomy( 'venue_type', 'venue', $args );

	}

	/**
	 * Return Nearby Venues
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return array Return the IDs of all nearby venues
	 */
	public static function nearby( $lat, $lng ) {
		/**
		 * Short-circuits the checking for a venue if it is not stored as normal.
		 *
		 * @param mixed  $value     The boolean value as to whether someone is at a venue.
		 *                          Default null.
		 * @param float $lat Latitude.
		 * @param float $lnt Longitude.
		 */
		$check = apply_filters( 'pre_nearby_venue', null, $lat, $lng );

		if ( ! is_null( $check ) ) {
			return $check;
		}

		$box = geo_radius_box( $lat, $lng );
		return get_posts(
			array(
				'post_type'  => 'venue',
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'     => 'geo_latitude',
						'compare' => 'BETWEEN',
						'type'    => 'DECIMAL( 10, 7 )',
						'value'   => array( $box[0], $box[2] ),
					),
					array(
						'key'     => 'geo_longitude',
						'compare' => 'BETWEEN',
						'type'    => 'DECIMAL(10,7)',
						'value'   => array( $box[1], $box[3] ),
					),
				),
			)
		);
	}

	/**
	 * Return Nearby Venues in a Select List
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @param int   $current Current venue.
	 * @return array Return select
	 */
	public static function nearby_select( $lat, $lng, $current = 0, $echo = true ) {
		$current = intval( $current );
		$venues  = self::nearby( $lat, $lng );
		$return  = '<select name="venue_id" id="venue_id">';
		$return .= sprintf( '<option value="0" %1$s>%2$s</option>', selected( $current, 0, false ), esc_html__( 'None', 'simple-location' ) );
		foreach ( $venues as $venue ) {
			$venue   = intval( $venue );
			$return .= sprintf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $venue ), selected( $current, $venue, false ), esc_html( get_the_title( $venue ) ) );
		}
		$return .= '</select>';
		if ( $echo ) {
			echo $return;
		}
		return $return;
	}

	/**
	 * Checks if a Location is at a venue.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return boolean|int Return either the Post ID of first venue found or false if none are found
	 */
	public static function at_venue( $lat, $lng ) {
		/**
		 * Short-circuits the checking for a venue if it is not stored as normal.
		 *
		 * @param mixed  $value     The boolean value as to whether someone is at a venue.
		 *                          Default null.
		 * @param float $lat Latitude.
		 * @param float $lnt Longitude.
		 */
		$check = apply_filters( 'pre_at_venue', null, $lat, $lng );

		if ( ! is_null( $check ) ) {
			return $check;
		}

		$venue_ids = get_posts(
			array(
				'post_type' => 'venue',
				'fields'    => 'ids',
			)
		);
		$venues    = get_array_post_geodata( $venue_ids );
		foreach ( $venues as $key => $venue ) {
			$radius = get_post_meta( $venue, 'venue_radius', true );
			if ( ! $radius ) {
				$radius = 50;
			}
			if ( geo_in_radius( $venue['latitude'], $venue['longitude'], $lat, $lng, $radius ) ) {
				return $key;
			}
		}
		return false;
	}

	public static function get_post_venue( $post_id ) {
		return get_post_meta( $post_id, 'venue_id', true );
	}

	public static function set_post_venue( $post_id, $venue_id ) {
		if ( ! is_numeric( $venue_id ) ) {
			return false;
		}
		return update_post_meta( $post_id, 'venue_id', $venue_id );
	}

	public static function add_new_venue( $venue ) {
		$venue = Location_Taxonomy::normalize_address( $venue );
		if ( array_key_exists( 'name', $venue ) ) {
			$title = $venue['name'];
		} elseif ( array_key_exists( 'label', $venue ) ) {
			$title = $venue['label'];
		} else {
			return false;
		}

		$meta = array();

		if ( array_key_exists( 'url', $venue ) ) {
			// Temporary fix just in case multiple arrays exists
			if ( is_array( $venue['url'] ) ) {
				$venue['url'] = $venue['url'][0];
			}
			$meta['venue_url'] = $venue['url'];
			// If the URL in the check-in property is local, then return the existing URL.
			$id = url_to_postid( $venue['url'] );
			if ( $id ) {
				return $id;
			}

			$match = get_posts(
				array(
					'post_type'  => 'venue',
					'fields'     => 'ids',
					'meta_query' => array(
						array(
							'key'   => 'venue_url',
							'value' => $venue['url'],
						),
					),
				)
			);
			if ( ! empty( $match ) ) {
				return $match[0];
			}

			$match = get_posts(
				array(
					'post_type'  => 'venue',
					'fields'     => 'ids',
					'meta_query' => array(
						array(
							'compare' => 'LIKE',
							'key'     => 'venue_alternative',
							'value'   => $venue['url'],
						),
					),
				)
			);
			if ( ! empty( $match ) ) {
				return $match[0];
			}
		}

		if ( array_key_exists( 'street-address', $venue ) ) {
			$meta['venue_street_address'] = $venue['street-address'];
			$meta['_venue_data']          = $venue;
		}

		$location = Location_Taxonomy::get_location( $venue, true );

		$wp = array(
			'post_title'  => $title,
			'post_status' => 'publish',
			'post_type'   => 'venue',
			'meta_input'  => $meta,
		);

		$id = wp_insert_post( $wp, true );
		if ( ! is_wp_error( $id ) ) {
			if ( $location ) {
				Location_Taxonomy::set_location( $id, $location );
			}
			set_post_geodata( $id, null, $venue );
			set_post_geodata( $id, 'visibility', 'public' );
			if ( array_key_exists( 'zoom', $venue ) ) {
				set_post_geodata( $id, 'zoom', $venue['zoom'] );
			} else {
				set_post_geodata( $id, 'zoom', 18 );
			}
		}

		return $id;
	}

	public static function get_venue_type( $venue_id = null ) {
		$venue = get_post( $venue_id );
		if ( ! $venue || 'venue' !== get_post_type( $venue ) ) {
			return '';
		}
		$terms = wp_get_object_terms( $venue->ID, 'venue_type', array( 'fields' => 'ids' ) );
		if ( empty( $terms ) ) {
			return false;
		}
		return $terms;
	}

	public static function get_venue_posts( $venue_id = null ) {
			$venue = get_post( $venue_id );
		if ( 'venue' !== get_post_type( $venue ) ) {
			return false;
		}

			return get_posts(
				array(
					'post_type'  => 'post',
					'fields'     => 'ids',
					'meta_query' => array(
						array(
							'key'   => 'venue_id',
							'value' => $venue->ID,
						),
					),
				)
			);
	}



}

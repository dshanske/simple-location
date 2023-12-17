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
				'hierarchical'     => true,
				'show_in_rest'     => true,
				'menu_icon'        => 'dashicons-location',
				'supports'         => array( 'title', 'thumbnail', 'geo-location', 'editor', 'custom-fields', 'page-attributes' ),
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

		add_filter( 'manage_venue_posts_columns', array( __CLASS__, 'remove_date_admin_column' ) );
		add_filter( 'manage_venue_posts_columns', array( 'Geo_Base', 'add_location_admin_column' ) );
		add_filter( 'manage_venue_posts_columns', array( __CLASS__, 'add_checkins_admin_column' ) );
		add_action( 'manage_venue_posts_custom_column', array( 'Geo_Base', 'manage_location_admin_column' ), 10, 2 );
		add_action( 'manage_venue_posts_custom_column', array( __CLASS__, 'manage_checkins_admin_column' ), 10, 2 );

		// Add Dropdown.
		add_action( 'restrict_manage_posts', array( __CLASS__, 'venue_types_dropdown' ), 12, 2 );

		add_filter( 'bulk_actions-edit-venue', array( __CLASS__, 'register_bulk_edit' ), 10 );
		add_filter( 'handle_bulk_actions-edit-venue', array( __CLASS__, 'handle_bulk_edit' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'bulk_action_admin_notices' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'post__in' ) );
	}

	/**
	 * Adds posts__in support to edit post screen.
	 *
	 * @param WP_Query $query Query Object.
	 */
	public static function post__in( $query ) {
		if ( ! is_admin() ) {
			return $query;
		}

		if ( ! array_key_exists( 'post__in', $_GET ) ) {
			return $query;
		}

		$ids = $_GET['post__in'];
		$ids = array_map( 'intval', $ids );

		$query->set( 'post__in', $ids );
	}

	/**
	 * This registers an admin column in the venue edit screen.
	 *
	 * @param array $columns Columns passed through from filter.
	 * @return array $columns Column with extra property added.
	 * @since 1.0.0
	 */
	public static function add_checkins_admin_column( $columns ) {
		$columns['checkins'] = __( 'Checkins', 'simple-location' );
		return $columns;
	}

	/**
	 * Returns number of checkins for the venue edit screen column.
	 *
	 * @param string $column Which column is being rendered.
	 * @param int    $post_id The post id for the row.
	 */
	public static function manage_checkins_admin_column( $column, $post_id ) {
		if ( 'checkins' === $column ) {
			$ids = self::get_venue_posts( $post_id );
			if ( is_countable( $ids ) ) {
				$count = count( $ids );
				$url   = add_query_arg(
					array(
						'post__in' => $ids,
					),
					'edit.php'
				);
				printf( '<a href="%s">%s</a>', esc_url( $url ), $count );
			} else {
				__e( 'None', 'simple-location' );
			}
		}
	}


	/**
	 * Register Bulk Edit of Location.
	 *
	 * Adds options to bulk edit location.
	 *
	 * @param array $actions List of Registered Bulk Edit Actions.
	 *
	 * @since 1.0.0
	 */
	public static function register_bulk_edit( $actions ) {
		$actions['venue_children'] = __( 'Update Venue from Its Checkins', 'simple-location' );
		return $actions;
	}

	/**
	 * Bulk Edit Handler.
	 *
	 * Allows for Bulk Updating venues.
	 *
	 * @param string $redirect_to Where to redirect once complete.
	 * @param string $doaction The Action Being Requested.
	 * @param array  $post_ids The list of Post IDs to act on.
	 * @return string $redirect_to Return the Redirect_To Parameter
	 */
	public static function handle_bulk_edit( $redirect_to, $doaction, $post_ids ) {
		switch ( $doaction ) {
			case 'venue_children':
				$count = 0;
				foreach ( $post_ids as $post_id ) {
					$data = get_post_geodata( $post_id );
					if ( ! array_key_exists( 'longitude', $data ) && ! array_key_exists( 'latitude', $data ) ) {
						$ids = self::get_venue_posts( $post_id );
						if ( ! empty( $ids ) ) {
							$data = get_post_geodata( $ids[0] );
							if ( array_key_exists( 'longitude', $data ) ) {
								set_post_geodata( $post_id, '', $data );
								$location = Location_Taxonomy::get_location_taxonomy( $ids[0] );
								if ( $location ) {
									Location_Taxonomy::set_location( $post_id, $location->term_id );
								}
							}
							++$count;
						}
					}
				}
				$redirect_to = add_query_arg( 'bulk_venue_children', $count, $redirect_to );
				break;

		}
		return $redirect_to;
	}

	/**
	 * Add Notice when Bulk Action is run with results.
	 */
	public static function bulk_action_admin_notices() {
		if ( isset( $_REQUEST['bulk_venue_children'] ) ) {
			$count = intval( $_REQUEST['bulk_venue_children'] );
			if ( 0 === $count ) {
				$string = __( 'None of the Venues Were Updated.', 'simple-location' );
			} else {
				/* translators: Count of posts updated. */
				$string = sprintf( _n( 'Updated %s venue.', 'Updated %s venues.', $count, 'simple-location' ), $count );
			}
			printf( '<div id="message" class="updated fade">%1$s</div>', esc_html( $string ) );
		}
	}

	/**
	 * This removes the data column from displaying on the venue edit screen
	 *
	 * @param array $columns Columns passed through from filter.
	 * @return array $columns Column with date removed.
	 */
	public static function remove_date_admin_column( $columns ) {
		unset( $columns['date'] );
		return $columns;
	}

	/**
	 * Generates a dropdown
	 *
	 * Allows visibility to be filtered on post edit screen.
	 *
	 * @param string $post_type The post type slug.
	 * @param string $which     The location of the extra table nav markup:
	 *                          'top' or 'bottom' for WP_Posts_List_Table,
	 *                          'bar' for WP_Media_List_Table.
	 * @since 1.0.0
	 */
	public static function venue_types_dropdown( $post_type, $which ) {
		if ( 'venue' !== $post_type ) {
			return;
		}
		$selected = 'all';
		if ( isset( $_REQUEST['venue_type'] ) ) {
			$selected = sanitize_text_field( $_REQUEST['venue_type'] );
		}
		$list = array( '' => __( 'All Types', 'simple-location' ) );
		foreach ( get_terms( 'venue_type', array( 'hide_empty' => false ) ) as $type ) {
			$list[ $type->slug ] = $type->name;
		}
		echo '<select id="venue_type" name="venue_type">';
		foreach ( $list as $key => $value ) {
			echo wp_kses( sprintf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $selected, $key ), $value ), Geo_Base::kses_option() );
		}
		echo '</select>';
	}

	/**
	 * Return Nearby Venues
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @param int $radius Radius to Search In. Optional.
	 * @return array Return the IDs of all nearby venues
	 */
	public static function nearby( $lat, $lng, $radius = 100 ) {
		/**
		 * Short-circuits the checking for a venue if it is not stored as normal.
		 *
		 * @param mixed  $value     The boolean value as to whether someone is at a venue.
		 *                          Default null.
		 * @param float $lat Latitude.
		 * @param float $lnt Longitude.
		 * @param int $radius Radius.
		 */
		$check = apply_filters( 'pre_nearby_venue', null, $lat, $lng, $radius );

		if ( ! is_null( $check ) ) {
			return $check;
		}

		$box = geo_radius_box( $lat, $lng, $radius );
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
		$return .= '</select><br />';
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

		// Only look at venues that are within 500 meters.
		$venue_ids = self::nearby( $lat, $lng, 500 );
		$venues    = array_filter( get_array_post_geodata( $venue_ids ) );
		foreach ( $venues as $venue_id => $venue ) {
			$radius = get_post_meta( $venue_id, 'venue_radius', true );
			if ( ! $radius ) {
				$radius = 50;
			}
			if ( geo_in_radius( $venue['latitude'], $venue['longitude'], $lat, $lng, $radius ) ) {
				return $venue_id;
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
			'post_name'   => sanitize_title(
				$title . ' ' . Location_Taxonomy::display_name(
					$location,
					array(
						'links' => false,
						'flag'  => false,
					)
				)
			),
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

	public static function has_parent_venue( $venue_id = null ) {
		return has_post_parent( $venue_id );
	}

	public static function get_parent_venue( $venue_id = null ) {
		return wp_get_post_parent_id( $venue_id );
	}

	public static function get_venue_children( $venue_id = null ) {
		$venue = get_post( $venue_id );
		if ( ! $venue ) {
			return false;
		}

		return get_posts(
			array(
				'post_parent' => $venue->ID,
				'fields'      => 'ids',
			)
		);
	}

	public static function get_venue_ancestors( $venue_id = null ) {
		$venue = get_post( $venue_id );
		if ( ! $venue ) {
			return false;
		}
		return get_ancestors( $venue->ID, 'venue', 'post_type' );
	}

	/* Gets attached photos from posts from a venue */

	public static function get_attached_photos( $venue_id ) {
		$post_ids = self::get_venue_posts( $venue_id );
		if ( empty( $post_ids ) ) {
			return array();
		}
		$media_ids = array();
		foreach ( $post_ids as $post_id ) {
			$attached  = get_attached_media( 'image', $post_id );
			$media_ids = array_merge( $media_ids, array_keys( $attached ) );
		}
		return $media_ids;
	}

	public static function get_venue_gallery( $venue_id = null ) {
		$media_ids = self::get_attached_photos( $venue_id );
		return gallery_shortcode(
			array(
				'ids'     => $media_ids,
				'columns' => 3,
				'link'    => 'file',
			)
		);
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
				'nopaging'   => true,
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

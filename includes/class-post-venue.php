<?php
/**
 * Venue Custom Post Type
 *
 * Registers a custom post type and sets its behavior.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

// Register Vanue Taxonomy.
add_action( 'init', array( 'Post_Venue', 'register' ), 1 );

/**
 * Registers the venue post type and venue type taxonomy, and handles venues.
 *
 * @since 5.0.0
 */
class Post_Venue {

	/**
	 * Smallest distance in meters within which a venue with the same name is the same venue.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const MATCH_RADIUS = 100;

	/**
	 * Most of the site's venues read when listing the places near a location.
	 *
	 * @since 6.3.0
	 * @var int
	 */
	const PLACES_SCAN = 100;

	/**
	 * Meta key holding each identifier of a venue, one per row, so venues can be found by it.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const UID_KEY = '_venue_uid';

	/**
	 * Meta key holding each other name of a venue, such as that of a venue merged into it, one per row.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const ALIAS_KEY = '_venue_alias';

	/**
	 * Register the meta for venues.
	 *
	 * @since 5.0.0
	 * @since 6.1.0 Registers the visibility for posts made at the venue.
	 * @since 6.2.0 venue_url is sanitized as a URL.
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
				'sanitize_callback' => array( __CLASS__, 'sanitize_url' ),
				'show_in_rest'      => true,
			)
		);

		register_meta(
			'post',
			'venue_alternate',
			array(
				'object_subtype'    => 'venue',
				'type'              => 'array',
				'description'       => __( 'Alternate URLs for Venue', 'simple-location' ),
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_alternate' ),
				'show_in_rest'      => array(
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
			'venue_sources',
			array(
				'object_subtype' => 'venue',
				'type'           => 'array',
				'description'    => __( 'Venue providers the venue was found with', 'simple-location' ),
				'single'         => true,
				'show_in_rest'   => false,
			)
		);

		register_meta(
			'post',
			'venue_category',
			array(
				'object_subtype'    => 'venue',
				'type'              => 'string',
				'description'       => __( 'Shared category of the venue, such as food', 'simple-location' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_category' ),
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

		register_meta(
			'post',
			'venue_post_visibility',
			array(
				'object_subtype'    => 'venue',
				'type'              => 'string',
				'description'       => __( 'Visibility of the location of posts made at the venue', 'simple-location' ),
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_post_visibility' ),
			)
		);
	}

	/**
	 * Sanitizes a venue's visibility for posts made there.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Visibility.
	 * @return string 'public', 'protected' or 'private', or an empty string for no default.
	 */
	public static function sanitize_post_visibility( $value ) {
		return in_array( $value, array( 'public', 'protected', 'private' ), true ) ? $value : '';
	}

	/**
	 * Returns the visibility a venue gives the location of posts made there, such as private at home.
	 *
	 * @since 6.1.0
	 *
	 * @param int|string|false $venue_id Venue ID.
	 * @return string 'public', 'protected' or 'private', or an empty string when the venue doesn't set one, in which case
	 *                each way of posting keeps its own default.
	 */
	public static function get_post_visibility( $venue_id ) {
		if ( ! is_numeric( $venue_id ) || 0 >= (int) $venue_id ) {
			return '';
		}
		return self::sanitize_post_visibility( get_post_meta( (int) $venue_id, 'venue_post_visibility', true ) );
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
		add_filter( 'rest_prepare_venue', array( __CLASS__, 'rest_prepare_venue' ), 10, 2 );
		// A theme that supports Simple Location shows the links itself, with get_venue_links().
		if ( ! current_theme_supports( 'simple-location' ) ) {
			add_filter( 'the_content', array( __CLASS__, 'venue_content' ), 13 );
		}
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
	 * @since 6.0.0 Accepts a single ID or a comma-separated list.
	 * @since 6.0.0 Only changes the main query on a post list screen, not every query on the page.
	 *
	 * @param WP_Query $query Query Object.
	 */
	public static function post__in( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		if ( ! isset( $_GET['post__in'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		$ids = array_filter( wp_parse_id_list( wp_unslash( $_GET['post__in'] ) ) );
		if ( ! empty( $ids ) ) {
			$query->set( 'post__in', $ids );
		}
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
				printf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( number_format_i18n( $count ) ) );
			} else {
				esc_html_e( 'None', 'simple-location' );
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
					if ( current_user_can( 'edit_post', $post_id ) ) {
						$data = get_post_geodata( $post_id );
						if ( ! is_array( $data ) || ( ! array_key_exists( 'longitude', $data ) && ! array_key_exists( 'latitude', $data ) ) ) {
							$ids = self::get_venue_posts( $post_id );
							if ( ! empty( $ids ) ) {
								$data = get_post_geodata( $ids[0] );
								if ( is_array( $data ) && array_key_exists( 'longitude', $data ) ) {
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
				}
				$redirect_to = add_query_arg( 'bulk_venue_children', $count, $redirect_to );
				break;

		}
		return $redirect_to;
	}

	/**
	 * Add Notice when Bulk Action is run with results.
	 *
	 * @since 6.2.0 The notice is printed with wp_admin_notice().
	 */
	public static function bulk_action_admin_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads the count that the bulk action redirect added, to show a notice.
		if ( isset( $_REQUEST['bulk_venue_children'] ) ) {
			$count = intval( $_REQUEST['bulk_venue_children'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads the count that the bulk action redirect added, to show a notice.
			if ( 0 === $count ) {
				$string = __( 'None of the Venues Were Updated.', 'simple-location' );
			} else {
				/* translators: Count of posts updated. */
				$string = sprintf( _n( 'Updated %s venue.', 'Updated %s venues.', $count, 'simple-location' ), $count );
			}
			wp_admin_notice(
				esc_html( $string ),
				array(
					'type'        => 'success',
					'id'          => 'message',
					'dismissible' => true,
				)
			);
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads the list filter to show it as selected.
		if ( isset( $_REQUEST['venue_type'] ) ) {
			$selected = sanitize_text_field( wp_unslash( $_REQUEST['venue_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads the list filter to show it as selected.
		}
		$list  = array( '' => __( 'All Types', 'simple-location' ) );
		$types = get_terms(
			array(
				'taxonomy'   => 'venue_type',
				'hide_empty' => false,
			)
		);
		if ( is_array( $types ) ) {
			foreach ( $types as $type ) {
				$list[ $type->slug ] = $type->name;
			}
		}
		echo '<select id="venue_type" name="venue_type">';
		foreach ( $list as $key => $value ) {
			echo wp_kses( sprintf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $selected, $key, false ), esc_html( $value ) ), Geo_Base::kses_option() );
		}
		echo '</select>';
	}

	/**
	 * Return Nearby Venues
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @param int   $radius Radius to Search In. Optional.
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
		$check = apply_filters( 'pre_nearby_venue', null, $lat, $lng, $radius ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.

		if ( ! is_null( $check ) ) {
			return $check;
		}

		$box = geo_radius_box( $lat, $lng, $radius );
		return get_posts(
			array(
				'post_type'  => 'venue',
				'fields'     => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Venues are found by their coordinates.
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
	 * @param bool  $echo    Optional. Whether to print the list. Default true.
	 * @return string HTML select list.
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
			echo $return; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each part is escaped above as the list is built.
		}
		return $return;
	}

	/**
	 * Returns the places near a location: the site's venues, then the venue providers', as mf2 h-cards.
	 *
	 * The site's venues within the sloc_venue_radius setting come first, nearest first, each with its page on the
	 * site as 'url' and 'uid'. The venue providers' follow, without those the site already has, found by their
	 * identifier or one of their links. Each place names where it came from as 'provider': 'local' for the site's,
	 * or the provider's slug.
	 *
	 * A private, draft or password-protected venue, or one whose location is private, is left out unless the
	 * current user can edit it. A venue whose location is protected shows without its coordinates, address or
	 * distance.
	 *
	 * The providers aren't asked when the site's venues already fill the limit.
	 *
	 * @since 6.3.0
	 *
	 * @param float $lat  Latitude.
	 * @param float $lng  Longitude.
	 * @param array $args {
	 *     Optional. Arguments.
	 *
	 *     @type int  $limit Most places. Default the sloc_venue_limit setting, at most Venue_Provider::MAX_LIMIT.
	 *     @type bool $local Only the site's venues. Default false.
	 * }
	 * @return array[] Places as mf2 h-cards; empty for coordinates that aren't valid.
	 */
	public static function places( $lat, $lng, $args = array() ) {
		$lat = clean_coordinate( $lat, 'latitude' );
		$lng = clean_coordinate( $lng, 'longitude' );
		if ( false === $lat || false === $lng ) {
			return array();
		}
		$args  = wp_parse_args(
			$args,
			array(
				'limit' => get_option( 'sloc_venue_limit', Venue_Provider::DEFAULT_LIMIT ),
				'local' => false,
			)
		);
		$limit = Venue_Provider::sanitize_limit( $args['limit'] );

		$local = self::local_places( $lat, $lng );
		$items = array();
		if ( ! $args['local'] && count( $local ) < $limit && Loc_Config::venue_provider() ) {
			$found = Sloc_Provider_Chain::venues( $lat, $lng, $limit );
			$items = ( is_array( $found ) && is_array( $found['items'] ?? null ) ) ? array_filter( $found['items'], 'is_array' ) : array();
		}

		if ( ! empty( $items ) ) {
			// Venues the site has that the providers found, though they aren't within the radius here.
			$known = wp_list_pluck( wp_list_pluck( $local, 'venue' ), 'ID' );
			foreach ( self::venues_by_uid( array_column( $items, 'uid' ) ) as $venue ) {
				$access = self::place_access( $venue );
				if ( $access && ! in_array( $venue->ID, $known, true ) ) {
					$local[] = array(
						'venue'    => $venue,
						'access'   => $access,
						'distance' => self::venue_distance( $venue, $lat, $lng ),
					);
				}
			}

			$links = array();
			foreach ( $local as $place ) {
				$links = array_merge( $links, self::venue_identifiers( $place['venue'] ) );
			}
			$items = array_filter(
				$items,
				function ( $item ) use ( $links ) {
					$urls = self::venue_urls( $item );
					$ids  = array_merge( array( $item['uid'] ?? '', $urls['url'] ), $urls['alternate'] );
					return empty( array_intersect( array_filter( $ids, 'is_string' ), $links ) );
				}
			);
		}

		// Nearest first, the oldest first at the same distance; a venue without coordinates last.
		usort( $local, fn( $a, $b ) => array( $a['distance'] ?? PHP_INT_MAX, $a['venue']->ID ) <=> array( $b['distance'] ?? PHP_INT_MAX, $b['venue']->ID ) );
		$cards = array_map( fn( $place ) => self::local_card( $place['venue'], $place['access'], $place['distance'] ), $local );
		$cards = array_slice( array_merge( $cards, array_values( $items ) ), 0, $limit );

		/**
		 * Filters the places near a location.
		 *
		 * @since 6.3.0
		 *
		 * @param array[] $places Places as mf2 h-cards, the site's venues first.
		 * @param float   $lat    Latitude.
		 * @param float   $lng    Longitude.
		 * @param array   $args   Arguments: 'limit' and 'local'.
		 */
		return apply_filters( 'sloc_places', array_map( array( __CLASS__, 'jf2_to_mf2' ), $cards ), $lat, $lng, $args );
	}

	/**
	 * Returns the site's venues within the venue search radius that the current user may see.
	 *
	 * At most PLACES_SCAN venues in the box around the radius are read.
	 *
	 * @since 6.3.0
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return array[] Each with 'venue', the post; 'access', as place_access() returns; and 'distance' in meters.
	 */
	protected static function local_places( $lat, $lng ) {
		$radius = Venue_Provider::sanitize_radius( get_option( 'sloc_venue_radius', Venue_Provider::DEFAULT_RADIUS ) );
		$box    = geo_radius_box( $lat, $lng, $radius );
		$venues = get_posts(
			array(
				'post_type'      => 'venue',
				'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'posts_per_page' => self::PLACES_SCAN,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Venues are found by their coordinates.
				'meta_query'     => array(
					array(
						'key'     => 'geo_latitude',
						'compare' => 'BETWEEN',
						'type'    => 'DECIMAL(10,7)',
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
		$places = array();
		foreach ( $venues as $venue ) {
			$access   = self::place_access( $venue );
			$distance = self::venue_distance( $venue, $lat, $lng );
			if ( $access && null !== $distance && $distance <= $radius ) {
				$places[] = array(
					'venue'    => $venue,
					'access'   => $access,
					'distance' => $distance,
				);
			}
		}
		return $places;
	}

	/**
	 * Returns the site's venues with any of a list of identifiers.
	 *
	 * @since 6.3.0
	 *
	 * @param array $uids Identifiers; anything that isn't a string is skipped.
	 * @return WP_Post[] Venues.
	 */
	protected static function venues_by_uid( $uids ) {
		$uids = array_values( array_unique( array_filter( $uids, fn( $uid ) => is_string( $uid ) && '' !== $uid ) ) );
		if ( empty( $uids ) ) {
			return array();
		}
		return get_posts(
			array(
				'post_type'      => 'venue',
				'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'posts_per_page' => count( $uids ),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Venues are matched by their identifiers.
				'meta_query'     => array(
					array(
						'key'     => self::UID_KEY,
						'compare' => 'IN',
						'value'   => $uids,
					),
				),
			)
		);
	}

	/**
	 * Returns how much of a venue the current user may see in a list of places.
	 *
	 * @since 6.3.0
	 *
	 * @param WP_Post $venue Venue.
	 * @return string|false 'full'; 'name' for a venue whose location is protected; false to leave it out.
	 */
	protected static function place_access( $venue ) {
		if ( current_user_can( 'edit_post', $venue->ID ) ) {
			return 'full';
		}
		if ( 'publish' !== $venue->post_status || '' !== $venue->post_password ) {
			return false;
		}
		$visibility = get_post_geodata( $venue->ID, 'visibility' );
		if ( 'public' === $visibility ) {
			return 'full';
		}
		return 'protected' === $visibility ? 'name' : false;
	}

	/**
	 * Returns a venue's distance from a location.
	 *
	 * @since 6.3.0
	 *
	 * @param WP_Post $venue Venue.
	 * @param float   $lat   Latitude.
	 * @param float   $lng   Longitude.
	 * @return int|null Meters, or null if the venue has no coordinates.
	 */
	protected static function venue_distance( $venue, $lat, $lng ) {
		$geo = get_post_geodata( $venue );
		if ( ! is_array( $geo ) || ! is_numeric( $geo['latitude'] ?? null ) || ! is_numeric( $geo['longitude'] ?? null ) ) {
			return null;
		}
		return (int) round( geo_distance( $lat, $lng, $geo['latitude'], $geo['longitude'] ) );
	}

	/**
	 * Returns the identifiers and links a venue is known by: its page, identifiers, website and other pages.
	 *
	 * @since 6.3.0
	 *
	 * @param WP_Post $venue Venue.
	 * @return string[] Identifiers and URLs.
	 */
	protected static function venue_identifiers( $venue ) {
		return array_values(
			array_filter(
				array_merge(
					array( get_permalink( $venue ), (string) get_post_meta( $venue->ID, 'venue_url', true ) ),
					get_post_meta( $venue->ID, self::UID_KEY ),
					(array) get_post_meta( $venue->ID, 'venue_alternate', true )
				),
				fn( $id ) => is_string( $id ) && '' !== $id
			)
		);
	}

	/**
	 * Returns a site venue as a place, a jf2 h-card.
	 *
	 * @since 6.3.0
	 *
	 * @param WP_Post  $venue    Venue.
	 * @param string   $access   'full', or 'name' for the name and links only.
	 * @param int|null $distance Distance in meters, or null.
	 * @return array h-card.
	 */
	protected static function local_card( $venue, $access, $distance ) {
		$permalink = get_permalink( $venue );
		$card      = array(
			'type'     => 'card',
			'name'     => $venue->post_title,
			'url'      => array_values( array_unique( array_filter( array( $permalink, (string) get_post_meta( $venue->ID, 'venue_url', true ) ) ) ) ),
			'uid'      => $permalink,
			'provider' => 'local',
		);
		$types = get_the_terms( $venue, 'venue_type' );
		if ( is_array( $types ) ) {
			$card['category'] = wp_list_pluck( $types, 'name' );
		}
		if ( 'full' === $access ) {
			$geo  = get_post_geodata( $venue );
			$geo  = is_array( $geo ) ? $geo : array();
			$card = array_merge(
				$card,
				array(
					'latitude'       => is_numeric( $geo['latitude'] ?? null ) ? (float) $geo['latitude'] : null,
					'longitude'      => is_numeric( $geo['longitude'] ?? null ) ? (float) $geo['longitude'] : null,
					'altitude'       => is_numeric( $geo['altitude'] ?? null ) ? (float) $geo['altitude'] : null,
					'street-address' => (string) get_post_meta( $venue->ID, 'venue_street_address', true ),
					'label'          => $geo['address'] ?? null,
					'distance'       => $distance,
				)
			);
		}
		return $card;
	}

	/**
	 * Converts a jf2 object, such as a venue's h-card, to mf2 JSON.
	 *
	 * Each property becomes a list of values, and a nested object is converted too. Empty values, 'raw' and other
	 * structures that aren't values are left out.
	 *
	 * @since 6.3.0
	 *
	 * @param array $item jf2 object, with 'type' such as 'card'. Default type card.
	 * @return array mf2 object, with 'type' such as array( 'h-card' ) and 'properties'.
	 */
	public static function jf2_to_mf2( $item ) {
		$properties = array();
		foreach ( (array) $item as $key => $value ) {
			if ( 'type' === $key || 'raw' === $key ) {
				continue;
			}
			$values = array();
			foreach ( ( is_array( $value ) && wp_is_numeric_array( $value ) ) ? $value : array( $value ) as $one ) {
				if ( is_array( $one ) && isset( $one['type'] ) ) {
					$values[] = self::jf2_to_mf2( $one );
				} elseif ( is_scalar( $one ) && '' !== $one ) {
					$values[] = $one;
				}
			}
			if ( ! empty( $values ) ) {
				$properties[ $key ] = $values;
			}
		}
		$type = ( isset( $item['type'] ) && is_string( $item['type'] ) && '' !== $item['type'] ) ? $item['type'] : 'card';
		return array(
			'type'       => array( 'h-' . $type ),
			'properties' => $properties,
		);
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
		$check = apply_filters( 'pre_at_venue', null, $lat, $lng ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public API that predates the prefix rule; renaming it would break sites that use it. To be revisited with namespacing.

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

	/**
	 * Returns the ID of a post's venue.
	 *
	 * @since 5.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string The venue ID, or an empty string if the post has no venue.
	 */
	public static function get_post_venue( $post_id ) {
		return get_post_meta( $post_id, 'venue_id', true );
	}

	/**
	 * Sets the venue of a post.
	 *
	 * @since 5.0.0
	 * @since 6.0.0 A post without a location is given the venue's location.
	 *
	 * @param int $post_id  Post ID.
	 * @param int $venue_id Venue ID.
	 * @return int|bool Meta ID if the venue was added, true if it was changed, false otherwise.
	 */
	public static function set_post_venue( $post_id, $venue_id ) {
		if ( ! is_numeric( $venue_id ) ) {
			return false;
		}
		$return = update_post_meta( $post_id, 'venue_id', $venue_id );
		if ( (int) $venue_id > 0 && ! Location_Taxonomy::get_post_location( $post_id ) ) {
			$term = Location_Taxonomy::get_post_location( $venue_id );
			if ( $term ) {
				Location_Taxonomy::set_location( $post_id, $term );
			}
		}
		return $return;
	}

	/**
	 * Returns the venue matching a check-in, creating one if there is none.
	 *
	 * A venue matches when the check-in URL is a local venue, or as find_venue() finds it. A matched venue
	 * gains the check-in's identifier and alternate URLs. A new venue is published with public visibility,
	 * and its location term is found or created from the address.
	 *
	 * @since 5.0.0
	 * @since 6.1.0 Matches by identifier and by name nearby, and keeps the identifier and provider pages. Keeps
	 *              only what the provider allows; see storage(). Gives a new venue one of the user's venue types,
	 *              and its shared category (#729).
	 *
	 * @param array $venue Check-in or address properties. Needs a name or label.
	 * @return int|WP_Error|false The venue ID, or false without a name.
	 */
	public static function add_new_venue( $venue ) {
		$venue = Location_Taxonomy::normalize_address( $venue );
		if ( array_key_exists( 'name', $venue ) ) {
			$title = $venue['name'];
		} elseif ( array_key_exists( 'label', $venue ) ) {
			$title = $venue['label'];
		} else {
			return false;
		}

		$urls = self::venue_urls( $venue );
		// A check-in URL that is a local venue is that venue.
		if ( '' !== $urls['url'] ) {
			$id = url_to_postid( $urls['url'] );
			if ( $id ) {
				return $id;
			}
		}

		$id = self::find_venue( $venue, $urls );
		if ( $id ) {
			self::add_venue_sources( $id, $venue, $urls );
			return $id;
		}

		// The type is one of the user's own terms, so it is chosen before a reference provider's details are left out.
		$type     = self::match_venue_type( $venue );
		$category = self::sanitize_category( $venue['venue-category'] ?? '' );
		if ( Venue_Provider::STORAGE_REFERENCE === self::storage( $venue ) ) {
			$venue = self::reference_details( $venue );
		}

		$meta = array();
		if ( '' !== $urls['url'] ) {
			$meta['venue_url'] = $urls['url'];
		}
		if ( ! empty( $urls['alternate'] ) ) {
			$meta['venue_alternate'] = $urls['alternate'];
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
			self::add_venue_sources( $id, $venue, array() );
			if ( $type ) {
				wp_set_object_terms( $id, array( $type ), 'venue_type' );
			}
			if ( '' !== $category ) {
				update_post_meta( $id, 'venue_category', $category );
			}
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

	/**
	 * Leaves a venue's alternate URLs out of REST responses unless its location is public or the user can edit it.
	 *
	 * Alternate URLs include map pages, such as an OpenStreetMap or Google Maps link, that give away where a
	 * private venue is.
	 *
	 * @since 6.1.0
	 *
	 * @param WP_REST_Response $response Response.
	 * @param WP_Post          $post     Venue.
	 * @return WP_REST_Response Response.
	 */
	public static function rest_prepare_venue( $response, $post ) {
		$data = $response->get_data();
		if ( ! isset( $data['meta']['venue_alternate'] ) || current_user_can( 'edit_post', $post->ID ) || 'public' === get_post_geodata( $post->ID, 'visibility' ) ) {
			return $response;
		}
		unset( $data['meta']['venue_alternate'] );
		$response->set_data( $data );
		return $response;
	}

	/**
	 * Returns what may be kept of a check-in's venue, from the venue provider that found it.
	 *
	 * A check-in without a provider, such as one a Micropub client sent, is the user's own, and is kept.
	 *
	 * @since 6.1.0
	 *
	 * @param array $venue Check-in or venue properties, with 'provider', a venue provider's slug.
	 * @return string Venue_Provider::STORAGE_FULL or Venue_Provider::STORAGE_REFERENCE.
	 */
	public static function storage( $venue ) {
		$storage  = Venue_Provider::STORAGE_FULL;
		$slug     = ( isset( $venue['provider'] ) && is_string( $venue['provider'] ) ) ? $venue['provider'] : '';
		$provider = '' === $slug ? null : Loc_Config::venue_provider( $slug );
		if ( $provider instanceof Venue_Provider ) {
			$storage = $provider->storage();
		}
		/**
		 * Filters what may be kept of a check-in's venue.
		 *
		 * @since 6.1.0
		 *
		 * @param string $storage Venue_Provider::STORAGE_FULL to keep the details, or Venue_Provider::STORAGE_REFERENCE
		 *                        to keep only the name, coordinates, identifier and links, with the address from the
		 *                        site's geocoder.
		 * @param array  $venue   Check-in or venue properties.
		 */
		$storage = apply_filters( 'sloc_venue_storage', $storage, $venue );
		return Venue_Provider::STORAGE_REFERENCE === $storage ? $storage : Venue_Provider::STORAGE_FULL;
	}

	/**
	 * Returns a venue from a provider that allows keeping only references: its name, coordinates, identifier and
	 * links, with the address looked up with the site's geocoders.
	 *
	 * @since 6.1.0
	 *
	 * @param array $venue Check-in or venue properties.
	 * @return array Venue properties.
	 */
	public static function reference_details( $venue ) {
		$kept = wp_array_slice_assoc( $venue, array( 'name', 'label', 'latitude', 'longitude', 'altitude', 'zoom', 'url', 'uid', 'provider' ) );
		if ( ! is_numeric( $kept['latitude'] ?? null ) || ! is_numeric( $kept['longitude'] ?? null ) ) {
			return $kept;
		}
		$address = Sloc_Provider_Chain::reverse_lookup( $kept['latitude'], $kept['longitude'] );
		if ( is_array( $address ) ) {
			$address = wp_array_slice_assoc( $address, array( 'street-address', 'extended-address', 'post-office-box', 'locality', 'region', 'region-code', 'postal-code', 'country-name', 'country-code' ) );
			$kept    = Location_Taxonomy::normalize_address( array_merge( $address, $kept ) );
		}
		return $kept;
	}

	/**
	 * Sanitizes the venue_url meta value.
	 *
	 * @since 6.2.0
	 *
	 * @param mixed $value URL.
	 * @return string An HTTP or HTTPS URL, or an empty string.
	 */
	public static function sanitize_url( $value ) {
		$url = is_string( $value ) ? trim( $value ) : '';
		// Only web addresses; esc_url_raw() would make one of a word.
		return preg_match( '#^https?://#i', $url ) ? esc_url_raw( $url, array( 'http', 'https' ) ) : '';
	}

	/**
	 * Sanitizes a venue's alternate URLs.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value URLs, as a list, or a string with one URL per line.
	 * @return string[] HTTP and HTTPS URLs, each once.
	 */
	public static function sanitize_alternate( $value ) {
		$list = is_string( $value ) ? preg_split( '/[\r\n]+/', $value ) : (array) $value;
		$urls = array();
		foreach ( $list as $url ) {
			$url = self::sanitize_url( $url );
			if ( '' !== $url && ! in_array( $url, $urls, true ) ) {
				$urls[] = $url;
			}
		}
		return $urls;
	}

	/**
	 * Returns the name of a site with pages about places, for a link to one of them.
	 *
	 * @since 6.1.0
	 *
	 * @param string $url URL.
	 * @return string Name, such as OpenStreetMap, or the host without www.
	 */
	public static function link_name( $url ) {
		$host  = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$names = array(
			'openstreetmap.org' => 'OpenStreetMap',
			'osm.org'           => 'OpenStreetMap',
			'google.com'        => 'Google Maps',
			'maps.app.goo.gl'   => 'Google Maps',
			'maps.apple.com'    => 'Apple Maps',
			'bing.com'          => 'Bing Maps',
			'here.com'          => 'HERE WeGo',
			'foursquare.com'    => 'Foursquare',
			'4sq.com'           => 'Foursquare',
			'swarmapp.com'      => 'Swarm',
			'wikipedia.org'     => 'Wikipedia',
			'wikidata.org'      => 'Wikidata',
			'yelp.com'          => 'Yelp',
			'tripadvisor.com'   => 'Tripadvisor',
		);
		foreach ( $names as $match => $name ) {
			if ( $host === $match || substr( $host, -strlen( $match ) - 1 ) === '.' . $match ) {
				return $name;
			}
		}
		return preg_replace( '/^www\./', '', $host );
	}

	/**
	 * Returns the links of a venue: its website, then its pages on other sites.
	 *
	 * The pages on other sites, which can be map links, are left out unless the venue's location is public.
	 *
	 * @since 6.1.0
	 *
	 * @param int $venue_id Venue ID.
	 * @return array[] Links, each with 'url', 'name' and 'type', 'website' or 'alternate'.
	 */
	public static function get_venue_links( $venue_id ) {
		$links   = array();
		$website = esc_url_raw( (string) get_post_meta( $venue_id, 'venue_url', true ), array( 'http', 'https' ) );
		if ( '' !== $website ) {
			$links[] = array(
				'url'  => $website,
				'name' => __( 'Website', 'simple-location' ),
				'type' => 'website',
			);
		}
		if ( 'public' === get_post_geodata( $venue_id, 'visibility' ) ) {
			foreach ( self::sanitize_alternate( get_post_meta( $venue_id, 'venue_alternate', true ) ) as $url ) {
				if ( $url !== $website ) {
					$links[] = array(
						'url'  => $url,
						'name' => self::link_name( $url ),
						'type' => 'alternate',
					);
				}
			}
		}
		/**
		 * Filters the links shown for a venue.
		 *
		 * @since 6.1.0
		 *
		 * @param array[] $links    Links, each with 'url', 'name' and 'type', 'website' or 'alternate'.
		 * @param int     $venue_id Venue ID.
		 */
		return (array) apply_filters( 'sloc_venue_links', $links, $venue_id );
	}

	/**
	 * Returns a venue's links as a list, each marked up as a URL of the venue.
	 *
	 * @since 6.1.0
	 *
	 * @param int $venue_id Venue ID.
	 * @return string HTML list, or an empty string without links.
	 */
	public static function venue_links_html( $venue_id ) {
		$items = '';
		foreach ( self::get_venue_links( $venue_id ) as $link ) {
			if ( empty( $link['url'] ) ) {
				continue;
			}
			$items .= sprintf(
				'<li class="venue-link venue-link-%1$s"><a class="u-url" href="%2$s">%3$s</a></li>',
				esc_attr( $link['type'] ?? 'alternate' ),
				esc_url( $link['url'] ),
				esc_html( $link['name'] ?? $link['url'] )
			);
		}
		return '' === $items ? '' : '<ul class="sloc-venue-links">' . $items . '</ul>';
	}

	/**
	 * Adds a venue's links to its content. Hooked to `the_content`.
	 *
	 * @since 6.1.0
	 *
	 * @param string $content Content.
	 * @return string Content, with the links on a venue.
	 */
	public static function venue_content( $content ) {
		$venue_id = get_the_ID();
		if ( ! $venue_id || 'venue' !== get_post_type( $venue_id ) ) {
			return $content;
		}
		return $content . self::venue_links_html( $venue_id );
	}

	/**
	 * Sanitizes a venue's shared category.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Category, a key of Venue_Provider::categories(), such as 'food'.
	 * @return string Category, or an empty string for anything else.
	 */
	public static function sanitize_category( $value ) {
		return ( is_string( $value ) && isset( Venue_Provider::categories()[ $value ] ) ) ? $value : '';
	}

	/**
	 * Returns the existing venue type that fits a check-in's venue.
	 *
	 * A venue type fits when its slug or name is the venue's shared category, such as food or Food, or one of the
	 * provider's own categories, such as cafe. Venue types are the user's own: none is created.
	 *
	 * @since 6.1.0
	 *
	 * @param array $venue Check-in or venue properties, with 'venue-category' and 'category'.
	 * @return int Venue type term ID, or 0 if none fits.
	 */
	public static function match_venue_type( $venue ) {
		$candidates = array();
		$category   = self::sanitize_category( $venue['venue-category'] ?? '' );
		if ( '' !== $category && 'other' !== $category ) {
			$candidates[] = $category;
			$candidates[] = Venue_Provider::categories()[ $category ];
		}
		foreach ( (array) ( $venue['category'] ?? array() ) as $name ) {
			if ( is_string( $name ) && '' !== trim( $name ) ) {
				$candidates[] = str_replace( '_', ' ', trim( $name ) );
			}
		}
		$term_id = 0;
		foreach ( $candidates as $candidate ) {
			$term = get_term_by( 'slug', sanitize_title( $candidate ), 'venue_type' );
			if ( ! $term ) {
				$term = get_term_by( 'name', $candidate, 'venue_type' );
			}
			if ( $term instanceof WP_Term ) {
				$term_id = $term->term_id;
				break;
			}
		}
		/**
		 * Filters the venue type given to a new venue.
		 *
		 * @since 6.1.0
		 *
		 * @param int   $term_id Venue type term ID, or 0 for none.
		 * @param array $venue   Check-in or venue properties.
		 */
		return (int) apply_filters( 'sloc_venue_type', $term_id, $venue );
	}

	/**
	 * Returns the venue type suggested for a venue without one, from its shared category.
	 *
	 * @since 6.1.0
	 *
	 * @param int $venue_id Venue ID.
	 * @return array|null Array with 'category', 'name' and 'term_id', the existing term or 0 for a new one; or null
	 *                    if the venue has a type, or no category to suggest one from.
	 */
	public static function suggested_type( $venue_id ) {
		$category = self::sanitize_category( get_post_meta( $venue_id, 'venue_category', true ) );
		if ( '' === $category || 'other' === $category || ! empty( wp_get_object_terms( $venue_id, 'venue_type', array( 'fields' => 'ids' ) ) ) ) {
			return null;
		}
		return array(
			'category' => $category,
			'name'     => Venue_Provider::categories()[ $category ],
			'term_id'  => self::match_venue_type( array( 'venue-category' => $category ) ),
		);
	}

	/**
	 * Gives a venue the venue type suggested for it, creating the type if the user may.
	 *
	 * @since 6.1.0
	 *
	 * @param int $venue_id Venue ID.
	 * @return int|false Venue type term ID, or false if there is no suggestion or the user can't add it.
	 */
	public static function add_suggested_type( $venue_id ) {
		$suggestion = self::suggested_type( $venue_id );
		$taxonomy   = get_taxonomy( 'venue_type' );
		if ( ! $suggestion || ! $taxonomy || ! current_user_can( $taxonomy->cap->assign_terms ) || ! current_user_can( 'edit_post', $venue_id ) ) {
			return false;
		}
		$term_id = $suggestion['term_id'];
		if ( ! $term_id ) {
			$term = wp_insert_term( $suggestion['name'], 'venue_type', array( 'slug' => $suggestion['category'] ) );
			if ( is_wp_error( $term ) ) {
				return false;
			}
			$term_id = (int) $term['term_id'];
		}
		wp_set_object_terms( $venue_id, array( $term_id ), 'venue_type', true );
		return $term_id;
	}

	/**
	 * Returns the hosts of sites with pages about places, such as map and listing sites.
	 *
	 * A link to one of them is a page about the venue, not the venue's own website.
	 *
	 * @since 6.1.0
	 *
	 * @return string[] Hosts. Subdomains of each host match too.
	 */
	public static function alternate_hosts() {
		$hosts = array(
			'openstreetmap.org',
			'osm.org',
			'google.com',
			'maps.app.goo.gl',
			'maps.apple.com',
			'bing.com',
			'here.com',
			'foursquare.com',
			'4sq.com',
			'swarmapp.com',
			'wikipedia.org',
			'wikidata.org',
			'yelp.com',
			'tripadvisor.com',
		);
		/**
		 * Filters the hosts of sites with pages about places.
		 *
		 * A check-in URL on one of these hosts is kept as an alternate URL of the venue, not its website.
		 *
		 * @since 6.1.0
		 *
		 * @param string[] $hosts Hosts, such as 'openstreetmap.org'. Subdomains match too.
		 */
		return (array) apply_filters( 'sloc_venue_alternate_hosts', $hosts );
	}

	/**
	 * Returns whether a URL is a page about a place on another site, such as a map or listing.
	 *
	 * @since 6.1.0
	 *
	 * @param string $url URL.
	 * @return bool True for a URL on one of alternate_hosts().
	 */
	public static function is_alternate_url( $url ) {
		$host = is_string( $url ) ? wp_parse_url( $url, PHP_URL_HOST ) : null;
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}
		$host = strtolower( $host );
		foreach ( self::alternate_hosts() as $match ) {
			$match = strtolower( (string) $match );
			if ( '' !== $match && ( $host === $match || substr( $host, -strlen( $match ) - 1 ) === '.' . $match ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Sorts a check-in's URLs into the venue's website and pages about it on other sites.
	 *
	 * The first URL that isn't on alternate_hosts() is the website; every other URL, and the identifier if it
	 * is a web address, is an alternate URL.
	 *
	 * @since 6.1.0
	 *
	 * @param array $venue Check-in or venue properties, with 'url' as a string or a list, and 'uid'.
	 * @return array Array with 'url', a string that can be empty, and 'alternate', a list of URLs.
	 */
	public static function venue_urls( $venue ) {
		$uid  = $venue['uid'] ?? null;
		$list = (array) ( $venue['url'] ?? array() );
		// Only an identifier that is a web address is a page about the venue.
		if ( is_string( $uid ) && preg_match( '#^https?://#i', $uid ) ) {
			$list[] = $uid;
		}
		$urls = array(
			'url'       => '',
			'alternate' => array(),
		);
		foreach ( $list as $url ) {
			$url = is_string( $url ) ? esc_url_raw( trim( $url ), array( 'http', 'https' ) ) : '';
			if ( '' === $url ) {
				continue;
			}
			if ( '' === $urls['url'] && ! self::is_alternate_url( $url ) && $uid !== $url ) {
				$urls['url'] = $url;
			} elseif ( $url !== $urls['url'] && ! in_array( $url, $urls['alternate'], true ) ) {
				$urls['alternate'][] = $url;
			}
		}
		return $urls;
	}

	/**
	 * Returns the venue a check-in is at, if there is one.
	 *
	 * In order: a venue with the check-in's identifier ('uid'); a venue with one of its URLs as its URL or an
	 * alternate URL; a venue with the same name, or other name, whose radius, or MATCH_RADIUS if larger, includes
	 * the check-in.
	 *
	 * @since 6.1.0
	 *
	 * @param array $venue Check-in or venue properties: 'name' or 'label', 'uid', 'url', 'latitude' and 'longitude'.
	 * @param array $urls  Optional. URLs, as venue_urls() returns. Default the check-in's.
	 * @return int|false Venue ID, or false if there is none.
	 */
	public static function find_venue( $venue, $urls = null ) {
		$urls     = is_array( $urls ) ? $urls : self::venue_urls( $venue );
		$venue_id = false;
		$uid      = ( isset( $venue['uid'] ) && is_string( $venue['uid'] ) ) ? trim( $venue['uid'] ) : '';
		if ( '' !== $uid ) {
			$venue_id = self::first_venue(
				array(
					array(
						'key'   => self::UID_KEY,
						'value' => $uid,
					),
				)
			);
		}

		foreach ( array_filter( array_merge( array( $urls['url'] ), $urls['alternate'] ) ) as $url ) {
			if ( $venue_id ) {
				break;
			}
			$venue_id = self::first_venue(
				array(
					'relation' => 'OR',
					array(
						'key'   => 'venue_url',
						'value' => $url,
					),
					// A list is stored serialized, with each URL in quotes.
					array(
						'key'     => 'venue_alternate',
						'compare' => 'LIKE',
						'value'   => '"' . $url . '"',
					),
					array(
						'key'   => 'venue_alternate',
						'value' => $url,
					),
				)
			);
		}

		$name = $venue['name'] ?? $venue['label'] ?? '';
		if ( ! $venue_id && is_string( $name ) && '' !== $name && is_numeric( $venue['latitude'] ?? null ) && is_numeric( $venue['longitude'] ?? null ) ) {
			$named = get_posts(
				array(
					'post_type'   => 'venue',
					'post_status' => 'any',
					'fields'      => 'ids',
					'title'       => $name,
				)
			);
			// Other names, such as those of venues merged into one.
			$aliased = get_posts(
				array(
					'post_type'   => 'venue',
					'post_status' => 'any',
					'fields'      => 'ids',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Venues are matched by their other names.
					'meta_query'  => array(
						array(
							'key'   => self::ALIAS_KEY,
							'value' => $name,
						),
					),
				)
			);
			foreach ( array_unique( array_merge( $named, $aliased ) ) as $id ) {
				$geo = get_post_geodata( $id );
				if ( ! is_array( $geo ) || ! is_numeric( $geo['latitude'] ?? null ) || ! is_numeric( $geo['longitude'] ?? null ) ) {
					continue;
				}
				$radius = max( self::MATCH_RADIUS, (int) get_post_meta( $id, 'venue_radius', true ) );
				if ( geo_in_radius( $geo['latitude'], $geo['longitude'], $venue['latitude'], $venue['longitude'], $radius ) ) {
					$venue_id = $id;
					break;
				}
			}
		}

		/**
		 * Filters the venue a check-in is at.
		 *
		 * @since 6.1.0
		 *
		 * @param int|false $venue_id Venue ID, or false to create a venue.
		 * @param array     $venue    Check-in or venue properties.
		 */
		$venue_id = apply_filters( 'sloc_find_venue', $venue_id, $venue );
		return $venue_id ? (int) $venue_id : false;
	}

	/**
	 * Returns the first venue matching a meta query.
	 *
	 * @since 6.1.0
	 *
	 * @param array $meta_query Meta query.
	 * @return int|false Venue ID, or false if none match.
	 */
	protected static function first_venue( $meta_query ) {
		$match = get_posts(
			array(
				'post_type'      => 'venue',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Venues are matched by their identifiers and URLs.
				'meta_query'     => $meta_query,
			)
		);
		return empty( $match ) ? false : (int) $match[0];
	}

	/**
	 * Merges venues into one.
	 *
	 * The posts and photos at each duplicate, and its child venues, move to the venue kept. The kept venue gains the
	 * duplicates' identifiers, sources, URLs, names (as other names, for matching) and venue types, and keeps its own
	 * title, website and location. The duplicates are deleted, and their addresses redirect to the kept venue; see
	 * Sloc_Merge.
	 *
	 * @since 6.1.0
	 *
	 * @param int   $keep       Venue to keep.
	 * @param int[] $duplicates Venues to merge into it. The kept venue, and anything that isn't a venue the user can
	 *                          delete, is skipped.
	 * @return int Number of venues merged.
	 */
	public static function merge_venues( $keep, $duplicates ) {
		$keep = (int) $keep;
		if ( 'venue' !== get_post_type( $keep ) || ! current_user_can( 'edit_post', $keep ) ) {
			return 0;
		}
		$count = 0;
		foreach ( array_unique( array_map( 'intval', (array) $duplicates ) ) as $duplicate ) {
			if ( $duplicate === $keep || 'venue' !== get_post_type( $duplicate ) || ! current_user_can( 'delete_post', $duplicate ) ) {
				continue;
			}
			$old_link = get_permalink( $duplicate );
			$new_link = get_permalink( $keep );
			Sloc_Merge::add_redirects( 'post', $keep, $duplicate, $old_link );

			// Posts and photos at the duplicate, and the venue named in their check-ins.
			$posts = get_posts(
				array(
					'post_type'   => get_post_types(),
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Run only when merging venues.
					'meta_query'  => array(
						array(
							'key'   => 'venue_id',
							'value' => $duplicate,
						),
					),
				)
			);
			foreach ( $posts as $post_id ) {
				update_post_meta( $post_id, 'venue_id', $keep );
				$checkin = get_post_meta( $post_id, 'mf2_checkin', true );
				if ( is_array( $checkin ) && isset( $checkin['properties']['url'] ) && in_array( $old_link, (array) $checkin['properties']['url'], true ) ) {
					$checkin['properties']['url'] = array( $new_link );
					if ( isset( $checkin['properties']['name'] ) ) {
						$checkin['properties']['name'] = array( get_the_title( $keep ) );
					}
					update_post_meta( $post_id, 'mf2_checkin', $checkin );
				}
			}

			// Child venues. A kept venue inside the duplicate takes the duplicate's place.
			if ( (int) wp_get_post_parent_id( $keep ) === $duplicate ) {
				wp_update_post(
					array(
						'ID'          => $keep,
						'post_parent' => wp_get_post_parent_id( $duplicate ),
					)
				);
			}
			$children = get_posts(
				array(
					'post_type'   => 'venue',
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
					'post_parent' => $duplicate,
				)
			);
			foreach ( $children as $child ) {
				if ( (int) $child !== $keep ) {
					wp_update_post(
						array(
							'ID'          => $child,
							'post_parent' => $keep,
						)
					);
				}
			}

			// Identifiers and sources.
			foreach ( get_post_meta( $duplicate, self::UID_KEY ) as $uid ) {
				if ( ! in_array( $uid, get_post_meta( $keep, self::UID_KEY ), true ) ) {
					add_post_meta( $keep, self::UID_KEY, $uid );
				}
			}
			$sources = self::get_venue_sources( $keep );
			$known   = wp_list_pluck( $sources, 'uid' );
			foreach ( self::get_venue_sources( $duplicate ) as $source ) {
				if ( ! in_array( $source['uid'] ?? null, $known, true ) ) {
					$sources[] = $source;
				}
			}
			if ( ! empty( $sources ) ) {
				update_post_meta( $keep, 'venue_sources', $sources );
			}

			// The duplicate's website and other pages become other pages of the kept venue.
			$urls = self::sanitize_alternate( array_merge( array( (string) get_post_meta( $duplicate, 'venue_url', true ) ), (array) get_post_meta( $duplicate, 'venue_alternate', true ) ) );
			self::add_venue_sources(
				$keep,
				array(),
				array(
					'url'       => '',
					'alternate' => $urls,
				)
			);

			// The duplicate's names, so check-ins by them find the kept venue.
			$aliases = get_post_meta( $keep, self::ALIAS_KEY );
			foreach ( array_merge( array( get_the_title( $duplicate ) ), get_post_meta( $duplicate, self::ALIAS_KEY ) ) as $alias ) {
				if ( is_string( $alias ) && '' !== $alias && get_the_title( $keep ) !== $alias && ! in_array( $alias, $aliases, true ) ) {
					add_post_meta( $keep, self::ALIAS_KEY, $alias );
					$aliases[] = $alias;
				}
			}

			$types = wp_get_object_terms( $duplicate, 'venue_type', array( 'fields' => 'ids' ) );
			if ( is_array( $types ) && ! empty( $types ) ) {
				wp_set_object_terms( $keep, $types, 'venue_type', true );
			}

			wp_delete_post( $duplicate, true );
			++$count;
		}
		return $count;
	}

	/**
	 * Returns the venue providers and identifiers a venue was found with.
	 *
	 * @since 6.1.0
	 *
	 * @param int $venue_id Venue ID.
	 * @return array[] Sources, each with 'provider', the venue provider's slug or an empty string; 'uid'; and
	 *                 'retrieved', when it was found, in ISO 8601.
	 */
	public static function get_venue_sources( $venue_id ) {
		$sources = get_post_meta( $venue_id, 'venue_sources', true );
		return is_array( $sources ) ? array_values( array_filter( $sources, 'is_array' ) ) : array();
	}

	/**
	 * Adds a check-in's identifier and alternate URLs to a venue.
	 *
	 * The venue's own URL is set only if it has none, so a URL the user set is kept.
	 *
	 * @since 6.1.0
	 *
	 * @param int   $venue_id Venue ID.
	 * @param array $venue    Check-in or venue properties, with 'uid' and 'provider'.
	 * @param array $urls     URLs, as venue_urls() returns, to add to the venue. An empty array adds none.
	 */
	public static function add_venue_sources( $venue_id, $venue, $urls ) {
		$uid = ( isset( $venue['uid'] ) && is_string( $venue['uid'] ) ) ? trim( $venue['uid'] ) : '';
		if ( '' !== $uid && ! in_array( $uid, get_post_meta( $venue_id, self::UID_KEY ), true ) ) {
			add_post_meta( $venue_id, self::UID_KEY, $uid );
			$sources   = self::get_venue_sources( $venue_id );
			$sources[] = array(
				'provider'  => ( isset( $venue['provider'] ) && is_string( $venue['provider'] ) ) ? sanitize_key( $venue['provider'] ) : '',
				'uid'       => $uid,
				'retrieved' => gmdate( 'c' ),
			);
			update_post_meta( $venue_id, 'venue_sources', $sources );
		}

		if ( ! empty( $urls['url'] ) && '' === (string) get_post_meta( $venue_id, 'venue_url', true ) ) {
			update_post_meta( $venue_id, 'venue_url', $urls['url'] );
		}
		$add = array_merge( empty( $urls['url'] ) ? array() : array( $urls['url'] ), $urls['alternate'] ?? array() );
		if ( ! empty( $add ) ) {
			$current   = get_post_meta( $venue_id, 'venue_alternate', true );
			$current   = array_values( array_filter( (array) $current ) );
			$own       = (string) get_post_meta( $venue_id, 'venue_url', true );
			$alternate = array_values( array_unique( array_merge( $current, array_diff( $add, array( $own ) ) ) ) );
			if ( $alternate !== $current ) {
				update_post_meta( $venue_id, 'venue_alternate', $alternate );
			}
		}
	}

	/**
	 * Returns the venue type terms of a venue.
	 *
	 * @since 5.0.2
	 *
	 * @param int|WP_Post|null $venue_id Venue ID or object. Defaults to the current post.
	 * @return int[]|false|string Term IDs; false if it has none, or an empty string if it isn't a venue.
	 */
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

	/**
	 * Whether a venue is inside another venue.
	 *
	 * @since 5.0.6
	 *
	 * @param int|WP_Post|null $venue_id Venue ID or object. Defaults to the current post.
	 * @return boolean Whether it has a parent.
	 */
	public static function has_parent_venue( $venue_id = null ) {
		return has_post_parent( $venue_id );
	}

	/**
	 * Returns the ID of the venue a venue is inside.
	 *
	 * @since 5.0.6
	 *
	 * @param int|WP_Post|null $venue_id Venue ID or object. Defaults to the current post.
	 * @return int|false Parent venue ID, or false if it has none.
	 */
	public static function get_parent_venue( $venue_id = null ) {
		return wp_get_post_parent_id( $venue_id );
	}

	/**
	 * Returns the IDs of a venue's published child venues.
	 *
	 * @since 5.0.7
	 * @since 6.0.0 Searches venues instead of posts and returns every child.
	 *
	 * @param int|WP_Post|null $venue_id Venue. Defaults to the current post.
	 * @return int[]|false Child venue IDs, or false if the venue doesn't exist.
	 */
	public static function get_venue_children( $venue_id = null ) {
		$venue = get_post( $venue_id );
		if ( ! $venue ) {
			return false;
		}

		return get_posts(
			array(
				'post_type'   => 'venue',
				'post_parent' => $venue->ID,
				'fields'      => 'ids',
				'numberposts' => -1,
			)
		);
	}

	/**
	 * Returns the IDs of the venues a venue is inside, nearest first.
	 *
	 * @since 5.0.6
	 *
	 * @param int|WP_Post|null $venue_id Venue ID or object. Defaults to the current post.
	 * @return int[]|false Ancestor IDs, or false if the venue doesn't exist.
	 */
	public static function get_venue_ancestors( $venue_id = null ) {
		$venue = get_post( $venue_id );
		if ( ! $venue ) {
			return false;
		}
		return get_ancestors( $venue->ID, 'venue', 'post_type' );
	}

	/**
	 * Returns the images attached to the posts at a venue.
	 *
	 * @since 5.0.6
	 *
	 * @param int|WP_Post|null $venue_id Venue ID or object.
	 * @return int[] Attachment IDs.
	 */
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

	/**
	 * Returns a gallery of the images attached to the posts at a venue.
	 *
	 * @since 5.0.6
	 *
	 * @param int|WP_Post|null $venue_id Venue ID or object. Defaults to the current post.
	 * @return string Gallery markup.
	 */
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

	/**
	 * Returns the IDs of the posts at a venue.
	 *
	 * @since 5.0.2
	 *
	 * @param int|WP_Post|null $venue_id Venue ID or object. Defaults to the current post.
	 * @return int[]|false Post IDs, or false if it isn't a venue.
	 */
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
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Posts at a venue are found by their venue ID.
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

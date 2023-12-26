<?php
/**
 * Location Taxonomy Class
 *
 * Registers the taxonomy and sets its behavior.
 *
 * @package Post Kinds
 */

add_action( 'init', array( 'Location_Taxonomy', 'init' ) );

/**
 * Class that handles the Location taxonomy functions.
 */
final class Location_Taxonomy {
	use Geolocation_Trait;

	public static function init() {
		self::register();
		add_action( 'location_add_form_fields', array( __CLASS__, 'create_screen_fields' ), 10 );
		add_action( 'location_edit_form_fields', array( __CLASS__, 'edit_screen_fields' ), 10, 2 );
		add_action( 'created_location', array( __CLASS__, 'save_data' ), 10 );
		add_action( 'edited_location', array( __CLASS__, 'save_data' ), 10 );
		add_action( 'location_pre_add_form', array( __CLASS__, 'pre_add_form' ), 10 );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_location_posts' ) );
		add_filter( 'manage_location_custom_column', array( __CLASS__, 'manage_column_content' ), 10, 3 );
		add_filter( 'manage_edit-location_columns', array( __CLASS__, 'manage_column' ), 10 );
		add_filter( 'taxonomy_parent_dropdown_args', array( __CLASS__, 'taxonomy_parent_dropdown_args' ), 10, 3 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'location_dropdown' ), 12, 2 );

		add_filter( 'get_the_archive_title', array( __CLASS__, 'archive_title' ), 10 );
		add_filter( 'get_pages_query_args', array( __CLASS__, 'get_pages_query_args' ), 10, 2 );
	}

	public static function admin_menu() {
		remove_meta_box( 'locationdiv', 'post', 'side' );
		remove_meta_box( 'locationdiv', 'venue', 'side' );
	}

	// Passes Tax Queries through for get_pages
	public static function get_pages_query_args( $query_args, $parsed_args ) {
		if ( array_key_exists( 'tax_query', $parsed_args ) ) {
			$query_args['tax_query'] = $parsed_args['tax_query'];
		}
		return $query_args;
	}

	public static function taxonomy_parent_dropdown_args( $dropdown_args, $taxonomy, $context ) {
		if ( 'location' === $taxonomy ) {
			$dropdown_args['depth'] = 2;
		}
		return $dropdown_args;
	}

	public static function archive_title( $title ) {
		if ( ! is_tax( 'location' ) ) {
			return $title;
		}
		$term    = get_queried_object();
		$display = self::display_name( $term->term_id );
		$type    = self::location_type( self::get_location_type( $term->term_id ) );
		/* translators: 1. Location Type. 2: Location Name */
		return sprintf( __( '%1$1s: %2$2s', 'simple-location' ), $type, $display );
	}

	public static function manage_column( $columns ) {
		$columns['location-type'] = __( 'Type', 'simple-location' );
		return $columns;
	}

	public static function manage_column_content( $content, $column_name, $term_id ) {
		if ( 'location-type' === $column_name ) {
			return self::location_type( self::get_location_type( $term_id ) );
		}
		return $content;
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
	public static function location_dropdown( $post_type, $which ) {
		if ( ! post_type_supports( $post_type, 'geo-location' ) ) {
			return;
		}
		$type = get_post_type_object( $post_type );
		$selected = '';
		if ( isset( $_REQUEST['location'] ) ) {
			$selected = sanitize_text_field( $_REQUEST['location'] );
		}
		wp_dropdown_categories( 
			array(
				'name' => 'location',
				'id' => 'location',
				'show_option_none' => $type->labels->all_items,
				'option_none_value' => '',
				'hierarchical' => true,
				'taxonomy' => 'location',
				'value_field' => 'slug',
				'selected' => $selected
			)
		);
	}

	/**
	 * Filters Location in Posts.
	 *
	 * @param WP_Query $query Query Object.
	 *
	 * @since 1.0.0
	 */
	public static function filter_location_posts( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return $query;
		}
		if ( is_tax( 'location' ) ) {
			if ( ! current_user_can( 'read_posts_location' ) ) {
				$public = array(
					'key'     => 'geo_public',
					'type'    => 'numeric',
					'compare' => '=',
					'value'   => 1,
				);
				$query->set( 'meta_query', array( $public ) );
			}
			$post_types = get_post_types_by_support( 'geo-location' );
			$search     = array_search( 'venue', $post_types, true );
			unset( $post_types[ $search ] );
			$query->set( 'post_type', $post_types );
		}
		return $query;
	}

	public static function pre_add_form() {
		printf( '<p>%1$s</p>', esc_html__( 'Locations allow for 3 levels of hierarchy: Country, region, and locality. Countries have no parent. Regions have a country as a parent. Localities have regions are their parent.', 'simple-location' ) );
	}

	public static function create_screen_fields( $taxonomy ) {
		echo '<div class="form-field form-required">';
				printf( '<label for="location-code">%1$s</label>', esc_html__( 'Location Code:', 'simple-location' ) );
		printf( '<input class="widefat" type="text" name="location-code" required />' );
		printf( '<p>%1$s</p>', esc_html__( 'Required. The code for this location. If no code, you can use the location.', 'simple-location' ) );
		echo '</div>';
		wp_nonce_field( 'create', 'location_taxonomy_meta' );
	}

	public static function edit_screen_fields( $term, $taxonomy ) {
		$type = self::get_location_type( $term->term_id );
		echo '<tr class="form-field">';

		switch ( $type ) {
			case 'country':
				?>
				<th><label for="country"><?php esc_html_e( 'Country:', 'simple-location' ); ?></label></th>
				<td><?php self::country_select( get_term_meta( $term->term_id, 'country', true ) ); ?>
					<p class="description"><?php esc_html_e( 'Country.', 'simple-location' ); ?></p></td>
				</td>
				<?php
				break;
			case 'region':
				?>
				<th><label for="region"><?php esc_html_e( 'Region:', 'simple-location' ); ?></label></th>
				<td><?php self::region_select( get_term_meta( $term->term_id, 'region', true ), self::get_parent_country( $term->term_id ) ); ?>

				<p class="description"><?php esc_html_e( 'The state, county, or province code for the location(attempts to use ISO3166-2 coding for regions). This can be different than than the name of the region, but is usually the same as the slug(accounting for multiple places with the same name', 'simple-location' ); ?></p>
				</td>
				<?php
				break;
			case 'locality':
				?>
				<th><label for="locality"><?php esc_html_e( 'Locality:', 'simple-location' ); ?></label></th>
				<td><input class="widefat" type="text" name="locality" value="<?php echo esc_attr( get_term_meta( $term->term_id, 'locality', true ) ); ?>" required />
					<p class="description"><?php esc_html_e( 'The city, village, or town for the location', 'simple-location' ); ?></p>
				</td>
				<?php
				break;
			default:
				?>
				<p class="notice notice-error"><?php esc_html_e( 'Error: Cannot Identify Type. This likely means the parent of this term is a locality. Localities can have no child. Change Parent to Country, Region, or None.', 'simple-location' ); ?> </p>
				<?php
		}
		echo '</tr>';
		wp_nonce_field( 'edit', 'location_taxonomy_meta' );
	}

	public static function save_data( $term_id ) {
		// This option only exists when using one of the two forms.
		if ( ! array_key_exists( 'location_taxonomy_meta', $_POST ) ) {
			return;
		}
		$nonce = sanitize_text_field( $_POST['location_taxonomy_meta'] );
		if ( ! wp_verify_nonce( $nonce, 'edit' ) && ! wp_verify_nonce( $nonce, 'create' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		// phpcs:disable
		$term = get_term( $term_id );

		if ( 'location' !== $term->taxonomy ) {
			return;
		}

		$type = self::get_location_type( $term_id );

		if ( array_key_exists( 'location-code', $_POST ) ) {
			if ( $type ) {
				update_term_meta( $term_id, $type, sanitize_text_field( $_POST['location-code'] ) );
			}
			return;
			
		}

		if ( array_key_exists( $type, $_POST ) ) {
			update_term_meta( $term_id, $type, sanitize_text_field( $_POST[$type] ) );
		} else {
			update_term_meta( $term_id, $type, strtoupper( $term->slug ) );
		}
		// phpcs:enable
	}

	/**
	 * Register the custom taxonomy for location.
	 */
	public static function register() {
		global $wp_rewrite;
		$labels = array(
			'name'                       => _x( 'Locations', 'taxonomy general name', 'simple-location' ),
			'singular_name'              => _x( 'Location', 'taxonomy singular name', 'simple-location' ),
			'search_items'               => _x( 'Search Locations', 'search locations', 'simple-location' ),
			'popular_items'              => _x( 'Popular Locations', 'popular locations', 'simple-location' ),
			'all_items'                  => _x( 'All Locations', 'all taxonomy items', 'simple-location' ),
			'parent_item'                => _x( 'Parent Location', 'taxonomy parent item', 'simple-location' ),
			'parent_item_colon'          => _x( 'Parent Location:', 'taxonomy parent item with colon', 'simple-location' ),
			'edit_item'                  => _x( 'Edit Location', 'edit taxonomy item', 'simple-location' ),
			'view_item'                  => _x( 'View Location', 'view taxonomy item', 'simple-location' ),
			'update_item'                => _x( 'Update Location', 'update taxonomy item', 'simple-location' ),
			'add_new_item'               => _x( 'Add New Location', 'add taxonomy item', 'simple-location' ),
			'new_item_name'              => _x( 'New Location', 'new taxonomy item', 'simple-location' ),
			'separate_items_with_commas' => _x( 'Separate locations with commas', 'separate kinds with commas', 'simple-location' ),
			'add_or_remove_items'        => _x( 'Add or remove location', 'add or remove items', 'simple-location' ),
			'choose_from_most_used'      => _x( 'Choose from the most used location', 'choose most used', 'simple-location' ),
			'not found'                  => _x( 'No locations found', 'no locations found', 'simple-location' ),
			'no_terms'                   => _x( 'No locations', 'no locations', 'simple-location' ),
			'name_field_description'     => __( 'The name of the location', 'simple-location' ),
			'parent_field_description'   => __( 'Localities should have a Region as a Parent, and Regions should have a Country as their Parent', 'simple-location' ),
			'slug_field_description'     => __( 'The slug field contains the ISO 3166-1 code for country(such as US) or region(such as NY) or the locality name. In the event of duplication, may use something else', 'simple-location' ),
			'desc_field_description'     => __( 'Will display on location archive pages if set', 'simple-location' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'hierarchical'       => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_in_rest'       => false,
			'show_tagcloud'      => true,
			'show_in_quick_edit' => false,
			'show_admin_column'  => true,
			'rewrite'            => array(
				'hierarchical' => true,
			),
			'query_var'          => true,
		);
		register_taxonomy( 'location', get_post_types_by_support( 'geo-location' ), $args );

		register_meta(
			'term',
			'country',
			array(
				'object_subtype'    => 'location',
				'type'              => 'string',
				'description'       => __( 'Country Code', 'simple-location' ),
				'single'            => true,
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			)
		);
		register_meta(
			'term',
			'region',
			array(
				'object_subtype'    => 'location',
				'type'              => 'string',
				'description'       => __( 'Region Code or name if code not available', 'simple-location' ),
				'single'            => true,
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			)
		);

		register_meta(
			'term',
			'locality',
			array(
				'object_subtype'    => 'location',
				'type'              => 'string',
				'description'       => __( 'Locality Name', 'simple-location' ),
				'single'            => true,
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			)
		);
	}

	/**
	 * Normalizes address data.
	 *
	 * @param array $address Address data.
	 * @return array Normalized Address Data.
	 */
	public static function normalize_address( $address ) {
		if ( array_key_exists( 'type', $address ) && array_key_exists( 'properties', $address ) ) {
			$address = $address['properties'];
			$address = array_map(
				function ( $v ) {
					if ( is_array( $v ) ) {
						return $v[0];
					}
				},
				$address
			);
		}
		if ( ! array_key_exists( 'country-code', $address ) && array_key_exists( 'country-name', $address ) ) {
			$address['country-code'] = Geo_Provider::country_code( $address['country-name'] );
		}
		if ( ! array_key_exists( 'country-name', $address ) && array_key_exists( 'country-code', $address ) ) {
			$address['country-code'] = Geo_Provider::country_name( $address['country-code'] );
		}
		if ( array_key_exists( 'region', $address ) && ! array_key_exists( 'region-code', $address ) ) {
			$address['region-code'] = $address['region'];
		}
		if ( ! array_key_exists( 'region-code', $address ) && array_key_exists( 'region-name', $address ) ) {
			$address['region-code'] = Geo_Provider::country_code( $address['region-name'] );
		}
		if ( ! array_key_exists( 'region-name', $address ) && array_key_exists( 'region-code', $address ) ) {
			$address['region-code'] = Geo_Provider::country_name( $address['region-code'] );
		}
		return array_filter( $address );
	}

	/**
	 * Returns the location term for the current post.
	 *
	 * @access public
	 *
	 * @param array|null $post Post to retrieve terms for.
	 * @return bool|string
	 */
	public static function get_location_taxonomy( $post = null ) {
		if ( is_array( $post ) && isset( $post['id'] ) ) {
			$post = $post['id'];
		}
		$post = get_post( $post );
		if ( ! $post ) {
			return false;
		}
		$_location = get_the_terms( $post->ID, 'location' );
		if ( ! empty( $_location ) ) {
			return array_shift( $_location );
		} else {
			return false;
		}
	}

	/**
	 * Returns the location slug for the current post.
	 *
	 * @access public
	 *
	 * @param array|null $post Post to retrieve terms for.
	 * @return bool|string
	 */
	public static function get_location_taxonomy_slug( $post = null ) {
		$location = self::get_location_taxonomy( $post );
		if ( ! $location ) {
			return $location;
		}
		return $location->slug;
	}

	/**
	 * Display taxonomy selection as select box
	 *
	 * @param WP_Post $post Post.
	 * @param array   $box Box Arguments.
	 */
	public static function taxonomy_select_meta_box( $post, $box ) {
		$defaults = array( 'taxonomy' => 'category' );

		if ( ! isset( $box['args'] ) || ! is_array( $box['args'] ) ) {
			$args = array();
		} else {
			$args = $box['args'];
		}

		$args     = wp_parse_args( $args, $defaults );
		$taxonomy = $args['taxonomy'];

		$tax          = get_taxonomy( $taxonomy );
		$selected     = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
		$hierarchical = $tax->hierarchical;
		?>
	<div id="taxonomy-<?php echo esc_attr( $taxonomy ); ?>" class="selectdiv">
		<?php if ( current_user_can( $tax->cap->edit_terms ) ) : ?>
			<?php
			if ( $hierarchical ) {
				wp_dropdown_categories(
					array(
						'taxonomy'         => $taxonomy,
						'class'            => 'widefat',
						'hide_empty'       => 0,
						'name'             => "tax_input[$taxonomy][]",
						'id'               => $taxonomy . '_dropdown',
						'selected'         => count( $selected ) >= 1 ? $selected[0] : '',
						'orderby'          => 'name',
						'hierarchical'     => $hierarchical,
						/* translators: 1. Taxonomy Label */
						'show_option_none' => sprintf( __( 'No %1$s', 'simple-location' ), $tax->label ),

					)
				);
			} else {
				?>
				<select name="<?php echo esc_attr( "tax_input[$taxonomy][]" ); ?>" class="widefat">
					<option value="0"></option>
					<?php foreach ( get_terms( $taxonomy, array( 'hide_empty' => false ) ) as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>" <?php echo selected( $term->term_id, count( $selected ) >= 1 ? $selected[0] : '' ); ?>><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php
			}
			?>
		<?php endif; ?>
	</div>
		<?php
	}

	/**
	 * Returns region.
	 * First region code, then region, then false.
	 *
	 * @param array $addr Address data.
	 * @return false|string Region.
	 */
	public static function region_return( $addr ) {
		if ( array_key_exists( 'region-code', $addr ) ) {
			return $addr['region-code'];
		} elseif ( array_key_exists( 'region', $addr ) ) {
			return $addr['region'];
		}
		return false;
	}

	/**
	 * Return an array of term_ids for localities in this region.
	 *
	 * @param int|string $country_code Term ID or Country Code.
	 * @return array|false Associative array of Term IDs as key and name as value or false if not found.
	 */
	public static function get_region_localities( $region_code, $country_code ) {
		$region = self::get_region(
			array(
				'region_code'  => $region_code,
				'country_code' => $country_code,
			)
		);
		if ( ! $region ) {
			return false;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'location',
				'hide_empty' => 0,
				'fields'     => 'id=>name',
				'childless'  => $childless,
				'parent'     => $region,
			)
		);
		if ( ! empty( $terms ) ) {
			return $terms;
		}
		return false;
	}

	/**
	 * Return the term_id for the locality if it exists.
	 *
	 * @param array $addr Array of Address Properties
	 * @return int|false Term ID or false if not found.
	 */
	public static function get_locality( $addr ) {
		if ( empty( $addr ) || ! is_array( $addr ) ) {
			return false;
		}

		if ( ! array_key_exists( 'locality', $addr ) ) {
			return false;
		}

		$args  = array(
			array(
				'key'   => 'locality',
				'value' => $addr['locality'],
			),
		);
		$terms = get_terms(
			array(
				'taxonomy'   => 'location',
				'hide_empty' => 0,
				'meta_query' => $args,
				'fields'     => 'ids',
			)
		);

		// If you did not find any term that matched return.
		if ( empty( $terms ) ) {
			return false;
		}

		foreach ( $terms as $term_id ) {
			$data = self::get_location_data( $term_id );
			// This should not happen.
			if ( empty( $data ) || ! array_key_exists( 'country', $data ) ) {
				return false;
			}
			if ( $addr['country-code'] === $data['country']['code'] ) {
				if ( $data['region']['code'] === self::region_return( $addr ) ) {
					return $term_id;
				}
			}
		}

		return false;
	}

	/**
	 * Return an array of term_ids for regions in this country.
	 *
	 * @param int|string $country_code Term ID or Country Code.
	 * @return array|false Associative array of Term IDs as key and name as value or false if not found.
	 */
	public static function get_country_regions( $country_code ) {
		if ( is_string( $country_code ) ) {
			$country_code = self::get_country( $country_code );
		}
		if ( ! is_numeric( $country_code ) ) {
			return false;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'location',
				'hide_empty' => 0,
				'fields'     => 'id=>name',
				'childless'  => $childless,
				'parent'     => $country_code,
			)
		);
		if ( ! empty( $terms ) ) {
			return $terms;
		}
		return false;
	}

	/**
	 * Return the term_id for the region if it exists.
	 *
	 * @param array   $addr Array of Address Properties
	 * @param boolean $childless Only look for regions that have no localities currently.
	 * @return int|false Term ID or false if not found.
	 */
	public static function get_region( $addr, $childless = false ) {
		if ( empty( $addr ) ) {
			return false;
		}

		foreach ( array( 'region-code', 'region' ) as $region ) {
			if ( ! array_key_exists( $region, $addr ) ) {
				continue;
			}

			$args  = array(
				array(
					'key'   => 'region',
					'value' => $addr[ $region ],
				),
			);
			$terms = get_terms(
				array(
					'taxonomy'   => 'location',
					'hide_empty' => 0,
					'meta_query' => $args,
					'fields'     => 'ids',
				)
			);
			foreach ( $terms as $term ) {
				$data = self::get_location_data( $term );
				if ( ! array_key_exists( 'locality', $data ) ) {
					if ( $addr['country-code'] === $data['country']['code'] ) {
						return $term;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Return the term_id for the country if it exists.
	 *
	 * @param string  $country_code Country Code.
	 * @param bookean $childless Only look for countries that have no regions currently.
	 * @return false|int Term ID or false if not found.
	 */
	public static function get_country( $country_code, $childless = false ) {
		$args  = array(
			array(
				'key'   => 'country',
				'value' => $country_code,
			),
		);
		$terms = get_terms(
			array(
				'taxonomy'   => 'location',
				'hide_empty' => 0,
				'meta_query' => $args,
				'fields'     => 'ids',
				'childless'  => $childless,
				'parent'     => 0,
			)
		);
		// If found return it.
		if ( ! empty( $terms ) ) {
			return $terms[0];
		}
		return false;
	}

	/*
	 * Returns an existing term or creates a new one based on returned address data
	 *
	 * @param array $addr Address data.
	 * @param boolean $term If Term is True Return a New Term if One Does Not Exist
	 * @return int|false Returns an existing term or creates a new one.
	 */
	public static function get_location( $addr, $term = false ) {
		$locality = self::get_locality( $addr );
		if ( is_numeric( $locality ) && 0 !== $locality ) {
			return $locality;
		}

		if ( ! $term ) {
			return false;
		}

		$region = self::get_region( $addr );
		if ( ! $region ) {
			$country = self::get_country( $addr['country-code'] );
			if ( ! $country ) {
				$return = wp_insert_term(
					$addr['country-name'],
					'location',
					array(
						'slug' => $addr['country-code'],
					)
				);
				if ( is_array( $return ) ) {
					$country = $return['term_id'];
					add_term_meta( $country, 'country', $addr['country-code'] );
				}
			}
			$region_code = self::region_return( $addr );
			if ( $region_code ) {
				$return = wp_insert_term(
					$addr['region'],
					'location',
					array(
						'slug'   => $region_code,
						'parent' => $country,
					)
				);
				if ( is_array( $return ) ) {
					$region = $return['term_id'];
					add_term_meta( $region, 'region', $region_code );
				}
			} else {
				return $country;
			}
		}

		// Cover the possibility only region and country is available.
		if ( ! array_key_exists( 'locality', $addr ) ) {
			return $region;
		}

		$return = wp_insert_term(
			$addr['locality'],
			'location',
			array(
				'slug'   => sanitize_title( $addr['locality'] ),
				'parent' => $region,
			)
		);
		if ( is_array( $return ) ) {
			$locality = $return['term_id'];
			add_term_meta( $locality, 'locality', $addr['locality'] );
			return $locality;
		}
		return false;
	}

	public static function set_location( $post_id, $term_id ) {
		if ( $post_id instanceof WP_Post ) {
			$post_id = $post->ID;
		}
		return wp_set_post_terms( $post_id, $term_id, 'location' );
	}

	public static function get_location_type( $term_id ) {
		$term = get_term( $term_id );
		if ( ! $term instanceof WP_Term ) {
			return false;
		}
		if ( 'location' !== $term->taxonomy ) {
			return false;
		}

		// If there are no parents it should be assumed to be a country.
		if ( 0 === $term->parent ) {
			return 'country';
		}

		$ancestors = get_ancestors( $term_id, 'location' );
		// The most ancestors a location should have is 2.
		if ( 3 <= count( $ancestors ) ) {
			return false;
		}
		if ( 2 === count( $ancestors ) ) {
			return 'locality';
		}
		if ( 1 === count( $ancestors ) ) {
			return 'region';
		}

		return false;
	}

	public static function location_type( $type ) {
		$types = array(
			'country'  => __( 'Country', 'simple-location' ),
			'region'   => __( 'Region', 'simple-location' ),
			'locality' => __( 'Locality', 'simple-location' ),
		);
		if ( is_string( $type ) && array_key_exists( $type, $types ) ) {
			return $types[ $type ];
		}
		return __( 'None', 'simple-location' );
	}

	public static function get_location_data( $term_id ) {
		$term = get_term( $term_id, 'location' );
		if ( ! $term instanceof WP_Term ) {
			return false;
		}

		$return              = array();
		$type                = self::get_location_type( $term->term_id );
			$return[ $type ] = array(
				'term_id' => $term->term_id,
				'name'    => $term->name,
				'code'    => get_term_meta( $term->term_id, $type, true ),
			);
			while ( 0 !== $term->parent ) {
				$term = get_term( $term->parent, 'location' );
				if ( $term instanceof WP_Term ) {
					$type = self::get_location_type( $term->term_id );
					if ( $type ) {
						$return[ $type ] = array(
							'term_id' => $term->term_id,
							'name'    => $term->name,
							'code'    => get_term_meta( $term->term_id, $type, true ),
						);
					}
				}
			}
			return $return;
	}

	public static function get_parent_country( $term_id ) {
		$term = get_term( $term_id );
		if ( ! $term instanceof WP_Term ) {
			return false;
		}
		if ( 'location' !== $term->taxonomy ) {
			return false;
		}

		// If there are no parents it should be assumed to be a country.
		if ( 0 === $term->parent ) {
			return get_term_meta( $term_id, 'country', true );
		}

		$ancestors = get_ancestors( $term_id, 'location' );
		$term      = get_term( end( $ancestors ) );
		return get_term_meta( $term->term_id, 'country', true );
	}

	public static function display_name( $term_id, $args = array() ) {
		$defaults = array(
			'links' => true,
			'flag'  => true,
		);

		$args = wp_parse_args( $args, $defaults );
		$term = get_term( $term_id );
		if ( is_wp_error( $term ) ) {
			return '';
		}
		$return = array();
		if ( 0 === $term->parent ) {
			$country = get_term_meta( $term->term_id, 'country', true );
			if ( $args['flag'] ) {
				$flag     = Geo_Provider::country_flag( $country );
				$return[] = $flag . ' ' . $term->name;
			} else {
				$return[] = $term->name;
			}
		} else {
			$return[] = $term->name;
		}
		while ( 0 !== $term->parent ) {
			$term = get_term( $term->parent, 'location' );
			if ( 0 === $term->parent ) {
				$country = get_term_meta( $term->term_id, 'country', true );
				if ( $country !== get_option( 'sloc_country' ) ) {
					$flag = Geo_Provider::country_flag( $country );
					if ( $args['links'] ) {
						$return[] = sprintf( '<a href="%1$s">%2$s</a>', get_term_link( $term->term_id, 'location' ), $flag . ' ' . $term->name );
					} else {
						$return[] = $flag . ' ' . $term->name;
					}
				}
			} elseif ( $args['links'] ) {
					$return[] = sprintf( '<a href="%1$s">%2$s</a>', get_term_link( $term->term_id, 'location' ), $term->name );
			} else {
				$return[] = $term->name;
			}
		}
		return implode( ', ', $return );
	}


	public static function get_location_link( $term_id ) {
		$term = get_term( $term_id );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		$link   = get_term_link( $term->term_id, 'location' );
		$return = array();
		if ( 0 === $term->parent ) {
			$country  = get_term_meta( $term->term_id, 'country', true );
			$flag     = Geo_Provider::country_flag( $country );
			$return[] = $flag . ' ' . $term->name;
		} else {
			$return[] = $term->name;
		}
		while ( 0 !== $term->parent ) {
			$term = get_term( $term->parent, 'location' );
			if ( 0 === $term->parent ) {
				$country = get_term_meta( $term->term_id, 'country', true );
				if ( $country !== get_option( 'sloc_country' ) ) {
					$flag     = Geo_Provider::country_flag( $country );
					$return[] = $flag . ' ' . $term->name;
				}
			} else {
				$return[] = $term->name;
			}
		}
		return sprintf( '<a href="%1$s">%2$s</a>', $link, implode( ', ', $return ) );
	}

	public static function get_post_location( $post_id = null ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$terms = wp_get_object_terms( $post->ID, 'location', array( 'fields' => 'ids' ) );
		if ( empty( $terms ) ) {
			return false;
		}
		return $terms[0];
	}

	public static function get_post_location_link( $post_id = null ) {
		$term = self::get_post_location( $post_id );
		if ( ! $term ) {
			return '';
		}
		return self::get_location_link( $terms[0] );
	}

	/**
	 * Wrapper around wp_list_categories for now that outputs a list of the locations.
	 * May be customized further in future.
	 */

	public static function list_locations() {
		$defaults = array(
			'taxonomy' => 'location',
			'title_li' => null,
			'order_by' => 'name',
		);
		$args     = wp_parse_args( $args, $defaults );
		return wp_list_categories( $args );
	}

	public static function location_data_to_hadr( $data ) {
		if ( empty( $data ) ) {
			return array();
		}
		$return = array();
		if ( array_key_exists( 'locality', $data ) ) {
			$return['locality'] = $data['locality']['name'];
		}
		if ( array_key_exists( 'region', $data ) ) {
			$return['region-code'] = $data['region']['code'];
			$return['region']      = $data['region']['name'];
		}
		if ( array_key_exists( 'country', $data ) ) {
			$return['country-code'] = $data['country']['code'];
			$return['country-name'] = $data['country']['name'];
		}
		return $return;
	}
} // End Class Location_Taxonomy



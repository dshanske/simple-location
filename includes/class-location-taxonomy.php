<?php

/**
 * Location Taxonomy Class
 *
 * Registers the taxonomy and sets its behavior.
 *
 * @package Post Kinds
 */
add_action( 'init', array( 'Location_Taxonomy', 'init' ) );

final class Location_Taxonomy {

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

		add_filter( 'get_the_archive_title', array( __CLASS__, 'archive_title' ), 10 );
	}

	/**
	 * To Be Run on Plugin Activation.
	 */
	public static function activate_location() {
		self::register();
		flush_rewrite_rules();
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
	 * Filters Location in Posts.
	 *
	 * @param WP_Query $query Query Object.
	 *
	 * @since 1.0.0
	 */
	public static function filter_location_posts( $query ) {
		if ( is_admin() || current_user_can( 'read_private_posts' ) ) {
			return $query;
		}
		if ( is_tax( 'location' ) ) {
			$public = array(
				'key'     => 'geo_public',
				'type'    => 'numeric',
				'compare' => '=',
				'value'   => 1,
			);
			$query->set( 'meta_query', array( $public ) );
		}
		return $query;
	}

	public static function pre_add_form() {
		printf( '<p>%1$s</p>', esc_html__( 'Locations allow for 3 levels of hierarchy: Country, region, and locality. You can add a location here and add detail in the edit screen.', 'simple-location' ) );
	}

	public static function country_select( $country ) {
		$file  = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/countries.json';
		$codes = json_decode( file_get_contents( $file ), true );
		echo '<select name="country" id="country">';
		foreach ( $codes as $code => $name ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $code ), selected( $country, $code, false ), esc_html( $name ) ); // phpcs:ignore
		}
		echo '</select>';
	}

	public static function create_screen_fields( $taxonomy ) {
		echo '<div class="form-field">';
		printf( '<label for="country">%1$s</label>', esc_html( 'Country:', 'simple-location' ) );
		self::country_select( get_option( 'sloc_country' ) );
		?> 
		</div>
		<?php
	}

	public static function edit_screen_fields( $term, $taxonomy ) {
		$parents = get_ancestors( $term->term_id, 'location', 'taxonomy' );
		?>
	<tr class="form-field">
		<tr>
		<th><label for="country"><?php esc_html_e( 'Country:', 'simple-location' ); ?></label></th>
		<td><?php self::country_select( get_term_meta( $term->term_id, 'country', true ) ); ?>
		<p class="description"><?php esc_html_e( 'Country.', 'simple-location' ); ?></p></td>
		</td>
		</tr>
		<?php if ( 1 <= count( $parents ) ) { ?>
			<tr>
			<th><label for="region"><?php esc_html_e( 'Region:', 'simple-location' ); ?></label></th>
			<td><input class="widefat" type=text" name="region" value="<?php echo get_term_meta( $term->term_id, 'region', true ); ?>" />
			<p class="description"><?php esc_html_e( 'The state, county, or province for the location.', 'simple-location' ); ?></p></td>
			</tr> 
		<?php } ?>
		<?php if ( 2 === count( $parents ) ) { ?>
			<th><label for="locality"><?php esc_html_e( 'Locality:', 'simple-location' ); ?></label></th>
			<td><input class="widefat" type=text" name="locality" value="<?php echo get_term_meta( $term->term_id, 'locality', true ); ?>" />
			<p class="description"><?php esc_html_e( 'The city, village, or town for the location', 'simple-location' ); ?></p>
			</td>
			</tr> 
		<?php } ?>
	</tr>
		<?php
	}

	public static function save_data( $term_id ) {
		// phpcs:disable
		foreach( array( 'country', 'region', 'locality' ) as $field ) {
			if ( ! empty( $_POST[$field] ) ) {
				update_term_meta( $term_id, $field, $_POST[$field] );
			} else {
				delete_term_meta( $term_id, $field );
			}
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
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'hierarchical'       => true,
			'show_ui'            => true,
			'show_in_menu'       => WP_DEBUG,
			'show_in_nav_menus'  => true,
			'show_in_rest'       => false,
			'show_tagcloud'      => true,
			'show_in_quick_edit' => false,
			'show_admin_column'  => true,
			'meta_box_cb'        => array( static::class, 'taxonomy_select_meta_box' ),
			'rewrite'            => array(
				'hierarchical' => true,
			),
			'query_var'          => true,
		);
		register_taxonomy( 'location', array( 'post' ), $args );

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
				'description'       => __( 'Region Code', 'simple-location' ),
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

		extract( wp_parse_args( $args, $defaults ), EXTR_SKIP );

		$tax          = get_taxonomy( $taxonomy );
		$selected     = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
		$hierarchical = $tax->hierarchical;
		?>
	<div id="taxonomy-<?php echo $taxonomy; ?>" class="selectdiv">
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
						'show_option_none' => sprintf( __( 'No %1$s', 'simple-location' ), $tax->label ),

					)
				);
			} else {
				?>
				<select name="<?php echo "tax_input[$taxonomy][]"; ?>" class="widefat">
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
	 * Return the term_id for the locality if it exists.
	 *
	 * @param array $addr Array of Address Properties
	 * @return int|false Term ID or false if not found.
	 */
	public static function get_locality( $addr ) {
		if ( empty( $addr ) ) {
			return false;
		}

		if ( array_key_exists( 'region-code', $addr ) && array( 'country-code', $addr ) && array_key_exists( 'locality', $addr ) ) {
			$args  = array(
				array(
					'key'   => 'country',
					'value' => $addr['country-code'],
				),
				array(
					'key'   => 'region',
					'value' => $addr['region-code'],
				),
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
			// If found return it.
			if ( ! empty( $terms ) ) {
				return $terms[0];
			}
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

		if ( array_key_exists( 'region-code', $addr ) && array( 'country-code', $addr ) ) {
			$args  = array(
				array(
					'key'   => 'country',
					'value' => $addr['country-code'],
				),
				array(
					'key'   => 'region',
					'value' => $addr['region-code'],
				),
				array(
					'key'     => 'locality',
					'compare' => 'NOT EXISTS',
				),
			);
			$terms = get_terms(
				array(
					'taxonomy'   => 'location',
					'hide_empty' => 0,
					'meta_query' => $args,
					'fields'     => 'ids',
					'childless'  => $childless,
				)
			);
			// If found return it.
			if ( ! empty( $terms ) ) {
				return $terms[0];
			}
		}
		return false;
	}

	/**
	 * Return the term_id for the country if it exists.
	 *
	 * @param array   $addr Array of Address Properties
	 * @param bookean $childless Only look for countries that have no regions currently.
	 */
	public static function get_country( $addr, $childless = false ) {
		if ( empty( $addr ) ) {
			return false;
		}

		if ( array_key_exists( 'country-code', $addr ) ) {
			$args  = array(
				array(
					'key'   => 'country',
					'value' => $addr['country-code'],
				),
				array(
					'key'     => 'region',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'locality',
					'compare' => 'NOT EXISTS',
				),
			);
			$terms = get_terms(
				array(
					'taxonomy'   => 'location',
					'hide_empty' => 0,
					'meta_query' => $args,
					'fields'     => 'ids',
					'childless'  => $childless,
				)
			);
			// If found return it.
			if ( ! empty( $terms ) ) {
				return $terms[0];
			}
		}
		return false;
	}

	/*
	 * Returns an existing term or creates a new one based on returned address data
	 *
	 * @param array $addr Address data.
	 * @return WP_Term|false Returns an existing term or creates a new one.
	 */
	public static function get_location( $addr ) {
		$locality = self::get_locality( $addr );
		if ( $locality ) {
			return $locality;
		}

		$region = self::get_region( $addr );
		if ( ! $region ) {
			$country = self::get_country( $addr );
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

			$return = wp_insert_term(
				$addr['region'],
				'location',
				array(
					'slug'   => $addr['region-code'],
					'parent' => $country,
				)
			);

			if ( is_array( $return ) ) {
				$region = $return['term_id'];
				add_term_meta( $region, 'country', $addr['country-code'] );
				add_term_meta( $region, 'region', $addr['region-code'] );
			}
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
			add_term_meta( $locality, 'country', $addr['country-code'] );
			add_term_meta( $locality, 'region', $addr['region-code'] );

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
		$meta = get_term_meta( $term_id );
		if ( empty( $meta ) ) {
			return false;
		}
		if ( array_key_exists( 'locality', $meta ) ) {
			return 'locality';
		}
		if ( array_key_exists( 'region', $meta ) ) {
			return 'region';
		}
		if ( array_key_exists( 'country', $meta ) ) {
			return 'country';
		}
		return false;
	}

	public static function location_type( $type ) {
		$types = array(
			'country'  => __( 'Country', 'simple-location' ),
			'region'   => __( 'Region', 'simple-location' ),
			'locality' => __( 'Locality', 'simple-location' ),
		);
		if ( array_key_exists( $type, $types ) ) {
			return $types[ $type ];
		}
		return __( 'None', 'simple-location' );
	}

	public static function display_name( $term_id ) {
		$term     = get_term( $term_id );
		$return   = array();
		$return[] = $term->name;
		while ( 0 !== $term->parent ) {
			$term = get_term( $term->parent, 'location' );
			if ( 0 === $term->parent ) {
				$country = get_term_meta( $term->term_id, 'country', true );
				if ( $country !== get_option( 'sloc_country' ) ) {
					$return[] = $term->name;
				}
			} else {
				$return[] = $term->name;
			}
		}
		return implode( ', ', $return );
	}


} // End Class Location_Taxonomy



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
	}

	/**
	 * To Be Run on Plugin Activation.
	 */
	public static function activate_location() {
		self::register();
		flush_rewrite_rules();
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
			'rewrite'            => true,
			'query_var'          => true,
		);
		register_taxonomy( 'location', array( 'post' ), $args );
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

	/*
	 * Returns an existing term or creates a new one based on returned address data
	 *
	 * @param array $addr Address data.
	 * @return WP_Term|false Returns an existing term or creates a new one.
	 */
	public static function update_location( $addr ) {
		$country = null;
		// If there is a region code try to find a term for it.
		if ( array_key_exists( 'region-code', $addr ) ) {
			$args  = array(
				array(
					'key'   => 'country',
					'value' => $addr['country-code'],
				),
				array(
					'key'   => 'region',
					'value' => $addr['region-code'],
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
			} else {
				$args  = array(
					array(
						'key'   => 'country',
						'value' => $addr['country-code'],
					),
				);
				$terms = get_terms(
					array(
						'taxonomy'   => 'location',
						'meta_query' => $args,
						'hide_empty' => 0,
						'fields'     => 'ids',
					)
				);
				if ( empty( $terms ) ) {
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
				} else {
					$country = $terms[0];
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
					add_term_meta( $return['term_id'], 'country', $addr['country-code'] );
					add_term_meta( $return['term_id'], 'region', $addr['region-code'] );
					return $return['term_id'];
				} else {
					return false;
				}
			}
		} else {
			$args  = array(
				array(
					'key'   => 'country',
					'value' => $addr['country-code'],
				),
			);
			$terms = get_terms(
				array(
					'taxonomy'   => 'location',
					'meta_query' => $args,
					'hide_empty' => 0,
					'fields'     => 'ids',
				)
			);
			if ( empty( $terms ) ) {
				$return = wp_insert_term(
					$addr['country-name'],
					'location',
					array(
						'slug' => $addr['country-code'],
					)
				);
				if ( is_array( $return ) ) {
					add_term_meta( $return['term_id'], 'country', $addr['country-code'] );
					return $return['term_id'];
				}
			} else {
				return $terms[0];
			}
		}
		return false;
	}


} // End Class Location_Taxonomy



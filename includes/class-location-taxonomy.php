<?php
/**
 * Location Taxonomy Class
 *
 * Registers the taxonomy and sets its behavior.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', array( 'Location_Taxonomy', 'init' ) );

/**
 * Class that handles the Location taxonomy functions.
 */
final class Location_Taxonomy {
	use GeoLocation_Trait;

	/**
	 * Registers the taxonomy and its hooks.
	 *
	 * @since 4.4.0
	 * @since 6.1.0 Adds the about link to location archive descriptions.
	 */
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
		add_filter( 'get_the_archive_description', array( __CLASS__, 'archive_description' ), 10 );
		add_filter( 'get_pages_query_args', array( __CLASS__, 'get_pages_query_args' ), 10, 2 );
	}

	/**
	 * Removes the default location taxonomy metaboxes, which the location metabox replaces.
	 *
	 * @since 4.4.6
	 * @since 6.1.0 Removes them from every post type with the location metabox, such as pages and attachments (#295).
	 */
	public static function admin_menu() {
		foreach ( array_unique( array_merge( Geo_Base::screens(), array( 'post', 'venue' ) ) ) as $post_type ) {
			remove_meta_box( 'locationdiv', $post_type, 'side' );
		}
	}

	/**
	 * Passes a tax query through to the query get_pages() makes, so venues can be listed by location.
	 *
	 * @since 5.0.20
	 *
	 * @param array $query_args  Query arguments for get_posts().
	 * @param array $parsed_args Arguments passed to get_pages().
	 * @return array Query arguments.
	 */
	public static function get_pages_query_args( $query_args, $parsed_args ) {
		if ( array_key_exists( 'tax_query', $parsed_args ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Passes through a query the caller made.
			$query_args['tax_query'] = $parsed_args['tax_query'];
		}
		return $query_args;
	}

	/**
	 * Limits the parent dropdown for locations to countries and regions.
	 *
	 * @since 4.4.6
	 *
	 * @param array  $dropdown_args Arguments for the parent dropdown.
	 * @param string $taxonomy      Taxonomy name.
	 * @param string $context       Screen: new or edit.
	 * @return array Dropdown arguments.
	 */
	public static function taxonomy_parent_dropdown_args( $dropdown_args, $taxonomy, $context ) {
		if ( 'location' === $taxonomy ) {
			$dropdown_args['depth'] = 2;
		}
		return $dropdown_args;
	}

	/**
	 * Sets the archive title of a location to its type and full name, such as "Locality: Philadelphia, Pennsylvania".
	 *
	 * @since 4.4.0
	 *
	 * @param string $title Archive title.
	 * @return string Archive title.
	 */
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

	/**
	 * Sanitizes a location's about URL.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value URL.
	 * @return string HTTP or HTTPS URL, or an empty string.
	 */
	public static function sanitize_about_url( $value ) {
		return is_string( $value ) ? (string) esc_url_raw( trim( $value ), array( 'http', 'https' ) ) : '';
	}

	/**
	 * Returns the link to another page about a location, such as the Wikipedia article on the region.
	 *
	 * @since 6.1.0
	 *
	 * @param int $term_id Term ID.
	 * @return string URL, or an empty string if none is set.
	 */
	public static function get_about_url( $term_id ) {
		return self::sanitize_about_url( get_term_meta( (int) $term_id, 'location_about_url', true ) );
	}

	/**
	 * Adds a link to the location's about page to the description of a location archive.
	 *
	 * The link isn't marked up as the location's URL, which is the archive itself.
	 *
	 * @since 6.1.0
	 *
	 * @param string $description Archive description.
	 * @return string Archive description.
	 */
	public static function archive_description( $description ) {
		if ( ! is_tax( 'location' ) ) {
			return $description;
		}
		$term = get_queried_object();
		if ( ! $term instanceof WP_Term ) {
			return $description;
		}
		$url = self::get_about_url( $term->term_id );
		if ( '' === $url ) {
			return $description;
		}
		return $description . sprintf(
			'<p class="sloc-location-about"><a href="%1$s">%2$s</a></p>',
			esc_url( $url ),
			/* translators: %s: Location name. */
			esc_html( sprintf( __( 'About %s', 'simple-location' ), self::display_name( $term->term_id ) ) )
		);
	}

	/**
	 * Adds the location type column to the location terms list.
	 *
	 * @since 4.4.0
	 *
	 * @param string[] $columns Column names keyed by ID.
	 * @return string[] Columns.
	 */
	public static function manage_column( $columns ) {
		$columns['location-type'] = __( 'Type', 'simple-location' );
		return $columns;
	}

	/**
	 * Returns the content of the location type column.
	 *
	 * @since 4.4.0
	 *
	 * @param string $content     Column content.
	 * @param string $column_name Column ID.
	 * @param int    $term_id     Term ID.
	 * @return string Column content.
	 */
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
		$type     = get_post_type_object( $post_type );
		$selected = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads the list filter to show it as selected.
		if ( isset( $_REQUEST['location'] ) ) {
			$selected = sanitize_text_field( wp_unslash( $_REQUEST['location'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads the list filter to show it as selected.
		}
		wp_dropdown_categories(
			array(
				'name'              => 'location',
				'id'                => 'location',
				'show_option_none'  => $type->labels->all_items,
				'option_none_value' => '',
				'hierarchical'      => true,
				'taxonomy'          => 'location',
				'value_field'       => 'slug',
				'selected'          => $selected,
			)
		);
	}

	/**
	 * Returns a meta query clause matching posts whose location is public.
	 *
	 * A post without a stored visibility, or with an empty or unknown one, uses the site default
	 * (see Geo_Data::get_geodata()), so when the default is public those posts match too.
	 *
	 * @since 6.0.0
	 *
	 * @return array Meta query clause.
	 */
	public static function public_meta_query() {
		$public = array(
			'key'     => 'geo_public',
			'type'    => 'numeric',
			'compare' => '=',
			'value'   => 1,
		);
		if ( 'public' !== Geo_Data::get_default_visibility() ) {
			return $public;
		}
		return array(
			'relation' => 'OR',
			array(
				'key'     => 'geo_public',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => 'geo_public',
				'compare' => '=',
				'value'   => '',
			),
			array(
				'key'     => 'geo_public',
				'type'    => 'numeric',
				'compare' => 'NOT IN',
				'value'   => array( 0, 2 ),
			),
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
				$query->set( 'meta_query', array( self::public_meta_query() ) );
			}
			$post_types = get_post_types_by_support( 'geo-location' );
			$search     = array_search( 'venue', $post_types, true );
			if ( false !== $search ) {
				unset( $post_types[ $search ] );
			}
			$query->set( 'post_type', array_values( $post_types ) );
		}
		return $query;
	}

	/**
	 * Explains the location hierarchy above the form for adding a location.
	 *
	 * @since 4.4.0
	 */
	public static function pre_add_form() {
		printf( '<p>%1$s</p>', esc_html__( 'Locations allow for 3 levels of hierarchy: Country, region, and locality. Countries have no parent. Regions have a country as a parent. Localities have regions are their parent.', 'simple-location' ) );
	}

	/**
	 * Prints the location code field on the form for adding a location.
	 *
	 * @since 4.4.0
	 * @since 6.1.0 Adds the about URL field.
	 *
	 * @param string $taxonomy Taxonomy name.
	 */
	public static function create_screen_fields( $taxonomy ) {
		echo '<div class="form-field form-required">';
				printf( '<label for="location-code">%1$s</label>', esc_html__( 'Location Code:', 'simple-location' ) );
		printf( '<input class="widefat" type="text" name="location-code" required />' );
		printf( '<p>%1$s</p>', esc_html__( 'Required. The code for this location. If no code, you can use the location.', 'simple-location' ) );
		echo '</div>';
		echo '<div class="form-field">';
		printf( '<label for="location-about-url">%1$s</label>', esc_html__( 'About URL:', 'simple-location' ) );
		echo '<input class="widefat" type="url" name="location_about_url" id="location-about-url" />';
		printf( '<p>%1$s</p>', esc_html__( 'Optional. A page about this location, such as its Wikipedia article.', 'simple-location' ) );
		echo '</div>';
		wp_nonce_field( 'create', 'location_taxonomy_meta' );
	}

	/**
	 * Prints the fields for the location's code, and its type's other data, on the form for editing a location.
	 *
	 * @since 4.4.0
	 * @since 6.1.0 Adds the about URL field.
	 *
	 * @param WP_Term $term     Term being edited.
	 * @param string  $taxonomy Taxonomy name.
	 */
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
		?>
		<tr class="form-field">
			<th><label for="location-about-url"><?php esc_html_e( 'About URL:', 'simple-location' ); ?></label></th>
			<td><input class="widefat" type="url" name="location_about_url" id="location-about-url" value="<?php echo esc_attr( self::get_about_url( $term->term_id ) ); ?>" />
				<p class="description"><?php esc_html_e( 'Optional. A page about this location, such as its Wikipedia article. It is linked from the location archive.', 'simple-location' ); ?></p>
			</td>
		</tr>
		<?php
		wp_nonce_field( 'edit', 'location_taxonomy_meta' );
	}

	/**
	 * Saves the country, region or locality code of a location term from the term form.
	 *
	 * @since 6.0.0 Stops for terms nested too deep to have a type.
	 * @since 6.1.0 Saves the about URL.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save_data( $term_id ) {
		// This option only exists when using one of the two forms.
		if ( ! array_key_exists( 'location_taxonomy_meta', $_POST ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['location_taxonomy_meta'] ) );
		if ( ! wp_verify_nonce( $nonce, 'edit' ) && ! wp_verify_nonce( $nonce, 'create' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		// phpcs:disable
		$term = get_term( $term_id );

		if ( ! $term instanceof WP_Term || 'location' !== $term->taxonomy ) {
			return;
		}

		if ( array_key_exists( 'location_about_url', $_POST ) ) {
			$url = self::sanitize_about_url( wp_unslash( $_POST['location_about_url'] ) );
			if ( '' === $url ) {
				delete_term_meta( $term_id, 'location_about_url' );
			} else {
				update_term_meta( $term_id, 'location_about_url', $url );
			}
		}

		// Only countries, regions and localities have a code; a deeper term has no type.
		$type = self::get_location_type( $term_id );
		if ( ! $type ) {
			return;
		}

		if ( array_key_exists( 'location-code', $_POST ) ) {
			update_term_meta( $term_id, $type, sanitize_text_field( $_POST['location-code'] ) );
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
	 *
	 * @since 4.4.0
	 * @since 6.1.0 Registers the about URL term meta.
	 */
	public static function register() {
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

		register_meta(
			'term',
			'location_about_url',
			array(
				'object_subtype'    => 'location',
				'type'              => 'string',
				'description'       => __( 'Link to a page about the location, such as its Wikipedia article', 'simple-location' ),
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_about_url' ),
				'show_in_rest'      => true,
			)
		);
	}

	/**
	 * Normalizes address data.
	 *
	 * Fills in the country name or code from the other, and the region code from the region name.
	 *
	 * @since 4.0.0
	 * @since 6.0.0 A country code fills in the country name, and region codes are no longer lost.
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
			$address['country-name'] = Geo_Provider::country_name( $address['country-code'] );
		}
		// The region is a name. Use its code when the country is known, so it matches reverse lookups.
		if ( ! array_key_exists( 'region', $address ) && array_key_exists( 'region-name', $address ) ) {
			$address['region'] = $address['region-name'];
		}
		if ( array_key_exists( 'region', $address ) && ! array_key_exists( 'region-code', $address ) ) {
			$code                   = empty( $address['country-code'] ) ? false : Geo_Provider::region_code( $address['region'], $address['country-code'] );
			$address['region-code'] = $code ? $code : $address['region'];
		}
		if ( ! array_key_exists( 'region', $address ) && ! empty( $address['region-code'] ) && ! empty( $address['country-code'] ) ) {
			$address['region'] = Geo_Provider::region_name( $address['region-code'], $address['country-code'] );
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
					<?php
					foreach ( get_terms(
						array(
							'taxonomy'   => $taxonomy,
							'hide_empty' => false,
						)
					) as $term ) :
						?>
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
	 * @since 4.0.0
	 * @since 6.0.0 Finds the region, and added the `$childless` parameter.
	 *
	 * @param string  $region_code  Region Code.
	 * @param string  $country_code Country Code.
	 * @param boolean $childless    Optional. Only return localities with no children. Default false.
	 * @return array|false Associative array of Term IDs as key and name as value or false if not found.
	 */
	public static function get_region_localities( $region_code, $country_code, $childless = false ) {
		$region = self::get_region(
			array(
				'region-code'  => $region_code,
				'country-code' => $country_code,
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
	 * @param array $addr Array of Address Properties.
	 * @return int|false Term ID or false if not found.
	 */
	public static function get_locality( $addr ) {
		if ( empty( $addr ) || ! is_array( $addr ) ) {
			return false;
		}

		if ( ! array_key_exists( 'locality', $addr ) || empty( $addr['country-code'] ) ) {
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
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Locations are matched on their codes in term meta.
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
				if ( isset( $data['region']['code'] ) && self::region_return( $addr ) === $data['region']['code'] ) {
					return $term_id;
				}
			}
		}

		return false;
	}

	/**
	 * Return an array of term_ids for regions in this country.
	 *
	 * @since 4.0.0
	 * @since 6.0.0 Added the `$childless` parameter.
	 *
	 * @param int|string $country_code Term ID or Country Code.
	 * @param boolean    $childless    Optional. Only return regions with no localities. Default false.
	 * @return array|false Associative array of Term IDs as key and name as value or false if not found.
	 */
	public static function get_country_regions( $country_code, $childless = false ) {
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
	 * @param array   $addr Array of Address Properties.
	 * @param boolean $childless Only look for regions that have no localities currently.
	 * @return int|false Term ID or false if not found.
	 */
	public static function get_region( $addr, $childless = false ) {
		if ( empty( $addr ) || empty( $addr['country-code'] ) ) {
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
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Locations are matched on their codes in term meta.
					'meta_query' => $args,
					'fields'     => 'ids',
				)
			);
			foreach ( $terms as $term ) {
				$data = self::get_location_data( $term );
				if ( ! array_key_exists( 'locality', $data ) && isset( $data['country']['code'] ) ) {
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
	 * @param boolean $childless Only look for countries that have no regions currently.
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
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Locations are matched on their codes in term meta.
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

	/**
	 * Returns an existing term or creates a new one based on returned address data.
	 *
	 * @since 4.4.0
	 *
	 * @param array   $addr Address data.
	 * @param boolean $term If Term is True Return a New Term if One Does Not Exist.
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

		// A new term needs a country, and its name if only the code is known.
		$addr = self::normalize_address( $addr );
		if ( empty( $addr['country-code'] ) || empty( $addr['country-name'] ) ) {
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
					$addr['region'] ?? $region_code,
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

	/**
	 * Sets the location term of a post.
	 *
	 * @since 6.0.0 Works when passed a WP_Post.
	 *
	 * @param int|WP_Post $post_id Post ID or post.
	 * @param int|array   $term_id Location term ID, or IDs.
	 * @return array|false|WP_Error See wp_set_post_terms().
	 */
	public static function set_location( $post_id, $term_id ) {
		if ( $post_id instanceof WP_Post ) {
			$post_id = $post_id->ID;
		}
		return wp_set_post_terms( $post_id, $term_id, 'location' );
	}

	/**
	 * Returns the type of a location term from its place in the hierarchy.
	 *
	 * @since 4.4.0
	 *
	 * @param int $term_id Term ID.
	 * @return string|false 'country', 'region' or 'locality', or false if the term is not a location or is nested too deeply.
	 */
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

	/**
	 * Returns the label for a location type.
	 *
	 * @since 4.4.0
	 *
	 * @param string|false $type Location type: 'country', 'region' or 'locality'.
	 * @return string Label, or 'None' for an unknown type.
	 */
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

	/**
	 * Returns a location term and its parents, with their names and codes.
	 *
	 * @since 4.4.2
	 *
	 * @param int $term_id Term ID.
	 * @return array|false Arrays with the term_id, name and code, keyed by location type; false if there is no term.
	 */
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

	/**
	 * Returns the country code of the country a location is in.
	 *
	 * @since 4.4.4
	 *
	 * @param int $term_id Term ID.
	 * @return string|false Country code, or false if the term is not a location.
	 */
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

	/**
	 * Returns the name of a location term with its parents, such as "Philadelphia, Pennsylvania".
	 *
	 * @since 4.4.0
	 * @since 6.0.0 Term names and links are escaped.
	 * @since 6.1.0 A parent with the same name as its child, such as the region of Berlin, is left out (#240).
	 *
	 * @param int|WP_Term $term_id Term ID or object.
	 * @param array       $args {
	 *     Optional. Arguments.
	 *
	 *     @type bool $links Link each parent to its archive. Default true.
	 *     @type bool $flag  Show the country flag. Default true.
	 * }
	 * @return string HTML.
	 */
	public static function display_name( $term_id, $args = array() ) {
		$defaults = array(
			'links' => true,
			'flag'  => true,
		);

		$args = wp_parse_args( $args, $defaults );
		$term = get_term( $term_id );
		if ( ! $term instanceof WP_Term ) {
			return '';
		}
		$return = array();
		if ( 0 === $term->parent ) {
			$country = get_term_meta( $term->term_id, 'country', true );
			if ( $args['flag'] ) {
				$flag     = Geo_Provider::country_flag( $country );
				$return[] = $flag . ' ' . esc_html( $term->name );
			} else {
				$return[] = esc_html( $term->name );
			}
		} else {
			$return[] = esc_html( $term->name );
		}
		while ( 0 !== $term->parent ) {
			$child = $term->name;
			$term  = get_term( $term->parent, 'location' );
			if ( ! $term instanceof WP_Term ) {
				break;
			}
			if ( Geo_Provider::same_name( $term->name, $child ) ) {
				continue;
			}
			if ( 0 === $term->parent ) {
				$country = get_term_meta( $term->term_id, 'country', true );
				if ( get_option( 'sloc_country' ) !== $country ) {
					$name = esc_html( $term->name );
					if ( $args['flag'] ) {
						$name = Geo_Provider::country_flag( $country ) . ' ' . $name;
					}
					if ( $args['links'] ) {
						$return[] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( get_term_link( $term->term_id, 'location' ) ), $name );
					} else {
						$return[] = $name;
					}
				}
			} elseif ( $args['links'] ) {
					$return[] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( get_term_link( $term->term_id, 'location' ) ), esc_html( $term->name ) );
			} else {
				$return[] = esc_html( $term->name );
			}
		}
		return implode( ', ', $return );
	}


	/**
	 * Returns a link to a location term's archive, named with the term and its parents.
	 *
	 * @since 4.4.14
	 * @since 6.0.0 Term names and the link are escaped.
	 * @since 6.1.0 A parent with the same name as its child is left out (#240).
	 *
	 * @param int|WP_Term $term_id Term ID or object.
	 * @return string|WP_Error HTML link, an empty string if there is no term, or an error.
	 */
	public static function get_location_link( $term_id ) {
		$term = get_term( $term_id );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		if ( ! $term instanceof WP_Term ) {
			return '';
		}
		$link   = get_term_link( $term->term_id, 'location' );
		$return = array();
		if ( 0 === $term->parent ) {
			$country  = get_term_meta( $term->term_id, 'country', true );
			$flag     = Geo_Provider::country_flag( $country );
			$return[] = $flag . ' ' . esc_html( $term->name );
		} else {
			$return[] = esc_html( $term->name );
		}
		while ( 0 !== $term->parent ) {
			$child = $term->name;
			$term  = get_term( $term->parent, 'location' );
			if ( ! $term instanceof WP_Term ) {
				break;
			}
			if ( Geo_Provider::same_name( $term->name, $child ) ) {
				continue;
			}
			if ( 0 === $term->parent ) {
				$country = get_term_meta( $term->term_id, 'country', true );
				if ( get_option( 'sloc_country' ) !== $country ) {
					$flag     = Geo_Provider::country_flag( $country );
					$return[] = $flag . ' ' . esc_html( $term->name );
				}
			} else {
				$return[] = esc_html( $term->name );
			}
		}
		return sprintf( '<a href="%1$s">%2$s</a>', esc_url( $link ), implode( ', ', $return ) );
	}

	/**
	 * Returns the ID of a post's location term.
	 *
	 * @since 5.0.0
	 *
	 * @param int|WP_Post|null $post_id Post ID or object. Defaults to the current post.
	 * @return int|false|string Term ID; false if the post has no location, or an empty string if there is no post.
	 */
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

	/**
	 * Returns a link to the location archive for a post's location.
	 *
	 * @since 6.0.0 Returns the link; it used an undefined variable and never did.
	 *
	 * @param int|WP_Post|null $post_id Optional. Post ID or post. Default the current post.
	 * @return string Link HTML, or an empty string if the post has no location.
	 */
	public static function get_post_location_link( $post_id = null ) {
		$term = self::get_post_location( $post_id );
		if ( ! $term ) {
			return '';
		}
		return self::get_location_link( $term );
	}

	/**
	 * Wrapper around wp_list_categories for now that outputs a list of the locations.
	 * May be customized further in future.
	 *
	 * @since 6.0.0 Accepts arguments for wp_list_categories(), which it already tried to use.
	 *
	 * @param array $args Optional. Arguments for wp_list_categories(). Default lists every location by name.
	 * @return void|string|false See wp_list_categories().
	 */
	public static function list_locations( $args = array() ) {
		$defaults = array(
			'taxonomy' => 'location',
			'title_li' => null,
			'order_by' => 'name',
		);
		$args     = wp_parse_args( $args, $defaults );
		return wp_list_categories( $args );
	}

	/**
	 * Converts location data to microformats2 address properties.
	 *
	 * @since 4.4.3
	 *
	 * @param array $data Location data, as returned by get_location_data().
	 * @return array Locality, region, region code, country name and country code, where set.
	 */
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



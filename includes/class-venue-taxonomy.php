<?php
/**
 * Venue Taxonomy Class
 *
 * Registers the taxonomy and sets its behavior.
 *
 * @package Simple Location
 */

add_action( 'init', array( 'Venue_Taxonomy', 'init' ) );
// Register Vanue Taxonomy.
add_action( 'init', array( 'Venue_Taxonomy', 'register' ), 1 );

class Venue_Taxonomy {
	use Geolocation_Trait;
	public static function init() {
		// Add the Correct Archive Title to Venue Archives.
		add_filter( 'get_the_archive_title', array( 'Venue_Taxonomy', 'archive_title' ), 10, 3 );
		// Remove Meta Box
		add_action( 'admin_menu', array( 'Venue_Taxonomy', 'remove_meta_box' ) );

		add_action( 'admin_print_scripts-term.php', array( 'Venue_Taxonomy', 'enqueue_term_scripts' ) );
		add_action( 'admin_print_scripts-edit-tags.php', array( 'Venue_Taxonomy', 'enqueue_term_scripts' ) );

		if ( is_admin() ) {
			add_action( 'venue_add_form_fields', array( 'Venue_Taxonomy', 'create_screen_fields' ), 10, 1 );
			add_action( 'venue_edit_form_fields', array( 'Venue_Taxonomy', 'edit_screen_fields' ), 10, 2 );

			add_action( 'created_venue', array( 'Venue_Taxonomy', 'save_data' ), 10, 1 );
			add_action( 'edited_venue', array( 'Venue_Taxonomy', 'save_data' ), 10, 1 );
		}
	}

	public static function enqueue_term_scripts() {
		if ( 'venue' === sanitize_text_field( $_GET['taxonomy'] ) ) {
			wp_enqueue_script(
				'location',
				plugins_url( 'simple-location/js/location.js' ),
				array( 'jquery' ),
				Simple_Location_Plugin::$version,
				true
			);
		}
	}

	/**
	 * Register the custom taxonomy for venues.
	 */
	public static function register() {
		$labels = array(
			'name'                       => __( 'Venues', 'simple-location' ),
			'singular_name'              => __( 'Venue', 'simple-location' ),
			'search_items'               => __( 'Search Venues', 'simple-location' ),
			'popular_items'              => __( 'Popular Venues', 'simple-location' ),
			'all_items'                  => __( 'All Venues', 'simple-location' ),
			'parent_item'                => __( 'Parent Vanue', 'simple-location' ),
			'parent_item_colon'          => __( 'Parent Venue:', 'simple-location' ),
			'edit_item'                  => __( 'Edit Venue', 'simple-location' ),
			'update_item'                => __( 'Update Venue', 'simple-location' ),
			'add_new_item'               => __( 'Add New Venue', 'simple-location' ),
			'new_item_name'              => __( 'New Venue', 'simple-location' ),
			'separate_items_with_commas' => __( 'Separate venues with commas', 'simple-location' ),
			'add_or_remove_items'        => __( 'Add or remove venues', 'simple-location' ),
			'choose_from_most_used'      => __( 'Choose from the most used venues', 'simple-location' ),
			'menu_name'                  => __( 'Venues', 'simple-location' ),
			'name_field_description'     => __( 'The name of the venue or place', 'simple-location' ),
			'desc_field_description'     => __( 'Will display on venue archive pages if set', 'simple-location' ),
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Reflects a location', 'simple-location' ),
			'public'             => true,
			'show_in_nav_menus'  => false,
			'show_ui'            => WP_DEBUG,
			'show_in_menu'       => WP_DEBUG,
			'show_tagcloud'      => true,
			'show_admin_column'  => false,
			'hierarchical'       => false,
			'rewrite'            => true,
			'query_var'          => true,
			'show_in_quick_edit' => false,
		);
		register_taxonomy( 'venue', array( 'post' ), $args );
	}

	public static function archive_title( $title ) {
		return $title;
	}

	public static function remove_meta_box() {
		remove_meta_box( 'tagsdiv-venue', 'post', 'normal' );
	}

	public static function create_screen_fields( $taxonomy ) {
		wp_nonce_field( 'create', 'venue_taxonomy_meta' );
		?>
	<div class="form-field">
		<label for="latitude"><?php esc_html_e( 'Latitude:', 'simple-location' ); ?></label>
		<input type="text" name="latitude" id="latitude" value="" size="10" />                                                                           
		<label for="longitude"><?php esc_html_e( 'Longitude:', 'simple-location' ); ?></label>
		<input type="text" name="longitude" id="longitude" value="" size="10" />  

		<button type="button" class="button lookup-address-button"><?php esc_html_e( 'Get Location', 'simple-location' ); ?></button>
	</div>
		<?php
	}

	public static function edit_screen_fields( $term, $taxonomy ) {
		wp_nonce_field( 'edit', 'venue_taxonomy_meta' );
		load_template( plugin_dir_path( __DIR__ ) . 'templates/venue-edit-fields.php' );
	}

	public static function save_data( $term_id ) {
		// This option only exists when using one of the two forms.
		if ( ! array_key_exists( 'venue_taxonomy_meta', $_POST ) ) {
			return;
		}
		$nonce = sanitize_text_field( $_POST['venue_taxonomy_meta'] );
		if ( ! wp_verify_nonce( $nonce, 'edit' ) && ! wp_verify_nonce( $nonce, 'create' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		// phpcs:disable
		$term = get_term( $term_id );

		if ( 'venue' !== $term->taxonomy ) {
			return;
		}
		Loc_Metabox::save_meta( 'term', $term->term_id );
	}
}

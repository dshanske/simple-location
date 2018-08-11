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
		if ( 'venue' === $_GET['taxonomy'] ) {
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
		?>
	<tr class="form-field">
		<tr>
		<th><label for="latitude"><?php esc_html_e( 'Latitude:', 'simple-location' ); ?></label></th>
		<td><input type="text" name="latitude" id="latitude" value="" size="10" /></td></tr>   
		<tr>									
		<th><label for="longitude"><?php esc_html_e( 'Longitude:', 'simple-location' ); ?></label></th>
		<td><input type="text" name="longitude" id="longitude" value="" size="10" />  </td>
		</tr>

		<tr><td><button type="button" class="button lookup-address-button"><?php esc_html_e( 'Get Location', 'simple-location' ); ?></button></td></tr>

		<tr><th><label for="street-address"><?php esc_html_e( 'Address', 'simple-location' ); ?></label></th>
		<td><input type="text" name="street-address" id="street-address" value="" size="50" /></td></tr>

		<tr><th><label for="locality"><?php esc_html_e( 'City/Town/Village', 'simple-location' ); ?></label></th>
		<td><input type="text" name="locality" id="locality" value="<?php echo esc_attr( ifset( $address['locality'], '' ) ); ?>" size="30" /></td></tr>    

		<tr><th><label for="region"><?php esc_html_e( 'State/County/Province', 'simple-location' ); ?></label></th>
		<td><input type="text" name="region" id="region" value="" size="30" /> </td></tr>

		<tr><th><label for="country-code"><?php esc_html_e( 'Country Code', 'simple-location' ); ?></label></th>
		<td><input type="text" name="country-code" id="country-code" value="" size="2" /></td></tr>                                             

		<tr><th><label for="extended-address"><?php esc_html_e( 'Neighborhood/Suburb', 'simple-location' ); ?></label></th>
		<td><input type="text" name="extended-address" id="extended-address" value="" size="30" /></td></tr>                                                                                              
		<tr><th><label for="postal-code"><?php esc_html_e( 'Postal Code', 'simple-location' ); ?></label></th>                                   
		<td><input type="text" name="postal-code" id="postal-code" value="" size="10" /></td></tr>                                              

		<tr><th><label for="country-name"><?php esc_html_e( 'Country Name', 'simple-location' ); ?></label></th>
		<td><input type="text" name="country-name" id="country-name" value="" size="30" /></td></tr>
	</tr>
		<?php
	}

	public static function save_data( $term_id ) {
		// phpcs:disable
		if ( ! empty( $_POST['latitude'] ) ) {
			update_term_meta( $term_id, 'geo_latitude', $_POST['latitude'] );
		} else {
			delete_term_meta( $term_id, 'geo_latitude' );
		}
		if ( ! empty( $_POST['longitude'] ) ) {
			update_post_meta( $post_id, 'geo_longitude', $_POST['longitude'] );
		} else {
			delete_post_meta( $post_id, 'geo_longitude' );
		}
		if ( ! empty( $_POST['address'] ) ) {
			update_post_meta( $post_id, 'geo_address', sanitize_text_field( $_POST['address'] ) );
		} else {
			delete_post_meta( $post_id, 'geo_address' );
		}
		// phpcs:enable
	}
}

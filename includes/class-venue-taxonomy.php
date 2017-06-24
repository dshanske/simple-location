<?php
/**
 * Venue Taxonomy Class
 *
 * Registers the taxonomy and sets its behavior.
 *
 * @package Simple Location
 */

add_action( 'init' , array( 'Venue_Taxonomy', 'init' ) );
// Register Vanue Taxonomy.
add_action( 'init', array( 'Venue_Taxonomy', 'register' ), 1 );

class Venue_Taxonomy {
	public static function init() {
		// Add the Correct Archive Title to Venue Archives.
		add_filter( 'get_the_archive_title', array( 'Venue_Taxonomy', 'archive_title' ) , 10 , 3 );
		// Remove Meta Box
		add_action( 'admin_menu', array( 'Venue_Taxonomy', 'remove_meta_box' ) );

		add_action( 'admin_print_scripts-term.php', array( 'Venue_Taxonomy', 'enqueue_term_scripts' ) );
		add_action( 'admin_print_scripts-edit-tags.php', array( 'Venue_Taxonomy', 'enqueue_term_scripts' ) );

		if ( is_admin() ) {
			add_action( 'venue_add_form_fields',  array( 'Venue_Taxonomy', 'create_screen_fields' ), 10, 1 );
			add_action( 'venue_edit_form_fields', array( 'Venue_Taxonomy', 'edit_screen_fields' ),  10, 2 );

			add_action( 'created_venue', array( 'Venue_Taxonomy', 'save_data' ), 10, 1 );
			add_action( 'edited_venue',  array( 'Venue_Taxonomy', 'save_data' ), 10, 1 );
		}
	}

	public static function enqueue_term_scripts() {
		if ( 'venue' === $_GET['taxonomy'] ) {
			wp_enqueue_script(
				'location',
				plugins_url( 'simple-location/js/location.js' ),
				array( 'jquery' ),
				Simple_Location_Plugin::$version
			);
		}
	}

	/**
	 * Register the custom taxonomy for venues.
	 */
	public static function register() {
		$labels = array(
			'name' => _x( 'Venues', 'Simple Location' ),
			'singular_name' => _x( 'Venue', 'Simple Location' ),
			'search_items' => _x( 'Search Venues', 'Simple Location' ),
			'popular_items' => _x( 'Popular Venues', 'Simple Location' ),
			'all_items' => _x( 'All Venues', 'Simple Location' ),
			'parent_item' => _x( 'Parent Vanue', 'Simple Location' ),
			'parent_item_colon' => _x( 'Parent Venue:', 'Simple Location' ),
			'edit_item' => _x( 'Edit Venue', 'Simple Location' ),
			'update_item' => _x( 'Update Venue', 'Simple Location' ),
			'add_new_item' => _x( 'Add New Venue', 'Simple Location' ),
			'new_item_name' => _x( 'New Venue', 'Simple Location' ),
			'separate_items_with_commas' => _x( 'Separate venues with commas', 'Simple Location' ),
			'add_or_remove_items' => _x( 'Add or remove venues', 'Simple Location' ),
			'choose_from_most_used' => _x( 'Choose from the most used venues', 'Simple Location' ),
			'menu_name' => _x( 'Venues', 'Simple Location' ),
		);

		$args = array(
			'labels' => $labels,
			'public' => true,
			'show_in_nav_menus' => true,
			'show_ui' => true,
			'show_in_menu' => WP_DEBUG,
			'show_tagcloud' => true,
			'show_admin_column' => true,
			'hierarchical' => false,
			'rewrite' => true,
			'query_var' => true,
		);
		register_taxonomy( 'venue', array( 'post' ), $args );
	}

	public static function archive_title($title) {
		return $title;
	}

	public static function remove_meta_box() {
		remove_meta_box( 'tagsdiv-venue', 'post', 'normal' );
	}

	public static function create_screen_fields( $taxonomy ) {
?>
	<div class="form-field">
		<label for="latitude"><?php _e( 'Latitude:', 'simple-location' ); ?></label>
		<input type="text" name="latitude" id="latitude" value="" size="6" />                                                                           
		<label for="longitude"><?php _e( 'Longitude:', 'simple-location' ); ?></label>
		<input type="text" name="longitude" id="longitude" value="" size="6" />  

		<button type="button" class="button" onclick="getLocation();return false;"><?php _e( 'Get Location', 'Simple Location' ); ?></button>
	</div>
<?php
	}

	public static function edit_screen_fields( $term, $taxonomy ) {
?>
	<tr class="form-field">
		<tr>
		<th><label for="latitude"><?php _e( 'Latitude:', 'simple-location' ); ?></label></th>
		<td><input type="text" name="latitude" id="latitude" value="" size="6" /></td></tr>   
		<tr>									
		<th><label for="longitude"><?php _e( 'Longitude:', 'simple-location' ); ?></label></th>
		<td><input type="text" name="longitude" id="longitude" value="" size="6" />  </td>
		</tr>

		<tr><td><button type="button" class="button" onclick="getLocation();return false;"><?php _e( 'Get Location', 'Simple Location' ); ?></button></td></tr>

		<tr><th><label for="street-address"><?php _e( 'Address', 'simple-location' ); ?></label></th>
		<td><input type="text" name="street-address" id="street-address" value="" size="50" /></td></tr>

		<tr><th><label for="locality"><?php _e( 'City/Town/Village', 'simple-location' ); ?></label></th>
		<td><input type="text" name="locality" id="locality" value="<?php echo ifset( $address['locality'], '' ); ?>" size="30" /></td></tr>    

		<tr><th><label for="region"><?php _e( 'State/County/Province', 'simple-location' ); ?></label></th>
		<td><input type="text" name="region" id="region" value="" size="30" /> </td></tr>

		<tr><th><label for="country-code"><?php _e( 'Country Code', 'simple-location' ); ?></label></th>
		<td><input type="text" name="country-code" id="country-code" value="" size="2" /></td></tr>                                             

		<tr><th><label for="extended-address"><?php _e( 'Neighborhood/Suburb', 'simple-location' ); ?></label></th>
		<td><input type="text" name="extended-address" id="extended-address" value="" size="30" /></td></tr>                                                                                              
		<tr><th><label for="postal-code"><?php _e( 'Postal Code', 'simple-location' ); ?></label></th>                                   
		<td><input type="text" name="postal-code" id="postal-code" value="" size="10" /></td></tr>                                              

		<tr><th><label for="country-name"><?php _e( 'Country Name', 'simple-location' ); ?></label></th>
		<td><input type="text" name="country-name" id="country-name" value="" size="30" /></td></tr>
	</tr>
<?php
	}

	public static function save_data( $term_id ) {
	}
}

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

		// Add Form Fields
		// add_filter( 'venue_add_form_fields', array( 'Venue_Taxonomy', 'add_form_fields' ) , 10 , 3 );
		// add_filter( 'venue_edit_form_fields', array( 'Venue_Taxonomy', 'edit_form_fields' ) , 10 , 3 );
		add_action( 'admin_enqueue_scripts', array( 'Venue_Taxonomy', 'enqueue_admin_scripts' ) );

		// add_action( 'created_term', array( 'Venue_Taxonomy', 'save_venue_meta' ), 10, 2 );
		// add_action( 'edited_term', array( 'Venue_Taxonomy', 'save_venue_meta' ), 10, 2 );
		register_meta( 'term', 'latitude', array( 'Venue_Taxonomy', 'clean_coordinate' ) );
		register_meta( 'term', 'longitude', array( 'Venue_Taxonomy', 'clean_coordinate' ) );

	}

	public static function enqueue_admin_scripts() {
		if ( 'venue' === get_current_screen()->taxonomy ) {
			wp_enqueue_script(
				'venue-get',
				plugins_url( 'simple-location/includes/retrieve.js' ),
				array( 'jquery' ),
				SIMPLE_LOCATION_VERSION
			);
		}
	}

	public static function clean_coordinate($coordinate) {
		$pattern = '/^(\-)?(\d{1,3})\.(\d{1,15})/';
		preg_match( $pattern, $coordinate, $matches );
		return $matches[0];
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
			'show_ui' => WP_DEBUG,
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

	public static function get_venue_meta($term_id, $key) {
		$value = get_term_meta( $term_id, $key, true );
		if ( ! $value ) {
			return false;
		}
		return sanitize_text_field( $value );
	}

	public static function add_form_fields() {

		wp_nonce_field( basename( __FILE__ ), 'venue_taxonomy_nonce' ); ?>

	<div class="form-field venue-form">
		<label for="uid"><?php _e( 'URL', 'Simple Location' ); ?></label>
		<input type="uid" name="uid" id="geo_lat" value="" />
		<p>Official URL of the Venue</p>
	</div>
	<div class="form-field venue-form">
		<button type="button" class="venue-coordinates-button button-primary" onclick="getLocation();return false;">Locate</button>
		<label for="latitude"><?php _e( 'Latitude', 'Simple Location' ); ?></label>
		<input type="number" name="latitude" id="latitude" value="" />
			  <p>The latitude of the venue</p>
		</div>
	<div class="form-field venue-form">
		<label for="longitude"><?php _e( 'Longitude', 'Simple Location' ); ?></label>
		<input type="number" name="longitude" id="longitude" value="" />
				<p>The longitude of the venue</p>
	</div>

	<div class="form-field venue-form">
		<button type="button" class="venue-address-button button-primary">Lookup</button>

		<label for="street-address"><?php _e( 'Street Address', 'Simple Location' ); ?></label>
		<input type="text" name="street-address" id="street-address" value="" />
		<p>The street address of the venue</p>
	</div>

	<div class="form-field venue-form">
		<label for="extended-address"><?php _e( 'Neighborhood/Suburb', 'Simple Location' ); ?></label>
		<input type="text" name="extended-address" id="extended-address" value="" />
		<p>The extended address of the venue</p>
	</div>

	<div class="form-field venue-form">
		<label for="locality"><?php _e( 'City/Town/Village', 'Simple Location' ); ?></label>
		<input type="text" name="locality" id="locality" value="" />
		<p>The locality of the venue</p>
	</div>

	<div class="form-field venue-form">
		<label for="region"><?php _e( 'State/County/Province', 'Simple Location' ); ?></label>
		<input type="text" name="region" id="region" value="" />
		<p>The region of the venue</p>
	</div>

	<div class="form-field venue-form">
		<label for="postal-code"><?php _e( 'Postal Code', 'Simple Location' ); ?></label>
		<input type="postal-code" name="postal-code" id="postal-code" value="" />
		<p>The postal code of the venue</p>
	</div>


	<div class="form-field venue-form">
		<label for="country-name"><?php _e( 'Country', 'Simple Location' ); ?></label>
		<input type="country-name" name="country-name" id="country-name" value="" />
		<p>The country name of the venue</p>
	</div>

	<div class="form-field venue-form">
		<label for="country-code"><?php _e( 'Country Code', 'Simple Location' ); ?></label>
		<input type="country-code" name="country-code" id="country-code" value="" />
		<p>The country of the venue</p>
	</div>



		<?php
	}
	public static function edit_form_fields( $term ) {
		wp_nonce_field( basename( __FILE__ ), 'venue_taxonomy_nonce' );
		echo serialize( get_term_meta( $term->id ) );
		?>
	<tr class="form-field venue-form">
		<th scope="row"><label for="uid"><?php _e( 'URL', 'Simple Location' ); ?></label></th>
	   	<td><input type="uid" name="uid" id="uid" value="<?php self::get_venue_meta( $term->id, 'uid' );?>" />
		<p>Official URL of the Venue</p></td>
	</tr>
	<tr class="form-field venue-form">
		<th scope="row"><label for="latitude"><?php _e( 'Latitude', 'Simple Location' ); ?></label></th>
		<td><input type="number" name="latitude" id="latitude" value="<?php self::get_venue_meta( $term->id, 'latitude' );?>"  />
			  <p>The latitude of the venue</p></td>
		</tr>
	<tr class="form-field venue-form">
		<th scope="row"><label for="longitude"><?php _e( 'Longitude', 'Simple Location' ); ?></label></th>
		<td><input type="number" name="longitude" id="longitude" value="<?php self::get_venue_meta( $term->id, 'longitude' );?>"  />
				<p>The longitude of the venue</p></td>
	</tr>

	<tr class="form-field venue-form">
		<th scope="row"><label for="street-address"><?php _e( 'Street Address', 'Simple Location' ); ?></label></th>
		<td><input type="text" name="street-address" id="street-address" value="<?php self::get_venue_meta( $term->id, 'street-address' );?>"  />
		<p>The street address of the venue</p></td>
	</tr>

	<tr class="form-field venue-form">
		<th scope="row"><label for="extended-address"><?php _e( 'Neighborhood/Suburb', 'Simple Location' ); ?></label></th>
		<td><input type="text" name="extended-address" id="extended-address" value="<?php self::get_venue_meta( $term->id, 'extended-address' );?>"  />
		<p>The extended address of the venue</p></td>
	</tr>

	<tr class="form-field venue-form">
		<th scope="row"><label for="locality"><?php _e( 'City/Town/Village', 'Simple Location' ); ?></label></th>
		<td><input type="text" name="locality" id="locality" value="<?php self::get_venue_meta( $term->id, 'locality' );?>"  />
		<p>The locality of the venue</p></td>
	</tr>

	<tr class="form-field venue-form">
		<th scope="row"><label for="region"><?php _e( 'State/County/Province', 'Simple Location' ); ?></label></th>
		<td><input type="text" name="region" id="region" value="<?php self::get_venue_meta( $term->id, 'region' );?>"  />
		<p>The region of the venue</p></td>
	</tr>

	<tr class="form-field venue-form">
		<th scope="row"><label for="postal-code"><?php _e( 'Postal Code', 'Simple Location' ); ?></label></th>
		<td><input type="postal-code" name="postal-code" id="postal-code" value="<?php self::get_venue_meta( $term->id, 'postal-code' );?>"  />
		<p>The postal code of the venue</p></td>
	</tr>

	<tr class="form-field venue-form">
		<th scope="row"><label for="country-name"><?php _e( 'Country', 'Simple Location' ); ?></label></th>
		<td><input type="country-name" name="country-name" id="country-name" value="<?php self::get_venue_meta( $term->id, 'country-name' );?>"  />
		<p>The country name of the venue</p></td>
	</tr>

	<tr class="form-field venue-form">
		<th scope="row"><label for="country-code"><?php _e( 'Country Code', 'Simple Location' ); ?></label></th>
		<td><input type="country-code" name="country-code" id="country-code" value="<?php self::get_venue_meta( $term->id, 'country-code' );?>"  />
		<p>The country of the venue</p></td>
	</tr>



		<?php
	}

	public static function set_meta($term_id = 0, $key = '') {
		// No meta_key, so delete
		if ( empty( $_POST[$key] ) ) {
			delete_term_meta( $term_id, $key );
			// Update meta_key value
		} else {
			update_term_meta( $term_id, $key, $_POST[$key] );
		}
	}

	public static function save_venue_meta( $term_id, $tt_id ) {
		if ( ! isset( $_POST['venue_taxonomy_nonce'] ) || ! wp_verify_nonce( $_POST['venue_taxonomy_nonce'], basename( __FILE__ ) ) ) {
			return;
		}
			error_log( 'Trip Save' );
			self::set_meta( $term_id, 'latitude' );
		self::set_meta( $term_id, 'longitude' );
		self::set_meta( $term_id, 'uid' );
		self::set_meta( $term_id, 'street-address' );
		self::set_meta( $term_id, 'extended-address' );
		self::set_meta( $term_id, 'locality' );
		self::set_meta( $term_id, 'region' );
		self::set_meta( $term_id, 'postal-code' );
		self::set_meta( $term_id, 'country-name' );
			self::set_meta( $term_id, 'country-code' );
	}

}

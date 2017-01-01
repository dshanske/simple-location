<?php
// Adds Post Meta Box for Location
add_action( 'init' , array( 'loc_metabox', 'init' ) );

class loc_metabox {
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( 'loc_metabox', 'enqueue' ) );
		// Add meta box to new post/post pages only
		add_action( 'load-post.php', array( 'loc_metabox', 'slocbox_setup' ) );
		add_action( 'load-post-new.php', array( 'loc_metabox', 'slocbox_setup' ) );
		add_action( 'save_post', array( 'loc_metabox', 'locationbox_save_post_meta' ) );
	}

	public static function enqueue() {
		if ( 'post' === get_current_screen()->id ) {
			wp_enqueue_script(
				'venue-get',
				plugins_url( 'simple-location/js/location.js' ),
				array( 'jquery' ),
				SIMPLE_LOCATION_VERSION
			);
		}
	}

	/* Meta box setup function. */
	public static function slocbox_setup() {
		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', array( 'loc_metabox', 'locbox_add_postmeta_boxes' ) );
	}

	/* Create location meta boxes to be displayed on the post editor screen. */
	public static function locbox_add_postmeta_boxes() {
		$screens = array( 'post' );
		$screens = apply_filters( 'sloc_post_types', $screens );
		foreach ( $screens as $screen ) {
			add_meta_box(
				'locationbox-meta',      // Unique ID
				esc_html__( 'Location', 'simple-location' ),    // Title
				array( 'loc_metabox', 'location_metabox' ),   // Callback function
				$screen,         // Admin page (or post type)
				'side',         // Context
				'default'         // Priority
			);
		}
	}

	public static function location_metabox( $object, $box ) {
		wp_nonce_field( 'location_metabox', 'location_metabox_nonce' );
		add_thickbox();
		$geodata = WP_Geo_Data::get_geodata( $object->ID );
		if ( is_null ($geodata) ) {
			$geodata = array( 'public' => 1 );
		}
		if ( 2 < $geodata['public'] ) {
			$geodata['public'] = 2;
		}
	?>
		<label for="geo_public"><?php _e( 'Display:', 'simple-location' ); ?></label>
		<select name="geo_public">
		<option value=0 <?php selected( $geodata['public'], 0 ); ?>><?php _e( 'Private', 'simple-location' ); ?></option>
		<option value=1 <?php selected( $geodata['public'], 1 ); ?>><?php _e( 'Public', 'simple-location' ); ?></option>
		<option value=2 <?php selected( $geodata['public'], 2 ); ?>><?php _e( 'Show Text Only - No Coordinates', 'simple-location' ); ?></option>
		</select><br /><br />
		<a href="#TB_inline?width=600&height=550&inlineId=location-popup" class="thickbox"><button class="button-primary"><?php _e( 'Location', 'simple-location' ); ?></button></a> 
			<a href="#TB_inline?width=600&height=550&inlineId=venue-popup" class="thickbox"><button class="button-primary" disabled><?php _e( 'Venue', 'simple-location' ); ?></button></a>

		<div id="venue-popup" style="display:none">
			<h2>Existing Venues</h2>
			<button type="button" class="button-primary" disabled><?php _e( 'Set as Venue', 'simple-location' ); ?></button>

		</div>

		<div id="location-popup" style="display:none">
			<h2>Location</h2>
			<label for="address"><?php _e( 'Location Description: ', 'simple-location' ); ?></label>
			<input type="text" name="address" id="address" value="<?php echo ifset( $geodata['address'], '' ); ?> " size="60" />
			<p class="latlong">
	  			<label for="latitude"><?php _e( 'Latitude:', 'simple-location' ); ?></label>
				<input type="text" name="latitude" id="latitude" value="<?php echo ifset( $geodata['latitude'], '' ); ?>" size="6" />
	  			<label for="longitude"><?php _e( 'Longitude:', 'simple-location' ); ?></label>
				<input type="text" name="longitude" id="longitude" value="<?php echo ifset( $geodata['longitude'], '' ); ?>" size="6" />
				<button type="button" class="button" onclick="getLocation();return false;"><?php _e( 'Get Location', 'simple-location' ); ?></button> 
			<br /><br />

		<h3>Location Data</h3>
		<p> <?php _e( "Location Data below can be used to complete the location description, which will be displayed, or saved as a venue.", "simple-location" ); ?></p>
			<button type="button" class="lookup-address-button button-secondary"><?php _e( 'Lookup from Coordinates', 'simple-location' ); ?></button>
			<br /><br />
			<label for="name"><?php _e( 'Location Name', 'simple-location' ); ?></label>
			<input type="text" name="location-name" id="location-name" value="" size="50" />
			<br /></br />
		
			<label for="street-address"><?php _e( 'Address', 'simple-location' ); ?></label>
			<input type="text" name="street-address" id="street-address" value="" size="50" />
		
			<br /><br />
			<label for="extended-address"><?php _e( 'Extended Address', 'simple-location' ); ?></label>
                          <input type="text" name="extended-address" id="extended-address" value="" size="50" />  
                          <br /><br />

		<label for="locality"><?php _e( 'City/Town/Village', 'simple-location' ); ?></label>
		<input type="text" name="locality" id="locality" value="<?php echo ifset( $address['locality'], '' ); ?>" size="30" />
			<br /><br />
		<label for="region"><?php _e( 'State/County/Province', 'simple-location' ); ?></label>
		<input type="text" name="region" id="region" value="" size="30" />
		<label for="country-code"><?php _e( 'Country', 'simple-location' ); ?></label>
		<input type="text" name="country-code" id="country-code" value="" size="2" />
		<br /><br />
	   	<label for="extended-address"><?php _e( 'Neighborhood/Suburb', 'simple-location' ); ?></label>
			<input type="text" name="extended-address" id="extended-address" value="" size="30" />
			<br />
	  <label for="postal-code"><?php _e( 'Postal Code', 'simple-location' ); ?></label>
	  <input type="text" name="postal-code" id="postal-code" value="" size="10" />
			<br />
			<label for="country-name"><?php _e( 'Country Name', 'simple-location' ); ?></label>
			<input type="text" name="country-name" id="country-name" value="" size="30" />
			</p>
	 	<br />
		<br />
		<p> <?php _e( 'Venue functionality is not yet available. To save your location in the post you may just close this popup.', 'simple-location'); ?></p>
		<div class="button-group">
		<button type="button" class="save-venue-button button-secondary" disabled><?php _e( 'Save as Venue', 'simple-location' ); ?> </button>
		<button type="button" class="clear-location-button button-primary" onclick="clearLocation();return false;"><?php _e( 'Clear', 'simple-location' ); ?></button> 
		</div>>
	</div>
	<?php
	}

	/* Save the meta box's post metadata. */
	public static function locationbox_save_post_meta( $post_id ) {
		/*
		 * We need to verify this came from our screen and with proper authorization,
		 * because the save_post action can be triggered at other times.
		 */
		if ( ! isset( $_POST['location_metabox_nonce'] ) ) {
			return;
		}
		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['location_metabox_nonce'], 'location_metabox' ) ) {
			return;
		}
		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		// Check the user's permissions.
		if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}
		if ( ! empty( wp_get_post_terms( $post_id, 'venue', array( 'fields' => 'ids' ) ) ) ) {
			return;
		}
		/* OK, its safe for us to save the data now. */
		if ( ! empty( $_POST['latitude'] ) ) {
			update_post_meta( $post_id, 'geo_latitude', $_POST['latitude'] );
		} else {
			delete_post_meta( $post_id, 'geo_latitude' );
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

		if ( empty( $_POST['latitude'] ) || empty( $_POST['address'] ) ) {
			if ( isset( $_POST['geo_public'] ) ) {
				update_post_meta( $post_id, 'geo_public', $_POST['geo_public'] );
			}
			else {
				delete_post_meta( $post_id, 'geo_public' );
			}
		}
	}
}
?>

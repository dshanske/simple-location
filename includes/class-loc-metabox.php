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
		?>
		<p class="latlong">
	  <label for="latitude"><?php _e( 'Lat:', 'simple-location' ); ?></label>
	  <input type="text" name="latitude" id="latitude" value="" size="6" />
	  <label for="longitude"><?php _e( 'Lon:', 'simple-location' ); ?></label>
	  <input type="text" name="longitude" id="longitude" value="" size="6" />
	<button type="button" class="button" onclick="getLocation();return false;"><?php _e( '^', 'Simple Location' ); ?></button>
		</p>  
		<a href="#TB_inline?width=600&height=550&inlineId=address-popup" class="thickbox"><button class="button-primary"><?php _e( 'Address', 'Simple Location' ); ?></button></a> 
			<a href="#TB_inline?width=600&height=550&inlineId=venue-popup" class="thickbox"><button class="button-primary"><?php _e( 'Venues', 'Simple Location' ); ?></button></a>

		<div id="venue-popup" style="display:none">
			<h2>Existing Venues</h2>
			<button type="button" class="button-primary"><?php _e( 'Set as Venue', 'Simple Location' ); ?></button>

		</div>

		<div id="address-popup" style="display:none">
			<h2>Address</h2>
			<p>
		  <button type="button" class="lookup-address-button button-primary">Reverse Lookup</button>
			<br /><br />

			<label for="name"><?php _e( 'Location Name', 'simple-location' ); ?></label>
			<input type="text" name="location-name" id="name" value="" size="50" />
			<br /></br />
		
			<label for="street-address"><?php _e( 'Address', 'simple-location' ); ?></label>
			<input type="text" name="street-address" id="street-address" value="" size="50" />
		
			<br /><br />
		<label for="locality"><?php _e( 'City/Town/Village', 'simple-location' ); ?></label>
		<input type="text" name="locality" id="locality" value="<?php echo ifset( $address['locality'], '' ); ?>" size="30" />
			<br /><br />
		<label for="region"><?php _e( 'State/County/Province', 'simple-location' ); ?></label>
		<input type="text" name="region" id="region" value="" size="30" />

		<label for="country-code"><?php _e( 'Country', 'simple-location' ); ?></label>
		<input type="text" name="country-code" id="country-code" value="" size="2" />
	
			<h3>Additional Information</h3>

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
		<div class="button-group">
		<button type="button" class="save-venue-button button-secondary" disabled>Save as Venue</button>
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
		if ( ! empty( $_POST['geo_latitude'] ) ) {
			update_post_meta( $post_id, 'geo_latitude', $_POST['latitude'] );
		} else {
			delete_post_meta( $post_id, 'geo_latitude' );
		}
		if ( ! empty( $_POST['geo_longitude'] ) ) {
			update_post_meta( $post_id, 'geo_longitude', $_POST['longitude'] );
		} else {
			delete_post_meta( $post_id, 'geo_longitude' );
		}

		$map = $_POST['geo_map'];
		if ( $map ) {
			update_post_meta( $post_id, 'geo_map', 1 );
		} else {
			if ( ! empty( $_POST['geo_address'] ) ) {
				update_post_meta( $post_id, 'geo_address', $_POST['geo_address'] );
			}
			if ( ! empty( $_POST['street-address'] ) ) {
				$adr['street-address'] = sanitize_text_field( $_POST['street-address'] );
			}
			if ( ! empty( $_POST['extended-address'] ) ) {
				$adr['extended-address'] = sanitize_text_field( $_POST['extended-address'] );
			}
			if ( ! empty( $_POST['locality'] ) ) {
					$adr['locality'] = sanitize_text_field( $_POST['locality'] );
			}
			if ( ! empty( $_POST['region'] ) ) {
				$adr['region'] = sanitize_text_field( $_POST['region'] );
			}
			if ( ! empty( $_POST['country-name'] ) ) {
				$adr['country-name'] = sanitize_text_field( $_POST['country-name'] );
			}
			update_post_meta( $post_id, 'mf2_adr', $adr );
		}
	}
}
?>

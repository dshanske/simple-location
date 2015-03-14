<?php
// Adds Post Meta Box for Location


// Add meta box to new post/post pages only 
add_action('load-post.php', 'locbox_setup');
add_action('load-post-new.php', 'locbox_setup');


/* Meta box setup function. */
function locbox_setup() {

  /* Add meta boxes on the 'add_meta_boxes' hook. */
  add_action( 'add_meta_boxes', 'locbox_add_postmeta_boxes' );
}

/* Create one or more meta boxes to be displayed on the post editor screen. */
function locbox_add_postmeta_boxes() {
  $screens = array( 'post', 'page' );
  foreach ( $screens as $screen ) {
    add_meta_box(
      'locationbox-meta',      // Unique ID
      esc_html__( 'Location', 'simple-location' ),    // Title
      'location_metabox',   // Callback function
      $screen,         // Admin page (or post type)
      'normal',         // Context
      'default'         // Priority
    );
  }  
}

function location_metabox( $object, $box ) { ?>

  <?php wp_nonce_field( 'location_metabox', 'location_metabox_nonce' ); ?>
   <script language="javascript">
	function getLocation()
  	   {
  		if (navigator.geolocation)
			{
		      navigator.geolocation.getCurrentPosition(showPosition);
	   }
  		else{alert("Geolocation is not supported by this browser.");}
  }
function showPosition(position)
  {
	document.getElementById("geo_latitude").value = position.coords.latitude;
     	document.getElementById("geo_longitude").value = position.coords.longitude;
	document.getElementById("geo_address").value = json.address.road + ',' + json.address.city;

  }
  </script>


  <p>
    <label for="geo_public"><?php _e( "Public", 'simple-location' ); ?></label>
    <input type="checkbox" name="geo_public" id="geo_public" <?php checked(get_post_meta( $object->ID, 'geo_public', true ), "1" ); ?>" />
    <br />
    <label for="geo_latitude"><?php _e( "Latitude", 'simple-location' ); ?></label>
    <input type="text" name="geo_latitude" id="geo_latitude" value="<?php echo esc_attr( get_post_meta( $object->ID, 'geo_latitude', true ) ); ?>" size="30" />
    <br />
    <label for="geo_longitude"><?php _e( "Longitude", 'simple-location' ); ?></label>
    <input type="text" name="geo_longitude" id="geo_longitude" value="<?php echo esc_attr( get_post_meta( $object->ID, 'geo_longitude', true ) ); ?>" size="30" />
    <br />
    <label for="geo_address"><?php _e( "Human-Readable Address (Optional)", 'simple-location' ); ?></label>
    <br />
    <textarea name="geo_address" id="geo_address" cols="70"><?php echo esc_attr( get_post_meta( $object->ID, 'geo_address', true ) ); ?></textarea>   
    <br />
    <label for="geo_venue"><?php _e( "Venue - Name or URL of the Location(Example: Home, John's Pizza, Wordpress University, etc (Optional)", 'simple-location' ); ?></label>
    <br />
    <input type="text" name="geo_venue" id="geo_venue" value="<?php echo esc_attr( get_post_meta( $object->ID, 'geo_venue', true ) ); ?>" size="70" />   
     <button type="button" onclick="getLocation();return false;">Retrieve Location</button>
 </p>

<?php }

/* Save the meta box's post metadata. */
function locationbox_save_post_meta( $post_id ) {

	/*
	 * We need to verify this came from our screen and with proper authorization,
	 * because the save_post action can be triggered at other times.
	 */

	// Check if our nonce is set.
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

	/* OK, its safe for us to save the data now. */
	if( isset( $_POST[ 'geo_latitude' ] ) ) {
        update_post_meta( $post_id, 'geo_latitude', esc_attr( $_POST[ 'geo_latitude' ] ) );
	}
	if( isset( $_POST[ 'geo_longitude' ] ) ) {
        update_post_meta( $post_id, 'geo_longitude', esc_attr( $_POST[ 'geo_longitude' ] ) );
    }
	if( isset( $_POST[ 'geo_address' ] ) ) {
        update_post_meta( $post_id, 'geo_address', esc_attr( $_POST[ 'geo_address' ] ) );
    }
	// Unlike the other fields, venue is not part of Geodata, but is essentially a title for the address
	if( isset( $_POST[ 'geo_venue' ] ) ) {
        update_post_meta( $post_id, 'geo_venue', esc_attr( $_POST[ 'geo_venue' ] ) );
    }
	$public= $_POST[ 'geo_public' ];
	if($public)
	  		update_post_meta($post_id, 'geo_public', 1);
	  	else
	  		update_post_meta($post_id, 'geo_public', 0);
}

add_action( 'save_post', 'locationbox_save_post_meta' );

?>

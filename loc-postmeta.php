<?php
// Adds Post Meta Box for Location

if (!function_exists('ifset') ) {
  function ifset(&$var, $default = false) {
      return isset($var) ? $var : $default;
  }
}

// Add meta box to new post/post pages only 
add_action('load-post.php', 'locbox_setup');
add_action('load-post-new.php', 'locbox_setup');


/* Meta box setup function. */
function locbox_setup() {

  /* Add meta boxes on the 'add_meta_boxes' hook. */
  add_action( 'add_meta_boxes', 'venuebox_add_postmeta_boxes' );
  add_action( 'add_meta_boxes', 'locbox_add_postmeta_boxes' );
  add_action( 'add_meta_boxes', 'adrbox_add_postmeta_boxes' );
}

/* Create location meta boxes to be displayed on the post editor screen. */
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

/* Create address meta boxes to be displayed on the post editor screen. */
function adrbox_add_postmeta_boxes() {
  $screens = array( 'post', 'page' );
  foreach ( $screens as $screen ) {
    add_meta_box(
      'addressbox-meta',      // Unique ID
      esc_html__( 'Address', 'simple-location' ),    // Title
      'address_metabox',   // Callback function
      $screen,         // Admin page (or post type)
      'normal',         // Context
      'default'         // Priority
    );
  }
}

/* Create a venue meta boxes to only be displayed on the page editor screen. */
function venuebox_add_postmeta_boxes() {
  $screens = array( 'page' );
  foreach ( $screens as $screen ) {
    add_meta_box(
      'venuebox-meta',      // Unique ID
      esc_html__( 'Venue', 'simple-location' ),    // Title
      'venue_metabox',   // Callback function
      $screen,         // Admin page (or post type)
      'normal',         // Context
      'default'         // Priority
    );
  }
}

function venue_metabox( $object, $box ) { ?>
  <?php wp_nonce_field( 'venue_metabox', 'venue_metabox_nonce' ); ?>
  <p>
    <label for="is_venue"><?php _e( "Set Page as Venue", 'simple-location' ); ?></label>
      <input type="checkbox" name="is_venue" id="is_venue" <?php checked(get_post_meta( $object->ID, 'is_venue', true ), "1" ); ?>" />
    <br />

<?php }


function location_metabox( $object, $box ) { ?>
  <?php wp_nonce_field( 'location_metabox', 'location_metabox_nonce' ); ?>
  <p>
    <label for="geo_public"><?php _e( "Display Location", 'simple-location' ); ?></label>
    <input type="checkbox" name="geo_public" id="geo_public" <?php checked(get_post_meta( $object->ID, 'geo_public', true ), "1" ); ?>" />

    <label for="geo_round"><?php _e( "Round Coordinates", 'simple-location' ); ?></label>
    <input type="checkbox" name="geo_round" id="geo_round" <?php checked(get_post_meta( $object->ID, 'geo_round', true ), "1" ); ?>" />

    <label for="geo_map"><?php _e( "Display Map", 'simple-location' ); ?></label>
    <input type="checkbox" name="geo_map" id="geo_map" <?php checked(get_post_meta( $object->ID, 'geo_map', true ), "1" ); ?>" />
    <br />
    <br />

    <label for="geo_latitude"><?php _e( "Latitude", 'simple-location' ); ?></label>
    <input type="text" name="geo_latitude" id="geo_latitude" value="<?php echo esc_attr( get_post_meta( $object->ID, 'geo_latitude', true ) ); ?>" size="10" />
    
    <label for="geo_longitude"><?php _e( "Longitude", 'simple-location' ); ?></label>
    <input type="text" name="geo_longitude" id="geo_longitude" value="<?php echo esc_attr( get_post_meta( $object->ID, 'geo_longitude', true ) ); ?>" size="10" />
    
    <label for="geo_altitude"><?php _e( "Altitude", 'simple-location' ); ?></label>
    <input type="text" name="geo_altitude" id="geo_altitude" value="<?php echo esc_attr( get_post_meta( $object->ID, 'geo_altitude', true ) ); ?>" size="10" />
    <br />
     <button type="button" onclick="getLocation();return false;">Retrieve Location</button>
 </p>

<?php }

function address_metabox( $object, $box ) { ?>
  <?php 
    wp_nonce_field( 'address_metabox', 'address_metabox_nonce' ); 
    $address = array_pop( get_post_meta( $object->ID, 'mf2_adr') );
  ?>
 <p> <?php _e('This data is automatically imported from the location coordinates when available on save/publish if the below is checked', 'simple-location'); ?></p>
    <label for="geo_lookup"><?php _e("Address Lookup", 'simple-location' ); ?></label>
    <input type="checkbox" name="geo_lookup" id="geo_lookup" <?php checked(get_post_meta( $object->ID, 'geo_lookup', true ), "1" ); ?>" />
    <br />
 <label for="geo_address"><?php _e( "Display Name", 'simple-location' ); ?></label>
    <br />
    <input type="text" name="geo_address" id="geo_address" value="<?php echo get_post_meta( $object->ID, 'geo_address', true); ?>" size="70" />
 </p>


  <p>
    <label for="street-address"><?php _e( "Street Address", 'simple-location' ); ?></label>
    <br />
    <input type="text" name="street-address" id="street-address" value="<?php echo ifset($address['street-address'], ""); ?>" size="70" />
  </p>
  <p>
   <label for="extended-address"><?php _e( "Extended Address", 'simple-location' ); ?></label>
    <br /> 
    <input type="text" name="extended-address" id="extended-address" value="<?php echo ifset($address['extended-address'], ""); ?>" size="70" />
 </p>
  <p> 
   <label for="locality"><?php _e( "City/Town/Village", 'simple-location' ); ?></label>
    <br />
    <input type="text" name="locality" id="locality" value="<?php echo ifset($address['locality'], ""); ?>" size="70" />
 </p>
  <p>
   <label for="region"><?php _e( "State/County/Province", 'simple-location' ); ?></label>
    <br />
    <input type="text" name="region" id="region" value="<?php echo ifset($address['region'], ""); ?>" size="70" />
 </p>

 <label for="country-name"><?php _e( "Country", 'simple-location' ); ?></label>
    <br />
    <input type="text" name="country-name" id="country-name" value="<?php echo ifset($address['country-name'], ""); ?>" size="70" />
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
	if( !empty( $_POST[ 'geo_latitude' ] ) ) {
    update_post_meta( $post_id, 'geo_latitude', esc_attr( clean_coordinate($_POST[ 'geo_latitude' ]) ) );
	}
  else {
    delete_post_meta( $post_id, 'geo_latitude');
  }
	if( !empty( $_POST[ 'geo_longitude' ] ) ) {
    update_post_meta( $post_id, 'geo_longitude', esc_attr( clean_coordinate($_POST[ 'geo_longitude' ]) ) );
    }
  else {
          delete_post_meta( $post_id, 'geo_longitude');
  }
	// Unlike the other fields, venue is not part of Geodata, but is essentially a title for the address
	if( !empty( $_POST[ 'geo_venue' ] ) ) {
        update_post_meta( $post_id, 'geo_venue', esc_attr( $_POST[ 'geo_venue' ] ) );
    }
  else {
          delete_post_meta( $post_id, 'geo_venue');
       }
	$public= $_POST[ 'geo_public' ];
	if($public)
	  		update_post_meta($post_id, 'geo_public', 1);
	  	else
	  		update_post_meta($post_id, 'geo_public', 0);

  $round= $_POST[ 'geo_round' ];
  if($round)
        update_post_meta($post_id, 'geo_round', 1);
      else
        update_post_meta($post_id, 'geo_round', 0);
  $map= $_POST[ 'geo_map' ];
  if($map)
        update_post_meta($post_id, 'geo_map', 1);
      else
        update_post_meta($post_id, 'geo_map', 0);
}

/* Save the meta box's post metadata. */
function venuebox_save_post_meta( $post_id ) {
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
 $isvenue= $_POST[ 'is_venue' ];
  if($isvenue)
        update_post_meta($post_id, 'isvenue', 1);
      else
        update_post_meta($post_id, 'isvenue', 0);



}

add_action( 'save_post', 'locationbox_save_post_meta' );

add_action( 'save_post', 'venuebox_save_post_meta' );


function addressbox_save_post_meta( $post_id ) {
  /*
   * We need to verify this came from our screen and with proper authorization,
   * because the save_post action can be triggered at other times.
   */

  // Check if our nonce is set.
  if ( ! isset( $_POST['address_metabox_nonce'] ) ) {
    return;
  }
  // Verify that the nonce is valid.
  if ( ! wp_verify_nonce( $_POST['address_metabox_nonce'], 'address_metabox' ) ) {
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
  $lookup= $_POST[ 'geo_lookup' ];
  $adr = array();
  if($lookup) {
    if ( !empty( $_POST[ 'geo_latitude' ] ) && !empty( $_POST[ 'geo_longitude' ] ) ) {
        $reverse_adr = reverse_lookup($_POST['geo_latitude'], $_POST['geo_longitude']);
        update_post_meta( $post_id, 'mf2_adr', $reverse_adr );
    }
   update_post_meta($post_id, 'geo_lookup', 0);
  }
  else {
    if( !empty( $_POST[ 'geo_address' ] ) ) {
      update_post_meta($post_id, 'geo_address', $_POST[ 'geo_address'] );
    } 
    else {
    update_post_meta($post_id, 'geo_address', $reverse_adr['name'] );
    }
   if( !empty( $_POST[ 'street-address' ] ) ) {
     $adr['street-address'] = $_POST[ 'street-address' ];
   }
   if( !empty( $_POST[ 'extended-address' ] ) ) {
    $adr['extended-address'] = $_POST[ 'extended-address' ];
   }
   if( !empty( $_POST[ 'locality' ] ) ) {
    $adr['locality'] = $_POST[ 'locality' ];
   }
   if( !empty( $_POST[ 'region' ] ) ) {
    $adr['region'] = $_POST[ 'region' ];
   }
   if( !empty( $_POST[ 'country-name' ] ) ) {
    $adr['country-name'] = $_POST[ 'country-name' ];
   }
  update_post_meta( $post_id, 'mf2_adr', $adr );
  }
}

add_action( 'save_post', 'addressbox_save_post_meta' );

?>

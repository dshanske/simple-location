<?php
// Adds Post Meta Box for Location
add_action( 'init', array( 'Loc_Metabox', 'init' ) );
add_action( 'admin_init', array( 'Loc_Metabox', 'admin_init' ) );

class Loc_Metabox {
	public static function admin_init() {
		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', array( 'Loc_Metabox', 'add_meta_boxes' ) );
	}

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( 'Loc_Metabox', 'enqueue' ) );
		add_action( 'save_post', array( 'Loc_Metabox', 'save_post_meta' ) );
		add_action( 'save_post', array( 'Loc_Metabox', 'last_seen' ), 20, 2 );
		add_action( 'edit_attachment', array( 'Loc_Metabox', 'save_post_meta' ) );
		add_action( 'edit_comment', array( 'Loc_Metabox', 'save_comment_meta' ) );
		add_action( 'show_user_profile', array( 'Loc_Metabox', 'user_profile' ), 12 );
		add_action( 'edit_user_profile', array( 'Loc_Metabox', 'user_profile' ), 12 );
		add_action( 'personal_options_update', array( 'Loc_Metabox', 'save_user_meta' ), 12 );
		add_action( 'edit_user_profile_update', array( 'Loc_Metabox', 'save_user_meta' ), 12 );
	}

	public static function screens() {
		$screens = array( 'post', 'comment', 'attachment' );
		return apply_filters( 'sloc_post_types', $screens );
	}

	public static function enqueue( $hook_suffix ) {
		$screens = self::screens();
		if ( in_array( get_current_screen()->id, $screens, true ) || 'profile.php' === $hook_suffix ) {
			wp_enqueue_script(
				'sloc_location',
				plugins_url( 'js/location.js', dirname( __FILE__ ) ),
				array( 'jquery' ),
				Simple_Location_Plugin::$version
			);
			wp_enqueue_style(
				'sloc_location',
				plugins_url( 'css/location-admin-meta-box.css', dirname( __FILE__ ) ),
				array(),
				Simple_Location_Plugin::$version
			);
		}
	}

	/* Create location meta boxes to be displayed on the post editor screen. */
	public static function add_meta_boxes() {
		add_meta_box(
			'locationbox-meta',      // Unique ID
			esc_html__( 'Location', 'simple-location' ),    // Title
			array( 'Loc_Metabox', 'metabox' ),   // Callback function
			self::screens(),         // Admin page (or post type)
			'normal',         // Context
			'default'         // Priority
		);
	}

	public static function geo_public( $public ) {
		?>
		<label for="geo_public"><?php _e( 'Show:', 'simple-location' ); ?></label><br />
		<select name="geo_public">
		<option value=0 <?php selected( $public, 0 ); ?>><?php _e( 'Hide', 'simple-location' ); ?></option>
		<option value=1 <?php selected( $public, 1 ); ?>><?php _e( 'Show Map and Description', 'simple-location' ); ?></option>
		<option value=2 <?php selected( $public, 2 ); ?>><?php _e( 'Description Only', 'simple-location' ); ?></option>
		</select><br /><br />
		<?php
	}

	public static function geo_public_user( $user ) {
		$public = get_the_author_meta( 'geo_public', $user->ID );
		if ( ! $public ) {
			$public = get_option( 'geo_public' );
		}
		$public = (int) $public;
?>
		<tr>
		<th><label for="geo_public"><?php _e( 'Show:', 'simple-location' ); ?></label></th>
		<td><select name="geo_public">
		<option value=0 <?php selected( $public, 0 ); ?>><?php _e( 'Hide', 'simple-location' ); ?></option>
		<option value=1 <?php selected( $public, 1 ); ?>><?php _e( 'Show Map and Description', 'simple-location' ); ?></option>
		<option value=2 <?php selected( $public, 2 ); ?>><?php _e( 'Description Only', 'simple-location' ); ?></option>
		</select></td>
		</tr>
		<?php
	}

	public static function temp_unit() {
		switch ( get_option( 'sloc_measurements' ) ) {
			case 'imperial':
				return 'F';
			default:
				return 'C';
		}
	}


	public static function metabox( $object, $box ) {
		wp_nonce_field( 'location_metabox', 'location_metabox_nonce' );
		$geodata = WP_Geo_Data::get_geodata( $object );
		$weather = ifset( $geodata['weather'], array() );
		$wind    = ifset( $weather['wind'], array() );
		if ( is_null( $geodata ) ) {
			$geodata = array( 'public' => get_option( 'geo_public' ) );
		}
?>
		<div class="location hide-if-no-js">
			<h3>Location</h3>
			<p>
				<a
					class="lookup-address-button button button-primary"
					aria-label="<?php _e( 'Location Lookup', 'simple-location' ); ?>"
					title="<?php _e( 'Location Lookup', 'simple-location' ); ?>
				">
					<?php _e( 'Use My Current Location', 'simple-location' ); ?>
				</a>
			</p>

			<label for="address"><?php _e( 'Location Name:', 'simple-location' ); ?></label>
			<input type="text" name="address" id="address" value="<?php echo ifset( $geodata['address'] ); ?>" class="widefat" data-role="none" />

			<p class="latlong">
				<label for="latitude">
					<?php _e( 'Latitude:', 'simple-location' ); ?>
					<input type="text" name="latitude" id="latitude" value="<?php echo ifset( $geodata['latitude'], '' ); ?>" />
				</label>

				<label for="longitude">
					<?php _e( 'Longitude:', 'simple-location' ); ?>
					<input type="text" name="longitude" id="longitude" value="<?php echo ifset( $geodata['longitude'], '' ); ?>" />
				</label>

				<input type="hidden" name="map_zoom" id="map_zoom" value="<?php echo ifset( $geodata['map_zoom'], '' ); ?>" />
				<input type="hidden" name="accuracy" id="accuracy" value="<?php echo ifset( $geodata['accuracy'], '' ); ?>" />
				<input type="hidden" name="heading" id="heading" value="<?php echo ifset( $geodata['heading'], '' ); ?>" />
				<input type="hidden" name="speed" id="speed" value="<?php echo ifset( $geodata['speed'], '' ); ?>" />
				<input type="hidden" name="altitude" id="altitude" value="<?php echo ifset( $geodata['altitude'], '' ); ?>" />
			</p>

			<?php self::geo_public( ifset( $geodata['public'] ) ); ?>
		</div>

		<div class="weather-data hide-if-no-js">
			<h3>Weather</h3>

			<p>
				<a
					class="lookup-weather-button button button-primary"
					aria-label="<?php _e( 'Weather Lookup', 'simple-location' ); ?>"
					title="<?php _e( 'Weather Lookup', 'simple-location' ); ?>
				">
					<?php _e( 'Get the Weather', 'simple-location' ); ?>
				</a>
			</p>

			<label for="temperature"><?php _e( 'Temperature: ', 'simple-location' ); ?></label>
			<input type="text" name="temperature" id="temperature" value="<?php echo ifset( $weather['temperature'], '' ); ?>" style="width:10%" />
			<label for="humidity"><?php _e( 'Humidity: ', 'simple-location' ); ?></label>
			<input type="text" name="humidity" id="humidity" value="<?php echo ifset( $weather['humidity'], '' ); ?>" style="width:10%" />
			<input type="hidden" name="weather_summary" id="weather_summary" value="<?php echo ifset( $weather['summary'], '' ); ?>" style="width:25%" />
			<input type="hidden" name="weather_icon" id="weather_icon" value="<?php echo ifset( $weather['icon'], '' ); ?>" style="width:25%" />
			<input type="hidden" name="pressure" id="pressure" value="<?php echo ifset( $weather['pressure'], '' ); ?>" style="width:25%" />
			<input type="hidden" name="visibility" id="visibility" value="<?php echo ifset( $weather['visibility'], '' ); ?>" style="width:25%" />
			<input type="hidden" name="wind_speed" id="wind_speed" value="<?php echo ifset( $wind['speed'], '' ); ?>" style="width:25%" />
			<input type="hidden" name="wind_degree" id="wind_degree" value="<?php echo ifset( $wind['degree'], '' ); ?>" style="width:25%" />
			<input type="hidden" name="units" id="units" value="<?php echo ifset( $wind['units'], self::temp_unit() ); ?>" style="width:25%" />
		</div>

		<a href="#location_detail" class="show-location-details hide-if-no-js"><?php _e( 'Show Detail', 'simple-location' ); ?></span></a>
			<div id="location-detail" class="hide-if-js">
			<br />
			<a class="clear-location-button button-link hide-if-no-js"><?php _e( 'Clear Location', 'simple-location' ); ?></a>

		<p> <?php _e( 'Location Data below can be used to complete the location description, which will be displayed, or saved as a venue.', 'simple-location' ); ?></p>
			<br />
			<label for="name"><?php _e( 'Location Name', 'simple-location' ); ?></label>
			<input type="text" name="location-name" id="location-name" value="" class="widefat" />
			<br /></br />

			<label for="street-address"><?php _e( 'Address', 'simple-location' ); ?></label>
			<input type="text" name="street-address" id="street-address" value="" class="widefat" />

			<p>
				<label for="extended-address"><?php _e( 'Extended Address', 'simple-location' ); ?></label>
				<input type="text" name="extended-address" id="extended-address" value="" class="widefat" />
			</p>

			<p>
				<label for="locality"><?php _e( 'City/Town/Village', 'simple-location' ); ?></label>
				<input type="text" name="locality" id="locality" value="<?php echo ifset( $address['locality'], '' ); ?>" class="widefat" />
			</p>
			<p>
				<label for="region"><?php _e( 'State/County/Province', 'simple-location' ); ?></label>
				<input type="text" name="region" id="region" value="" class="widefat" style="width:75%" />
				<label for="country-code"><?php _e( 'Country Code', 'simple-location' ); ?></label>
				<input type="text" name="country-code" id="country-code" value="" size="2" />
			</p>
			<p>
				<label for="extended-address"><?php _e( 'Neighborhood/Suburb', 'simple-location' ); ?></label>
				<input type="text" name="extended-address" id="extended-address" value="" class="widefat" />
			</p>
			<p>
				<label for="postal-code"><?php _e( 'Postal Code', 'simple-location' ); ?></label>
				<input type="text" name="postal-code" id="postal-code" value="" class="widefat" style="width:25%" />

				<br />

				<label for="country-name"><?php _e( 'Country Name', 'simple-location' ); ?></label>
				<input type="text" name="country-name" id="country-name" value="" class="widefat" style="width:40%" />
			</p>

		<div class="button-group">
			<button type="button" class="save-venue-button button-secondary" disabled><?php _e( 'Save as Venue', 'simple-location' ); ?> </button>
		</div>
	</div>
	<?php
	}

	public static function user_profile( $user ) {
		echo '<h3>' . esc_html__( 'Last Reported Location', 'simple-location' ) . '</h3>';
		echo '<p>' . esc_html__( 'This allows you to set the last reported location for this author. See Simple Location settings for options.', 'simple-location' ) . '</p>';
		echo '<a class="hide-if-no-js lookup-address-button">';
				echo '<span class="dashicons dashicons-location" aria-label="' . __( 'Location Lookup', 'simple-location' ) . '" title="' . __( 'Location Lookup', 'simple-location' ) . '"></span></a>';
		echo '<table class="form-table">';
		self::profile_text_field( $user, 'latitude', __( 'Latitude', 'simple-location' ), 'Description' );
		self::profile_text_field( $user, 'longitude', __( 'Longitude', 'simple-location' ), 'Description' );
		self::profile_text_field( $user, 'address', __( 'Address', 'simple-location' ), 'Description' );
		self::geo_public_user( $user );
		echo '</table>';
	}




	public static function profile_text_field( $user, $key, $title, $description ) {
	?>
	<tr>
		<th><label for="<?php echo esc_html( $key ); ?>"><?php echo esc_html( $title ); ?></label></th>
		<td>
			<input type="text" name="<?php echo esc_html( $key ); ?>" id="<?php echo esc_html( $key ); ?>" value="<?php echo esc_attr( get_the_author_meta( 'geo_' . $key, $user->ID ) ); ?>" class="regular-text" /><br />
			<span class="description"><?php echo esc_html( $description ); ?></span>
		</td>
	</tr>
	<?php
	}


	public static function last_seen( $post_id, $post ) {
		if ( 0 === (int) get_option( 'sloc_last_report' ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		if ( $post->post_date !== $post->post_modified ) {
			return;
		}
		$geodata = WP_Geo_Data::get_geodata( $post );
		$author  = new WP_User( $post->post_author );
		WP_Geo_Data::set_geodata( $author, $geodata );
	}

	/* Save the meta box's post metadata. */
	public static function save_post_meta( $post_id ) {
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
		if ( isset( $_POST['post_type'] ) && 'page' === $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}
		if ( has_term( '', 'venue' ) ) {
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

		if ( ! empty( $_POST['map_zoom'] ) ) {
			update_post_meta( $post_id, 'geo_zoom', sanitize_text_field( $_POST['map_zoom'] ) );
		} else {
			delete_post_meta( $post_id, 'geo_zoom' );
		}

		if ( ! empty( $_POST['altitude'] ) ) {
			update_post_meta( $post_id, 'geo_altitude', sanitize_text_field( $_POST['altitude'] ) );
		} else {
			delete_post_meta( $post_id, 'geo_altitude' );
		}

		if ( ! empty( $_POST['speed'] ) && 'NaN' !== $_POST['speed'] ) {
			update_post_meta( $post_id, 'geo_speed', sanitize_text_field( $_POST['speed'] ) );
		} else {
			delete_post_meta( $post_id, 'geo_speed' );
		}

		if ( ! empty( $_POST['heading'] ) && 'NaN' !== $_POST['heading'] ) {
			update_post_meta( $post_id, 'geo_heading', sanitize_text_field( $_POST['heading'] ) );
		} else {
			delete_post_meta( $post_id, 'geo_heading' );
		}

		$weather = array();

		if ( ! empty( $_POST['temperature'] ) ) {
			$weather['temperature'] = sanitize_text_field( $_POST['temperature'] );
		}

		if ( ! empty( $_POST['units'] ) ) {
			$weather['units'] = sanitize_text_field( $_POST['units'] );
		}

		if ( ! empty( $_POST['humidity'] ) ) {
			$weather['humidity'] = sanitize_text_field( $_POST['humidity'] );
		}
		if ( ! empty( $_POST['pressure'] ) ) {
			$weather['pressure'] = sanitize_text_field( $_POST['pressure'] );
		}
		if ( ! empty( $_POST['weather_summary'] ) ) {
			$weather['summary'] = sanitize_text_field( $_POST['weather_summary'] );
		}
		if ( ! empty( $_POST['weather_icon'] ) ) {
			$weather['icon'] = sanitize_text_field( $_POST['weather_icon'] );
		}
		if ( ! empty( $_POST['visibility'] ) ) {
			$weather['visibility'] = sanitize_text_field( $_POST['visibility'] );
		}

		$wind = array();
		if ( ! empty( $_POST['wind_speed'] ) ) {
			$wind['speed'] = sanitize_text_field( $_POST['wind_speed'] );
		}
		if ( ! empty( $_POST['wind_degree'] ) ) {
			$wind['degree'] = sanitize_text_field( $_POST['wind_degree'] );
		}
		if ( ! empty( $wind ) ) {
			$weather['wind'] = $wind;
		}

		if ( ! empty( $weather ) ) {
			update_post_meta( $post_id, 'geo_weather', $weather );
		} else {
			delete_post_meta( $post_id, 'geo_weather' );
		}

		if ( ! empty( $_POST['address'] ) ) {
			if ( isset( $_POST['geo_public'] ) ) {
				update_post_meta( $post_id, 'geo_public', $_POST['geo_public'] );
			}
		}
	}

	/* Save the meta box's comment metadata. */
	public static function save_comment_meta( $comment_id ) {
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
		if ( ! current_user_can( 'edit_comment', $comment_id ) ) {
			return;
		}
		/* OK, its safe for us to save the data now. */
		if ( ! empty( $_POST['latitude'] ) ) {
			update_comment_meta( $comment_id, 'geo_latitude', $_POST['latitude'] );
		} else {
			delete_comment_meta( $comment_id, 'geo_latitude' );
		}
		if ( ! empty( $_POST['longitude'] ) ) {
			update_comment_meta( $comment_id, 'geo_longitude', $_POST['longitude'] );
		} else {
			delete_comment_meta( $comment_id, 'geo_longitude' );
		}
		if ( ! empty( $_POST['address'] ) ) {
			update_comment_meta( $comment_id, 'geo_address', sanitize_text_field( $_POST['address'] ) );
		} else {
			delete_comment_meta( $comment_id, 'geo_address' );
		}

		if ( ! empty( $_POST['map_zoom'] ) ) {
			update_comment_meta( $comment_id, 'geo_zoom', sanitize_text_field( $_POST['map_zoom'] ) );
		} else {
			delete_comment_meta( $comment_id, 'geo_zoom' );
		}

		if ( ! empty( $_POST['address'] ) ) {
			if ( isset( $_POST['geo_public'] ) ) {
				update_comment_meta( $comment_id, 'geo_public', $_POST['geo_public'] );
			}
		}
	}


	/* Save the user metadata. */
	public static function save_user_meta( $user_id ) {
		// Check the user's permissions.
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		/* OK, its safe for us to save the data now. */
		if ( ! empty( $_POST['latitude'] ) ) {
			update_user_meta( $user_id, 'geo_latitude', $_POST['latitude'] );
		} else {
			delete_user_meta( $user_id, 'geo_latitude' );
		}
		if ( ! empty( $_POST['longitude'] ) ) {
			update_user_meta( $user_id, 'geo_longitude', $_POST['longitude'] );
		} else {
			delete_user_meta( $user_id, 'geo_longitude' );
		}

		if ( ! empty( $_POST['address'] ) ) {
			update_user_meta( $user_id, 'geo_address', $_POST['address'] );
		} else {
			delete_user_meta( $user_id, 'geo_address' );
		}
		if ( ! empty( $_POST['latitude'] ) && ! empty( $_POST['longitude'] ) ) {
			if ( isset( $_POST['geo_public'] ) ) {
				update_user_meta( $user_id, 'geo_public', $_POST['geo_public'] );
			}
		}
	}



}
?>

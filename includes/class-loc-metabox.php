<?php
// Adds Post Meta Box for Location
add_action( 'init', array( 'Loc_Metabox', 'init' ) );
add_action( 'admin_init', array( 'Loc_Metabox', 'admin_init' ) );

class Loc_Metabox {
	public static function admin_init() {
		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', array( 'Loc_Metabox', 'add_meta_boxes' ) );
		add_action( 'post_submitbox_misc_actions', array( 'Loc_Metabox', 'post_submitbox' ) );
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

	public static function post_submitbox() {
		$choices = WP_Geo_Data::geo_public();
		global $post;
		$public = WP_Geo_Data::get_visibility( 'post', $post->ID );
		wp_nonce_field( 'location_visibility_metabox', 'location_visibility_nonce' );
		?>
				<div class="misc-pub-section misc-pub-location">
				<span class="dashicons dashicons-location" id="location-lookup" title="<?php esc_html_e( 'Lookup Location', 'simple-location' ); ?>"></span>
						<label for="post-location"><?php esc_html_e( 'Location:', 'simple-location' ); ?></label>
						<span id="post-location-label">
						<?php

								echo $choices[ $public ]; // phpcs:ignore
						?>
</span>
						<a href="#post_location" class="edit-post-location hide-if-no-js" role="button"><span aria-hidden="true">Edit</span> <span class="screen-reader-text">Location Settings</span></a>
				<br />
<div id="post-location-select" class="hide-if-js">
				<input type="hidden" name="hidden_post_location" id="hidden_post_location" value="<?php echo esc_attr( $public ); ?>" />
				<input type="hidden" name="location_default" id="location_default" value="<?php echo esc_attr( get_option( 'geo_public' ) ); ?>" />
				<select name="geo_public" id="post-location" width="90%">
				<?php
						echo WP_Geo_Data::geo_public_select( $public ); // phpcs:ignore
						echo '</select>';
				?>
<br />
				<a href="#post_location" class="save-post-location hide-if-no-js button">OK</a>
				<a href="#post_location" class="cancel-post-location hide-if-no-js button-cancel">Cancel</a>
</div>
</div>
		<?php
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
				Simple_Location_Plugin::$version,
				true
			);
			wp_enqueue_style(
				'sloc_metabox',
				plugins_url( 'css/location-admin.min.css', dirname( __FILE__ ) ),
				array(),
				Simple_Location_Plugin::$version
			);
			wp_localize_script(
				'sloc_location',
				'geo_public_options',
				WP_Geo_Data::geo_public()
			);
			wp_localize_script(
				'sloc_location',
				'geo_options',
				array(
					'lookup' => get_option( 'sloc_geolocation_provider' ),
				)
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

	public static function geo_public_user( $public ) {
		?>
		<tr>
		<th><label for="geo_public"><?php esc_html_e( 'Show:', 'simple-location' ); ?></label></th>
		<td><select name="geo_public">
		<?php WP_Geo_Data::geo_public_select( $public, true ); ?>
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
		load_template( plugin_dir_path( __DIR__ ) . 'templates/loc-metabox.php' );
	}

	public static function user_profile( $user ) {
		load_template( plugin_dir_path( __DIR__ ) . 'templates/loc-user-metabox.php' );
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

	public static function save_meta( $meta_type, $object_id ) {
		// phpcs:disable
		$lon_params = array( 'latitude', 'longitude', 'address', 'map_zoom', 'altitude', 'speed', 'heading' );
		foreach ( $lon_params as $param ) {
			if ( ! empty( $_POST[ $param ] ) && 'NaN' !== $_POST[ $param ] ) {
				update_metadata( $meta_type, $object_id, 'geo_' . $param, $_POST[ $param ] );
			} else {
				delete_metadata( $meta_type, $object_id, 'geo_' . $param );
			}
		}

		$weather    = array();
		$wtr_params = array( 'temperature', 'units', 'humidity', 'pressure', 'weather_summary', 'weather_icon', 'visibility' );
		foreach ( $wtr_params as $param ) {
			if ( ! empty( $_POST[ $param ] ) ) {
				$weather[ str_replace( 'weather_', '', $param ) ] = $_POST[ $param ];
			}
		}

		$wind = array();
		if ( ! empty( $_POST['wind_speed'] ) ) {
			$wind['speed'] = sanitize_text_field( $_POST['wind_speed'] );
		}
		if ( ! empty( $_POST['wind_degree'] ) ) {
			$wind['degree'] = sanitize_text_field( $_POST['wind_degree'] );
		}
		$wind = array_filter( $wind );
		if ( ! empty( $wind ) ) {
			$weather['wind'] = $wind;
		}
		$weather = array_filter( $weather );
		if ( ! empty( $weather ) ) {
			update_metadata( $meta_type, $object_id, 'geo_weather', $weather );
		} else {
			delete_metadata( $meta_type, $object_id, 'geo_weather' );
		}
		if ( isset( $_POST['latitude'] ) || isset( $_POST['longitude'] ) || isset( $_POST['address'] ) ) {
			WP_Geo_Data::set_visibility( $meta_type, $object_id, $_POST['geo_public'] );
		} else {
			delete_metadata( $meta_type, $object_id, 'geo_public' );
		}
		// phpcs:enable
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
		self::save_meta( 'post', $post_id );
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
		self::save_meta( 'comment', $comment_id );
	}


	/* Save the user metadata. */
	public static function save_user_meta( $user_id ) {
		// Check the user's permissions.
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		self::save_meta( 'user', $user_id );
	}



}
?>

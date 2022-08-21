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

	public static function location_side_metabox() {
		load_template( plugin_dir_path( __DIR__ ) . 'templates/loc-side-metabox.php' );
		do_action( 'simple_location_sidebox', get_current_screen()->id );
	}

	public static function screens() {
		$screens = array( 'post', 'attachment' );
		return apply_filters( 'sloc_post_types', $screens );
	}

	public static function enqueue( $hook_suffix ) {
		$screens   = self::screens();
		$screens[] = 'comment';
		$hooks     = array( 'profile.php' );

		if ( in_array( get_current_screen()->id, $screens, true ) || in_array( $hook_suffix, $hooks, true ) ) {
			wp_enqueue_style(
				'sloc_admin',
				plugins_url( 'css/location-admin.min.css', dirname( __FILE__ ) ),
				array(),
				Simple_Location_Plugin::$version
			);
			wp_enqueue_script(
				'sloc_location',
				plugins_url( 'js/location.js', dirname( __FILE__ ) ),
				array( 'jquery' ),
				Simple_Location_Plugin::$version,
				true
			);
			wp_enqueue_script(
				'moment-timezone',
				plugins_url( 'js/luxon.min.js', dirname( __FILE__ ) ),
				array(),
				Simple_Location_Plugin::$version,
				true
			);
			wp_localize_script(
				'sloc_location',
				'slocOptions',
				array(
					'lookup'             => get_option( 'sloc_geolocation_provider' ),
					'units'              => get_option( 'sloc_measurements' ),
					'visibility_options' => Geo_Base::geo_public(),
					'api_nonce'          => wp_create_nonce( 'wp_rest' ),
					'api_url'            => rest_url( '/sloc_geo/1.0/' ),
				)
			);
		}
	}

	/* Create location meta boxes to be displayed on the post editor screen. */
	public static function add_meta_boxes() {
		/*
		  add_meta_box(
			'locationbox-meta',      // Unique ID
			esc_html__( 'Location', 'simple-location' ),    // Title
			array( 'Loc_Metabox', 'metabox' ),   // Callback function
			self::screens(),         // Admin page (or post type)
			'normal',         // Context
			'default'         // Priority
		); */
		add_meta_box(
			'locationsidebox',
			esc_html__( 'Location', 'simple-location' ),
			array( 'Loc_Metabox', 'location_side_metabox' ),
			self::screens(), // post types
			'side',
			'default',
			array(
				'__block_editor_compatible_meta_box' => true,
				'__back_compat_meta_box'             => false,
			)
		);
		add_meta_box(
			'locationsidebox',
			esc_html__( 'Location', 'simple-location' ),
			array( 'Loc_Metabox', 'location_side_metabox' ),
			'comment',
			'normal',
			'default'
		);
	}

	public static function geo_public_user( $public ) {
		?>
		<tr>
		<th><label for="geo_public"><?php esc_html_e( 'Show:', 'simple-location' ); ?></label></th>
		<td><select id="location-visibility" name="geo_public">
		<?php Geo_Base::geo_public_select( $public, true ); ?>
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
		if ( Geo_Base::current_user_can_edit( $object ) ) {
			load_template( plugin_dir_path( __DIR__ ) . 'templates/loc-metabox.php' );
		}
	}

	public static function user_profile( $user ) {
		if ( current_user_can( 'edit_users_location', $user->ID ) ) {
			load_template( plugin_dir_path( __DIR__ ) . 'templates/loc-user-metabox.php' );
		}
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

		$timestamp = get_post_timestamp( $post );

		// Do not update the last seen notation if the post is more than 10 minutes old.
		if ( abs( time() - $timestamp ) > 600 ) {
			return;
		}

		$geodata = get_post_geodata( $post->ID );
		if ( ! is_array( $geodata ) ) {
			return;
		}
		$author = new WP_User( $post->post_author );
		if ( 'private' !== $geodata['visibility'] ) {
			set_post_geodata( $author, $geodata );
		}
	}

	public static function save_meta( $meta_type, $object_id, $convert = true ) {
		// phpcs:disable
		$units              = get_option( 'sloc_measurements' );
		$params = array( 'latitude', 'longitude', 'map_zoom', 'altitude', 'speed', 'heading', 'address','location_icon', 'timezone', 'geo_public' );
		$data = wp_array_slice_assoc( $_POST, $params );
		$data = array_map( 'sanitize_text_field', $data );

		if ( array_key_exists( 'map_zoom', $data ) ) {
			$data['zoom'] = $data['map_zoom'];
			unset( $data['map_zoom'] );
		}

		if ( array_key_exists( 'location_icon', $data ) && ! empty( $data['location_icon'] ) ) {
			$data['icon'] = $data['location_icon'];
			unset( $data['location_icon'] );
		}

		if ( array_key_exists( 'timezone', $data ) ) {
			$timezone = timezone_open( $data['timezone'] );
			if ( $timezone && ( ! Loc_Timezone::compare_timezones( wp_timezone(), $timezone  ) ) ) {
				$data['timezone'] = $timezone->getName();
			} else {
				$data['timezone'] = '';
			}
		}

		if ( ! empty( $weather['latitude'] ) || ! empty( $weather['longitude'] ) || ! empty( $weather['address'] ) ) {
			$data['visibility'] = $data['geo_public'];
		} else {
			Geo_Data::delete_geodata( $meta_type, $object_id, 'visibility' );
		}

		unset( $data['geo_public'] );

		foreach ( $data as $key => $value ) {
			if ( ! empty( $value ) ) {
				if ( is_numeric( $value ) ) {
					$value = floatval( $value );
				}
				Geo_Data::set_geodata( $meta_type, $object_id, $key, $value );
			} else {
				Geo_Data::delete_geodata( $meta_type, $object_id, $key );
			}
		}

		// Numeric Properties
		$wtr_params = array( 'temperature', 'humidity', 'pressure', 'cloudiness', 'rain', 'snow', 'windspeed', 'winddegree', 'windgust' );
		$weather = wp_array_slice_assoc( $_POST, $wtr_params );

		if ( array_key_exists( 'weather_visibility', $_POST ) ) {
			$weather['visibility'] = $_POST['weather_visibility'];
		}

		foreach( $weather as $key => $value ) {
			if ( ! is_numeric( $value ) ) {
				unset( $weather[ $key ] );
			}
		}
		$weather = array_map( 'floatval', $weather );

		if ( array_key_exists( 'weather_summary', $_POST ) ) {
			$weather['summary'] = sanitize_text_field( $_POST['weather_summary'] );
		}

		if ( array_key_exists( 'weather_icon', $_POST ) ) {
			$weather['icon'] = sanitize_text_field( $_POST['weather_icon'] );
		}

		if ( 'imperial' === $units && $convert ) {
			$weather = Weather_Provider::imperial_to_metric( $weather );
		}

		if ( ! empty( $weather ) ) {
			Sloc_Weather_Data::set_object_weatherdata( $meta_type, $object_id, '', $weather );
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

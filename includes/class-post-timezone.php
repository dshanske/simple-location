<?php
// Overrides Timezone for a Post
add_action( 'init', array( 'Post_Timezone', 'init' ) );

class Post_Timezone {
	public static function init() {
		add_filter( 'get_the_date', array( 'Post_Timezone', 'get_the_date' ), 12, 2 );
		add_filter( 'get_the_time', array( 'Post_Timezone', 'get_the_time' ), 12, 2 );
		add_filter( 'get_the_modified_date', array( 'Post_Timezone', 'get_the_date' ), 12, 2 );
		add_filter( 'get_the_modified_time', array( 'Post_Timezone', 'get_the_time' ), 12, 2 );
		add_action( 'post_submitbox_misc_actions', array( 'Post_Timezone', 'post_submitbox' ) );
		add_action( 'save_post', array( 'Post_Timezone', 'postbox_save_post_meta' ) );
		add_action( 'after_micropub', array( 'Post_Timezone', 'after_micropub' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( 'Post_Timezone', 'enqueue' ) );

	}

	public static function enqueue() {
		wp_enqueue_script(
			'jstz',
			plugins_url( 'js/jstz.min.js', dirname( __FILE__ ) ),
			array(),
			Simple_Location_Plugin::$version
		);
	}


	public static function after_micropub( $input, $args ) {
		if ( $args && array_key_exists( 'timezone', $args ) ) {
			update_post_meta( $args['ID'], 'geo_timezone', $args['timezone'] );
		}
	}

	public static function post_submitbox() {
		global $post;
		wp_nonce_field( 'timezone_override_metabox', 'timezone_override_nonce' );
		$timezone = get_post_meta( $post->ID, 'geo_timezone', true );
		if ( ! $timezone ) {
			$timezone = get_post_meta( $post->ID, '_timezone', true );
			if ( $timezone ) {
				update_post_meta( $post->ID, 'geo_timezone', true );
				delete_post_meta( $post->ID, '_timezone' );
			}
			if ( ! $timezone ) {
				$timezone = get_option( 'timezone_string' );
				if ( ! $timezone ) {
					$timezone = get_option( 'gmt_offset', 0 );
					if ( 0 < (int) $timezone ) {
						$timezone = 'UTC+' . $timezone;
					} else {
						$timezone = 'UTC' . $timezone;
					}
				}
			}
		}
?>
		<div class="misc-pub-section misc-pub-timezone">
		<span class="dashicons dashicons-clock" id="timezone-browser" title="<?php _e( 'Set Local Timezone', 'simple-location' );?>"></span>
			<label for="post-timezone"><?php _e( 'Timezone:', 'simple-location' ); ?></label>
			<span id="post-timezone-label">
			<?php
			if ( $timezone ) {
				echo $timezone; }
?>
</span>
			<a href="#post_timezone" class="edit-post-timezone hide-if-no-js" role="button"><span aria-hidden="true">Edit</span> <span class="screen-reader-text">Override Timezone</span></a>
		<br />
<div id="post-timezone-select" class="hide-if-js">
		<input type="hidden" name="hidden_post_timezone" id="hidden_post_timezone" value="<?php echo $timezone; ?>" />
		<input type="hidden" name="timezone_default" id="timezone_default" value="<?php echo get_option( 'timezone_string' ); ?>" />
		<select name="post_timezone" id="post-timezone" width="90%">
		<?php
			echo wp_timezone_choice( $timezone );
			echo '</select>';
?>
<br />
		<a href="#post_timezone" class="save-post-timezone hide-if-no-js button">OK</a>
		<a href="#post_timezone" class="cancel-post-timezone hide-if-no-js button-cancel">Cancel</a>
</div>
</div>
<?php
	}

	/* Save the post timezone metadata. */
	public static function postbox_save_post_meta( $post_id ) {
		/*
		* We need to verify this came from our screen and with proper authorization,
		* because the save_post action can be triggered at other times.
		*/
		if ( ! isset( $_POST['timezone_override_nonce'] ) ) {
			return;
		}
		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['timezone_override_nonce'], 'timezone_override_metabox' ) ) {
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
		if ( isset( $_POST['post_timezone'] ) ) {
			$tzlist = DateTimeZone::listIdentifiers();
			if ( get_option( 'timezone_string' ) !== $_POST['post_timezone'] ) {
				// For now protect against non-standard timezones
				if ( in_array( $_POST['post_timezone'], $tzlist, true ) ) {
					update_post_meta( $post_id, 'geo_timezone', $_POST['post_timezone'] );

				}
				return;
			} else {
				delete_post_meta( $post_id, 'geo_timezone' );
			}
		}
	}


	public static function get_the_date( $the_date, $d = '', $post = null ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return $the_date;
		}
		$timezone = get_post_meta( $post->ID, 'geo_timezone', true );
		if ( ! $timezone ) {
			$timezone = get_post_meta( $post->ID, '_timezone', true );
			if ( ! $timezone ) {
				return $the_date;
			}
		}
		if ( 1 === strlen( $timezone ) ) {
			// Something Got Set Wrong
			delete_post_meta( $post->ID, 'geo_timezone' );
			return $the_time;
		}

		if ( '' === $d ) {
			$d = get_option( 'date_format' );
		}
		// For now disable functionality if manual offset
		if ( false !== stripos( $timezone, 'UTC' ) && 'UTC' !== $timezone ) {
			return $the_date;
		}
		$datetime = new DateTime( $post->post_date_gmt, new DateTimeZone( 'GMT' ) );
		$datetime->setTimezone( new DateTimeZone( $timezone ) );
		return $datetime->format( $d );
	}

	public static function get_the_time( $the_time, $d = '', $post = null ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return $the_time;
		}
		$timezone = get_post_meta( $post->ID, 'geo_timezone', true );
		// For now disable with manual offset
		if ( false !== stripos( $timezone, 'UTC' ) && 'UTC' !== $timezone ) {
			return $the_time;
		}
		if ( ! $timezone ) {
			$timezone = get_post_meta( $post->ID, '_timezone', true );
			if ( ! $timezone ) {
				return $the_time;
			}
		}
		if ( 1 === strlen( $timezone ) ) {
			// Something Got Set Wrong
			delete_post_meta( $post->ID, 'geo_timezone' );
			return $the_time;
		}
		if ( '' === $d ) {
			$d = get_option( 'time_format' );
		}
		$datetime = new DateTime( $post->post_date_gmt, new DateTimeZone( 'GMT' ) );
		$datetime->setTimezone( new DateTimeZone( $timezone ) );
		$the_time = $datetime->format( $d );
		return $the_time;
	}

} // End Class

<?php
// Overrides Timezone for a Post
add_action( 'init' , array( 'post_timezone', 'init' ) );

class post_timezone {
	public static function init() {
		add_filter( 'get_the_date', array( 'post_timezone', 'get_the_date' ), 12, 2 );
		add_filter( 'get_the_time', array( 'post_timezone', 'get_the_time' ), 12, 2 );
		add_filter( 'get_the_modified_date' , array( 'post_timezone', 'get_the_date' ), 12, 2 );
		add_filter( 'get_the_modified_time' , array( 'post_timezone', 'get_the_time' )
		, 12, 2);
		add_action( 'post_submitbox_misc_actions', array( 'post_timezone', 'post_submitbox' ) );
		add_action( 'save_post', array( 'post_timezone', 'postbox_save_post_meta' ) );
	}

	public static function post_submitbox() {
		global $post;
		if ( get_post_type( $post ) == 'post' ) {
			echo '<div class="misc-pub-section misc-pub-section-last">';
			wp_nonce_field( 'timezone_override_metabox', 'timezone_override_nonce' );
			$tzlist = DateTimeZone::listIdentifiers();
			?>
			<p>
			<label for="override_timezone"><?php _e( 'Override Default Timezone', 'simple-location' ); ?></label>
		<input type="checkbox" name="override_timezone" id="override_timezone" <?php if ( get_post_meta( $post->ID, '_timezone', true ) ) { echo 'checked="checked'; } ?>" />
		 <br />
		<select name="timezone" width="90%">
			<?php
			$timezone = get_post_meta( $post->ID, '_timezone', true );
			if ( ! $timezone ) {
				$timezone = get_option( 'timezone_string' );
			}
			foreach ( $tzlist as $tz ) {
				echo '<option value="' . $tz . '"';
				if ( $timezone == $tz ) {
					echo ' selected';
				}
				echo '>' . $tz . '</option>';
			}
			echo '</select>';

			echo '</div>';
		}

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
		if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}
		if ( $_POST['override_timezone'] ) {
			update_post_meta( $post_id, '_timezone', $_POST['timezone'] );
		} else {
			delete_post_meta( $post_id, '_timezone' );
		}
	}


	public static function get_the_date($the_date, $d = '' , $post = null) {
		$post = get_post( $post );
		if ( ! $post ) {
			return $the_date;
		}
		$timezone = get_post_meta( $post->ID, '_timezone', true );
		if ( ! $timezone ) {
			return $the_date;
		}
		if ( '' == $d ) {
			$d = get_option( 'date_format' );
		}
		$datetime = new DateTime( $post->post_date_gmt, new DateTimeZone( 'GMT' ) );
		$datetime->setTimezone( new DateTimeZone( $timezone ) );
		return $datetime->format( $d );
	}

	public static function get_the_time($the_time, $d = '' , $post = null) {
		$post = get_post( $post );
		if ( ! $post ) {
			return $the_time;
		}
		$timezone = get_post_meta( $post->ID, '_timezone', true );
		if ( ! $timezone ) {
			return $the_time;
		}
		if ( '' == $d ) {
			$d = get_option( 'time_format' );
		}
		$datetime = new DateTime( $post->post_date_gmt, new DateTimeZone( 'GMT' ) );
		$datetime->setTimezone( new DateTimeZone( $timezone ) );
		$the_time = $datetime->format( $d );
		return $the_time;
	}

} // End Class


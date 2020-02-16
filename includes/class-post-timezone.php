<?php
// Overrides Timezone for a Post
add_action( 'init', array( 'Post_Timezone', 'init' ) );

class Post_Timezone {
	public static function init() {
		$cls = get_called_class();
		add_filter( 'get_the_date', array( $cls, 'get_the_date' ), 12, 3 );
		add_filter( 'get_the_time', array( $cls, 'get_the_time' ), 12, 3 );
		add_filter( 'get_the_modified_date', array( $cls, 'get_the_modified_date' ), 12, 3 );
		add_filter( 'get_the_modified_time', array( $cls, 'get_the_modified_time' ), 12, 3 );
		add_filter( 'get_comment_date', array( $cls, 'get_comment_date' ), 12, 3 );
		add_filter( 'get_comment_time', array( $cls, 'get_comment_time' ), 12, 5 );
		add_action( 'simple_location_sidebox', array( $cls, 'post_submitbox' ) );
		add_action( 'save_post', array( $cls, 'postbox_save_post_meta' ) );
		add_action( 'after_micropub', array( $cls, 'after_micropub' ), 10, 2 );
		add_filter( 'rest_prepare_post', array( $cls, 'rest_prepare_post' ), 10, 3 );
	}

	public static function rest_prepare_post( $response, $post, $request ) {
		$data                 = $response->get_data();
		$data['date']         = self::get_the_date( $data['date_gmt'], DATE_W3C, $post );
		$data['modified']     = self::get_the_modified_date( $data['date_gmt'], DATE_W3C, $post );
		$date_gmt             = new DateTime( $data['date_gmt'], new DatetimeZone( 'GMT' ) );
		$data['date_gmt']     = $date_gmt->format( DATE_W3C );
		$modified_gmt         = new DateTime( $data['modified_gmt'], new DatetimeZone( 'GMT' ) );
		$data['modified_gmt'] = $modified_gmt->format( DATE_W3C );
		$response->set_data( $data );
		return $response;
	}

	public static function after_micropub( $input, $args ) {
		if ( isset( $args['meta_input'] ) ) {
			if ( isset( $args['meta_input']['geo_latitude'] ) ) {
				update_post_meta( $args['ID'], 'geo_timezone', (string) Loc_Timezone::timezone_for_location( $args['meta_input']['geo_latitude'], $args['meta_input']['geo_longitude'] ) );
			}
			return;
		}
		if ( $args && array_key_exists( 'timezone', $args ) ) {
			update_post_meta( $args['ID'], 'geo_timezone', $args['timezone'] );
		}

	}

	public static function wp_timezone_choice( $selected_zone, $locale = null ) {
		static $mo_loaded = false, $locale_loaded = null;

		$continents = array( 'Africa', 'America', 'Antarctica', 'Arctic', 'Asia', 'Atlantic', 'Australia', 'Europe', 'Indian', 'Pacific' );

		// Load translations for continents and cities.
		if ( ! $mo_loaded || $locale !== $locale_loaded ) {
			$locale_loaded = $locale ? $locale : get_locale();
			$mofile        = WP_LANG_DIR . '/continents-cities-' . $locale_loaded . '.mo';
			unload_textdomain( 'continents-cities' );
			load_textdomain( 'continents-cities', $mofile );
			$mo_loaded = true;
		}

		$zonen = array();
		foreach ( timezone_identifiers_list() as $zone ) {
			$zone = explode( '/', $zone );
			if ( ! in_array( $zone[0], $continents, true ) ) {
				continue;
			}

			// This determines what gets set and translated - we don't translate Etc/* strings here, they are done later
			$exists    = array(
				0 => ( isset( $zone[0] ) && $zone[0] ),
				1 => ( isset( $zone[1] ) && $zone[1] ),
				2 => ( isset( $zone[2] ) && $zone[2] ),
			);
			$exists[3] = ( $exists[0] && 'Etc' !== $zone[0] );
			$exists[4] = ( $exists[1] && $exists[3] );
			$exists[5] = ( $exists[2] && $exists[3] );

		        // phpcs:disable WordPress.WP.I18n.LowLevelTranslationFunction,WordPress.WP.I18n.NonSingularStringLiteralText
			$zonen[] = array(
				'continent'   => ( $exists[0] ? $zone[0] : '' ),
				'city'        => ( $exists[1] ? $zone[1] : '' ),
				'subcity'     => ( $exists[2] ? $zone[2] : '' ),
				't_continent' => ( $exists[3] ? translate( str_replace( '_', ' ', $zone[0] ), 'continents-cities' ) : '' ), // phpcs:ignore
				't_city'      => ( $exists[4] ? translate( str_replace( '_', ' ', $zone[1] ), 'continents-cities' ) : '' ), // phpcs:ignore
				't_subcity'   => ( $exists[5] ? translate( str_replace( '_', ' ', $zone[2] ), 'continents-cities' ) : '' ), // phpcs:ignore
			);
        		// phpcs:enable
		}
		usort( $zonen, '_wp_timezone_choice_usort_callback' );

		$structure = array();

		if ( empty( $selected_zone ) ) {
			$structure[] = '<option selected="selected" value="">' . __( 'Select a city', 'default' ) . '</option>';
		}

		foreach ( $zonen as $key => $zone ) {
			// Build value in an array to join later
			$value = array( $zone['continent'] );

			if ( empty( $zone['city'] ) ) {
				// It's at the continent level (generally won't happen)
				$display = $zone['t_continent'];
			} else {
				// It's inside a continent group
				// Continent optgroup
				if ( ! isset( $zonen[ $key - 1 ] ) || $zonen[ $key - 1 ]['continent'] !== $zone['continent'] ) {
					$label       = $zone['t_continent'];
					$structure[] = '<optgroup label="' . esc_attr( $label ) . '">';
				}

				// Add the city to the value
				$value[] = $zone['city'];

				$display = $zone['t_city'];
				if ( ! empty( $zone['subcity'] ) ) {
					// Add the subcity to the value
					$value[]  = $zone['subcity'];
					$display .= ' - ' . $zone['t_subcity'];
				}
			}

			// Build the value
			$value    = join( '/', $value );
			$selected = '';
			if ( $value === $selected_zone ) {
				$selected = 'selected="selected" ';
			}
			$structure[] = '<option ' . $selected . 'value="' . esc_attr( $value ) . '">' . esc_html( $display ) . '</option>';

			// Close continent optgroup
			if ( ! empty( $zone['city'] ) && ( ! isset( $zonen[ $key + 1 ] ) || ( isset( $zonen[ $key + 1 ] ) && $zonen[ $key + 1 ]['continent'] !== $zone['continent'] ) ) ) {
				$structure[] = '</optgroup>';
			}
		}
		// Older versions of PHP didn't handle offsets as timezones well so hide this feature
		if ( version_compare( PHP_VERSION, '5.4', '>' ) ) {
			// Do UTC
			$structure[] = '<optgroup label="' . esc_attr__( 'UTC', 'default' ) . '">';
			$selected    = '';
			if ( 'UTC' === $selected_zone ) {
				$selected = 'selected="selected" ';
			}
			$structure[] = '<option ' . $selected . 'value="' . esc_attr( 'UTC' ) . '">' . __( 'UTC', 'default' ) . '</option>';
			$structure[] = '</optgroup>';

			// Do manual UTC offsets
			$structure[]  = '<optgroup label="' . esc_attr__( 'Manual Offsets', 'default' ) . '">';
			$offset_range = array(
				-12,
				-11.5,
				-11,
				-10.5,
				-10,
				-9.5,
				-9,
				-8.5,
				-8,
				-7.5,
				-7,
				-6.5,
				-6,
				-5.5,
				-5,
				-4.5,
				-4,
				-3.5,
				-3,
				-2.5,
				-2,
				-1.5,
				-1,
				-0.5,
				0,
				0.5,
				1,
				1.5,
				2,
				2.5,
				3,
				3.5,
				4,
				4.5,
				5,
				5.5,
				5.75,
				6,
				6.5,
				7,
				7.5,
				8,
				8.5,
				8.75,
				9,
				9.5,
				10,
				10.5,
				11,
				11.5,
				12,
				12.75,
				13,
				13.75,
				14,
			);
			foreach ( $offset_range as $offset ) {
				if ( 0 <= $offset ) {
					$offset_name = '+' . $offset;
				} else {
					$offset_name = (string) $offset;
				}

				$offset_name  = str_replace( array( '.25', '.5', '.75' ), array( ':15', ':30', ':45' ), $offset_name );
				$offset_value = $offset_name;
				$offset_name  = 'UTC' . $offset_name;
				$selected     = '';
				if ( $offset_value === $selected_zone ) {
					$selected = 'selected="selected" ';
				}
				$structure[] = '<option ' . $selected . 'value="' . esc_attr( $offset_value ) . '">' . esc_html( $offset_name ) . '</option>';

			}
			$structure[] = '</optgroup>';
		}
		return join( "\n", $structure );
	}


	public static function post_submitbox( $screen ) {
		if ( in_array( $screen, array( 'comment', 'nav-menu' ), true ) ) {
			return;
		}
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
				$timezone = wp_timezone_string();
			}
		}
		?>
		<div class="location-section location-section-timezone">
			<span class="dashicons-before dashicons-clock" id="timezone-browser" title="<?php esc_html_e( 'Set Local Timezone', 'simple-location' ); ?>"> <?php esc_html_e( 'Timezone:', 'simple-location' ); ?></span>
				<span id="post-timezone-label">
				<?php
				if ( $timezone ) {
					echo esc_html( $timezone ); }
				?>
			</span>
			<a href="#post_timezone" class="edit-post-timezone hide-if-no-js" role="button"><span aria-hidden="true">Edit</span> <span class="screen-reader-text">Override Timezone</span></a>

			<div id="post-timezone-select" class="hide-if-js">
				<input type="hidden" name="hidden_post_timezone" id="hidden_post_timezone" value="<?php echo esc_html( $timezone ); ?>" />
				<input type="hidden" name="timezone_default" id="timezone_default" value="<?php echo esc_attr( wp_timezone_string() ); ?>" />
				<select name="post_timezone" id="post-timezone" width="90%">
				<?php
					echo self::wp_timezone_choice( $timezone ); // phpcs:ignore
					echo '</select>';
				?>

				<p>
					<a href="#post_timezone" class="save-post-timezone hide-if-no-js button">OK</a>
					<a href="#post_timezone" class="cancel-post-timezone hide-if-no-js button-cancel">Cancel</a>
				</p>
			</div><!-- #post-timezone-select -->
		</div><!-- .location-section -->
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
			if ( wp_timezone_string() !== $_POST['post_timezone'] ) {
				update_post_meta( $post_id, 'geo_timezone', $_POST['post_timezone'] );
				return;
			} else {
				delete_post_meta( $post_id, 'geo_timezone' );
			}
		}
	}

	public static function get_timezone( $object = null ) {
		if ( ! $object ) {
			$object = get_post();
		}
		// If numeric assume post_ID
		if ( is_numeric( $object ) ) {
			$object = get_post( $object );
		}
		if ( $object instanceof WP_Post ) {
			$type = 'post';
			$id   = $object->ID;
		}
		if ( $object instanceof WP_Comment ) {
			$id   = $object->comment_post_ID;
			$type = 'post';
		}
		if ( $object instanceof WP_Term ) {
			$id   = $object->term_id;
			$type = 'term';
		}
		if ( $object instanceof WP_User ) {
			$id   = $object->ID;
			$type = 'user';
		}

		$timezone = wp_cache_get( $id, $type . '_timezone' );
		if ( false !== $timezone ) {
			return $timezone;
		}

		$timezone = get_metadata( $type, $id, 'geo_timezone', true );
		if ( ! $timezone ) {
			return null;
		}
		// Ensure timezone is a string
		if ( ! is_string( $timezone ) ) {
			$timezone = strval( $timezone );
		}
		// For now disable with manual offset
		if ( false !== stripos( $timezone, 'UTC' ) && 'UTC' !== $timezone ) {
			wp_cache_set( $id, null, $type . '_timezone', DAY_IN_SECONDS );
			return null;
		}
		if ( 1 === strlen( $timezone ) ) {
			// Something Got Set Wrong
			delete_metadata( $type, $id, 'geo_timezone' );
			wp_cache_set( $id, null, $type . '_timezone', DAY_IN_SECONDS );
			return null;
		}

		$timezone = new DateTimeZone( $timezone );
		wp_cache_set( $id, $timezone, $type . '_timezone', DAY_IN_SECONDS );
		return $timezone;
	}


	public static function get_the_date( $the_date, $d = '', $post = null ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return $the_date;
		}
		$timezone = self::get_timezone( $post );
		if ( is_null( $timezone ) ) {
			return $the_date;
		}

		if ( '' === $d ) {
			$d = get_option( 'date_format' );
		}

		return wp_date( $d, get_post_timestamp( $post ), $timezone );
	}

	public static function get_the_time( $the_time, $d = '', $post = null ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return $the_time;
		}
		$timezone = self::get_timezone( $post );
		if ( is_null( $timezone ) ) {
			return $the_time;
		}
		if ( '' === $d ) {
			$d = get_option( 'time_format' );
		}
		return wp_date( $d, get_post_timestamp( $post ), $timezone );
	}

	public static function get_the_modified_date( $the_date, $d = '', $post = null ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return $the_date;
		}
		$timezone = self::get_timezone( $post );
		if ( is_null( $timezone ) ) {
			return $the_date;
		}

		if ( '' === $d ) {
			$d = get_option( 'date_format' );
		}

		return wp_date( $d, get_post_timestamp( $post, 'modified' ), $timezone );
	}

	public static function get_the_modified_time( $the_time, $d = '', $post = null ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return $the_time;
		}
		$timezone = self::get_timezone( $post );
		if ( is_null( $timezone ) ) {
			return $the_time;
		}
		if ( '' === $d ) {
			$d = get_option( 'time_format' );
		}
		return wp_date( $d, get_post_timestamp( $post, 'modified' ), $timezone );
	}


	public static function get_comment_date( $date, $d, $comment ) {
		if ( '' === $d ) {
			$d = get_option( 'date_format' );
		}
		$timezone = self::get_timezone( $comment );
		if ( is_null( $timezone ) ) {
			return $date;
		}
		return wp_date( $d, get_comment_timestamp( $comment ), $timezone );
	}

	public static function get_comment_time( $date, $d, $gmt, $translate, $comment ) {
		if ( '' === $d ) {
			$d = get_option( 'time_format' );
		}
		$timezone = self::get_timezone( $comment );
		if ( is_null( $timezone ) ) {
			return $date;
		}
		return wp_date( $d, get_comment_timestamp( $comment ), $timezone );
	}

} // End Class

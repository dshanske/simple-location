<?php
/**
 * Geographical Metadata
 *
 * Registers geographic metadata and supplies functions to assist in manipulating it.
 *
 * @package Simple Location
 */

add_action( 'init', array( 'WP_Geo_Data', 'init' ), 1 );

// Jetpack added Location support in 2017 remove it due conflict https://github.com/Automattic/jetpack/pull/9573 which created conflict.
add_filter( 'jetpack_tools_to_include', array( 'WP_Geo_Data', 'jetpack_remove' ), 11 );

/**
 * Handles Geo Functionality for WordPress objects.
 *
 * @since 1.0.0
 */
class WP_Geo_Data {


	/**
	 * Geo Data Initialization Function.
	 *
	 * Meant to be attached to init hook. Sets up all the geodata enhancements.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		$cls = get_called_class();
		self::register_meta();
		add_filter( 'query_vars', array( $cls, 'query_var' ) );
		add_filter( 'template_include', array( $cls, 'map_archive_template' ) );

		add_action( 'pre_get_posts', array( $cls, 'remove_maps_pagination' ) );
		add_action( 'pre_get_posts', array( $cls, 'filter_location_posts' ) );
		add_action( 'pre_get_comments', array( $cls, 'pre_get_comments' ) );

		add_action( 'wp_generate_attachment_metadata', array( $cls, 'attachment' ), 20, 2 );
		add_filter( 'attachment_fields_to_edit', array( $cls, 'attachment_fields_to_edit' ), 10, 2 );
		add_action( 'attachment_submitbox_misc_actions', array( $cls, 'attachment_submitbox_metadata' ), 12 );

		self::rewrite();

		add_action( 'rss2_ns', array( $cls, 'georss_namespace' ) );
		add_action( 'atom_ns', array( $cls, 'georss_namespace' ) );
		add_action( 'rdf_ns', array( $cls, 'georss_namespace' ) );

		add_action( 'rss_item', array( $cls, 'georss_item' ) );
		add_action( 'rss2_item', array( $cls, 'georss_item' ) );
		add_action( 'atom_entry', array( $cls, 'georss_item' ) );
		add_action( 'rdf_item', array( $cls, 'georss_item' ) );
		add_action( 'json_feed_item', array( $cls, 'json_feed_item' ), 10, 2 );
		add_action( 'wp_head', array( $cls, 'meta_tags' ) );

		add_action( 'rest_api_init', array( $cls, 'rest_location' ) );

		// Add Dropdown.
		add_action( 'restrict_manage_posts', array( $cls, 'geo_posts_dropdown' ), 12, 2 );
		add_action( 'restrict_manage_comments', array( $cls, 'geo_comments_dropdown' ), 12 );
		add_filter( 'manage_posts_columns', array( $cls, 'add_location_admin_column' ) );
		add_action( 'manage_posts_custom_column', array( $cls, 'manage_location_admin_column' ), 10, 2 );

		add_filter( 'bulk_actions-edit-post', array( $cls, 'register_bulk_edit_location' ), 10 );
		add_filter( 'handle_bulk_actions-edit-post', array( $cls, 'handle_bulk_edit_location' ), 10, 3 );
		add_action( 'admin_notices', array( $cls, 'bulk_action_admin_notices' ) );

		// Add the Same Post Type Support JetPack uses.
		add_post_type_support( 'post', 'geo-location' );
		add_post_type_support( 'page', 'geo-location' );
		add_post_type_support( 'attachment', 'geo-location' );

		add_filter( 'rest_prepare_post', array( $cls, 'rest_prepare_post' ), 10, 3 );
		add_filter( 'rest_prepare_comment', array( $cls, 'rest_prepare_comment' ), 10, 3 );
		add_filter( 'rest_prepare_user', array( $cls, 'rest_prepare_user' ), 10, 3 );

		add_filter( 'map_meta_cap', array( $cls, 'map_meta_cap' ), 1, 4 );

	}


	/**
	 * Removes the Pagination from the Map Archive Page.
	 *
	 * Filter query variables.
	 *
	 * @param WP_Query $query Query Object.
	 *
	 * @since 4.0.0
	 */
	public static function remove_maps_pagination( $query ) {
		if ( ! array_key_exists( 'map', $query->query_vars ) || ! $query->is_main_query() ) {
			return;
		}
		$query->set( 'meta_query', array( self::filter_geo_query( 'map' ) ) );
		$query->set( 'posts_per_page', SLOC_PER_PAGE );
		$query->set( 'order', 'ASC' );
	}

	/**
	 * Routes Requests for the Query Variable Map to a Special Template.
	 *
	 * Routes Requests for the Query Varible Map to a Special Template.
	 *
	 * @param string $original_template The template that would normally be returned.
	 * @return string $return The original template or the map archive
	 * @since 4.0.0
	 */
	public static function map_archive_template( $original_template ) {
		if ( false === get_query_var( 'map', false ) ) {
			return $original_template;
		}
		if ( ! is_archive() ) {
			return $original_template;
		}
		$template_name = 'map-archive.php';
		$look          = array(
			get_theme_file_path(),
			plugin_dir_path( __DIR__ ) . 'templates/',
		);

		foreach ( $look as $l ) {
			if ( file_exists( $l . $template_name ) ) {
				return $l . $template_name;
			}
		}
		return $original_template;
	}

	/**
	 * Get a list of post IDs in the current query.
	 *
	 * This gets a list of all the IDs in the current query.
	 *
	 * @return $post_ids array of post ids.
	 *
	 * @since 1.0.0
	 */
	public static function location_list() {
		global $wp_query;
		$post_ids = wp_list_pluck( $wp_query->posts, 'ID' );
		return $post_ids;
	}

	/**
	 * Get a list of all the posts with a public location
	 *
	 * In order to generate an archive map.
	 *
	 * @since 1.0.0
	 */
	public static function get_archive_public_location_list() {
		global $wp_query;
		$locations = array();
		if ( empty( $wp_query->posts ) ) {
			return '';
		}
		foreach ( $wp_query->posts as $post ) {
			$location = self::get_geodata( $post, false );
			if ( 'public' === $location['visibility'] && array_key_exists( 'latitude', $location ) ) {
				$locations[] = array_values(
					wp_array_slice_assoc(
						$location,
						array(
							'latitude',
							'longitude',
						)
					)
				);
			}
		}
		return $locations;
	}


	/**
	 * Register Bulk Edit of Location.
	 *
	 * Adds options to bulk edit location.
	 *
	 * @param array $actions List of Registered Bulk Edit Actions.
	 *
	 * @since 1.0.0
	 */
	public static function register_bulk_edit_location( $actions ) {
		$actions['location_public']  = __( 'Public Location', 'simple-location' );
		$actions['location_private'] = __( 'Private Location', 'simple-location' );
		$actions['lookup_location']  = __( 'Lookup Location', 'simple-location' );
		return $actions;
	}

	/**
	 * Add Notice when Bulk Action is run with results.
	 */
	public static function bulk_action_admin_notices() {
		if ( isset( $_REQUEST['bulk_lookup_location_count'] ) ) {
			$count = intval( $_REQUEST['bulk_lookup_location_count'] );
			if ( 0 === $count ) {
				$string = __( 'None of the Posts Were Updated.', 'simple-location' );
			} else {
				/* translators: Count of posts updated. */
				$string = sprintf( _n( 'Updated %s post.', 'Updated %s posts.', $count, 'simple-location' ), $count );
			}
			printf( '<div id="message" class="updated fade">%1$s</div>', esc_html( $string ) );
		}
	}

	/**
	 * Bulk Edit Location Handler.
	 *
	 * Allows for Bulk Updating the location visibility.
	 *
	 * @param string $redirect_to Where to redirect once complete.
	 * @param string $doaction The Action Being Requested.
	 * @param array  $post_ids The list of Post IDs to act on.
	 * @return string $redirect_to Return the Redirect_To Parameter
	 * @since 1.0.0
	 */
	public static function handle_bulk_edit_location( $redirect_to, $doaction, $post_ids ) {
		if ( in_array( $doaction, array( 'location_public', 'location_private' ), true ) ) {
			$visibility = str_replace( 'location_', '', $doaction );
			foreach ( $post_ids as $post_id ) {
				self::set_visibility( 'post', $post_id, $visibility );
			}
		}
		if ( empty( $post_ids ) ) {
			return $redirect_to;
		}
		if ( 'lookup_location' === $doaction ) {
			$return = array();
			foreach ( $post_ids as $post_id ) {
				$update  = false;
				$post    = get_post( $post_id );
				$geodata = self::get_geodata( $post, true );
				if ( ! $geodata ) {
					$update      = true;
					$geolocation = Loc_Config::geolocation_provider();
					if ( ! is_object( $geolocation ) ) {
						return $redirect_to;
					}
					if ( ! $geolocation->background() || ! $post->post_author ) {
						return $redirect_to;
					}
					$geolocation->set_user( $post->post_author );
					$geolocation->retrieve( get_post_datetime( $post ) );
					$geodata = $geolocation->get();
				}

				if ( is_wp_error( $geodata ) ) {
					$redirect_to = add_query_arg( 'lookup_location_error', $geodata->get_error_message() );
					return $geodata;
				} elseif ( ! empty( $geodata ) ) {
					// Default for this is to set the location to private if it is not already set.
					if ( false === get_post_meta( $post_id, 'geo_public', true ) ) {
						$geodata['visibility'] = 'private';
					}
					// Determine if we need to look up the location again.
					$term = Location_Taxonomy::get_location_taxonomy( $post );
					if ( empty( $term ) || ! array_key_exists( 'address', $geodata ) ) {
						$reverse = Loc_Config::geo_provider();
						$reverse->set( $geodata['latitude'], $geodata['longitude'] );
						$reverse_adr = $reverse->reverse_lookup();
						if ( ! is_wp_error( $reverse_adr ) ) {
							$update = true;
							$term   = Location_Taxonomy::get_location( $reverse_adr );
							Location_Taxonomy::set_location( $post_id, $term );
							$zone = Location_Zones::in_zone( $geodata['latitude'], $geodata['longitude'] );
							if ( ! empty( $zone ) ) {
								$geodata['address'] = $zone;
							} elseif ( ! array_key_exists( 'address', $geodata ) && array_key_exists( 'display-name', $reverse_adr ) ) {
								$geodata['address'] = $reverse_adr['display-name'];
							}
						}
					}
					if ( ! array_key_exists( 'weather', $geodata ) ) {
						$weather = Loc_Config::weather_provider();
						$weather->set( $geodata['latitude'], $geodata['longitude'] );
						$conditions = $weather->get_conditions( get_post_timestamp( $post ) );
						if ( ! empty( $conditions ) && ! is_wp_error( $conditions ) ) {
							$update             = true;
							$geodata['weather'] = $conditions;
						}
					}
					if ( true === $update ) {
						$return[] = $post_id;
						self::set_geodata( $post, $geodata );
					}
				}
			}
			$redirect_to = add_query_arg( 'bulk_lookup_location_count', count( $return ), $redirect_to );
		}
		return $redirect_to;
	}




	/**
	 * This registers an admin column in the post edit screen.
	 *
	 * Allows a column for location visibility.
	 *
	 * @param array $columns Columns passed through from filter.
	 * @return array $columns Column with extra property added.
	 * @since 1.0.0
	 */
	public static function add_location_admin_column( $columns ) {
		if ( array_key_exists( 'post_type', $_GET ) && ! in_array( $_GET['post_type'], Loc_Metabox::screens() ) ) {
			return $columns;
		}
		$columns['location'] = __( 'Location', 'simple-location' );
		return $columns;
	}

	/**
	 * Returns location visibility for the post edit screen column.
	 *
	 * Gets the location visibility property and echos it.
	 *
	 * @param string $column Which column is being rendered.
	 * @param int    $post_id The post id for the row.
	 * @since 1.0.0
	 */
	public static function manage_location_admin_column( $column, $post_id ) {
		if ( 'location' === $column ) {
			$geo_public = self::geo_public();
			$location   = self::get_geodata( $post_id );
			if ( $location ) {
				echo esc_html( $geo_public[ $location['visibility'] ] );
			} else {
				esc_html_e( 'None', 'simple-location' );
			}
		}
	}


	/**
	 * Removes JetPacks Geolocation Features as they conflict with this plugin.
	 *
	 * Does an array search to find and remove geo-location.php.
	 *
	 * @param array $tools The tools being loaded by JetPack.
	 * @return array $tools The tools array less the geolocation module.
	 * @since 1.0.0
	 */
	public static function jetpack_remove( $tools ) {
		$index = array_search( 'geo-location.php', $tools, true );
		if ( false !== $index ) {
			unset( $tools[ $index ] );
		}
		return $tools;
	}



	/**
	 * Returns the strings for the visibility parameters.
	 *
	 * Returns translated strings for the geo_public property.
	 *
	 * @return array $return Array of possible geo_public values.
	 * @since 1.0.0
	 */
	public static function geo_public() {
		return array(
			'private'   => esc_html__( 'Private', 'simple-location' ),
			'public'    => esc_html__( 'Public', 'simple-location' ),
			'protected' => esc_html__( 'Protected', 'simple-location' ),
		);
	}


	/**
	 * Sets visibility property on any metadata capable object.
	 *
	 * Allows visibility to be set on posts, comments, terms, etc.
	 *
	 * @param string $type Object Type.
	 * @param int    $id Post ID, Comment ID, User ID, etc.
	 * @param string $status Visibility to be set.
	 * @since 1.0.0
	 */
	public static function set_visibility( $type, $id, $status ) {
		switch ( $status ) {
			case '0':
			case 'private':
				$status = '0';
				break;
			case '1':
			case 'public':
				$status = '1';
				break;
			case '2':
			case 'protected':
				$status = '2';
				break;
			default:
				delete_metadata( $type, $id, 'geo_public' );
				return false;
		}
		update_metadata( $type, $id, 'geo_public', $status );
	}

	/**
	 * Retrieves visibility property on any metadata capable object.
	 *
	 * Gets visibility from posts, comments, terms, etc.
	 *
	 * @param string $type Object Type.
	 * @param int    $id Post ID, Comment ID, User ID, etc.
	 * @return false|string $status Visibility.
	 * @since 1.0.0
	 */
	public static function get_visibility( $type = null, $id = null ) {
		$status = false;
		if ( ! is_null( $type ) && ! is_null( $id ) ) {
			$status = get_metadata( $type, $id, 'geo_public', true );
		}
		if ( false === $status ) {
			$status = get_option( 'geo_public' );
		}
		switch ( $status ) {
			case '0':
			case 0:
			case 'private':
				return 'private';
			case '1':
			case 1:
			case 'public':
				return 'public';
			case '2':
			case 2:
			case 'protected':
				return 'protected';
			default:
				return false;
		}
	}

	/**
	 * KSES Option Filter
	 *
	 * @return array Option Filter for KSES
	 */
	public static function kses_option() {
		return array(
			'option' => array(
				'value'    => array(),
				'selected' => array(),
			),
		);
	}


	/**
	 * Offers a formatted list of visibility options for a select form object.
	 *
	 * Allows for echo or return.
	 *
	 * @param string  $public The value to set the select field to.
	 * @param boolean $echo True to echo, false to return.
	 * @return string $return Optional. Returns the created string.
	 * @since 1.0.0
	 */
	public static function geo_public_select( $public, $echo = false ) {
		$choices = self::geo_public();
		$return  = '';
		foreach ( $choices as $value => $text ) {
			$return .= sprintf( '<option value="%1s" %2s>%3s</option>', esc_attr( $value ), selected( $public, $value, false ), esc_html( $text ) );
		}
		if ( ! $echo ) {
			return $return;
		}
		echo wp_kses( $return, self::kses_option() );

	}


	/**
	 * Generates a dropdown
	 *
	 * Allows visibility to be filtered on post edit screen.
	 *
	 * @param string $post_type The post type slug.
	 * @param string $which     The location of the extra table nav markup:
	 *                          'top' or 'bottom' for WP_Posts_List_Table,
	 *                          'bar' for WP_Media_List_Table.
	 * @since 1.0.0
	 */
	public static function geo_posts_dropdown( $post_type, $which ) {
		if ( 'post' !== $post_type ) {
			return;
		}
		$selected = 'none';
		if ( isset( $_REQUEST['geo'] ) ) {
			$selected = sanitize_text_field( $_REQUEST['geo'] );
		}
		$list = array(
			'none'      => esc_html__( 'All Posts', 'simple-location' ),
			'all'       => esc_html__( 'With Location', 'simple-location' ),
			'private'   => esc_html__( 'Private', 'simple-location' ),
			'public'    => esc_html__( 'Public', 'simple-location' ),
			'protected' => esc_html__( 'Protected', 'simple-location' ),
		);
		echo '<select id="geo" name="geo">';
		foreach ( $list as $key => $value ) {
			echo wp_kses( sprintf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $selected, $key ), $value ), self::kses_option() );
		}
		echo '</select>';
	}


	/**
	 * Generates a comment dropdown.
	 *
	 * Allows visibility to be filtered on comment edit screen.
	 *
	 * @since 1.0.0
	 */
	public static function geo_comments_dropdown() {
		$selected = 'none';
		if ( isset( $_REQUEST['geo'] ) ) {
			$selected = sanitize_text_field( $_REQUEST['geo'] );
		}
		$list = array(
			'none'      => esc_html__( 'All Comments', 'simple-location' ),
			'all'       => esc_html__( 'With Location', 'simple-location' ),
			'private'   => esc_html__( 'Private', 'simple-location' ),
			'public'    => esc_html__( 'Public', 'simple-location' ),
			'protected' => esc_html__( 'Protected', 'simple-location' ),
		);
		echo '<select id="geo" name="geo">';
		foreach ( $list as $key => $value ) {
			echo wp_kses( sprintf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $selected, $key ), $value ), self::kses_option() );
		}
		echo '</select>';
	}


	/**
	 * Echos the georss namespace for RSS feeds
	 *
	 * Added to the RSS templates.
	 *
	 * @since 1.0.0
	 */
	public static function georss_namespace() {
		echo PHP_EOL . 'xmlns:georss="http://www.georss.org/georss" xmlns:geo="http://www.w3.org/2003/01/geo/wgs84_pos#" ';
	}


	/**
	 * Adds georss to an RSS feed.
	 *
	 * Adds georss to a single post on a RSS feed.
	 *
	 * @since 1.0.0
	 */
	public static function georss_item() {
		$geo = self::get_geodata();
		if ( ! $geo ) {
			return;
		}

		if ( ! isset( $geo['latitude'] ) || ! isset( $geo['longitude'] ) ) {
			return;
		}

		if ( empty( $geo['visibility'] ) || 'public' !== $geo['visibility'] ) {
			return;
		}

		$geo = array_map( 'esc_html', $geo );
		$geo = array_map( 'ent2ncr', $geo );

		printf( '\t<georss:point>%1$s %2$s</georss:point>\n', floatval( $geo['latitude'] ), floatval( $geo['longitude'] ) );
		printf( '\t\t<geo:lat>%1$s</geo:lat>\n', floatval( $geo['latitude'] ) );
		printf( '\t\t<geo:long>%1$s</geo:long>', floatval( $geo['longitude'] ) );
		if ( isset( $geo['address'] ) ) {
			printf( '\t\t<geo:featureName>%1$s</geo:featureName>', wp_kses_post( $geo['address'] ) );
		}
	}


	/**
	 * Adds geodata to a jsonfeed.
	 *
	 * Adds geodata in JSON format to a jsonfeed item.
	 *
	 * @param array   $feed_item A single post rendered for jsonfeed.
	 * @param WP_Post $post The post object.
	 * @return $feed_item The feed item with geojson added if applicable.
	 *
	 * @since 1.0.0
	 */
	public static function json_feed_item( $feed_item, $post ) {
		$geo = self::get_geodata( $post );
		if ( ! $geo ) {
			return $feed_item;
		}

		if ( empty( $geo['visibility'] ) || 'public' !== $geo['visibility'] ) {
			return $feed_item;
		}
		$json             = array(
			'type'       => 'Feature',
			'geometry'   => array(
				'type'        => 'Point',
				'coordinates' => array( floatval( $geo['longitude'] ), floatval( $geo['latitude'] ) ),
			),
			'properties' => array(
				'name' => sanitize_text_field( $geo['address'] ),
			),
		);
		$feed_item['geo'] = $json;
		return $feed_item;
	}


	/**
	 * Generates meta tags representing a location.
	 *
	 * Adds geo and ICBM meta tags to a single page.
	 *
	 * @since 1.0.0
	 */
	public static function meta_tags() {
		if ( ! is_single() ) {
			return;
		}
		$geo = self::get_geodata( get_the_id() );
		if ( ! $geo ) {
			return;
		}
		if ( empty( $geo['visibility'] ) || 'private' === $geo['visibility'] ) {
			return;
		}
		if ( isset( $geo['address'] ) ) {
			printf(
				'<meta name="geo.placename" content="%s" />' . PHP_EOL,
				esc_attr( $geo['address'] )
			);
		}
		if ( isset( $geo['latitude'] ) && 'protected' !== $geo['visibility'] ) {
			printf(
				'<meta name="geo.position" content="%s;%s" />' . PHP_EOL,
				esc_attr( $geo['latitude'] ),
				esc_attr( $geo['longitude'] )
			);
			printf(
				'<meta name="ICBM" content="%s, %s" />' . PHP_EOL,
				esc_attr( $geo['latitude'] ),
				esc_attr( $geo['longitude'] )
			);
		}
	}


	/**
	 * Echos the uploaded date to the attachment edit page.
	 *
	 * Adds the uploaded date, if present to the attachment submit metabox.
	 *
	 * @param WP_Post $post The attachment.
	 *
	 * @since 1.0.0
	 */
	public static function attachment_submitbox_metadata( $post ) {
		$published = get_post_meta( $post->ID, 'mf2_published', true );
		$date      = new DateTime( $published );
		if ( $published ) {
			$created_on = sprintf(
			/* translators: Publish box date string. 1: Date, 2: Time. See https://secure.php.net/date */
				__( '%1$s at %2$s', 'simple-location' ),
				/* translators: Publish box date format, see https://secure.php.net/date */
				wp_date( _x( 'M j, Y', 'publish box date format', 'simple-location' ), $date->getTimestamp(), $date->getTimeZone() ),
				/* translators: Publish box time format, see https://secure.php.net/date */
				wp_date( _x( 'H:i T', 'publish box time format', 'simple-location' ), $date->getTimestamp(), $date->getTimeZone() )
			);
			echo '<div class="misc-pub-section curtime misc-pub-pubtime">';
			/* translators: Attachment information. %s: Date based on the timestamp in the attachment file. */
			echo wp_kses_post( sprintf( __( 'Created on: %s', 'simple-location' ), '<b>' . $created_on . '</b>' ) );
			echo '</div>';
		}
	}


	/**
	 * Displays the Latitude, Longitude, and Address Description on the Attachment Page.
	 *
	 * Adds the fields for location data from an attachment.
	 *
	 * @param array   $form_fields See attachment_fields_to_edit filter in WordPress.
	 * @param WP_Post $post Attachment post object.
	 * @return array $form_fields Updated with extra fields.
	 *
	 * @since 1.0.0
	 */
	public static function attachment_fields_to_edit( $form_fields, $post ) {
		$geodata                    = self::get_geodata( $post );
		$form_fields['geo_address'] = array(
			'value'        => ifset( $geodata['address'] ),
			'label'        => __( 'Location', 'simple-location' ),
			'input'        => 'html',
			'show_in_edit' => false,
			'html'         => sprintf( '<span>%1$s</span>', ifset( $geodata['address'] ) ),
		);
		if ( isset( $geodata['latitude'] ) && isset( $geodata['longitude'] ) ) {
			$form_fields['location'] = array(
				'value'        => '',
				'label'        => __( 'Geo Coordinates', 'simple-location' ),
				'input'        => 'html',
				'show_in_edit' => false,
				'html'         => sprintf( '<span>%1$s, %2$s</span>', $geodata['latitude'], $geodata['longitude'] ),
			);
		}
		$time = get_post_meta( $post->ID, 'mf2_published', true );
		if ( $time ) {
			$form_fields['mf2_published'] = array(
				'value'        => $time,
				'label'        => __( 'Creation Time', 'simple-location' ),
				'input'        => 'html',
				'show_in_edit' => false,
				'html'         => sprintf( '<time dateime="%1$s" />%1$s</time><br />', $time ),
			);
		}
		return $form_fields;
	}

	/**
	 * Takes data from image meta and moves it to the appropriate keys in the attachments post meta.
	 *
	 * This includes moving the created date and location to their own keys and looking up the location and setting the address description.
	 *
	 * @param array $meta Image metadata.
	 * @param int   $post_id The attachment ID.
	 * @return array $meta The updated metadata.
	 *
	 * @since 1.0.0
	 */
	public static function attachment( $meta, $post_id ) {
		if ( ! isset( $meta['image_meta'] ) ) {
			return $meta;
		}

		$data   = $meta['image_meta'];
		$update = array();
		if ( isset( $data['created'] ) ) {
			$update['mf2_published'] = $data['created'];
		}
		if ( isset( $data['location'] ) ) {
			foreach ( array( 'latitude', 'longitude', 'altitude' ) as $prop ) {
				if ( array_key_exists( $prop, $data['location'] ) ) {
					$update[ 'geo_' . $prop ] = $data['location'][ $prop ];
				}
			}
			$reverse = Loc_Config::geo_provider();
			$reverse->set( $data['location']['latitude'], $data['location']['longitude'] );
			$reverse_adr = $reverse->reverse_lookup();
			if ( isset( $reverse_adr['display-name'] ) ) {
				$update['geo_address'] = $reverse_adr['display-name'];
			}
			if ( ! array_key_exists( 'geo_altitude', $update ) ) {
				$update['geo_altitude'] = $reverse->elevation();
			}
			$zone = Location_Zones::in_zone( $data['location']['latitude'], $data['location']['longitude'] );
			if ( ! empty( $zone ) ) {
				$update['geo_address'] = $zone;
				self::set_visibility( 'post', $post_id, 'protected' );
				$update['geo_zone'] = $zone;
			} else {
				self::set_visibility( 'post', $post_id, 'public' );
			}
		}
		$update = array_filter( $update );
		foreach ( $update as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		return $meta;
	}

	/**
	 * Reduce points with Visvalingam-Whyatt algorithm.
	 *
	 * @param array $points Points.
	 * @param int   $target Desired count of points.
	 *
	 * @return array Reduced set of points.
	 */
	public static function simplify_bw( $points, $target ) {
		// Refuse to reduce if points are less than target.
		if ( count( $points ) <= $target ) {
			return $points;
		}
		$kill = count( $points ) - $target;
		while ( $kill-- > 0 ) {
			$idx      = 1;
			$min_area = area_of_triangle( $points[0], $points[1], $points[2] );
			foreach ( range( 2, array_key_last_index( $points, -2 ) ) as $segment ) {
				$area = self::area_of_triangle(
					$points[ $segment - 1 ],
					$points[ $segment ],
					$points[ $segment + 1 ]
				);
				if ( $area < $min_area ) {
					$min_area = $area;
					$idx      = $segment;
				}
			}
			array_splice( $points, $idx, 1 );
		}

		return $points;
	}

	/**
	 * Reduce points with Ramer–Douglas–Peucker algorithm.
	 *
	 * @param array $points Points.
	 * @param int   $tolerance Tolerance.
	 *
	 * @return array Reduced set of points.
	 */
	public static function simplify_rdp( $points, $tolerance ) {
		// if this is a multilinestring, then we call ourselves one each segment individually, collect the list, and return that list of simplified lists.
		if ( is_array( $points[0][0] ) ) {
			$multi = array();
			foreach ( $points as $subvertices ) {
				$multi[] = self::simplify_rdp( $subvertices, $tolerance );
			}
			return $multi;
		}
		$tolerance2 = $tolerance * $tolerance;

		// okay, so this is a single linestring and we simplify it individually.
		return self::segment_rdp( $points, $tolerance2 );
	}

	/**
	 * Reduce single linestring with Ramer–Douglas–Peucker algorithm.
	 *
	 * @param array $segment Single line segment.
	 * @param int   $tolerance_squared Tolerance Squared.
	 *
	 * @return array Reduced set of points.
	 */
	public static function segment_rdp( $segment, $tolerance_squared ) {
		if ( count( $segment ) <= 2 ) {
			return $segment; // segment is too small to simplify, hand it back as-is.
		}

		/*
		 * Find the maximum distance (squared) between this line $segment and each vertex.
		 * distance is solved as described at UCSD page linked above.
		 * cheat: vertical lines (directly north-south) have no slope so we fudge it with a very tiny nudge to one vertex; can't imagine any units where this will matter.
		 */
		$startx = (float) $segment[0][0];
		$starty = (float) $segment[0][1];
		$endx   = (float) $segment[ count( $segment ) - 1 ][0];
		$endy   = (float) $segment[ count( $segment ) - 1 ][1];

		if ( $endx === $startx ) {
			$startx += 0.00001;
		}

		$m = ( $endy - $starty ) / ( $endx - $startx ); // slope, as in y = mx + b.
		$b = $starty - ( $m * $startx );              // y-intercept, as in y = mx + b.

		$max_distance_squared = 0;
		$max_distance_index   = null;
		for ( $i = 1, $l = count( $segment ); $i <= $l - 2; $i++ ) {
			$x1 = $segment[ $i ][0];
			$y1 = $segment[ $i ][1];

			$closestx = ( ( $m * $y1 ) + ( $x1 ) - ( $m * $b ) ) / ( ( $m * $m ) + 1 );
			$closesty = ( $m * $closestx ) + $b;
			$distsqr  = ( $closestx - $x1 ) * ( $closestx - $x1 ) + ( $closesty - $y1 ) * ( $closesty - $y1 );

			if ( $distsqr > $max_distance_squared ) {
				$max_distance_squared = $distsqr;
				$max_distance_index   = $i;
			}
		}

		/*
		 * Cleanup and disposition.
		 * if the max distance is below tolerance, we can bail, giving a straight line between the start vertex and end vertex.
		 * (all points are so close to the straight line).
		 */
		if ( $max_distance_squared <= $tolerance_squared ) {
			return array( $segment[0], $segment[ count( $segment ) - 1 ] );
		}

		/*
		 * But if we got here then a vertex falls outside the tolerance.
		 * split the line segment into two smaller segments at that "maximum error vertex" and simplify those.
		 */
		$slice1 = array_slice( $segment, 0, $max_distance_index );
		$slice2 = array_slice( $segment, $max_distance_index );
		$segs1  = self::segment_rdp( $slice1, $tolerance_squared );
		$segs2  = self::segment_rdp( $slice2, $tolerance_squared );
		return array_merge( $segs1, $segs2 );
	}

	/**
	 * Adds rewrite endpoints.
	 *
	 * This adds rewrite endpoints for the plugin.
	 *
	 * @since 1.0.0
	 */
	public static function rewrite() {
		global $wp_rewrite;
		$pagination_regex = $wp_rewrite->pagination_base . '/?([0-9]{1,})/?$';

		add_rewrite_endpoint( 'geo', EP_ALL_ARCHIVES );
		add_rewrite_endpoint( 'map', EP_ALL_ARCHIVES );

		add_rewrite_rule(
			'map/location/(.+?)/' . $pagination_regex,
			'index.php?map=1&location=$matches[1]&paged=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'map/location/(.+?)/?$',
			'index.php?location=$matches[1]&map=1',
			'top'
		);
	}


	/**
	 * Registers query variables.
	 *
	 * Registers a query variable.
	 *
	 * @param array $vars Query Variables.
	 * @return array $vars Returns the updated array.
	 *
	 * @since 1.0.0
	 */
	public static function query_var( $vars ) {
		$vars[] = 'geo';
		$vars[] = 'map';
		$vars[] = 'sloc_units';
		return $vars;
	}

	/**
	 * Return meta query arguments based on input.
	 *
	 * @param array $geo WP_Query arguments.
	 *
	 * @since 1.0.0
	 */
	public static function filter_geo_query( $geo ) {
		$args   = array(
			'relation' => 'OR',
			array(
				'key'     => 'geo_longitude',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => 'geo_address',
				'compare' => 'EXISTS',
			),
		);
		$public = array(
			'key'     => 'geo_public',
			'type'    => 'numeric',
			'compare' => '=',
		);
		switch ( $geo ) {
			case 'all':
				return $args;
			case 'private':
				$public['value'] = (int) 0;
				return $public;
			case 'public':
			case 'map':
				$public['value'] = (int) 1;
				return $public;
			case 'text':
			case 'description':
			case 'protected':
				$public['value'] = (int) 2;
				return $public;
			default:
				return array();
		}
	}


	/**
	 * Filters Location in Posts.
	 *
	 * @param WP_Query $query Query Object.
	 *
	 * @since 1.0.0
	 */
	public static function filter_location_posts( $query ) {
		if ( ! array_key_exists( 'geo', $query->query_vars ) || ! $query->is_main_query() ) {
			return;
		}

		$geo  = $query->get( 'geo' );
		$args = self::filter_geo_query( $geo );
		if ( ! empty( $args ) ) {
			$query->set( 'meta_query', array( $args ) );
		}
	}


	/**
	 * Filters Location in Comments.
	 *
	 * @param WP_Query $query Query Object.
	 *
	 * @since 1.0.0
	 */
	public static function pre_get_comments( $query ) {
		if ( ! isset( $_REQUEST['geo'] ) ) {
			return;
		}
		$geo  = sanitize_text_field( $_REQUEST['geo'] );
		$args = self::filter_geo_query( $geo );
		if ( ! empty( $args ) ) {
			$query->query_vars['meta_query'] = array( $args );
			$query->meta_query->parse_query_vars( $query->query_vars );
		}
	}

	/**
	 * Set GeoData on an Object.
	 *
	 * @param mixed $object Can be WP_Comment, WP_User, WP_Post, WP_Term, or int which will be considered a post id.
	 * @param array $geodata {
	 *  An array of details about a location.
	 *
	 *  @type float $latitude Decimal Latitude.
	 *  @type float $longitude Decimal Longitude.
	 *  @type float $altitude Altitude in Meters.
	 *  @type string $icon Icon.
	 *  @type string $address Textual Description of location.
	 *  @type int $map_zoom Zoom for Map Display.
	 *  @type float $speed Speed in Meters.
	 *  @type float $heading If set, between 0 and 360 degrees.
	 *  @type string $wikipedia_link URL of the Airport Homepage
	 *  @type string $visibility Can be either public, private, or protected.
	 *  @type string $timezone Timezone string.
	 *  @type array $weather Array of Weather Properties.
	 * }
	 * @return WP_Error|boolean Return success or WP_Error.
	 *
	 * @since 1.0.0
	 */
	public static function set_geodata( $object = null, $geodata ) {
		if ( ! is_array( $geodata ) ) {
			return false;
		}
		$type    = null;
		$geodata = wp_array_slice_assoc( $geodata, array( 'latitude', 'longitude', 'address', 'trip', 'map_zoom', 'weather', 'altitude', 'speed', 'heading', 'visibility', 'timezone', 'icon' ) );
		if ( isset( $geodata['map_zoom'] ) ) {
			$geodata['zoom'] = $geodata['map_zoom'];
			unset( $geodata['map_zoom'] );
		}

		if ( ! $object ) {
			$object = get_post();
		}
		// If numeric assume post_ID.
		if ( is_numeric( $object ) ) {
			$object = get_post( $object );
		}
		if ( $object instanceof WP_Post ) {
			$type = 'post';
			$id   = $object->ID;
		}

		if ( $object instanceof WP_Comment ) {
			$id   = $object->comment_ID;
			$type = 'comment';
		}
		if ( $object instanceof WP_Term ) {
			$id   = $object->term_id;
			$type = 'term';
		}
		if ( $object instanceof WP_User ) {
			$id   = $object->ID;
			$type = 'user';
		}
		if ( ! $type ) {
			return new WP_Error(
				'invalid input',
				__( 'Invalid Input', 'simple-location' ),
				array(
					'object'  => $object,
					'geodata' => $geodata,
				)
			);
		}
		if ( isset( $geodata['visibility'] ) ) {
			self::set_visibility( $type, $id, $geodata['visibility'] );
			unset( $geodata['visibility'] );
		}
		foreach ( $geodata as $key => $value ) {
			update_metadata( $type, $id, 'geo_' . $key, $value );
		}
		return true;
	}


	/**
	 * Get Geo Meta Data on an Object.
	 *
	 * @param string  $type Object type.
	 * @param int     $id Object ID.
	 * @param boolean $full Return just location and visibility or everything.
	 * @return array $geodata See get_geodata and set_geodata for full list.
	 *
	 * @since 1.0.0
	 */
	private static function get_geometadata( $type, $id, $full = true ) {
		$geodata              = array();
		$geodata['longitude'] = get_metadata( $type, $id, 'geo_longitude', true );
		$geodata['latitude']  = get_metadata( $type, $id, 'geo_latitude', true );
		$geodata['altitude']  = get_metadata( $type, $id, 'geo_altitude', true );
		$geodata['trip']      = get_metadata( $type, $id, 'geo_trip', true );
		$geodata['address']   = get_metadata( $type, $id, 'geo_address', true );
		$geodata['icon']      = get_metadata( $type, $id, 'geo_icon', true );
		if ( empty( $geodata['icon'] ) ) {
			$geodata['icon'] = Loc_View::get_default_icon();
		}
		$geodata['visibility'] = self::get_visibility( $type, $id );

		if ( $full ) {
			$geodata['timezone'] = get_metadata( $type, $id, 'geo_timezone', true );
			$geodata['map_zoom'] = get_metadata( $type, $id, 'geo_zoom', true );
			$geodata['weather']  = get_metadata( $type, $id, 'geo_weather', true );
		}
		$geodata = array_filter( $geodata );
		if ( empty( $geodata['longitude'] ) && empty( $geodata['address'] ) && empty( $geodata['trip'] ) ) {
			return null;
		}
		return array_filter( $geodata );
	}

	/**
	 * Does this object have location data.
	 *
	 * @param mixed $object Object type.
	 * @return WP_Error|boolean Return success or WP_Error.
	 *
	 * @since 1.0.0
	 */
	public static function has_location( $object = null ) {
		$data = self::get_geodata( $object );
		return ! is_null( $data );
	}


	/**
	 * Set GeoData on an Object.
	 *
	 * @param mixed   $object Can be WP_Comment, WP_User, WP_Post, WP_Term, or int which will be considered a post id.
	 * @param boolean $full Return all or just some of the data.
	 * @return array $geodata {
	 *  An array of details about a location.
	 *
	 *  @type float $latitude Decimal Latitude.
	 *  @type float $longitude Decimal Longitude.
	 *  @type float $altitude Altitude in Meters.
	 *  @type string $address Textual Description of location.
	 *  @type int $map_zoom Zoom for Map Display.
	 *  @type float $speed Speed in Meters.
	 *  @type float $heading If set, between 0 and 360 degrees.
	 *  @type string $wikipedia_link URL of the Airport Homepage
	 *  @type string $visibility Can be either public, private, or protected.
	 *  @type string $timezone Timezone string.
	 *  @type array $weather Array of Weather Properties.
	 * }
	 *
	 * @since 1.0.0
	 */
	public static function get_geodata( $object = null, $full = true ) {
		$geodata = false;
		if ( ! $object ) {
			$object = get_post();
		}
		// If numeric assume post_ID.
		if ( is_numeric( $object ) ) {
			$object = get_post( $object );
		}
		if ( $object instanceof WP_Post ) {
			$geodata = self::get_geometadata( 'post', $object->ID, $full );
			if ( ! $geodata ) {
				return null;
			}
			$geodata['ID'] = $object->ID;
			// Remove Old Metadata.
			delete_post_meta( $object->ID, 'geo_map' );
			delete_post_meta( $object->ID, 'geo_full' );
			delete_post_meta( $object->ID, 'geo_lookup' );
		}

		if ( $object instanceof WP_Comment ) {
			$geodata = self::get_geometadata( 'comment', $object->comment_ID, $full );
			if ( ! $geodata ) {
				return null;
			}
			$geodata['comment_ID'] = $object->comment_ID;
		}
		if ( $object instanceof WP_Term ) {
			$geodata = self::get_geometadata( 'term', $object->term_id, $full );
			if ( ! $geodata ) {
				return null;
			}
			$geodata['term_id'] = $object->term_id;
		}
		if ( $object instanceof WP_User ) {
			$geodata = self::get_geometadata( 'user', $object->ID, $full );
			if ( ! $geodata ) {
				return null;
			}
			$geodata['user_ID'] = $object->ID;
		}

		return $geodata;
	}


	/**
	 * Registers Geo Metadata.
	 *
	 * @since 1.0.0
	 */
	public static function register_meta() {
		$args = array(
			'sanitize_callback' => 'clean_coordinate',
			'type'              => 'number',
			'description'       => __( 'Latitude', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'geo_latitude', $args );
		register_meta( 'comment', 'geo_latitude', $args );
		register_meta( 'user', 'geo_latitude', $args );
		register_meta( 'term', 'geo_latitude', $args );

		$args = array(
			'sanitize_callback' => 'clean_coordinate',
			'type'              => 'number',
			'description'       => __( 'Longitude', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'geo_longitude', $args );
		register_meta( 'comment', 'geo_longitude', $args );
		register_meta( 'user', 'geo_longitude', $args );
		register_meta( 'term', 'geo_longitude', $args );

		$args = array(
			'type'         => 'string',
			'description'  => __( 'Timezone of Location', 'simple-location' ),
			'single'       => true,
			'show_in_rest' => false,
		);
		register_meta( 'post', 'geo_timezone', $args );
		register_meta( 'comment', 'geo_timezone', $args );
		register_meta( 'user', 'geo_timezone', $args );
		register_meta( 'term', 'geo_timezone', $args );

		$args = array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_address' ),
			'type'              => 'string',
			'description'       => __( 'Geodata Address', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'geo_address', $args );
		register_meta( 'comment', 'geo_address', $args );
		register_meta( 'user', 'geo_address', $args );
		register_meta( 'term', 'geo_address', $args );

		$args = array(
			'sanitize_callback' => array( __CLASS__, 'esc_attr' ),
			'type'              => 'string',
			'description'       => __( 'Geodata Icon', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'geo_icon', $args );
		register_meta( 'comment', 'geo_icon', $args );
		register_meta( 'user', 'geo_icon', $args );
		register_meta( 'term', 'geo_icon', $args );


		// Numeric Geo Properties
		$numerics = array(
			'altitude'    => __( 'Altitude', 'simple-location' ),
			'zoom'        => __( 'Geodata Zoom for Map Display', 'simple-location' ),
			/*
			 * Officially 0 is private 1 is public and absence or non-zero is assumed public.
			 * Therefore any non-zero number could be used to specify different display options.
			 */
			'public'      => __( 'Geodata Public', 'simple-location' ),
		);
		foreach ( $numerics as $prop => $description ) {
			$args = array(
				'type'         => 'number',
				'description'  => 'Altitude',
				'single'       => true,
				'show_in_rest' => false,
			);
			foreach ( array( 'post', 'comment', 'user', 'term' ) as $type ) {
				register_meta( $type, 'geo_' . $prop, $args );
			}
		}

		// Legacy Weather Storage
		$args = array(
			'type'         => 'array',
			'description'  => 'Weather Data (Deprecated)',
			'single'       => true,
			'show_in_rest' => false,
		);
		register_meta( 'post', 'geo_weather', $args );
		register_meta( 'comment', 'geo_weather', $args );
		register_meta( 'user', 'geo_weather', $args );
		register_meta( 'term', 'geo_weather', $args );


		$numerics = array(
			// Weather Properties.
			'temperature' => __( 'Temperature', 'simple-location' ),
			'humidity'    => __( 'Humidity', 'simple-location' ),
			'heatindex'   => __( 'Heat Index', 'simple-location' ),
			'windchill'   => __( 'Wind Chill', 'simple-location' ),
			'dewpoint'    => __( 'Dewpoint', 'simple-location' ),
			'pressure'    => __( 'Atmospheric Pressure', 'simple-location' ),
			'cloudiness'  => __( 'Cloudiness', 'simple-location' ),
			'rain'        => __( 'Rainfall', 'simple-location' ),
			'snow'        => __( 'Snowfall', 'simple-location' ),
			'visibility'  => __( 'Visibility', 'simple-location' ),
			'radiation'   => __( 'Radiation', 'simple-location' ),
			'illuminance' => __( 'Illuminance', 'simple-location' ),
			'uv'          => __( 'UV Index', 'simple-location' ),
			'aqi'         => __( 'Air Quality Index', 'simple-location' ),
			'pm1_0'       => __( 'Particulate Matter 1.0', 'simple-location' ),
			'pm2_5'       => __( 'Particulate Matter 2.5', 'simple-location' ),
			'pm10_0'      => __( 'Particulate Matter 10.0', 'simple-location' ),
			'co'          => __( 'Carbon Monoxide', 'simple-location' ),
			'co2'         => __( 'Carbon Dioxide', 'simple-location' ),
			'nh3'         => __( 'Ammonia', 'simple-location' ),
			'o3'          => __( 'Ozone', 'simple-location' ),
			'pb'          => __( 'Lead', 'simple-location' ),
			'so2'         => __( 'Sulfur Dioxide', 'simple-location' ),
			'windspeed'   => __( 'Wind Speed', 'simple-location' ),
			'winddegree'  => __( 'Wind Degree', 'simple-location' ),
			'windgust'    => __( 'Wind Gust', 'simple-location' ),
		);

		foreach ( $numerics as $prop => $description ) {
			$args = array(
				'type'         => 'number',
				'description'  => 'Altitude',
				'single'       => true,
				'show_in_rest' => false,
			);
			foreach ( array( 'post', 'comment', 'user', 'term' ) as $type ) {
				register_meta( $type, 'weather_' . $prop, $args );
			}
		}

		$args = array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_text_field' ),
			'type'              => 'string',
			'description'       => __( 'Weather Summary', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'weather_summary', $args );
		register_meta( 'comment', 'weather_summary', $args );
		register_meta( 'user', 'weather_summary', $args );
		register_meta( 'term', 'weather_summary', $args );

		$args = array(
			'sanitize_callback' => array( __CLASS__, 'esc_attr' ),
			'type'              => 'string',
			'description'       => __( 'Weather Icon', 'simple-location' ),
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'weather_icon', $args );
		register_meta( 'comment', 'weather_icon', $args );
		register_meta( 'user', 'weather_icon', $args );
		register_meta( 'term', 'weather_icon', $args );
	}


	/**
	 * Registers REST Fields.
	 *
	 * @since 1.0.0
	 */
	public static function rest_location() {
		register_rest_field(
			array( 'post', 'comment', 'user', 'term' ),
			'latitude',
			array(
				'get_callback' => array( 'WP_Geo_Data', 'rest_get_latitude' ),
				'schema'       => array(
					'latitude' => __( 'Latitude', 'simple-location' ),
					'type'     => 'float',
				),
			)
		);
		register_rest_field(
			array( 'post', 'comment', 'user', 'term' ),
			'longitude',
			array(
				'get_callback' => array( 'WP_Geo_Data', 'rest_get_longitude' ),
				'schema'       => array(
					'longitude' => __( 'Longitude', 'simple-location' ),
					'type'      => 'float',
				),
			)
		);
		register_rest_field(
			array( 'post', 'comment', 'term', 'user' ),
			'geo_address',
			array(
				'get_callback' => array( 'WP_Geo_Data', 'rest_get_address' ),
				'schema'       => array(
					'geo_address' => __( 'Location', 'simple-location' ),
					'type'        => 'string',
				),
			)
		);
		register_rest_field(
			array( 'post', 'comment', 'term', 'user' ),
			'timezone',
			array(
				'get_callback' => array( 'WP_Geo_Data', 'rest_get_timezone' ),
				'schema'       => array(
					'geo_public' => __( 'Last Reported Timezone', 'simple-location' ),
					'type'       => 'string',
				),
			)
		);

	}

	/**
	 * Adds longitude as a field to the REST API.
	 *
	 * @param mixed           $object The object being acted on.
	 * @param string          $attr Not Used but Required by Filter.
	 * @param WP_Rest_Request $request REST Request Object.
	 * @param string          $object_type Object Type.
	 * @return string Return data.
	 *
	 * @since 1.0.0
	 */
	public static function rest_get_longitude( $object, $attr, $request, $object_type ) {
		$object  = sloc_get_object_from_id( $object, $object_type );
		$geodata = self::get_geodata( $object );
		if ( ! is_array( $geodata ) ) {
			return '';
		}
		if ( empty( $geodata['latitude'] ) ) {
			return '';
		}
		if ( 'public' === $geodata['visibility'] ) {
			return $geodata['longitude'];
		}
		return '';
	}

	/**
	 * Adds latitude as a field to the REST API.
	 *
	 * @param mixed           $object The object being acted on.
	 * @param string          $attr Not used but required by filter.
	 * @param WP_Rest_Request $request REST Request Object.
	 * @param string          $object_type Object Type.
	 * @return string Return data.
	 *
	 * @since 1.0.0
	 */
	public static function rest_get_latitude( $object, $attr, $request, $object_type ) {
		$object  = sloc_get_object_from_id( $object, $object_type );
		$geodata = self::get_geodata( $object );

		if ( ! is_array( $geodata ) ) {
			return '';
		}
		if ( empty( $geodata['latitude'] ) ) {
			return '';
		}
		if ( 'public' === $geodata['visibility'] ) {
			return $geodata['latitude'];
		}
		return '';
	}


	/**
	 * Adds address as a field to the REST API.
	 *
	 * @param mixed           $object Object.
	 * @param string          $attr Not used but required by filter.
	 * @param WP_Rest_Request $request REST Request Object.
	 * @param string          $object_type Object Type.
	 * @return string Return data.
	 *
	 * @since 1.0.0
	 */
	public static function rest_get_address( $object, $attr, $request, $object_type ) {
		$object  = sloc_get_object_from_id( $object, $object_type );
		$geodata = self::get_geodata( $object );

		if ( ! is_array( $geodata ) ) {
			return '';
		}
		if ( empty( $geodata['address'] ) ) {
			return '';
		}
		if ( in_array( $geodata['visibility'], array( 'public', 'protected' ), true ) ) {
			return $geodata['address'];
		}
		return '';
	}


	/**
	 * Adds timezone as a field to the REST API.
	 *
	 * @param mixed           $object Object type.
	 * @param string          $attr Not used but required by filter.
	 * @param WP_Rest_Request $request REST Request Object.
	 * @param string          $object_type Object type.
	 * @return string Return data.
	 *
	 * @since 1.0.0
	 */
	public static function rest_get_timezone( $object, $attr, $request, $object_type ) {
		$object  = sloc_get_object_from_id( $object, $object_type );
		$geodata = self::get_geodata( $object );

		if ( ! is_array( $geodata ) ) {
			return '';
		}

		if ( empty( $geodata['timezone'] ) ) {
			return wp_timezone_string();
		}
		if ( in_array( $geodata['visibility'], array( 'public', 'protected' ), true ) ) {
			return $geodata['timezone'];
		}

		return wp_timezone_string();
	}


	/**
	 * Sanitizes address fields
	 *
	 * @param string $data The address.
	 * @return string $data Sanitized version of
	 *
	 * @since 1.0.0
	 */
	public static function sanitize_address( $data ) {
		$data = wp_kses_post( $data );
		$data = trim( $data );
		if ( empty( $data ) ) {
			$data = null;
		}
		return $data;
	}

	public static function rest_prepare_post( $response, $post, $request ) {
		$data = $response->get_data();
		foreach ( array( 'latitude', 'longitude', 'geo_address', 'timezone' ) as $field ) {
			if ( empty( $data[ $field ] ) ) {
				unset( $data[ $field ] );
			}
		}
		$response->set_data( $data );
		return $response;
	}

	public static function rest_prepare_comment( $response, $comment, $request ) {
		$data = $response->get_data();
		foreach ( array( 'latitude', 'longitude', 'geo_address', 'timezone' ) as $field ) {
			if ( empty( $data[ $field ] ) ) {
				unset( $data[ $field ] );
			}
		}
		$response->set_data( $data );
		return $response;
	}

	public static function rest_prepare_user( $response, $user, $request ) {
		$data = $response->get_data();
		foreach ( array( 'latitude', 'longitude', 'geo_address', 'timezone' ) as $field ) {
			if ( empty( $data[ $field ] ) ) {
				unset( $data[ $field ] );
			}
		}
		$response->set_data( $data );
		return $response;
	}

	 /*
	  Sets Location custom capabilities.
	 *
	 * @param string[] $caps    Array of the users capabilities.
	  * @param string   $cap     Capability name.
	  * @param int      $user_id The user ID.
	 * @param array    $args    Adds the context to the cap. Typically the object ID.
	  */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		$id = 0;
		if ( isset( $args[0] ) ) {
			$id = $args[0];
		}
		switch ( $cap ) {
			case 'read_posts_location':
				$post = get_post( $id );
				// If it is your post you just need to be able to read it. Otherwise you need the private posts capability.
				if ( $post && ( $user_id === $post->post_author ) ) {
					$caps = array( 'read' );
				} else {
					$caps = array( 'read_private_posts' );
				}
				break;
			case 'read_comments_location':
				$comment = get_comment( $id );
				// If it is your comment you just need to be able to read it. Otherwise you need the private posts capability.
				if ( $comment && ( $user_id === $comment->user_id ) && ( 0 !== $comment->user_id ) ) {
					$caps = array( 'read' );
				} else {
					$caps = array( 'read_private_posts' );
				}
				break;
			case 'read_users_location':
				// If it is your post you just need to be able to read it. Otherwise you need the private posts capability.
				if ( $user_id === $id ) {
					$caps = array( 'read' );
				} else {
					$caps = array( 'edit_users' );
				}
				break;
			case 'edit_posts_location':
				$post = get_post( $id );
				// If it is your post you just need to be able to read it. Otherwise you need the private posts capability.
				if ( $post && ( $user_id === $post->post_author ) ) {
					$caps = array( 'edit_posts' );
				} else {
					$caps = array( 'edit_others_posts' );
				}
				break;
			case 'edit_comments_location':
				$comment = get_comment( $id );
				// If it is your comment you just need to be able to read it. Otherwise you need the private posts capability.
				if ( $comment && ( $user_id === $comment->user_id ) && ( 0 !== $comment->user_id ) ) {
					$caps = array( 'edit_posts' );
				} else {
					$caps = array( 'moderate_comments' );
				}
				break;
			case 'edit_users_location':
				// If it is your post you just need to be able to read it. Otherwise you need the private posts capability.
				if ( $user_id === $id ) {
					$caps = array( 'read' );
				} else {
					$caps = array( 'edit_users' );
				}
				break;

		}
		return $caps;
	}

	/*
	 Current User Can Read with Object for Private Location.
	 *
	 * @param mixed $object The object.
	 * @return boolean True if current user can.
	 */
	public static function current_user_can_read( $object ) {
		if ( ! $object ) {
			$object = get_post();
		}

		if ( $object instanceof WP_Post ) {
			return current_user_can( 'read_posts_location', $object->ID );
		} elseif ( $object instanceof WP_Comment ) {
			return current_user_can( 'read_comments_location', $object->comment_ID );
		} elseif ( $object instanceof WP_Term ) {
			return current_user_can( 'read_terms_location', $object->term_id );
		} elseif ( $object instanceof WP_User ) {
			return current_user_can( 'read_users_location', $object->ID );
		} elseif ( is_numeric( $object ) ) {
			$object = get_post( $object );
			if ( $object instanceof WP_Post ) {
				return current_user_can( 'read_posts_location', $object->ID );
			}
		}
		return false;
	}

	/*
	 Current User Can Read with Object for Private Location.
	 *
	 * @param mixed $object The object.
	 * @return boolean True if current user can.
	 */
	public static function current_user_can_edit( $object ) {
		if ( ! $object ) {
			$object = get_post();
		}

		if ( $object instanceof WP_Post ) {
			return current_user_can( 'edit_posts_location', $object->ID );
		} elseif ( $object instanceof WP_Comment ) {
			return current_user_can( 'edit_comments_location', $object->comment_ID );
		} elseif ( $object instanceof WP_Term ) {
			return current_user_can( 'edit_terms_location', $object->term_id );
		} elseif ( $object instanceof WP_User ) {
			return current_user_can( 'edit_users_location', $object->ID );
		} elseif ( is_numeric( $object ) ) {
			$object = get_post( $object );
			if ( $object instanceof WP_Post ) {
				return current_user_can( 'edit_posts_location', $object->ID );
			}
		}
		return false;
	}
}

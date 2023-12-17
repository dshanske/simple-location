<?php
/**
 * Location Data
 *
 * Sets up basic support for location.
 */

add_action( 'init', array( 'Geo_Base', 'init' ), 1 );
add_action( 'admin_init', array( 'Geo_Base', 'admin_init' ) );

// Jetpack added Location support in 2017 remove it due conflict https://github.com/Automattic/jetpack/pull/9573 which created conflict.
add_filter( 'jetpack_tools_to_include', array( 'Geo_Base', 'jetpack_remove' ), 11 );

/**
 * Handles Basic Geo Functionality for WordPress.
 *
 * @since 1.0.0
 */
class Geo_Base {


	/**
	 * Geo Data Initialization Function.
	 *
	 * Meant to be attached to init hook. Sets up all the geodata enhancements.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_filter( 'query_vars', array( __CLASS__, 'query_var' ) );
		add_filter( 'template_include', array( __CLASS__, 'map_archive_template' ) );

		self::rewrite();

		add_action( 'rss2_ns', array( __CLASS__, 'georss_namespace' ) );
		add_action( 'atom_ns', array( __CLASS__, 'georss_namespace' ) );
		add_action( 'rdf_ns', array( __CLASS__, 'georss_namespace' ) );

		add_action( 'rss_item', array( __CLASS__, 'georss_item' ) );
		add_action( 'rss2_item', array( __CLASS__, 'georss_item' ) );
		add_action( 'atom_entry', array( __CLASS__, 'georss_item' ) );
		add_action( 'rdf_item', array( __CLASS__, 'georss_item' ) );
		add_action( 'json_feed_item', array( __CLASS__, 'json_feed_item' ), 10, 2 );
		add_action( 'wp_head', array( __CLASS__, 'meta_tags' ) );

		add_action( 'rest_api_init', array( __CLASS__, 'rest_location' ) );

		// Add the Same Post Type Support JetPack uses.
		add_post_type_support( 'post', 'geo-location' );
		add_post_type_support( 'page', 'geo-location' );
		add_post_type_support( 'attachment', 'geo-location' );

		// Add Dropdown.
		add_action( 'restrict_manage_posts', array( __CLASS__, 'geo_posts_dropdown' ), 12, 2 );
		add_action( 'restrict_manage_comments', array( __CLASS__, 'geo_comments_dropdown' ), 12 );


		add_filter( 'bulk_actions-edit-post', array( __CLASS__, 'register_bulk_edit_location' ), 10 );
		add_filter( 'handle_bulk_actions-edit-post', array( __CLASS__, 'handle_bulk_edit_location' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'bulk_action_admin_notices' ) );

		add_filter( 'rest_prepare_post', array( __CLASS__, 'rest_prepare_post' ), 10, 3 );
		add_filter( 'rest_prepare_comment', array( __CLASS__, 'rest_prepare_comment' ), 10, 3 );
		add_filter( 'rest_prepare_user', array( __CLASS__, 'rest_prepare_user' ), 10, 3 );

		add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap' ), 1, 4 );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post_meta' ) );
		add_action( 'save_post', array( __CLASS__, 'last_seen' ), 20, 2 );
		add_action( 'edit_attachment', array( __CLASS__, 'save_post_meta' ) );
		add_action( 'edit_comment', array( __CLASS__, 'save_comment_meta' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'user_profile' ), 12 );
		add_action( 'edit_user_profile', array( __CLASS__, 'user_profile' ), 12 );
		add_action( 'personal_options_update', array( __CLASS__, 'save_user_meta' ), 12 );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_meta' ), 12 );

		foreach ( get_post_types_by_support( 'geo-location' ) as $post_type ) {
			add_filter( sprintf( 'manage_%1$s_posts_columns', $post_type ), array( __CLASS__, 'add_location_admin_column' ) );
			add_action( sprintf( 'manage_%1$s_posts_custom_column', $post_type ), array( __CLASS__, 'manage_location_admin_column' ), 10, 2 );
		}
	}

	public static function admin_init() {
		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
	}

	public static function screens() {
		return apply_filters( 'sloc_post_types', get_post_types_by_support( 'geo-location' ) );
	}

	/* Create location meta boxes to be displayed on the post editor screen. */
	public static function add_meta_boxes() {
		add_meta_box(
			'locationsidebox',
			esc_html__( 'Location', 'simple-location' ),
			array( __CLASS__, 'metabox' ),
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
			array( __CLASS__, 'metabox' ),
			'comment',
			'normal',
			'default'
		);
	}

	public static function metabox( $object, $args ) {
		if ( self::current_user_can_edit( $object ) ) {
			load_template( plugin_dir_path( __DIR__ ) . 'templates/loc-metabox.php' );
			do_action( 'simple_location_sidebox', get_current_screen(), $object, $args );
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
		if ( 'venue' === $post->post_type ) {
			return;
		}

		if ( ! post_type_supports( $post->post_type, 'geo-location' ) ) {
			return;
		}
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
		if ( 'private' !== $geodata['visibility'] ) {
			set_user_geodata( $post->post_author, '', $geodata );
		}
	}

	public static function geo_public_user( $public ) {
		?>
		<tr>
		<th><label for="geo_public"><?php esc_html_e( 'Show:', 'simple-location' ); ?></label></th>
		<td><select id="location-visibility" name="geo_public">
		<?php self::geo_public_select( $public, true ); ?>
		</select></td>
		</tr>
		<?php
	}

	public static function enqueue( $hook_suffix ) {
		$screens   = self::screens();
		$screens[] = 'comment';
		$hooks     = array( 'profile.php' );
		$screen    = get_current_screen();
		if ( in_array( $screen->id, $screens, true ) || in_array( $hook_suffix, $hooks, true ) ) {
			wp_enqueue_style(
				'sloc_admin',
				plugins_url( 'css/location-admin.min.css', __DIR__ ),
				array(),
				Simple_Location_Plugin::$version
			);
			wp_enqueue_script(
				'sloc_location',
				plugins_url( 'js/location.js', __DIR__ ),
				array( 'jquery' ),
				Simple_Location_Plugin::$version,
				true
			);
			wp_enqueue_script(
				'moment-timezone',
				plugins_url( 'js/luxon.min.js', __DIR__ ),
				array(),
				Simple_Location_Plugin::$version,
				true
			);

			if ( 'post' === $screen->post_type ) {
				$weather = 'yes';
			} elseif ( 'comment' === $screen->id ) {
				$weather = 'yes';
			} else {
				$weather = 'no';
			}

			$options = array(
				'lookup'             => get_option( 'sloc_geolocation_provider' ),
				'units'              => get_option( 'sloc_measurements' ),
				'visibility_options' => self::geo_public(),
				'api_nonce'          => wp_create_nonce( 'wp_rest' ),
				'api_url'            => rest_url( '/sloc_geo/1.0/' ),
				'weather'            => $weather,
			);

			wp_localize_script(
				'sloc_location',
				'slocOptions',
				$options
			);
		}
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
				set_geo_visibility( 'post', $post_id, $visibility );
			}
		}
		if ( empty( $post_ids ) ) {
			return $redirect_to;
		}
		if ( 'lookup_location' === $doaction ) {
			$return = array();
			foreach ( $post_ids as $post_id ) {
				$return[] = Geo_Data::bulk_edit_lookup_location( $post_id );
			}
			$return      = array_filter( $return );
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
			if ( ! has_post_location( $post_id ) ) {
				echo esc_html_e( 'None', 'simple-location' );
			} else {
				$geo_public = self::geo_public();
				$visibility = get_post_geodata( $post_id, 'visibility' );
				echo esc_html( $geo_public[ $visibility ] );
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
		if ( ! post_type_supports( $post_type, 'geo-location' ) ) {
			return;
		}
		$type = get_post_type_object( $post_type );
		$selected = 'none';
		if ( isset( $_REQUEST['geo'] ) ) {
			$selected = sanitize_text_field( $_REQUEST['geo'] );
		}
		$list = array(
			'none'      => $type->labels->all_items,
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
		$geo = get_post_geodata();
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
		$geo = get_post_geodata( $post );
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
		$geo = get_post_geodata();
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
	 * Registers REST Fields.
	 *
	 * @since 1.0.0
	 */
	public static function rest_location() {
		register_rest_field(
			array( 'post', 'comment', 'user', 'term' ),
			'latitude',
			array(
				'get_callback' => array( __CLASS__, 'rest_get_latitude' ),
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
				'get_callback' => array( __CLASS__, 'rest_get_longitude' ),
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
				'get_callback' => array( __CLASS__, 'rest_get_address' ),
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
				'get_callback' => array( __CLASS__, 'rest_get_timezone' ),
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
		$id      = sloc_get_id_from_object( $object );
		$geodata = Geo_Data::get_geodata( $object_type, $id );
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
		$id      = sloc_get_id_from_object( $object );
		$geodata = Geo_Data::get_geodata( $object_type, $id );

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
		$id      = sloc_get_id_from_object( $object, $object_type );
		$geodata = Geo_Data::get_geodata( $object_type, $id );

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
		$id      = sloc_get_id_from_object( $object, $object_type );
		$geodata = Geo_Data::get_geodata( $object_type, $id );

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
			try {
				$timezone = timezone_open( $data['timezone'] );
			} catch(Exception $e) {
				$timezone = wp_timezone();
			}
			if ( $timezone && ( ! Loc_Timezone::compare_timezones( wp_timezone(), $timezone  ) ) ) {
				$data['timezone'] = $timezone->getName();
			} else {
				$data['timezone'] = '';
			}
		}

		if ( ! empty( $data['latitude'] ) || ! empty( $data['longitude'] ) || ! empty( $data['address'] ) ) {
			$data['visibility'] = ifset( $data['geo_public'] );
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


		if ( array_key_exists( 'weather_code', $_POST ) ) {
			$weather['code'] = $_POST['weather_code'];
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
		} elseif ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
		}
		if ( has_term( '', 'venue' ) ) {
			return;
		}
		if ( 'venue' !== get_post_type( $post_id ) ) {
			is_day_post( $post_id );
			if ( isset( $_POST['venue_id'] ) && is_numeric( $_POST['venue_id'] ) && 0 !== intval( $_POST['venue_id'] ) ) {
				update_post_meta( $post_id, 'venue_id', intval( $_POST['venue_id'] ) );
			} else {
				delete_post_meta( $post_id, 'venue_id' );
			}
		} else { 
			if ( isset( $_POST['venue_radius'] ) && is_numeric( $_POST['venue_radius'] ) ) {
				update_post_meta( $post_id, 'venue_radius', intval( $_POST['venue_radius'] ) );
			} else {
				delete_post_meta( $post_id, 'venue_radius' );
			}
			if ( isset( $_POST['venue_url'] ) ) {
				update_post_meta( $post_id, 'venue_url', sanitize_url( $_POST['venue_url'] ) );
			} else {
				delete_post_meta( $post_id, 'venue_url' );
			}

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
		is_day_comment( $comment_id );
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

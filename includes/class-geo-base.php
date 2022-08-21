<?php
/**
 * Geographical Metadata
 *
 * Sets up basic support for geo and weather.
 *
 * @package Simple Location
 */

add_action( 'init', array( 'Geo_Base', 'init' ), 1 );

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

		// Add Dropdown.
		add_action( 'restrict_manage_posts', array( __CLASS__, 'geo_posts_dropdown' ), 12, 2 );
		add_action( 'restrict_manage_comments', array( __CLASS__, 'geo_comments_dropdown' ), 12 );
		add_filter( 'manage_posts_columns', array( __CLASS__, 'add_location_admin_column' ) );
		add_action( 'manage_posts_custom_column', array( __CLASS__, 'manage_location_admin_column' ), 10, 2 );

		add_filter( 'bulk_actions-edit-post', array( __CLASS__, 'register_bulk_edit_location' ), 10 );
		add_filter( 'handle_bulk_actions-edit-post', array( __CLASS__, 'handle_bulk_edit_location' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'bulk_action_admin_notices' ) );

		// Add the Same Post Type Support JetPack uses.
		add_post_type_support( 'post', 'geo-location' );
		add_post_type_support( 'page', 'geo-location' );
		add_post_type_support( 'attachment', 'geo-location' );

		add_filter( 'rest_prepare_post', array( __CLASS__, 'rest_prepare_post' ), 10, 3 );
		add_filter( 'rest_prepare_comment', array( __CLASS__, 'rest_prepare_comment' ), 10, 3 );
		add_filter( 'rest_prepare_user', array( __CLASS__, 'rest_prepare_user' ), 10, 3 );

		add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap' ), 1, 4 );

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
			$location   = get_post_geodata( $post_id );
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
				'get_callback' => array( '__CLASS__', 'rest_get_longitude' ),
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
				'get_callback' => array( '__CLASS__', 'rest_get_address' ),
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
				'get_callback' => array( '__CLASS__', 'rest_get_timezone' ),
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
		$geodata = get_geodata( $id, $object_type );
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
		$geodata = get_geodata( $id, $object_type );

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
		$geodata = get_geodata( $object );

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
		$geodata = get_geodata( $object );

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

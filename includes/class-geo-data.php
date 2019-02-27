<?php
/**
 * Geographical Metadata
 *
 * Registers geographic metadata and supplies functions to assist in manipulating it.
 *
 * @package Simple Location
 */

add_action( 'init', array( 'WP_Geo_Data', 'init' ), 1 );
// Jetpack added Location support in 2017 remove it due conflict https://github.com/Automattic/jetpack/pull/9573 which created conflict
add_filter( 'jetpack_tools_to_include', array( 'WP_Geo_Data', 'jetpack_remove' ), 11 );

class WP_Geo_Data {
	public static function init() {
		self::register_meta();
		add_filter( 'query_vars', array( 'WP_Geo_Data', 'query_var' ) );
		add_action( 'pre_get_posts', array( 'WP_Geo_Data', 'pre_get_posts' ) );
		add_action( 'pre_get_comments', array( 'WP_Geo_Data', 'pre_get_comments' ) );

		// Grab geo data from EXIF, if it's available
		$wp_version = get_bloginfo( 'version' );
		if ( version_compare( $wp_version, '5.0', '>=' ) ) {
			add_action( 'wp_read_image_metadata', array( 'WP_Geo_Data', 'exif_data' ), 10, 5 );
		} else {
			add_action( 'wp_read_image_metadata', array( 'WP_Geo_Data', 'exif_data' ), 10, 3 );
		}
		add_action( 'wp_update_attachment_metadata', array( 'WP_Geo_Data', 'attachment' ), 10, 2 );

		self::rewrite();

		add_action( 'rss2_ns', array( 'WP_Geo_Data', 'georss_namespace' ) );
		add_action( 'atom_ns', array( 'WP_Geo_Data', 'georss_namespace' ) );
		add_action( 'rdf_ns', array( 'WP_Geo_Data', 'georss_namespace' ) );

		add_action( 'rss_item', array( 'WP_Geo_Data', 'georss_item' ) );
		add_action( 'rss2_item', array( 'WP_Geo_Data', 'georss_item' ) );
		add_action( 'atom_entry', array( 'WP_Geo_Data', 'georss_item' ) );
		add_action( 'rdf_item', array( 'WP_Geo_Data', 'georss_item' ) );
		add_action( 'json_feed_item', array( 'WP_Geo_Data', 'json_feed_item' ), 10, 2 );
		add_action( 'wp_head', array( 'WP_Geo_Data', 'meta_tags' ) );

		add_action( 'rest_api_init', array( 'WP_Geo_Data', 'rest_location' ) );

		// Add Dropdown
		add_action( 'restrict_manage_posts', array( 'WP_Geo_Data', 'geo_posts_dropdown' ), 12, 2 );
		add_action( 'restrict_manage_comments', array( 'WP_Geo_Data', 'geo_comments_dropdown' ), 12, 2 );

		// Add the Same Post Type Support JetPack uses
		add_post_type_support( 'post', 'geo-location' );
		add_post_type_support( 'page', 'geo-location' );
		add_post_type_support( 'attachment', 'geo-location' );

	}

	public static function jetpack_remove( $tools ) {
		$index = array_search( 'geo-location.php', $tools, true );
		if ( false !== $index ) {
			unset( $tools[ $index ] );
		}
		return $tools;
	}


	public static function geo_public() {
			return array(
				'private'   => esc_html__( 'Private', 'simple-location' ),
				'public'    => esc_html__( 'Public', 'simple-location' ),
				'protected' => esc_html__( 'Protected', 'simple-location' ),
			);
	}

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

	public static function geo_public_select( $public, $echo = false ) {
			$choices = self::geo_public();
			$return  = '';
		foreach ( $choices as $value => $text ) {
				$return .= sprintf( '<option value=%1s %2s>%3s</option>', esc_attr( $value ), selected( $public, $value, false ), $text );
		}
		if ( ! $echo ) {
				return $return;
		}
			echo $return; // phpcs:ignore

	}

	public static function geo_posts_dropdown( $post_type, $which ) {
		if ( 'post' !== $post_type ) {
			return;
		}
			$selected = 'none';
		if ( isset( $_REQUEST['geo'] ) ) {
			$selected = $_REQUEST['geo'];
		}
			$list = array(
				'none' => esc_html__( 'All Posts', 'simple-location' ),
				'all'  => esc_html__( 'With Location', 'simple-location' ),
			);
		echo '<select id="geo" name="geo">';
		foreach ( $list as $key => $value ) {
			$select = ( $key === $selected ) ? ' selected="selected"' : '';
			echo '<option value="' . $key . '"' . selected( $selected, $key ) . '>' . $value . ' </option>'; // phpcs:ignore
		}
		echo '</select>';
	}

	public static function geo_comments_dropdown() {
			$selected = 'none';
		if ( isset( $_REQUEST['geo'] ) ) {
			$selected = $_REQUEST['geo'];
		}
			$list = array(
				'none' => esc_html__( 'All Comments', 'simple-location' ),
				'all'  => esc_html__( 'With Location', 'simple-location' ),
			);
		echo '<select id="geo" name="geo">';
		foreach ( $list as $key => $value ) {
			$select = ( $key === $selected ) ? ' selected="selected"' : '';
			echo '<option value="' . $key . '"' . selected( $selected, $key ) . '>' . $value . ' </option>'; // phpcs:ignore
		}
		echo '</select>';
	}

	public static function georss_namespace() {
		echo PHP_EOL . 'xmlns:georss="http://www.georss.org/georss" xmlns:geo="http://www.w3.org/2003/01/geo/wgs84_pos#" ';
	}

	public static function georss_item() {
		$geo = self::get_geodata();
		if ( ! $geo ) {
			return;
		}

		if ( ! isset( $geo['latitude'] ) || ! isset( $geo['longitude'] ) ) {
			return;
		}

		if ( empty( $geo['visibility'] ) || 'private' === $geo['visibility'] ) {
			return;
		}

		$geo = array_map( 'esc_html', $geo );
		$geo = array_map( 'ent2ncr', $geo );

		echo "\t<georss:point>{$geo['latitude']} {$geo['longitude']}</georss:point>\n"; // phpcs:ignore
		echo "\t\t<geo:lat>{$geo['latitude']}</geo:lat>\n"; // phpcs:ignore
		echo "\t\t<geo:long>{$geo['longitude']}</geo:long>"; // phpcs:ignore
		if ( isset( $geo['address'] ) ) {
			echo "\t\t<geo:featureName>{$geo['address']}</geo:featureName>"; // phpcs:ignore
		}
	}

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
				'coordinates' => array( $geo['longitude'], $geo['latitude'] ),
			),
			'properties' => array(
				'name' => $geo['address'],
			),
		);
		$feed_item['geo'] = $json;
		return $feed_item;
	}

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
		if ( $geo['latitude'] && 'protected' !== $geo['visibility'] ) {
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

	public static function attachment( $meta, $post_id ) {
		$current_user = wp_get_current_user();
		if ( isset( $meta['image_meta'] ) && isset( $meta['image_meta']['location'] ) ) {
			update_post_meta( $post_id, 'geo_latitude', $meta['image_meta']['location']['latitude'] );
			update_post_meta( $post_id, 'geo_longitude', $meta['image_meta']['location']['longitude'] );
		}
		return $meta;
	}

	/* Calculates the distance in meters between two coordinates */

	public static function gc_distance( $lat1, $lng1, $lat2, $lng2 ) {
		return ( 6378100 * acos( cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * cos( deg2rad( $lng2 ) - deg2rad( $lng1 ) ) + sin( deg2rad( $lat1 ) ) * sin( deg2rad( $lat2 ) ) ) );
	}

	/* Advises if new coordinates are within x meters of center */
	public static function in_radius( $lat1, $lng1, $lat2, $lng2, $meters = 50 ) {
		return ( self::gc_distance( $lat1, $lng1, $lat2, $lng2 ) <= $meters );
	}

	public static function exif_data( $meta, $file, $image_type, $iptc = null, $exif = null ) {
		if ( ! is_array( $exif ) && is_callable( 'exif_read_data' ) && in_array( $image_type, apply_filters( 'wp_read_image_metadata_types', array( IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM ) ), true ) ) {
				$exif = @exif_read_data( $file );
		}
		if ( ! empty( $exif['GPSLongitude'] ) && count( $exif['GPSLongitude'] ) === 3 && ! empty( $exif['GPSLongitudeRef'] ) ) {
			$meta['location']['longitude'] = round( ( 'W' === $exif['GPSLongitudeRef'] ? - 1 : 1 ) * wp_exif_gps_convert( $exif['GPSLongitude'] ), 7 );
		}
		if ( ! empty( $exif['GPSLatitude'] ) && count( $exif['GPSLatitude'] ) === 3 && ! empty( $exif['GPSLatitudeRef'] ) ) {
				$meta['location']['latitude'] = round( ( 'S' === $exif['GPSLatitudeRef'] ? - 1 : 1 ) * wp_exif_gps_convert( $exif['GPSLatitude'] ), 7 );
		}
		return $meta;
	}

	public static function rewrite() {
		add_rewrite_endpoint( 'geo', EP_ALL_ARCHIVES | EP_ROOT );
	}

	public static function query_var( $vars ) {
		$vars[] = 'geo';
		return $vars;
	}

	public static function pre_get_posts( $query ) {
		if ( ! array_key_exists( 'geo', $query->query_vars ) ) {
			return;
		}

		$geo  = $query->get( 'geo' );
		$args = array(
			'key'  => 'geo_public',
			'type' => 'numeric',
		);

		switch ( $geo ) {
			case 'all':
				$args['compare'] = '>';
				$args['value']   = (int) 0;
				$query->set( 'meta_query', array( $args ) );
				break;
			case 'public':
			case 'map':
				$args['compare'] = '=';
				$args['value']   = (int) 1;
				$query->set( 'meta_query', array( $args ) );
				break;
			case 'text':
			case 'description':
			case 'protected':
				$args['compare'] = '=';
				$args['value']   = (int) 2;
				$query->set( 'meta_query', array( $args ) );
				break;
			default:
				return;
		}
	}

	public static function pre_get_comments( $query ) {
		if ( ! isset( $_REQUEST['geo'] ) ) {
			return;
		}
		$geo  = $_REQUEST['geo'];
		$args = array(
			'key'  => 'geo_public',
			'type' => 'numeric',
		);

		switch ( $geo ) {
			case 'all':
				$args['compare'] = '>';
				$args['value']   = (int) 0;
				break;
			case 'public':
			case 'map':
				$args['compare'] = '=';
				$args['value']   = (int) 1;
				break;
			case 'text':
			case 'description':
				$args['compare'] = '=';
				$args['value']   = (int) 2;
				break;
			default:
				return;
		}
		$query->query_vars['meta_query'] = array( $args );
		$query->meta_query->parse_query_vars( $query->query_vars );
	}

	public static function sanitize_float( $input ) {
		return filter_var( $input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
	}

	public static function clean_coordinate( $coordinate ) {
		$pattern = '/^(\-)?(\d{1,3})\.(\d{1,15})/';
		preg_match( $pattern, $coordinate, $matches );
		return round( (float) $matches[0], 7 );
	}

	public static function set_geodata( $object = null, $geodata ) {
		if ( ! is_array( $geodata ) ) {
			return false;
		}
		$type    = null;
		$geodata = wp_array_slice_assoc( $geodata, array( 'latitude', 'longitude', 'address', 'map_zoom', 'weather', 'altitude', 'speed', 'heading', 'visibility' ) );
		if ( isset( $geodata['map_zoom'] ) ) {
			$geodata['zoom'] = $geodata['map_zoom'];
			unset( $geodata['map_zoom'] );
		}

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

	private static function get_geometadata( $type, $id ) {
		$geodata               = array();
		$geodata['longitude']  = get_metadata( $type, $id, 'geo_longitude', true );
		$geodata['latitude']   = get_metadata( $type, $id, 'geo_latitude', true );
		$geodata['altitude']   = get_metadata( $type, $id, 'geo_altitude', true );
		$geodata['address']    = get_metadata( $type, $id, 'geo_address', true );
		$geodata['map_zoom']   = get_metadata( $type, $id, 'geo_zoom', true );
		$geodata['weather']    = get_metadata( $type, $id, 'geo_weather', true );
		$geodata               = array_filter( $geodata );
		$geodata['visibility'] = self::get_visibility( $type, $id );
		if ( empty( $geodata['longitude'] ) && empty( $geodata['address'] ) ) {
			return null;
		}
		return array_filter( $geodata );
	}

	public static function has_location( $object = null ) {
		$data = self::get_geodata( $object );
		return ! is_null( $data );
	}


	public static function get_geodata( $object = null ) {
		if ( ! $object ) {
			$object = get_post();
		}
		// If numeric assume post_ID
		if ( is_numeric( $object ) ) {
			$object = get_post( $object );
		}
		if ( $object instanceof WP_Post ) {
			$geodata = self::get_geometadata( 'post', $object->ID );
			if ( ! $geodata ) {
				return null;
			}
			$geodata['ID'] = $object->ID;
			// Remove Old Metadata
			delete_post_meta( $object->ID, 'geo_map' );
			delete_post_meta( $object->ID, 'geo_full' );
			delete_post_meta( $object->ID, 'geo_lookup' );
		}

		if ( $object instanceof WP_Comment ) {
			$geodata = self::get_geometadata( 'comment', $object->comment_ID );
			if ( ! $geodata ) {
				return null;
			}
			$geodata['comment_ID'] = $object->comment_ID;
		}
		if ( $object instanceof WP_Term ) {
			$geodata = self::get_geometadata( 'term', $object->term_id );
			if ( ! $geodata ) {
				return null;
			}
			$geodata['term_id'] = $object->term_id;
		}
		if ( $object instanceof WP_User ) {
			$geodata = self::get_geometadata( 'user', $object->ID );
			if ( ! $geodata ) {
				return null;
			}
			$geodata['user_ID'] = $object->ID;
		}

		/*	if ( empty( $geodata['address'] ) ) {
			if ( empty( $geodata['longitude'] ) ) {
				return null;
			}
			$map = Loc_Config::geo_provider();
			$map->set( $geodata );
			$adr = $map->reverse_lookup();
			if ( array_key_exists( 'display-name', $adr ) ) {
				$geodata['address'] = trim( $adr['display-name'] );
				if ( ! empty( $geodata['address'] ) ) {
					if ( $object instanceof WP_Comment ) {
						update_post_meta( $object->comment_ID, 'geo_address', $geodata['address'] );
						update_post_meta( $object->comment_ID, 'geo_timezone', $adr['timezone'] );
					}
					if ( $object instanceof WP_Post ) {
						update_post_meta( $object->ID, 'geo_address', $geodata['address'] );
						update_post_meta( $object->ID, 'geo_timezone', $adr['timezone'] );
					}
				}
			}
			$geodata['adr'] = $adr;
		} */

		return $geodata;
	}

	public static function register_meta() {
		$args = array(
			'sanitize_callback' => array( 'WP_Geo_Data', 'clean_coordinate' ),
			'type'              => 'float',
			'description'       => 'Latitude',
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'geo_latitude', $args );
		register_meta( 'comment', 'geo_latitude', $args );
		register_meta( 'user', 'geo_latitude', $args );
		register_meta( 'term', 'geo_latitude', $args );

		$args = array(
			'sanitize_callback' => array( 'WP_Geo_Data', 'clean_coordinate' ),
			'type'              => 'float',
			'description'       => 'Longitude',
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'geo_longitude', $args );
		register_meta( 'comment', 'geo_longitude', $args );
		register_meta( 'user', 'geo_longitude', $args );
		register_meta( 'term', 'geo_longitude', $args );

		$args = array(
			'type'         => 'int',
			'description'  => 'Altitude',
			'single'       => true,
			'show_in_rest' => false,
		);
		register_meta( 'post', 'geo_altitude', $args );
		register_meta( 'comment', 'geo_altitude', $args );
		register_meta( 'user', 'geo_altitude', $args );
		register_meta( 'term', 'geo_altitude', $args );

		$args = array(
			'type'         => 'array',
			'description'  => 'Weather Data',
			'single'       => true,
			'show_in_rest' => false,
		);
		register_meta( 'post', 'geo_weather', $args );
		register_meta( 'comment', 'geo_weather', $args );
		register_meta( 'user', 'geo_weather', $args );
		register_meta( 'term', 'geo_weather', $args );

		$args = array(
			'type'         => 'string',
			'description'  => 'Timezone of Location',
			'single'       => true,
			'show_in_rest' => false,
		);
		register_meta( 'post', 'geo_timezone', $args );
		register_meta( 'comment', 'geo_timezone', $args );
		register_meta( 'user', 'geo_timezone', $args );
		register_meta( 'term', 'geo_timezone', $args );

		$args = array(
			'type'         => 'integer',
			'description'  => 'Geodata Zoom for Map Display',
			'single'       => true,
			'show_in_rest' => false,
		);
		register_meta( 'post', 'geo_zoom', $args );
		register_meta( 'comment', 'geo_zoom', $args );
		register_meta( 'user', 'geo_zoom', $args );
		register_meta( 'term', 'geo_zoom', $args );

		$args = array(
			'type'         => 'integer',
			'description'  => 'Geodata Public',
			'single'       => true,
			'show_in_rest' => false,
		);
		// Officially 0 is private 1 is public and absence or non-zero is assumed public.
		// Therefore any non-zero number could be used to specify different display options.
		register_meta( 'post', 'geo_public', $args );
		register_meta( 'comment', 'geo_public', $args );
		register_meta( 'user', 'geo_public', $args );
		register_meta( 'term', 'geo_public', $args );

		$args = array(
			'sanitize_callback' => array( 'WP_Geo_Data', 'sanitize_address' ),
			'type'              => 'string',
			'description'       => 'Geodata Address',
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'geo_address', $args );
		register_meta( 'comment', 'geo_address', $args );
		register_meta( 'user', 'geo_address', $args );
		register_meta( 'term', 'geo_address', $args );
	}


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
			array( 'post', 'comment', 'term' ),
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
			array( 'post', 'comment', 'term' ),
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
			array( 'post', 'comment', 'term' ),
			'geo_public',
			array(
				'get_callback' => array( 'WP_Geo_Data', 'rest_get_visibility' ),
				'schema'       => array(
					'geo_public' => __( 'Location Visibility', 'simple-location' ),
					'type'       => 'string',
				),
			)
		);

	}

	public static function object( $object, $object_type ) {
		if ( ! is_object( $object ) ) {
			return null;
		}
		switch ( $object_type ) {
			case 'post':
				return get_post( $object->ID );
			case 'comment':
				return get_comment( $object->comment_ID );
			case 'user':
				return get_user_by( 'id', $object->ID );
			case 'term':
				return get_term( $object->term_id );
			default:
				return null;
		}
	}

	public static function rest_get_longitude( $object, $attr, $request, $object_type ) {
		$object  = self::object( $object, $object_type );
		$geodata = self::get_geodata( $object );
		if ( empty( $geodata['latitude'] ) ) {
			return '';
		}
		if ( 'public' === $geodata['visibility'] ) {
			return $geodata['longitude'];
		}
		return 'private';
	}

	public static function rest_get_latitude( $object, $attr, $request, $object_type ) {
		$object  = self::object( $object, $object_type );
		$geodata = self::get_geodata( $object );
		if ( empty( $geodata['latitude'] ) ) {
			return '';
		}
		if ( 'public' === $geodata['visibility'] ) {
			return $geodata['latitude'];
		}
		return 'private';
	}

	public static function rest_get_address( $object, $attr, $request, $object_type ) {
		$object  = self::object( $object, $object_type );
		$geodata = self::get_geodata( $object );
		if ( empty( $geodata['address'] ) ) {
			return '';
		}
		if ( in_array( $geodata['visibility'], array( 'public', 'protected' ), true ) ) {
			return $geodata['address'];
		}
		return 'private';
	}

	public static function rest_get_visibility( $object, $attr, $request, $object_type ) {
		$object     = self::object( $object, $object_type );
		$visibility = self::get_visibility( $object_type, $object );
		return $visibility;
	}

	public static function sanitize_address( $data ) {
		$data = wp_kses_post( $data );
		$data = trim( $data );
		if ( empty( $data ) ) {
			$data = null;
		}
		return $data;
	}

}

/**
 * Convert the EXIF geographical longitude and latitude from degrees, minutes
 * and seconds to degrees format.
 * This is part of a Trac Ticket - https://core.trac.wordpress.org/ticket/9257
 * closed due privacy concerns. Updated to match location storage for this just in case
 * and to use their function over my original one.
 *
 *
 * @param string $coordinate The coordinate to convert to degrees format.
 * @return float Coordinate in degrees format.
 */
if ( ! function_exists( 'wp_exif_gps_convert' ) ) {
	function wp_exif_gps_convert( $coordinate ) {
			@list( $degree, $minute, $second ) = $coordinate;
			$float                             = wp_exif_frac2dec( $degree ) + ( wp_exif_frac2dec( $minute ) / 60 ) + ( wp_exif_frac2dec( $second ) / 3600 );

			return ( ( is_float( $float ) || ( is_int( $float ) && $degree === $float ) ) && ( abs( $float ) <= 180 ) ) ? $float : 999;
	}
}

function dec_to_dms( $latitude, $longitude, $altitude = '' ) {
	$latitudedirection  = $latitude < 0 ? 'S' : 'N';
	$longitudedirection = $longitude < 0 ? 'W' : 'E';

	$latitudenotation  = $latitude < 0 ? '-' : '';
	$longitudenotation = $longitude < 0 ? '-' : '';

	$latitudeindegrees  = floor( abs( $latitude ) );
	$longitudeindegrees = floor( abs( $longitude ) );

	$latitudedecimal  = abs( $latitude ) - $latitudeindegrees;
	$longitudedecimal = abs( $longitude ) - $longitudeindegrees;

	$_precision       = 3;
	$latitudeminutes  = round( $latitudedecimal * 60, $_precision );
	$longitudeminutes = round( $longitudedecimal * 60, $_precision );
	if ( ! empty( $altitude ) && is_numeric( $altitude ) ) {
		$altitudedisplay = sprintf( '%1$s%2$s', $altitude, __( 'm', 'simple-location' ) );
	} else {
		$altitudedisplay = '';
	}
	return sprintf(
		'%s%s° %s %s %s%s° %s %s%s',
		$latitudenotation,
		$latitudeindegrees,
		$latitudeminutes,
		$latitudedirection,
		$longitudenotation,
		$longitudeindegrees,
		$longitudeminutes,
		$longitudedirection,
		$altitudedisplay
	);
}

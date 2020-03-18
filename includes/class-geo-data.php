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
		$cls = get_called_class();
		self::register_meta();
		add_filter( 'query_vars', array( $cls, 'query_var' ) );
		add_filter( 'template_include', array( $cls, 'map_archive_template' ) );

		add_action( 'pre_get_posts', array( $cls, 'remove_maps_pagination' ) );
		add_action( 'pre_get_posts', array( $cls, 'filter_location_posts' ) );
		add_action( 'pre_get_comments', array( $cls, 'pre_get_comments' ) );

		// Grab geo data from EXIF, if it's available
		$wp_version = get_bloginfo( 'version' );
		if ( version_compare( $wp_version, '5.0', '>=' ) ) {
			add_action( 'wp_read_image_metadata', array( $cls, 'exif_data' ), 10, 5 );
		} else {
			add_action( 'wp_read_image_metadata', array( $cls, 'exif_data' ), 10, 3 );
		}
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

		// Add Dropdown
		add_action( 'restrict_manage_posts', array( $cls, 'geo_posts_dropdown' ), 12, 2 );
		add_action( 'restrict_manage_comments', array( $cls, 'geo_comments_dropdown' ), 12, 2 );
		add_filter( 'manage_posts_columns', array( $cls, 'add_location_admin_column' ) );
		add_action( 'manage_posts_custom_column', array( $cls, 'manage_location_admin_column' ), 10, 2 );

		add_filter( 'bulk_actions-edit-post', array( $cls, 'register_bulk_edit_location' ), 10 );
		add_filter( 'handle_bulk_actions-edit-post', array( $cls, 'handle_bulk_edit_location' ), 10, 3 );

		// Add the Same Post Type Support JetPack uses
		add_post_type_support( 'post', 'geo-location' );
		add_post_type_support( 'page', 'geo-location' );
		add_post_type_support( 'attachment', 'geo-location' );

	}

	public static function remove_maps_pagination( $query ) {
		if ( ! array_key_exists( 'map', $query->query_vars ) ) {
			return;
		}
		$query->set( 'meta_query', array( self::filter_geo_query( 'map' ) ) );
		$query->set( 'posts_per_page', SLOC_PER_PAGE );
		$query->set( 'order', 'ASC' );
	}

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

	public static function location_list() {
		global $wp_query;
		$post_ids = wp_list_pluck( $wp_query->posts, 'ID' );
		return $post_ids;
	}

	public static function get_archive_public_location_list() {
		global $wp_query;
		$locations = array();
		if ( empty( $wp_query->posts ) ) {
			return '';
		}
		foreach ( $wp_query->posts as $post ) {
			$location = self::get_geodata( $post, false );
			if ( 'public' === $location['visibility'] ) {
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

	public static function register_bulk_edit_location( $actions ) {
		$actions['location_public']  = __( 'Public Location', 'simple-location' );
		$actions['location_private'] = __( 'Private Location', 'simple-location' );
		return $actions;
	}

	public static function handle_bulk_edit_location( $redirect_to, $doaction, $post_ids ) {
		if ( in_array( $doaction, array( 'location_public', 'location_private' ), true ) ) {
			$visibility = str_replace( 'location_', '', $doaction );
			foreach ( $post_ids as $post_id ) {
				self::set_visibility( 'post', $post_id, $visibility );
			}
		}
		return $redirect_to;
	}




	public static function add_location_admin_column( $columns ) {
		$columns['location'] = __( 'Location', 'simple-location' );
		return $columns;
	}

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
			'none'      => esc_html__( 'All Posts', 'simple-location' ),
			'all'       => esc_html__( 'With Location', 'simple-location' ),
			'private'   => esc_html__( 'Private', 'simple-location' ),
			'public'    => esc_html__( 'Public', 'simple-location' ),
			'protected' => esc_html__( 'Protected', 'simple-location' ),
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
			'none'      => esc_html__( 'All Comments', 'simple-location' ),
			'all'       => esc_html__( 'With Location', 'simple-location' ),
			'private'   => esc_html__( 'Private', 'simple-location' ),
			'public'    => esc_html__( 'Public', 'simple-location' ),
			'protected' => esc_html__( 'Protected', 'simple-location' ),
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

		if ( empty( $geo['visibility'] ) || 'public' !== $geo['visibility'] ) {
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
			printf(  __( 'Created on: %s', 'simple-location' ), '<b>' . $created_on . '</b>' ); // phpcs:ignore
			echo '</div>';
		}
	}

	public static function attachment_fields_to_edit( $form_fields, $post ) {
		$geodata                    = self::get_geodata( $post );
		$form_fields['geo_address'] = array(
			'value'        => $geodata['address'],
			'label'        => __( 'Location', 'simple-location' ),
			'input'        => 'html',
			'show_in_edit' => false,
			'html'         => sprintf( '<span>%1$s</span>', $geodata['address'] ),
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

	/* Calculates the distance in meters between two coordinates */

	public static function gc_distance( $lat1, $lng1, $lat2, $lng2 ) {
		$lat1 = floatval( $lat1 );
		$lng1 = floatval( $lng1 );
		$lat2 = floatval( $lat2 );
		$lng2 = floatval( $lng2 );
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
		// If there is no Exif Version set return
		if ( ! $exif['ExifVersion'] ) {
			return $meta;
		}
		$version             = (int) $exif['ExifVersion'];
		$meta['ExifVersion'] = sanitize_text_field( $exif['ExifVersion'] );
		if ( $version < 232 ) {
			if ( ! empty( $exif['GPSLongitude'] ) && count( $exif['GPSLongitude'] ) === 3 && ! empty( $exif['GPSLongitudeRef'] ) ) {
				$meta['location']['longitude'] = round( ( 'W' === $exif['GPSLongitudeRef'] ? - 1 : 1 ) * wp_exif_gps_convert( $exif['GPSLongitude'] ), 7 );
			}
			if ( ! empty( $exif['GPSLatitude'] ) && count( $exif['GPSLatitude'] ) === 3 && ! empty( $exif['GPSLatitudeRef'] ) ) {
				$meta['location']['latitude'] = round( ( 'S' === $exif['GPSLatitudeRef'] ? - 1 : 1 ) * wp_exif_gps_convert( $exif['GPSLatitude'] ), 7 );
			}
			$datetime = null;
			if ( 231 === $version ) {
				foreach (
					array(
						'DateTimeOriginal'  => 'UndefinedTag:0x9011',
						'DateTimeDigitized' => 'UndefinedTag:0x9012',
					)
					as $time => $offset
				) {
					if ( ! empty( $exif[ $time ] ) && ! empty( $exif[ $offset ] ) ) {
						$datetime = wp_exif_datetime( $exif[ $time ], $exif[ $offset ] );
						break;
					}
				}
			} else {
				if ( ! empty( $meta['location'] ) ) {
					// Try to get the right timezone from the location
					$timezone = Loc_Timezone::timezone_for_location( $meta['location']['latitude'], $meta['location']['longitude'] );
				} else {
					$timezone = wp_timezone();
				}
				if ( ! empty( $exif['DateTimeOriginal'] ) ) {
					$datetime = wp_exif_datetime( $exif['DateTimeOriginal'], $timezone );
				} elseif ( ! empty( $exif['DateTimeDigitized'] ) ) {
					$datetime = wp_exif_datetime( $exif['DateTimeDigitized'], $timezone );
				}
			}
			if ( $datetime ) {
				$meta['created_timestamp'] = $datetime->getTimestamp();
				$meta['created']           = $datetime->format( DATE_W3C );
			}
		} elseif ( 232 === $version ) {
			if ( ! empty( $exif['DateTimeOriginal'] ) ) {
				$datetime = new DateTime( $exif['DateTimeOriginal'] );
			} elseif ( ! empty( $exif['DateTimeDigitized'] ) ) {
				$datetime = new DateTime( $exif['DateTimeDigitized'] );
			}
			if ( $datetime ) {
				$meta['created']           = $datetime->getTimestamp();
				$meta['created_timestamp'] = $datetime->format( DATE_W3C );
			}
		}
		if ( ! empty( $exif['GPSAltitude'] ) ) {
			$meta['location']['altitude'] = wp_exif_frac2dec( $exif['GPSAltitude'] ) * ( 1 === $exif['GPSAltitudeRef'] ? -1 : 1 );
		}
		return $meta;
	}

	public static function rewrite() {
		add_rewrite_endpoint( 'geo', EP_ALL_ARCHIVES );
		add_rewrite_endpoint( 'map', EP_ALL_ARCHIVES );
	}

	public static function query_var( $vars ) {
		$vars[] = 'geo';
		$vars[] = 'map';
		return $vars;
	}

	// Return meta query arguments based on input
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

	public static function filter_location_posts( $query ) {
		if ( ! array_key_exists( 'geo', $query->query_vars ) ) {
			return;
		}

		$geo  = $query->get( 'geo' );
		$args = self::filter_geo_query( $geo );
		if ( ! empty( $args ) ) {
			$query->set( 'meta_query', array( $args ) );
		}
	}

	public static function pre_get_comments( $query ) {
		if ( ! isset( $_REQUEST['geo'] ) ) {
			return;
		}
		$geo  = $_REQUEST['geo'];
		$args = self::filter_geo_query( $geo );
		if ( ! empty( $args ) ) {
			$query->query_vars['meta_query'] = array( $args );
			$query->meta_query->parse_query_vars( $query->query_vars );
		}
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
		$geodata = wp_array_slice_assoc( $geodata, array( 'latitude', 'longitude', 'address', 'map_zoom', 'weather', 'altitude', 'speed', 'heading', 'visibility', 'timezone' ) );
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

	private static function get_geometadata( $type, $id, $full = true ) {
		$geodata               = array();
		$geodata['longitude']  = get_metadata( $type, $id, 'geo_longitude', true );
		$geodata['latitude']   = get_metadata( $type, $id, 'geo_latitude', true );
		$geodata['altitude']   = get_metadata( $type, $id, 'geo_altitude', true );
		$geodata['address']    = get_metadata( $type, $id, 'geo_address', true );
		$geodata['visibility'] = self::get_visibility( $type, $id );

		if ( $full ) {
			$geodata['timezone'] = get_metadata( $type, $id, 'geo_timezone', true );
			$geodata['map_zoom'] = get_metadata( $type, $id, 'geo_zoom', true );
			$geodata['weather']  = get_metadata( $type, $id, 'geo_weather', true );
		}
		$geodata = array_filter( $geodata );
		if ( empty( $geodata['longitude'] ) && empty( $geodata['address'] ) ) {
			return null;
		}
		return array_filter( $geodata );
	}

	public static function has_location( $object = null ) {
		$data = self::get_geodata( $object );
		return ! is_null( $data );
	}

	public static function get_geodata( $object = null, $full = true ) {
		if ( ! $object ) {
			$object = get_post();
		}
		// If numeric assume post_ID
		if ( is_numeric( $object ) ) {
			$object = get_post( $object );
		}
		if ( $object instanceof WP_Post ) {
			$geodata = self::get_geometadata( 'post', $object->ID, $full );
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
			'type'              => 'number',
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
			'type'              => 'number',
			'description'       => 'Longitude',
			'single'            => true,
			'show_in_rest'      => false,
		);
		register_meta( 'post', 'geo_longitude', $args );
		register_meta( 'comment', 'geo_longitude', $args );
		register_meta( 'user', 'geo_longitude', $args );
		register_meta( 'term', 'geo_longitude', $args );

		$args = array(
			'type'         => 'number',
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
			'type'         => 'number',
			'description'  => 'Geodata Zoom for Map Display',
			'single'       => true,
			'show_in_rest' => false,
		);
		register_meta( 'post', 'geo_zoom', $args );
		register_meta( 'comment', 'geo_zoom', $args );
		register_meta( 'user', 'geo_zoom', $args );
		register_meta( 'term', 'geo_zoom', $args );

		$args = array(
			'type'         => 'number',
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
 * This is based on a Trac Ticket - https://core.trac.wordpress.org/ticket/9257
 * closed due privacy concerns. Updated to match location storage for this just in case
 * and to use their function over my original one.
 *
 *
 * @param array|string $coordinate The coordinate to convert to degrees format.
 * @return float|false Coordinate in degrees format or false if failure
 */
if ( ! function_exists( 'wp_exif_gps_convert' ) ) {
	function wp_exif_gps_convert( $coordinate ) {
		if ( is_array( $coordinate ) ) {
			@list( $degree, $minute, $second ) = $coordinate;
			$float                             = wp_exif_frac2dec( $degree ) + ( wp_exif_frac2dec( $minute ) / 60 ) + ( wp_exif_frac2dec( $second ) / 3600 );

			return ( ( is_float( $float ) || ( is_int( $float ) && $degree === $float ) ) && ( abs( $float ) <= 180 ) ) ? $float : 999;
		}
		return false;
	}
}


/**
 * Convert the exif date format to a datetime object
 *
 *
 * @param string $str
 * @param string|DateTimeZone $timezone A timezone or offset string. Default is the WordPress timezone
 * @return DateTime
 */
if ( ! function_exists( 'wp_exif_datetime' ) ) {
	function wp_exif_datetime( $str, $timezone = null ) {
		if ( is_string( $timezone ) ) {
			$timezone = new DateTimeZone( $timezone );
		} elseif ( ! $timezone instanceof DateTimeZone ) {
			$timezone = wp_timezone();
		}
		$datetime = new DateTime( $str, $timezone );
		return $datetime;
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

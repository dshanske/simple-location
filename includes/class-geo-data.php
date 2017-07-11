<?php
/**
 * Geographical Metadata
 *
 * Registers geographic metadata and supplies functions to assist in manipulating it.
 *
 * @package Simple Location
 */

add_action( 'init', array( 'WP_Geo_Data', 'init' ), 1 );

class WP_Geo_Data {
	public static function init() {
		self::register_meta();
		add_filter( 'query_vars', array( 'WP_Geo_Data', 'query_var' ) );
		add_action( 'pre_get_posts', array( 'WP_Geo_Data', 'pre_get_posts' ) );
		add_action( 'save_post', array( 'WP_Geo_Data', 'public_post' ), 99, 2 );
		self::rewrite();
	}

	// Set Posts Added by Means other than the Post UI to the system default if not set
	public static function public_post( $post_id, $post ) {
		$public = get_post_meta( $post_id, 'geo_public' );
		if ( ! $public ) {
			add_post_meta( $post_id, 'geo_public', get_option( 'geo_public' ) );
		}
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

		$geo = $query->get( 'geo' );
		$args = array(
			'key'     => 'geo_public',
			'type'    => 'numeric',
			);

		switch ( $geo ) {
			case 'all' :
				$args['compare'] = '>';
				$args['value'] = (int) 0;
				$query->set( 'meta_query', array( $args ) );
			break;
			case 'public':
			case 'map':
				$args['compare'] = '=';
				$args['value'] = (int) 1;
				$query->set( 'meta_query', array( $args ) );
			break;
			case 'text':
			case 'description':
				$args['compare'] = '=';
				$args['value'] = (int) 2;
				$query->set( 'meta_query', array( $args ) );
			break;
			default:
			return;
		}
	}

	public static function clean_coordinate( $coordinate ) {
		$pattern = '/^(\-)?(\d{1,3})\.(\d{1,15})/';
		preg_match( $pattern, $coordinate, $matches );
		return $matches[0];
	}

	public static function get_geodata( $object = null ) {
		$geodata = array();
		if ( ! $object ) {
			$object = get_post();
		}
		// If numeric assume post_ID
		if ( is_numeric( $object ) ) {
			$object = get_post( $object );
		}
		if ( $object instanceof WP_Post ) {
			$geodata['longitude'] = get_post_meta( $object->ID, 'geo_longitude', true );
			$geodata['latitude'] = get_post_meta( $object->ID, 'geo_latitude', true );
			$geodata['address'] = get_post_meta( $object->ID, 'geo_address', true );
			if ( empty( $geodata['longitude'] ) && empty( $geodata['address'] ) ) {
				return null;
			}
			$geodata['public'] = get_post_meta( $object->ID, 'geo_public', true );
			$geodata['ID'] = $object->ID;
			// Remove Old Metadata
			delete_post_meta( $object->ID, 'geo_map' );
			delete_post_meta( $object->ID, 'geo_full' );
			delete_post_meta( $object->ID, 'geo_lookup' );
		}

		if ( $object instanceof WP_Comment ) {
			$geodata['longitude'] = get_comment_meta( $object->comment_ID, 'geo_longitude', true );
			$geodata['latitude'] = get_comment_meta( $object->comment_ID, 'geo_latitude', true );
			$geodata['address'] = get_comment_meta( $object->comment_ID, 'geo_address', true );
			if ( empty( $geodata['longitude'] ) && empty( $geodata['address'] ) ) {
				return null;
			}
			$geodata['public'] = get_comment_meta( $object->comment_ID, 'geo_public', true );
			$geodata['comment_ID'] = $object->comment_ID;
		}
		if ( $object instanceof WP_Term ) {
			$geodata['longitude'] = get_term_meta( $object->term_id, 'geo_longitude', true );
			$geodata['latitude'] = get_term_meta( $object->term_id, 'geo_latitude', true );
			$geodata['address'] = get_term_meta( $object->term_id, 'geo_address', true );
			if ( empty( $geodata['longitude'] ) && empty( $geodata['address'] ) ) {
				return null;
			}
			$geodata['public'] = get_term_meta( $object->term_id, 'geo_public', true );
			$geodata['term_id'] = $object->term_id;
		}
		if ( $object instanceof WP_User ) {
			$geodata['longitude'] = get_user_meta( $object->ID, 'geo_longitude', true );
			$geodata['latitude'] = get_user_meta( $object->ID, 'geo_latitude', true );
			$geodata['address'] = get_user_meta( $object->ID, 'geo_address', true );
			if ( empty( $geodata['longitude'] ) && empty( $geodata['address'] ) ) {
				return null;
			}
			$geodata['public'] = get_user_meta( $object->ID, 'geo_public', true );
			$geodata['user_ID'] = $object->ID;
		}

		if ( empty( $geodata['address'] ) ) {
			if ( empty( $geodata['longitude'] ) ) {
				return null;
			}
			$map = Loc_Config::default_reverse_provider();
			$map->set( $geodata['latitude'], $geodata['longitude'] );
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
		}

		// Set using global default
		if ( ! array_key_exists( 'public', $geodata ) ) {
			$geodata['public'] = get_option( 'geo_public' );
		} else {
			if ( 3 === $geodata['public'] ) {
				$geodata['public'] = 2;
			}
		}
		return $geodata;
	}

	public static function register_meta() {
		$args = array(
				'sanitize_callback' => array( 'WP_Geo_Data', 'clean_coordinate' ),
				'type' => 'float',
				'description' => 'Latitude',
				'single' => true,
				'show_in_rest' => false,
		);
		register_meta( 'post', 'geo_latitude', $args );
		register_meta( 'comment', 'geo_latitude', $args );
		register_meta( 'user', 'geo_latitude', $args );
		register_meta( 'term', 'geo_latitude', $args );

		$args = array(
				'sanitize_callback' => array( 'WP_Geo_Data', 'clean_coordinate' ),
				'type' => 'float',
				'description' => 'Longitude',
				'single' => true,
				'show_in_rest' => false,
		);
		register_meta( 'post', 'geo_longitude', $args );
		register_meta( 'comment', 'geo_longitude', $args );
		register_meta( 'user', 'geo_longitude', $args );
		register_meta( 'term', 'geo_longitude', $args );

		$args = array(
				'type' => 'string',
				'description' => 'Timezone of Location',
				'single' => true,
				'show_in_rest' => false,
		);
		register_meta( 'post', 'geo_timezone', $args );
		register_meta( 'comment', 'geo_timezone', $args );
		register_meta( 'user', 'geo_timezone', $args );
		register_meta( 'term', 'geo_timezone', $args );

		$args = array(
		//		'sanitize_callback' => '',
				'type' => 'integer',
				'description' => 'Geodata Zoom for Map Display',
				'single' => true,
				'show_in_rest' => false,
		);
		register_meta( 'post', 'geo_zoom', $args );
		register_meta( 'comment', 'geo_zoom', $args );
		register_meta( 'user', 'geo_zoom', $args );
		register_meta( 'term', 'geo_zoom', $args );

		$args = array(
		//		'sanitize_callback' => '',
				'type' => 'integer',
				'description' => 'Geodata Public',
				'single' => true,
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
				'type' => 'string',
				'description' => 'Geodata Address',
				'single' => true,
				'show_in_rest' => false,
		);
		register_meta( 'post', 'geo_address', $args );
		register_meta( 'comment', 'geo_address', $args );
		register_meta( 'user', 'geo_address', $args );
		register_meta( 'term', 'geo_address', $args );
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

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
		self::rewrite();
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
		$args =    array(
			'key'     => 'geo_public',
			'type'    => 'numeric',
			);

		switch ( $geo ) {
		case 'all' :
			$args['compare'] = '>';
			$args['value'] = (int) 0;
			$query->set('meta_query', array( $args ) );
			break;
		case 'public':
			$args['compare'] = '=';
			$args['value'] = (int) 1;
			$query->set('meta_query', array( $args ) );
			break;
		case 'text':
			$args['compare'] = '=';
			$args['value'] = (int) 2;
			$query->set('meta_query', array( $args ) );
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

	public static function get_geodata( $post_ID = false ) {
		if ( ! $post_ID ) {
			$post_ID = get_the_ID();
		}
		$geodata = array();

		$geodata['longitude'] = get_post_meta( $post_ID, 'geo_longitude', true );

		$geodata['latitude'] = get_post_meta( $post_ID, 'geo_latitude', true );
		$geodata['address'] = get_post_meta( $post_ID, 'geo_address', true );
		if ( empty( $geodata['longitude'] ) && empty( $geodata['address'] ) ) {
			return null;
		}
		$geodata['public'] = get_post_meta( $post_ID, 'geo_public', true );
		$geodata['ID'] = $post_ID;

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
					update_post_meta( $post_ID, 'geo_address', $geodata['address'] );
					update_post_meta( $post_ID, 'geo_timezone', $adr['timezone'] );
				}
			}
			$geodata['adr'] = $adr;
			// Remove Old Metadata
			delete_post_meta( $post_ID, 'geo_map' );
			delete_post_meta( $post_ID, 'geo_full' );
			delete_post_meta( $post_ID, 'geo_lookup' );
		}

		// Behavior Based on the Absence of the geo_public flag
		if ( ! array_key_exists( 'public', $geodata ) ) {
			$geodata['public'] = apply_filters( 'geo_public_default', SLOC_PUBLIC );
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

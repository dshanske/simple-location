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
		// Sets Latitude and Address but if either is not set returns a failure	
		if ( ( ! $geodata['latitude'] = get_post_meta( $post_ID, 'geo_latitude', true ) ) || ( ! $geodata['address'] = get_post_meta( $post_ID, 'geo_address', true ) ) ) {
			return null;
		}
		$geodata['public'] = get_post_meta( $post_ID, 'geo_public', true );
		$geodata['ID'] = $post_ID;

		$adr = get_post_meta( $post_ID, 'mf2_adr', true );

		// This indicates an old Simple Location storage format
		if ( is_array( $adr ) ) {
			if ( ! $geodata['address'] ) {
				$map = new Geo_Provider_OSM();
				$map->set( $geodata['latitude'], $geodata['longitude'] );
				$geodata['adr'] = $map->reverse_lookup();
				if ( array_key_exists( 'display-name', $adr ) ) {
					$geodata['address'] = $adr['display-name'];
					update_post_meta( $post_ID, 'geo_address', $geodata['address'] );
				}
			}
			// Remove Old Metadata
			delete_post_meta( $post_ID, 'mf2_adr' );
			delete_post_meta( $post_ID, 'geo_map' );
			delete_post_meta( $post_ID, 'geo_full' );
			delete_post_meta( $post_ID, 'geo_lookup' );
		}

		// Assume the absence of a public is the same as public
		if ( ! array_key_exists( 'public', $geodata ) ) {
			$geodata['public'] = 1;
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
				'sanitize_callback' => 'wp_kses_data',
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


}

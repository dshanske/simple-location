<?php
/**
 * 
 *
 * Passes and Returns Geodata
 */

class REST_Geo {
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'localize_script' ), 11 );

	}

	public function localize_script() {
		// Provide a global object to our JS file containing our REST API endpoint, and API nonce
		// Nonce must be 'wp_rest'
		wp_localize_script( 'sloc_location', 'sloc',
			array(
				'api_nonce' => wp_create_nonce( 'wp_rest' ),
				'api_url'   => rest_url( '/sloc_geo/1.0/' ),
			)
		);
	}

	/**
	 * Register the Route.
	 */
	public function register_routes() {
		register_rest_route( 'sloc_geo/1.0', '/reverse', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'reverse' ),
				'args'  => array(
					'longitude'  => array(
						'required' => true,
					),
					'latitude' => array(
						'required' => true,
					),
				),
			),
		) );
		register_rest_route( 'sloc_geo/1.0', '/map', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'map' ),
				'args'  => array(
					'longitude'  => array(
						'required' => true,
					),
					'latitude' => array(
						'required' => true,
					),
				),
			),
		) );
	}

	/**
	 * Returns if valid URL for REST validation
	 *
	 * @param string $url
	 *
	 * @return boolean
	 */
	public static function is_valid_url($url, $request, $key) {
		if ( ! is_string( $url ) || empty( $url ) ) {
			return false;
		}
		return filter_var( $url, FILTER_VALIDATE_URL );
	}

	// Callback Handler for Map Retrieval
	public static function map( $request ) {
		// We don't need to specifically check the nonce like with admin-ajax. It is handled by the API.
		$params = $request->get_params();
		if ( ! empty( $params['longitude'] ) && ! empty( $params['latitude'] ) ) {
			$map = Loc_Config::default_map_provider();
			$map->set( $params['latitude'], $params['longitude'] );
			return $map->get_the_map();
		}
		return new WP_Error( 'missing_geo' , __( 'Missing Coordinates for Reverse Lookup' , 'simple-location' ), array( 'status' => 400 ) );
	}


	// Callback Handler for Reverse Lookup
	public static function reverse( $request ) {
		// We don't need to specifically check the nonce like with admin-ajax. It is handled by the API.
		$params = $request->get_params();
		if ( ! empty( $params['longitude'] ) && ! empty( $params['latitude'] ) ) {
			$reverse = Loc_Config::default_reverse_provider();
			$reverse->set( $params['latitude'], $params['longitude'] );
			$reverse_adr = $reverse->reverse_lookup();
			if ( is_wp_error( $reverse_adr ) ) {
				return $response;
			}
			return array_filter( $reverse_adr );
		}
		return new WP_Error( 'missing_geo' , __( 'Missing Coordinates for Reverse Lookup' , 'simple-location' ), array( 'status' => 400 ) );
	}

}
?>

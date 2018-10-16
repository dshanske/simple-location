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
		wp_localize_script(
			'sloc_location',
			'sloc',
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
		register_rest_route(
			'sloc_geo/1.0',
			'/reverse',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'reverse' ),
					'args'                => array(
						'longitude' => array(
							'required' => true,
						),
						'latitude'  => array(
							'required' => true,
						),
					),
					'permission_callback' => function() {
							return current_user_can( 'publish_posts' );
					},
				),
			)
		);
		register_rest_route(
			'sloc_geo/1.0',
			'/weather',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'weather' ),
					'args'                => array(
						'longitude' => array(),
						'latitude'  => array(),
						'units'     => array(),
					),
					'permission_callback' => function() {
						return current_user_can( 'publish_posts' );
					},
				),
			)
		);
		register_rest_route(
			'sloc_geo/1.0',
			'/lookup',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'lookup' ),
					'args'                => array(
						'user' => array(
							'required' => true,
						),
					),
					'permission_callback' => function() {
						return current_user_can( 'publish_posts' );
					},
				),
			)
		);
		register_rest_route(
			'sloc_geo/1.0',
			'/map',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'map' ),
					'args'                => array(
						'longitude' => array(
							'required' => true,
						),
						'latitude'  => array(
							'required' => true,
						),
						'height'    => array(),
						'width'     => array(),
						'zoom'      => array(),
						// If url exists return URL
						'url'       => array(),
						// If map exists return image src
						'map'       => array(),
					),
					'permission_callback' => function() {
						return current_user_can( 'publish_posts' );
					},
				),
			)
		);
	}

	/**
	 * Returns if valid URL for REST validation
	 *
	 * @param string $url
	 *
	 * @return boolean
	 */
	public static function is_valid_url( $url, $request, $key ) {
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
			$args = array(
				'latitude'  => $params['latitude'],
				'longitude' => $param['longitude'],
			);
			if ( ! empty( $params['zoom'] ) ) {
				$args['map_zoom'] = $params['zoom'];
			}
			if ( ! empty( $params['height'] ) ) {
				$args['height'] = $params['height'];
			}
			if ( ! empty( $params['width'] ) ) {
				$args['width'] = $params['width'];
			}

			$map = Loc_Config::default_map_provider( $args );
			if ( ! empty( $params['url'] ) ) {
				return $map->get_the_map_url();
			}
			if ( ! empty( $params['map'] ) ) {
				return $map->get_the_static_map();
			}
			return $map->get_the_map();
		}
		return new WP_Error( 'missing_geo', __( 'Missing Coordinates for Reverse Lookup', 'simple-location' ), array( 'status' => 400 ) );
	}

	// Callback Handler for Geolocation Retrieval
	public static function lookup( $request ) {
		$params       = $request->get_params();
		$args['user'] = $params['user'];
		$geolocation  = Loc_Config::default_geolocation_provider( $args );
		if ( $geolocation ) {
			$geolocation->retrieve();
			return $geolocation->get();
		}
		return new WP_Error( 'no_provider', __( 'No Geolocation Provider Available', 'simple-location' ), array( 'status' => 400 ) );
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
				return $reverse_adr;
			}
			return array_filter( $reverse_adr );
		}
		return new WP_Error( 'missing_geo', __( 'Missing Coordinates for Reverse Lookup', 'simple-location' ), array( 'status' => 400 ) );
	}

	// Callback Handler for Weather
	public static function weather( $request ) {
		// We don't need to specifically check the nonce like with admin-ajax. It is handled by the API.
		$params = $request->get_params();
		$args   = array(
			'cache_key'  => 'slocw',
			'cache_time' => 600,
		);
		if ( isset( $params['units'] ) ) {
			$args['temp_units'] = $params['units'];
		}
		$return  = array();
		$weather = Loc_Config::default_weather_provider( $args );
		if ( ! empty( $params['longitude'] ) && ! empty( $params['latitude'] ) ) {
			$weather->set_location( $params['latitude'], $params['longitude'] );
			$return = array(
				'latitude'  => $params['latitude'],
				'longitude' => $params['longitude'],
			);
		} elseif ( ! $weather->get_station() ) {
				return new WP_Error( 'missing_geo', __( 'Missing Coordinates or Station for Weather Lookup', 'simple-location' ), array( 'status' => 400 ) );
		}
		$conditions = $weather->get_conditions();
		$return     = array_filter( $return );
		return array_merge( $conditions, $return );
	}



}


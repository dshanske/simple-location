<?php
/**
 *
 *
 * Passes and Returns Geodata
 */

class REST_Geo {
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the Route.
	 */
	public function register_routes() {
		register_rest_route(
			'sloc_geo/1.0',
			'/timezone',
			array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'timezone' ),
					'args'     => array(
						'longitude' => array(),
						'latitude'  => array(),
						'airport'   => array(),
					),
				),
			)
		);
		register_rest_route(
			'sloc_geo/1.0',
			'/geocode',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'geocode' ),
					'args'                => array(
						'longitude' => array(),
						'latitude'  => array(),
						'altitude'  => array(),
						'address'   => array(),
						'weather'   => array(),
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
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( $this, 'weather' ),
					'args'     => array(
						'longitude' => array(),
						'latitude'  => array(),
						'station'   => array(),
					),
					/* 'permission_callback' => function() {
						return current_user_can( 'publish_posts' );
					}, */
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

			$map = Loc_Config::map_provider();
			$map->set( $args );
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
		$geolocation  = Loc_Config::geolocation_provider();
		if ( is_object( $geolocation ) ) {
			$geolocation->set( $args );
			$geolocation->retrieve();
			return $geolocation->get();
		} elseif ( 'null' === $geolocation ) {
			return $geolocation;
		}
		return new WP_Error( 'no_provider', __( 'No Geolocation Provider Available', 'simple-location' ), array( 'status' => 400 ) );
	}

	public static function geocode( $request ) {
		// We dont need to check the nonce like with admin-ajax.
		$params = $request->get_params();
		if ( ! empty( $params['longitude'] ) && ! empty( $params['latitude'] ) ) {
			$reverse = Loc_Config::geo_provider();
			$reverse->set( $params );
			$reverse_adr = $reverse->reverse_lookup();
			if ( is_wp_error( $reverse_adr ) ) {
				return $reverse_adr;
			}
			if ( isset( $params['altitude'] ) && 0 !== $params['altitude'] ) {
				$reverse_adr['altitude'] = $reverse->elevation();
			}
			if ( isset( $params['weather'] ) ) {
				$weather = Loc_Config::weather_provider();
				$weather->set( $params );
				$reverse_adr['weather'] = $weather->get_conditions();
			}
			return array_filter( $reverse_adr );
		}
		return new WP_Error( 'missing_params', __( 'Missing Arguments', 'simple-location' ), array( 'status' => 400 ) );
	}

	// Callback Handler for Weather
	public static function weather( $request ) {
		// We don't need to specifically check the nonce like with admin-ajax. It is handled by the API.
		$params  = $request->get_params();
		$args    = array(
			'cache_key'  => 'slocw',
			'cache_time' => 600,
		);
		$return  = array();
		$weather = Loc_Config::weather_provider();
		if ( isset( $params['station'] ) ) {
			$weather->set( array( 'station_id' => $params['station'] ) );
		} elseif ( ! empty( $params['longitude'] ) && ! empty( $params['latitude'] ) ) {
			$weather->set( $params );
			$timezone = Loc_Timezone::timezone_for_location( $params['latitude'], $params['longitude'] );
			$return   = array(
				'latitude'  => $params['latitude'],
				'longitude' => $params['longitude'],
			);
		} else {
				return new WP_Error( 'missing_geo', __( 'Missing Coordinates or Station for Weather Lookup', 'simple-location' ), array( 'status' => 400 ) );
		}
		$conditions = $weather->get_conditions();
		$return     = array_filter( $return );
		if ( is_array( $conditions ) ) {
			$return = array_merge( $conditions, $return );
		}
		return $return;
	}

	// Callback handler for timezone
	public static function timezone( $request ) {
		$params = $request->get_params();
		$return = array();
		if ( isset( $params['airport'] ) ) {
			$return = Airport_Location::get( $params['airport'] );
			if ( $return ) {
				$params['latitude']  = $return['latitude'];
				$params['longitude'] = $return['longitude'];
			} else {
				return new WP_Error( 'airport_not_found', __( 'This Airport Code was Not Found', 'simple-location' ) );
			}
		}

		$timezone = Loc_Timezone::timezone_for_location( $params['latitude'], $params['longitude'] );
		if ( ! $timezone instanceof Timezone_Result ) {
			return new WP_Error( 'timezone_not_found', __( 'Could Not Determine Timezone', 'simple-location' ) );
		}
		return array_merge(
			$return,
			array(
				'timezone'  => $timezone->name,
				'latitude'  => $params['latitude'],
				'longitude' => $params['longitude'],
				'localtime' => $timezone->localtime,
				'offset'    => $timezone->offset,
				'seconds'   => $timezone->seconds,
				'sunrise'   => sloc_sunrise( $params['latitude'], $params['longitude'], $timezone ),
				'sunset'    => sloc_sunset( $params['latitude'], $params['longitude'], $timezone ),
			)
		);
	}


}

new REST_Geo();

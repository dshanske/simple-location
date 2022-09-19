<?php
/**
 * Adds endpoint for accessing Simple Location providers.
 *
 * @package Simple_Location
 */

/**
 * Passes Data from the Various Providers via an API.
 *
 * @since 1.0.0
 */
class REST_Geo {
	/**
	 * Adds the registration function to the REST API Init.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}


	/**
	 * Trims, sanitizes, and converts coordinate parameters.
	 *
	 * The value must be a float stored as a string.
	 *
	 * @param string          $value   The value passed.
	 * @param WP_REST_Request $request Full details about the request.
	 * @param string          $param   The parameter that is being sanitized.
	 * @return int|bool|WP_Error
	 */
	public static function sanitize_coordinates( $value, $request, $param ) {
		return clean_coordinate( $value );
	}

	/**
	 * Trims and converts string to float.
	 *
	 * @param string          $value   The value passed.
	 * @param WP_REST_Request $request Full details about the request.
	 * @param string          $param   The parameter that is being sanitized.
	 * @return int|bool|WP_Error
	 */
	public static function sanitize_float( $value, $request, $param ) {
		// Remove whitespace.
		$value = trim( $value );
		return is_float( $value ) ? floatval( $value ) : false;
	}

	/**
	 * Trims and converts string to int.
	 *
	 * @param string          $value   The value passed.
	 * @param WP_REST_Request $request Full details about the request.
	 * @param string          $param   The parameter that is being sanitized.
	 * @return int|bool|WP_Error
	 */
	public static function sanitize_int( $value, $request, $param ) {
		// Remove whitespace.
		$value = trim( $value );
		return is_numeric( $value ) ? intval( $value ) : false;
	}

	/**
	 * Register the Route.
	 */
	public function register_routes() {
		register_rest_route(
			'sloc_geo/1.0',
			'/user',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'user' ),
					'args'                => array(
						'longitude'  => array(
							'required'          => true,
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'latitude'   => array(
							'required'          => true,
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'altitude'   => array(
							'sanitize_callback' => array( $this, 'sanitize_float' ),
						),
						'accuracy'   => array(
							'sanitize_callback' => array( $this, 'sanitize_float' ),
						),
						'speed'      => array(
							'sanitize_callback' => array( $this, 'sanitize_float' ),
						),
						'heading'    => array(
							'sanitize_callback' => array( $this, 'sanitize_float' ),
						),
						'visibility' => array(
							'sanitize_callback' => 'sanitize_text_field',
						),

					),
					'permission_callback' => function( $request ) {
						return current_user_can( 'read' );
					},
				),
			)
		);
		register_rest_route(
			'sloc_geo/1.0',
			'/timezone',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'timezone' ),
					'args'                => array(
						'longitude' => array(
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'latitude'  => array(
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'airport'   => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
					'permission_callback' => '__return_true',
				),
			)
		);
		register_rest_route(
			'sloc_geo/1.0',
			'/airport',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'airport' ),
					'args'                => array(
						'iata_code'    => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'municipality' => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'ident'        => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'gps_code'     => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
					'permission_callback' => '__return_true',
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
						'longitude' => array(
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'latitude'  => array(
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'altitude'  => array(
							'sanitize_callback' => array( $this, 'sanitize_float' ),
						),
						'address'   => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'weather'   => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'height'    => array(
							'sanitize_callback' => array( $this, 'sanitize_int' ),
						),
						'width'     => array(
							'sanitize_callback' => array( $this, 'sanitize_int' ),
						),
						'units'     => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'provider'  => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
					'permission_callback' => function( $request ) {
						return current_user_can( 'publish_posts' );
					},
				),
			)
		);
		register_rest_route(
			'sloc_geo/1.0',
			'/elevation',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'elevation' ),
					'args'                => array(
						'longitude' => array(
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'latitude'  => array(
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'units'     => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'provider'  => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
					'permission_callback' => function( $request ) {
						return current_user_can( 'publish_posts' );
					},
				),
			)
		);

		register_rest_route(
			'sloc_geo/1.0',
			'/venue',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'venue' ),
					'args'                => array(
						'longitude' => array(
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'latitude'  => array(
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'provider'  => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
					'permission_callback' => function( $request ) {
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
						'longitude' => array(
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'latitude'  => array(
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'station'   => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'provider'  => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'units'     => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'time'      => array(),
					),
					'permission_callback' => function( $request ) {
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
						'args'                => array(),
						'permission_callback' => function( $request ) {
							return current_user_can( 'read' );
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
							'required'          => true,
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'latitude'  => array(
							'required'          => true,
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'height'    => array(
							'sanitize_callback' => array( $this, 'sanitize_int' ),
						),
						'width'     => array(
							'sanitize_callback' => array( $this, 'sanitize_int' ),
						),
						'zoom'      => array(
							'sanitize_callback' => array( $this, 'sanitize_int' ),
						),
					),
					'permission_callback' => function( $request ) {
						return current_user_can( 'publish_posts' );
					},
				),
			)
		);
	}


	/**
	 * Callback handler for Map Retrieval
	 *
	 * @param WP_Rest_Request $request REST Request.
	 */
	public static function map( $request ) {
		// We don't need to specifically check the nonce like with admin-ajax. It is handled by the API.
		$params = $request->get_params();
		if ( ! empty( $params['longitude'] ) && ! empty( $params['latitude'] ) ) {
			$args = array(
				'latitude'  => $params['latitude'],
				'longitude' => $params['longitude'],
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
			$args['url']    = $map->get_the_map_url();
			$args['map']    = $map->get_the_static_map();
			$args['return'] = $map->get_the_map();
			return $args;
		}
		return new WP_Error( 'missing_geo', __( 'Missing Coordinates for Reverse Lookup', 'simple-location' ), array( 'status' => 400 ) );
	}

	/**
	 * Callback handler for Geolocation Retrieval.
	 *
	 * @param WP_Rest_Request $request REST Request.
	 */
	public static function lookup( $request ) {
		$params      = $request->get_params();
		$time        = ifset( $params['time'], null );
		$geolocation = Loc_Config::geolocation_provider();
		if ( is_object( $geolocation ) ) {
			if ( 'HTML5' === $geolocation->get_slug() ) {
				$geolocation = Loc_Config::geolocation_provider( 'dummy' );
			}
			$geolocation->set_user( get_current_user_id() );
			$return = $geolocation->retrieve( $time, $params );

			if ( is_wp_error( $return ) ) {
				return $return;
			}

			return $geolocation->get();
		} elseif ( 'null' === $geolocation ) {
			return $geolocation;
		}
		return new WP_Error( 'no_provider', __( 'No Geolocation Provider Available', 'simple-location' ), array( 'status' => 400 ) );
	}

	/**
	 * Callback handler for updating user location.
	 *
	 * @param WP_Rest_Request $request REST Request.
	 */
	public static function user( $request ) {
		$json = $request->get_json_params();
		if ( is_array( $json ) && array_key_exists( 'locations', $json ) ) {
			$location = array();
			$json     = $json['locations'];
			if ( isset( $json['geometry'] ) && isset( $json['properties'] ) ) {
				$coord                 = $json['geometry']['coordinates'];
				$location['longitude'] = $coord[0];
				$location['latitude']  = $coord[1];
				$location['altitude']  = isset( $coord[2] ) ? $coord[2] : null;
				$properties            = $response['properties'];
				$location['accuracy']  = isset( $properties['accuracy'] ) ? $properties['accuracy'] : null;
				$location['speed']     = isset( $properties['speed'] ) ? $properties['speed'] : null;
				$location['heading']   = isset( $properties['heading'] ) ? $properties['heading'] : null;
			}
		} else {
			$params   = $request->get_params();
			$location = wp_array_slice_assoc( $params, array( 'latitude', 'longitude', 'altitude', 'accuracy', 'speed', 'heading', 'visibility' ) );
		}
		$location = array_filter( $location );
		if ( isset( $location['latitude'] ) && isset( $location['longitude'] ) ) {
			$reverse = Loc_Config::geo_provider();
			$reverse->set( $location['latitude'], $location['longitude'] );
			$reverse_adr = $reverse->reverse_lookup();

			if ( isset( $reverse_adr['display-name'] ) ) {
				$location['address'] = $reverse_adr['display-name'];
			}
			if ( isset( $location['altitude'] ) && 0 !== $location['altitude'] && 'NaN' !== $location['altitude'] ) {
				unset( $location['altitude'] );
			}
			if ( ! isset( $location['altitude'] ) ) {
				$location['altitude'] = $reverse->elevation();
			}
		}

		$return = set_user_geodata( wp_get_current_user()->ID, $location );
		if ( is_wp_error( $return ) ) {
			return $return;
		} else {
			return __( 'Updated', 'simple-location' );
		}
	}

	/**
	 * Callback handler for Venue Retrieval.
	 *
	 * @param WP_Rest_Request $request REST Request.
	 */
	public static function venue( $request ) {
		// We dont need to check the nonce like with admin-ajax.
		$params   = $request->get_params();
		$provider = empty( $params['provider'] ) ? null : $params['provider'];
		if ( ! empty( $params['longitude'] ) && ! empty( $params['latitude'] ) ) {
			$venue = Loc_Config::venue_provider( $provider );
			if ( ! $venue ) {
				return new WP_Error( 'not_found', __( 'Provider Not Found', 'simple-location' ), array( 'provider' => $provider ) );
			}
			$venue->set( $params );
			return $venue->reverse_lookup();
		}
		return new WP_Error( 'missing_params', __( 'Missing Arguments', 'simple-location' ), array( 'status' => 400 ) );
	}



	/**
	 * Callback handler for Elevation Retrieval.
	 *
	 * @param WP_Rest_Request $request REST Request.
	 */
	public static function elevation( $request ) {
		// We dont need to check the nonce like with admin-ajax.
		$params   = $request->get_params();
		$provider = empty( $params['provider'] ) ? null : $params['provider'];
		if ( ! empty( $params['longitude'] ) && ! empty( $params['latitude'] ) ) {
			$elevation = Loc_Config::elevation_provider( $provider );
			if ( ! $elevation ) {
				return new WP_Error( 'not_found', __( 'Provider Not Found', 'simple-location' ), array( 'provider' => $provider ) );
			}
			$elevation->set( $params );
			return $elevation->elevation();
		}
		return new WP_Error( 'missing_params', __( 'Missing Arguments', 'simple-location' ), array( 'status' => 400 ) );
	}

	/**
	 * Callback handler for Geolocation Retrieval.
	 *
	 * @param WP_Rest_Request $request REST Request.
	 */
	public static function geocode( $request ) {
		// We dont need to check the nonce like with admin-ajax.
		$params      = $request->get_params();
		$provider    = empty( $params['provider'] ) ? null : $params['provider'];
		$term_lookup = array_key_exists( 'term', $params );
		if ( ! empty( $params['longitude'] ) && ! empty( $params['latitude'] ) ) {
			$zone    = Location_Zones::in_zone( $params['latitude'], $params['longitude'] );
			$reverse = Loc_Config::geo_provider( $provider );
			if ( ! $reverse ) {
				return new WP_Error( 'not_found', __( 'Provider Not Found', 'simple-location' ), array( 'provider' => $provider ) );
			}
			$reverse->set( $params );
			$map      = Loc_Config::map_provider();
			$map_args = array(
				'latitude'  => $params['latitude'],
				'longitude' => $params['longitude'],
				'height'    => ifset( $params['height'] ),
				'width'     => ifset( $params['width'] ),
			);

			$map->set( array_filter( $map_args ) );
			if ( ! empty( $zone ) ) {
				$reverse_adr = array(
					'display-name' => $zone,
					'visibility'   => 'protected',
				);
			} else {
				$reverse_adr = $reverse->reverse_lookup();
				if ( is_wp_error( $reverse_adr ) ) {
					return $reverse_adr;
				}
				$reverse_adr['map_url']    = $map->get_the_static_map();
				$reverse_adr['map_link']   = $map->get_the_map_url();
				$reverse_adr['map_return'] = $map->get_the_map();
				$term                      = Location_Taxonomy::get_location( $reverse_adr, $term_lookup );
				if ( $term ) {
					$reverse_adr['term_id']      = $term;
					$reverse_adr['term_details'] = Location_Taxonomy::get_location_data( $term );
					$reverse_adr['terms']        = wp_dropdown_categories(
						array(
							'echo'             => 0,
							'taxonomy'         => 'location',
							'class'            => 'widefat',
							'hide_empty'       => 0,
							'name'             => 'tax_input[location][]',
							'id'               => 'location_dropdown',
							'orderby'          => 'name',
							'hierarchical'     => true,
							'selected'         => $term,
							'show_option_none' => __( 'No Location', 'simple-location' ),
						)
					);

				}
			}
			if ( isset( $params['weather'] ) && ( 'no' !== $params['weather'] ) ) {
				$weather = Loc_Config::weather_provider();
				$weather->set( $params );
				$time                   = ifset( $params['time'], null );
				$reverse_adr['weather'] = $weather->get_conditions( $time );
				if ( array_key_exists( 'units', $params ) && 'imperial' === $params['units'] ) {
					$reverse_adr['weather'] = $weather->metric_to_imperial( $reverse_adr['weather'] );
				}
			}

			if ( ! isset( $reverse_adr['altitude'] ) ) {
				$reverse_adr['altitude'] = $reverse->elevation();
			}
			return array_filter( $reverse_adr );
		} elseif ( isset( $params['address'] ) ) {
			$geocode = Loc_Config::geo_provider( $provider );
			return $geocode->geocode( $params['address'] );
		}
		return new WP_Error( 'missing_params', __( 'Missing Arguments', 'simple-location' ), array( 'status' => 400 ) );
	}

	/**
	 * Callback handler for Weather Retrieval.
	 *
	 * @param WP_Rest_Request $request REST Request.
	 */
	public static function weather( $request ) {
		// We don't need to specifically check the nonce like with admin-ajax. It is handled by the API.
		$params = $request->get_params();
		$args   = array(
			'cache_key'  => 'slocw',
			'cache_time' => 600,
		);
		$time   = null;
		if ( array_key_exists( 'time', $params ) ) {
			$time = (int) $params['time'];
		}
		$return   = array();
		$provider = empty( $params['provider'] ) ? null : $params['provider'];
		$weather  = Loc_Config::weather_provider( $provider );
		if ( ! empty( $params['station'] ) ) {
			$weather->set( array( 'station_id' => $params['station'] ) );
		} elseif ( ! empty( $params['longitude'] ) && ! empty( $params['latitude'] ) ) {
			$weather->set( $params );
			$return = array(
				'latitude'  => $params['latitude'],
				'longitude' => $params['longitude'],
			);
		} else {
				return new WP_Error( 'missing_geo', __( 'Missing Coordinates or Station for Weather Lookup', 'simple-location' ), array( 'status' => 400 ) );
		}
		$conditions = $weather->get_conditions( $time );
		if ( array_key_exists( 'units', $params ) && 'imperial' === $params['units'] ) {
			$conditions = $weather->metric_to_imperial( $conditions );
		}

		if ( is_wp_error( $conditions ) ) {
			return $conditions;
		}
		$return = array_filter( $return );
		if ( is_array( $conditions ) ) {
			$return = array_merge( $conditions, $return );
		}
		return $return;
	}

	/**
	 * Callback handler for Airport Lookup.
	 *
	 * @param WP_Rest_Request $request REST Request.
	 */
	public static function airport( $request ) {
		$params = $request->get_params();
		$return = array();
		if ( isset( $params['iata_code'] ) ) {
			$return = Airport_Location::get( $params['iata_code'], 'iata_code' );
		} elseif ( isset( $params['ident'] ) ) {
			$return = Airport_Location::get( $params['ident'], 'ident' );
		} elseif ( isset( $params['gps_code'] ) ) {
			$return = Airport_Location::get( $params['gps_code'], 'gps_code' );
		} elseif ( isset( $params['municipality'] ) ) {
			$return = Airport_Location::get( $params['municipality'], 'municipality' );
		}

		if ( $return ) {
			return $return;
		} else {
			return new WP_Error( 'airport_not_found', __( 'This Airport Code was Not Found', 'simple-location' ) );
		}
	}

	/**
	 * Callback handler for Looking Up Timezone Information.
	 *
	 * @param WP_Rest_Request $request REST Request.
	 */
	public static function timezone( $request ) {
		$params = $request->get_params();
		$return = array();
		if ( isset( $params['airport'] ) ) {
			$return = Airport_Location::get( $params['airport'] );
			if ( $return ) {
				$params['latitude']  = $return['latitude'];
				$params['longitude'] = $return['longitude'];
				$params['altitude']  = $return['elevation'];
			} else {
				return new WP_Error( 'airport_not_found', __( 'This Airport Code was Not Found', 'simple-location' ) );
			}
		}
		$params['altitude'] = ifset( $params['altitude'], null );

		$timezone = Loc_Timezone::timezone_for_location( $params['latitude'], $params['longitude'] );
		if ( ! $timezone instanceof Timezone_Result ) {
			return new WP_Error( 'timezone_not_found', __( 'Could Not Determine Timezone', 'simple-location' ) );
		}
		$calc = new Astronomical_Calculator( $params['latitude'], $params['longitude'], $params['altitude'] );
		$moon = $calc->get_moon_data();

		return array_merge(
			$return,
			array(
				'timezone'  => $timezone->name,
				'latitude'  => $params['latitude'],
				'longitude' => $params['longitude'],
				'localtime' => $timezone->localtime,
				'offset'    => $timezone->offset,
				'seconds'   => $timezone->seconds,
				'sunrise'   => $calc->get_iso8601( null, 'sunrise' ),
				'sunset'    => $calc->get_iso8601( null, 'sunset' ),
				'moonrise'  => $calc->get_iso8601( null, 'moonrise' ),
				'moonset'   => $calc->get_iso8601( null, 'moonset' ),
				'moonphase' => $moon['text'],
			)
		);
	}


}

new REST_Geo();

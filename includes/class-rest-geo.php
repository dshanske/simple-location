<?php
/**
 * Adds endpoint for accessing Simple Location providers.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

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
	 * @param string|float    $value   The value passed.
	 * @param WP_REST_Request $request Full details about the request.
	 * @param string          $param   The parameter that is being sanitized.
	 * @return float|WP_Error The coordinate, or an error if it is not a valid latitude or longitude.
	 *
	 * @since 6.0.0 Returns a WP_Error for invalid or out-of-range coordinates instead of false.
	 */
	public static function sanitize_coordinates( $value, $request, $param ) {
		$coordinate = clean_coordinate( $value, $param );
		if ( false === $coordinate ) {
			return new WP_Error(
				'rest_invalid_param',
				/* translators: %s: Parameter name. */
				sprintf( __( '%s is not a valid coordinate.', 'simple-location' ), $param ),
				array( 'status' => 400 )
			);
		}
		return $coordinate;
	}

	/**
	 * Trims and converts string to float.
	 *
	 * @param string          $value   The value passed.
	 * @param WP_REST_Request $request Full details about the request.
	 * @param string          $param   The parameter that is being sanitized.
	 * @return float|null The number, or null if it is not numeric.
	 *
	 * @since 6.0.0 Numeric strings are converted instead of always being discarded.
	 */
	public static function sanitize_float( $value, $request, $param ) {
		// Remove whitespace.
		if ( is_string( $value ) ) {
			$value = trim( $value );
		}
		return is_numeric( $value ) ? (float) $value : null;
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
	 * Validates an airport code or municipality before the airport data file is searched.
	 *
	 * IATA codes are 3 characters, ICAO idents 3 to 8 letters, digits or dashes, and GPS codes 3 or 4. Municipality
	 * names are limited to 100 characters.
	 *
	 * @since 6.0.0
	 *
	 * @param mixed           $value   The value passed.
	 * @param WP_REST_Request $request Full details about the request.
	 * @param string          $param   The parameter being validated.
	 * @return true|WP_Error True if valid.
	 */
	public static function validate_airport_param( $value, $request, $param ) {
		if ( ! is_string( $value ) ) {
			$valid = false;
		} elseif ( 'municipality' === $param ) {
			$length = strlen( trim( $value ) );
			$valid  = $length > 0 && $length <= 100;
		} else {
			$patterns = array(
				'airport'   => '/^[A-Za-z0-9]{3}$/',
				'iata_code' => '/^[A-Za-z0-9]{3}$/',
				'ident'     => '/^[A-Za-z0-9-]{3,8}$/',
				'gps_code'  => '/^[A-Za-z0-9]{3,4}$/',
			);
			$valid    = isset( $patterns[ $param ] ) && preg_match( $patterns[ $param ], trim( $value ) );
		}
		if ( $valid ) {
			return true;
		}
		return new WP_Error(
			'rest_invalid_param',
			/* translators: %s: Parameter name. */
			sprintf( __( 'Invalid %s.', 'simple-location' ), $param ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Permission check for local lookup APIs
	 *
	 * @since 6.0.0 The sloc_local_api_protected filter is deprecated in favor of sloc_local_api_public.
	 *
	 * @return bool
	 */
	public static function local_permission_check() {
		$granted = apply_filters_deprecated( 'sloc_local_api_protected', array( false ), '6.0.0', 'sloc_local_api_public' );

		/**
		 * Filters whether the local airport and timezone lookup endpoints are open to everyone.
		 *
		 * By default they require a logged-in user who can read.
		 *
		 * @since 6.0.0
		 *
		 * @param bool $public Whether anyone, including logged-out visitors, can use the endpoints.
		 */
		$granted = apply_filters( 'sloc_local_api_public', $granted );
		return $granted ? true : current_user_can( 'read' );
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
					// The location can also be a geo URI or a GeoJSON body; see user_location().
					'args'                => array(
						'longitude'  => array(
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'latitude'   => array(
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'geo'        => array(
							'sanitize_callback' => 'sanitize_text_field',
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
					'permission_callback' => function ( $request ) {
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
							'validate_callback' => array( $this, 'validate_airport_param' ),
						),
					),
					'permission_callback' => array( 'REST_Geo', 'local_permission_check' ),
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
							'validate_callback' => array( $this, 'validate_airport_param' ),
						),
						'municipality' => array(
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_airport_param' ),
						),
						'ident'        => array(
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_airport_param' ),
						),
						'gps_code'     => array(
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_airport_param' ),
						),
					),
					'permission_callback' => array( 'REST_Geo', 'local_permission_check' ),
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
						'plus_code' => array(
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
					'permission_callback' => function ( $request ) {
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
					'permission_callback' => function ( $request ) {
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
						'select'    => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
					'permission_callback' => function ( $request ) {
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
					'permission_callback' => function ( $request ) {
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
						'permission_callback' => function ( $request ) {
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
						// Coordinates, or a post or comment whose location to draw.
						'longitude'  => array(
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'latitude'   => array(
							'sanitize_callback' => array( $this, 'sanitize_coordinates' ),
						),
						'post_id'    => array(
							'sanitize_callback' => 'absint',
						),
						'comment_id' => array(
							'sanitize_callback' => 'absint',
						),
						'height'    => array(
							'sanitize_callback' => array( $this, 'sanitize_int' ),
						),
						'width'     => array(
							'sanitize_callback' => array( $this, 'sanitize_int' ),
						),
						'zoom'         => array(
							'sanitize_callback' => array( $this, 'sanitize_int' ),
						),
						'style_family' => array(
							'sanitize_callback' => array( 'Map_Provider', 'sanitize_map_style' ),
						),
					),
					'permission_callback' => function ( $request ) {
						return current_user_can( 'publish_posts' );
					},
				),
			)
		);
	}


	/**
	 * Returns the geo data of the post or comment a map request names, if the user may see its map.
	 *
	 * Someone who can edit the post or comment gets its location whatever its visibility, as they can see it in the
	 * editor; someone who can only read it gets a public location only.
	 *
	 * @since 6.2.0
	 *
	 * @param array $params Request parameters, with 'post_id' or 'comment_id'.
	 * @return array|WP_Error Geo data, with the saved zoom as 'map_zoom', or an error.
	 */
	protected static function object_location( $params ) {
		if ( ! empty( $params['comment_id'] ) ) {
			$type    = 'comment';
			$id      = (int) $params['comment_id'];
			$comment = get_comment( $id );
			$exists  = $comment instanceof WP_Comment;
			$edit    = $exists && current_user_can( 'edit_comment', $id );
			$read    = $exists && ( $edit || ( '1' === (string) $comment->comment_approved && current_user_can( 'read_post', (int) $comment->comment_post_ID ) ) );
		} else {
			$type   = 'post';
			$id     = (int) $params['post_id'];
			$exists = (bool) get_post( $id );
			$edit   = $exists && current_user_can( 'edit_post', $id );
			$read   = $exists && ( $edit || current_user_can( 'read_post', $id ) );
		}
		if ( ! $read ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to see this map.', 'simple-location' ), array( 'status' => rest_authorization_required_code() ) );
		}
		$loc = Geo_Data::get_geodata( $type, $id );
		// A location the user may not see is reported as no location, so its existence isn't revealed.
		if ( ! is_array( $loc ) || ! isset( $loc['latitude'], $loc['longitude'] ) || ( ! $edit && 'public' !== ( $loc['visibility'] ?? '' ) ) ) {
			return new WP_Error( 'no_location', __( 'There is no location to show on a map.', 'simple-location' ), array( 'status' => 404 ) );
		}
		$loc['map_zoom'] = Geo_Data::map_zoom( $loc );
		return $loc;
	}

	/**
	 * Callback handler for Map Retrieval
	 *
	 * @since 6.2.0 Takes `style_family`, and `post_id` or `comment_id` in place of coordinates, for that object's map
	 *              at its saved zoom and style family.
	 *
	 * @param WP_REST_Request $request REST Request.
	 */
	public static function map( $request ) {
		// We don't need to specifically check the nonce like with admin-ajax. It is handled by the API.
		$params = $request->get_params();
		$object = null;
		if ( ! empty( $params['post_id'] ) || ! empty( $params['comment_id'] ) ) {
			$object = self::object_location( $params );
			if ( is_wp_error( $object ) ) {
				return $object;
			}
			$params['latitude']  = $object['latitude'];
			$params['longitude'] = $object['longitude'];
		}
		if ( is_numeric( $params['longitude'] ?? null ) && is_numeric( $params['latitude'] ?? null ) ) {
			$args = array(
				'latitude'  => $params['latitude'],
				'longitude' => $params['longitude'],
			);
			if ( $object ) {
				// The object's saved zoom and style, which the request can override.
				$args['map_zoom']     = $object['map_zoom'];
				$args['style_family'] = $object['map_style'] ?? '';
				$args['location']     = ( 'public' === ( $object['visibility'] ?? '' ) ) ? ( $object['address'] ?? '' ) : '';
			}
			if ( ! empty( $params['zoom'] ) ) {
				$args['map_zoom'] = $params['zoom'];
			}
			if ( isset( $params['style_family'] ) ) {
				$args['style_family'] = $params['style_family'];
			}
			$args['style_family'] = Map_Provider::sanitize_map_style( $args['style_family'] ?? '' );
			if ( ! empty( $params['height'] ) ) {
				$args['height'] = $params['height'];
			}
			if ( ! empty( $params['width'] ) ) {
				$args['width'] = $params['width'];
			}

			$map = Loc_Config::map_provider();
			if ( ! is_object( $map ) ) {
				return new WP_Error( 'no_provider', __( 'No Map Provider Set', 'simple-location' ), array( 'status' => 400 ) );
			}
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
	 * @since 6.2.0 Without a server-side provider, including the browser (HTML5) one, returns the no_provider error
	 *              directly; the branches that could never run are removed.
	 *
	 * @param WP_REST_Request $request REST Request.
	 */
	public static function lookup( $request ) {
		$params      = $request->get_params();
		$time        = $params['time'] ?? null;
		$geolocation = Loc_Config::geolocation_provider();
		// The browser (HTML5) provider runs in the browser and has nothing here, so it is an error, like no provider.
		if ( ! $geolocation instanceof Location_Provider ) {
			return new WP_Error( 'no_provider', __( 'No Geolocation Provider Available', 'simple-location' ), array( 'status' => 400 ) );
		}
		$geolocation->set_user( get_current_user_id() );
		$return = $geolocation->retrieve( $time, $params );
		if ( is_wp_error( $return ) ) {
			return $return;
		}
		return $geolocation->get();
	}

	/**
	 * Returns the location sent to the user endpoint.
	 *
	 * The location is one of, in order:
	 * - a GeoJSON body: a Feature with a Point geometry, a Point, or either as `{"locations": …}`; a Feature's
	 *   accuracy, speed and heading properties are kept;
	 * - a geo URI as the `geo` parameter, such as geo:45.01,-75.44;u=10;
	 * - the latitude, longitude, altitude, accuracy, speed and heading parameters.
	 *
	 * A visibility parameter can come with any of them, or alone.
	 *
	 * @since 6.1.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error Location, or an error without one, or with a coordinate that isn't valid.
	 */
	public static function user_location( $request ) {
		$location = array();
		$json     = $request->get_json_params();
		$geometry = null;
		if ( is_array( $json ) ) {
			$feature    = ( isset( $json['locations'] ) && is_array( $json['locations'] ) ) ? $json['locations'] : $json;
			$properties = ( 'Feature' === ( $feature['type'] ?? null ) && is_array( $feature['properties'] ?? null ) ) ? $feature['properties'] : array();
			$geometry   = 'Feature' === ( $feature['type'] ?? null ) ? ( $feature['geometry'] ?? null ) : $feature;
			if ( is_array( $geometry ) && 'Point' === ( $geometry['type'] ?? null ) && is_array( $geometry['coordinates'] ?? null ) && count( $geometry['coordinates'] ) >= 2 ) {
				$coordinates = array_values( $geometry['coordinates'] );
				$location    = array(
					'longitude' => $coordinates[0],
					'latitude'  => $coordinates[1],
					'altitude'  => $coordinates[2] ?? null,
				);
				foreach ( array( 'accuracy', 'speed', 'heading' ) as $key ) {
					$location[ $key ] = $properties[ $key ] ?? null;
				}
			} elseif ( isset( $json['locations'] ) || isset( $json['type'] ) ) {
				return new WP_Error( 'rest_invalid_param', __( 'The location must be a GeoJSON Point, or a Feature with a Point geometry.', 'simple-location' ), array( 'status' => 400 ) );
			}
		}
		if ( empty( $location ) && is_string( $request->get_param( 'geo' ) ) && '' !== $request->get_param( 'geo' ) ) {
			$geo = Geo_Data::parse_geo_uri( $request->get_param( 'geo' ) );
			if ( ! is_array( $geo ) ) {
				return new WP_Error( 'rest_invalid_param', __( 'geo is not a valid geo URI.', 'simple-location' ), array( 'status' => 400 ) );
			}
			$location = wp_array_slice_assoc( $geo, array( 'latitude', 'longitude', 'altitude', 'accuracy' ) );
		}
		if ( empty( $location ) ) {
			$location = wp_array_slice_assoc( $request->get_params(), array( 'latitude', 'longitude', 'altitude', 'accuracy', 'speed', 'heading' ) );
		}

		// Coordinates from a body or a geo URI haven't been checked yet. Both or neither.
		if ( isset( $location['latitude'] ) || isset( $location['longitude'] ) ) {
			foreach ( array( 'latitude', 'longitude' ) as $key ) {
				$coordinate = clean_coordinate( $location[ $key ] ?? '', $key );
				if ( false === $coordinate ) {
					/* translators: %s: Parameter name. */
					return new WP_Error( 'rest_invalid_param', sprintf( __( '%s is not a valid coordinate.', 'simple-location' ), $key ), array( 'status' => 400 ) );
				}
				$location[ $key ] = $coordinate;
			}
		}
		foreach ( array( 'altitude', 'accuracy', 'speed', 'heading' ) as $key ) {
			if ( isset( $location[ $key ] ) ) {
				$location[ $key ] = is_numeric( $location[ $key ] ) ? (float) $location[ $key ] : null;
			}
		}
		$visibility = $request->get_param( 'visibility' );
		if ( is_string( $visibility ) && '' !== $visibility ) {
			$location['visibility'] = $visibility;
		}
		$location = array_filter( $location, fn( $value ) => null !== $value && '' !== $value );
		if ( empty( $location ) ) {
			return new WP_Error( 'missing_location', __( 'Send a latitude and longitude, a geo URI, or a GeoJSON location.', 'simple-location' ), array( 'status' => 400 ) );
		}
		return $location;
	}

	/**
	 * Callback handler for updating user location.
	 *
	 * @since 6.1.0 Takes a GeoJSON Point or Feature, with or without "locations", or a geo URI (#407).
	 *
	 * @param WP_REST_Request $request REST Request.
	 */
	public static function user( $request ) {
		$location = self::user_location( $request );
		if ( is_wp_error( $location ) ) {
			return $location;
		}
		if ( isset( $location['latitude'] ) && isset( $location['longitude'] ) ) {
			if ( ! Loc_Config::geo_provider() ) {
				return __( 'Provider Not Available', 'simple-location' );
			}
			$reverse_adr = Sloc_Provider_Chain::reverse_lookup( $location['latitude'], $location['longitude'] );

			if ( isset( $reverse_adr['display-name'] ) ) {
				$location['address'] = $reverse_adr['display-name'];
			}
			// Discard an altitude that isn't a number, so it is looked up instead.
			if ( isset( $location['altitude'] ) && ( ! is_numeric( $location['altitude'] ) || is_nan( (float) $location['altitude'] ) ) ) {
				unset( $location['altitude'] );
			}
			if ( ! isset( $location['altitude'] ) ) {
				$location['altitude'] = Sloc_Provider_Chain::elevation( $location['latitude'], $location['longitude'] );
			}
		}

		$return = set_user_geodata( get_current_user_id(), '', $location );
		if ( is_wp_error( $return ) ) {
			return $return;
		} else {
			return __( 'Updated', 'simple-location' );
		}
	}

	/**
	 * Callback handler for Venue Retrieval.
	 *
	 * @param WP_REST_Request $request REST Request.
	 */
	public static function venue( $request ) {
		// We dont need to check the nonce like with admin-ajax.
		$params   = $request->get_params();
		$select   = empty( $params['select'] ) ? null : $params['select'];
		$provider = empty( $params['provider'] ) ? null : $params['provider'];
		$location = empty( $params['location'] ) ? null : $params['location'];
		if ( $select ) {
			$venue_args = array(
				'name'              => 'venue_id',
				'id'                => 'venue_id',
				'show_option_none'  => __( 'No Venue', 'simple-location' ),
				'option_none_value' => '0',
				'hierarchical'      => true,
				'post_type'         => 'venue',
				'echo'              => false,
			);
			if ( is_numeric( $location ) && $location > 0 ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Venues are listed by location.
				$venue_args['tax_query'] = array(
					array(
						'taxonomy' => 'location',
						'terms'    => $location,
					),
				);
			}
			$response = wp_dropdown_pages( $venue_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Not printed ('echo' is false); core escapes the list.
			if ( $response ) {
				return array(
					'venue_select' => $response,
				);
			} else {
				return $venue_args;
			}
			return new WP_Error( 'no_venues_found', __( 'Venues Not Found', 'simple-location' ) );
		}
		if ( is_numeric( $params['longitude'] ?? null ) && is_numeric( $params['latitude'] ?? null ) ) {
			$venue = Loc_Config::venue_provider( $provider );
			if ( ! $venue ) {
				return new WP_Error( 'not_found', __( 'Provider Not Found', 'simple-location' ), array( 'provider' => $provider ) );
			}
			// A provider asked for by name is the only one tried.
			if ( $provider ) {
				$venue->set( $params );
				return $venue->nearby();
			}
			return Sloc_Provider_Chain::venues( $params['latitude'], $params['longitude'] );
		}
		return new WP_Error( 'missing_params', __( 'Missing Arguments', 'simple-location' ), array( 'status' => 400 ) );
	}



	/**
	 * Callback handler for Elevation Retrieval.
	 *
	 * @param WP_REST_Request $request REST Request.
	 */
	public static function elevation( $request ) {
		// We dont need to check the nonce like with admin-ajax.
		$params   = $request->get_params();
		$provider = empty( $params['provider'] ) ? null : $params['provider'];
		if ( is_numeric( $params['longitude'] ?? null ) && is_numeric( $params['latitude'] ?? null ) ) {
			if ( ! $provider ) {
				return Sloc_Provider_Chain::elevation( $params['latitude'], $params['longitude'] );
			}
			// A provider asked for by name is the only one tried.
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
	 * @since 6.1.0 At a venue, the visibility is the venue's visibility for posts made there, when it sets one.
	 * @since 6.1.0 A plus_code is decoded and looked up in place of the coordinates, which are used to find a short
	 *              code without a place (#197). The response then has the code's latitude, longitude and plus_code.
	 *
	 * @param WP_REST_Request $request REST Request.
	 */
	public static function geocode( $request ) {
		// We dont need to check the nonce like with admin-ajax.
		$params      = $request->get_params();
		$provider    = empty( $params['provider'] ) ? null : $params['provider'];
		$term_lookup = array_key_exists( 'term', $params );
		$plus_code   = null;
		if ( ! empty( $params['plus_code'] ) ) {
			$plus_code = Sloc_Plus_Code::locate( $params['plus_code'], $params['latitude'] ?? null, $params['longitude'] ?? null );
			if ( is_wp_error( $plus_code ) ) {
				return $plus_code;
			}
			$params['latitude']  = $plus_code['latitude'];
			$params['longitude'] = $plus_code['longitude'];
			unset( $params['altitude'] );
		}
		if ( is_numeric( $params['longitude'] ?? null ) && is_numeric( $params['latitude'] ?? null ) ) {
			$map = Loc_Config::map_provider();
			if ( is_object( $map ) ) {
				$map_args = array(
					'latitude'     => $params['latitude'],
					'longitude'    => $params['longitude'],
					'height'       => $params['height'] ?? null,
					'width'        => $params['width'] ?? null,
					'map_zoom'     => is_numeric( $params['map_zoom'] ?? null ) ? (int) $params['map_zoom'] : null,
					'style_family' => Map_Provider::sanitize_map_style( $params['map_style'] ?? '' ),
				);
				$map->set( array_filter( $map_args ) );
			}
			$venue = Post_Venue::at_venue( $params['latitude'], $params['longitude'] );
			if ( false !== $venue ) {
				$term        = Location_Taxonomy::get_location_taxonomy( $venue );
				$term_id     = ( $term instanceof WP_Term ) ? $term->term_id : 0;
				$reverse_adr = array(
					'venue_id'      => $venue,
					'display-name'  => get_the_title( $venue ),
					'term_id'       => $term_id,
					'term_details'  => $term_id ? Location_Taxonomy::get_location_data( $term_id ) : array(),
					'terms'         => wp_dropdown_categories(
						array(
							'echo'             => 0,
							'taxonomy'         => 'location',
							'class'            => 'widefat',
							'hide_empty'       => 0,
							'name'             => 'tax_input[location][]',
							'id'               => 'location_dropdown',
							'orderby'          => 'name',
							'hierarchical'     => true,
							'selected'         => $term_id,
							'show_option_none' => __( 'No Location', 'simple-location' ),
						)
					),
					'nearby_select' => Post_Venue::nearby_select( $params['latitude'], $params['longitude'], $venue, false ),
				);
				// The venue's visibility for posts made there, if it sets one.
				$visibility = Post_Venue::get_post_visibility( $venue );
				if ( '' === $visibility ) {
					$visibility = get_post_geodata( $venue, 'visibility' );
					// A private venue becomes protected as you do not want to show the coordinates but you would want to show the label
					if ( 'private' === $visibility ) {
						$visibility = 'protected';
					}
				}
				$reverse_adr['visibility'] = $visibility;
			} else {
				$reverse = Loc_Config::geo_provider( $provider );
				if ( ! $reverse ) {
					return new WP_Error( 'not_found', __( 'Provider Not Found', 'simple-location' ), array( 'provider' => $provider ) );
				}
				if ( $provider ) {
					// A provider asked for by name is the only one tried.
					$reverse->set( $params );
					$reverse_adr = $reverse->reverse_lookup();
				} else {
					$reverse_adr = Sloc_Provider_Chain::reverse_lookup( $params['latitude'], $params['longitude'] );
				}
				if ( is_wp_error( $reverse_adr ) ) {
					return $reverse_adr;
				}
				if ( $map ) {
					$reverse_adr['map_url']    = $map->get_the_static_map();
					$reverse_adr['map_link']   = $map->get_the_map_url();
					$reverse_adr['map_return'] = $map->get_the_map();
				}
				$term = Location_Taxonomy::get_location( $reverse_adr, $term_lookup );
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
				if ( Loc_Config::weather_provider() ) {
					$reverse_adr['weather'] = Sloc_Provider_Chain::weather( $params['latitude'], $params['longitude'], $params['time'] ?? null );
					if ( array_key_exists( 'units', $params ) && 'imperial' === $params['units'] ) {
						$reverse_adr['weather'] = Weather_Provider::metric_to_imperial( $reverse_adr['weather'] );
					}
				}
			}

			if ( ! isset( $reverse_adr['altitude'] ) ) {
				$elevation = Sloc_Provider_Chain::elevation( $params['latitude'], $params['longitude'] );
				// Leave the altitude out when it can't be looked up.
				if ( is_numeric( $elevation ) ) {
					$reverse_adr['altitude'] = $elevation;
				}
			}

			if ( ! isset( $reverse_adr['nearby_select'] ) ) {
				$reverse_adr['nearby_select'] = Post_Venue::nearby_select( $params['latitude'], $params['longitude'], 0, false );
			}

			if ( $plus_code ) {
				$reverse_adr['latitude']  = $plus_code['latitude'];
				$reverse_adr['longitude'] = $plus_code['longitude'];
				$reverse_adr['plus_code'] = $plus_code['plus_code'];
			}

			return array_filter( $reverse_adr );
		} elseif ( isset( $params['address'] ) ) {
			$geocode = Loc_Config::geo_provider( $provider );
			if ( ! $geocode ) {
				return new WP_Error( 'no_provider', __( 'Reverse Geolocation Provider Not Set', 'simple-location' ) );
			}
			// A provider asked for by name is the only one tried.
			return $provider ? $geocode->geocode( $params['address'] ) : Sloc_Provider_Chain::geocode( $params['address'] );
		}
		return new WP_Error( 'missing_params', __( 'Missing Arguments', 'simple-location' ), array( 'status' => 400 ) );
	}

	/**
	 * Callback handler for Weather Retrieval.
	 *
	 * @param WP_REST_Request $request REST Request.
	 */
	public static function weather( $request ) {
		// We don't need to specifically check the nonce like with admin-ajax. It is handled by the API.
		$params = $request->get_params();
		$time   = null;
		if ( array_key_exists( 'time', $params ) ) {
			$time = (int) $params['time'];
		}
		$return   = array();
		$provider = empty( $params['provider'] ) ? null : $params['provider'];
		$weather  = Loc_Config::weather_provider( $provider );
		if ( ! $weather ) {
			return new WP_Error( 'no_provider', __( 'No Weather Provider Set', 'simple-location' ) );
		}
		// A copy, so the lookup leaves the shared provider as it was (#681).
		$weather = clone $weather;
		if ( ! empty( $params['station'] ) ) {
			$weather->set( array( 'station_id' => $params['station'] ) );
			$conditions = $weather->get_conditions( $time );
		} elseif ( is_numeric( $params['longitude'] ?? null ) && is_numeric( $params['latitude'] ?? null ) ) {
			$return = array(
				'latitude'  => $params['latitude'],
				'longitude' => $params['longitude'],
			);
			if ( $provider ) {
				// A provider asked for by name is the only one tried.
				$weather->set( $params );
				$conditions = $weather->get_conditions( $time );
			} else {
				$conditions = Sloc_Provider_Chain::weather( $params['latitude'], $params['longitude'], $time );
			}
		} else {
				return new WP_Error( 'missing_geo', __( 'Missing Coordinates or Station for Weather Lookup', 'simple-location' ), array( 'status' => 400 ) );
		}
		if ( is_wp_error( $conditions ) ) {
			return $conditions;
		}
		if ( array_key_exists( 'units', $params ) && 'imperial' === $params['units'] ) {
			$conditions = $weather->metric_to_imperial( $conditions );
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
	 * @param WP_REST_Request $request REST Request.
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

		if ( $return && ! is_wp_error( $return ) ) {
			return $return;
		}
		return new WP_Error( 'airport_not_found', __( 'This Airport Code was Not Found', 'simple-location' ), array( 'status' => 404 ) );
	}

	/**
	 * Callback handler for Looking Up Timezone Information.
	 *
	 * @param WP_REST_Request $request REST Request.
	 */
	public static function timezone( $request ) {
		$params = $request->get_params();
		$return = array();
		if ( isset( $params['airport'] ) ) {
			$return = Airport_Location::get( $params['airport'] );
			if ( ! is_array( $return ) || ! isset( $return['latitude'], $return['longitude'] ) ) {
				return new WP_Error( 'airport_not_found', __( 'This Airport Code was Not Found', 'simple-location' ), array( 'status' => 404 ) );
			}
			$params['latitude']  = $return['latitude'];
			$params['longitude'] = $return['longitude'];
			$params['altitude']  = $return['elevation'] ?? null;
		}
		$params['altitude'] = $params['altitude'] ?? null;
		if ( ! array_key_exists( 'latitude', $params ) || ! array_key_exists( 'longitude', $params ) ) {
			return new WP_Error( 'location_not_provided', __( 'No location data was provided', 'simple-location' ) );
		}

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

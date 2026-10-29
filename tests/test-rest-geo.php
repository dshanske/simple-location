<?php
/**
 * Tests for the sloc_geo/1.0 REST endpoints in includes/class-rest-geo.php.
 *
 * Providers are the fakes in tests/includes/class-sloc-test-providers.php, so no request
 * leaves the test run.
 *
 * @package Simple_Location
 */

/**
 * REST endpoint tests.
 */
class RestGeoTest extends WP_UnitTestCase {

	/**
	 * Author, who can publish posts.
	 *
	 * @var int
	 */
	protected static $author;

	/**
	 * Subscriber, who can only read.
	 *
	 * @var int
	 */
	protected static $subscriber;

	/**
	 * Create users shared by every test.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$author     = $factory->user->create( array( 'role' => 'author' ) );
		self::$subscriber = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Use the fake providers and start a fresh REST server.
	 */
	public function set_up() {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		foreach ( array( 'map', 'geo', 'weather', 'elevation', 'venue' ) as $type ) {
			update_option( 'sloc_' . $type . '_provider', 'sloc_test' );
		}
		update_option( 'sloc_measurements', 'metric' );
		Geo_Provider_Sloc_Test::reset();
		Weather_Provider_Sloc_Test::reset();
		wp_set_current_user( self::$author );
	}

	/**
	 * Remove the REST server.
	 */
	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tear_down();
	}

	/**
	 * Sends a request to an endpoint.
	 *
	 * @param string $route  Route below the namespace, e.g. '/map'.
	 * @param array  $params Query or body parameters.
	 * @param string $method HTTP method.
	 * @return WP_REST_Response
	 */
	protected function request( $route, $params = array(), $method = 'GET' ) {
		$request = new WP_REST_Request( $method, '/sloc_geo/1.0' . $route );
		if ( 'GET' === $method ) {
			$request->set_query_params( $params );
		} else {
			$request->set_body_params( $params );
		}
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Asserts a response is an error with the given code.
	 *
	 * @param string           $code     Expected error code.
	 * @param WP_REST_Response $response Response.
	 * @param int|null         $status   Expected HTTP status, if any.
	 */
	protected function assertErrorResponse( $code, $response, $status = null ) {
		$this->assertTrue( $response->is_error(), 'Expected an error response.' );
		$this->assertSame( $code, $response->as_error()->get_error_code() );
		if ( $status ) {
			$this->assertSame( $status, $response->get_status() );
		}
	}

	/**
	 * The integer sanitizer accepts numbers and rejects anything else.
	 */
	public function test_sanitize_int() {
		$this->assertSame( 42, REST_Geo::sanitize_int( '42', null, 'height' ) );
		$this->assertSame( 7, REST_Geo::sanitize_int( ' 7 ', null, 'height' ) );
		$this->assertFalse( REST_Geo::sanitize_int( 'tall', null, 'height' ) );
	}

	/**
	 * The float sanitizer accepts numeric strings.
	 */
	public function test_sanitize_float() {
		$this->assertSame( 12.5, REST_Geo::sanitize_float( '12.5', null, 'altitude' ) );
		$this->assertSame( 100.0, REST_Geo::sanitize_float( ' 100 ', null, 'altitude' ) );
	}

	/**
	 * The coordinate sanitizer passes values through clean_coordinate().
	 */
	public function test_sanitize_coordinates() {
		$this->assertSame( clean_coordinate( '45.123456789' ), REST_Geo::sanitize_coordinates( '45.123456789', null, 'latitude' ) );
	}

	/**
	 * The float sanitizer returns null for values that are not numbers.
	 */
	public function test_sanitize_float_rejects_text() {
		$this->assertNull( REST_Geo::sanitize_float( 'high', null, 'altitude' ) );
		$this->assertNull( REST_Geo::sanitize_float( null, null, 'altitude' ) );
		$this->assertSame( 3.0, REST_Geo::sanitize_float( 3, null, 'altitude' ) );
	}

	/**
	 * The coordinate sanitizer returns an error for invalid or out-of-range values.
	 */
	public function test_sanitize_coordinates_invalid() {
		$this->assertWPError( REST_Geo::sanitize_coordinates( 'north', null, 'latitude' ) );
		$this->assertWPError( REST_Geo::sanitize_coordinates( '91', null, 'latitude' ) );
		$this->assertWPError( REST_Geo::sanitize_coordinates( '300', null, 'longitude' ) );
		$this->assertSame( 91.0, REST_Geo::sanitize_coordinates( '91', null, 'longitude' ) );
	}

	/**
	 * Every endpoint is registered.
	 */
	public function test_routes_registered() {
		$routes = rest_get_server()->get_routes();
		foreach ( array( 'user', 'timezone', 'airport', 'geocode', 'elevation', 'venue', 'weather', 'lookup', 'map' ) as $route ) {
			$this->assertArrayHasKey( '/sloc_geo/1.0/' . $route, $routes );
		}
	}

	/**
	 * Data provider of endpoints and the capability each requires.
	 *
	 * @return array
	 */
	public function data_permissions() {
		return array(
			'user'      => array( '/user', 'read' ),
			'timezone'  => array( '/timezone', 'read' ),
			'airport'   => array( '/airport', 'read' ),
			'lookup'    => array( '/lookup', 'read' ),
			'geocode'   => array( '/geocode', 'publish_posts' ),
			'elevation' => array( '/elevation', 'publish_posts' ),
			'venue'     => array( '/venue', 'publish_posts' ),
			'weather'   => array( '/weather', 'publish_posts' ),
			'map'       => array( '/map', 'publish_posts' ),
		);
	}

	/**
	 * Each endpoint's permission callback grants access by capability.
	 *
	 * @dataProvider data_permissions
	 *
	 * @param string $route      Route.
	 * @param string $capability Capability required.
	 */
	public function test_permission_callbacks( $route, $capability ) {
		$routes   = rest_get_server()->get_routes();
		$callback = $routes[ '/sloc_geo/1.0' . $route ][0]['permission_callback'];
		$request  = new WP_REST_Request( 'GET', '/sloc_geo/1.0' . $route );

		wp_set_current_user( 0 );
		$this->assertFalse( (bool) call_user_func( $callback, $request ), 'Logged-out visitors are refused.' );

		wp_set_current_user( self::$subscriber );
		$this->assertSame( 'read' === $capability, (bool) call_user_func( $callback, $request ), 'Subscribers can use only the read endpoints.' );

		wp_set_current_user( self::$author );
		$this->assertTrue( (bool) call_user_func( $callback, $request ), 'Authors can use every endpoint.' );
	}

	/**
	 * Logged-out requests get a 401, and subscribers a 403 on author-only endpoints.
	 */
	public function test_unauthorized_requests() {
		wp_set_current_user( 0 );
		$this->assertSame(
			401,
			$this->request(
				'/map',
				array(
					'latitude'  => '45.01',
					'longitude' => '-75.44',
				)
			)->get_status()
		);
		$this->assertSame(
			401,
			$this->request(
				'/timezone',
				array(
					'latitude'  => '45.01',
					'longitude' => '-75.44',
				)
			)->get_status()
		);

		wp_set_current_user( self::$subscriber );
		$this->assertSame(
			403,
			$this->request(
				'/geocode',
				array(
					'latitude'  => '45.01',
					'longitude' => '-75.44',
				)
			)->get_status()
		);
	}

	/**
	 * The sloc_local_api_public filter opens the local lookup endpoints to everyone.
	 */
	public function test_local_permission_filter() {
		wp_set_current_user( 0 );
		$this->assertFalse( REST_Geo::local_permission_check() );
		add_filter( 'sloc_local_api_public', '__return_true' );
		$this->assertTrue( REST_Geo::local_permission_check() );
		remove_filter( 'sloc_local_api_public', '__return_true' );
	}

	/**
	 * The old sloc_local_api_protected filter name still works, with a deprecation notice.
	 */
	public function test_local_permission_deprecated_filter() {
		$this->setExpectedDeprecated( 'sloc_local_api_protected' );
		wp_set_current_user( 0 );
		add_filter( 'sloc_local_api_protected', '__return_true' );
		$this->assertTrue( REST_Geo::local_permission_check() );
		remove_filter( 'sloc_local_api_protected', '__return_true' );
	}

	/**
	 * /map returns the map URLs and markup for the coordinates and size given.
	 */
	public function test_map() {
		$response = $this->request(
			'/map',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
				'zoom'      => '12',
				'height'    => '300',
				'width'     => '400',
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'https://maps.example.com/static?c=45.01,-75.44&z=12&s=400x300', $data['map'] );
		$this->assertSame( 'https://maps.example.com/?c=45.01,-75.44', $data['url'] );
		$this->assertStringContainsString( '<img', $data['return'] );
		$this->assertSame( 12, $data['map_zoom'] );
	}

	/**
	 * The location box redraws its map preview from /map when only the zoom changes (#795).
	 */
	public function test_map_redraw_at_new_zoom() {
		$params = array(
			'latitude'  => '45.01',
			'longitude' => '-75.44',
			'height'    => '200',
			'width'     => '200',
		);
		$near   = $this->request( '/map', array_merge( $params, array( 'zoom' => '14' ) ) )->get_data();
		$far    = $this->request( '/map', array_merge( $params, array( 'zoom' => '10' ) ) )->get_data();
		$this->assertStringContainsString( 'z=14', $near['return'] );
		$this->assertStringContainsString( 'z=10', $far['return'] );

		// Contributors can't publish, so they don't get maps, as with the lookups.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$this->assertTrue( $this->request( '/map', array_merge( $params, array( 'zoom' => '10' ) ) )->is_error() );
	}

	/**
	 * /map requires coordinates.
	 */
	public function test_map_missing_coordinates() {
		$this->assertErrorResponse( 'missing_geo', $this->request( '/map' ), 400 );
	}

	/**
	 * Creates a published post with a location.
	 *
	 * @param string $visibility Location visibility.
	 * @param int    $author     Author. Default the test author.
	 * @return int Post ID.
	 */
	protected function located_post( $visibility, $author = 0 ) {
		$post_id = self::factory()->post->create( array( 'post_author' => $author ? $author : self::$author ) );
		set_post_geodata(
			$post_id,
			'',
			array(
				'latitude'   => 45.01,
				'longitude'  => -75.44,
				'address'    => 'Ottawa',
				'visibility' => $visibility,
				'map_zoom'   => 9,
			)
		);
		return $post_id;
	}

	/**
	 * /map draws a post's map from its ID, at its saved zoom, with its visibility respected (#118).
	 */
	public function test_map_by_post() {
		$data = $this->request( '/map', array( 'post_id' => $this->located_post( 'public' ) ) )->get_data();
		$this->assertStringStartsWith( 'https://maps.example.com/static?c=45.01,-75.44&z=9&', $data['map'], 'The post\'s place and saved zoom.' );
		$this->assertStringContainsString( '<img', $data['return'] );

		// The request can override the saved zoom.
		$data = $this->request(
			'/map',
			array(
				'post_id' => $this->located_post( 'public' ),
				'zoom'    => '15',
			)
		)->get_data();
		$this->assertStringContainsString( 'z=15', $data['map'] );

		// Its author can see the map of their own private location; another author can't.
		$private = $this->located_post( 'private' );
		$this->assertSame( 200, $this->request( '/map', array( 'post_id' => $private ) )->get_status() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertErrorResponse( 'no_location', $this->request( '/map', array( 'post_id' => $private ) ), 404 );
		$this->assertErrorResponse( 'no_location', $this->request( '/map', array( 'post_id' => $this->located_post( 'protected', self::$author ) ) ), 404 );
		$this->assertSame( 200, $this->request( '/map', array( 'post_id' => $this->located_post( 'public', self::$author ) ) )->get_status() );
	}

	/**
	 * /map by ID needs a post the user can read, with a location.
	 */
	public function test_map_by_post_errors() {
		$this->assertSame( 'rest_forbidden', $this->request( '/map', array( 'post_id' => PHP_INT_MAX ) )->as_error()->get_error_code() );

		$draft = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_author' => self::factory()->user->create( array( 'role' => 'editor' ) ),
			)
		);
		set_post_geodata( $draft, '', array( 'latitude' => 1, 'longitude' => 2, 'visibility' => 'public' ) );
		$this->assertSame( 'rest_forbidden', $this->request( '/map', array( 'post_id' => $draft ) )->as_error()->get_error_code(), 'Someone else\'s draft.' );

		$this->assertErrorResponse( 'no_location', $this->request( '/map', array( 'post_id' => self::factory()->post->create() ) ), 404 );
	}

	/**
	 * /map draws a comment's map from its ID.
	 */
	public function test_map_by_comment() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );
		set_comment_geodata(
			$comment_id,
			'',
			array(
				'latitude'   => 40.7,
				'longitude'  => -74.01,
				'visibility' => 'public',
			)
		);
		$data = $this->request( '/map', array( 'comment_id' => $comment_id ) )->get_data();
		$this->assertStringContainsString( 'c=40.7,-74.01', $data['map'] );

		$others  = self::factory()->post->create( array( 'post_author' => self::factory()->user->create( array( 'role' => 'editor' ) ) ) );
		$pending = self::factory()->comment->create(
			array(
				'comment_approved' => '0',
				'comment_post_ID'  => $others,
			)
		);
		set_comment_geodata( $pending, '', array( 'latitude' => 1, 'longitude' => 2, 'visibility' => 'public' ) );
		$this->assertSame( 'rest_forbidden', $this->request( '/map', array( 'comment_id' => $pending ) )->as_error()->get_error_code(), 'An unapproved comment.' );
	}

	/**
	 * /map returns an error when no map provider is set.
	 */
	public function test_map_without_provider() {
		update_option( 'sloc_map_provider', 'none' );
		$response = $this->request(
			'/map',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
			)
		);
		$this->assertTrue( $response->is_error() );
	}

	/**
	 * /geocode with coordinates returns the reverse lookup, map, elevation, and venue list.
	 */
	public function test_geocode_reverse() {
		$response = $this->request(
			'/geocode',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'Test Address', $data['display-name'] );
		$this->assertSame( array( 45.01, -75.44 ), Geo_Provider_Sloc_Test::$last );
		$this->assertStringStartsWith( 'https://maps.example.com/static', $data['map_url'] );
		$this->assertSame( 'https://maps.example.com/?c=45.01,-75.44', $data['map_link'] );
		$this->assertSame( 123.0, $data['altitude'] );
		$this->assertStringContainsString( '<select name="venue_id"', $data['nearby_select'] );
		$this->assertArrayNotHasKey( 'weather', $data );
	}

	/**
	 * /geocode leaves the altitude out when the elevation lookup fails.
	 */
	public function test_geocode_without_elevation() {
		Elevation_Provider_Sloc_Test::$elevation = new WP_Error( 'no_results', 'No elevation found' );
		try {
			$data = $this->request(
				'/geocode',
				array(
					'latitude'  => '45.01',
					'longitude' => '-75.44',
				)
			)->get_data();
		} finally {
			Elevation_Provider_Sloc_Test::$elevation = 123.0;
		}
		$this->assertArrayNotHasKey( 'altitude', $data );
		$this->assertSame( 'Test Address', $data['display-name'] );
	}

	/**
	 * /geocode keeps an altitude that the reverse lookup returned.
	 */
	public function test_geocode_keeps_provider_altitude() {
		Geo_Provider_Sloc_Test::$reverse['altitude'] = 77;
		$data                                        = $this->request(
			'/geocode',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
			)
		)->get_data();
		$this->assertSame( 77, $data['altitude'] );
	}

	/**
	 * /geocode adds weather when asked, converted to imperial units if requested.
	 */
	public function test_geocode_with_weather() {
		$params = array(
			'latitude'  => '45.01',
			'longitude' => '-75.44',
			'weather'   => 'yes',
		);
		$data   = $this->request( '/geocode', $params )->get_data();
		$this->assertSame( 20, $data['weather']['temperature'] );

		$params['units'] = 'imperial';
		$data            = $this->request( '/geocode', $params )->get_data();
		$this->assertEqualsWithDelta( 68, $data['weather']['temperature'], 0.01 );

		$params['weather'] = 'no';
		$data              = $this->request( '/geocode', $params )->get_data();
		$this->assertArrayNotHasKey( 'weather', $data );
	}

	/**
	 * /geocode passes on an error from the reverse lookup.
	 */
	public function test_geocode_reverse_error() {
		Geo_Provider_Sloc_Test::$reverse = new WP_Error( 'lookup_failed', 'Lookup failed' );
		$response                        = $this->request(
			'/geocode',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
			)
		);
		$this->assertErrorResponse( 'lookup_failed', $response );
	}

	/**
	 * /geocode returns an error for an unknown provider.
	 */
	public function test_geocode_unknown_provider() {
		$response = $this->request(
			'/geocode',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
				'provider'  => 'nonexistent',
			)
		);
		$this->assertErrorResponse( 'not_found', $response );
	}

	/**
	 * /geocode with an address returns the forward geocode.
	 */
	public function test_geocode_address() {
		$data = $this->request( '/geocode', array( 'address' => '1 Main St' ) )->get_data();
		$this->assertSame( Geo_Provider_Sloc_Test::$geocode, $data );
	}

	/**
	 * /geocode with neither coordinates nor an address is a 400.
	 */
	public function test_geocode_missing_params() {
		$this->assertErrorResponse( 'missing_params', $this->request( '/geocode' ), 400 );
	}

	/**
	 * /geocode at a venue returns the venue and its location term instead of a reverse lookup.
	 */
	public function test_geocode_at_venue() {
		$term_id  = self::factory()->term->create( array( 'taxonomy' => 'location' ) );
		$venue_id = self::factory()->post->create(
			array(
				'post_type'  => 'venue',
				'post_title' => 'Test Venue',
			)
		);
		wp_set_object_terms( $venue_id, $term_id, 'location' );
		$at_venue = function () use ( $venue_id ) {
			return $venue_id;
		};
		add_filter( 'pre_at_venue', $at_venue );

		$data = $this->request(
			'/geocode',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
			)
		)->get_data();
		remove_filter( 'pre_at_venue', $at_venue );

		$this->assertSame( $venue_id, $data['venue_id'] );
		$this->assertSame( 'Test Venue', $data['display-name'] );
		$this->assertSame( $term_id, $data['term_id'] );
		$this->assertStringContainsString( 'location_dropdown', $data['terms'] );
		$this->assertSame( 'public', $data['visibility'] );
		$this->assertSame( array(), Geo_Provider_Sloc_Test::$last, 'No reverse lookup at a venue.' );
	}

	/**
	 * /geocode at a private venue returns protected visibility, so the name shows but not the coordinates.
	 */
	public function test_geocode_at_private_venue() {
		$term_id  = self::factory()->term->create( array( 'taxonomy' => 'location' ) );
		$venue_id = self::factory()->post->create( array( 'post_type' => 'venue' ) );
		wp_set_object_terms( $venue_id, $term_id, 'location' );
		set_post_geodata( $venue_id, 'visibility', 'private' );
		$at_venue = function () use ( $venue_id ) {
			return $venue_id;
		};
		add_filter( 'pre_at_venue', $at_venue );
		$data = $this->request(
			'/geocode',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
			)
		)->get_data();
		remove_filter( 'pre_at_venue', $at_venue );
		$this->assertSame( 'protected', $data['visibility'] );
	}

	/**
	 * /geocode at a venue that sets the visibility for posts made there returns that, whatever the venue's own (#143).
	 */
	public function test_geocode_at_venue_post_visibility() {
		$venue_id = self::factory()->post->create( array( 'post_type' => 'venue' ) );
		set_post_geodata( $venue_id, 'visibility', 'private' );
		$at_venue = function () use ( $venue_id ) {
			return $venue_id;
		};
		add_filter( 'pre_at_venue', $at_venue );
		foreach ( array( 'public', 'private' ) as $visibility ) {
			update_post_meta( $venue_id, 'venue_post_visibility', $visibility );
			$data = $this->request(
				'/geocode',
				array(
					'latitude'  => '45.01',
					'longitude' => '-75.44',
				)
			)->get_data();
			$this->assertSame( $visibility, $data['visibility'] );
		}
		remove_filter( 'pre_at_venue', $at_venue );
	}

	/**
	 * /geocode at a venue with no location term doesn't fail.
	 */
	public function test_geocode_at_venue_without_term() {
		$venue_id = self::factory()->post->create( array( 'post_type' => 'venue' ) );
		$at_venue = function () use ( $venue_id ) {
			return $venue_id;
		};
		add_filter( 'pre_at_venue', $at_venue );
		$response = $this->request(
			'/geocode',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
			)
		);
		remove_filter( 'pre_at_venue', $at_venue );
		$this->assertSame( $venue_id, $response->get_data()['venue_id'] );
	}

	/**
	 * /elevation returns the provider's elevation.
	 */
	public function test_elevation() {
		$response = $this->request(
			'/elevation',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
			)
		);
		$this->assertSame( 123.0, $response->get_data() );
	}

	/**
	 * /elevation errors without coordinates or with an unknown provider.
	 */
	public function test_elevation_errors() {
		$this->assertErrorResponse( 'missing_params', $this->request( '/elevation' ), 400 );
		$response = $this->request(
			'/elevation',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
				'provider'  => 'nonexistent',
			)
		);
		$this->assertErrorResponse( 'not_found', $response );
	}

	/**
	 * /venue returns the provider's lookup.
	 */
	public function test_venue_lookup() {
		$data = $this->request(
			'/venue',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
			)
		)->get_data();
		$this->assertSame( 'Test Cafe', $data['items'][0]['name'] );
		$this->assertSame( 45.01, $data['items'][0]['latitude'] );
	}

	/**
	 * /venue errors without coordinates or with an unknown provider.
	 */
	public function test_venue_errors() {
		$this->assertErrorResponse( 'missing_params', $this->request( '/venue' ), 400 );
		$response = $this->request(
			'/venue',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
				'provider'  => 'nonexistent',
			)
		);
		$this->assertErrorResponse( 'not_found', $response );
	}

	/**
	 * /venue with select returns a dropdown of venues.
	 */
	public function test_venue_select() {
		self::factory()->post->create(
			array(
				'post_type'  => 'venue',
				'post_title' => 'Corner Cafe',
			)
		);
		$data = $this->request( '/venue', array( 'select' => '1' ) )->get_data();
		$this->assertStringContainsString( 'Corner Cafe', $data['venue_select'] );
		$this->assertStringContainsString( "name='venue_id'", $data['venue_select'] );
	}

	/**
	 * /weather by coordinates returns the conditions and the coordinates.
	 */
	public function test_weather_coordinates() {
		$data = $this->request(
			'/weather',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
				'time'      => '1700000000',
			)
		)->get_data();
		$this->assertSame( 20, $data['temperature'] );
		$this->assertSame( 'Clear', $data['summary'] );
		$this->assertSame( 45.01, $data['latitude'] );
		$this->assertSame( 1700000000, Weather_Provider_Sloc_Test::$time );
	}

	/**
	 * /weather by station sets the station and returns only the conditions.
	 */
	public function test_weather_station() {
		$data = $this->request( '/weather', array( 'station' => 'KJFK' ) )->get_data();
		$this->assertSame( 20, $data['temperature'] );
		$this->assertArrayNotHasKey( 'latitude', $data );
		$this->assertSame( 'KJFK', Weather_Provider_Sloc_Test::$station );
		$this->assertEmpty( Loc_Config::weather_provider()->get_station_id(), 'The shared provider is left as it was (#681).' );
	}

	/**
	 * /weather converts to imperial units when asked.
	 */
	public function test_weather_imperial() {
		$data = $this->request(
			'/weather',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
				'units'     => 'imperial',
			)
		)->get_data();
		$this->assertEqualsWithDelta( 68, $data['temperature'], 0.01 );
	}

	/**
	 * /weather with a provider that isn't registered is an error and leaves the site's provider alone.
	 */
	public function test_weather_unknown_provider() {
		update_option( 'sloc_weather_provider', 'sloc_test' );
		$response = $this->request(
			'/weather',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
				'provider'  => 'not-a-provider',
			)
		);
		$this->assertErrorResponse( 'no_provider', $response );
		$this->assertSame( 'sloc_test', get_option( 'sloc_weather_provider' ), "The site's weather provider is kept." );
	}

	/**
	 * /weather passes on a provider error, including with imperial units.
	 */
	public function test_weather_provider_error() {
		Weather_Provider_Sloc_Test::$conditions = new WP_Error( 'weather_failed', 'Weather failed' );
		$params                                 = array(
			'latitude'  => '45.01',
			'longitude' => '-75.44',
		);
		$this->assertErrorResponse( 'weather_failed', $this->request( '/weather', $params ) );
		$params['units'] = 'imperial';
		$this->assertErrorResponse( 'weather_failed', $this->request( '/weather', $params ) );
	}

	/**
	 * /weather errors without a location, or without a provider.
	 */
	public function test_weather_errors() {
		$this->assertErrorResponse( 'missing_geo', $this->request( '/weather' ), 400 );
		update_option( 'sloc_weather_provider', 'none' );
		$this->assertErrorResponse(
			'no_provider',
			$this->request(
				'/weather',
				array(
					'latitude'  => '45.01',
					'longitude' => '-75.44',
				)
			)
		);
	}

	/**
	 * /lookup returns the location from the geolocation provider.
	 */
	public function test_lookup() {
		update_option( 'sloc_geolocation_provider', 'dummy' );
		Geo_Data::set_geodata(
			'user',
			self::$author,
			'',
			array(
				'latitude'  => 40.7,
				'longitude' => -74.01,
			)
		);
		$data = $this->request( '/lookup' )->get_data();
		$this->assertEquals( 40.7, $data['latitude'] );
		$this->assertEquals( -74.01, $data['longitude'] );
	}

	/**
	 * /lookup without a server-side geolocation provider is an error.
	 */
	public function test_lookup_without_provider() {
		update_option( 'sloc_geolocation_provider', 'HTML5' );
		$this->assertErrorResponse( 'no_provider', $this->request( '/lookup' ), 400 );

		// A provider that isn't registered, such as one that was removed, is the same error (#771).
		remove_all_filters( 'sanitize_option_sloc_geolocation_provider' );
		update_option( 'sloc_geolocation_provider', 'removed-provider' );
		$this->assertErrorResponse( 'no_provider', $this->request( '/lookup' ), 400 );
	}

	/**
	 * /user stores the current user's location with an address from the reverse lookup.
	 */
	public function test_user() {
		$response = $this->request(
			'/user',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
			),
			'POST'
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Updated', $response->get_data() );
		$geodata = get_user_geodata( self::$author );
		$this->assertEquals( 45.01, $geodata['latitude'] );
		$this->assertEquals( -75.44, $geodata['longitude'] );
		$this->assertSame( 'Test Address', $geodata['address'] );
		$this->assertEquals( 123, $geodata['altitude'], 'Without an altitude, it is looked up.' );
	}

	/**
	 * /user keeps an altitude that was sent instead of looking it up.
	 */
	public function test_user_altitude() {
		$this->request(
			'/user',
			array(
				'latitude'  => '45.01',
				'longitude' => '-75.44',
				'altitude'  => '10',
			),
			'POST'
		);
		$this->assertEquals( 10, get_user_geodata( self::$author, 'altitude' ) );
	}

	/**
	 * /user rejects an impossible coordinate and leaves the stored location alone.
	 */
	public function test_user_rejects_out_of_range() {
		$response = $this->request(
			'/user',
			array(
				'latitude'  => '45.01',
				'longitude' => '300',
			),
			'POST'
		);
		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
		$this->assertEmpty( get_user_meta( self::$author, 'geo_longitude', true ) );
	}

	/**
	 * Sends a JSON body to /user.
	 *
	 * @param array $body Body.
	 * @return WP_REST_Response Response.
	 */
	protected function user_json( $body ) {
		$request = new WP_REST_Request( 'POST', '/sloc_geo/1.0/user' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * /user takes a GeoJSON Feature, as the readme says, with or without "locations", and a Point (#407).
	 */
	public function test_user_geojson() {
		$feature  = array(
			'type'       => 'Feature',
			'geometry'   => array(
				'type'        => 'Point',
				'coordinates' => array( -75.44, 45.01, 15 ),
			),
			'properties' => array(
				'accuracy' => 12,
				'heading'  => 0,
			),
		);
		$response = $this->user_json( array( 'locations' => $feature ) );
		$this->assertSame( 200, $response->get_status() );
		$geodata = get_user_geodata( self::$author );
		$this->assertEquals( 45.01, $geodata['latitude'] );
		$this->assertEquals( -75.44, $geodata['longitude'] );
		$this->assertEquals( 15, $geodata['altitude'], 'Sent, not looked up.' );
		$this->assertSame( 'Test Address', $geodata['address'] );

		$feature['geometry']['coordinates'] = array( 2.35, 48.85 );
		$this->assertSame( 200, $this->user_json( $feature )->get_status() );
		$this->assertEquals( 48.85, get_user_geodata( self::$author, 'latitude' ) );

		$this->assertSame(
			200,
			$this->user_json(
				array(
					'type'        => 'Point',
					'coordinates' => array( -0.12, 51.5 ),
				)
			)->get_status()
		);
		$this->assertEquals( 51.5, get_user_geodata( self::$author, 'latitude' ) );
	}

	/**
	 * /user takes a geo URI (#407).
	 */
	public function test_user_geo_uri() {
		$this->assertSame( 200, $this->request( '/user', array( 'geo' => 'geo:45.01,-75.44,20;u=8' ), 'POST' )->get_status() );
		$geodata = get_user_geodata( self::$author );
		$this->assertEquals( 45.01, $geodata['latitude'] );
		$this->assertEquals( 20, $geodata['altitude'] );
		$this->assertErrorResponse( 'rest_invalid_param', $this->request( '/user', array( 'geo' => 'geo:45.01' ), 'POST' ), 400 );
	}

	/**
	 * /user rejects a request without a location, GeoJSON that isn't a point, and coordinates out of range in any
	 * format, leaving the stored location alone (#407).
	 */
	public function test_user_invalid_locations() {
		$this->assertErrorResponse( 'missing_location', $this->request( '/user', array(), 'POST' ), 400 );
		$this->assertErrorResponse(
			'rest_invalid_param',
			$this->user_json(
				array(
					'type'        => 'LineString',
					'coordinates' => array( array( 0, 0 ), array( 1, 1 ) ),
				)
			),
			400
		);
		$this->assertErrorResponse( 'rest_invalid_param', $this->user_json( array( 'locations' => array( 'type' => 'Feature' ) ) ), 400 );
		$this->assertErrorResponse(
			'rest_invalid_param',
			$this->user_json(
				array(
					'type'        => 'Point',
					'coordinates' => array( 300, 45 ),
				)
			),
			400
		);
		$this->assertErrorResponse( 'rest_invalid_param', $this->request( '/user', array( 'geo' => 'geo:95,10' ), 'POST' ), 400 );
		$this->assertErrorResponse( 'rest_invalid_param', $this->request( '/user', array( 'latitude' => '45.01' ), 'POST' ), 400, 'Both coordinates or neither.' );
		$this->assertEmpty( get_user_meta( self::$author, 'geo_latitude', true ) );
	}

	/**
	 * The location read from each format keeps accuracy, speed and heading, including a heading of 0 (#407).
	 */
	public function test_user_location() {
		$request = new WP_REST_Request( 'POST', '/sloc_geo/1.0/user' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'type'       => 'Feature',
					'geometry'   => array(
						'type'        => 'Point',
						'coordinates' => array( '-75.44', '45.01' ),
					),
					'properties' => array(
						'accuracy' => 12,
						'speed'    => '1.5',
						'heading'  => 0,
						'other'    => 'x',
					),
				)
			)
		);
		$this->assertSame(
			array(
				'longitude' => -75.44,
				'latitude'  => 45.01,
				'accuracy'  => 12.0,
				'speed'     => 1.5,
				'heading'   => 0.0,
			),
			REST_Geo::user_location( $request )
		);

		$request = new WP_REST_Request( 'POST', '/sloc_geo/1.0/user' );
		$request->set_param( 'geo', 'geo:45.01,-75.44;u=8' );
		$this->assertEquals( 8, REST_Geo::user_location( $request )['accuracy'] );
	}

	/**
	 * /user can change only the visibility (#407).
	 */
	public function test_user_visibility_only() {
		$this->assertSame( 200, $this->request( '/user', array( 'visibility' => 'private' ), 'POST' )->get_status() );
		$this->assertSame( 'private', get_user_geodata( self::$author, 'visibility' ) );
	}

	/**
	 * /timezone for coordinates returns the zone and sun and moon times.
	 */
	public function test_timezone_coordinates() {
		$data = $this->request(
			'/timezone',
			array(
				'latitude'  => '40.7128',
				'longitude' => '-74.0060',
			)
		)->get_data();
		$this->assertSame( 'America/New_York', $data['timezone'] );
		foreach ( array( 'localtime', 'offset', 'seconds', 'sunrise', 'sunset', 'moonphase' ) as $key ) {
			$this->assertArrayHasKey( $key, $data );
		}
		$this->assertMatchesRegularExpression( '/^\d{4}-\d\d-\d\dT/', $data['sunrise'] );
	}

	/**
	 * /timezone errors without coordinates, and far from any known city.
	 */
	public function test_timezone_errors() {
		$this->assertErrorResponse( 'location_not_provided', $this->request( '/timezone' ) );
		$response = $this->request(
			'/timezone',
			array(
				'latitude'  => '0.5',
				'longitude' => '-140.5',
			)
		);
		$this->assertErrorResponse( 'timezone_not_found', $response );
	}

	/**
	 * /timezone for an airport code uses the airport's location.
	 */
	public function test_timezone_airport() {
		$data = $this->request( '/timezone', array( 'airport' => 'JFK' ) )->get_data();
		$this->assertSame( 'America/New_York', $data['timezone'] );
		$this->assertSame( 'JFK', $data['code'] );
	}

	/**
	 * /timezone for an unknown airport code is an error.
	 */
	public function test_timezone_unknown_airport() {
		$this->assertErrorResponse( 'airport_not_found', $this->request( '/timezone', array( 'airport' => 'ZZZ' ) ), 404 );
	}

	/**
	 * Codes that can't be valid are rejected before the airport data file is searched.
	 */
	public function test_airport_params_validated() {
		$this->assertErrorResponse( 'rest_invalid_param', $this->request( '/timezone', array( 'airport' => 'ZZZZ' ) ), 400 );
		$this->assertErrorResponse( 'rest_invalid_param', $this->request( '/timezone', array( 'airport' => 'J K' ) ), 400 );
		$this->assertErrorResponse( 'rest_invalid_param', $this->request( '/airport', array( 'iata_code' => 'TORONTO' ) ), 400 );
		$this->assertErrorResponse( 'rest_invalid_param', $this->request( '/airport', array( 'ident' => '../../etc' ) ), 400 );
		$this->assertErrorResponse( 'rest_invalid_param', $this->request( '/airport', array( 'gps_code' => 'CYYZ1' ) ), 400 );
		$this->assertErrorResponse( 'rest_invalid_param', $this->request( '/airport', array( 'municipality' => str_repeat( 'a', 101 ) ) ), 400 );
		$this->assertErrorResponse( 'rest_invalid_param', $this->request( '/airport', array( 'municipality' => ' ' ) ), 400 );
	}

	/**
	 * The validator accepts the formats in the airport data.
	 */
	public function test_validate_airport_param() {
		$this->assertTrue( REST_Geo::validate_airport_param( 'JFK', null, 'airport' ) );
		$this->assertTrue( REST_Geo::validate_airport_param( 'yyz', null, 'iata_code' ) );
		$this->assertTrue( REST_Geo::validate_airport_param( 'CYYZ', null, 'ident' ) );
		$this->assertTrue( REST_Geo::validate_airport_param( 'US-0123', null, 'ident' ) );
		$this->assertTrue( REST_Geo::validate_airport_param( '00A', null, 'gps_code' ) );
		$this->assertTrue( REST_Geo::validate_airport_param( 'Toronto', null, 'municipality' ) );
		$this->assertWPError( REST_Geo::validate_airport_param( array( 'JFK' ), null, 'airport' ) );
		$this->assertWPError( REST_Geo::validate_airport_param( 'JFK', null, 'unknown' ) );
	}

	/**
	 * Searches that find nothing are cached under a hashed key, so repeating them doesn't search the file again.
	 */
	public function test_airport_miss_cached() {
		$transient = 'sloc_airport_' . md5( 'zzz|iata_code' );
		$this->assertWPError( Airport_Location::get( 'ZZZ' ) );
		$this->assertSame( array(), get_transient( $transient ) );
		$this->assertWPError( Airport_Location::get( 'zzz' ) );
		$this->assertFalse( get_transient( 'airports_ZZZ_iata_code' ) );
	}

	/**
	 * Empty searches and unknown fields return an error.
	 */
	public function test_airport_invalid_search() {
		$this->assertWPError( Airport_Location::get( '' ) );
		$this->assertWPError( Airport_Location::get( 'JFK', 'nonexistent_field' ) );
	}

	/**
	 * /airport finds an airport by IATA code, ICAO ident, or GPS code.
	 */
	public function test_airport_codes() {
		foreach ( array(
			'iata_code' => 'YYZ',
			'ident'     => 'CYYZ',
			'gps_code'  => 'cyyz',
		) as $field => $code ) {
			$data = $this->request( '/airport', array( $field => $code ) )->get_data();
			$this->assertSame( 'Toronto Pearson International Airport', $data['name'], $field );
			$this->assertSame( 'YYZ', $data['code'] );
			$this->assertEqualsWithDelta( 43.676, $data['latitude'], 0.001 );
			$this->assertSame( 569, $data['elevation_ft'] );
		}
	}

	/**
	 * Municipality searches return every airport in the place, from the index; fields without an index still
	 * work by reading the file.
	 */
	public function test_airport_lists() {
		$airports = Airport_Location::get( 'philadelphia', 'municipality' );
		$this->assertGreaterThan( 10, count( $airports ) );
		$this->assertContains( 'KPHL', wp_list_pluck( $airports, 'ident' ) );
		foreach ( $airports as $airport ) {
			$this->assertSame( 'Philadelphia', $airport['municipality'] );
		}

		$airports = Airport_Location::get( 'Philadelphia International Airport', 'name' );
		$this->assertSame( array( 'KPHL' ), wp_list_pluck( $airports, 'ident' ) );
	}

	/**
	 * /airport reports elevation in meters.
	 */
	public function test_airport_elevation_in_meters() {
		$data = $this->request( '/airport', array( 'iata_code' => 'YYZ' ) )->get_data();
		$this->assertEqualsWithDelta( 173, $data['elevation'], 1 );
	}

	/**
	 * Airports at sea level keep an elevation of 0; airports with no elevation in the file have none.
	 */
	public function test_airport_sea_level() {
		$airport = Airport_Location::get( 'ELW' );
		$this->assertSame( 'Ellamar Seaplane Base', $airport['name'] );
		$this->assertEquals( 0, $airport['elevation'] );
		$this->assertSame( 0, $airport['elevation_ft'] );

		$data = $this->request( '/airport', array( 'iata_code' => 'HBH' ) )->get_data();
		$this->assertEquals( 0, $data['elevation'] );

		$airport = Airport_Location::get( 'WLR' );
		$this->assertSame( 'Loring Seaplane Base', $airport['name'] );
		$this->assertArrayNotHasKey( 'elevation', $airport );
		$this->assertArrayNotHasKey( 'elevation_ft', $airport );
		// Still dropped when not scheduled, as documented.
		$this->assertArrayNotHasKey( 'scheduled_service', $airport );
	}

	/**
	 * /airport by municipality returns a list of airports.
	 */
	public function test_airport_municipality() {
		$data = $this->request( '/airport', array( 'municipality' => 'toronto' ) )->get_data();
		$this->assertGreaterThan( 1, count( $data ) );
		$this->assertSame( array( 'Toronto' ), array_unique( wp_list_pluck( $data, 'municipality' ) ) );
	}

	/**
	 * /airport returns the same results from the cache.
	 */
	public function test_airport_municipality_cached() {
		$first  = $this->request( '/airport', array( 'municipality' => 'Toronto' ) )->get_data();
		$second = $this->request( '/airport', array( 'municipality' => 'Toronto' ) )->get_data();
		$this->assertSame( $first, $second );
	}

	/**
	 * /airport finds the first airport in the data file.
	 */
	public function test_airport_first_row() {
		$data = $this->request( '/airport', array( 'ident' => '00A' ) )->get_data();
		$this->assertSame( 'Total RF Heliport', $data['name'] );
	}

	/**
	 * /airport errors for unknown codes and when no code is given.
	 */
	public function test_airport_not_found() {
		$this->assertTrue( $this->request( '/airport', array( 'iata_code' => 'ZZZ' ) )->is_error() );
		$this->assertErrorResponse( 'airport_not_found', $this->request( '/airport' ) );
	}

	/**
	 * Airlines are found by IATA or ICAO code, including the first row of the file, which has no header.
	 */
	public function test_get_airline() {
		$airline = Airport_Location::get_airline( 'B6' );
		$this->assertSame( 'JetBlue Airways', $airline['name'] );
		$this->assertSame( 'JBU', $airline['icao_code'] );
		// \N means no value.
		$this->assertArrayNotHasKey( 'alias', $airline );

		$this->assertSame( 'JetBlue Airways', Airport_Location::get_airline( ' jbu ', 'icao_code' )['name'] );
		$this->assertSame( '-1', Airport_Location::get_airline( 'Unknown', 'name' )['id'] );
	}

	/**
	 * Unknown airlines, empty searches and unknown fields return an error, and misses are cached.
	 */
	public function test_get_airline_not_found() {
		$this->assertSame( 'unable_to_find', Airport_Location::get_airline( 'ZZZZ' )->get_error_code() );
		$this->assertSame( array(), get_transient( 'sloc_airline_' . md5( 'zzzz|iata_code' ) ) );
		$this->assertWPError( Airport_Location::get_airline( 'zzzz' ) );
		$this->assertWPError( Airport_Location::get_airline( '' ) );
		$this->assertWPError( Airport_Location::get_airline( 'B6', 'nonexistent_field' ) );
	}
}

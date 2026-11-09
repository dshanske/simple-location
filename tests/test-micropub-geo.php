<?php
/**
 * Tests for the experimental Micropub geo query (#842).
 *
 * @package Simple_Location
 */

/**
 * Micropub geo query tests.
 */
class MicropubGeoTest extends WP_UnitTestCase {

	/**
	 * Philadelphia City Hall.
	 */
	const LAT = 39.9524;
	const LNG = -75.1636;

	/**
	 * Known providers, and a user who can edit posts.
	 */
	public function set_up() {
		parent::set_up();
		Geo_Provider_Sloc_Test::reset();
		Geo_Provider_Sloc_Test::$reverse         = array(
			'display-name' => 'Philadelphia City Hall',
			'locality'     => 'Philadelphia',
			'region'       => 'Pennsylvania',
			'country-name' => 'United States',
			'country-code' => 'US',
			'latitude'     => self::LAT,
		);
		Elevation_Provider_Sloc_Test::$elevation = 123.0;
		update_option( 'sloc_geo_provider', 'sloc_test' );
		update_option( 'sloc_elevation_provider', 'sloc_test' );
		update_option( 'sloc_map_provider', 'sloc_test' );
		update_option( 'geo_public', 1 );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
	}

	/**
	 * Clean up.
	 */
	public function tear_down() {
		Geo_Provider_Sloc_Test::reset();
		Elevation_Provider_Sloc_Test::$elevation = 123.0;
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Runs a geo query.
	 *
	 * @param array $input Input, without q.
	 * @return array|WP_Error
	 */
	protected function query( $input ) {
		return Location_Plugins::micropub_query_geo( array(), array( 'q' => 'geo' ) + $input );
	}

	/**
	 * Coordinates give the location, elevation, timezone, local time, address and a map.
	 */
	public function test_coordinates() {
		$geo = $this->query(
			array(
				'lat' => (string) self::LAT,
				'lon' => (string) self::LNG,
			)
		)['geo'];
		$this->assertSame( self::LAT, $geo['latitude'] );
		$this->assertSame( self::LNG, $geo['longitude'] );
		$this->assertSame( 123.0, $geo['elevation'] );
		$this->assertSame( '87F6XR2P+XH', $geo['plus-code'] );
		$this->assertSame( 'America/New_York', $geo['timezone'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}-0[45]:00$/', $geo['localtime'] );
		$this->assertSame(
			array(
				'type'       => array( 'h-card' ),
				'properties' => array(
					'locality'     => array( 'Philadelphia' ),
					'region'       => array( 'Pennsylvania' ),
					'country-name' => array( 'United States' ),
					'country-code' => array( 'US' ),
					'label'        => array( 'Philadelphia City Hall' ),
				),
			),
			$geo['address'],
			'Only address properties, with display-name as the label.'
		);
		$this->assertStringContainsString( 'sig=', $geo['map'] );
		$this->assertStringStartsWith( home_url(), $geo['map'], 'The map is served from the site.' );
		$this->assertStringContainsString( 'map-image', $geo['map'] );
		$this->assertStringNotContainsString( 'maps.example.com', $geo['map'] );

		// A geo URI.
		$geo = $this->query( array( 'uri' => 'geo:40.5,-74.25;u=35' ) )['geo'];
		$this->assertSame( 40.5, $geo['latitude'] );
		$this->assertSame( array( 40.5, -74.25 ), Geo_Provider_Sloc_Test::$last );
	}

	/**
	 * The map can be sized, zoomed and styled.
	 */
	public function test_map_arguments() {
		$map  = $this->query(
			array(
				'lat'    => (string) self::LAT,
				'lon'    => (string) self::LNG,
				'width'  => '320',
				'height' => '200',
				'zoom'   => '12',
				'style'  => 'nonsense',
			)
		)['geo']['map'];
		$args = array();
		wp_parse_str( (string) wp_parse_url( $map, PHP_URL_QUERY ), $args );
		$this->assertSame( '320', $args['width'] );
		$this->assertSame( '200', $args['height'] );
		$this->assertSame( '12', $args['zoom'] );

		$map  = $this->query(
			array(
				'lat'   => (string) self::LAT,
				'lon'   => (string) self::LNG,
				'width' => '-5',
				'zoom'  => 'far',
			)
		)['geo']['map'];
		$args = array();
		wp_parse_str( (string) wp_parse_url( $map, PHP_URL_QUERY ), $args );
		$this->assertNotSame( '-5', $args['width'], 'A size or zoom that isn\'t a positive number is ignored.' );
		$this->assertNotSame( 'far', $args['zoom'] );
	}

	/**
	 * An airport's IATA code gives its location, elevation and name.
	 */
	public function test_airport() {
		$airport = Airport_Location::get( 'JFK' );
		$geo     = $this->query( array( 'airport' => ' jfk ' ) )['geo'];
		$this->assertSame( round( (float) $airport['latitude'], 7 ), $geo['latitude'] );
		$this->assertSame( (float) $airport['elevation'], $geo['elevation'], 'The airport\'s elevation, without asking the provider.' );
		$this->assertSame( 'America/New_York', $geo['timezone'] );
		$this->assertSame( array( $airport['name'] ), $geo['address']['properties']['name'] );
		$this->assertSame( array( $airport['municipality'] ), $geo['address']['properties']['locality'], 'What the airport knows comes first.' );
		$this->assertSame( array( 'Pennsylvania' ), $geo['address']['properties']['region'], 'The provider\'s address fills in.' );

		foreach ( array( 'XXX', 'KJFK', 'JF', array( 'JFK' ) ) as $code ) {
			$error = $this->query( array( 'airport' => $code ) );
			$this->assertWPError( $error );
			$this->assertSame( 'invalid_request', $error->get_error_code() );
			$this->assertSame( 400, $error->get_error_data()['status'] );
		}
	}

	/**
	 * A post's URL gives its location when the user may see it, with its own altitude and address.
	 */
	public function test_url() {
		$post_id = self::factory()->post->create( array( 'post_author' => 1 ) );
		set_post_geodata(
			$post_id,
			'',
			array(
				'latitude'   => self::LAT,
				'longitude'  => self::LNG,
				'altitude'   => 15,
				'address'    => 'City Hall',
				'visibility' => 'public',
			)
		);
		Geo_Provider_Sloc_Test::$reverse = array();
		$geo                             = $this->query( array( 'url' => get_permalink( $post_id ) ) )['geo'];
		$this->assertSame( self::LAT, $geo['latitude'] );
		$this->assertSame( 15.0, $geo['elevation'] );
		$this->assertSame( array( 'City Hall' ), $geo['address']['properties']['label'], 'The saved address without the provider\'s.' );

		// A private location, to a user who can't edit the post, is no location, like a URL that isn't a post.
		set_post_geodata( $post_id, 'visibility', 'private' );
		foreach ( array( get_permalink( $post_id ), 'https://example.com/elsewhere', array( 'x' ) ) as $url ) {
			$error = $this->query( array( 'url' => $url ) );
			$this->assertWPError( $error );
			$this->assertSame( 'invalid_request', $error->get_error_code() );
		}

		// The post's author sees it.
		wp_set_current_user( 1 );
		$this->assertSame( self::LAT, $this->query( array( 'url' => get_permalink( $post_id ) ) )['geo']['latitude'] );
	}

	/**
	 * Without the right to edit posts, only what the site knows itself; without providers, values are left out.
	 */
	public function test_lookups_need_edit_posts() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$geo = $this->query(
			array(
				'lat' => (string) self::LAT,
				'lon' => (string) self::LNG,
			)
		)['geo'];
		$this->assertSame( array( 'latitude', 'longitude', 'plus-code', 'timezone', 'localtime' ), array_keys( $geo ) );
		$this->assertEmpty( Geo_Provider_Sloc_Test::$last, 'No provider was asked.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		update_option( 'sloc_geo_provider', 'none' );
		update_option( 'sloc_map_provider', 'none' );
		Elevation_Provider_Sloc_Test::$elevation = new WP_Error( 'down', 'Down' );
		$geo                                     = $this->query(
			array(
				'lat' => '0',
				'lon' => '-160',
			)
		)['geo'];
		$this->assertSame( array( 'latitude', 'longitude', 'plus-code' ), array_keys( $geo ), 'No timezone in the open ocean, and no address, elevation or map.' );
	}

	/**
	 * Without a location, an error; other queries are left alone; the configuration lists geo once.
	 */
	public function test_errors_and_config() {
		$error = $this->query( array( 'lat' => 'north' ) );
		$this->assertWPError( $error );
		$this->assertSame( 'invalid_request', $error->get_error_code() );

		$this->assertSame( array( 'resp' ), Location_Plugins::micropub_query_geo( array( 'resp' ), array( 'q' => 'source' ) ) );
		$this->assertSame( array( 'resp' ), Location_Plugins::micropub_query_geo( array( 'resp' ), array() ) );

		$config = Location_Plugins::micropub_query_geo( array( 'q' => array( 'config', 'places' ) ), array( 'q' => 'config' ) );
		$this->assertSame( array( 'config', 'places', 'geo' ), $config['q'] );
		$this->assertSame( $config, Location_Plugins::micropub_query_geo( $config, array( 'q' => 'config' ) ) );
		$this->assertSame( array( 'geo' ), Location_Plugins::micropub_query_geo( array(), array( 'q' => 'config' ) )['q'] );
		$error = new WP_Error( 'forbidden', 'No' );
		$this->assertSame( $error, Location_Plugins::micropub_query_geo( $error, array( 'q' => 'config' ) ) );
	}

	/**
	 * A Plus Code gives the location: a full code on its own, a short one with its place for a user who can edit posts.
	 */
	public function test_plus_code() {
		$geo = $this->query( array( 'plus_code' => '87F6XR2P+XH' ) )['geo'];
		$this->assertEqualsWithDelta( self::LAT, $geo['latitude'], 0.0002 );
		$this->assertEqualsWithDelta( self::LNG, $geo['longitude'], 0.0002 );
		$this->assertSame( '87F6XR2P+XH', $geo['plus-code'] );

		Geo_Provider_Sloc_Test::$geocode = array(
			'latitude'  => 39.95,
			'longitude' => -75.16,
		);
		$this->assertSame( '87F6XR2P+XH', $this->query( array( 'plus_code' => 'XR2P+XH Philadelphia' ) )['geo']['plus-code'] );

		foreach ( array( 'nonsense', 'XR2P+XH', array( '87F6XR2P+XH' ) ) as $code ) {
			$error = $this->query( array( 'plus_code' => $code ) );
			$this->assertWPError( $error );
			$this->assertSame( 'invalid_request', $error->get_error_code() );
			$this->assertSame( 400, $error->get_error_data()['status'] );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( '87F6XR2P+XH', $this->query( array( 'plus_code' => '87F6XR2P+XH' ) )['geo']['plus-code'] );
		$this->assertWPError( $this->query( array( 'plus_code' => 'XR2P+XH Philadelphia' ) ), 'A short code\'s place needs a provider.' );
	}
}

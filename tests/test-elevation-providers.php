<?php
/**
 * Tests for the elevation providers in includes/elevation/, using recorded vendor responses
 * (tests/data/elevation).
 *
 * @package Simple_Location
 */

/**
 * Elevation provider tests.
 */
class ElevationProvidersTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

	/**
	 * Philadelphia City Hall.
	 */
	const LAT = 39.9524;
	const LNG = -75.1636;

	/**
	 * The registered Google elevation provider, restored after a test replaces it.
	 *
	 * @var Elevation_Provider|null
	 */
	protected $registered_google;

	/**
	 * Remembers the registered provider a test may replace.
	 */
	public function set_up() {
		parent::set_up();
		$this->registered_google = Loc_Config::elevation_provider( 'google' );
	}

	/**
	 * Restores the registered provider.
	 */
	public function tear_down() {
		Loc_Config::register_provider( $this->registered_google );
		parent::tear_down();
	}

	/**
	 * Creates a provider at City Hall.
	 *
	 * @param string $class Provider class.
	 * @param array  $args  Constructor arguments.
	 * @return Elevation_Provider
	 */
	protected function provider( $class, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			)
		);
		return new $class( $args );
	}

	/**
	 * Every bundled elevation provider is registered with its slug and name.
	 */
	public function test_providers_registered() {
		$providers = Loc_Config::elevation_providers();
		foreach ( array( 'geonames', 'google', 'openroute' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $providers );
			$this->assertNotEmpty( $providers[ $slug ]['name'], $slug );
		}
	}

	/**
	 * A provider is active when it is the chosen elevation provider, and can be looked up by slug.
	 */
	public function test_active_provider() {
		update_option( 'sloc_elevation_provider', 'google' );
		$this->assertTrue( $this->provider( 'Elevation_Provider_Google' )->is_active() );
		$this->assertFalse( $this->provider( 'Elevation_Provider_Geonames' )->is_active() );
		$this->assertInstanceOf( 'Elevation_Provider_Google', Loc_Config::elevation_provider() );
		$this->assertInstanceOf( 'Elevation_Provider_Geonames', Loc_Config::elevation_provider( 'geonames' ) );
	}

	/**
	 * Providers that need a key or user name return an error without one, before any request.
	 */
	public function test_missing_credentials() {
		$this->mock_http( array() );
		$this->assertSame( 'missing_username', $this->provider( 'Elevation_Provider_Geonames', array( 'user' => '' ) )->elevation()->get_error_code() );
		$this->assertSame( 'missing_api_key', $this->provider( 'Elevation_Provider_OpenRoute', array( 'api' => '' ) )->elevation()->get_error_code() );
		$this->assertSame( 'missing_api_key', $this->provider( 'Elevation_Provider_Google', array( 'api' => '' ) )->elevation()->get_error_code() );
		$this->assertEmpty( $this->http_requests );
	}

	/**
	 * GeoNames returns the SRTM1 elevation and reports account errors.
	 */
	public function test_geonames() {
		$fixture = $this->fixture( 'elevation/geonames-srtm1.json' );
		$this->mock_http( array( 'secure.geonames.org/srtm1' => array( 'body' => $fixture ) ) );
		$elevation = $this->provider( 'Elevation_Provider_Geonames', array( 'user' => 'someone' ) )->elevation();

		$request = $this->last_request();
		$this->assertSame( 'someone', $request['query']['username'] );
		$this->assertEquals( self::LAT, $request['query']['lat'] );
		$this->assertEquals( self::LNG, $request['query']['lng'] );
		$this->assertEquals( $fixture['srtm1'], $elevation );

		$error = $this->fixture( 'geo/geonames-error.json' );
		$this->mock_http( array( 'secure.geonames.org/srtm1' => array( 'body' => $error ) ) );
		$result = $this->provider( 'Elevation_Provider_Geonames', array( 'user' => 'someone' ) )->elevation();
		$this->assertWPError( $result );
		$this->assertSame( $error['status']['message'], $result->get_error_message() );
	}

	/**
	 * GeoNames' -32768 marks a point without data, such as the ocean, and is an error rather than an altitude.
	 */
	public function test_geonames_no_data() {
		$this->mock_http(
			array(
				'secure.geonames.org/srtm1' => array(
					'body' => array(
						'srtm1' => -32768,
						'lat'   => 39.5,
						'lng'   => -70.5,
					),
				),
			)
		);
		$result = $this->provider( 'Elevation_Provider_Geonames', array( 'user' => 'someone' ) )->elevation();
		$this->assertWPError( $result );
		$this->assertSame( 'no_elevation_data', $result->get_error_code() );
	}

	/**
	 * The GeoNames provider links to GeoNames and describes itself in the description.
	 */
	public function test_geonames_details() {
		$provider = $this->provider( 'Elevation_Provider_Geonames' );
		$this->assertSame( 'https://www.geonames.org/', $provider->get_url() );
		$this->assertStringContainsString( 'creative commons', $provider->get_description() );
	}

	/**
	 * Google's Elevation API returns the first result, rounded.
	 */
	public function test_google() {
		$fixture = $this->fixture( 'elevation/google-elevation.json' );
		$this->mock_http( array( 'maps.googleapis.com/maps/api/elevation/json' => array( 'body' => $fixture ) ) );
		$elevation = $this->provider( 'Elevation_Provider_Google', array( 'api' => 'KEY' ) )->elevation();

		$request = $this->last_request();
		$this->assertSame( '39.9524,-75.1636', $request['query']['locations'] );
		$this->assertSame( 'KEY', $request['query']['key'] );
		$this->assertSame( round( $fixture['results'][0]['elevation'], 2 ), $elevation );
	}

	/**
	 * A Google status other than OK becomes an error with that status as the code.
	 */
	public function test_google_denied() {
		$this->mock_http( array( 'maps.googleapis.com' => array( 'body' => $this->fixture( 'geo/google-denied.json' ) ) ) );
		$result = $this->provider( 'Elevation_Provider_Google', array( 'api' => 'KEY' ) )->elevation();
		$this->assertWPError( $result );
		$this->assertSame( 'REQUEST_DENIED', $result->get_error_code() );
		$this->assertSame( 'The provided API key is invalid.', $result->get_error_message() );
	}

	/**
	 * An empty result list is an error, without a warning.
	 */
	public function test_google_no_results() {
		$this->mock_http(
			array(
				'maps.googleapis.com' => array(
					'body' => array(
						'results' => array(),
						'status'  => 'OK',
					),
				),
			)
		);
		$this->assertSame( 'no_results', $this->provider( 'Elevation_Provider_Google', array( 'api' => 'KEY' ) )->elevation()->get_error_code() );
	}

	/**
	 * OpenRouteService's elevation point API returns the third coordinate, in longitude-first order.
	 */
	public function test_openroute() {
		$fixture = $this->fixture( 'elevation/openroute-point.json' );
		$this->mock_http( array( 'api.openrouteservice.org/elevation/point' => array( 'body' => $fixture ) ) );
		$elevation = $this->provider( 'Elevation_Provider_OpenRoute', array( 'api' => 'KEY' ) )->elevation();

		$request = $this->last_request();
		$this->assertSame( 'KEY', $request['query']['api_key'] );
		$this->assertSame( 'point', $request['query']['format_out'] );
		$this->assertSame( '-75.1636,39.9524', $request['query']['geometry'] );
		$this->assertSame( round( $fixture['geometry'][2], 2 ), $elevation );
	}

	/**
	 * A response without a point is an error, not sea level, and elevations are rounded.
	 */
	public function test_openroute_results() {
		$this->mock_http( array( 'api.openrouteservice.org/elevation/point' => array( 'body' => array( 'version' => '0.2.1' ) ) ) );
		$result = $this->provider( 'Elevation_Provider_OpenRoute', array( 'api' => 'KEY' ) )->elevation();
		$this->assertWPError( $result );
		$this->assertSame( 'no_results', $result->get_error_code() );

		$this->mock_http( array( 'api.openrouteservice.org/elevation/point' => array( 'body' => array( 'geometry' => array( -75.1636, 39.9524, 14.256 ) ) ) ) );
		$this->assertSame( 14.26, $this->provider( 'Elevation_Provider_OpenRoute', array( 'api' => 'KEY' ) )->elevation() );
	}

	/**
	 * Geo and venue providers ask the chosen elevation provider for the same point.
	 */
	public function test_lookup_through_chosen_provider() {
		update_option( 'sloc_elevation_provider', 'google' );
		update_option( 'sloc_google_api', 'KEY' );
		Loc_Config::register_provider( new Elevation_Provider_Google() );
		$fixture = $this->fixture( 'elevation/google-elevation.json' );
		$this->mock_http( array( 'maps.googleapis.com/maps/api/elevation/json' => array( 'body' => $fixture ) ) );

		$geo = new Geo_Provider_Nominatim();
		$geo->set( self::LAT, self::LNG );
		$this->assertSame( round( $fixture['results'][0]['elevation'], 2 ), $geo->elevation() );
		$this->assertSame( '39.9524,-75.1636', $this->last_request()['query']['locations'] );

		$venue = new Venue_Provider_Sloc_Test();
		$venue->set( self::LAT, self::LNG );
		$this->assertSame( round( $fixture['results'][0]['elevation'], 2 ), $venue->elevation() );

		delete_option( 'sloc_elevation_provider' );
		$this->assertSame( 'no_elevation_provider', $venue->elevation()->get_error_code() );
		$this->assertSame( 'no_elevation_provider', $geo->elevation()->get_error_code() );
	}
}

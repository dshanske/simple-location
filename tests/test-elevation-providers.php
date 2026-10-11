<?php
/**
 * Tests for the elevation providers in includes/elevation/, using recorded vendor responses
 * (tests/data/elevation).
 *
 * The MapQuest and OpenMapQuest providers use a shut-down API (see tests/data/README.md) and
 * have no tests.
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
		foreach ( array( 'bing', 'geonames', 'google', 'mapquest', 'openmapquest', 'openroute' ) as $slug ) {
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
		$this->assertFalse( $this->provider( 'Elevation_Provider_Bing' )->is_active() );
		$this->assertInstanceOf( 'Elevation_Provider_Google', Loc_Config::elevation_provider() );
		$this->assertInstanceOf( 'Elevation_Provider_Bing', Loc_Config::elevation_provider( 'bing' ) );
	}

	/**
	 * Providers that need a key or user name return an error without one, before any request.
	 */
	public function test_missing_credentials() {
		$this->mock_http( array() );
		$this->assertSame( 'no_key', $this->provider( 'Elevation_Provider_Bing', array( 'api' => '' ) )->elevation()->get_error_code() );
		$this->assertSame( 'missing_username', $this->provider( 'Elevation_Provider_Geonames', array( 'user' => '' ) )->elevation()->get_error_code() );
		$this->assertSame( 'missing_api_key', $this->provider( 'Elevation_Provider_OpenRoute', array( 'api' => '' ) )->elevation()->get_error_code() );
		$this->assertNull( $this->provider( 'Elevation_Provider_Google', array( 'api' => '' ) )->elevation() );
		$this->assertEmpty( $this->http_requests );
	}

	/**
	 * Bing's Elevation List API returns the first elevation.
	 */
	public function test_bing() {
		$fixture = $this->fixture( 'elevation/bing-elevation.json' );
		$this->mock_http( array( 'dev.virtualearth.net/REST/v1/Elevation/List' => array( 'body' => $fixture ) ) );
		$elevation = $this->provider( 'Elevation_Provider_Bing', array( 'api' => 'KEY' ) )->elevation();

		$request = $this->last_request();
		$this->assertSame( '39.9524,-75.1636', $request['query']['points'] );
		$this->assertSame( 'KEY', $request['query']['key'] );
		$this->assertEquals( round( $fixture['resourceSets'][0]['resources'][0]['elevations'][0], 2 ), $elevation );
	}

	/**
	 * Bing reports a failed key in authenticationResultCode, and the body may lack results.
	 */
	public function test_bing_errors() {
		$fixture = $this->fixture( 'elevation/bing-elevation.json' );
		$denied  = array_merge( $fixture, array( 'authenticationResultCode' => 'InvalidCredentials' ) );
		$this->mock_http( array( 'virtualearth.net' => array( 'body' => $denied ) ) );
		$this->assertSame( 'invalid_api_key', $this->provider( 'Elevation_Provider_Bing', array( 'api' => 'KEY' ) )->elevation()->get_error_code() );

		unset( $fixture['resourceSets'] );
		$this->mock_http( array( 'virtualearth.net' => array( 'body' => $fixture ) ) );
		$this->assertSame( 'no_resource_sets', $this->provider( 'Elevation_Provider_Bing', array( 'api' => 'KEY' ) )->elevation()->get_error_code() );
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
		$this->assertEquals( $fixture['geometry'][2], $elevation );
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
		$this->assertSame( 0, $venue->elevation() );
	}
}

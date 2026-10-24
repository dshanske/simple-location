<?php
/**
 * Tests for the venue provider chain (#708).
 *
 * @package Simple_Location
 */

/**
 * Venue chain tests.
 */
class VenueChainTest extends WP_UnitTestCase {

	/**
	 * Philadelphia.
	 */
	const LAT = 39.9524;
	const LNG = -75.1636;

	/**
	 * The fake venue providers in a chain, without recorded errors.
	 */
	public function set_up() {
		parent::set_up();
		Venue_Provider_Sloc_Test::reset();
		Venue_Provider_Sloc_Test_Fallback::reset();
		update_option( 'sloc_venue_provider', 'sloc_test' );
		update_option( 'sloc_venue_fallbacks', array( 'sloc_test_fallback' ) );
		delete_option( Sloc_Provider_Errors::OPTION );
	}

	/**
	 * Clean up.
	 */
	public function tear_down() {
		Venue_Provider_Sloc_Test::reset();
		Venue_Provider_Sloc_Test_Fallback::reset();
		delete_option( 'sloc_venue_fallbacks' );
		parent::tear_down();
	}

	/**
	 * The venue provider answers first, with its venues sorted and the provider named.
	 */
	public function test_first_provider() {
		$venues = Sloc_Provider_Chain::venues( self::LAT, self::LNG );
		$this->assertSame( 'sloc_test', $venues['provider'] );
		$this->assertSame( 'Test Cafe', $venues['items'][0]['name'] );
		$this->assertSame( 0, $venues['items'][0]['distance'] );
		$this->assertSame( 0, Venue_Provider_Sloc_Test_Fallback::$calls );
	}

	/**
	 * No venues, or an error, moves on to the fallback; an error is recorded.
	 */
	public function test_fallback() {
		Venue_Provider_Sloc_Test::$result = array( 'items' => array() );
		$this->assertSame( 'sloc_test_fallback', Sloc_Provider_Chain::venues( self::LAT, self::LNG )['provider'] );

		Venue_Provider_Sloc_Test::$result = new WP_Error( 'invalid_response', 'Quota', array( 'status' => 429 ) );
		$this->assertSame( 'sloc_test_fallback', Sloc_Provider_Chain::venues( self::LAT, self::LNG )['provider'] );
		$this->assertNotEmpty( Sloc_Provider_Errors::get_errors(), 'The quota error is recorded.' );
		$this->assertStringContainsString( 'venue lookups are failing', Sloc_Provider_Errors::message( array_values( Sloc_Provider_Errors::get_errors() )[0] ) );

		// Venues beyond the radius don't count.
		Venue_Provider_Sloc_Test::$result = array(
			'items' => array(
				array(
					'name'      => 'Far away',
					'latitude'  => self::LAT + 1,
					'longitude' => self::LNG,
				),
			),
		);
		$this->assertSame( 'sloc_test_fallback', Sloc_Provider_Chain::venues( self::LAT, self::LNG )['provider'] );
	}

	/**
	 * Without venues anywhere, an empty list; with only errors, the last error; without a provider, an error.
	 */
	public function test_nothing_found() {
		Venue_Provider_Sloc_Test::$result          = array( 'items' => array() );
		Venue_Provider_Sloc_Test_Fallback::$result = array( 'items' => array() );
		$this->assertSame( array( 'items' => array() ), Sloc_Provider_Chain::venues( self::LAT, self::LNG ) );

		Venue_Provider_Sloc_Test_Fallback::$result = new WP_Error( 'unable_to_retrieve', 'Down' );
		$this->assertSame( 'unable_to_retrieve', Sloc_Provider_Chain::venues( self::LAT, self::LNG )->get_error_code() );

		update_option( 'sloc_venue_provider', 'none' );
		delete_option( 'sloc_venue_fallbacks' );
		$this->assertSame( 'no_provider', Sloc_Provider_Chain::venues( self::LAT, self::LNG )->get_error_code() );
	}

	/**
	 * The fallbacks setting keeps known venue providers, and providers are active anywhere in the chain.
	 */
	public function test_setting() {
		$this->assertSame( array( 'sloc_test_fallback' ), Sloc_Provider_Chain::sanitize_venue_fallbacks( array( 'sloc_test_fallback', 'openmeteo', 'sloc_test_fallback' ) ) );
		$this->assertArrayHasKey( 'sloc_venue_fallbacks', get_registered_settings() );
		$this->assertTrue( ( new Venue_Provider_Sloc_Test_Fallback() )->is_active() );
		update_option( 'sloc_venue_fallbacks', array() );
		$this->assertFalse( ( new Venue_Provider_Sloc_Test_Fallback() )->is_active() );
	}

	/**
	 * The REST venue route and Micropub use the chain; a provider asked for by name is the only one tried.
	 */
	public function test_callers() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		Venue_Provider_Sloc_Test::$result = array( 'items' => array() );
		$request                          = new WP_REST_Request( 'GET', '/sloc_geo/1.0/venue' );
		$request->set_param( 'latitude', self::LAT );
		$request->set_param( 'longitude', self::LNG );
		$this->assertSame( 'sloc_test_fallback', rest_do_request( $request )->get_data()['provider'] );

		$request->set_param( 'provider', 'sloc_test' );
		$this->assertSame( array(), rest_do_request( $request )->get_data()['items'] );

		$response = Location_Plugins::micropub_query_geo(
			array(),
			array(
				'q'   => 'geo',
				'lat' => self::LAT,
				'lon' => self::LNG,
			)
		);
		$this->assertSame( 'sloc_test_fallback', $response['venues']['provider'] );
	}
}

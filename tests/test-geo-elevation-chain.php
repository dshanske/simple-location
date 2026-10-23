<?php
/**
 * Tests for the geocoding and elevation chains (#490).
 *
 * @package Simple_Location
 */

/**
 * Geocoding and elevation chain tests.
 */
class GeoElevationChainTest extends WP_UnitTestCase {

	/**
	 * Philadelphia.
	 */
	const LAT = 39.9524;
	const LNG = -75.1636;

	/**
	 * One-line notification messages fired.
	 *
	 * @var string[]
	 */
	protected $messages = array();

	/**
	 * The fake providers in chains, without recorded errors.
	 */
	public function set_up() {
		parent::set_up();
		Geo_Provider_Sloc_Test::reset();
		Geo_Provider_Sloc_Test_Fallback::reset();
		Elevation_Provider_Sloc_Test::$elevation          = 123.0;
		Elevation_Provider_Sloc_Test_Fallback::$elevation = 456.0;
		update_option( 'sloc_geo_provider', 'sloc_test' );
		update_option( 'sloc_geo_fallbacks', array( 'sloc_test_fallback' ) );
		update_option( 'sloc_elevation_provider', 'sloc_test' );
		update_option( 'sloc_elevation_fallbacks', array( 'sloc_test_fallback' ) );
		delete_option( Sloc_Provider_Errors::OPTION );
		$this->messages = array();
		add_action(
			'sloc_provider_error',
			function ( $message ) {
				$this->messages[] = $message;
			}
		);
	}

	/**
	 * Restore the fakes' defaults.
	 */
	public function tear_down() {
		Elevation_Provider_Sloc_Test::$elevation          = 123.0;
		Elevation_Provider_Sloc_Test_Fallback::$elevation = 456.0;
		parent::tear_down();
	}

	/**
	 * Reverse lookups try the geo providers in order.
	 */
	public function test_reverse_lookup() {
		$this->assertSame( 'Test Address', Sloc_Provider_Chain::reverse_lookup( self::LAT, self::LNG )['display-name'] );
		$this->assertSame( array( self::LAT, self::LNG ), Geo_Provider_Sloc_Test::$last );
		$this->assertSame( array(), Geo_Provider_Sloc_Test_Fallback::$last );

		Geo_Provider_Sloc_Test::$reverse = new WP_Error( 'missing_api_key', 'You have not set an API key for Test Geo' );
		$this->assertSame( 'Fallback Address', Sloc_Provider_Chain::reverse_lookup( self::LAT, self::LNG )['display-name'] );
		$this->assertSame( array( self::LAT, self::LNG ), Geo_Provider_Sloc_Test_Fallback::$last );
		$this->assertSame( array( 'Simple Location: Test Geo geocoding lookups are failing: You have not set an API key for Test Geo' ), $this->messages );
		$this->assertArrayHasKey( 'geo:sloc_test', Sloc_Provider_Errors::get_errors() );

		Geo_Provider_Sloc_Test::$reverse = array();
		$this->assertSame( 'Fallback Address', Sloc_Provider_Chain::reverse_lookup( self::LAT, self::LNG )['display-name'], 'Nothing found moves on too.' );

		Geo_Provider_Sloc_Test_Fallback::$reverse = new WP_Error( 'invalid_response', 'Down', array( 'status' => 503 ) );
		$this->assertSame( 'invalid_response', Sloc_Provider_Chain::reverse_lookup( self::LAT, self::LNG )->get_error_code() );
	}

	/**
	 * Geocoding an address tries the geo providers in order, wherever the shared providers were set.
	 */
	public function test_geocode() {
		Loc_Config::geo_provider( 'sloc_test' )->set( 0, -30 );
		$this->assertSame( 45.01, Sloc_Provider_Chain::geocode( 'City Hall, Philadelphia' )['latitude'] );

		Geo_Provider_Sloc_Test::$geocode = new WP_Error( 'no_results', 'Not found' );
		$this->assertSame( 1.5, Sloc_Provider_Chain::geocode( 'City Hall, Philadelphia' )['latitude'] );

		// The Address location provider geocodes with the chain.
		$provider = new Location_Provider_Address();
		$provider->retrieve( null, array( 'address' => 'City Hall, Philadelphia' ) );
		$this->assertSame( 1.5, $provider->get()['latitude'] );
	}

	/**
	 * Elevation lookups try the elevation providers in order; sea level is an answer.
	 */
	public function test_elevation() {
		$this->assertSame( 123.0, Sloc_Provider_Chain::elevation( self::LAT, self::LNG ) );

		Elevation_Provider_Sloc_Test::$elevation = new WP_Error( 'invalid_response', 'Quota', array( 'status' => 429 ) );
		$this->assertSame( 456.0, Sloc_Provider_Chain::elevation( self::LAT, self::LNG ) );
		$this->assertSame( array( 'Simple Location: Test Elevation elevation lookups are failing: Quota' ), $this->messages );

		Elevation_Provider_Sloc_Test::$elevation = 0.0;
		$this->assertSame( 0.0, Sloc_Provider_Chain::elevation( self::LAT, self::LNG ), 'Sea level is an elevation.' );
		$this->assertArrayNotHasKey( 'elevation:sloc_test', Sloc_Provider_Errors::get_errors(), 'Answering clears the error.' );

		Elevation_Provider_Sloc_Test::$elevation          = null;
		Elevation_Provider_Sloc_Test_Fallback::$elevation = null;
		$this->assertSame( 'no_elevation_data', Sloc_Provider_Chain::elevation( self::LAT, self::LNG )->get_error_code() );

		update_option( 'sloc_elevation_provider', 'none' );
		delete_option( 'sloc_elevation_fallbacks' );
		$this->assertSame( 'no_elevation_provider', Sloc_Provider_Chain::elevation( self::LAT, self::LNG )->get_error_code() );
	}

	/**
	 * Geo and venue providers' elevation uses the elevation chain.
	 */
	public function test_provider_elevation() {
		Elevation_Provider_Sloc_Test::$elevation = new WP_Error( 'down', 'Down' );
		$geo                                     = new Geo_Provider_Sloc_Test();
		$geo->set( self::LAT, self::LNG );
		$this->assertSame( 456.0, $geo->elevation() );
		$venue = new Venue_Provider_Sloc_Test();
		$venue->set( self::LAT, self::LNG );
		$this->assertSame( 456.0, $venue->elevation() );
	}

	/**
	 * Providers anywhere in a chain are active, so their settings load.
	 */
	public function test_active() {
		$this->assertTrue( ( new Geo_Provider_Sloc_Test_Fallback() )->is_active() );
		$this->assertTrue( ( new Elevation_Provider_Sloc_Test_Fallback() )->is_active() );
		delete_option( 'sloc_geo_fallbacks' );
		delete_option( 'sloc_elevation_fallbacks' );
		$this->assertFalse( ( new Geo_Provider_Sloc_Test_Fallback() )->is_active() );
		$this->assertFalse( ( new Elevation_Provider_Sloc_Test_Fallback() )->is_active() );
	}

	/**
	 * The fallback settings keep known providers of their own type.
	 */
	public function test_sanitize() {
		$this->assertSame( array( 'sloc_test_fallback', 'nominatim' ), Sloc_Provider_Chain::sanitize_geo_fallbacks( array( 'sloc_test_fallback', 'openmeteo', 'nominatim' ) ) );
		$this->assertSame( array( 'sloc_test_fallback' ), Sloc_Provider_Chain::sanitize_elevation_fallbacks( array( 'none', 'nominatim', 'sloc_test_fallback' ) ) );
	}

	/**
	 * REST: lookups use the chains, unless a provider is asked for by name.
	 */
	public function test_rest() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		Elevation_Provider_Sloc_Test::$elevation = new WP_Error( 'down', 'Down' );
		Geo_Provider_Sloc_Test::$reverse         = new WP_Error( 'down', 'Down' );

		$request = new WP_REST_Request( 'GET', '/sloc_geo/1.0/elevation' );
		$request->set_param( 'latitude', self::LAT );
		$request->set_param( 'longitude', self::LNG );
		$this->assertSame( 456.0, REST_Geo::elevation( $request ) );
		$request->set_param( 'provider', 'sloc_test' );
		$this->assertSame( 'down', REST_Geo::elevation( $request )->get_error_code() );

		$request = new WP_REST_Request( 'GET', '/sloc_geo/1.0/geocode' );
		$request->set_param( 'latitude', self::LAT );
		$request->set_param( 'longitude', self::LNG );
		$this->assertSame( 'Fallback Address', REST_Geo::geocode( $request )['display-name'] );
		$request->set_param( 'provider', 'sloc_test' );
		$this->assertSame( 'down', REST_Geo::geocode( $request )->get_error_code() );

		Geo_Provider_Sloc_Test::$geocode = new WP_Error( 'down', 'Down' );
		$request                         = new WP_REST_Request( 'GET', '/sloc_geo/1.0/geocode' );
		$request->set_param( 'address', 'City Hall' );
		$this->assertSame( 1.5, REST_Geo::geocode( $request )['latitude'] );
	}

	/**
	 * The settings show the 2nd and 3rd geo and elevation providers.
	 */
	public function test_settings_fields() {
		global $wp_settings_fields;
		require_once ABSPATH . 'wp-admin/includes/template.php';
		Loc_Config::admin_init();
		foreach ( array( 'sloc_weather_fallbacks', 'sloc_geo_fallbacks', 'sloc_elevation_fallbacks' ) as $name ) {
			$this->assertArrayHasKey( $name, $wp_settings_fields['sloc_providers']['sloc_providers'], $name );
		}
		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'sloc_geo_fallbacks', $settings );
		$this->assertArrayHasKey( 'sloc_elevation_fallbacks', $settings );
	}
}

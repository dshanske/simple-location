<?php
/**
 * Tests for the reverse geocoding providers in includes/geo/, the Geo_Provider base class and
 * Sloc_Provider::fetch_json(), using recorded vendor responses (tests/data/geo).
 *
 * @package Simple_Location
 */

/**
 * Geo provider tests.
 */
class GeoProvidersTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

	/**
	 * Philadelphia City Hall.
	 */
	const LAT = 39.9524;
	const LNG = -75.1636;

	/**
	 * Known settings for every test.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_country', '' );
		update_option( 'sloc_elevation_provider', 'sloc_test' );
		// An IP address from the documentation range, so URL validation needs no DNS lookup.
		// Core treats the range as not external, so allow it explicitly.
		update_option( 'sloc_pelias_url', 'https://203.0.113.10/v1' );
		add_filter( 'http_request_host_is_external', '__return_true' );
	}

	/**
	 * Creates a provider at City Hall.
	 *
	 * @param string $class Provider class.
	 * @param array  $args  Constructor arguments.
	 * @return Geo_Provider
	 */
	protected function provider( $class, $args = array() ) {
		$provider = new $class( $args );
		$provider->set( self::LAT, self::LNG );
		return $provider;
	}

	/**
	 * Asserts the parsed City Hall address.
	 *
	 * @param array $addr    Parsed address.
	 * @param array $exclude Keys this provider doesn't return.
	 */
	protected function assertCityHall( $addr, $exclude = array() ) {
		$expected = array(
			'street-address' => '1400 John F. Kennedy Boulevard',
			'locality'       => 'Philadelphia',
			'region'         => 'Pennsylvania',
			'region-code'    => 'PA',
			'country-code'   => 'US',
			'postal-code'    => '19107',
		);
		foreach ( array_diff_key( $expected, array_flip( $exclude ) ) as $key => $value ) {
			$this->assertArrayHasKey( $key, $addr, $key );
			$this->assertSame( $value, $addr[ $key ], $key );
		}
		$this->assertEquals( self::LAT, $addr['latitude'] );
		$this->assertEquals( self::LNG, $addr['longitude'] );
	}

	/**
	 * Every bundled geo provider is registered with its slug and name.
	 */
	public function test_providers_registered() {
		$providers = Loc_Config::geo_providers();
		foreach ( array( 'nominatim', 'locationiq', 'google', 'bing', 'mapquest', 'pelias', 'openroute', 'geoapify', 'here', 'geonames' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $providers );
			$this->assertNotEmpty( $providers[ $slug ]['name'], $slug );
			$this->assertNotEmpty( $providers[ $slug ]['url'], $slug );
		}
	}

	/**
	 * Requests go through wp_safe_remote_get() with JSON headers, a user agent and a timeout.
	 */
	public function test_fetch_json_request() {
		$this->mock_http( array( 'example.com' => array( 'body' => array( 'ok' => true ) ) ) );
		$provider = new Geo_Provider_Nominatim();
		$this->assertSame( array( 'ok' => true ), $provider->fetch_json( 'https://example.com/api', array( 'q' => 'a b' ), array( 'X-Test' => '1' ) ) );
		$request = $this->last_request();
		$this->assertSame( 'https://example.com/api', $request['base'] );
		$this->assertSame( 'a b', $request['query']['q'] );
		$this->assertSame( 'application/json', $request['args']['headers']['Accept'] );
		$this->assertSame( '1', $request['args']['headers']['X-Test'] );
		$this->assertSame( 'Simple Location for WordPress', $request['args']['user-agent'] );
		$this->assertSame( 10, $request['args']['timeout'] );
		$this->assertTrue( $request['args']['reject_unsafe_urls'] );
	}

	/**
	 * HTTP errors, failed connections and non-JSON bodies become WP_Errors.
	 */
	public function test_fetch_json_errors() {
		$provider = new Geo_Provider_Nominatim();

		$this->mock_http(
			array(
				'example.com/missing' => array(
					'code' => 404,
					'body' => 'Not Found',
				),
			)
		);
		$error = $provider->fetch_json( 'https://example.com/missing', array() );
		$this->assertWPError( $error );
		$this->assertSame( 'invalid_response', $error->get_error_code() );
		$this->assertSame( array( 'status' => 404 ), $error->get_error_data() );

		$this->mock_http( array( 'example.com/html' => array( 'body' => '<html></html>' ) ) );
		$this->assertSame( 'not_json_response', $provider->fetch_json( 'https://example.com/html', array() )->get_error_code() );

		$this->mock_http( array( 'example.com/down' => new WP_Error( 'http_request_failed', 'Timed out' ) ) );
		$this->assertSame( 'http_request_failed', $provider->fetch_json( 'https://example.com/down', array() )->get_error_code() );
	}

	/**
	 * Nominatim reverse geocoding sends the documented parameters and parses the address.
	 */
	public function test_nominatim_reverse() {
		$this->mock_http( array( 'nominatim.openstreetmap.org/reverse' => array( 'body' => $this->fixture( 'geo/nominatim-reverse.json' ) ) ) );
		$addr = $this->provider( 'Geo_Provider_Nominatim' )->reverse_lookup();

		$request = $this->last_request();
		$this->assertSame( 'https://nominatim.openstreetmap.org/reverse', $request['base'] );
		$this->assertSame( 'jsonv2', $request['query']['format'] );
		$this->assertEquals( self::LAT, $request['query']['lat'] );
		$this->assertEquals( 18, $request['query']['zoom'] );

		$this->assertCityHall( $addr );
		$this->assertSame( 'Philadelphia City Hall', $addr['name'] );
		$this->assertSame( 'Center City', $addr['extended-address'] );
		$this->assertSame( 'United States', $addr['country-name'] );
		$this->assertSame( 'https://www.phila.gov/', $addr['url'] );
		$this->assertSame( 'Philadelphia City Hall, Philadelphia, PA, US', $addr['display-name'] );
		$this->assertSame( 'America/New_York', $addr['timezone'] );
		$this->assertArrayHasKey( 'raw', $addr );
	}

	/**
	 * Nominatim reverse geocoding passes errors on, and returns nothing without an address.
	 */
	public function test_nominatim_reverse_errors() {
		$this->mock_http( array( 'nominatim' => array( 'body' => array( 'error' => 'Unable to geocode' ) ) ) );
		$this->assertSame( array(), $this->provider( 'Geo_Provider_Nominatim' )->reverse_lookup() );

		$this->mock_http(
			array(
				'nominatim' => array(
					'code' => 429,
					'body' => 'Too Many Requests',
				),
			)
		);
		$this->assertWPError( $this->provider( 'Geo_Provider_Nominatim' )->reverse_lookup() );
	}

	/**
	 * Nominatim forward geocoding returns the coordinates of the first result.
	 */
	public function test_nominatim_geocode() {
		$this->mock_http( array( 'nominatim.openstreetmap.org/search' => array( 'body' => $this->fixture( 'geo/nominatim-search.json' ) ) ) );
		$result = ( new Geo_Provider_Nominatim() )->geocode( 'Philadelphia City Hall' );
		$this->assertSame( 'Philadelphia City Hall', $this->last_request()['query']['q'] );
		$this->assertSame( '39.9524', $result['latitude'] );
		$this->assertSame( '-75.1636', $result['longitude'] );
		$this->assertSame( 'https://www.phila.gov/', $result['url'] );
	}

	/**
	 * LocationIQ uses its own endpoint and key, and the state code it returns.
	 */
	public function test_locationiq_reverse() {
		$this->assertWPError( $this->provider( 'Geo_Provider_LocationIQ', array( 'api' => '' ) )->reverse_lookup() );

		$this->mock_http( array( 'locationiq.com/v1/reverse' => array( 'body' => $this->fixture( 'geo/locationiq-reverse.json' ) ) ) );
		$addr    = $this->provider( 'Geo_Provider_LocationIQ', array( 'api' => 'liq-key' ) )->reverse_lookup();
		$request = $this->last_request();
		$this->assertSame( 'liq-key', $request['query']['key'] );
		$this->assertSame( '1', $request['query']['statecode'] );
		$this->assertCityHall( $addr );
	}

	/**
	 * Google reverse geocoding needs a key, sends it, and parses the address components.
	 */
	public function test_google_reverse() {
		$this->assertSame( 'missing_api_key', $this->provider( 'Geo_Provider_Google', array( 'api' => '' ) )->reverse_lookup()->get_error_code() );

		$this->mock_http( array( 'maps.googleapis.com/maps/api/geocode/json' => array( 'body' => $this->fixture( 'geo/google-reverse.json' ) ) ) );
		$addr    = $this->provider( 'Geo_Provider_Google', array( 'api' => 'g-key' ) )->reverse_lookup();
		$request = $this->last_request();
		$this->assertSame( 'g-key', $request['query']['key'] );
		$this->assertSame( self::LAT . ',' . self::LNG, $request['query']['latlng'] );

		$this->assertCityHall( $addr, array( 'street-address' ) );
		$this->assertSame( '1400 John F Kennedy Blvd', $addr['street-address'] );
		$this->assertSame( 'Center City', $addr['extended-address'] );
		$this->assertSame( 'United States', $addr['country-name'] );
		$this->assertSame( '87F6WWX6+XG', $addr['plus-code'] );
	}

	/**
	 * Google errors in the response status become WP_Errors.
	 */
	public function test_google_reverse_status_error() {
		$this->mock_http( array( 'maps.googleapis.com' => array( 'body' => $this->fixture( 'geo/google-denied.json' ) ) ) );
		$error = $this->provider( 'Geo_Provider_Google', array( 'api' => 'g-key' ) )->reverse_lookup();
		$this->assertSame( 'REQUEST_DENIED', $error->get_error_code() );
	}

	/**
	 * Bing reverse geocoding needs a valid key and parses the address.
	 */
	public function test_bing_reverse() {
		$this->assertSame( 'missing_api_key', $this->provider( 'Geo_Provider_Bing', array( 'api' => '' ) )->reverse_lookup()->get_error_code() );

		$this->mock_http( array( 'dev.virtualearth.net/REST/v1/Locations/' => array( 'body' => $this->fixture( 'geo/bing-locations.json' ) ) ) );
		$addr    = $this->provider( 'Geo_Provider_Bing', array( 'api' => 'b-key' ) )->reverse_lookup();
		$request = $this->last_request();
		$this->assertSame( 'https://dev.virtualearth.net/REST/v1/Locations/' . self::LAT . ',' . self::LNG, $request['base'] );
		$this->assertSame( 'b-key', $request['query']['key'] );

		$this->assertCityHall( $addr, array( 'street-address' ) );
		$this->assertSame( '1400 John F Kennedy Blvd', $addr['street-address'] );
		$this->assertSame( 'Center City', $addr['extended-address'] );
	}

	/**
	 * Bing errors: rejected key and an unexpected body.
	 */
	public function test_bing_reverse_errors() {
		$this->mock_http( array( 'virtualearth' => array( 'body' => $this->fixture( 'geo/bing-invalid-key.json' ) ) ) );
		$this->assertSame( 'invalid_api_key', $this->provider( 'Geo_Provider_Bing', array( 'api' => 'b-key' ) )->reverse_lookup()->get_error_code() );

		$this->mock_http( array( 'virtualearth' => array( 'body' => array( 'authenticationResultCode' => 'ValidCredentials' ) ) ) );
		$this->assertSame( 'unknown_error', $this->provider( 'Geo_Provider_Bing', array( 'api' => 'b-key' ) )->reverse_lookup()->get_error_code() );
	}

	/**
	 * Bing forward geocoding returns the point of the first result.
	 */
	public function test_bing_geocode() {
		$this->assertNull( ( new Geo_Provider_Bing( array( 'api' => '' ) ) )->geocode( 'City Hall' ) );

		$this->mock_http( array( 'virtualearth.net/REST/v1/Locations' => array( 'body' => $this->fixture( 'geo/bing-locations.json' ) ) ) );
		$result = ( new Geo_Provider_Bing( array( 'api' => 'b-key' ) ) )->geocode( 'City Hall, Philadelphia' );
		$this->assertSame( 'City Hall, Philadelphia', $this->last_request()['query']['q'] );
		$this->assertSame( self::LAT, $result['latitude'] );
		$this->assertSame( self::LNG, $result['longitude'] );
		$this->assertSame( 'Philadelphia', $result['locality'] );
	}

	/**
	 * MapQuest reverse and forward geocoding parse the first location.
	 */
	public function test_mapquest() {
		$this->assertSame( 'missing_api_key', $this->provider( 'Geo_Provider_Mapquest', array( 'api' => '' ) )->reverse_lookup()->get_error_code() );
		$this->assertSame( 'missing_api_key', ( new Geo_Provider_Mapquest( array( 'api' => '' ) ) )->geocode( 'x' )->get_error_code() );

		$this->mock_http( array( 'mapquestapi.com/geocoding/v1/' => array( 'body' => $this->fixture( 'geo/mapquest-reverse.json' ) ) ) );
		$addr    = $this->provider( 'Geo_Provider_Mapquest', array( 'api' => 'mq-key' ) )->reverse_lookup();
		$request = $this->last_request();
		$this->assertStringEndsWith( 'mapquestapi.com/geocoding/v1/reverse', $request['base'] );
		$this->assertSame( 'mq-key', $request['query']['key'] );
		$this->assertCityHall( $addr, array( 'street-address' ) );
		$this->assertSame( 'United States', $addr['country-name'] );
		$this->assertSame( 'Center City', $addr['extended-address'] );

		$result = ( new Geo_Provider_Mapquest( array( 'api' => 'mq-key' ) ) )->geocode( 'City Hall' );
		$this->assertStringEndsWith( 'mapquestapi.com/geocoding/v1/address', $this->last_request()['base'] );
		$this->assertSame( self::LAT, $result['latitude'] );
	}

	/**
	 * MapQuest responses without results or locations are errors.
	 */
	public function test_mapquest_errors() {
		$this->mock_http( array( 'mapquestapi.com' => array( 'body' => array( 'info' => array( 'statuscode' => 403 ) ) ) ) );
		$this->assertSame( 'no_results', $this->provider( 'Geo_Provider_Mapquest', array( 'api' => 'mq-key' ) )->reverse_lookup()->get_error_code() );

		$this->mock_http( array( 'mapquestapi.com' => array( 'body' => array( 'results' => array( array( 'providedLocation' => array() ) ) ) ) ) );
		$this->assertSame( 'no_locations', $this->provider( 'Geo_Provider_Mapquest', array( 'api' => 'mq-key' ) )->reverse_lookup()->get_error_code() );
	}

	/**
	 * Pelias needs a key and a service URL, picks the most confident feature and converts ISO3 country codes.
	 */
	public function test_pelias_reverse() {
		$this->assertSame( 'missing_api_key', $this->provider( 'Geo_Provider_Pelias', array( 'api' => '' ) )->reverse_lookup()->get_error_code() );
		update_option( 'sloc_pelias_url', 'not a url' );
		$this->assertSame( 'missing_pelias_url', $this->provider( 'Geo_Provider_Pelias', array( 'api' => 'p-key' ) )->reverse_lookup()->get_error_code() );
		update_option( 'sloc_pelias_url', 'https://203.0.113.10/v1' );

		$this->mock_http( array( '203.0.113.10/v1/reverse/' => array( 'body' => $this->fixture( 'geo/pelias-reverse.json' ) ) ) );
		$addr    = $this->provider( 'Geo_Provider_Pelias', array( 'api' => 'p-key' ) )->reverse_lookup();
		$request = $this->last_request();
		$this->assertSame( 'p-key', $request['query']['api_key'] );
		$this->assertEquals( self::LAT, $request['query']['point_lat'] );

		$this->assertCityHall( $addr );
		$this->assertSame( 'Philadelphia City Hall', $addr['name'] );
		$this->assertSame( 'Center City', $addr['extended-address'] );
		$this->assertSame( 'Philadelphia City Hall, Philadelphia, PA, US', $addr['display-name'] );
	}

	/**
	 * Pelias forward geocoding takes the coordinates from the first feature.
	 */
	public function test_pelias_geocode() {
		$this->mock_http( array( '203.0.113.10/v1/search/' => array( 'body' => $this->fixture( 'geo/pelias-reverse.json' ) ) ) );
		$result = ( new Geo_Provider_Pelias( array( 'api' => 'p-key' ) ) )->geocode( 'City Hall' );
		$this->assertSame( 'City Hall', $this->last_request()['query']['text'] );
		$this->assertSame( self::LAT, $result['latitude'] );
		$this->assertSame( self::LNG, $result['longitude'] );
		$this->assertSame( 'Philadelphia City Hall', $result['name'] );
	}

	/**
	 * OpenRoute uses the Pelias format at its own endpoint.
	 */
	public function test_openroute() {
		$this->assertSame( 'missing_api_key', $this->provider( 'Geo_Provider_OpenRoute', array( 'api' => '' ) )->reverse_lookup()->get_error_code() );
		$this->assertSame( 'missing_api_key', ( new Geo_Provider_OpenRoute( array( 'api' => '' ) ) )->geocode( 'x' )->get_error_code() );

		$this->mock_http( array( 'api.openrouteservice.org/geocode/' => array( 'body' => $this->fixture( 'geo/pelias-reverse.json' ) ) ) );
		$addr = $this->provider( 'Geo_Provider_OpenRoute', array( 'api' => 'ors-key' ) )->reverse_lookup();
		$this->assertSame( 'https://api.openrouteservice.org/geocode/reverse', $this->last_request()['base'] );
		$this->assertCityHall( $addr );

		$result = ( new Geo_Provider_OpenRoute( array( 'api' => 'ors-key' ) ) )->geocode( 'City Hall' );
		$this->assertSame( 'https://api.openrouteservice.org/geocode/search', $this->last_request()['base'] );
		$this->assertSame( self::LAT, $result['latitude'] );
	}

	/**
	 * Providers whose parsing is blocked by open issues still refuse to run without credentials.
	 */
	public function test_missing_credentials() {
		$this->mock_http( array() );
		$this->assertSame( 'missing_api_key', $this->provider( 'Geo_Provider_Here', array( 'api' => '' ) )->reverse_lookup()->get_error_code() );
		$this->assertSame( 'missing_api_key', ( new Geo_Provider_Here( array( 'api' => '' ) ) )->geocode( 'x' )->get_error_code() );
		$this->assertSame( 'missing_api_key', $this->provider( 'Geo_Provider_GeoApify', array( 'api' => '' ) )->reverse_lookup()->get_error_code() );
		$this->assertSame( 'missing_api_key', ( new Geo_Provider_GeoApify( array( 'api' => '' ) ) )->geocode( 'x' )->get_error_code() );
		$this->assertSame( 'missing_api_key', ( new Geo_Provider_Google( array( 'api' => '' ) ) )->geocode( 'x' )->get_error_code() );
		$this->assertSame( 'missing_api_key', ( new Geo_Provider_LocationIQ( array( 'api' => '' ) ) )->geocode( 'x' )->get_error_code() );
		$this->assertSame( 'no_username_set', ( new Geo_Provider_Geonames( array( 'user' => '' ) ) )->geocode( 'x' )->get_error_code() );
		$this->assertSame( array(), $this->http_requests, 'No request is made without credentials.' );
	}

	/**
	 * GeoNames account errors become WP_Errors.
	 */
	public function test_geonames_status_error() {
		$this->mock_http( array( 'secure.geonames.org/search' => array( 'body' => $this->fixture( 'geo/geonames-error.json' ) ) ) );
		$error = ( new Geo_Provider_Geonames( array( 'user' => 'demo' ) ) )->geocode( 'Philadelphia' );
		$this->assertSame( 'unknown_error', $error->get_error_code() );
		$this->assertStringContainsString( 'not enabled', $error->get_error_message() );
		$this->assertSame( 'demo', $this->last_request()['query']['username'] );
	}

	/**
	 * API keys are read from each provider's option.
	 */
	public function test_api_key_options() {
		$options = array(
			'Geo_Provider_Google'     => 'sloc_google_api',
			'Geo_Provider_Bing'       => 'sloc_bing_api',
			'Geo_Provider_Here'       => 'sloc_here_api',
			'Geo_Provider_LocationIQ' => 'sloc_locationiq_api',
			'Geo_Provider_Mapquest'   => 'sloc_mapquest_api',
			'Geo_Provider_OpenRoute'  => 'sloc_openroute_api',
			'Geo_Provider_GeoApify'   => 'sloc_geoapify_api',
			'Geo_Provider_Pelias'     => 'sloc_pelias_api',
		);
		$this->mock_http( array() );
		foreach ( $options as $class => $option ) {
			update_option( $option, 'from-option' );
			$provider = new $class();
			$property = new ReflectionProperty( $provider, 'api' );
			if ( PHP_VERSION_ID < 80100 ) {
				$property->setAccessible( true );
			}
			$this->assertSame( 'from-option', $property->getValue( $provider ), $class );
		}
	}

	/**
	 * The display name is built from the most specific parts, leaving out the home country.
	 */
	public function test_display_name() {
		$provider = new Geo_Provider_Nominatim();
		$this->assertFalse( $provider->display_name( 'text' ) );
		$this->assertSame( 'Given', $provider->display_name( array( 'display_name' => 'Given' ) ) );

		$addr = array(
			'name'         => 'City Hall',
			'locality'     => 'Philadelphia',
			'region'       => 'Pennsylvania',
			'region-code'  => 'PA',
			'country-code' => 'US',
		);
		$this->assertSame( 'City Hall, Philadelphia, PA, US', $provider->display_name( $addr ) );
		update_option( 'sloc_country', 'US' );
		$this->assertSame( 'City Hall, Philadelphia, PA', $provider->display_name( $addr ) );

		$this->assertSame(
			'Center City, Philadelphia, Pennsylvania, United States',
			$provider->display_name(
				array(
					'extended-address' => 'Center City',
					'locality'         => 'Philadelphia',
					'region'           => 'Pennsylvania',
					'country-name'     => 'United States',
				)
			)
		);
		$this->assertSame( 'Pennsylvania', $provider->display_name( array( 'region' => 'Pennsylvania' ) ) );

		$filter = function ( $name ) {
			return strtoupper( $name );
		};
		add_filter( 'location_display_name', $filter );
		$this->assertSame( 'PENNSYLVANIA', $provider->display_name( array( 'region' => 'Pennsylvania' ) ) );
		remove_filter( 'location_display_name', $filter );
	}

	/**
	 * Geo providers get elevation from the elevation provider, or 0 without one.
	 */
	public function test_elevation() {
		$provider = new Geo_Provider_Nominatim();
		$provider->set( self::LAT, self::LNG );
		$this->assertSame( 123.0, $provider->elevation() );
		update_option( 'sloc_elevation_provider', 'none' );
		$this->assertSame( 0, $provider->elevation() );
	}

	/**
	 * A provider is active when it is the chosen geo provider.
	 */
	public function test_is_active() {
		update_option( 'sloc_geo_provider', 'bing' );
		$this->assertTrue( ( new Geo_Provider_Bing( array( 'api' => '' ) ) )->is_active() );
		$this->assertFalse( ( new Geo_Provider_Nominatim() )->is_active() );
	}
}

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
	 * Asserts the parts of the City Hall address every provider should agree on.
	 *
	 * Fixtures may be replaced with real captures (see tests/data/README.md), so only stable
	 * facts are checked here; vendor-specific details are compared with the fixture itself.
	 *
	 * @param array $addr Parsed address.
	 */
	protected function assertCityHall( $addr ) {
		$expected = array(
			'locality'     => 'Philadelphia',
			'region'       => 'Pennsylvania',
			'region-code'  => 'PA',
			'country-code' => 'US',
		);
		foreach ( $expected as $key => $value ) {
			$this->assertArrayHasKey( $key, $addr, $key );
			$this->assertSame( $value, $addr[ $key ], $key );
		}
		$this->assertStringStartsWith( '191', $addr['postal-code'], 'A Philadelphia ZIP code.' );
		$this->assertMatchesRegularExpression( '/Kennedy|JFK/i', $addr['street-address'] );
		$this->assertEquals( self::LAT, $addr['latitude'] );
		$this->assertEquals( self::LNG, $addr['longitude'] );
	}

	/**
	 * Every bundled geo provider is registered with its slug and name.
	 */
	public function test_providers_registered() {
		$providers = Loc_Config::geo_providers();
		foreach ( array( 'nominatim', 'locationiq', 'google', 'mapquest', 'pelias', 'openroute', 'geoapify', 'here', 'geonames' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $providers );
			$this->assertNotEmpty( $providers[ $slug ]['name'], $slug );
			$this->assertNotEmpty( $providers[ $slug ]['url'], $slug );
		}
	}

	/**
	 * Impossible coordinates are rejected by set(), which keeps the previous ones.
	 */
	public function test_set_rejects_invalid_coordinates() {
		$provider = $this->provider( 'Geo_Provider_Nominatim' );
		$this->assertFalse( $provider->set( 95, self::LNG ) );
		$this->assertFalse( $provider->set( self::LAT, 300 ) );
		$this->assertFalse( $provider->set( 'north', 'west' ) );
		$this->assertFalse(
			$provider->set(
				array(
					'latitude'  => 300,
					'longitude' => self::LNG,
				)
			)
		);
		$geo = $provider->get();
		$this->assertEquals( self::LAT, $geo['latitude'] );
		$this->assertEquals( self::LNG, $geo['longitude'] );
	}

	/**
	 * Whole numbers are accepted by set().
	 */
	public function test_set_whole_numbers() {
		$provider = $this->provider( 'Geo_Provider_Nominatim' );
		$this->assertTrue( $provider->set( '45', -75, '5' ) );
		$geo = $provider->get();
		$this->assertSame( 45.0, $geo['latitude'] );
		$this->assertSame( -75.0, $geo['longitude'] );
		$this->assertSame( 5.0, $geo['altitude'] );
	}

	/**
	 * Coordinates and altitude of 0 are kept by get().
	 */
	public function test_get_keeps_zero() {
		$provider = $this->provider( 'Geo_Provider_Nominatim' );
		$this->assertTrue( $provider->set( 0, 10, 0 ) );
		$this->assertSame(
			array(
				'latitude'  => 0.0,
				'longitude' => 10.0,
				'altitude'  => 0.0,
			),
			$provider->get()
		);
		$this->assertTrue( $provider->set( 10, 0 ) );
		$this->assertSame( 0.0, $provider->get()['longitude'] );
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
		$this->assertSame( 'The service returned HTTP status 404: Not Found', $error->get_error_message() );
		$this->assertSame(
			array(
				'status' => 404,
				'body'   => 'Not Found',
			),
			$error->get_error_data()
		);

		$this->mock_http( array( 'example.com/html' => array( 'body' => '<html></html>' ) ) );
		$error = $provider->fetch_json( 'https://example.com/html', array() );
		$this->assertSame( 'not_json_response', $error->get_error_code() );
		$this->assertSame( 'The service did not return JSON.', $error->get_error_message() );
		$this->assertSame( '<html></html>', $error->get_error_data()['body'] );

		$this->mock_http( array( 'example.com/down' => new WP_Error( 'http_request_failed', 'Timed out' ) ) );
		$this->assertSame( 'http_request_failed', $provider->fetch_json( 'https://example.com/down', array() )->get_error_code() );
	}

	/**
	 * Error messages are a short line of plain text, and neither they nor the stored response contain credentials
	 * sent with the request, even when the service echoes the request back.
	 */
	public function test_fetch_json_error_redacts_credentials() {
		$provider = new Geo_Provider_LocationIQ( array( 'api' => 'pk.SECRET123' ) );
		$echo     = '<html><body><h1>Unauthorized</h1><p>Request https://example.com/x?key=pk.SECRET123&amp;token=tok%2Fen&amp;q=Philadelphia was refused for pk.SECRET123</p>' . str_repeat( ' padding', 100 ) . '</body></html>';
		$this->mock_http(
			array(
				'example.com/x' => array(
					'code' => 401,
					'body' => $echo,
				),
			)
		);
		$error   = $provider->fetch_json(
			'https://example.com/x',
			array(
				'key'   => 'pk.SECRET123',
				'token' => 'tok/en',
				'q'     => 'Philadelphia',
			)
		);
		$message = $error->get_error_message();
		$this->assertStringStartsWith( 'The service returned HTTP status 401: Unauthorized Request https://example.com/x?key=[redacted]', $message );
		$this->assertStringNotContainsString( 'SECRET123', $message );
		$this->assertStringNotContainsString( '<', $message );
		$this->assertLessThanOrEqual( 250, strlen( $message ) );
		$body = $error->get_error_data()['body'];
		$this->assertStringNotContainsString( 'SECRET123', $body );
		$this->assertStringNotContainsString( 'tok%2Fen', $body );
		$this->assertStringContainsString( 'q=Philadelphia', $body, 'Values that aren\'t credentials are kept.' );

		// An empty error response gives just the status.
		$this->mock_http(
			array(
				'example.com/x' => array(
					'code' => 503,
					'body' => '',
				),
			)
		);
		$this->assertSame( 'The service returned HTTP status 503.', $provider->fetch_json( 'https://example.com/x', array() )->get_error_message() );
	}

	/**
	 * JSON that isn't an object or a list, such as `true`, is an error, so providers never parse it.
	 */
	public function test_fetch_json_unexpected_response() {
		$this->mock_http( array( 'example.com/x' => array( 'body' => 'true' ) ) );
		$error = ( new Geo_Provider_Nominatim() )->fetch_json( 'https://example.com/x', array() );
		$this->assertSame( 'unexpected_response', $error->get_error_code() );
		$this->assertSame( 'true', $error->get_error_data()['body'] );
	}

	/**
	 * Pelias and OpenRoute: no results, and Pelias's own errors, are errors for reverse and forward lookups.
	 */
	public function test_pelias_no_results() {
		update_option( 'sloc_pelias_url', 'https://203.0.113.10/v1' );
		$empty = array(
			'type'     => 'FeatureCollection',
			'features' => array(),
		);
		foreach ( array( 'Geo_Provider_Pelias', 'Geo_Provider_OpenRoute' ) as $class ) {
			$this->mock_http( array( '' => array( 'body' => $empty ) ) );
			$this->assertSame( 'no_results', $this->provider( $class, array( 'api' => 'KEY' ) )->reverse_lookup()->get_error_code(), $class );
			$this->assertSame( 'no_results', ( new $class( array( 'api' => 'KEY' ) ) )->geocode( 'nowhere' )->get_error_code(), $class );

			$this->mock_http(
				array(
					'' => array(
						'body' => array_merge( $empty, array( 'geocoding' => array( 'errors' => array( "'text' parameter is required" ) ) ) ),
					),
				)
			);
			$error = ( new $class( array( 'api' => 'KEY' ) ) )->geocode( '' );
			$this->assertSame( 'pelias_error', $error->get_error_code(), $class );
			$this->assertSame( "'text' parameter is required", $error->get_error_message() );
		}
	}

	/**
	 * GeoNames: a search that finds nothing, or a reverse lookup with no address, is an error.
	 */
	public function test_geonames_no_results() {
		$this->mock_http(
			array(
				'geonames.org' => array(
					'body' => array(
						'totalResultsCount' => 0,
						'geonames'          => array(),
					),
				),
			)
		);
		$this->assertSame( 'no_results', ( new Geo_Provider_Geonames( array( 'user' => 'someone' ) ) )->geocode( 'nowhere' )->get_error_code() );
		$this->assertSame( 'no_results', $this->provider( 'Geo_Provider_Geonames', array( 'user' => 'someone' ) )->reverse_lookup()->get_error_code() );
	}

	/**
	 * Looking up a country without a code finds nothing, without a deprecation notice.
	 */
	public function test_country_data_without_code() {
		$this->assertFalse( Geo_Provider::country_data( null ) );
		$this->assertFalse( Geo_Provider::country_data( '' ) );
		$this->assertSame( 'United States', Geo_Provider::country_data( 'us' )['name'] );
	}

	/**
	 * Every 2xx status is a success, not only 200.
	 */
	public function test_fetch_json_success_statuses() {
		$provider = new Geo_Provider_Nominatim();
		foreach ( array( 200, 201, 203 ) as $code ) {
			$this->mock_http(
				array(
					'example.com/ok' => array(
						'code' => $code,
						'body' => array( 'ok' => true ),
					),
				)
			);
			$this->assertSame( array( 'ok' => true ), $provider->fetch_json( 'https://example.com/ok', array() ), (string) $code );
		}
	}

	/**
	 * Nominatim reverse geocoding sends the documented parameters and parses the address.
	 */
	public function test_nominatim_reverse() {
		$fixture = $this->fixture( 'geo/nominatim-reverse.json' );
		$this->mock_http( array( 'nominatim.openstreetmap.org/reverse' => array( 'body' => $fixture ) ) );
		$addr = $this->provider( 'Geo_Provider_Nominatim' )->reverse_lookup();

		$request = $this->last_request();
		$this->assertSame( 'https://nominatim.openstreetmap.org/reverse', $request['base'] );
		$this->assertSame( 'jsonv2', $request['query']['format'] );
		$this->assertEquals( self::LAT, $request['query']['lat'] );
		$this->assertEquals( 18, $request['query']['zoom'] );

		$this->assertCityHall( $addr );
		$address = $fixture['address'];
		$this->assertSame( $address['house_number'] . ' ' . $address['road'], $addr['street-address'] );
		$this->assertSame( $address['tourism'], $addr['name'] );
		$this->assertSame( $address['neighbourhood'], $addr['extended-address'] );
		$this->assertSame( $address['country'], $addr['country-name'] );
		$this->assertSame( $fixture['extratags']['website'], $addr['url'] );
		$this->assertSame( $address['tourism'] . ', Philadelphia, PA, US', $addr['display-name'] );
		$this->assertSame( 'America/New_York', $addr['timezone'] );
		$this->assertSame( $fixture, $addr['raw'] );
	}

	/**
	 * Nominatim reverse geocoding passes errors on, and returns nothing without an address.
	 */
	public function test_nominatim_reverse_errors() {
		// A point with no address, such as open ocean, is an error with Nominatim's message.
		$this->mock_http( array( 'nominatim' => array( 'body' => $this->fixture( 'geo/nominatim-error.json' ) ) ) );
		$error = $this->provider( 'Geo_Provider_Nominatim' )->reverse_lookup();
		$this->assertSame( 'no_results', $error->get_error_code() );
		$this->assertSame( 'Unable to geocode', $error->get_error_message() );
		$this->assertSame( array( 'status' => 404 ), $error->get_error_data() );

		$this->mock_http( array( 'locationiq.com' => array( 'body' => $this->fixture( 'geo/nominatim-error.json' ) ) ) );
		$this->assertSame( 'Unable to geocode', $this->provider( 'Geo_Provider_LocationIQ', array( 'api' => 'KEY' ) )->reverse_lookup()->get_error_message() );

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
	 * Nominatim: a place with no region, such as Singapore, has no region code, without a warning.
	 */
	public function test_nominatim_no_region() {
		$fixture            = $this->fixture( 'geo/nominatim-reverse.json' );
		$fixture['address'] = array(
			'road'         => 'Orchard Road',
			'city'         => 'Singapore',
			'country'      => 'Singapore',
			'country_code' => 'sg',
		);
		$this->mock_http( array( 'nominatim.openstreetmap.org/reverse' => array( 'body' => $fixture ) ) );
		$addr = $this->provider( 'Geo_Provider_Nominatim' )->reverse_lookup();
		$this->assertSame( 'Singapore', $addr['locality'] );
		$this->assertArrayNotHasKey( 'region-code', $addr );

		$this->mock_http( array( 'nominatim.openstreetmap.org/reverse' => array( 'body' => $fixture ) ) );
		$this->assertIsArray( $this->provider( 'Venue_Provider_Nominatim' )->reverse_lookup() );
	}

	/**
	 * Nominatim forward geocoding returns the address and coordinates of the first result.
	 */
	public function test_nominatim_geocode() {
		$fixture = $this->fixture( 'geo/nominatim-search.json' );
		$this->mock_http( array( 'nominatim.openstreetmap.org/search' => array( 'body' => $fixture ) ) );
		$result = ( new Geo_Provider_Nominatim() )->geocode( 'Philadelphia City Hall' );
		$this->assertSame( 'Philadelphia City Hall', $this->last_request()['query']['q'] );
		$this->assertSame( $fixture[0]['lat'], $result['latitude'] );
		$this->assertSame( $fixture[0]['lon'], $result['longitude'] );
		$this->assertEqualsWithDelta( self::LAT, (float) $result['latitude'], 0.001 );
		$this->assertSame( $fixture[0]['extratags']['website'], $result['url'] );
		$this->assertCityHall( $result );
		$this->assertSame( 'United States', $result['country-name'] );
		$this->assertSame( 'America/New_York', $result['timezone'] );
	}

	/**
	 * Forward geocoding returns an error when nothing is found, and leaves the provider's own location alone.
	 */
	public function test_nominatim_geocode_no_results() {
		// fetch_json() already treats an empty list as an error.
		$this->mock_http( array( 'nominatim.openstreetmap.org/search' => array( 'body' => '[]' ) ) );
		$provider = $this->provider( 'Geo_Provider_Nominatim' );
		$this->assertWPError( $provider->geocode( 'Nowhere at all' ) );

		$this->mock_http( array( 'nominatim.openstreetmap.org/search' => array( 'body' => array( 'error' => 'Unable to geocode' ) ) ) );
		$this->assertSame( 'no_results', $provider->geocode( 'Nowhere at all' )->get_error_code() );

		$this->mock_http( array( 'nominatim.openstreetmap.org/search' => array( 'body' => $this->fixture( 'geo/nominatim-search.json' ) ) ) );
		$provider->set( 51.5, -0.12 );
		$provider->geocode( 'Philadelphia City Hall' );
		$this->assertEquals( 51.5, $provider->get()['latitude'] );
	}

	/**
	 * LocationIQ forward geocoding needs a key and returns the address of the first result.
	 */
	public function test_locationiq_geocode() {
		$error = ( new Geo_Provider_LocationIQ( array( 'api' => '' ) ) )->geocode( 'Philadelphia City Hall' );
		$this->assertSame( 'missing_api_key', $error->get_error_code() );
		$this->assertSame( 'You have not set an API key for LocationIQ', $error->get_error_message() );

		// LocationIQ returns the same format as Nominatim.
		$this->mock_http( array( 'locationiq.com/v1/search' => array( 'body' => $this->fixture( 'geo/nominatim-search.json' ) ) ) );
		$result = ( new Geo_Provider_LocationIQ( array( 'api' => 'liq-key' ) ) )->geocode( 'Philadelphia City Hall' );
		$this->assertSame( 'liq-key', $this->last_request()['query']['key'] );
		$this->assertCityHall( $result );

		$this->mock_http( array( 'locationiq.com/v1/search' => array( 'body' => array( 'error' => 'Unable to geocode' ) ) ) );
		$this->assertSame( 'no_results', ( new Geo_Provider_LocationIQ( array( 'api' => 'liq-key' ) ) )->geocode( 'Nowhere' )->get_error_code() );
	}

	/**
	 * LocationIQ needs its own key, uses its own endpoint, and the state code it returns.
	 */
	public function test_locationiq_reverse() {
		$error = $this->provider( 'Geo_Provider_LocationIQ', array( 'api' => '' ) )->reverse_lookup();
		$this->assertSame( 'missing_api_key', $error->get_error_code() );
		$this->assertSame( 'You have not set an API key for LocationIQ', $error->get_error_message() );

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

		$fixture = $this->fixture( 'geo/google-reverse.json' );
		$this->mock_http( array( 'maps.googleapis.com/maps/api/geocode/json' => array( 'body' => $fixture ) ) );
		$addr    = $this->provider( 'Geo_Provider_Google', array( 'api' => 'g-key' ) )->reverse_lookup();
		$request = $this->last_request();
		$this->assertSame( 'g-key', $request['query']['key'] );
		$this->assertSame( self::LAT . ',' . self::LNG, $request['query']['latlng'] );

		$this->assertCityHall( $addr );
		$components = wp_list_pluck( $fixture['results'][0]['address_components'], 'short_name' );
		$this->assertSame( $components[0] . ' ' . $components[1], $addr['street-address'], 'Street number and route.' );
		$this->assertSame( 'United States', $addr['country-name'] );
		$this->assertSame( $fixture['plus_code']['global_code'], $addr['plus-code'] );
		$this->assertSame( $fixture['results'][0]['formatted_address'], $addr['display-name'], "Google's formatted address." );
	}

	/**
	 * Google forward geocoding returns the address and coordinates of the first result.
	 */
	public function test_google_geocode() {
		$this->assertSame( 'missing_api_key', ( new Geo_Provider_Google( array( 'api' => '' ) ) )->geocode( 'Philadelphia City Hall' )->get_error_code() );

		// A search returns the same format as a reverse lookup.
		$fixture = $this->fixture( 'geo/google-reverse.json' );
		$this->mock_http( array( 'maps.googleapis.com/maps/api/geocode/json' => array( 'body' => $fixture ) ) );
		$result = ( new Geo_Provider_Google( array( 'api' => 'g-key' ) ) )->geocode( 'Philadelphia City Hall' );
		$this->assertSame( 'Philadelphia City Hall', $this->last_request()['query']['address'] );
		$this->assertCityHall( $result );
		$this->assertSame( $fixture['results'][0]['formatted_address'], $result['display-name'] );
		$this->assertSame( 'America/New_York', $result['timezone'] );
	}

	/**
	 * Google forward geocoding returns an error when nothing is found or the request fails.
	 */
	public function test_google_geocode_errors() {
		$provider = new Geo_Provider_Google( array( 'api' => 'g-key' ) );
		$this->mock_http(
			array(
				'maps.googleapis.com' => array(
					'body' => array(
						'results' => array(),
						'status'  => 'ZERO_RESULTS',
					),
				),
			)
		);
		$this->assertSame( 'no_results', $provider->geocode( 'Nowhere' )->get_error_code() );

		$this->mock_http( array( 'maps.googleapis.com' => array( 'body' => $this->fixture( 'geo/google-denied.json' ) ) ) );
		$error = $provider->geocode( 'Philadelphia City Hall' );
		$this->assertSame( 'REQUEST_DENIED', $error->get_error_code() );
		$this->assertSame( 'The provided API key is invalid.', $error->get_error_message() );
	}

	/**
	 * Google errors in the response status become WP_Errors.
	 */
	public function test_google_reverse_status_error() {
		$this->mock_http( array( 'maps.googleapis.com' => array( 'body' => $this->fixture( 'geo/google-denied.json' ) ) ) );
		$error = $this->provider( 'Geo_Provider_Google', array( 'api' => 'g-key' ) )->reverse_lookup();
		$this->assertSame( 'REQUEST_DENIED', $error->get_error_code() );
		$this->assertSame( 'The provided API key is invalid.', $error->get_error_message() );
	}

	/**
	 * MapQuest reverse and forward geocoding parse the first location.
	 */
	public function test_mapquest() {
		$this->assertSame( 'missing_api_key', $this->provider( 'Geo_Provider_Mapquest', array( 'api' => '' ) )->reverse_lookup()->get_error_code() );
		$this->assertSame( 'missing_api_key', ( new Geo_Provider_Mapquest( array( 'api' => '' ) ) )->geocode( 'x' )->get_error_code() );

		$fixture  = $this->fixture( 'geo/mapquest-reverse.json' );
		$location = $fixture['results'][0]['locations'][0];
		$this->mock_http( array( 'mapquestapi.com/geocoding/v1/' => array( 'body' => $fixture ) ) );
		$addr    = $this->provider( 'Geo_Provider_Mapquest', array( 'api' => 'mq-key' ) )->reverse_lookup();
		$request = $this->last_request();
		$this->assertSame( 'https://www.mapquestapi.com/geocoding/v1/reverse', $request['base'] );
		$this->assertSame( 'mq-key', $request['query']['key'] );
		$this->assertCityHall( $addr );
		$this->assertSame( $location['street'], $addr['street-address'] );
		$this->assertSame( 'United States', $addr['country-name'] );
		$this->assertSame( $location['adminArea6'] ?? null, $addr['extended-address'] ?? null );

		$result = ( new Geo_Provider_Mapquest( array( 'api' => 'mq-key' ) ) )->geocode( 'City Hall' );
		$this->assertSame( 'https://www.mapquestapi.com/geocoding/v1/address', $this->last_request()['base'] );
		$this->assertSame( $location['latLng']['lat'], $result['latitude'] );
	}

	/**
	 * MapQuest responses without results or locations are errors.
	 */
	public function test_mapquest_errors() {
		// MapQuest's own error status, with HTTP 200, is reported with its message, for reverse and forward lookups.
		$this->mock_http( array( 'mapquestapi.com' => array( 'body' => $this->fixture( 'geo/mapquest-no-results.json' ) ) ) );
		foreach ( array( $this->provider( 'Geo_Provider_Mapquest', array( 'api' => 'mq-key' ) )->reverse_lookup(), ( new Geo_Provider_Mapquest( array( 'api' => 'mq-key' ) ) )->geocode( 'City Hall' ) ) as $error ) {
			$this->assertSame( 'mapquest_error', $error->get_error_code() );
			$this->assertSame( 'This key is not authorized for this service.', $error->get_error_message() );
			$this->assertSame( array( 'status' => 403 ), $error->get_error_data() );
		}

		$this->mock_http( array( 'mapquestapi.com' => array( 'body' => $this->fixture( 'geo/mapquest-no-locations.json' ) ) ) );
		$this->assertSame( 'no_results', $this->provider( 'Geo_Provider_Mapquest', array( 'api' => 'mq-key' ) )->reverse_lookup()->get_error_code() );
		$this->assertSame( 'no_results', ( new Geo_Provider_Mapquest( array( 'api' => 'mq-key' ) ) )->geocode( 'nowhere' )->get_error_code() );
	}

	/**
	 * Pelias needs a key and a service URL, picks the most confident feature and converts ISO3 country codes.
	 */
	public function test_pelias_reverse() {
		$this->assertSame( 'missing_api_key', $this->provider( 'Geo_Provider_Pelias', array( 'api' => '' ) )->reverse_lookup()->get_error_code() );
		update_option( 'sloc_pelias_url', 'not a url' );
		$this->assertSame( 'missing_pelias_url', $this->provider( 'Geo_Provider_Pelias', array( 'api' => 'p-key' ) )->reverse_lookup()->get_error_code() );
		update_option( 'sloc_pelias_url', 'https://203.0.113.10/v1' );

		$fixture    = $this->fixture( 'geo/pelias-reverse.json' );
		$properties = $fixture['features'][0]['properties'];
		$this->mock_http( array( '203.0.113.10/v1/reverse/' => array( 'body' => $fixture ) ) );
		$addr    = $this->provider( 'Geo_Provider_Pelias', array( 'api' => 'p-key' ) )->reverse_lookup();
		$request = $this->last_request();
		$this->assertSame( 'p-key', $request['query']['api_key'] );
		$this->assertEquals( self::LAT, $request['query']['point_lat'] );

		$this->assertCityHall( $addr );
		$this->assertSame( $properties['housenumber'] . ' ' . $properties['street'], $addr['street-address'] );
		$this->assertSame( $properties['name'], $addr['name'] );
		$this->assertSame( $properties['neighbourhood'], $addr['extended-address'] );
		$this->assertSame( $properties['name'] . ', Philadelphia, PA, US', $addr['display-name'] );
	}

	/**
	 * A street-address result has no name of its own, so the display name is the address.
	 */
	public function test_pelias_reverse_address() {
		update_option( 'sloc_pelias_url', 'https://203.0.113.10/v1' );
		$fixture = $this->fixture( 'geo/pelias-reverse.json' );
		$feature = $fixture['features'][0];

		$feature['properties']['layer'] = 'address';
		$feature['properties']['name']  = '1400 John F. Kennedy Boulevard';
		$fixture['features']            = array( $feature );
		$this->mock_http( array( '203.0.113.10/v1/reverse/' => array( 'body' => $fixture ) ) );
		$addr = $this->provider( 'Geo_Provider_Pelias', array( 'api' => 'p-key' ) )->reverse_lookup();

		$this->assertArrayNotHasKey( 'name', $addr );
		$this->assertSame( '1400 John F. Kennedy Boulevard', $addr['street-address'] );
		$this->assertStringStartsWith( '1400 John F. Kennedy Boulevard', $addr['display-name'] );
	}

	/**
	 * Of results with equal confidence, a venue wins over an address, then the closer result wins.
	 */
	public function test_pelias_reverse_closer_result() {
		update_option( 'sloc_pelias_url', 'https://203.0.113.10/v1' );
		$fixture = $this->fixture( 'geo/pelias-reverse.json' );
		$far     = $fixture['features'][0];
		$near    = $far;
		$address = $far;

		$far['properties']['name']         = 'Far Venue';
		$far['properties']['distance']     = 0.5;
		$near['properties']['name']        = 'Near Venue';
		$near['properties']['distance']    = 0.1;
		$address['properties']['layer']    = 'address';
		$address['properties']['distance'] = 0.01;

		$fixture['features'] = array( $far, $address, $near );
		$this->mock_http( array( '203.0.113.10/v1/reverse/' => array( 'body' => $fixture ) ) );
		$addr = $this->provider( 'Geo_Provider_Pelias', array( 'api' => 'p-key' ) )->reverse_lookup();
		$this->assertSame( 'Near Venue', $addr['name'] );
	}

	/**
	 * Pelias forward geocoding takes the coordinates from the first feature.
	 */
	public function test_pelias_geocode() {
		$fixture = $this->fixture( 'geo/pelias-search.json' );
		$this->mock_http( array( '203.0.113.10/v1/search/' => array( 'body' => $fixture ) ) );
		$result = ( new Geo_Provider_Pelias( array( 'api' => 'p-key' ) ) )->geocode( 'City Hall' );
		$this->assertSame( 'City Hall', $this->last_request()['query']['text'] );
		$this->assertSame( $fixture['features'][0]['geometry']['coordinates'][1], $result['latitude'] );
		$this->assertSame( $fixture['features'][0]['geometry']['coordinates'][0], $result['longitude'] );
		$this->assertSame( $fixture['features'][0]['properties']['name'], $result['name'] );
	}

	/**
	 * Geoapify reverse geocoding parses Geoapify's own property names.
	 */
	public function test_geoapify_reverse() {
		$fixture    = $this->fixture( 'geo/geoapify-reverse.json' );
		$properties = $fixture['features'][0]['properties'];
		$this->mock_http( array( 'api.geoapify.com/v1/geocode/reverse/' => array( 'body' => $fixture ) ) );
		$addr    = $this->provider( 'Geo_Provider_GeoApify', array( 'api' => 'ga-key' ) )->reverse_lookup();
		$request = $this->last_request();
		$this->assertSame( 'https://api.geoapify.com/v1/geocode/reverse/', $request['base'] );
		$this->assertSame( 'ga-key', $request['query']['apiKey'] );
		$this->assertEquals( self::LAT, $request['query']['lat'] );
		$this->assertEquals( self::LNG, $request['query']['lon'] );

		$this->assertCityHall( $addr );
		$this->assertSame( $properties['housenumber'] . ' ' . $properties['street'], $addr['street-address'] );
		$this->assertSame( $properties['name'], $addr['name'] );
		$this->assertSame( $properties['suburb'], $addr['extended-address'] );
		$this->assertSame( $properties['country'], $addr['country-name'] );
		$this->assertSame( $properties['name'] . ', Philadelphia, PA, US', $addr['display-name'] );
	}

	/**
	 * Geoapify looks up a region code from the region name when there's no state code.
	 */
	public function test_geoapify_reverse_without_state_code() {
		$fixture = $this->fixture( 'geo/geoapify-reverse.json' );
		unset( $fixture['features'][0]['properties']['state_code'] );
		$this->mock_http( array( 'api.geoapify.com' => array( 'body' => $fixture ) ) );
		$addr = $this->provider( 'Geo_Provider_GeoApify', array( 'api' => 'ga-key' ) )->reverse_lookup();
		$this->assertSame( 'PA', $addr['region-code'] );
	}

	/**
	 * Geoapify forward geocoding takes the coordinates from the first feature.
	 */
	public function test_geoapify_geocode() {
		$error = ( new Geo_Provider_GeoApify( array( 'api' => '' ) ) )->geocode( 'x' );
		$this->assertSame( 'missing_api_key', $error->get_error_code() );
		$this->assertStringContainsString( 'GeoApify', $error->get_error_message() );

		$fixture = $this->fixture( 'geo/geoapify-search.json' );
		$this->mock_http( array( 'api.geoapify.com/v1/geocode/search' => array( 'body' => $fixture ) ) );
		$result  = ( new Geo_Provider_GeoApify( array( 'api' => 'ga-key' ) ) )->geocode( 'City Hall, Philadelphia' );
		$request = $this->last_request();
		$this->assertSame( 'https://api.geoapify.com/v1/geocode/search', $request['base'] );
		$this->assertSame( 'City Hall, Philadelphia', $request['query']['text'] );
		$this->assertSame( $fixture['features'][0]['geometry']['coordinates'][1], $result['latitude'] );
		$this->assertSame( $fixture['features'][0]['geometry']['coordinates'][0], $result['longitude'] );
		$this->assertSame( 'Philadelphia', $result['locality'] );
		$this->assertSame( 'US', $result['country-code'] );
	}

	/**
	 * Geoapify responses without features are errors.
	 */
	public function test_geoapify_no_results() {
		$this->mock_http( array( 'api.geoapify.com' => array( 'body' => $this->fixture( 'geo/geoapify-empty.json' ) ) ) );
		$this->assertSame( 'invalid_response', $this->provider( 'Geo_Provider_GeoApify', array( 'api' => 'ga-key' ) )->reverse_lookup()->get_error_code() );
		$this->assertSame( 'invalid_response', ( new Geo_Provider_GeoApify( array( 'api' => 'ga-key' ) ) )->geocode( 'nowhere' )->get_error_code() );
	}

	/**
	 * OpenRoute uses the Pelias format at its own endpoint.
	 */
	public function test_openroute() {
		$this->assertSame( 'missing_api_key', $this->provider( 'Geo_Provider_OpenRoute', array( 'api' => '' ) )->reverse_lookup()->get_error_code() );
		$this->assertSame( 'missing_api_key', ( new Geo_Provider_OpenRoute( array( 'api' => '' ) ) )->geocode( 'x' )->get_error_code() );

		$this->mock_http( array( 'api.openrouteservice.org/geocode/reverse' => array( 'body' => $this->fixture( 'geo/openroute-reverse.json' ) ) ) );
		$addr = $this->provider( 'Geo_Provider_OpenRoute', array( 'api' => 'ors-key' ) )->reverse_lookup();
		$this->assertSame( 'https://api.openrouteservice.org/geocode/reverse', $this->last_request()['base'] );
		$this->assertCityHall( $addr );

		$fixture = $this->fixture( 'geo/openroute-search.json' );
		$this->mock_http( array( 'api.openrouteservice.org/geocode/search' => array( 'body' => $fixture ) ) );
		$result = ( new Geo_Provider_OpenRoute( array( 'api' => 'ors-key' ) ) )->geocode( 'City Hall' );
		$this->assertSame( 'https://api.openrouteservice.org/geocode/search', $this->last_request()['base'] );
		$this->assertSame( $fixture['features'][0]['geometry']['coordinates'][1], $result['latitude'] );
	}

	/**
	 * HERE reverse geocoding converts the ISO3 country code and puts the house number first in the US.
	 */
	public function test_here_reverse() {
		$error = $this->provider( 'Geo_Provider_Here', array( 'api' => '' ) )->reverse_lookup();
		$this->assertSame( 'missing_api_key', $error->get_error_code() );
		$this->assertStringContainsString( 'HERE', $error->get_error_message() );

		$fixture = $this->fixture( 'geo/here-revgeocode.json' );
		$address = $fixture['items'][0]['address'];
		$this->mock_http( array( 'revgeocode.search.hereapi.com/v1/revgeocode' => array( 'body' => $fixture ) ) );
		$addr    = $this->provider( 'Geo_Provider_Here', array( 'api' => 'here-key' ) )->reverse_lookup();
		$request = $this->last_request();
		$this->assertSame( 'https://revgeocode.search.hereapi.com/v1/revgeocode', $request['base'] );
		$this->assertSame( 'here-key', $request['query']['apiKey'] );
		$this->assertSame( self::LAT . ',' . self::LNG, $request['query']['at'] );

		$this->assertCityHall( $addr );
		$this->assertSame( $address['houseNumber'] . ' ' . $address['street'], $addr['street-address'] );
		$this->assertSame( $address['countryName'], $addr['country-name'] );
		$this->assertSame( $address['district'], $addr['extended-address'] );
		$this->assertSame( $fixture['items'][0]['title'], $addr['name'] );
	}

	/**
	 * HERE puts the house number after the street where that's the local practice, without stray spaces.
	 */
	public function test_here_reverse_street_order() {
		$fixture = $this->fixture( 'geo/here-revgeocode.json' );

		// Germany puts the house number after the street.
		$fixture['items'][0]['address']['countryCode'] = 'DEU';
		$this->mock_http( array( 'hereapi.com' => array( 'body' => $fixture ) ) );
		$addr = $this->provider( 'Geo_Provider_Here', array( 'api' => 'here-key' ) )->reverse_lookup();
		$this->assertSame( 'John F Kennedy Blvd 1400', $addr['street-address'] );

		unset( $fixture['items'][0]['address']['houseNumber'] );
		$this->mock_http( array( 'hereapi.com' => array( 'body' => $fixture ) ) );
		$addr = $this->provider( 'Geo_Provider_Here', array( 'api' => 'here-key' ) )->reverse_lookup();
		$this->assertSame( 'John F Kennedy Blvd', $addr['street-address'] );
	}

	/**
	 * HERE forward geocoding uses the geocode host and takes the coordinates from the first item.
	 */
	public function test_here_geocode() {
		$error = ( new Geo_Provider_Here( array( 'api' => '' ) ) )->geocode( 'x' );
		$this->assertSame( 'missing_api_key', $error->get_error_code() );
		$this->assertStringContainsString( 'HERE', $error->get_error_message() );

		$fixture = $this->fixture( 'geo/here-revgeocode.json' );
		$this->mock_http( array( 'geocode.search.hereapi.com/v1/geocode' => array( 'body' => $fixture ) ) );
		$result  = ( new Geo_Provider_Here( array( 'api' => 'here-key' ) ) )->geocode( 'City Hall, Philadelphia' );
		$request = $this->last_request();
		$this->assertSame( 'https://geocode.search.hereapi.com/v1/geocode', $request['base'] );
		$this->assertSame( 'City Hall, Philadelphia', $request['query']['q'] );
		$this->assertSame( $fixture['items'][0]['position']['lat'], $result['latitude'] );
		$this->assertSame( $fixture['items'][0]['position']['lng'], $result['longitude'] );
		$this->assertSame( 'Philadelphia', $result['locality'] );
	}

	/**
	 * HERE responses without items are errors.
	 */
	public function test_here_no_results() {
		$this->mock_http( array( 'hereapi.com' => array( 'body' => $this->fixture( 'geo/here-empty.json' ) ) ) );
		$this->assertSame( 'invalid_response', $this->provider( 'Geo_Provider_Here', array( 'api' => 'here-key' ) )->reverse_lookup()->get_error_code() );
		$this->assertSame( 'invalid_response', ( new Geo_Provider_Here( array( 'api' => 'here-key' ) ) )->geocode( 'nowhere' )->get_error_code() );
	}

	/**
	 * Providers whose parsing is blocked by open issues still refuse to run without credentials.
	 */
	public function test_missing_credentials() {
		$this->mock_http( array() );
		$this->assertSame( 'missing_api_key', $this->provider( 'Geo_Provider_GeoApify', array( 'api' => '' ) )->reverse_lookup()->get_error_code() );
		$this->assertSame( 'missing_api_key', ( new Geo_Provider_GeoApify( array( 'api' => '' ) ) )->geocode( 'x' )->get_error_code() );
		$this->assertSame( 'missing_api_key', ( new Geo_Provider_Google( array( 'api' => '' ) ) )->geocode( 'x' )->get_error_code() );
		$this->assertSame( 'missing_api_key', ( new Geo_Provider_LocationIQ( array( 'api' => '' ) ) )->geocode( 'x' )->get_error_code() );
		$this->assertSame( 'no_username_set', ( new Geo_Provider_Geonames( array( 'user' => '' ) ) )->geocode( 'x' )->get_error_code() );
		$this->assertSame( array(), $this->http_requests, 'No request is made without credentials.' );
	}

	/**
	 * GeoNames reverse geocoding parses a US address, with the place name as the locality.
	 */
	public function test_geonames_reverse() {
		$this->assertSame( 'no_username_set', $this->provider( 'Geo_Provider_Geonames', array( 'user' => '' ) )->reverse_lookup()->get_error_code() );

		$fixture = $this->fixture( 'geo/geonames-reverse.json' );
		$this->mock_http( array( 'api.geonames.org/extendedFindNearbyJSON' => array( 'body' => $fixture ) ) );
		$addr    = $this->provider( 'Geo_Provider_Geonames', array( 'user' => 'demo' ) )->reverse_lookup();
		$request = $this->last_request();
		$this->assertSame( 'demo', $request['query']['username'] );
		$this->assertEquals( self::LAT, $request['query']['lat'] );

		$this->assertCityHall( $addr );
		$this->assertSame( '1400 John F Kennedy Blvd', $addr['street-address'] );
		$this->assertSame( 'United States', $addr['country-name'] );

		// The place name is the locality, not the county.
		$fixture['address']['placename']  = 'Menlo Park';
		$fixture['address']['adminName2'] = 'San Mateo';
		$this->mock_http( array( 'api.geonames.org/extendedFindNearbyJSON' => array( 'body' => $fixture ) ) );
		$addr = $this->provider( 'Geo_Provider_Geonames', array( 'user' => 'demo' ) )->reverse_lookup();
		$this->assertSame( 'Menlo Park', $addr['locality'] );
	}

	/**
	 * GeoNames account errors in a reverse lookup become WP_Errors.
	 */
	public function test_geonames_reverse_status_error() {
		$this->mock_http( array( 'api.geonames.org/extendedFindNearbyJSON' => array( 'body' => $this->fixture( 'geo/geonames-error.json' ) ) ) );
		$error = $this->provider( 'Geo_Provider_Geonames', array( 'user' => 'demo' ) )->reverse_lookup();
		$this->assertWPError( $error );
		$this->assertSame( 'unknown_error', $error->get_error_code() );
		$this->assertStringContainsString( 'not enabled', $error->get_error_message() );
	}

	/**
	 * The GeoNames provider links to GeoNames and describes itself in the description.
	 */
	public function test_geonames_details() {
		$provider = new Geo_Provider_Geonames( array( 'user' => 'demo' ) );
		$this->assertSame( 'https://www.geonames.org/', $provider->get_url() );
		$this->assertStringContainsString( 'creative commons', $provider->get_description() );
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
	 * Geo providers get elevation from the elevation provider, or an error without one rather than sea level.
	 */
	public function test_elevation() {
		$provider = new Geo_Provider_Nominatim();
		$provider->set( self::LAT, self::LNG );
		$this->assertSame( 123.0, $provider->elevation() );
		update_option( 'sloc_elevation_provider', 'none' );
		$this->assertSame( 'no_elevation_provider', $provider->elevation()->get_error_code() );
	}

	/**
	 * A provider is active when it is the chosen geo provider.
	 */
	public function test_is_active() {
		update_option( 'sloc_geo_provider', 'google' );
		$this->assertTrue( ( new Geo_Provider_Google( array( 'api' => '' ) ) )->is_active() );
		$this->assertFalse( ( new Geo_Provider_Nominatim() )->is_active() );
	}
}

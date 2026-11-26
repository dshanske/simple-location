<?php
/**
 * Tests for the Traccar Geocoder provider and its server list (#859).
 *
 * @package Simple_Location
 */

/**
 * Traccar Geocoder tests.
 */
class TraccarGeocoderTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

	/**
	 * Philadelphia City Hall.
	 */
	const LAT = 39.9524;
	const LNG = -75.1636;

	/**
	 * Home and travel servers, as in the readme example.
	 */
	const HOME   = 'http://203.0.113.10:3010';
	const TRAVEL = 'http://203.0.113.11:3011';

	/**
	 * The registered provider, restored after each test.
	 *
	 * @var Geo_Provider_Traccar_Geocoder
	 */
	protected $registered;

	/**
	 * Known settings for every test.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_country', '' );
		update_option( 'sloc_elevation_provider', 'sloc_test' );
		delete_option( Sloc_Provider_Errors::OPTION );
		delete_option( Geo_Provider_Traccar_Geocoder::OPTION );
		// Core treats the documentation range as not external, so allow it explicitly.
		add_filter( 'http_request_host_is_external', '__return_true' );
		$this->registered = Loc_Config::geo_provider( 'traccar_geocoder' );
	}

	/**
	 * Restores the registered provider.
	 */
	public function tear_down() {
		if ( $this->registered ) {
			register_sloc_provider( $this->registered );
		}
		parent::tear_down();
	}

	/**
	 * The two servers, each with its own key.
	 *
	 * @return array[] Servers.
	 */
	protected function servers() {
		return array(
			array(
				'url' => self::TRAVEL,
				'key' => 'travel-key',
			),
			array(
				'url' => self::HOME,
				'key' => 'home-key',
			),
		);
	}

	/**
	 * Creates a provider at City Hall.
	 *
	 * @param array $args Constructor arguments. Default the two servers.
	 * @return Geo_Provider_Traccar_Geocoder
	 */
	protected function provider( $args = array() ) {
		$provider = new Geo_Provider_Traccar_Geocoder( wp_parse_args( $args, array( 'servers' => $this->servers() ) ) );
		$provider->set( self::LAT, self::LNG );
		return $provider;
	}

	/**
	 * A full answer for City Hall.
	 *
	 * @return array Answer.
	 */
	protected function city_hall() {
		return array(
			'display_name' => '1400 John F Kennedy Boulevard, Philadelphia, 19107, Pennsylvania, United States',
			'address'      => array(
				'house_number' => '1400',
				'road'         => 'John F Kennedy Boulevard',
				'suburb'       => 'Center City',
				'city'         => 'Philadelphia',
				'county'       => 'Philadelphia County',
				'state'        => 'Pennsylvania',
				'postcode'     => '19107',
				'country'      => 'United States',
				'country_code' => 'US',
			),
		);
	}

	/**
	 * An answer from a server without the place: only what it reads from boundaries.
	 *
	 * @return array Answer.
	 */
	protected function country_only() {
		return array(
			'display_name' => 'United States',
			'address'      => array(
				'country'      => 'United States',
				'country_code' => 'US',
			),
		);
	}

	/**
	 * The provider is registered with its name and URL.
	 */
	public function test_registered() {
		$providers = Loc_Config::geo_providers();
		$this->assertArrayHasKey( 'traccar_geocoder', $providers );
		$this->assertSame( 'Traccar Geocoder', $providers['traccar_geocoder']['name'] );
		$this->assertNotEmpty( $providers['traccar_geocoder']['url'] );
	}

	/**
	 * An answer is parsed into the address, with the first server's key and no search distance, which servers up to 1.1.2 don't apply as documented.
	 */
	public function test_reverse_lookup() {
		$this->mock_http( array( self::TRAVEL . '/reverse' => array( 'body' => $this->city_hall() ) ) );
		$addr = $this->provider()->reverse_lookup();

		$request = $this->last_request();
		$this->assertCount( 1, $this->http_requests, 'The first server answered, so the second isn\'t asked.' );
		$this->assertSame( self::TRAVEL . '/reverse', $request['base'] );
		$this->assertEquals( self::LAT, $request['query']['lat'] );
		$this->assertEquals( self::LNG, $request['query']['lon'] );
		$this->assertSame( 'travel-key', $request['query']['key'] );
		$this->assertArrayNotHasKey( 'distance', $request['query'] );

		$this->assertSame( '1400', $addr['street-number'] );
		$this->assertSame( 'John F Kennedy Boulevard', $addr['street'] );
		$this->assertSame( '1400 John F Kennedy Boulevard', $addr['street-address'] );
		$this->assertSame( 'Center City', $addr['extended-address'] );
		$this->assertSame( 'Philadelphia', $addr['locality'] );
		$this->assertSame( 'Pennsylvania', $addr['region'] );
		$this->assertSame( 'PA', $addr['region-code'] );
		$this->assertSame( 'United States', $addr['country-name'] );
		$this->assertSame( '19107', $addr['postal-code'] );
		$this->assertSame( 'US', $addr['country-code'] );
		$this->assertEquals( self::LAT, $addr['latitude'] );
		$this->assertEquals( self::LNG, $addr['longitude'] );
		$this->assertSame( 'America/New_York', $addr['timezone'] );
		$this->assertNotEmpty( $addr['display-name'] );
		$this->assertSame( $this->city_hall(), $addr['raw'] );
	}

	/**
	 * The house number follows the street where that is the custom, the county stands in for a missing state, and
	 * the country name comes from the code when the server doesn't give it.
	 */
	public function test_address_formats() {
		$answer = array(
			'address' => array(
				'house_number' => '42',
				'road'         => 'Unter den Linden',
				'city'         => 'Berlin',
				'county'       => 'Berlin',
				'country_code' => 'de',
			),
		);
		$this->mock_http( array( '' => array( 'body' => $answer ) ) );
		$provider = $this->provider();
		$provider->set( 52.517, 13.389 );
		$addr = $provider->reverse_lookup();
		$this->assertSame( 'Unter den Linden 42', $addr['street-address'] );
		$this->assertSame( 'Berlin', $addr['region'] );
		$this->assertSame( 'DE', $addr['country-code'] );
		$this->assertSame( 'Germany', $addr['country-name'] );

		// A city without a street is enough.
		$this->mock_http(
			array(
				'' => array(
					'body' => array(
						'address' => array(
							'city'         => 'Philadelphia',
							'country_code' => 'US',
						),
					),
				),
			)
		);
		$addr = $this->provider()->reverse_lookup();
		$this->assertSame( 'Philadelphia', $addr['locality'] );
		$this->assertArrayNotHasKey( 'street-address', $addr );
	}

	/**
	 * An answer without a country, as from a server built from US state extracts, gets the country from the location,
	 * so the region code and country name are filled in.
	 */
	public function test_country_from_location() {
		$answer = array(
			'display_name' => 'John F Kennedy Boulevard 1400, 19107 Philadelphia',
			'address'      => array(
				'house_number' => '1400',
				'road'         => 'John F Kennedy Boulevard',
				'city'         => 'Philadelphia',
				'state'        => 'Pennsylvania',
				'county'       => 'Philadelphia County',
				'postcode'     => '19107',
			),
		);
		$this->mock_http( array( '' => array( 'body' => $answer ) ) );
		$addr = $this->provider()->reverse_lookup();
		$this->assertSame( 'US', $addr['country-code'] );
		$this->assertSame( 'United States', $addr['country-name'] );
		$this->assertSame( 'PA', $addr['region-code'] );
		$this->assertSame( '1400 John F Kennedy Boulevard', $addr['street-address'] );
	}

	/**
	 * A server that can't be reached, fails, or refuses its key is passed over for the next server.
	 */
	public function test_next_server_on_error() {
		$failures = array(
			'unreachable' => new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect' ),
			'server'      => array(
				'code' => 503,
				'body' => 'Service Unavailable',
			),
			'refused'     => array(
				'code' => 401,
				'body' => 'Invalid API key',
			),
			'limit'       => array(
				'code' => 429,
				'body' => 'Rate limit exceeded',
			),
			'not json'    => array( 'body' => '<html>Bad gateway</html>' ),
		);
		foreach ( $failures as $label => $failure ) {
			$this->mock_http(
				array(
					self::TRAVEL => $failure,
					self::HOME   => array( 'body' => $this->city_hall() ),
				)
			);
			$addr = $this->provider()->reverse_lookup();
			$this->assertIsArray( $addr, $label );
			$this->assertSame( 'Philadelphia', $addr['locality'], $label );
			$this->assertCount( 2, $this->http_requests, $label );
			$this->assertSame( 'home-key', $this->last_request()['query']['key'], 'Each server gets its own key: ' . $label );
		}
	}

	/**
	 * A server without the place, which answers with nothing or with only a country, is passed over.
	 */
	public function test_next_server_without_place() {
		foreach ( array( array( 'address' => array() ), $this->country_only() ) as $answer ) {
			$this->mock_http(
				array(
					self::TRAVEL => array( 'body' => $answer ),
					self::HOME   => array( 'body' => $this->city_hall() ),
				)
			);
			$addr = $this->provider()->reverse_lookup();
			$this->assertSame( 'Philadelphia', $addr['locality'] );
			$this->assertCount( 2, $this->http_requests );
		}
	}

	/**
	 * No server with the place is no_results, so the chain asks its next provider.
	 */
	public function test_no_results() {
		$this->mock_http(
			array(
				self::TRAVEL => array( 'body' => array( 'address' => array() ) ),
				self::HOME   => array( 'body' => $this->country_only() ),
			)
		);
		$result = $this->provider()->reverse_lookup();
		$this->assertWPError( $result );
		$this->assertSame( 'no_results', $result->get_error_code() );
		$this->assertSame( array(), Sloc_Provider_Errors::get_errors() );
	}

	/**
	 * The filter can accept an answer the default rule wouldn't, or refuse one it would.
	 */
	public function test_usable_filter() {
		$this->mock_http( array( '' => array( 'body' => $this->country_only() ) ) );
		$accept = function ( $usable, $address, $json, $url ) {
			return self::HOME === $url && isset( $address['country'] );
		};
		add_filter( 'sloc_traccar_geocoder_usable', $accept, 10, 4 );
		$addr = $this->provider()->reverse_lookup();
		remove_filter( 'sloc_traccar_geocoder_usable', $accept, 10 );
		$this->assertSame( 'US', $addr['country-code'] );
		$this->assertCount( 2, $this->http_requests, 'The travel server\'s answer was refused.' );

		$this->mock_http( array( '' => array( 'body' => $this->city_hall() ) ) );
		add_filter( 'sloc_traccar_geocoder_usable', '__return_false' );
		$result = $this->provider()->reverse_lookup();
		remove_filter( 'sloc_traccar_geocoder_usable', '__return_false' );
		$this->assertSame( 'no_results', $result->get_error_code() );
	}

	/**
	 * When every server fails, the error says the most useful thing, a refused key over an outage, and has the code
	 * that isn't recorded again for the provider as a whole.
	 */
	public function test_all_servers_fail() {
		$this->mock_http(
			array(
				self::TRAVEL => array(
					'code' => 401,
					'body' => 'Invalid API key',
				),
				self::HOME   => new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect' ),
			)
		);
		$provider = $this->provider();
		$result   = $provider->reverse_lookup();
		$this->assertSame( 'traccar_geocoder_failed', $result->get_error_code() );
		$this->assertStringContainsString( '401', $result->get_error_message() );
		$this->assertCount( 2, $result->get_error_data()['errors'] );
		$this->assertFalse( Sloc_Provider_Errors::is_actionable( $result, $provider ) );

		// Without an error an administrator can fix, the last error is the message.
		$this->mock_http( array( '' => new WP_Error( 'http_request_failed', 'cURL error 28: Timed out' ) ) );
		$this->assertSame( 'cURL error 28: Timed out', $this->provider()->reverse_lookup()->get_error_message() );
	}

	/**
	 * A server's error an administrator can fix is recorded for that server, even when another answers, and cleared
	 * when it answers again; an outage isn't recorded.
	 */
	public function test_server_errors_recorded() {
		$this->mock_http(
			array(
				self::TRAVEL => array(
					'code' => 401,
					'body' => 'Invalid API key',
				),
				self::HOME   => array( 'body' => $this->city_hall() ),
			)
		);
		$this->assertIsArray( $this->provider()->reverse_lookup() );
		$errors = Sloc_Provider_Errors::get_errors();
		$key    = 'geo:' . Geo_Provider_Traccar_Geocoder::server_slug( self::TRAVEL );
		$this->assertSame( array( $key ), array_keys( $errors ) );
		$this->assertSame( 'Traccar Geocoder (203.0.113.11:3011)', $errors[ $key ]['name'] );
		$this->assertSame( 'invalid_response', $errors[ $key ]['code'] );
		$this->assertStringNotContainsString( 'travel-key', wp_json_encode( $errors ), 'The key is never stored in the error.' );

		$this->mock_http( array( self::TRAVEL => array( 'body' => $this->city_hall() ) ) );
		$this->provider()->reverse_lookup();
		$this->assertSame( array(), Sloc_Provider_Errors::get_errors(), 'Cleared when the server answers.' );

		$this->mock_http(
			array(
				self::TRAVEL => new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect' ),
				self::HOME   => array( 'body' => $this->city_hall() ),
			)
		);
		$this->provider()->reverse_lookup();
		$this->assertSame( array(), Sloc_Provider_Errors::get_errors(), 'A server being down, such as while it rebuilds, isn\'t recorded.' );
	}

	/**
	 * Without servers the error is one an administrator can fix, and no request is made.
	 */
	public function test_no_servers() {
		$this->mock_http( array() );
		$provider = $this->provider( array( 'servers' => array() ) );
		$result   = $provider->reverse_lookup();
		$this->assertSame( 'missing_traccar_geocoder_url', $result->get_error_code() );
		$this->assertTrue( Sloc_Provider_Errors::is_actionable( $result, $provider ) );
		$this->assertSame( array(), $this->http_requests );
	}

	/**
	 * Looking up an address isn't supported, isn't shown to administrators, and makes no request.
	 */
	public function test_geocode_not_supported() {
		$this->mock_http( array() );
		$provider = $this->provider();
		$result   = $provider->geocode( '1400 John F Kennedy Blvd, Philadelphia' );
		$this->assertSame( 'not_supported', $result->get_error_code() );
		$this->assertFalse( Sloc_Provider_Errors::is_actionable( $result, $provider ) );
		$this->assertSame( array(), $this->http_requests );
	}

	/**
	 * In the chain, a server's refused key is recorded once, for the server, and the next provider answers when no
	 * server has the place; looking up an address goes straight to the next provider.
	 */
	public function test_chain() {
		Geo_Provider_Sloc_Test::reset();
		register_sloc_provider( new Geo_Provider_Traccar_Geocoder( array( 'servers' => $this->servers() ) ) );
		update_option( 'sloc_geo_provider', 'traccar_geocoder' );
		update_option( 'sloc_geo_fallbacks', array( 'sloc_test' ) );

		$this->mock_http(
			array(
				self::TRAVEL => array(
					'code' => 401,
					'body' => 'Invalid API key',
				),
				self::HOME   => array( 'body' => $this->country_only() ),
			)
		);
		$addr = Sloc_Provider_Chain::reverse_lookup( self::LAT, self::LNG );
		$this->assertIsArray( $addr );
		$this->assertNotSame( 'Philadelphia', $addr['locality'] ?? null, 'The test provider answered, not Traccar Geocoder.' );
		$this->assertSame( array( 'geo:' . Geo_Provider_Traccar_Geocoder::server_slug( self::TRAVEL ) ), array_keys( Sloc_Provider_Errors::get_errors() ) );

		$this->mock_http( array() );
		$this->assertIsArray( Sloc_Provider_Chain::geocode( 'Philadelphia' ) );
		$this->assertSame( array(), $this->http_requests );
	}

	/**
	 * Servers are kept in order with their keys; empty, removed and repeated rows and bad values are dropped.
	 */
	public function test_sanitize_servers() {
		$value = array(
			array(
				'url' => ' https://geo.example.com/ ',
				'key' => " abc\\'d ",
			),
			array(
				'url' => 'https://geo.example.com',
				'key' => 'repeat',
			),
			array(
				'url' => '',
				'key' => 'no-url',
			),
			array(
				'url'    => 'https://removed.example.com',
				'key'    => 'x',
				'remove' => '1',
			),
			array(
				'url' => 'ftp://files.example.com',
				'key' => 'x',
			),
			array( 'url' => array( 'bad' ) ),
			'not a row',
			array( 'url' => 'http://203.0.113.11:3011' ),
		);
		$this->assertSame(
			array(
				array(
					'url' => 'https://geo.example.com',
					'key' => "abc'd",
				),
				array(
					'url' => 'http://203.0.113.11:3011',
					'key' => '',
				),
			),
			Geo_Provider_Traccar_Geocoder::sanitize_servers( $value )
		);
		$this->assertSame( array(), Geo_Provider_Traccar_Geocoder::sanitize_servers( 'https://geo.example.com' ) );
		$this->assertSame( array(), Geo_Provider_Traccar_Geocoder::sanitize_servers( null ) );
	}

	/**
	 * Saving the servers forgets the recorded errors of servers no longer listed, without saying they work again.
	 */
	public function test_save_servers_forgets_removed() {
		update_option( Geo_Provider_Traccar_Geocoder::OPTION, $this->servers() );
		$errors = array();
		foreach ( array( self::TRAVEL, self::HOME ) as $url ) {
			$errors[ 'geo:' . Geo_Provider_Traccar_Geocoder::server_slug( $url ) ] = array(
				'type'     => 'geo',
				'provider' => Geo_Provider_Traccar_Geocoder::server_slug( $url ),
				'name'     => 'Traccar Geocoder',
				'code'     => 'invalid_response',
				'message'  => 'The service returned HTTP status 401.',
				'first'    => time(),
				'last'     => time(),
				'count'    => 1,
			);
		}
		update_option( Sloc_Provider_Errors::OPTION, $errors );
		$resolved = new MockAction();
		add_action( 'sloc_provider_error_resolved', array( $resolved, 'action' ) );

		$saved = Geo_Provider_Traccar_Geocoder::save_servers(
			array(
				array(
					'url' => self::HOME,
					'key' => 'home-key',
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'url' => self::HOME,
					'key' => 'home-key',
				),
			),
			$saved
		);
		$this->assertSame( array( 'geo:' . Geo_Provider_Traccar_Geocoder::server_slug( self::HOME ) ), array_keys( Sloc_Provider_Errors::get_errors() ) );
		$this->assertSame( 0, $resolved->get_call_count() );
		$this->assertFalse( Sloc_Provider_Errors::forget( 'geo', 'nothing' ) );
	}

	/**
	 * Server slugs are stable and differ by server.
	 */
	public function test_server_slug() {
		$this->assertSame( Geo_Provider_Traccar_Geocoder::server_slug( self::HOME ), Geo_Provider_Traccar_Geocoder::server_slug( self::HOME ) );
		$this->assertNotSame( Geo_Provider_Traccar_Geocoder::server_slug( self::HOME ), Geo_Provider_Traccar_Geocoder::server_slug( self::TRAVEL ) );
		$this->assertMatchesRegularExpression( '/^traccar_geocoder-[0-9a-f]{8}$/', Geo_Provider_Traccar_Geocoder::server_slug( self::HOME ) );
	}

	/**
	 * The settings are registered and shown in their own section of the API settings, with a row for each server, its
	 * key hidden, and an empty row for a new one.
	 */
	public function test_settings() {
		global $wp_registered_settings, $wp_settings_fields;
		Geo_Provider_Traccar_Geocoder::init();
		$this->assertArrayHasKey( Geo_Provider_Traccar_Geocoder::OPTION, $wp_registered_settings );
		$this->assertArrayNotHasKey( 'sloc_traccar_geocoder_distance', $wp_registered_settings );
		$this->assertFalse( $wp_registered_settings[ Geo_Provider_Traccar_Geocoder::OPTION ]['show_in_rest'], 'Keys stay out of REST.' );

		Geo_Provider_Traccar_Geocoder::admin_init();
		$this->assertArrayHasKey( Geo_Provider_Traccar_Geocoder::OPTION, $wp_settings_fields['sloc_api']['sloc_api'] );
		$this->assertSame( 'traccar', Sloc_Provider::api_group( Geo_Provider_Traccar_Geocoder::OPTION ) );
		$this->assertSame( 'Traccar Geocoder', Sloc_Provider::$api_names['traccar'] );

		update_option( Geo_Provider_Traccar_Geocoder::OPTION, $this->servers() );
		ob_start();
		Geo_Provider_Traccar_Geocoder::servers_callback( array() );
		$html = ob_get_clean();
		$this->assertSame( 3, substr_count( $html, '<li>' ), 'Two servers and an empty row.' );
		$this->assertStringContainsString( 'value="' . self::TRAVEL . '"', $html );
		$this->assertMatchesRegularExpression( '/type="password"[^>]*name="sloc_traccar_geocoder_servers\[0\]\[key\]" value="travel-key"/', $html );
		$this->assertSame( 2, substr_count( $html, '[remove]' ), 'Only saved servers can be removed.' );
		$this->assertStringContainsString( 'id="sloc_traccar_geocoder_servers"', $html, 'The label points at the first URL.' );
	}
}

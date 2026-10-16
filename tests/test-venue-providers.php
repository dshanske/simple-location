<?php
/**
 * Tests for the venue providers in includes/venue/, using recorded vendor responses
 * (tests/data/venue, plus the Nominatim reverse geocoding fixture in tests/data/geo).
 *
 * @package Simple_Location
 */

/**
 * Venue provider tests.
 */
class VenueProvidersTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

	/**
	 * Philadelphia City Hall.
	 */
	const LAT = 39.9524;
	const LNG = -75.1636;

	/**
	 * Creates a provider at City Hall.
	 *
	 * @param string $class Provider class.
	 * @param array  $args  Constructor arguments.
	 * @return Venue_Provider
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
	 * Asserts the address parts every provider should agree on (see tests/data/README.md).
	 *
	 * @param array $card Parsed venue.
	 */
	protected function assertPhiladelphia( $card ) {
		$expected = array(
			'locality'     => 'Philadelphia',
			'region'       => 'Pennsylvania',
			'region-code'  => 'PA',
			'country-code' => 'US',
		);
		foreach ( $expected as $key => $value ) {
			$this->assertArrayHasKey( $key, $card, $key );
			$this->assertSame( $value, $card[ $key ], $key );
		}
		$this->assertStringStartsWith( '191', $card['postal-code'], 'A Philadelphia ZIP code.' );
	}

	/**
	 * Asserts the timezone details added to each venue.
	 *
	 * @param array $card Parsed venue.
	 */
	protected function assertEasternTime( $card ) {
		$this->assertSame( 'America/New_York', $card['timezone'] );
		$this->assertArrayHasKey( 'offset', $card );
		$this->assertArrayHasKey( 'seconds', $card );
	}

	/**
	 * Every bundled venue provider is registered with its slug and name.
	 */
	public function test_providers_registered() {
		$providers = Loc_Config::venue_providers();
		foreach ( array( 'google', 'locationiq', 'nominatim' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $providers );
			$this->assertNotEmpty( $providers[ $slug ]['name'], $slug );
			$this->assertNotEmpty( $providers[ $slug ]['url'], $slug );
		}
	}

	/**
	 * A provider is active when it is the chosen venue provider.
	 */
	public function test_is_active() {
		update_option( 'sloc_venue_provider', 'nominatim' );
		$this->assertTrue( $this->provider( 'Venue_Provider_Nominatim' )->is_active() );
		$this->assertFalse( $this->provider( 'Venue_Provider_Google' )->is_active() );
	}

	/**
	 * Nominatim returns the place at the point as a single venue.
	 */
	public function test_nominatim() {
		$fixture = $this->fixture( 'geo/nominatim-reverse.json' );
		$this->mock_http( array( 'nominatim.openstreetmap.org/reverse' => array( 'body' => $fixture ) ) );
		$result = $this->provider( 'Venue_Provider_Nominatim' )->reverse_lookup();

		$request = $this->last_request();
		$this->assertSame( 'https://nominatim.openstreetmap.org/reverse', $request['base'] );
		$this->assertSame( 'jsonv2', $request['query']['format'] );
		$this->assertSame( '1', $request['query']['extratags'] );
		$this->assertSame( '1', $request['query']['namedetails'] );
		$this->assertEquals( self::LAT, $request['query']['lat'] );
		$this->assertEquals( self::LNG, $request['query']['lon'] );
		$this->assertSame( get_bloginfo( 'language' ), $request['query']['accept-language'] );

		$this->assertCount( 1, $result['items'] );
		$card = $result['items'][0];
		$this->assertSame( 'card', $card['type'] );
		$this->assertPhiladelphia( $card );
		$this->assertEasternTime( $card );
		$this->assertContains( $card['name'], $fixture['address'], 'The name is one of the address parts.' );
		$this->assertSame( $fixture['address']['road'], $card['street'] );
		$this->assertSame( $fixture['address']['house_number'] . ' ' . $fixture['address']['road'], $card['street-address'] );
		$this->assertSame( $fixture['address']['neighbourhood'], $card['extended-address'] );
		$this->assertSame( $fixture['type'], $card['category'] );
		$this->assertSame( $fixture['extratags']['website'], $card['url'] );
		$this->assertSame( $fixture['extratags']['image'], $card['photo'] );
		$this->assertSame( $fixture['boundingbox'], $card['boundingbox'] );
		$this->assertSame( $fixture, $card['raw'] );
	}

	/**
	 * A Nominatim list response becomes one venue per item, and items without an address are empty.
	 */
	public function test_nominatim_list() {
		$fixture = $this->fixture( 'geo/nominatim-reverse.json' );
		$this->mock_http( array( 'nominatim.openstreetmap.org' => array( 'body' => array( $fixture, array( 'place_id' => 1 ) ) ) ) );
		$result = $this->provider( 'Venue_Provider_Nominatim' )->reverse_lookup();
		$this->assertCount( 2, $result['items'] );
		$this->assertPhiladelphia( $result['items'][0] );
		$this->assertSame( array(), $result['items'][1] );
	}

	/**
	 * A Wikipedia tag becomes the venue URL when there is no website.
	 */
	public function test_nominatim_wikipedia_url() {
		$fixture              = $this->fixture( 'geo/nominatim-reverse.json' );
		$fixture['extratags'] = array( 'wikipedia' => 'en:Philadelphia City Hall' );
		$this->mock_http( array( 'nominatim.openstreetmap.org' => array( 'body' => $fixture ) ) );
		$card = $this->provider( 'Venue_Provider_Nominatim' )->reverse_lookup()['items'][0];
		$this->assertSame( 'https://en.wikipedia.org/wiki/Philadelphia_City_Hall', $card['url'] );
	}

	/**
	 * LocationIQ's Nearby API returns a list of venues with their addresses.
	 */
	public function test_locationiq() {
		$fixture = $this->fixture( 'venue/locationiq-nearby.json' );
		$this->mock_http( array( 'us1.locationiq.com/v1/nearby' => array( 'body' => $fixture ) ) );
		$result = $this->provider( 'Venue_Provider_LocationIQ', array( 'api' => 'KEY' ) )->reverse_lookup();

		$request = $this->last_request();
		$this->assertSame( 'KEY', $request['query']['key'] );
		$this->assertEquals( self::LAT, $request['query']['lat'] );
		$this->assertEquals( self::LNG, $request['query']['lon'] );

		$this->assertCount( count( $fixture ), $result['items'] );
		foreach ( $fixture as $i => $item ) {
			$card = $result['items'][ $i ];
			$this->assertPhiladelphia( $card );
			$this->assertEasternTime( $card );
			$this->assertSame( $item['address']['postcode'], $card['postal-code'] );
			$this->assertSame( $item['address']['road'], $card['street'] );
			$this->assertSame( $item['type'], $card['category'] );
			$this->assertSame( $item['boundingbox'], $card['boundingbox'] );
			$this->assertSame( (float) $item['lat'], $card['latitude'] );
			$this->assertSame( (float) $item['lon'], $card['longitude'] );
			$this->assertSame( $item['name'], $card['name'] );
		}
	}

	/**
	 * LocationIQ falls back to the address name, then the address type keys, for venues without a name.
	 */
	public function test_locationiq_name_fallback() {
		$fixture = $this->fixture( 'venue/locationiq-nearby.json' );
		unset( $fixture[0]['name'], $fixture[1]['name'], $fixture[1]['address']['name'] );
		$fixture[1]['address']['leisure'] = 'Dilworth Park';
		$this->mock_http( array( 'us1.locationiq.com/v1/nearby' => array( 'body' => $fixture ) ) );
		$items = $this->provider( 'Venue_Provider_LocationIQ', array( 'api' => 'KEY' ) )->reverse_lookup()['items'];
		$this->assertSame( 'Philadelphia City Hall', $items[0]['name'] );
		$this->assertSame( 'Dilworth Park', $items[1]['name'] );
	}

	/**
	 * LocationIQ needs a key, and error objects or malformed items don't cause errors.
	 */
	public function test_locationiq_errors() {
		$this->mock_http( array() );
		$this->assertSame( 'missing_api_key', $this->provider( 'Venue_Provider_LocationIQ', array( 'api' => '' ) )->reverse_lookup()->get_error_code() );
		$this->assertEmpty( $this->http_requests );

		$this->mock_http( array( 'us1.locationiq.com/v1/nearby' => array( 'body' => array( 'error' => 'No results' ) ) ) );
		$result = $this->provider( 'Venue_Provider_LocationIQ', array( 'api' => 'KEY' ) )->reverse_lookup();
		$this->assertWPError( $result );
		$this->assertSame( 'No results', $result->get_error_message() );

		$fixture   = $this->fixture( 'venue/locationiq-nearby.json' );
		$fixture[] = 'not a venue';
		$fixture[] = array( 'place_id' => '1' );
		$fixture[] = array( 'address' => 'Philadelphia' );
		$this->mock_http( array( 'us1.locationiq.com/v1/nearby' => array( 'body' => $fixture ) ) );
		$this->assertCount( 2, $this->provider( 'Venue_Provider_LocationIQ', array( 'api' => 'KEY' ) )->reverse_lookup()['items'] );
	}

	/**
	 * Google's Nearby Search returns establishments with their own coordinates and plus codes.
	 */
	public function test_google() {
		$fixture = $this->fixture( 'venue/google-nearbysearch.json' );
		$this->mock_http( array( 'maps.googleapis.com/maps/api/place/nearbysearch/json' => array( 'body' => $fixture ) ) );
		$items = $this->provider( 'Venue_Provider_Google', array( 'api' => 'KEY' ) )->reverse_lookup();

		$request = $this->last_request();
		$this->assertSame( '39.9524,-75.1636', $request['query']['location'] );
		$this->assertSame( '200', $request['query']['radius'] );
		$this->assertSame( 'establishment', $request['query']['type'] );
		$this->assertSame( 'KEY', $request['query']['key'] );

		$this->assertCount( count( $fixture['results'] ), $items );
		foreach ( $fixture['results'] as $i => $result ) {
			$card = $items[ $i ];
			$this->assertSame( 'card', $card['type'] );
			$this->assertSame( $result['name'], $card['name'] );
			$this->assertSame( $result['plus_code']['global_code'], $card['plus-code'] );
			$this->assertSame( $result['geometry']['location']['lat'], $card['latitude'] );
			$this->assertSame( $result['geometry']['location']['lng'], $card['longitude'] );
			$this->assertSame( $result['geometry']['viewport'], $card['boundingbox'] );
			$this->assertSame( $result['types'], $card['category'] );
			$this->assertEasternTime( $card );
		}
	}

	/**
	 * Google needs a key, and a status other than OK becomes an error with that status as the code.
	 */
	public function test_google_errors() {
		$this->mock_http( array() );
		$this->assertSame( 'missing_api_key', $this->provider( 'Venue_Provider_Google', array( 'api' => '' ) )->reverse_lookup()->get_error_code() );
		$this->assertEmpty( $this->http_requests );

		$this->mock_http( array( 'maps.googleapis.com' => array( 'body' => $this->fixture( 'geo/google-denied.json' ) ) ) );
		$result = $this->provider( 'Venue_Provider_Google', array( 'api' => 'KEY' ) )->reverse_lookup();
		$this->assertWPError( $result );
		$this->assertSame( 'REQUEST_DENIED', $result->get_error_code() );
	}

	/**
	 * HTTP failures are returned as errors.
	 */
	public function test_http_errors() {
		$this->mock_http( array( 'nominatim' => new WP_Error( 'http_request_failed', 'Timed out' ) ) );
		$this->assertSame( 'http_request_failed', $this->provider( 'Venue_Provider_Nominatim' )->reverse_lookup()->get_error_code() );

		$this->mock_http(
			array(
				'locationiq' => array(
					'code' => 401,
					'body' => array( 'error' => 'Invalid key' ),
				),
			)
		);
		$this->assertSame( 'invalid_response', $this->provider( 'Venue_Provider_LocationIQ', array( 'api' => 'KEY' ) )->reverse_lookup()->get_error_code() );
	}
}

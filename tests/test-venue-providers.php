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
		foreach ( array( 'google', 'locationiq', 'nominatim', 'overpass', 'geoapify', 'here', 'tomtom' ) as $slug ) {
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
	 * A Wikipedia tag without a language prefix is an English title, and titles may contain colons.
	 */
	public function test_wikipedia_url() {
		$this->assertSame( 'https://en.wikipedia.org/wiki/Philadelphia_City_Hall', Venue_Provider::wikipedia_url( 'en:Philadelphia City Hall' ) );
		$this->assertSame( 'https://en.wikipedia.org/wiki/Philadelphia_City_Hall', Venue_Provider::wikipedia_url( 'Philadelphia City Hall' ) );
		$this->assertSame( 'https://de.wikipedia.org/wiki/K%C3%B6lner_Dom', Venue_Provider::wikipedia_url( 'de:Kölner Dom' ) );
		$this->assertSame( 'https://zh-yue.wikipedia.org/wiki/%E9%A6%99%E6%B8%AF', Venue_Provider::wikipedia_url( 'zh-yue:香港' ) );
		$this->assertSame( 'https://en.wikipedia.org/wiki/Star_Trek%3A_Voyager', Venue_Provider::wikipedia_url( 'en:Star Trek: Voyager' ) );
		$this->assertSame( 'https://en.wikipedia.org/wiki/Star_Trek%3A_Voyager', Venue_Provider::wikipedia_url( 'Star Trek: Voyager' ) );
		$this->assertSame( 'https://en.wikipedia.org/wiki/AT%26T_Stadium', Venue_Provider::wikipedia_url( 'AT&T Stadium' ) );
		$this->assertSame( '', Venue_Provider::wikipedia_url( '' ) );
		$this->assertSame( '', Venue_Provider::wikipedia_url( array() ) );
	}

	/**
	 * Venue parsers build a URL from a Wikipedia tag without a language prefix instead of warning.
	 */
	public function test_wikipedia_tag_without_language() {
		$fixture              = $this->fixture( 'geo/nominatim-reverse.json' );
		$fixture['extratags'] = array( 'wikipedia' => 'Philadelphia City Hall' );
		$this->mock_http( array( 'nominatim.openstreetmap.org' => array( 'body' => $fixture ) ) );
		$card = $this->provider( 'Venue_Provider_Nominatim' )->reverse_lookup()['items'][0];
		$this->assertSame( 'https://en.wikipedia.org/wiki/Philadelphia_City_Hall', $card['url'] );

		$fixture = $this->fixture( 'venue/locationiq-nearby.json' );
		foreach ( array_keys( $fixture ) as $i ) {
			$fixture[ $i ]['extratags'] = array( 'wikipedia' => 'Philadelphia City Hall' );
		}
		$this->mock_http( array( 'us1.locationiq.com/v1/nearby' => array( 'body' => $fixture ) ) );
		$card = $this->provider( 'Venue_Provider_LocationIQ', array( 'api' => 'KEY' ) )->reverse_lookup()['items'][0];
		$this->assertSame( 'https://en.wikipedia.org/wiki/Philadelphia_City_Hall', $card['url'] );
	}

	/**
	 * A Nominatim error body is an empty list when there is nothing at the point, and an error otherwise.
	 */
	public function test_nominatim_error_body() {
		$this->mock_http( array( 'nominatim.openstreetmap.org' => array( 'body' => array( 'error' => 'Unable to geocode' ) ) ) );
		$this->assertSame( array( 'items' => array() ), $this->provider( 'Venue_Provider_Nominatim' )->reverse_lookup() );

		$this->mock_http(
			array(
				'nominatim.openstreetmap.org' => array(
					'body' => array(
						'error' => array(
							'code'    => 400,
							'message' => 'Parameter lat out of range',
						),
					),
				),
			)
		);
		$result = $this->provider( 'Venue_Provider_Nominatim' )->reverse_lookup();
		$this->assertWPError( $result );
		$this->assertSame( 'Parameter lat out of range', $result->get_error_message() );
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
		$this->assertSame( (string) Venue_Provider::DEFAULT_RADIUS, $request['query']['radius'] );
		$this->assertSame( (string) Venue_Provider::DEFAULT_LIMIT, $request['query']['limit'] );

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
	 * The radius and limit come from the settings, or the constructor, within bounds (#706).
	 */
	public function test_radius_and_limit() {
		$provider = $this->provider( 'Venue_Provider_LocationIQ', array( 'api' => 'KEY' ) );
		$this->assertSame( Venue_Provider::DEFAULT_RADIUS, $provider->get_radius() );
		$this->assertSame( Venue_Provider::DEFAULT_LIMIT, $provider->get_limit() );

		update_option( 'sloc_venue_radius', 500 );
		update_option( 'sloc_venue_limit', 3 );
		$provider = $this->provider( 'Venue_Provider_LocationIQ', array( 'api' => 'KEY' ) );
		$this->assertSame( 500, $provider->get_radius() );
		$this->assertSame( 3, $provider->get_limit() );
		$this->assertSame( 100, $this->provider( 'Venue_Provider_LocationIQ', array( 'radius' => 100 ) )->get_radius() );

		$this->assertSame( Venue_Provider::MIN_RADIUS, Venue_Provider::sanitize_radius( 1 ) );
		$this->assertSame( Venue_Provider::MAX_RADIUS, Venue_Provider::sanitize_radius( 999999 ) );
		$this->assertSame( Venue_Provider::DEFAULT_RADIUS, Venue_Provider::sanitize_radius( 'far' ) );
		$this->assertSame( 1, Venue_Provider::sanitize_limit( -4 ) );
		$this->assertSame( Venue_Provider::MAX_LIMIT, Venue_Provider::sanitize_limit( 500 ) );
		$this->assertSame( Venue_Provider::DEFAULT_LIMIT, Venue_Provider::sanitize_limit( null ) );
		$this->assertSame( 300, sanitize_option( 'sloc_venue_radius', '300' ), 'Saved through the settings.' );
		delete_option( 'sloc_venue_radius' );
		delete_option( 'sloc_venue_limit' );
	}

	/**
	 * Venues are sorted nearest first with their distance, those beyond the radius are dropped, and the list is
	 * limited; venues without coordinates come last (#706).
	 */
	public function test_sort_items() {
		$items  = array(
			array(
				'name'      => 'Far',
				'latitude'  => self::LAT + 0.01,
				'longitude' => self::LNG,
			),
			array( 'name' => 'Nowhere' ),
			array(
				'name'      => 'Middle',
				'latitude'  => self::LAT + 0.001,
				'longitude' => self::LNG,
			),
			array(
				'name'      => 'Here',
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			),
			'not a venue',
			array(),
		);
		$sorted = Venue_Provider::sort_items( $items, self::LAT, self::LNG, 250 );
		$this->assertSame( array( 'Here', 'Middle', 'Nowhere' ), wp_list_pluck( $sorted, 'name' ) );
		$this->assertSame( 0, $sorted[0]['distance'] );
		$this->assertEqualsWithDelta( 111, $sorted[1]['distance'], 1 );
		$this->assertArrayNotHasKey( 'distance', $sorted[2] );

		$this->assertSame( array( 'Here', 'Middle', 'Far', 'Nowhere' ), wp_list_pluck( Venue_Provider::sort_items( $items, self::LAT, self::LNG ), 'name' ), 'Any distance.' );
		$this->assertSame( array( 'Here' ), wp_list_pluck( Venue_Provider::sort_items( $items, self::LAT, self::LNG, 0, 1 ), 'name' ), 'Limited.' );
	}

	/**
	 * nearby() returns the provider's venues sorted, limited and with distances; errors are passed on (#706).
	 */
	public function test_nearby() {
		$fixture = $this->fixture( 'venue/locationiq-nearby.json' );
		$this->mock_http( array( 'us1.locationiq.com/v1/nearby' => array( 'body' => array_reverse( $fixture ) ) ) );
		$result    = $this->provider(
			'Venue_Provider_LocationIQ',
			array(
				'api'    => 'KEY',
				'radius' => Venue_Provider::MAX_RADIUS,
			)
		)->nearby();
		$distances = wp_list_pluck( $result['items'], 'distance' );
		$this->assertCount( count( $fixture ), $distances );
		$sorted = $distances;
		sort( $sorted );
		$this->assertSame( $sorted, $distances, 'Nearest first.' );

		$this->mock_http( array( 'us1.locationiq.com/v1/nearby' => array( 'body' => $fixture ) ) );
		$this->assertCount(
			1,
			$this->provider(
				'Venue_Provider_LocationIQ',
				array(
					'api'   => 'KEY',
					'limit' => 1,
				)
			)->nearby()['items']
		);

		$this->mock_http( array( 'us1.locationiq.com/v1/nearby' => new WP_Error( 'http_request_failed', 'Down' ) ) );
		$this->assertWPError( $this->provider( 'Venue_Provider_LocationIQ', array( 'api' => 'KEY' ) )->nearby() );
	}

	/**
	 * OpenStreetMap tags are mapped to the shared categories, with an icon for transport and lodging (#707).
	 */
	public function test_osm_category() {
		$cases = array(
			array( 'amenity', 'cafe', 'food', '' ),
			array( 'amenity', 'pub', 'drink', '' ),
			array( 'tourism', 'hotel', 'lodging', 'fa-suitcase' ),
			array( 'tourism', 'attraction', 'culture', '' ),
			array( 'leisure', 'park', 'outdoors', '' ),
			array( 'leisure', 'stadium', 'sport', '' ),
			array( 'shop', 'books', 'shop', '' ),
			array( 'office', 'company', 'office', '' ),
			array( 'historic', 'castle', 'culture', '' ),
			array( 'aeroway', 'aerodrome', 'transport', 'fa-plane' ),
			array( 'railway', 'station', 'transport', 'fa-train' ),
			array( 'amenity', 'townhall', 'government', '' ),
			array( 'AMENITY', 'Place_Of_Worship', 'worship', '' ),
			array( 'building', 'yes', 'other', '' ),
			array( '', '', 'other', '' ),
		);
		foreach ( $cases as $case ) {
			$this->assertSame(
				array(
					'category' => $case[2],
					'icon'     => $case[3],
				),
				Venue_Provider::osm_category( $case[0], $case[1] ),
				$case[0] . '=' . $case[1]
			);
		}
		$this->assertContains( 'other', array_keys( Venue_Provider::categories() ) );
	}

	/**
	 * Google types are mapped from the most specific, and any kind of store is a shop (#707).
	 */
	public function test_google_category() {
		$this->assertSame( 'government', Venue_Provider_Google::google_category( array( 'city_hall', 'tourist_attraction', 'point_of_interest' ) )['category'] );
		$this->assertSame( 'fa-train', Venue_Provider_Google::google_category( array( 'train_station', 'transit_station' ) )['icon'] );
		$this->assertSame( 'shop', Venue_Provider_Google::google_category( array( 'book_store', 'store' ) )['category'] );
		$this->assertSame( 'other', Venue_Provider_Google::google_category( array( 'point_of_interest', 'establishment' ) )['category'] );
		$this->assertSame( 'other', Venue_Provider_Google::google_category( array() )['category'] );
	}

	/**
	 * Every provider's venues have their shared category, and their own categories as before; a filter can change
	 * them (#707).
	 */
	public function test_venue_categories() {
		$this->mock_http( array( 'us1.locationiq.com/v1/nearby' => array( 'body' => $this->fixture( 'venue/locationiq-nearby.json' ) ) ) );
		$items = $this->provider( 'Venue_Provider_LocationIQ', array( 'api' => 'KEY' ) )->reverse_lookup()['items'];
		$this->assertSame( array( 'culture', 'outdoors' ), wp_list_pluck( $items, 'venue-category' ) );
		$this->assertSame( array( 'attraction', 'park' ), wp_list_pluck( $items, 'category' ) );

		$this->mock_http( array( 'places.googleapis.com' => array( 'body' => $this->fixture( 'venue/google-places-nearby.json' ) ) ) );
		$items = $this->provider( 'Venue_Provider_Google', array( 'api' => 'KEY' ) )->reverse_lookup()['items'];
		$this->assertSame( array( 'government', 'outdoors' ), wp_list_pluck( $items, 'venue-category' ) );

		$this->mock_http( array( 'nominatim.openstreetmap.org/reverse' => array( 'body' => $this->fixture( 'geo/nominatim-reverse.json' ) ) ) );
		$this->assertSame( 'culture', $this->provider( 'Venue_Provider_Nominatim' )->reverse_lookup()['items'][0]['venue-category'] );

		$filter = fn( $match ) => array(
			'category' => 'food',
			'icon'     => 'fa-home',
		);
		add_filter( 'sloc_venue_category', $filter );
		$this->mock_http( array( 'nominatim.openstreetmap.org/reverse' => array( 'body' => $this->fixture( 'geo/nominatim-reverse.json' ) ) ) );
		$card = $this->provider( 'Venue_Provider_Nominatim' )->reverse_lookup()['items'][0];
		remove_filter( 'sloc_venue_category', $filter );
		$this->assertSame( 'food', $card['venue-category'] );
		$this->assertSame( 'fa-home', $card['icon'] );
	}

	/**
	 * Nominatim's venue has the place's own coordinates, not the search point's (#720).
	 */
	public function test_nominatim_venue_coordinates() {
		$fixture        = $this->fixture( 'geo/nominatim-reverse.json' );
		$fixture['lat'] = '39.9530';
		$fixture['lon'] = '-75.1640';
		$this->mock_http( array( 'nominatim.openstreetmap.org/reverse' => array( 'body' => $fixture ) ) );
		$provider = $this->provider( 'Venue_Provider_Nominatim' );
		$provider->set( self::LAT, self::LNG, 12 );
		$card = $provider->reverse_lookup()['items'][0];
		$this->assertSame( 39.953, $card['latitude'] );
		$this->assertSame( -75.164, $card['longitude'] );
		$this->assertArrayNotHasKey( 'altitude', $card );
		$this->assertGreaterThan( 0, $provider->nearby()['items'][0]['distance'] ?? 0, 'Not 0 m away.' );
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
	 * Google's Places API (New) Nearby Search returns places nearest first, with their addresses (#703).
	 */
	public function test_google() {
		$fixture = $this->fixture( 'venue/google-places-nearby.json' );
		$this->mock_http( array( 'places.googleapis.com/v1/places:searchNearby' => array( 'body' => $fixture ) ) );
		$result = $this->provider( 'Venue_Provider_Google', array( 'api' => 'KEY' ) )->reverse_lookup();
		$this->assertSame( array( 'items' ), array_keys( $result ), 'The same shape as the other venue providers.' );
		$items = $result['items'];

		$request = $this->last_request();
		$this->assertSame( 'POST', $request['args']['method'] );
		$this->assertSame( 'KEY', $request['args']['headers']['X-Goog-Api-Key'] );
		$this->assertStringNotContainsString( 'KEY', $request['url'], 'The key is in a header, not the URL.' );
		$this->assertStringContainsString( 'places.displayName', $request['args']['headers']['X-Goog-FieldMask'] );
		$this->assertStringNotContainsString( 'websiteUri', $request['args']['headers']['X-Goog-FieldMask'], 'Only the Pro fields.' );
		$body = json_decode( $request['args']['body'], true );
		$this->assertEquals( self::LAT, $body['locationRestriction']['circle']['center']['latitude'] );
		$this->assertEquals( self::LNG, $body['locationRestriction']['circle']['center']['longitude'] );
		$this->assertEquals( Venue_Provider::DEFAULT_RADIUS, $body['locationRestriction']['circle']['radius'], 'The venue search radius (#706).' );
		$this->assertSame( Venue_Provider::DEFAULT_LIMIT, $body['maxResultCount'] );
		$this->assertSame( 'DISTANCE', $body['rankPreference'] );

		$this->assertCount( count( $fixture['places'] ), $items );
		foreach ( $fixture['places'] as $i => $place ) {
			$card = $items[ $i ];
			$this->assertSame( 'card', $card['type'] );
			$this->assertSame( $place['displayName']['text'], $card['name'] );
			$this->assertSame( $place['formattedAddress'], $card['label'] );
			$this->assertSame( $place['plusCode']['globalCode'], $card['plus-code'] );
			$this->assertSame( $place['location']['latitude'], $card['latitude'] );
			$this->assertSame( $place['location']['longitude'], $card['longitude'] );
			$this->assertSame( $place['viewport'], $card['boundingbox'] );
			$this->assertSame( $place['types'], $card['category'] );
			$this->assertPhiladelphia( $card );
			$this->assertEasternTime( $card );
		}
		$this->assertSame( '1400 John F Kennedy Boulevard', $items[0]['street-address'] );
		$this->assertSame( 'Center City', $items[0]['extended-address'] );
		$this->assertSame( 'United States', $items[0]['country-name'] );
		$this->assertSame( array( 'government', 'outdoors' ), wp_list_pluck( $items, 'venue-category' ) );

		// The limit is at most Google's 20.
		$this->provider(
			'Venue_Provider_Google',
			array(
				'api'   => 'KEY',
				'limit' => 50,
			)
		)->reverse_lookup();
		$this->assertSame( 20, json_decode( $this->last_request()['args']['body'], true )['maxResultCount'] );

		// More fields can be asked for.
		$fields = fn( $list ) => array_merge( $list, array( 'websiteUri' ) );
		add_filter( 'sloc_google_venue_fields', $fields );
		$this->provider( 'Venue_Provider_Google', array( 'api' => 'KEY' ) )->reverse_lookup();
		remove_filter( 'sloc_google_venue_fields', $fields );
		$this->assertStringContainsString( 'places.websiteUri', $this->last_request()['args']['headers']['X-Goog-FieldMask'] );
	}

	/**
	 * Google needs a key, and a refused request is an error with Google's status and message, without the key.
	 */
	public function test_google_errors() {
		$this->mock_http( array() );
		$this->assertSame( 'missing_api_key', $this->provider( 'Venue_Provider_Google', array( 'api' => '' ) )->reverse_lookup()->get_error_code() );
		$this->assertEmpty( $this->http_requests );

		$this->mock_http(
			array(
				'places.googleapis.com' => array(
					'code' => 403,
					'body' => array(
						'error' => array(
							'code'    => 403,
							'message' => 'Places API (New) has not been used in project 123 before or it is disabled.',
							'status'  => 'PERMISSION_DENIED',
						),
					),
				),
			)
		);
		$provider = $this->provider( 'Venue_Provider_Google', array( 'api' => 'KEY' ) );
		$result   = $provider->reverse_lookup();
		$this->assertWPError( $result );
		$this->assertSame( 'invalid_response', $result->get_error_code() );
		$this->assertStringContainsString( 'Places API (New)', $result->get_error_message() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
		$this->assertSame( 'PERMISSION_DENIED', $result->get_error_data()['reason'] );
		$this->assertTrue( Sloc_Provider_Errors::is_actionable( $result, $provider ), 'Shown to administrators.' );
	}

	/**
	 * No places nearby is an empty list, and places without a name are left out.
	 */
	public function test_google_empty_results() {
		$this->mock_http( array( 'places.googleapis.com' => array( 'body' => '{}' ) ) );
		$this->assertSame( array( 'items' => array() ), $this->provider( 'Venue_Provider_Google', array( 'api' => 'KEY' ) )->reverse_lookup() );

		$this->mock_http(
			array(
				'places.googleapis.com' => array(
					'body' => array(
						'places' => array(
							array( 'displayName' => array( 'text' => 'X' ) ),
							array( 'id' => 'no-name' ),
							'not a place',
						),
					),
				),
			)
		);
		$result = $this->provider( 'Venue_Provider_Google', array( 'api' => 'KEY' ) )->reverse_lookup();
		$this->assertCount( 1, $result['items'] );
		$this->assertSame( 'X', $result['items'][0]['name'] );
		$this->assertArrayNotHasKey( 'category', $result['items'][0] );
		$this->assertSame( 'other', $result['items'][0]['venue-category'] );
	}

	/**
	 * Overpass finds the named OpenStreetMap places around the point, with their addresses and categories (#709).
	 */
	public function test_overpass() {
		$fixture = $this->fixture( 'venue/overpass-nearby.json' );
		$this->mock_http( array( 'overpass-api.de/api/interpreter' => array( 'body' => $fixture ) ) );
		$provider = $this->provider( 'Venue_Provider_Overpass' );
		$result   = $provider->reverse_lookup();

		$request = $this->last_request();
		$this->assertSame( 'https://overpass-api.de/api/interpreter', $request['base'] );
		$this->assertSame( $provider->query(), $request['query']['data'] );
		$this->assertStringContainsString( 'around:250,39.9524,-75.1636', $request['query']['data'] );
		$this->assertStringContainsString( '[name]', $request['query']['data'] );
		$this->assertStringContainsString( 'out center tags', $request['query']['data'] );
		$this->assertSame( 'Simple Location for WordPress (' . home_url( '/' ) . ')', $request['args']['headers']['User-Agent'] );

		$items = $result['items'];
		$this->assertSame( array( 'Philadelphia City Hall', 'Rival Bros Coffee', 'Dilworth Park' ), wp_list_pluck( $items, 'name' ), 'Places without a name are left out.' );
		$this->assertPhiladelphia(
			array_merge(
				$items[0],
				array( 'region' => 'Pennsylvania' )
			)
		);
		$this->assertSame( '1400 John F. Kennedy Boulevard', $items[0]['street-address'] );
		$this->assertSame( 'https://www.phila.gov/', $items[0]['url'] );
		$this->assertSame( 'townhall', $items[0]['category'] );
		$this->assertSame( 'government', $items[0]['venue-category'] );
		$this->assertSame( 39.9527, $items[0]['latitude'], 'A way is at its center.' );
		$this->assertSame( 'food', $items[1]['venue-category'] );
		$this->assertSame( '+1 215 555 0100', $items[1]['tel'] );
		$this->assertSame( 'https://en.wikipedia.org/wiki/Dilworth_Park', $items[2]['url'] );
		$this->assertSame( 'outdoors', $items[2]['venue-category'] );
		$this->assertEasternTime( $items[2] );

		$this->mock_http( array( 'overpass-api.de' => array( 'body' => $fixture ) ) );
		$this->assertSame( array( 'Philadelphia City Hall', 'Rival Bros Coffee', 'Dilworth Park' ), wp_list_pluck( $provider->nearby()['items'], 'name' ), 'Nearest first.' );
	}

	/**
	 * Overpass uses the server in its setting, and passes on a busy server's error (#709).
	 */
	public function test_overpass_server_and_errors() {
		update_option( 'sloc_overpass_url', 'https://overpass.example.com/api/interpreter' );
		$this->mock_http( array( 'overpass.example.com' => array( 'body' => array( 'elements' => array() ) ) ) );
		$this->assertSame( array( 'items' => array() ), $this->provider( 'Venue_Provider_Overpass' )->reverse_lookup() );
		$this->assertSame( 'https://overpass.example.com/api/interpreter', $this->last_request()['base'] );
		delete_option( 'sloc_overpass_url' );
		$this->assertSame( '', Venue_Provider_Overpass::sanitize_url( 'javascript:alert(1)' ) );

		$this->mock_http(
			array(
				'overpass-api.de' => array(
					'code' => 429,
					'body' => 'Too Many Requests',
				),
			)
		);
		$provider = $this->provider( 'Venue_Provider_Overpass' );
		$error    = $provider->reverse_lookup();
		$this->assertSame( 429, $error->get_error_data()['status'] );
		$this->assertTrue( Sloc_Provider_Errors::is_actionable( $error, $provider ) );

		$this->mock_http( array() );
		$this->assertSame( array( 'items' => array() ), ( new Venue_Provider_Overpass() )->reverse_lookup(), 'No request without a location.' );
		$this->assertEmpty( $this->http_requests );
	}

	/**
	 * Geoapify's Places API finds places within the radius, with their addresses and categories (#710).
	 */
	public function test_geoapify() {
		$fixture = $this->fixture( 'venue/geoapify-places.json' );
		$this->mock_http( array( 'api.geoapify.com/v2/places' => array( 'body' => $fixture ) ) );
		$result = $this->provider( 'Venue_Provider_Geoapify', array( 'api' => 'KEY' ) )->reverse_lookup();

		$request = $this->last_request();
		$this->assertSame( 'https://api.geoapify.com/v2/places', $request['base'] );
		$this->assertSame( 'KEY', $request['query']['apiKey'] );
		$this->assertSame( 'circle:-75.1636,39.9524,' . Venue_Provider::DEFAULT_RADIUS, $request['query']['filter'], 'Longitude first.' );
		$this->assertSame( 'proximity:-75.1636,39.9524', $request['query']['bias'] );
		$this->assertSame( (string) Venue_Provider::DEFAULT_LIMIT, $request['query']['limit'] );
		$this->assertStringContainsString( 'catering', $request['query']['categories'] );
		$this->assertSame( 'en', $request['query']['lang'] );

		$items = $result['items'];
		$this->assertSame( array( 'Philadelphia City Hall', 'Rival Bros Coffee', 'Suburban Station' ), wp_list_pluck( $items, 'name' ), 'Places without a name are left out.' );
		$this->assertPhiladelphia( $items[0] );
		$this->assertEasternTime( $items[0] );
		$this->assertSame( '1400 John F. Kennedy Boulevard', $items[0]['street-address'] );
		$this->assertSame( 'Center City', $items[0]['extended-address'] );
		$this->assertSame( 'https://www.phila.gov/', $items[0]['url'] );
		$this->assertSame( 39.9527, $items[0]['latitude'] );
		$this->assertSame( array( 'government', 'food', 'transport' ), wp_list_pluck( $items, 'venue-category' ) );
		$this->assertSame( 'fa-train', $items[2]['icon'] );
		$this->assertSame( '+1 215 555 0100', $items[1]['tel'] );
		$this->assertContains( 'catering.cafe', $items[1]['category'] );
	}

	/**
	 * Geoapify needs a key, passes on refused requests, and maps its categories from the most specific (#710).
	 */
	public function test_geoapify_errors_and_categories() {
		$this->mock_http( array() );
		$this->assertSame( 'missing_api_key', $this->provider( 'Venue_Provider_Geoapify', array( 'api' => '' ) )->reverse_lookup()->get_error_code() );
		$this->assertEmpty( $this->http_requests );

		$this->mock_http(
			array(
				'api.geoapify.com' => array(
					'code' => 401,
					'body' => array(
						'statusCode' => 401,
						'error'      => 'Unauthorized',
						'message'    => 'Invalid apiKey',
					),
				),
			)
		);
		$error = $this->provider( 'Venue_Provider_Geoapify', array( 'api' => 'KEY' ) )->reverse_lookup();
		$this->assertSame( 401, $error->get_error_data()['status'] );
		$this->assertStringNotContainsString( 'KEY', $error->get_error_message() );

		$this->mock_http( array( 'api.geoapify.com' => array( 'body' => array( 'features' => array() ) ) ) );
		$this->assertSame( array( 'items' => array() ), $this->provider( 'Venue_Provider_Geoapify', array( 'api' => 'KEY' ) )->reverse_lookup() );

		$this->assertSame( 'drink', Venue_Provider_Geoapify::geoapify_category( array( 'catering', 'catering.pub' ) )['category'] );
		$this->assertSame( 'lodging', Venue_Provider_Geoapify::geoapify_category( array( 'accommodation.hotel' ) )['category'] );
		$this->assertSame( 'fa-plane', Venue_Provider_Geoapify::geoapify_category( array( 'airport.international' ) )['icon'] );
		$this->assertSame( 'other', Venue_Provider_Geoapify::geoapify_category( array( 'building', 'officer' ) )['category'], 'Whole keys only.' );
	}

	/**
	 * HERE's browse endpoint finds places within the radius, with their addresses, contacts and categories (#711).
	 */
	public function test_here() {
		$fixture = $this->fixture( 'venue/here-browse.json' );
		$this->mock_http( array( 'browse.search.hereapi.com/v1/browse' => array( 'body' => $fixture ) ) );
		$items = $this->provider( 'Venue_Provider_HERE', array( 'api' => 'KEY' ) )->reverse_lookup()['items'];

		$request = $this->last_request();
		$this->assertSame( 'KEY', $request['query']['apiKey'] );
		$this->assertSame( '39.9524,-75.1636', $request['query']['at'] );
		$this->assertSame( 'circle:39.9524,-75.1636;r=' . Venue_Provider::DEFAULT_RADIUS, $request['query']['in'] );
		$this->assertSame( (string) Venue_Provider::DEFAULT_LIMIT, $request['query']['limit'] );

		$this->assertSame( array( 'Philadelphia City Hall', 'Rival Bros Coffee' ), wp_list_pluck( $items, 'name' ), 'Only places, not streets.' );
		$this->assertPhiladelphia( $items[0] );
		$this->assertEasternTime( $items[0] );
		$this->assertSame( 'US', $items[0]['country-code'], 'From the three-letter code.' );
		$this->assertSame( '1400 John F Kennedy Blvd', $items[0]['street-address'] );
		$this->assertSame( 'https://www.phila.gov', $items[0]['url'] );
		$this->assertSame( '+12156862181', $items[0]['tel'] );
		$this->assertSame( array( 'Historical Monument', 'City Hall' ), $items[0]['category'] );
		$this->assertSame( 'government', $items[0]['venue-category'], 'From the primary category.' );
		$this->assertSame( 'food', $items[1]['venue-category'] );
	}

	/**
	 * HERE needs a key, passes on refused requests, and maps category IDs by their level (#711).
	 */
	public function test_here_errors_and_categories() {
		$this->mock_http( array() );
		$this->assertSame( 'missing_api_key', $this->provider( 'Venue_Provider_HERE', array( 'api' => '' ) )->reverse_lookup()->get_error_code() );
		$this->assertEmpty( $this->http_requests );

		$this->mock_http(
			array(
				'hereapi.com' => array(
					'code' => 401,
					'body' => array(
						'error'             => 'Unauthorized',
						'error_description' => 'apiKey invalid. apiKey not found.',
					),
				),
			)
		);
		$this->assertSame( 401, $this->provider( 'Venue_Provider_HERE', array( 'api' => 'KEY' ) )->reverse_lookup()->get_error_data()['status'] );

		$category = fn( $id ) => Venue_Provider_HERE::here_category( array( array( 'id' => $id ) ) );
		$this->assertSame( 'drink', $category( '200-2000-0011' )['category'] );
		$this->assertSame( 'entertainment', $category( '200-2100-0019' )['category'] );
		$this->assertSame( 'fa-plane', $category( '400-4000-4581' )['icon'] );
		$this->assertSame( 'lodging', $category( '500-5000-0053' )['category'] );
		$this->assertSame( 'other', $category( '1000-0000' )['category'], 'Whole levels only.' );
		$this->assertSame( 'other', Venue_Provider_HERE::here_category( array( 'not a category' ) )['category'] );
	}

	/**
	 * TomTom's nearby search finds places within the radius, with their addresses and classifications (#712).
	 */
	public function test_tomtom() {
		$fixture = $this->fixture( 'venue/tomtom-nearby.json' );
		$this->mock_http( array( 'api.tomtom.com/search/2/nearbySearch/.json' => array( 'body' => $fixture ) ) );
		$items = $this->provider( 'Venue_Provider_TomTom', array( 'api' => 'KEY' ) )->reverse_lookup()['items'];

		$request = $this->last_request();
		$this->assertSame( 'KEY', $request['query']['key'] );
		$this->assertEquals( self::LAT, $request['query']['lat'] );
		$this->assertEquals( self::LNG, $request['query']['lon'] );
		$this->assertSame( (string) Venue_Provider::DEFAULT_RADIUS, $request['query']['radius'] );
		$this->assertSame( (string) Venue_Provider::DEFAULT_LIMIT, $request['query']['limit'] );

		$this->assertSame( array( 'Philadelphia City Hall', 'Rival Bros Coffee' ), wp_list_pluck( $items, 'name' ) );
		$this->assertPhiladelphia( $items[0] );
		$this->assertEasternTime( $items[0] );
		$this->assertSame( '1400 John F Kennedy Boulevard', $items[0]['street-address'] );
		$this->assertSame( 'https://www.phila.gov', $items[0]['url'], 'A website without a scheme gets https.' );
		$this->assertSame( 'https://rivalbros.com', $items[1]['url'] );
		$this->assertSame( '+1 215-686-2181', $items[0]['tel'] );
		$this->assertSame( array( 'city hall', 'government office' ), $items[0]['category'] );
		$this->assertSame( array( 'government', 'food' ), wp_list_pluck( $items, 'venue-category' ) );
	}

	/**
	 * TomTom needs a key, passes on refused requests, and maps its classifications (#712).
	 */
	public function test_tomtom_errors_and_categories() {
		$this->mock_http( array() );
		$this->assertSame( 'missing_api_key', $this->provider( 'Venue_Provider_TomTom', array( 'api' => '' ) )->reverse_lookup()->get_error_code() );
		$this->assertEmpty( $this->http_requests );

		$this->mock_http(
			array(
				'api.tomtom.com' => array(
					'code' => 403,
					'body' => array(
						'errorText'      => 'Developer Inactive',
						'detailedError'  => array( 'message' => 'Developer Inactive' ),
						'httpStatusCode' => 403,
					),
				),
			)
		);
		$error = $this->provider( 'Venue_Provider_TomTom', array( 'api' => 'KEY' ) )->reverse_lookup();
		$this->assertSame( 403, $error->get_error_data()['status'] );
		$this->assertStringNotContainsString( 'KEY', $error->get_error_message() );

		$this->assertSame( 'fa-plane', Venue_Provider_TomTom::tomtom_category( array( array( 'code' => 'AIRPORT' ) ) )['icon'] );
		$this->assertSame( 'lodging', Venue_Provider_TomTom::tomtom_category( array( array( 'code' => 'HOTEL_MOTEL' ) ) )['category'] );
		$this->assertSame( 'other', Venue_Provider_TomTom::tomtom_category( array( array( 'code' => 'UNKNOWN' ), 'nonsense' ) )['category'] );
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

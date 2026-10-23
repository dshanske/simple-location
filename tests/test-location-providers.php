<?php
/**
 * Tests for the Location_Provider base class and the providers in includes/location/: the author
 * profile, address, airport and Compass providers.
 *
 * @package Simple_Location
 */

/**
 * Location provider tests.
 */
class LocationProvidersTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

	/**
	 * A user with a Compass account, logged in.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Known settings for every test.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_zoom', 14 );
		Geo_Provider_Sloc_Test::reset();
		$this->user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		update_user_meta( $this->user_id, 'compass_url', 'https://compass.example.com' );
		update_user_meta( $this->user_id, 'compass_api', 'TOKEN' );
		wp_set_current_user( $this->user_id );
	}

	/**
	 * A Location_Provider whose properties can be set directly.
	 *
	 * @param array $properties Property values.
	 * @return Location_Provider
	 */
	protected function provider_with( $properties ) {
		$provider = new Location_Provider_Dummy();
		$set      = function ( $properties ) {
			foreach ( $properties as $key => $value ) {
				$this->$key = $value;
			}
		};
		Closure::bind( $set, $provider, 'Location_Provider' )( $properties );
		return $provider;
	}

	/**
	 * The bundled providers are registered, after HTML5.
	 */
	public function test_providers_registered() {
		$providers = Loc_Config::geolocation_providers();
		$this->assertSame( 'HTML5', array_key_first( $providers ) );
		foreach ( array( 'dummy', 'address', 'airport', 'compass' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $providers );
			$this->assertNotEmpty( $providers[ $slug ], $slug );
		}

		update_option( 'sloc_geolocation_provider', 'compass' );
		$this->assertInstanceOf( 'Location_Provider_Compass', Loc_Config::geolocation_provider() );
		$this->assertInstanceOf( 'Location_Provider_Dummy', Loc_Config::geolocation_provider( 'dummy' ) );

		// HTML5 runs in the browser, so there is no server-side provider.
		update_option( 'sloc_geolocation_provider', 'HTML5' );
		$this->assertNull( Loc_Config::geolocation_provider() );
	}

	/**
	 * Only providers that can look up a location without the user can update in the background.
	 */
	public function test_background() {
		$this->assertTrue( ( new Location_Provider_Dummy() )->background() );
		$this->assertTrue( ( new Location_Provider_Compass() )->background() );
		$this->assertFalse( ( new Location_Provider_Address() )->background() );
		$this->assertFalse( ( new Location_Provider_Airport() )->background() );
	}

	/**
	 * Nothing set means nothing to return.
	 */
	public function test_empty_provider() {
		$provider = new Location_Provider_Dummy();
		$this->assertSame( array( 'zoom' => 14 ), $provider->get() );
		$this->assertFalse( $provider->get_geouri() );
		$this->assertFalse( $provider->get_geojson() );
		$this->assertFalse( $provider->get_mf2() );
	}

	/**
	 * The get() method returns every property that is set, with an icon for known activities.
	 */
	public function test_get() {
		$provider = $this->provider_with(
			array(
				'latitude'          => 39.9524,
				'longitude'         => -75.1636,
				'altitude'          => 14.3,
				'accuracy'          => 10,
				'altitude_accuracy' => 4,
				'heading'           => 90,
				'speed'             => 1.4,
				'time'              => '2025-07-01T16:00:00+00:00',
				'activity'          => 'walking',
				'annotation'        => 'City Hall',
				'other'             => array( 'wifi' => 'CityHallGuest' ),
			)
		);
		$this->assertSame(
			array(
				'latitude'          => 39.9524,
				'longitude'         => -75.1636,
				'altitude'          => 14.3,
				'accuracy'          => 10,
				'altitude_accuracy' => 4,
				'heading'           => 90,
				'speed'             => 1.4,
				'time'              => '2025-07-01T16:00:00+00:00',
				'zoom'              => 20,
				'activity'          => 'walking',
				'icon'              => 'fa-walking',
				'annotation'        => 'City Hall',
				'other'             => array( 'wifi' => 'CityHallGuest' ),
			),
			$provider->get()
		);

		foreach ( array(
			'plane'   => 'fa-plane',
			'train'   => 'fa-train',
			'running' => 'fa-running',
			'car'     => 'fa-car',
			'bike'    => 'fa-biking',
			'subway'  => 'fa-subway',
			'horse'   => 'fa-horse',
		) as $activity => $icon ) {
			$this->assertSame( $icon, $this->provider_with( array( 'activity' => $activity ) )->get()['icon'], $activity );
		}
		$this->assertArrayNotHasKey( 'icon', $this->provider_with( array( 'activity' => 'unknown' ) )->get() );
	}

	/**
	 * Zoom follows accuracy, capped at 20; high altitudes zoom out; otherwise the setting applies.
	 */
	public function test_derive_zoom() {
		$this->assertEquals( 20, $this->provider_with( array( 'accuracy' => 1 ) )->derive_zoom() );
		$this->assertEquals( 16, $this->provider_with( array( 'accuracy' => 500 ) )->derive_zoom() );
		$this->assertEquals( 8, $this->provider_with( array( 'accuracy' => 100000 ) )->derive_zoom() );
		$this->assertSame( 9, $this->provider_with( array( 'altitude' => 10000 ) )->derive_zoom() );
		$this->assertSame( 14, $this->provider_with( array( 'altitude' => 100 ) )->derive_zoom() );
	}

	/**
	 * Geo URIs include altitude and uncertainty when known.
	 */
	public function test_get_geouri() {
		$this->assertSame(
			'geo:39.9524,-75.1636',
			$this->provider_with(
				array(
					'latitude'  => 39.9524,
					'longitude' => -75.1636,
				)
			)->get_geouri()
		);
		$this->assertSame(
			'geo:39.9524,-75.1636,14.3;u=10',
			$this->provider_with(
				array(
					'latitude'  => 39.9524,
					'longitude' => -75.1636,
					'altitude'  => 14.3,
					'accuracy'  => 10,
				)
			)->get_geouri()
		);
	}

	/**
	 * GeoJSON points put longitude first.
	 */
	public function test_get_geojson() {
		$geojson = $this->provider_with(
			array(
				'latitude'  => 39.9524,
				'longitude' => -75.1636,
				'altitude'  => 14.3,
			)
		)->get_geojson();
		$this->assertSame(
			array(
				'type'       => 'Feature',
				'geometry'   => array(
					'type'        => 'Point',
					'coordinates' => array( -75.1636, 39.9524, 14.3 ),
				),
				'properties' => array(),
			),
			$geojson
		);
	}

	/**
	 * Microformats output is an h-geo with the annotation as its name.
	 */
	public function test_get_mf2() {
		$mf2 = $this->provider_with(
			array(
				'latitude'   => 39.9524,
				'longitude'  => -75.1636,
				'heading'    => 90,
				'annotation' => 'City Hall',
			)
		)->get_mf2();
		$this->assertSame(
			array(
				'type'       => array( 'h-geo' ),
				'properties' => array(
					'latitude'  => array( 39.9524 ),
					'longitude' => array( -75.1636 ),
					'heading'   => array( 90 ),
					'name'      => array( 'City Hall' ),
				),
			),
			$mf2
		);
	}

	/**
	 * Values of 0 (equator, prime meridian, sea level, due north, stationary) are real values and are kept.
	 */
	public function test_zero_values_kept() {
		$provider = $this->provider_with(
			array(
				'latitude'  => 0.0,
				'longitude' => 0.0,
				'altitude'  => 0.0,
				'heading'   => 0,
				'speed'     => 0.0,
			)
		);
		$this->assertSame(
			array(
				'latitude'  => 0.0,
				'longitude' => 0.0,
				'altitude'  => 0.0,
				'heading'   => 0,
				'speed'     => 0.0,
				'zoom'      => 14,
			),
			$provider->get()
		);
		$this->assertSame( 'geo:0,0,0', $provider->get_geouri() );
		$this->assertSame( array( 0.0, 0.0, 0.0 ), $provider->get_geojson()['geometry']['coordinates'] );
		$this->assertSame(
			array(
				'latitude'  => array( 0.0 ),
				'longitude' => array( 0.0 ),
				'altitude'  => array( 0.0 ),
				'heading'   => array( 0 ),
				'speed'     => array( 0.0 ),
			),
			$provider->get_mf2()['properties']
		);
	}

	/**
	 * A single coordinate is not enough for a geo URI or GeoJSON point.
	 */
	public function test_partial_coordinates() {
		$provider = $this->provider_with( array( 'latitude' => 39.9524 ) );
		$this->assertFalse( $provider->get_geouri() );
		$this->assertFalse( $provider->get_geojson() );
	}

	/**
	 * Helpers: activity labels and knots to meters per second.
	 */
	public function test_helpers() {
		$provider = new Location_Provider_Dummy();
		$list     = $provider->get_activity_list();
		$this->assertArrayHasKey( 'plane', $list );
		$this->assertArrayHasKey( 'walking', $list );
		$this->assertEquals( 257, Location_Provider::knots_to_meters( 500 ) );
	}

	/**
	 * The author profile provider returns the user's saved location.
	 */
	public function test_dummy() {
		$provider = new Location_Provider_Dummy();
		$provider->set_user( $this->user_id );
		$this->assertNull( $provider->retrieve() );

		// set_user_geodata() doesn't save (#305), so store the location directly.
		Geo_Data::set_geodata(
			'user',
			$this->user_id,
			'',
			array(
				'latitude'  => 39.9524,
				'longitude' => -75.1636,
				'altitude'  => 14.3,
			)
		);
		$provider->retrieve();
		$this->assertSame( 'geo:39.9524,-75.1636,14.3', $provider->get_geouri() );
	}

	/**
	 * A lookup for a user without a location doesn't return the previous user's location.
	 */
	public function test_dummy_does_not_keep_previous_location() {
		$other_user = self::factory()->user->create();
		set_user_geodata(
			$this->user_id,
			'',
			array(
				'latitude'  => 39.9524,
				'longitude' => -75.1636,
			)
		);
		$provider = new Location_Provider_Dummy();
		$provider->set_user( $this->user_id );
		$provider->retrieve();
		$this->assertSame( 'geo:39.9524,-75.1636', $provider->get_geouri() );

		$provider->set_user( $other_user );
		$this->assertFalse( $provider->get_geouri(), 'Changing the user clears the location.' );
		$this->assertNull( $provider->retrieve() );
		$this->assertFalse( $provider->get_geouri() );
		$this->assertFalse( $provider->get_mf2() );

		// The same user, after their location is removed.
		$provider->set_user( $this->user_id );
		$provider->retrieve();
		delete_user_meta( $this->user_id, 'geo_latitude' );
		delete_user_meta( $this->user_id, 'geo_longitude' );
		$this->assertNull( $provider->retrieve() );
		$this->assertFalse( $provider->get_geouri() );
	}

	/**
	 * The address provider geocodes the address with the chosen geo provider.
	 */
	public function test_address() {
		update_option( 'sloc_geo_provider', 'sloc_test' );
		Geo_Provider_Sloc_Test::$geocode = array(
			'latitude'     => 39.9524,
			'longitude'    => -75.1636,
			'altitude'     => 14.3,
			'display-name' => 'Philadelphia City Hall',
		);
		$provider                        = new Location_Provider_Address();
		$this->assertNull( $provider->retrieve( null, array( 'address' => '  City Hall, Philadelphia ' ) ) );
		$location = $provider->get();
		$this->assertSame( 39.9524, $location['latitude'] );
		$this->assertSame( -75.1636, $location['longitude'] );
		$this->assertSame( 14.3, $location['altitude'] );
		$this->assertSame( 'Philadelphia City Hall', $location['annotation'] );
	}

	/**
	 * The annotation comes from a real geo provider's geocode() result.
	 */
	public function test_address_with_nominatim() {
		update_option( 'sloc_geo_provider', 'nominatim' );
		$this->mock_http( array( 'nominatim.openstreetmap.org/search' => array( 'body' => $this->fixture( 'geo/nominatim-search.json' ) ) ) );
		$geocode = ( new Geo_Provider_Nominatim() )->geocode( 'City Hall, Philadelphia' );
		$this->assertNotEmpty( $geocode['display-name'] );

		$provider = new Location_Provider_Address();
		$this->assertNull( $provider->retrieve( null, array( 'address' => 'City Hall, Philadelphia' ) ) );
		$location = $provider->get();
		$this->assertEqualsWithDelta( 39.9524, $location['latitude'], 0.001 );
		$this->assertSame( $geocode['display-name'], $location['annotation'] );
	}

	/**
	 * The address provider reports missing addresses and failed lookups.
	 */
	public function test_address_errors() {
		update_option( 'sloc_geo_provider', 'sloc_test' );
		$provider = new Location_Provider_Address();
		$this->assertSame( 'empty', $provider->retrieve( null, array() )->get_error_code() );
		$this->assertSame( 'No Address Passed', $provider->get()['annotation'] );
		$this->assertSame( 'no_string', $provider->retrieve( null, array( 'address' => array( 'City Hall' ) ) )->get_error_code() );

		Geo_Provider_Sloc_Test::$geocode = new WP_Error( 'not_found', 'Not found' );
		$this->assertNull( $provider->retrieve( null, array( 'address' => 'Nowhere' ) ) );
		$this->assertSame( 'No Item Found', $provider->get()['annotation'] );
	}

	/**
	 * A failed address lookup doesn't return the previous lookup's location.
	 */
	public function test_address_does_not_keep_previous_location() {
		update_option( 'sloc_geo_provider', 'sloc_test' );
		Geo_Provider_Sloc_Test::$geocode = array(
			'latitude'     => 39.9524,
			'longitude'    => -75.1636,
			'display-name' => 'Philadelphia City Hall',
		);
		$provider                        = new Location_Provider_Address();
		$provider->retrieve( null, array( 'address' => 'City Hall' ) );
		$this->assertSame( 'geo:39.9524,-75.1636', $provider->get_geouri() );

		Geo_Provider_Sloc_Test::$geocode = new WP_Error( 'not_found', 'Not found' );
		$provider->retrieve( null, array( 'address' => 'Nowhere' ) );
		$this->assertFalse( $provider->get_geouri() );
		$this->assertArrayNotHasKey( 'latitude', $provider->get() );

		$provider->retrieve( null, array() );
		$this->assertFalse( $provider->get_geouri() );
	}

	/**
	 * The airport provider rejects anything that isn't a three- or four-letter code.
	 */
	public function test_airport_invalid_codes() {
		$provider = new Location_Provider_Airport();
		$this->assertSame( 'empty', $provider->retrieve( null, array() )->get_error_code() );
		$this->assertSame( 'empty', $provider->retrieve( null, array( 'address' => 'Philadelphia' ) )->get_error_code() );
		$this->assertSame( 'Something was passed but not an airport code', $provider->get()['annotation'] );
	}

	/**
	 * The airport provider looks up IATA and ICAO codes.
	 */
	public function test_airport() {
		$provider = new Location_Provider_Airport();
		$this->assertNull( $provider->retrieve( null, array( 'address' => ' PHL ' ) ) );
		$location = $provider->get();
		$this->assertEqualsWithDelta( 39.87, $location['latitude'], 0.02 );
		$this->assertEqualsWithDelta( -75.24, $location['longitude'], 0.02 );
		$this->assertStringContainsString( 'Philadelphia', $location['annotation'] );

		$provider = new Location_Provider_Airport();
		$provider->retrieve( null, array( 'address' => 'KPHL' ) );
		$this->assertStringContainsString( 'Philadelphia', $provider->get()['annotation'] );

		$this->assertSame( 'no_match', ( new Location_Provider_Airport() )->retrieve( null, array( 'address' => 'QQQ' ) )->get_error_code() );
	}

	/**
	 * The airport provider uses an elevation of 0, and works for airports with no elevation.
	 */
	public function test_airport_elevation() {
		$provider = new Location_Provider_Airport();
		$this->assertNull( $provider->retrieve( null, array( 'address' => 'ELW' ) ) );
		$this->assertEquals( 0, $provider->get()['altitude'] );

		$provider = new Location_Provider_Airport();
		$this->assertNull( $provider->retrieve( null, array( 'address' => 'WLR' ) ) );
		$location = $provider->get();
		$this->assertSame( 'Loring Seaplane Base', $location['annotation'] );
		$this->assertArrayNotHasKey( 'altitude', $location );
	}

	/**
	 * Compass adds its URL and key fields to the user profile.
	 */
	public function test_compass_profile_fields() {
		$fields = Location_Provider_Compass::user_contactmethods( array() );
		$this->assertArrayHasKey( 'compass_api', $fields );
		$this->assertArrayHasKey( 'compass_url', $fields );
		$this->assertSame( 12, has_filter( 'user_contactmethods', array( 'Location_Provider_Compass', 'user_contactmethods' ) ) );
	}

	/**
	 * Compass returns the user's last location with Overland's accuracy and motion properties.
	 */
	public function test_compass_last() {
		$fixture = $this->fixture( 'location/compass-last.json' );
		$this->mock_http( array( 'compass.example.com/api/last/' => array( 'body' => $fixture ) ) );
		$provider = new Location_Provider_Compass();
		$this->assertNull( $provider->retrieve() );

		$request = $this->last_request();
		$this->assertSame( 'https://compass.example.com/api/last/', $request['base'] );
		$this->assertSame( 'TOKEN', $request['query']['token'] );
		$this->assertArrayNotHasKey( 'before', $request['query'] );

		$properties = $fixture['data']['properties'];
		$location   = $provider->get();
		$this->assertSame( $fixture['data']['geometry']['coordinates'][1], $location['latitude'] );
		$this->assertSame( $fixture['data']['geometry']['coordinates'][0], $location['longitude'] );
		$this->assertSame( round( $fixture['data']['geometry']['coordinates'][2], 2 ), $location['altitude'] );
		$this->assertSame( $properties['horizontal_accuracy'], $location['accuracy'] );
		$this->assertSame( $properties['vertical_accuracy'], $location['altitude_accuracy'] );
		$this->assertSame( $properties['speed'], $location['speed'] );
		$this->assertSame( $properties['motion'][0], $location['activity'] );
		$this->assertSame( $properties['wifi'], $location['other']['wifi'] );
		$this->assertArrayNotHasKey( 'horizontal_accuracy', $location['other'] );
		$this->assertArrayNotHasKey( 'timestamp', $location['other'] );
	}

	/**
	 * Compass looks up the last location before a given time, in UTC.
	 */
	public function test_compass_before() {
		$this->mock_http( array( 'compass.example.com' => array( 'body' => $this->fixture( 'location/compass-last.json' ) ) ) );
		$provider = new Location_Provider_Compass();
		$provider->retrieve( '2025-07-01T12:00:00-04:00' );
		$this->assertSame( '2025-07-01T16:00:00', $this->last_request()['query']['before'] );
		$this->assertSame( '2025-07-01T16:00:00+00:00', $provider->get()['time'] );

		$time = new DateTime( '2025-07-01T12:00:00-04:00' );
		$provider->retrieve( $time );
		$this->assertSame( '2025-07-01T16:00:00', $this->last_request()['query']['before'] );
		$this->assertSame( '-04:00', $time->format( 'P' ), 'The caller\'s DateTime is not changed.' );
	}

	/**
	 * Compass converts immutable dates and Unix timestamps to UTC, and rejects times it can't parse.
	 */
	public function test_compass_before_immutable_and_timestamp() {
		update_option( 'timezone_string', 'America/New_York' );
		$this->mock_http( array( 'compass.example.com' => array( 'body' => $this->fixture( 'location/compass-last.json' ) ) ) );
		$provider = new Location_Provider_Compass();

		$provider->retrieve( new DateTimeImmutable( '2025-07-01 20:00', wp_timezone() ) );
		$this->assertSame( '2025-07-02T00:00:00', $this->last_request()['query']['before'] );
		$this->assertSame( '2025-07-02T00:00:00+00:00', $provider->get()['time'] );

		$provider->retrieve( 1751400000 );
		$this->assertSame( '2025-07-01T20:00:00', $this->last_request()['query']['before'] );

		$this->http_requests = array();
		$error               = $provider->retrieve( 'not a time' );
		$this->assertWPError( $error );
		$this->assertSame( 'invalid_time', $error->get_error_code() );
		$this->assertEmpty( $this->http_requests );
	}

	/**
	 * Altitude and speed can come from Overland-style properties.
	 */
	public function test_compass_property_fallbacks() {
		$fixture                                    = $this->fixture( 'location/compass-last.json' );
		$fixture['data']['geometry']['coordinates'] = array( -75.1636, 39.9524 );
		$fixture['data']['properties']              = array(
			'altitude'           => 14.333,
			'accuracy'           => 25,
			'ground_speed_knots' => 450,
			'activity'           => 'still',
			'heading'            => 270,
		);
		$this->mock_http( array( 'compass.example.com' => array( 'body' => $fixture ) ) );
		$provider = new Location_Provider_Compass();
		$provider->retrieve();
		$location = $provider->get();
		$this->assertSame( 14.33, $location['altitude'] );
		$this->assertSame( 25, $location['accuracy'] );
		$this->assertEquals( Location_Provider::knots_to_meters( 450 ), $location['speed'] );
		$this->assertSame( 270, $location['heading'] );
		$this->assertSame( 'still', $location['activity'] );
		foreach ( array( 'altitude', 'accuracy', 'ground_speed_knots', 'heading' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $location['other'], $key );
		}

		$fixture['data']['properties'] = array( 'ground_speed' => 100 );
		$this->mock_http( array( 'compass.example.com' => array( 'body' => $fixture ) ) );
		$provider = new Location_Provider_Compass();
		$provider->retrieve();
		$this->assertSame( Location_Provider::miph_to_mps( 100 ), $provider->get()['speed'] );
	}

	/**
	 * An operator that isn't a 2-letter IATA or 3-letter ICAO airline code, such as a name, is kept as it is.
	 */
	public function test_compass_operator_name() {
		$fixture                       = $this->fixture( 'location/compass-last.json' );
		$fixture['data']['properties'] = array( 'operator' => 'SEPTA Regional Rail' );
		$this->mock_http( array( 'compass.example.com' => array( 'body' => $fixture ) ) );
		$provider = new Location_Provider_Compass();
		$this->assertNull( $provider->retrieve() );
		$this->assertSame( 'SEPTA Regional Rail', $provider->get()['other']['operator'] );
	}

	/**
	 * Flights reported by a flight source are planes, annotated with the flight number.
	 */
	public function test_compass_flight() {
		$fixture                       = $this->fixture( 'location/compass-last.json' );
		$fixture['data']['properties'] = array(
			'source'        => 'flightaware.com',
			'flight_number' => 'b6123',
		);
		$this->mock_http( array( 'compass.example.com' => array( 'body' => $fixture ) ) );
		$provider = new Location_Provider_Compass();
		$provider->retrieve();
		$location = $provider->get();
		$this->assertSame( 'plane', $location['activity'] );
		$this->assertSame( 'fa-plane', $location['icon'] );
		$this->assertSame( 'B6123', $location['annotation'] );
		$this->assertSame( 'B6123', $location['other']['number'] );
		$this->assertArrayNotHasKey( 'flight_number', $location['other'] );

		// Known airline prefixes are removed from flight numbers.
		$fixture['data']['properties'] = array(
			'source' => 'flight',
			'flight' => 'ei105',
		);
		$this->mock_http( array( 'compass.example.com' => array( 'body' => $fixture ) ) );
		$provider = new Location_Provider_Compass();
		$provider->retrieve();
		$this->assertSame( '105', $provider->get()['annotation'] );
	}

	/**
	 * A source that names a known activity, such as train, becomes that activity.
	 */
	public function test_compass_known_source() {
		$fixture                       = $this->fixture( 'location/compass-last.json' );
		$fixture['data']['properties'] = array( 'source' => 'train' );
		$this->mock_http( array( 'compass.example.com' => array( 'body' => $fixture ) ) );
		$provider = new Location_Provider_Compass();
		$provider->retrieve();
		$this->assertSame( 'train', $provider->get()['activity'] );
		$this->assertSame( 'fa-train', $provider->get()['icon'] );
	}

	/**
	 * A location provider is active when it is the chosen geolocation provider.
	 */
	public function test_is_active() {
		update_option( 'sloc_geolocation_provider', 'compass' );
		$this->assertTrue( ( new Location_Provider_Compass() )->is_active() );
		update_option( 'sloc_geolocation_provider', 'dummy' );
		$this->assertFalse( ( new Location_Provider_Compass() )->is_active() );
	}

	/**
	 * Unknown sources become an unknown activity.
	 */
	public function test_compass_unknown_source() {
		$fixture                       = $this->fixture( 'location/compass-last.json' );
		$fixture['data']['properties'] = array( 'source' => 'somewhere' );
		$this->mock_http( array( 'compass.example.com' => array( 'body' => $fixture ) ) );
		$provider = new Location_Provider_Compass();
		$provider->retrieve();
		$this->assertSame( 'unknown', $provider->get()['activity'] );
	}

	/**
	 * Without a logged-in user, URL or key, Compass makes no request.
	 */
	public function test_compass_not_configured() {
		$this->mock_http( array() );
		$provider = new Location_Provider_Compass();

		delete_user_meta( $this->user_id, 'compass_api' );
		$this->assertNull( $provider->retrieve() );
		$this->assertNull( $provider->query( '2025-07-01T00:00:00Z', '2025-07-02T00:00:00Z' ) );

		delete_user_meta( $this->user_id, 'compass_url' );
		$this->assertNull( $provider->retrieve() );
		$this->assertNull( $provider->query( '2025-07-01T00:00:00Z', '2025-07-02T00:00:00Z' ) );

		wp_set_current_user( 0 );
		$this->assertNull( $provider->retrieve() );
		$this->assertNull( $provider->query( '2025-07-01T00:00:00Z', '2025-07-02T00:00:00Z' ) );

		$this->assertEmpty( $this->http_requests );
	}

	/**
	 * Compass uses the account of the user set with set_user(), but only for that user or someone who can edit them.
	 */
	public function test_compass_uses_set_user() {
		$fixture = array( 'compass.example.com' => array( 'body' => $this->fixture( 'location/compass-last.json' ) ) );
		$editor  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		update_user_meta( $editor, 'compass_url', 'https://editor-compass.example.org' );
		update_user_meta( $editor, 'compass_api', 'EDITOR' );
		$provider = new Location_Provider_Compass();
		$provider->set_user( $this->user_id );

		// The author themselves.
		$this->mock_http( $fixture );
		$provider->retrieve();
		$this->assertSame( 'https://compass.example.com/api/last/', $this->last_request()['base'] );
		$this->assertSame( 'TOKEN', $this->last_request()['query']['token'] );

		// An editor can't use the author's account, and their own isn't used instead.
		wp_set_current_user( $editor );
		$this->mock_http( $fixture );
		$provider->set_user( $this->user_id );
		$error = $provider->retrieve();
		$this->assertWPError( $error );
		$this->assertSame( 'compass_not_allowed', $error->get_error_code() );
		$this->assertSame( 'compass_not_allowed', $provider->query( '2025-07-01T00:00:00Z', '2025-07-02T00:00:00Z' )->get_error_code() );
		$this->assertFalse( $provider->get_geouri() );
		$this->assertEmpty( $this->http_requests );

		// An administrator can.
		wp_set_current_user( $admin );
		$this->mock_http( $fixture );
		$provider->retrieve();
		$this->assertSame( 'TOKEN', $this->last_request()['query']['token'] );
		$this->assertNotFalse( $provider->get_geouri() );

		// Without set_user(), the current user's account is used.
		wp_set_current_user( $editor );
		$this->mock_http( array( 'editor-compass.example.org' => array( 'body' => $this->fixture( 'location/compass-last.json' ) ) ) );
		$provider = new Location_Provider_Compass();
		$provider->retrieve();
		$this->assertSame( 'EDITOR', $this->last_request()['query']['token'] );
	}

	/**
	 * Sets up Bulk Lookup Location with Compass for a post by the test author.
	 *
	 * @return int Post ID.
	 */
	protected function compass_bulk_lookup_post() {
		update_option( 'sloc_geolocation_provider', 'compass' );
		update_option( 'sloc_geo_provider', 'sloc_test' );
		Geo_Provider_Sloc_Test::$reverse = array(
			'display-name' => 'Philadelphia City Hall',
			'locality'     => 'Philadelphia',
			'region'       => 'Pennsylvania',
			'region-code'  => 'PA',
			'country-name' => 'United States',
			'country-code' => 'US',
		);
		$this->mock_http( array( 'compass.example.com' => array( 'body' => $this->fixture( 'location/compass-last.json' ) ) ) );
		return self::factory()->post->create( array( 'post_author' => $this->user_id ) );
	}

	/**
	 * Bulk Lookup Location with Compass skips another author's post for a user who can't edit that author.
	 */
	public function test_compass_bulk_lookup_other_author_skipped() {
		$post_id = $this->compass_bulk_lookup_post();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertFalse( Geo_Data::bulk_edit_lookup_location( $post_id ) );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_latitude', true ) );
		$this->assertEmpty( $this->http_requests );
	}

	/**
	 * Bulk Lookup Location with Compass fills in another author's post from their account for an administrator.
	 */
	public function test_compass_bulk_lookup_other_author_admin() {
		$post_id = $this->compass_bulk_lookup_post();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( $post_id, Geo_Data::bulk_edit_lookup_location( $post_id ) );
		$this->assertNotSame( '', get_post_meta( $post_id, 'geo_latitude', true ) );
		$this->assertSame( 'TOKEN', $this->last_request()['query']['token'] );
	}

	/**
	 * Compass errors and incomplete responses are reported.
	 */
	public function test_compass_errors() {
		$provider = new Location_Provider_Compass();

		$this->mock_http(
			array(
				'compass.example.com' => array(
					'code' => 401,
					'body' => array( 'error' => 'invalid token' ),
				),
			)
		);
		$error = $provider->retrieve();
		$this->assertSame( 401, $error->get_error_code() );
		$this->assertStringNotContainsString( 'TOKEN', wp_json_encode( $error->get_all_error_data() ), 'The token is not in the error.' );

		$this->mock_http( array( 'compass.example.com' => array( 'body' => array( 'error' => 'no data' ) ) ) );
		$error = $provider->retrieve();
		$this->assertSame( 'compass_error', $error->get_error_code() );
		$this->assertSame( 'no data', $error->get_error_message() );

		$this->mock_http( array( 'compass.example.com' => new WP_Error( 'http_request_failed', 'Timed out' ) ) );
		$this->assertSame( 'http_request_failed', $provider->retrieve()->get_error_code() );

		$this->mock_http( array( 'compass.example.com' => array( 'body' => array( 'data' => null ) ) ) );
		$this->assertFalse( $provider->retrieve() );

		$this->mock_http( array( 'compass.example.com' => array( 'body' => array( 'data' => array( 'type' => 'Feature' ) ) ) ) );
		$this->assertFalse( $provider->retrieve() );
	}

	/**
	 * Compass lookups refuse local and private addresses, since the URL comes from the user's profile, and encode
	 * the token (#649).
	 */
	public function test_compass_safe_request() {
		update_user_meta( $this->user_id, 'compass_api', 'TO&KEN' );
		$this->mock_http( array( 'compass.example.com' => array( 'body' => $this->fixture( 'location/compass-last.json' ) ) ) );
		( new Location_Provider_Compass() )->retrieve();
		$request = $this->last_request();
		$this->assertTrue( $request['args']['reject_unsafe_urls'] );
		$this->assertSame( 'TO&KEN', $request['query']['token'] );

		// Without the mock, WordPress refuses the request before it is made.
		$this->mock_http( array() );
		remove_all_filters( 'pre_http_request' );
		update_user_meta( $this->user_id, 'compass_url', 'http://127.0.0.1:8080' );
		$error = ( new Location_Provider_Compass() )->retrieve();
		$this->assertWPError( $error );
		$this->assertSame( 'http_request_failed', $error->get_error_code() );
		$this->assertSame( 'A valid URL was not provided.', $error->get_error_message() );
	}

	/**
	 * A failed Compass lookup doesn't return the previous lookup's location.
	 */
	public function test_compass_does_not_keep_previous_location() {
		$provider = new Location_Provider_Compass();
		$this->mock_http( array( 'compass.example.com' => array( 'body' => $this->fixture( 'location/compass-last.json' ) ) ) );
		$provider->retrieve();
		$this->assertNotFalse( $provider->get_geouri() );

		$this->mock_http(
			array(
				'compass.example.com' => array(
					'code' => 401,
					'body' => array( 'error' => 'invalid token' ),
				),
			)
		);
		$provider->retrieve();
		$this->assertFalse( $provider->get_geouri() );
		$this->assertArrayNotHasKey( 'speed', $provider->get() );
		$this->assertArrayNotHasKey( 'activity', $provider->get() );
	}

	/**
	 * Compass queries send a UTC offset timezone intact.
	 */
	public function test_compass_query_offset_timezone() {
		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', 5.5 );
		$this->mock_http( array( 'compass.example.com/api/query/' => array( 'body' => $this->fixture( 'location/compass-query.json' ) ) ) );
		$provider = new Location_Provider_Compass();
		$provider->query( '2025-07-01T16:00:00', '2025-07-01T17:00:00' );
		$this->assertSame( '+05:30', $this->last_request()['query']['tz'] );
	}

	/**
	 * Compass queries return the track between two times as longitude, latitude pairs.
	 */
	public function test_compass_query() {
		update_option( 'timezone_string', 'America/New_York' );
		$fixture = $this->fixture( 'location/compass-query.json' );
		$this->mock_http( array( 'compass.example.com/api/query/' => array( 'body' => $fixture ) ) );
		$provider = new Location_Provider_Compass();
		$track    = $provider->query( '2025-07-01T16:00:00', new DateTime( '2025-07-01T17:00:00' ) );

		$request = $this->last_request();
		$this->assertSame( 'TOKEN', $request['query']['token'] );
		$this->assertSame( 'linestring', $request['query']['format'] );
		$this->assertSame( '2025-07-01T16:00:00', $request['query']['start'] );
		$this->assertSame( '2025-07-01T17:00:00', $request['query']['end'] );
		$this->assertSame( wp_timezone_string(), $request['query']['tz'] );

		$this->assertCount( count( $fixture['linestring']['coordinates'] ), $track );
		foreach ( $fixture['linestring']['coordinates'] as $i => $point ) {
			$this->assertSame( array( $point[0], $point[1] ), $track[ $i ] );
		}

		$this->mock_http( array( 'compass.example.com' => array( 'body' => array( 'linestring' => array() ) ) ) );
		$this->assertFalse( $provider->query( '2025-07-01T16:00:00', '2025-07-01T17:00:00' ) );
		$this->mock_http( array( 'compass.example.com' => array( 'body' => array() ) ) );
		$this->assertFalse( $provider->query( '2025-07-01T16:00:00', '2025-07-01T17:00:00' ) );
		$this->mock_http( array( 'compass.example.com' => new WP_Error( 'http_request_failed', 'Timed out' ) ) );
		$this->assertWPError( $provider->query( '2025-07-01T16:00:00', '2025-07-01T17:00:00' ) );
	}

	/**
	 * The REST lookup endpoint uses the chosen provider for the current user.
	 */
	public function test_rest_lookup() {
		update_option( 'sloc_geolocation_provider', 'dummy' );
		// set_user_geodata() doesn't save (#305), so store the location directly.
		Geo_Data::set_geodata(
			'user',
			$this->user_id,
			'',
			array(
				'latitude'  => 39.9524,
				'longitude' => -75.1636,
			)
		);
		$request  = new WP_REST_Request( 'GET', '/sloc_geo/1.0/lookup' );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertEquals( 39.9524, $response->get_data()['latitude'] );
		$this->assertEquals( -75.1636, $response->get_data()['longitude'] );
	}
}

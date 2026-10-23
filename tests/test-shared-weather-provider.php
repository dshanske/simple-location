<?php
/**
 * Tests that lookups don't leave a station, location or cache time on the shared weather providers (#681).
 *
 * @package Simple_Location
 */

/**
 * Shared weather provider tests.
 */
class SharedWeatherProviderTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

	/**
	 * Two custom stations, home at 10 °C and cabin at 30 °C.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_weather_provider', 'station' );
		delete_option( 'sloc_weather_fallbacks' );
		update_option( 'sloc_measurements', 'metric' );
		update_option(
			'sloc_stations',
			array(
				array(
					'id'        => 'home',
					'url'       => 'https://home.example.com/current.json',
					'latitude'  => 39.95,
					'longitude' => -75.16,
				),
				array(
					'id'        => 'cabin',
					'url'       => 'https://cabin.example.com/current.json',
					'latitude'  => 44.0,
					'longitude' => -72.0,
				),
			)
		);
		$reading = fn( $temperature ) => array(
			'body' => array(
				'temperature' => $temperature,
				'timestamp'   => time(),
			),
		);
		$this->mock_http(
			array(
				'home.example.com'  => $reading( 10 ),
				'cabin.example.com' => $reading( 30 ),
			)
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Clear the shared provider between tests.
	 */
	public function tear_down() {
		$shared = Loc_Config::weather_provider( 'station' );
		$shared->set( array( 'station_id' => '' ) );
		parent::tear_down();
	}

	/**
	 * Returns a provider's cache time.
	 *
	 * @param Weather_Provider $provider Provider.
	 * @return int
	 */
	protected function cache_time( $provider ) {
		$property = new ReflectionProperty( $provider, 'cache_time' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		return $property->getValue( $provider );
	}

	/**
	 * A lookup by station leaves the shared provider without the station, or its cache time.
	 */
	public function test_station_lookup_leaves_shared_provider() {
		$shared = Loc_Config::weather_provider( 'station' );
		$before = $this->cache_time( $shared );
		$this->assertSame( 30, Sloc_Weather_Data::get_weather_by_station( 'cabin', null, 600 )['temperature'] );
		$this->assertEmpty( $shared->get_station() );
		$this->assertSame( $before, $this->cache_time( $shared ) );
	}

	/**
	 * After the station widget's lookup, the weather at home is home's, also from REST with the provider named.
	 */
	public function test_location_after_station() {
		Sloc_Weather_Data::get_weather_by_station( 'cabin' );
		$this->assertSame( 10, Sloc_Weather_Data::get_weather_by_location( 39.95, -75.16 )['temperature'] );

		$request = new WP_REST_Request( 'GET', '/sloc_geo/1.0/weather' );
		$request->set_param( 'station', 'cabin' );
		$this->assertSame( 30, REST_Geo::weather( $request )['temperature'] );

		$request = new WP_REST_Request( 'GET', '/sloc_geo/1.0/weather' );
		$request->set_param( 'provider', 'station' );
		$request->set_param( 'latitude', 39.95 );
		$request->set_param( 'longitude', -75.16 );
		$this->assertSame( 10, REST_Geo::weather( $request )['temperature'] );
		$this->assertEmpty( Loc_Config::weather_provider( 'station' )->get_station() );
	}

	/**
	 * Setting coordinates clears a station; setting only a station clears the coordinates; setting both keeps both.
	 */
	public function test_set() {
		$provider = new Weather_Provider_Station();
		$provider->set( array( 'station_id' => 'cabin' ) );
		$provider->set( 39.95, -75.16 );
		$this->assertNull( $provider->get_station() );

		$provider->set( array( 'station_id' => 'cabin' ) );
		$this->assertSame( 'cabin', $provider->get_station() );
		$this->assertFalse( $provider->get(), 'A station replaces the location.' );

		$provider->set(
			array(
				'latitude'  => 39.95,
				'longitude' => -75.16,
			)
		);
		$this->assertNull( $provider->get_station() );

		$provider->set(
			array(
				'station_id' => 'KLGA',
				'latitude'   => 40.77,
				'longitude'  => -73.87,
			)
		);
		$this->assertSame( 'KLGA', $provider->get_station() );
		$this->assertSame( 40.77, $provider->get()['latitude'] );

		$provider->set( array( 'units' => 'imperial' ) );
		$this->assertSame( 'KLGA', $provider->get_station(), 'Setting only the units changes neither.' );
		$this->assertSame( 40.77, $provider->get()['latitude'] );
	}
}

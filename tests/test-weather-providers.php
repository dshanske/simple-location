<?php
/**
 * Tests for the weather providers in includes/weather/, using vendor responses in
 * tests/data/weather (see tests/data/README.md).
 *
 * @package Simple_Location
 */

/**
 * Weather provider tests.
 */
class WeatherProvidersTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

	/**
	 * Philadelphia.
	 */
	const LAT = 39.9524;
	const LNG = -75.1636;

	/**
	 * Known settings for every test.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'America/New_York' );
		update_option( 'sloc_measurements', 'metric' );
		delete_option( 'sloc_weather_fallbacks' );
	}

	/**
	 * Creates a provider for Philadelphia.
	 *
	 * @param string $class Provider class.
	 * @param array  $args  Constructor arguments.
	 * @return Weather_Provider
	 */
	protected function provider( $class, $args = array() ) {
		$provider = new $class( $args );
		$provider->set( self::LAT, self::LNG );
		return $provider;
	}

	/**
	 * Asserts the values every provider's fixture describes: about 21.5 °C, 60 % humidity, wind from 230°.
	 *
	 * Fixtures may be replaced with real captures, so the expected values are read from the fixture
	 * by each test; this checks the plugin's units and keys.
	 *
	 * @param array $expected Expected values by plugin key.
	 * @param array $actual   Parsed conditions.
	 */
	protected function assertConditions( $expected, $actual ) {
		$this->assertIsArray( $actual );
		foreach ( $expected as $key => $value ) {
			$this->assertArrayHasKey( $key, $actual, $key );
			if ( is_numeric( $value ) ) {
				$this->assertEqualsWithDelta( $value, $actual[ $key ], 0.51, $key );
			} else {
				$this->assertSame( $value, $actual[ $key ], $key );
			}
		}
		foreach ( array( 'sunrise', 'sunset', 'localtime' ) as $key ) {
			$this->assertArrayHasKey( $key, $actual, "extra_data() adds $key" );
		}
	}

	/**
	 * Every bundled weather provider is registered.
	 */
	public function test_providers_registered() {
		$providers = Loc_Config::weather_providers();
		foreach ( array( 'openweathermap', 'pirateweather', 'weatherbit', 'weatherstack', 'visualcrossing', 'aeris', 'nwsus', 'meteostat', 'here', 'station', 'openmeteo', 'metno', 'eccc' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $providers, $slug );
			$this->assertNotEmpty( $providers[ $slug ]['name'], $slug );
		}
	}

	/**
	 * Providers that need credentials return a `missing_api_key` error, which administrators are told about, and make
	 * no request without them.
	 */
	public function test_missing_credentials() {
		$this->mock_http( array() );
		foreach ( array( 'OpenWeatherMap', 'PirateWeather', 'Weatherbit', 'Weatherstack', 'VisualCrossing', 'Meteostat', 'HERE', 'Aeris' ) as $name ) {
			$class    = 'Weather_Provider_' . $name;
			$provider = $this->provider( $class, array( 'api' => '' ) );
			$error    = $provider->get_conditions();
			$this->assertWPError( $error, $name );
			$this->assertSame( 'missing_api_key', $error->get_error_code(), $name );
			$this->assertStringContainsString( $provider->get_name(), $error->get_error_message(), $name );
			$this->assertTrue( Sloc_Provider_Errors::is_actionable( $error, $provider ), $name );
		}
		$this->assertSame( array(), $this->http_requests );
	}

	/**
	 * Providers with only current weather say so, so the weather chain skips them for other times (#490).
	 */
	public function test_supports_time() {
		foreach ( array( 'OpenWeatherMap', 'Weatherbit', 'Weatherstack', 'HERE', 'NWSUS', 'Station', 'Weewx', 'Metno' ) as $name ) {
			$provider = $this->provider( 'Weather_Provider_' . $name );
			$this->assertTrue( $provider->supports_time(), $name );
			$this->assertTrue( $provider->supports_time( time() - 1800 ), $name );
			$this->assertFalse( $provider->supports_time( strtotime( '-2 days' ) ), $name );
			$this->assertFalse( $provider->supports_time( strtotime( '+2 days' ) ), $name );
		}
		foreach ( array( 'PirateWeather', 'VisualCrossing', 'Meteostat', 'Aeris', 'OpenMeteo' ) as $name ) {
			$this->assertTrue( $this->provider( 'Weather_Provider_' . $name )->supports_time( strtotime( '-2 days' ) ), $name );
		}
	}

	/**
	 * OpenWeatherMap: One Call 3.0 current conditions, in metric units, cached.
	 */
	public function test_openweathermap() {
		$fixture = $this->fixture( 'weather/openweathermap-onecall.json' );
		$this->mock_http( array( 'api.openweathermap.org/data/3.0/onecall' => array( 'body' => $fixture ) ) );
		$provider   = $this->provider(
			'Weather_Provider_OpenWeatherMap',
			array(
				'api'        => 'owm-key',
				'cache_time' => 600,
			)
		);
		$conditions = $provider->get_conditions();

		$request = $this->last_request();
		$this->assertSame( 'owm-key', $request['query']['appid'] );
		$this->assertSame( 'metric', $request['query']['units'] );
		$this->assertEquals( self::LAT, $request['query']['lat'] );

		$current = $fixture['current'];
		$this->assertConditions(
			array(
				'temperature' => $current['temp'],
				'dewpoint'    => $current['dew_point'],
				'humidity'    => $current['humidity'],
				'pressure'    => $current['pressure'],
				'cloudiness'  => $current['clouds'],
				'visibility'  => $current['visibility'],
				'uv'          => $current['uvi'],
				'windspeed'   => $current['wind_speed'],
				'winddegree'  => $current['wind_deg'],
				'windgust'    => $current['wind_gust'],
				'code'        => $current['weather'][0]['id'],
				'summary'     => $current['weather'][0]['description'],
			),
			$conditions
		);
		$this->assertEquals( $current['rain']['1h'], $conditions['rain'] );
		$this->assertSame( 3.6, $conditions['windspeed'], 'Wind speed to one decimal place (#348).' );

		$this->assertSame( $conditions['temperature'], $provider->get_conditions()['temperature'] );
		$this->assertCount( 1, $this->http_requests, 'The second call is served from the cache.' );
	}

	/**
	 * Each provider with an apparent temperature stores it, in degrees Celsius (#813).
	 */
	public function test_apparent_temperature() {
		update_option( 'sloc_aeris_client_id', 'client' );
		update_option( 'sloc_aeris_client_secret', 'secret' );
		$cases = array(
			'Weather_Provider_OpenWeatherMap' => array( 'openweathermap-onecall.json', 'api.openweathermap.org', fn( $f ) => $f['current']['feels_like'] ),
			'Weather_Provider_PirateWeather'  => array( 'pirateweather-forecast.json', 'api.pirateweather.net', fn( $f ) => $f['currently']['apparentTemperature'] ),
			'Weather_Provider_Weatherbit'     => array( 'weatherbit-current.json', 'api.weatherbit.io', fn( $f ) => $f['data'][0]['app_temp'] ),
			'Weather_Provider_Weatherstack'   => array( 'weatherstack-current.json', 'api.weatherstack.com', fn( $f ) => $f['current']['feelslike'] ),
			'Weather_Provider_VisualCrossing' => array( 'visualcrossing-current.json', 'weather.visualcrossing.com', fn( $f ) => $f['currentConditions']['feelslike'] ),
			'Weather_Provider_HERE'           => array( 'here-observation.json', 'weather.hereapi.com', fn( $f ) => (float) $f['places'][0]['observations'][0]['comfort'] ),
			'Weather_Provider_Aeris'          => array( 'aeris-observations.json', 'data.api.xweather.com', fn( $f ) => $f['response'][0]['ob']['feelslikeC'] ),
			'Weather_Provider_OpenMeteo'      => array( 'openmeteo-current.json', 'api.open-meteo.com', fn( $f ) => $f['current']['apparent_temperature'] ),
		);
		foreach ( $cases as $class => $case ) {
			$fixture = $this->fixture( 'weather/' . $case[0] );
			$this->mock_http( array( $case[1] => array( 'body' => $fixture ) ) );
			$conditions = $this->provider( $class, array( 'api' => 'key' ) )->get_conditions();
			$this->assertIsNumeric( $case[2]( $fixture ), $class );
			$this->assertEqualsWithDelta( $case[2]( $fixture ), $conditions['apptemp'], 0.01, $class );
			remove_filter( 'pre_http_request', array( $this, 'mock_http_response' ) );
		}
		$this->assertContains( 'apparent_temperature', Weather_Provider_OpenMeteo::VARIABLES );
		$this->assertArrayNotHasKey( 'apptemp', ( new Weather_Provider_OpenMeteo() )->convert_data( array( 'temperature_2m' => 5 ) ) );
	}

	/**
	 * Open-Meteo's past weather has the apparent temperature too.
	 */
	public function test_openmeteo_archive_apparent_temperature() {
		$fixture = $this->fixture( 'weather/openmeteo-archive.json' );
		$hour    = 6;
		$values  = array();
		foreach ( Weather_Provider_OpenMeteo::VARIABLES as $variable ) {
			$values[ $variable ] = $fixture['hourly'][ $variable ][ $hour ] ?? null;
		}
		$conditions = ( new Weather_Provider_OpenMeteo() )->convert_data( $values );
		$this->assertEqualsWithDelta( $fixture['hourly']['apparent_temperature'][ $hour ], $conditions['apptemp'], 0.01 );
	}

	/**
	 * NWS: the cloud base is the lowest cloud layer's base, in meters; not a sky hidden by fog, nor another unit (#813).
	 */
	public function test_nws_cloud_base() {
		$layer = fn( $amount, $base, $unit = 'wmoUnit:m' ) => array(
			'amount' => $amount,
			'base'   => array(
				'unitCode' => $unit,
				'value'    => $base,
			),
		);
		$this->assertSame( 760.0, Weather_Provider_NWSUS::cloud_base( array( $layer( 'BKN', 1520 ), $layer( 'FEW', 760 ), $layer( 'OVC', 3000 ) ) ) );
		$this->assertNull( Weather_Provider_NWSUS::cloud_base( array( $layer( 'VV', 60 ), $layer( 'CLR', null ) ) ) );
		$this->assertNull( Weather_Provider_NWSUS::cloud_base( array( $layer( 'SCT', 5000, 'wmoUnit:ft' ) ) ) );
		$this->assertNull( Weather_Provider_NWSUS::cloud_base( array( $layer( 'SCT', null ), 'not a layer' ) ) );
		$this->assertNull( Weather_Provider_NWSUS::cloud_base( null ) );

		$observation = $this->fixture( 'weather/nws-observation.json' );
		$this->mock_http(
			array(
				'api.weather.gov/stations/KPHL/observations/latest' => array( 'body' => $observation ),
				'api.weather.gov/stations/KPHL' => array( 'body' => $this->fixture( 'weather/nws-station.json' ) ),
			)
		);
		$provider = new Weather_Provider_NWSUS();
		$provider->set( array( 'station_id' => 'KPHL' ) );
		$this->assertEquals( $observation['properties']['cloudLayers'][0]['base']['value'], $provider->get_conditions()['cloudbase'] );
	}

	/**
	 * OpenWeatherMap passes on request failures, returns an error with the status when it refuses a request, and
	 * no_results for a body without current conditions (#692).
	 */
	public function test_openweathermap_errors() {
		$this->mock_http( array( 'openweathermap' => new WP_Error( 'http_request_failed', 'Timed out' ) ) );
		$this->assertWPError( $this->provider( 'Weather_Provider_OpenWeatherMap', array( 'api' => 'owm-key' ) )->get_conditions() );

		$this->mock_http(
			array(
				'openweathermap' => array(
					'code' => 401,
					'body' => array(
						'cod'     => 401,
						'message' => 'Invalid API key.',
					),
				),
			)
		);
		$provider = $this->provider( 'Weather_Provider_OpenWeatherMap', array( 'api' => 'owm-key' ) );
		$error    = $provider->get_conditions();
		$this->assertWPError( $error );
		$this->assertSame( 401, $error->get_error_data()['status'] );
		$this->assertStringNotContainsString( 'owm-key', $error->get_error_message() );
		$this->assertTrue( Sloc_Provider_Errors::is_actionable( $error, $provider ) );

		$this->mock_http( array( 'openweathermap' => array( 'body' => array( 'lat' => self::LAT ) ) ) );
		$this->assertSame( 'no_results', $this->provider( 'Weather_Provider_OpenWeatherMap', array( 'api' => 'owm-key' ) )->get_conditions()->get_error_code() );
	}

	/**
	 * OpenWeatherMap stations: the last hour's measurement over HTTPS, with wind as windspeed and winddegree (#348).
	 */
	public function test_openweathermap_station() {
		$this->mock_http(
			array(
				'api.openweathermap.org/data/3.0/measurements' => array(
					'body' => array(
						array(
							'type'       => 'h',
							'date'       => time() - 600,
							'station_id' => 'owm-station',
							'temp'       => array(
								'max'     => 21.4,
								'min'     => 19.2,
								'average' => 20.26,
							),
							'humidity'   => array( 'average' => 61.04 ),
							'wind'       => array(
								'deg'   => 225.33,
								'speed' => 3.46,
							),
							'pressure'   => array( 'average' => 1013.27 ),
						),
					),
				),
			)
		);
		$provider = $this->provider(
			'Weather_Provider_OpenWeatherMap',
			array(
				'api'        => 'owm-key',
				'cache_time' => 600,
			)
		);
		$provider->set( array( 'station_id' => 'owm-station' ) );
		$conditions = array_diff_key( $provider->get_conditions(), array( 'raw' => 1 ) );

		$request = $this->last_request();
		$this->assertStringStartsWith( 'https://', $request['url'] );
		$this->assertSame( 'owm-station', $request['query']['station_id'] );
		$this->assertSame( 'owm-key', $request['query']['appid'] );
		$this->assertSame( 'Simple Location for WordPress', $request['args']['user-agent'] );
		$this->assertSame(
			array(
				'temperature' => 20.3,
				'humidity'    => 61.0,
				'pressure'    => 1013.3,
				'windspeed'   => 3.5,
				'winddegree'  => 225.3,
			),
			$conditions
		);
		$this->assertArrayNotHasKey( 'wind', $conditions );

		$provider->get_conditions();
		$this->assertCount( 1, $this->http_requests, 'The second call is served from the cache.' );

		// No measurement in the last hour.
		$this->mock_http( array( 'api.openweathermap.org/data/3.0/measurements' => array( 'body' => '[]' ) ) );
		$provider = $this->provider( 'Weather_Provider_OpenWeatherMap', array( 'api' => 'owm-key' ) );
		$provider->set( array( 'station_id' => 'owm-station' ) );
		$this->assertSame( 'no_results', $provider->get_conditions()->get_error_code() );

		$this->mock_http( array( 'api.openweathermap.org/data/3.0/measurements' => array( 'code' => 401 ) ) );
		$provider = $this->provider( 'Weather_Provider_OpenWeatherMap', array( 'api' => 'owm-key' ) );
		$provider->set( array( 'station_id' => 'owm-station' ) );
		$this->assertSame( 401, $provider->get_conditions()->get_error_data()['status'] );
	}

	/**
	 * Cloudiness from any provider is a whole percentage.
	 */
	public function test_cloudiness_whole_percentage() {
		$fixture                            = $this->fixture( 'weather/pirateweather-forecast.json' );
		$fixture['currently']['cloudCover'] = 0.756;
		$this->mock_http( array( 'api.pirateweather.net/forecast/' => array( 'body' => $fixture ) ) );
		$this->assertSame( 76, $this->provider( 'Weather_Provider_PirateWeather', array( 'api' => 'pw-key' ) )->get_conditions()['cloudiness'] );

		$fixture                                    = $this->fixture( 'weather/visualcrossing-current.json' );
		$fixture['currentConditions']['cloudcover'] = 12.4;
		$this->mock_http( array( 'weather.visualcrossing.com' => array( 'body' => $fixture ) ) );
		$this->assertSame( 12, $this->provider( 'Weather_Provider_VisualCrossing', array( 'api' => 'vc-key' ) )->get_conditions()['cloudiness'] );
	}

	/**
	 * Pirate Weather: current conditions from the forecast endpoint, with SI units converted.
	 */
	public function test_pirateweather() {
		$fixture = $this->fixture( 'weather/pirateweather-forecast.json' );
		$this->mock_http( array( 'api.pirateweather.net/forecast/' => array( 'body' => $fixture ) ) );
		$conditions = $this->provider( 'Weather_Provider_PirateWeather', array( 'api' => 'pw-key' ) )->get_conditions();
		$request    = $this->last_request();
		$this->assertSame( 'https://api.pirateweather.net/forecast/pw-key/' . self::LAT . ',' . self::LNG, $request['base'] );
		$this->assertSame( 'si', $request['query']['units'] );
		$this->assertSame(
			array( 'units', 'exclude', 'lang' ),
			array_keys( $request['query'] ),
			'Request options are not sent as query parameters.'
		);
		$this->assertSame( 'Simple Location for WordPress', $this->http_requests[0]['args']['user-agent'] );

		$current = $fixture['currently'];
		$this->assertConditions(
			array(
				'temperature' => $current['temperature'],
				'humidity'    => $current['humidity'] * 100,
				'dewpoint'    => $current['dewPoint'],
				'pressure'    => $current['pressure'],
				'cloudiness'  => $current['cloudCover'] * 100,
				'windspeed'   => $current['windSpeed'],
				'windgust'    => $current['windGust'],
				'winddegree'  => $current['windBearing'],
				'uv'          => $current['uvIndex'],
				'visibility'  => $current['visibility'] * 1000,
				'summary'     => $current['summary'],
				'code'        => 803,
			),
			$conditions
		);

		$this->assertArrayNotHasKey( 'uvi', $conditions );

		// A past time requests that moment from the Time Machine host.
		$time = strtotime( '-2 days' );
		$this->provider( 'Weather_Provider_PirateWeather', array( 'api' => 'pw-key' ) )->get_conditions( $time );
		$this->assertSame( 'https://timemachine.pirateweather.net/forecast/pw-key/' . self::LAT . ',' . self::LNG . ',' . $time, $this->last_request()['base'] );

		// A time within the hour stays on the forecast host.
		$time = time() - 10 * MINUTE_IN_SECONDS;
		$this->provider( 'Weather_Provider_PirateWeather', array( 'api' => 'pw-key' ) )->get_conditions( $time );
		$this->assertSame( 'https://api.pirateweather.net/forecast/pw-key/' . self::LAT . ',' . self::LNG . ',' . $time, $this->last_request()['base'] );
	}

	/**
	 * Pirate Weather: the precipitation rate is stored as rain or snow by its type.
	 */
	public function test_pirateweather_precipitation() {
		$cases = array(
			'rain'    => array( 'rain', 1.2, array( 'rain' => 1.2 ) ),
			'snow'    => array( 'snow', 0.5321, array( 'snow' => 5.3 ) ),
			'sleet'   => array( 'sleet', 0.8, array( 'rain' => 0.8 ) ),
			'none'    => array( 'none', 0, array( 'rain' => 0.0 ) ),
			'no rate' => array( 'snow', null, array() ),
		);
		foreach ( $cases as $case => list( $type, $intensity, $expected ) ) {
			$fixture                                 = $this->fixture( 'weather/pirateweather-forecast.json' );
			$fixture['currently']['precipType']      = $type;
			$fixture['currently']['precipIntensity'] = $intensity;
			$this->mock_http( array( 'api.pirateweather.net/forecast/' => array( 'body' => $fixture ) ) );
			$conditions = $this->provider( 'Weather_Provider_PirateWeather', array( 'api' => 'pw-key' ) )->get_conditions();
			foreach ( array( 'rain', 'snow' ) as $key ) {
				if ( isset( $expected[ $key ] ) ) {
					$this->assertEqualsWithDelta( $expected[ $key ], $conditions[ $key ], 0.01, "$case: $key" );
				} else {
					$this->assertArrayNotHasKey( $key, $conditions, "$case: $key" );
				}
			}
		}
	}

	/**
	 * Pirate Weather: a request writes nothing to the PHP error log.
	 */
	public function test_pirateweather_no_logging() {
		$log  = wp_tempnam( 'sloc-log' );
		$prev = ini_set( 'error_log', $log ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		$this->mock_http( array( 'api.pirateweather.net/forecast/' => array( 'body' => $this->fixture( 'weather/pirateweather-forecast.json' ) ) ) );
		$this->provider( 'Weather_Provider_PirateWeather', array( 'api' => 'pw-key' ) )->get_conditions();
		ini_set( 'error_log', $prev ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		$this->assertSame( '', file_get_contents( $log ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		unlink( $log ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	/**
	 * Weatherbit goes through fetch_json() (#776): a refused request is an error without the key, and no data is no
	 * weather.
	 */
	public function test_weatherbit_errors() {
		$this->mock_http(
			array(
				'api.weatherbit.io/v2.0/current' => array(
					'code' => 403,
					'body' => array( 'error' => 'API key not valid, or not yet activated.' ),
				),
			)
		);
		$error = $this->provider( 'Weather_Provider_Weatherbit', array( 'api' => 'wb-secret' ) )->get_conditions();
		$this->assertWPError( $error );
		$this->assertSame( 403, $error->get_error_data()['status'] );
		$this->assertStringNotContainsString( 'wb-secret', $error->get_error_message() );
		$this->assertTrue( $this->last_request()['args']['reject_unsafe_urls'], 'Requested with wp_safe_remote_get().' );

		$this->mock_http(
			array(
				'api.weatherbit.io/v2.0/current' => array(
					'code' => 204,
					'body' => '',
				),
			)
		);
		$this->assertSame( array(), $this->provider( 'Weather_Provider_Weatherbit', array( 'api' => 'wb-key' ) )->get_conditions() );
	}

	/**
	 * NWS goes through fetch_json() (#776): a station without a recent observation, which NWS answers with 404, has no
	 * weather rather than an error.
	 */
	public function test_nws_fetch_json() {
		$this->mock_http(
			array(
				'api.weather.gov/stations/KXYZ/observations/latest' => array(
					'code' => 404,
					'body' => array( 'title' => 'Not Found' ),
				),
			)
		);
		$provider = new Weather_Provider_NWSUS();
		$provider->set( array( 'station_id' => 'KXYZ' ) );
		$this->assertSame( array(), $provider->get_conditions() );
		$this->assertTrue( $this->last_request()['args']['reject_unsafe_urls'], 'Requested with wp_safe_remote_get().' );

		$this->mock_http( array( 'api.weather.gov/stations/KXYZ/observations/latest' => new WP_Error( 'http_request_failed', 'Down' ) ) );
		$provider = new Weather_Provider_NWSUS();
		$provider->set( array( 'station_id' => 'KXYZ' ) );
		$this->assertWPError( $provider->get_conditions() );
	}

	/**
	 * Weatherbit: current conditions, with visibility converted from kilometers.
	 */
	public function test_weatherbit() {
		$fixture = $this->fixture( 'weather/weatherbit-current.json' );
		$this->mock_http( array( 'api.weatherbit.io/v2.0/current' => array( 'body' => $fixture ) ) );
		$conditions = $this->provider( 'Weather_Provider_Weatherbit', array( 'api' => 'wb-key' ) )->get_conditions();
		$request    = $this->last_request();
		$this->assertSame( 'wb-key', $request['query']['key'] );
		$this->assertSame( 'M', $request['query']['units'] );

		$data = $fixture['data'][0];
		$this->assertConditions(
			array(
				'temperature' => $data['temp'],
				'humidity'    => $data['rh'],
				'dewpoint'    => $data['dewpt'],
				'pressure'    => $data['pres'],
				'cloudiness'  => $data['clouds'],
				'windspeed'   => $data['wind_spd'],
				'winddegree'  => $data['wind_dir'],
				'visibility'  => $data['vis'] * 1000,
				'aqi'         => $data['aqi'],
				'uv'          => $data['uv'],
				'radiation'   => $data['solar_rad'],
				'summary'     => $data['weather']['description'],
				'code'        => $data['weather']['code'],
			),
			$conditions
		);
	}

	/**
	 * Weatherstack: current conditions from the Weatherstack API, with wind converted from km/h.
	 */
	public function test_weatherstack() {
		$fixture = $this->fixture( 'weather/weatherstack-current.json' );
		$this->mock_http( array( 'api.weatherstack.com/current' => array( 'body' => $fixture ) ) );
		$conditions = $this->provider( 'Weather_Provider_Weatherstack', array( 'api' => 'ws-key' ) )->get_conditions();
		$request    = $this->last_request();
		$this->assertCount( 1, $this->http_requests );
		$this->assertSame( 'https://api.weatherstack.com/current', $request['base'] );
		$this->assertSame( 'ws-key', $request['query']['access_key'] );
		$this->assertSame( self::LAT . ',' . self::LNG, $request['query']['query'] );
		$this->assertSame( 'm', $request['query']['units'] );

		$current = $fixture['current'];
		$this->assertConditions(
			array(
				'temperature' => $current['temperature'],
				'humidity'    => $current['humidity'],
				'pressure'    => $current['pressure'],
				'cloudiness'  => $current['cloudcover'],
				'windspeed'   => $current['wind_speed'] / 3.6,
				'winddegree'  => $current['wind_degree'],
				'rain'        => $current['precip'],
				'visibility'  => $current['visibility'] * 1000,
				'uv'          => $current['uv_index'],
				'summary'     => implode( ' ', $current['weather_descriptions'] ),
				'code'        => 801,
			),
			$conditions
		);
	}

	/**
	 * Weatherstack: a plan without HTTPS is retried over HTTP, and API errors are returned as errors.
	 */
	public function test_weatherstack_errors() {
		$fixture = $this->fixture( 'weather/weatherstack-current.json' );
		$this->mock_http(
			array(
				'https://api.weatherstack.com/' => array(
					'body' => array(
						'success' => false,
						'error'   => array(
							'code' => 105,
							'type' => 'https_access_restricted',
							'info' => 'Access Restricted - Your current Subscription Plan does not support HTTPS Encryption.',
						),
					),
				),
				'http://api.weatherstack.com/'  => array( 'body' => $fixture ),
			)
		);
		$conditions = $this->provider( 'Weather_Provider_Weatherstack', array( 'api' => 'ws-key' ) )->get_conditions();
		$this->assertCount( 2, $this->http_requests );
		$this->assertSame( 'http://api.weatherstack.com/current', $this->last_request()['base'] );
		$this->assertEqualsWithDelta( $fixture['current']['temperature'], $conditions['temperature'], 0.01 );

		$this->mock_http(
			array(
				'api.weatherstack.com' => array(
					'body' => array(
						'success' => false,
						'error'   => array(
							'code' => 101,
							'type' => 'invalid_access_key',
							'info' => 'You have not supplied a valid API Access Key.',
						),
					),
				),
			)
		);
		$error = $this->provider( 'Weather_Provider_Weatherstack', array( 'api' => 'bad-key' ) )->get_conditions();
		$this->assertWPError( $error );
		$this->assertSame( 'weatherstack_error', $error->get_error_code() );
		$this->assertSame( 101, $error->get_error_data()['code'] );
		$this->assertCount( 1, $this->http_requests );
	}

	/**
	 * HERE: Destination Weather API v3 observations, with units converted and the icon ID mapped to a code.
	 */
	public function test_here() {
		$fixture = $this->fixture( 'weather/here-observation.json' );
		$this->mock_http( array( 'weather.hereapi.com/v3/report' => array( 'body' => $fixture ) ) );
		$conditions = $this->provider( 'Weather_Provider_HERE', array( 'api' => 'here-key' ) )->get_conditions();
		$request    = $this->last_request();
		$this->assertSame( 'https://weather.hereapi.com/v3/report', $request['base'] );
		$this->assertSame( 'here-key', $request['query']['apiKey'] );
		$this->assertSame( 'observation', $request['query']['products'] );
		$this->assertSame( self::LAT . ',' . self::LNG, $request['query']['location'] );
		$this->assertSame( 'metric', $request['query']['units'] );

		$current = $fixture['places'][0]['observations'][0];
		$this->assertConditions(
			array(
				'temperature' => $current['temperature'],
				'dewpoint'    => $current['dewPoint'],
				'humidity'    => (float) $current['humidity'],
				'pressure'    => $current['barometerPressure'],
				'windspeed'   => $current['windSpeed'] / 3.6,
				'winddegree'  => $current['windDirection'],
				'rain'        => $current['precipitation1H'] * 10,
				'snow'        => 0,
				'visibility'  => $current['visibility'] * 1000,
				'uv'          => $current['uvIndex'],
				'summary'     => $current['description'],
				'code'        => 802,
			),
			$conditions
		);
		$this->assertIsFloat( $conditions['humidity'] );
	}

	/**
	 * HERE: an error response is returned, and a report without observations gives no conditions.
	 */
	public function test_here_errors() {
		$this->mock_http(
			array(
				'weather.hereapi.com' => array(
					'code' => 401,
					'body' => array(
						'error'             => 'Unauthorized',
						'error_description' => 'apiKey invalid. apiKey not found.',
					),
				),
			)
		);
		$this->assertWPError( $this->provider( 'Weather_Provider_HERE', array( 'api' => 'bad' ) )->get_conditions() );

		$this->mock_http( array( 'weather.hereapi.com' => array( 'body' => array( 'places' => array() ) ) ) );
		$this->assertSame( array(), $this->provider( 'Weather_Provider_HERE', array( 'api' => 'here-key' ) )->get_conditions() );
	}

	/**
	 * Visual Crossing: current conditions, with condition IDs mapped to summaries and codes.
	 */
	public function test_visualcrossing_current() {
		$fixture = $this->fixture( 'weather/visualcrossing-current.json' );
		$this->mock_http( array( 'weather.visualcrossing.com/VisualCrossingWebServices/rest/services/timeline/' => array( 'body' => $fixture ) ) );
		$conditions = $this->provider( 'Weather_Provider_VisualCrossing', array( 'api' => 'vc-key' ) )->get_conditions();
		$request    = $this->last_request();
		$this->assertStringEndsWith( '/timeline/' . self::LAT . ',' . self::LNG, $request['base'] );
		$this->assertSame( 'vc-key', $request['query']['key'] );
		$this->assertSame( 'metric', $request['query']['unitGroup'] );

		$current = $fixture['currentConditions'];
		$this->assertConditions(
			array(
				'temperature' => $current['temp'],
				'dewpoint'    => $current['dew'],
				'humidity'    => $current['humidity'],
				'pressure'    => $current['pressure'],
				'cloudiness'  => $current['cloudcover'],
				'windspeed'   => $current['windspeed'] / 3.6,
				'windgust'    => $current['windgust'] / 3.6,
				'winddegree'  => $current['winddir'],
				'visibility'  => $current['visibility'] * 1000,
				'radiation'   => $current['solarradiation'],
			),
			$conditions
		);
		$this->assertSame( 'Partly cloudy', $conditions['summary'] );
		$this->assertSame( 802, $conditions['code'] );
	}

	/**
	 * Returns the MET Norway fixture, with its first entry at the nearest hour and the next an hour later.
	 *
	 * @return array Fixture.
	 */
	protected function metno_fixture() {
		$fixture = $this->fixture( 'weather/metno-complete.json' );
		$hour    = (int) ( round( time() / HOUR_IN_SECONDS ) * HOUR_IN_SECONDS );
		foreach ( $fixture['properties']['timeseries'] as $index => $entry ) {
			$fixture['properties']['timeseries'][ $index ]['time'] = gmdate( 'Y-m-d\TH:i:s\Z', $hour + $index * HOUR_IN_SECONDS );
		}
		return $fixture;
	}

	/**
	 * MET Norway: the forecast for the nearest hour, asked for with four decimals, the altitude and the site's
	 * User-Agent (#153).
	 */
	public function test_metno_current() {
		$fixture = $this->metno_fixture();
		$this->mock_http( array( 'api.met.no' => array( 'body' => $fixture ) ) );
		$provider = $this->provider( 'Weather_Provider_Metno', array( 'cache_time' => 600 ) );
		$provider->set( 39.95241234, -75.16361234, 12.6 );
		$conditions = $provider->get_conditions();

		$request = $this->last_request();
		$this->assertSame( 'https://api.met.no/weatherapi/locationforecast/2.0/complete', $request['base'] );
		$this->assertSame( '39.9524', $request['query']['lat'] );
		$this->assertSame( '-75.1636', $request['query']['lon'] );
		$this->assertSame( '13', $request['query']['altitude'] );
		$this->assertSame( 'Simple Location for WordPress (' . home_url( '/' ) . ')', $request['args']['headers']['User-Agent'] );

		$now  = $fixture['properties']['timeseries'][0]['data'];
		$here = $now['instant']['details'];
		$this->assertConditions(
			array(
				'temperature' => $here['air_temperature'],
				'dewpoint'    => $here['dew_point_temperature'],
				'humidity'    => $here['relative_humidity'],
				'pressure'    => $here['air_pressure_at_sea_level'],
				'cloudiness'  => $here['cloud_area_fraction'],
				'windspeed'   => $here['wind_speed'],
				'windgust'    => $here['wind_speed_of_gust'],
				'winddegree'  => $here['wind_from_direction'],
				'uv'          => $here['ultraviolet_index_clear_sky'],
				'rain'        => $now['next_1_hours']['details']['precipitation_amount'],
				'code'        => 520,
				'summary'     => 'Light rain showers',
			),
			$conditions
		);
		$this->assertSame( 62, $conditions['cloudiness'] );

		$provider->get_conditions();
		$this->assertCount( 1, $this->http_requests, 'The second call is served from the cache.' );
	}

	/**
	 * MET Norway: only current weather; refused requests and responses without a forecast are errors (#153).
	 */
	public function test_metno_errors() {
		$this->mock_http( array() );
		$provider = $this->provider( 'Weather_Provider_Metno' );
		$this->assertSame( 'historic_unsupported', $provider->get_conditions( strtotime( '-2 days' ) )->get_error_code() );
		$this->assertTrue( $provider->supports_time() );
		$this->assertFalse( $provider->supports_time( strtotime( '+2 days' ) ) );
		$this->assertSame( array(), $this->http_requests );

		$this->mock_http( array( 'api.met.no' => array( 'code' => 403 ) ) );
		$this->assertSame( 403, $this->provider( 'Weather_Provider_Metno' )->get_conditions()->get_error_data()['status'] );

		$this->mock_http( array( 'api.met.no' => array( 'body' => array( 'type' => 'Feature' ) ) ) );
		$this->assertSame( 'no_results', $this->provider( 'Weather_Provider_Metno' )->get_conditions()->get_error_code() );
	}

	/**
	 * Environment Canada: the conditions of the nearest city with a reading, in metric units, in the site's language,
	 * cached (#154).
	 */
	public function test_eccc_current() {
		$fixture = $this->fixture( 'weather/eccc-citypage.json' );
		$this->mock_http( array( 'api.weather.gc.ca' => array( 'body' => $fixture ) ) );
		$provider = $this->provider( 'Weather_Provider_ECCC', array( 'cache_time' => 600 ) );
		// Parliament Hill, Ottawa.
		$provider->set( 45.4236, -75.7009 );
		$this->assertTrue( $provider->covers() );
		$conditions = $provider->get_conditions();

		$request = $this->last_request();
		$this->assertSame( Weather_Provider_ECCC::ENDPOINT, $request['base'] );
		$this->assertSame( '-75.9509,45.1736,-75.4509,45.6736', $request['query']['bbox'] );
		$this->assertSame( 'en', $request['query']['lang'] );

		// Ottawa, not Gatineau, nor the nearer city without a reading.
		$ottawa = $fixture['features'][0]['properties']['currentConditions'];
		$this->assertSame( 'on-118', $fixture['features'][0]['properties']['identifier'] );
		$this->assertConditions(
			array(
				'temperature' => $ottawa['temperature']['value']['en'],
				'humidity'    => $ottawa['relativeHumidity']['value']['en'],
				'dewpoint'    => $ottawa['dewpoint']['value']['en'],
				'windchill'   => $ottawa['windChill']['value']['en'],
				'pressure'    => round( $ottawa['pressure']['value']['en'] * 10, 1 ),
				'windspeed'   => round( $ottawa['wind']['speed']['value']['en'] / 3.6, 2 ),
				'windgust'    => round( $ottawa['wind']['gust']['value']['en'] / 3.6, 2 ),
				'winddegree'  => $ottawa['wind']['bearing']['value']['en'],
				'code'        => Weather_Provider_ECCC::ICON_CODES[ $ottawa['iconCode']['value'] ],
				'summary'     => $ottawa['condition']['en'],
			),
			$conditions
		);
		$this->assertSame( 'eccc', $conditions['provider'] );

		$provider->get_conditions();
		$this->assertCount( 1, $this->http_requests, 'The second call is served from the cache.' );
	}

	/**
	 * Environment Canada: only in Canada and for now; a wider search without a city near; errors (#154).
	 */
	public function test_eccc_search_and_errors() {
		$provider = $this->provider( 'Weather_Provider_ECCC' );
		$this->assertFalse( $provider->covers(), 'Philadelphia is not in Canada.' );
		$this->mock_http( array() );
		$this->assertSame( 'historic_unsupported', $provider->get_conditions( strtotime( '-2 days' ) )->get_error_code() );
		$this->assertSame( array(), $this->http_requests );

		// Nothing in the near box, then the city in the wider one; one too far away isn't used.
		$fixture  = $this->fixture( 'weather/eccc-citypage.json' );
		$provider = $this->provider( 'Weather_Provider_ECCC' );
		$provider->set( 45.9, -75.7 );
		$responses = array( array( 'features' => array() ), $fixture );
		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			function () use ( &$responses ) {
				return array(
					'headers'  => array( 'content-type' => 'application/json' ),
					'body'     => wp_json_encode( array_shift( $responses ) ),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			5
		);
		$conditions = $provider->get_conditions();
		$this->assertSame( $fixture['features'][1]['properties']['currentConditions']['temperature']['value']['en'], $conditions['temperature'], 'Gatineau, the nearest with a reading.' );

		$far = $this->provider( 'Weather_Provider_ECCC' );
		$far->set( 47.5, -75.7 );
		$responses = array( $fixture, $fixture );
		$this->assertSame( 'no_results', $far->get_conditions()->get_error_code(), 'Over 100 km away.' );
		remove_all_filters( 'pre_http_request', 5 );

		$this->mock_http( array( 'api.weather.gc.ca' => array( 'code' => 500 ) ) );
		$broken = $this->provider( 'Weather_Provider_ECCC' );
		$broken->set( 45.42, -75.7 );
		$this->assertWPError( $broken->get_conditions() );
	}

	/**
	 * Environment Canada's values are in English and French; French sites get French text, and its icon codes are all
	 * known conditions (#154).
	 */
	public function test_eccc_language_and_codes() {
		$this->assertSame( 'en', Weather_Provider_ECCC::language() );
		$this->assertSame(
			8.8,
			Weather_Provider_ECCC::value(
				array(
					'value' => array(
						'en' => 8.8,
						'fr' => 8.8,
					),
				)
			)
		);
		$this->assertSame(
			'Partiellement nuageux',
			Weather_Provider_ECCC::value(
				array(
					'en' => 'Partly Cloudy',
					'fr' => 'Partiellement nuageux',
				),
				'fr'
			)
		);
		$this->assertNull( Weather_Provider_ECCC::value( 'x' ) );
		$this->assertSame( 2, Weather_Provider_ECCC::value( array( 'value' => 2 ) ) );
		add_filter( 'locale', fn() => 'fr_CA' );
		$this->assertSame( 'fr', Weather_Provider_ECCC::language() );
		remove_all_filters( 'locale' );

		$known = Weather_Provider_ECCC::weather_condition_codes( null );
		foreach ( Weather_Provider_ECCC::ICON_CODES as $icon => $code ) {
			$this->assertArrayHasKey( (string) $code, $known, "Icon $icon" );
		}
		$converted = $this->provider( 'Weather_Provider_ECCC' )->convert_data(
			array(
				'iconCode'   => array( 'value' => 99 ),
				'visibility' => array( 'value' => array( 'en' => 24.1 ) ),
				'humidex'    => array( 'value' => array( 'en' => 31 ) ),
			)
		);
		$this->assertArrayNotHasKey( 'code', $converted, 'An unknown icon.' );
		$this->assertEquals( 24100, $converted['visibility'] );
		$this->assertEquals( 31, $converted['heatindex'] );
	}

	/**
	 * MET Norway's weather symbols are described and given condition codes, whatever the time of day (#153).
	 */
	public function test_metno_symbols() {
		$this->assertSame( 'clearsky', Weather_Provider_Metno::symbol( 'clearsky_polartwilight' ) );
		$this->assertSame( '', Weather_Provider_Metno::symbol( null ) );
		$this->assertSame( 800, Weather_Provider_Metno::code_map( 'clearsky' ) );
		$this->assertSame( 804, Weather_Provider_Metno::code_map( 'cloudy' ) );
		$this->assertSame( 200, Weather_Provider_Metno::code_map( 'lightrainshowersandthunder' ) );
		$this->assertSame( 201, Weather_Provider_Metno::code_map( 'rainandthunder' ) );
		$this->assertSame( 202, Weather_Provider_Metno::code_map( 'heavyrainandthunder' ) );
		$this->assertSame( 211, Weather_Provider_Metno::code_map( 'lightssnowshowersandthunder' ) );
		$this->assertSame( '', Weather_Provider_Metno::code_map( 'unknownandthunder' ) );
		$this->assertSame( 'Heavy snow showers', Weather_Provider_Metno::symbol_summary( 'heavysnowshowers' ) );

		// Every symbol has a code, and snow is counted as snow.
		$reflection = new ReflectionMethod( 'Weather_Provider_Metno', 'symbols' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}
		foreach ( array_keys( $reflection->invoke( null ) ) as $symbol ) {
			$this->assertIsInt( Weather_Provider_Metno::code_map( $symbol ), $symbol );
		}
		$snow = ( new Weather_Provider_Metno() )->convert_data(
			array(
				'instant'      => array( 'details' => array( 'air_temperature' => -2 ) ),
				'next_1_hours' => array(
					'summary' => array( 'symbol_code' => 'snow_night' ),
					'details' => array( 'precipitation_amount' => 1.2 ),
				),
			)
		);
		$this->assertSame( 1.2, $snow['snow'] );
		$this->assertArrayNotHasKey( 'rain', $snow );
	}

	/**
	 * Open-Meteo: current conditions from the free forecast API, without a key, with wind in meters per second.
	 */
	public function test_openmeteo_current() {
		$fixture = $this->fixture( 'weather/openmeteo-current.json' );
		$this->mock_http( array( 'api.open-meteo.com/v1/forecast' => array( 'body' => $fixture ) ) );
		$conditions = $this->provider( 'Weather_Provider_OpenMeteo' )->get_conditions();

		$request = $this->last_request();
		$this->assertSame( 'https://api.open-meteo.com/v1/forecast', $request['base'] );
		$this->assertEquals( self::LAT, $request['query']['latitude'] );
		$this->assertEquals( self::LNG, $request['query']['longitude'] );
		$this->assertSame( 'ms', $request['query']['wind_speed_unit'] );
		$this->assertSame( implode( ',', Weather_Provider_OpenMeteo::VARIABLES ), $request['query']['current'] );
		$this->assertArrayNotHasKey( 'apikey', $request['query'] );

		$current = $fixture['current'];
		$this->assertConditions(
			array(
				'temperature' => $current['temperature_2m'],
				'dewpoint'    => $current['dew_point_2m'],
				'humidity'    => $current['relative_humidity_2m'],
				'pressure'    => $current['pressure_msl'],
				'cloudiness'  => $current['cloud_cover'],
				'windspeed'   => $current['wind_speed_10m'],
				'windgust'    => $current['wind_gusts_10m'],
				'winddegree'  => $current['wind_direction_10m'],
				'visibility'  => $current['visibility'],
				'rain'        => $current['rain'] + $current['showers'],
				'snow'        => $current['snowfall'] * 10,
				'code'        => Weather_Provider_OpenMeteo::code_map( $current['weather_code'] ),
				'summary'     => Weather_Provider_OpenMeteo::wmo_summary( $current['weather_code'] ),
			),
			$conditions
		);
	}

	/**
	 * Open-Meteo: a time more than ARCHIVE_AFTER days ago uses the Historical Weather API for its UTC day, and the
	 * nearest hour. Values the archive doesn't have are left out.
	 */
	public function test_openmeteo_archive() {
		$fixture = $this->fixture( 'weather/openmeteo-archive.json' );
		$this->mock_http( array( 'archive-api.open-meteo.com/v1/archive' => array( 'body' => $fixture ) ) );
		// 14:20 in Philadelphia is 18:20 UTC, nearest to the 18:00 UTC hour.
		$time       = strtotime( '2025-07-01T14:20:00-04:00' );
		$conditions = $this->provider( 'Weather_Provider_OpenMeteo' )->get_conditions( $time );

		$request = $this->last_request();
		$this->assertSame( 'https://archive-api.open-meteo.com/v1/archive', $request['base'] );
		$this->assertSame( '2025-07-01', $request['query']['start_date'] );
		$this->assertSame( '2025-07-01', $request['query']['end_date'] );
		$this->assertSame( implode( ',', Weather_Provider_OpenMeteo::VARIABLES ), $request['query']['hourly'] );

		$index = array_search( strtotime( '2025-07-01T18:00:00Z' ), $fixture['hourly']['time'], true );
		$this->assertIsInt( $index );
		$this->assertSame( (float) $fixture['hourly']['temperature_2m'][ $index ], $conditions['temperature'] );
		$this->assertSame( (float) $fixture['hourly']['wind_speed_10m'][ $index ], $conditions['windspeed'] );
		$this->assertSame( Weather_Provider_OpenMeteo::code_map( $fixture['hourly']['weather_code'][ $index ] ), $conditions['code'] );
		$this->assertArrayNotHasKey( 'visibility', $conditions );
		$this->assertArrayNotHasKey( 'uv', $conditions );
	}

	/**
	 * Open-Meteo: a recent past time uses the forecast API's hourly data, which the archive doesn't have yet.
	 */
	public function test_openmeteo_recent_past() {
		$hour    = (int) ( floor( strtotime( '-3 days' ) / HOUR_IN_SECONDS ) * HOUR_IN_SECONDS );
		$hourly  = array( 'time' => array( $hour - HOUR_IN_SECONDS, $hour, $hour + HOUR_IN_SECONDS ) );
		$hourly += array(
			'temperature_2m' => array( 10.0, 11.5, 12.0 ),
			'rain'           => array( 0.0, 0.4, 0.0 ),
			'showers'        => array( 0.0, 0.3, 0.0 ),
			'snowfall'       => array( 0.0, 0.7, 0.0 ),
			'weather_code'   => array( 0, 61, 3 ),
		);
		$this->mock_http( array( 'api.open-meteo.com/v1/forecast' => array( 'body' => array( 'hourly' => $hourly ) ) ) );
		$conditions = $this->provider( 'Weather_Provider_OpenMeteo' )->get_conditions( $hour + 600 );

		$request = $this->last_request();
		$this->assertSame( 'https://api.open-meteo.com/v1/forecast', $request['base'] );
		$this->assertSame( gmdate( 'Y-m-d', $hour ), $request['query']['start_date'] );
		$this->assertArrayHasKey( 'hourly', $request['query'] );
		$this->assertSame( 11.5, $conditions['temperature'] );
		$this->assertSame( 0.7, $conditions['rain'] );
		$this->assertSame( 7.0, $conditions['snow'] );
		$this->assertSame( 500, $conditions['code'] );
		$this->assertSame( 'Slight rain', $conditions['summary'] );

		// An hour the response doesn't have is an error, not empty weather.
		$this->assertSame( 'no_results', $this->provider( 'Weather_Provider_OpenMeteo' )->get_conditions( $hour + 5 * HOUR_IN_SECONDS )->get_error_code() );
	}

	/**
	 * Open-Meteo: a commercial key uses the customer hosts, and a self-hosted server replaces Open-Meteo's hosts.
	 */
	public function test_openmeteo_hosts() {
		$current = $this->fixture( 'weather/openmeteo-current.json' );
		$archive = $this->fixture( 'weather/openmeteo-archive.json' );
		$past    = strtotime( '2025-07-01T14:00:00-04:00' );
		$this->mock_http(
			array(
				'/v1/forecast' => array( 'body' => $current ),
				'/v1/archive'  => array( 'body' => $archive ),
			)
		);

		$provider = $this->provider( 'Weather_Provider_OpenMeteo', array( 'api' => 'om-key' ) );
		$provider->get_conditions();
		$this->assertSame( 'https://customer-api.open-meteo.com/v1/forecast', $this->last_request()['base'] );
		$this->assertSame( 'om-key', $this->last_request()['query']['apikey'] );
		$provider->get_conditions( $past );
		$this->assertSame( 'https://customer-archive-api.open-meteo.com/v1/archive', $this->last_request()['base'] );

		update_option( 'sloc_openmeteo_url', 'https://weather.example.com/' );
		$provider = $this->provider( 'Weather_Provider_OpenMeteo', array( 'api' => '' ) );
		$provider->get_conditions();
		$this->assertSame( 'https://weather.example.com/v1/forecast', $this->last_request()['base'] );
		$provider->get_conditions( $past );
		$this->assertSame( 'https://weather.example.com/v1/archive', $this->last_request()['base'] );

		$this->assertSame( '', Weather_Provider_OpenMeteo::sanitize_url( 'ftp://weather.example.com' ) );
		$this->assertSame( '', Weather_Provider_OpenMeteo::sanitize_url( null ) );
	}

	/**
	 * Open-Meteo: a refused request carries Open-Meteo's reason.
	 */
	public function test_openmeteo_errors() {
		$fixture = $this->fixture( 'weather/openmeteo-error.json' );
		$this->mock_http(
			array(
				'api.open-meteo.com' => array(
					'code' => 400,
					'body' => $fixture,
				),
			)
		);
		$error = $this->provider( 'Weather_Provider_OpenMeteo' )->get_conditions();
		$this->assertWPError( $error );
		$this->assertSame( 'openmeteo_error', $error->get_error_code() );
		$this->assertSame( $fixture['reason'], $error->get_error_message() );
		$this->assertSame( array( 'status' => 400 ), $error->get_error_data() );

		$this->mock_http(
			array(
				'api.open-meteo.com' => array(
					'code' => 502,
					'body' => 'Bad Gateway',
				),
			)
		);
		$this->assertSame( 'invalid_response', $this->provider( 'Weather_Provider_OpenMeteo' )->get_conditions()->get_error_code() );

		$this->mock_http( array( 'api.open-meteo.com' => array( 'body' => array( 'latitude' => self::LAT ) ) ) );
		$this->assertSame( 'no_results', $this->provider( 'Weather_Provider_OpenMeteo' )->get_conditions()->get_error_code() );
	}

	/**
	 * Open-Meteo: every WMO code it documents has a description and a known condition code.
	 */
	public function test_openmeteo_codes() {
		foreach ( array( 0, 1, 2, 3, 45, 48, 51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 71, 73, 75, 77, 80, 81, 82, 85, 86, 95, 96, 99 ) as $wmo ) {
			$this->assertNotSame( '', Weather_Provider_OpenMeteo::wmo_summary( $wmo ), $wmo );
			$code = Weather_Provider_OpenMeteo::code_map( $wmo );
			$this->assertNotSame( '', Weather_Provider_Sloc_Test::weather_condition_codes( $code ), $wmo );
			$this->assertNotSame( '', Weather_Provider_Sloc_Test::weather_condition_icons( $code ), $wmo );
		}
		$this->assertSame( '', Weather_Provider_OpenMeteo::code_map( 42 ) );
		$this->assertSame( '', Weather_Provider_OpenMeteo::wmo_summary( 42 ) );
	}

	/**
	 * Open-Meteo registers its optional key and server URL, with descriptions.
	 */
	public function test_openmeteo_settings() {
		global $wp_settings_fields;
		require_once ABSPATH . 'wp-admin/includes/template.php';

		Weather_Provider_OpenMeteo::init();
		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'sloc_openmeteo_api', $settings );
		$this->assertSame( 'https://weather.example.com', sanitize_option( 'sloc_openmeteo_url', ' https://weather.example.com ' ) );

		Weather_Provider_OpenMeteo::admin_init();
		$fields = $wp_settings_fields['sloc_api']['sloc_api'];
		$this->assertSame( 'password', $fields['sloc_openmeteo_api']['args']['type'] );
		$this->assertStringContainsString( 'commercial', $fields['sloc_openmeteo_api']['args']['description'] );
		$this->assertSame( 'url', $fields['sloc_openmeteo_url']['args']['type'] );
		$this->assertFalse( $this->provider( 'Weather_Provider_OpenMeteo' )->is_station() );
	}

	/**
	 * Heat index values from the US National Weather Service's heat index chart.
	 *
	 * @return array Temperature in Fahrenheit, relative humidity, and the chart's heat index in Fahrenheit.
	 */
	public function data_heat_index_chart() {
		return array(
			'80 °F, 40 %'                => array( 80, 40, 80 ),
			'90 °F, 60 %'                => array( 90, 60, 100 ),
			'96 °F, 65 %'                => array( 96, 65, 121 ),
			'100 °F, 40 %'               => array( 100, 40, 109 ),
			'84 °F, 90 % (humid air)'    => array( 84, 90, 98 ),
			'104 °F, 10 % (dry air)'     => array( 104, 10, 98 ),
			'86 °F, 40 % (simple index)' => array( 86, 40, 85 ),
		);
	}

	/**
	 * The heat index matches the National Weather Service's chart (#652).
	 *
	 * @dataProvider data_heat_index_chart
	 *
	 * @param int $fahrenheit Temperature in Fahrenheit.
	 * @param int $humidity   Relative humidity in percent.
	 * @param int $expected   Heat index in Fahrenheit, from the chart.
	 */
	public function test_heat_index_chart( $fahrenheit, $humidity, $expected ) {
		$index = Weather_Provider_Sloc_Test::heat_index( Weather_Provider_Sloc_Test::fahrenheit_to_celsius( $fahrenheit ), $humidity );
		$this->assertIsFloat( $index );
		$this->assertEqualsWithDelta( $expected, Weather_Provider_Sloc_Test::celsius_to_fahrenheit( $index ), 0.6 );
	}

	/**
	 * Wind chill values from the National Weather Service's wind chill chart.
	 *
	 * @return array Temperature in Fahrenheit, wind speed in miles per hour, and the chart's wind chill in Fahrenheit.
	 */
	public function data_wind_chill_chart() {
		return array(
			'40 °F, 5 mph'   => array( 40, 5, 36 ),
			'30 °F, 10 mph'  => array( 30, 10, 21 ),
			'0 °F, 15 mph'   => array( 0, 15, -19 ),
			'-10 °F, 30 mph' => array( -10, 30, -39 ),
		);
	}

	/**
	 * The wind chill matches the National Weather Service's chart (#652).
	 *
	 * @dataProvider data_wind_chill_chart
	 *
	 * @param int $fahrenheit Temperature in Fahrenheit.
	 * @param int $mph        Wind speed in miles per hour.
	 * @param int $expected   Wind chill in Fahrenheit, from the chart.
	 */
	public function test_wind_chill_chart( $fahrenheit, $mph, $expected ) {
		$chill = Weather_Provider_Sloc_Test::wind_chill( Weather_Provider_Sloc_Test::fahrenheit_to_celsius( $fahrenheit ), $mph * 0.44704 );
		$this->assertIsFloat( $chill );
		$this->assertEqualsWithDelta( $expected, Weather_Provider_Sloc_Test::celsius_to_fahrenheit( $chill ), 0.6 );
	}

	/**
	 * The Magnus formula gives the dew point from the humidity and back (#655).
	 */
	public function test_dew_point_and_humidity() {
		$this->assertSame( 9.3, Weather_Provider_Sloc_Test::dew_point( 20, 50 ) );
		$this->assertSame( -12.8, Weather_Provider_Sloc_Test::dew_point( -10, 80 ) );
		$this->assertSame( 30.0, Weather_Provider_Sloc_Test::dew_point( 30, 100 ) );
		$this->assertSame( 55.1, Weather_Provider_Sloc_Test::relative_humidity( 30, 20 ) );
		$this->assertSame( 100.0, Weather_Provider_Sloc_Test::relative_humidity( 15, 15 ) );
		// A dew point above the temperature means saturated air.
		$this->assertSame( 100.0, Weather_Provider_Sloc_Test::relative_humidity( 15, 16 ) );
		$this->assertEqualsWithDelta( 37, Weather_Provider_Sloc_Test::relative_humidity( 25, Weather_Provider_Sloc_Test::dew_point( 25, 37 ) ), 0.3 );

		$this->assertNull( Weather_Provider_Sloc_Test::dew_point( 20, 0 ) );
		$this->assertNull( Weather_Provider_Sloc_Test::dew_point( null, 50 ) );
		$this->assertNull( Weather_Provider_Sloc_Test::relative_humidity( 20, 'wet' ) );
		// Humidity over 100 % is treated as 100 %.
		$this->assertSame( 20.0, Weather_Provider_Sloc_Test::dew_point( 20, 120 ) );
	}

	/**
	 * Clear-sky radiation in Philadelphia: about 950 W/m² at a summer noon, half that in winter, none at night (#204).
	 */
	public function test_clear_sky_radiation() {
		$calc = new Astronomical_Calculator( 39.9524, -75.1636, 12 );
		$this->assertEqualsWithDelta( 950, Weather_Provider::clear_sky_radiation( $calc, strtotime( '2025-06-21T17:00:00Z' ) ), 10 );
		$this->assertEqualsWithDelta( 474, Weather_Provider::clear_sky_radiation( $calc, strtotime( '2025-12-21T17:00:00Z' ) ), 10 );
		$this->assertEquals( 0, Weather_Provider::clear_sky_radiation( $calc, strtotime( '2025-06-21T03:00:00Z' ) ) );
	}

	/**
	 * The sky is estimated from the radiation, or illuminance, measured against a clear sky's; not with the sun low,
	 * and not without a measurement (#204).
	 */
	public function test_condition_from_sunlight() {
		$calc = new Astronomical_Calculator( 39.9524, -75.1636, 12 );
		$noon = strtotime( '2025-06-21T17:00:00Z' );
		$this->assertSame( 800, Weather_Provider::condition_from_sunlight( 900, null, $calc, $noon ) );
		$this->assertSame( 800, Weather_Provider::condition_from_sunlight( 1100, null, $calc, $noon ), 'Clouds can add reflected light.' );
		$this->assertSame( 801, Weather_Provider::condition_from_sunlight( 700, null, $calc, $noon ) );
		$this->assertSame( 802, Weather_Provider::condition_from_sunlight( 550, null, $calc, $noon ) );
		$this->assertSame( 803, Weather_Provider::condition_from_sunlight( 400, null, $calc, $noon ) );
		$this->assertSame( 804, Weather_Provider::condition_from_sunlight( 150, null, $calc, $noon ) );
		$this->assertSame( 800, Weather_Provider::condition_from_sunlight( null, 110000, $calc, $noon ), 'From lux, without radiation.' );
		$this->assertSame( 804, Weather_Provider::condition_from_sunlight( '150', 110000, $calc, new DateTime( '@' . $noon ) ), 'The radiation first.' );
		$this->assertNull( Weather_Provider::condition_from_sunlight( 100, null, $calc, strtotime( '2025-06-21T10:15:00Z' ) ), 'The sun is too low.' );
		$this->assertNull( Weather_Provider::condition_from_sunlight( null, null, $calc, $noon ) );
		$this->assertNull( Weather_Provider::condition_from_sunlight( -5, null, $calc, $noon ) );

		add_filter( 'sloc_condition_from_sunlight', fn( $code, $index ) => $index > 0.9 ? 800 : 803, 10, 2 );
		$this->assertSame( 803, Weather_Provider::condition_from_sunlight( 800, null, $calc, $noon ) );
	}

	/**
	 * Conditions without a condition get one estimated from the sunlight by day; a provider's own is kept (#204).
	 */
	public function test_condition_from_sunlight_in_conditions() {
		$provider = $this->provider( 'Weather_Provider_Sloc_Test' );
		$noon     = strtotime( '2025-06-21T17:00:00Z' );
		$this->assertSame( 802, $provider->extra_data( array( 'radiation' => 550 ), $noon )['code'] );
		$this->assertSame(
			500,
			$provider->extra_data(
				array(
					'radiation' => 550,
					'code'      => 500,
				),
				$noon
			)['code']
		);
		$this->assertArrayNotHasKey( 'code', $provider->extra_data( array( 'radiation' => 0 ), strtotime( '2025-06-21T04:00:00Z' ) ), 'Not at night.' );
		$this->assertArrayNotHasKey( 'code', $provider->extra_data( array( 'temperature' => 20 ), $noon ) );
	}

	/**
	 * Conditions get a dew point or humidity when only the other is given, which also gives a heat index, and a
	 * provider's own values are kept.
	 */
	public function test_dew_point_and_humidity_in_conditions() {
		$provider = $this->provider( 'Weather_Provider_Sloc_Test' );

		$conditions = $provider->extra_data(
			array(
				'temperature' => 20,
				'humidity'    => 50,
			)
		);
		$this->assertSame( 9.3, $conditions['dewpoint'] );

		// A station with only the dew point gets the humidity, and from it a heat index.
		$conditions = $provider->extra_data(
			array(
				'temperature' => 32,
				'dewpoint'    => 24,
			)
		);
		$this->assertSame( Weather_Provider_Sloc_Test::relative_humidity( 32, 24 ), $conditions['humidity'] );
		$this->assertSame( Weather_Provider_Sloc_Test::heat_index( 32, $conditions['humidity'] ), $conditions['heatindex'] );

		$conditions = $provider->extra_data(
			array(
				'temperature' => 20,
				'humidity'    => 50,
				'dewpoint'    => 9.0,
			)
		);
		$this->assertSame( 9.0, $conditions['dewpoint'] );
		$this->assertSame( 50, $conditions['humidity'] );

		$conditions = $provider->extra_data( array( 'temperature' => 20 ) );
		$this->assertArrayNotHasKey( 'dewpoint', $conditions );
		$this->assertArrayNotHasKey( 'humidity', $conditions );
	}

	/**
	 * Outside their ranges, or without their inputs, the wind chill and heat index aren't calculated.
	 */
	public function test_feels_like_out_of_range() {
		$this->assertNull( Weather_Provider_Sloc_Test::heat_index( 26, 90 ), 'Below 80 °F.' );
		$this->assertNull( Weather_Provider_Sloc_Test::heat_index( 30, null ) );
		$this->assertNull( Weather_Provider_Sloc_Test::heat_index( 'hot', 50 ) );
		$this->assertNull( Weather_Provider_Sloc_Test::wind_chill( 10.5, 10 ), 'Above 10 °C.' );
		$this->assertNull( Weather_Provider_Sloc_Test::wind_chill( -5, 1.3 ), 'Wind of 4.8 km/h or less.' );
		$this->assertNull( Weather_Provider_Sloc_Test::wind_chill( null, 10 ) );
		// Humidity over 100 % is treated as 100 %.
		$this->assertSame( Weather_Provider_Sloc_Test::heat_index( 30, 100 ), Weather_Provider_Sloc_Test::heat_index( 30, 140 ) );
	}

	/**
	 * Conditions get a calculated wind chill or heat index when they apply and the provider didn't give one, and a
	 * provider's own value is kept.
	 */
	public function test_feels_like_in_conditions() {
		$provider = $this->provider( 'Weather_Provider_Sloc_Test' );

		$cold = $provider->extra_data(
			array(
				'temperature' => -5,
				'windspeed'   => 8,
				'humidity'    => 80,
			)
		);
		$this->assertSame( Weather_Provider_Sloc_Test::wind_chill( -5, 8 ), $cold['windchill'] );
		$this->assertArrayNotHasKey( 'heatindex', $cold );

		$hot = $provider->extra_data(
			array(
				'temperature' => 32,
				'windspeed'   => 3,
				'humidity'    => 60,
			)
		);
		$this->assertSame( Weather_Provider_Sloc_Test::heat_index( 32, 60 ), $hot['heatindex'] );
		$this->assertArrayNotHasKey( 'windchill', $hot );

		$mild = $provider->extra_data(
			array(
				'temperature' => 18,
				'windspeed'   => 5,
				'humidity'    => 50,
			)
		);
		$this->assertArrayNotHasKey( 'windchill', $mild );
		$this->assertArrayNotHasKey( 'heatindex', $mild );

		$given = $provider->extra_data(
			array(
				'temperature' => -5,
				'windspeed'   => 8,
				'windchill'   => -9.9,
			)
		);
		$this->assertSame( -9.9, $given['windchill'] );
		$this->assertSame(
			0,
			$provider->extra_data(
				array(
					'temperature' => -5,
					'windspeed'   => 8,
					'windchill'   => 0,
				)
			)['windchill']
		);
	}

	/**
	 * Providers without their own heat index now have one, such as Open-Meteo on a hot, humid afternoon.
	 */
	public function test_feels_like_from_provider() {
		$fixture = $this->fixture( 'weather/openmeteo-archive.json' );
		$this->mock_http( array( 'archive-api.open-meteo.com' => array( 'body' => $fixture ) ) );
		$conditions = $this->provider( 'Weather_Provider_OpenMeteo' )->get_conditions( strtotime( '2025-07-01T14:00:00-04:00' ) );
		$this->assertSame( Weather_Provider_Sloc_Test::heat_index( $conditions['temperature'], $conditions['humidity'] ), $conditions['heatindex'] );
		$this->assertGreaterThan( $conditions['temperature'], $conditions['heatindex'] );
	}

	/**
	 * Visual Crossing: readings of 0 are kept, and missing readings are left out instead of becoming 0.
	 */
	public function test_visualcrossing_zero_and_missing_values() {
		$fixture = $this->fixture( 'weather/visualcrossing-current.json' );
		foreach ( array( 'temp', 'windspeed', 'precip', 'cloudcover', 'visibility' ) as $key ) {
			$fixture['currentConditions'][ $key ] = 0;
		}
		unset( $fixture['currentConditions']['windgust'], $fixture['currentConditions']['snow'] );
		$this->mock_http( array( 'weather.visualcrossing.com' => array( 'body' => $fixture ) ) );
		$conditions = $this->provider( 'Weather_Provider_VisualCrossing', array( 'api' => 'vc-key' ) )->get_conditions();

		$this->assertSame( 0.0, $conditions['temperature'] );
		$this->assertSame( 0.0, $conditions['windspeed'] );
		$this->assertSame( 0.0, $conditions['rain'] );
		$this->assertSame( 0, $conditions['cloudiness'] );
		$this->assertSame( 0.0, $conditions['visibility'] );
		$this->assertArrayNotHasKey( 'windgust', $conditions );
		$this->assertArrayNotHasKey( 'snow', $conditions );
	}

	/**
	 * Visual Crossing: visibility is converted to meters before it is rounded, so short distances are kept.
	 */
	public function test_visualcrossing_visibility() {
		$fixture = $this->fixture( 'weather/visualcrossing-current.json' );
		foreach ( array(
			'0.4'  => 400.0,
			'9.6'  => 9600.0,
			'0.05' => 50.0,
		) as $km => $meters ) {
			$fixture['currentConditions']['visibility'] = (float) $km;
			$this->mock_http( array( 'weather.visualcrossing.com' => array( 'body' => $fixture ) ) );
			$conditions = $this->provider( 'Weather_Provider_VisualCrossing', array( 'api' => 'vc-key' ) )->get_conditions();
			$this->assertSame( $meters, $conditions['visibility'], $km );
		}

		unset( $fixture['currentConditions']['visibility'] );
		$this->mock_http( array( 'weather.visualcrossing.com' => array( 'body' => $fixture ) ) );
		$conditions = $this->provider( 'Weather_Provider_VisualCrossing', array( 'api' => 'vc-key' ) )->get_conditions();
		$this->assertArrayNotHasKey( 'visibility', $conditions );
	}

	/**
	 * Visual Crossing: past conditions use the timeline for that moment and pick the matching hour.
	 */
	public function test_visualcrossing_past() {
		$fixture = $this->fixture( 'weather/visualcrossing-timeline.json' );
		$this->mock_http( array( 'weather.visualcrossing.com' => array( 'body' => $fixture ) ) );
		$time       = strtotime( '2025-07-01T14:00:00-04:00' );
		$conditions = $this->provider( 'Weather_Provider_VisualCrossing', array( 'api' => 'vc-key' ) )->get_conditions( $time );
		$this->assertStringEndsWith( ',' . self::LNG . '/' . $time, $this->last_request()['base'] );
		$this->assertEqualsWithDelta( $fixture['days'][0]['hours'][14]['temp'], $conditions['temperature'], 0.01 );
		$this->assertSame( 'Overcast', $conditions['summary'] );
	}

	/**
	 * Visual Crossing: HTTP errors return the WP_Error, and responses without conditions return nothing.
	 */
	public function test_visualcrossing_errors() {
		$this->mock_http(
			array(
				'weather.visualcrossing.com' => array(
					'code' => 401,
					'body' => 'No account found with API key',
				),
			)
		);
		$this->assertWPError( $this->provider( 'Weather_Provider_VisualCrossing', array( 'api' => 'bad-key' ) )->get_conditions() );

		$this->mock_http( array( 'weather.visualcrossing.com' => array( 'body' => array( 'queryCost' => 1 ) ) ) );
		$this->assertSame( array(), $this->provider( 'Weather_Provider_VisualCrossing', array( 'api' => 'vc-key' ) )->get_conditions() );

		$this->mock_http( array( 'weather.visualcrossing.com' => array( 'body' => array( 'days' => array( array( 'datetime' => '2025-07-01' ) ) ) ) ) );
		$this->assertSame( array(), $this->provider( 'Weather_Provider_VisualCrossing', array( 'api' => 'vc-key' ) )->get_conditions( strtotime( '2025-07-01T14:00:00-04:00' ) ) );
	}

	/**
	 * Visual Crossing: lightning without thunder maps to the lightning code.
	 */
	public function test_visualcrossing_lightning() {
		$fixture                                    = $this->fixture( 'weather/visualcrossing-current.json' );
		$fixture['currentConditions']['conditions'] = 'type_18';
		$this->mock_http( array( 'weather.visualcrossing.com' => array( 'body' => $fixture ) ) );
		$conditions = $this->provider( 'Weather_Provider_VisualCrossing', array( 'api' => 'vc-key' ) )->get_conditions();
		$this->assertSame( 790, $conditions['code'] );
		$this->assertSame( 'Lightning Without Thunder', $conditions['summary'] );
	}

	/**
	 * Xweather (Aeris): the closest observation to a location.
	 */
	public function test_aeris_location() {
		update_option( 'sloc_aeris_client_id', 'client' );
		update_option( 'sloc_aeris_client_secret', 'secret' );
		update_option( 'sloc_aeris_pws', 1 );
		$fixture = $this->fixture( 'weather/aeris-observations.json' );
		$this->mock_http( array( 'data.api.xweather.com/observations/closest' => array( 'body' => $fixture ) ) );
		$conditions = $this->provider( 'Weather_Provider_Aeris' )->get_conditions();

		$request = $this->last_request();
		$this->assertSame( 'client', $request['query']['client_id'] );
		$this->assertSame( 'secret', $request['query']['client_secret'] );
		$this->assertSame( self::LAT . ',' . self::LNG, $request['query']['p'] );
		$this->assertSame( 'allstations', $request['query']['filter'] );

		$station = $fixture['response'][0];
		$ob      = $station['ob'];
		$this->assertConditions(
			array(
				'station_id'  => $station['id'],
				'temperature' => $ob['tempC'],
				'dewpoint'    => $ob['dewpointC'],
				'humidity'    => $ob['humidity'],
				'pressure'    => $ob['spressureMB'],
				'windspeed'   => $ob['windSpeedKPH'] / 3.6,
				'windgust'    => $ob['windGustKPH'] / 3.6,
				'winddegree'  => $ob['windDirDEG'],
				'uv'          => $ob['uvi'],
				'radiation'   => $ob['solradWM2'],
				'visibility'  => round( $ob['visibilityKM'] * 1000 ),
				'summary'     => $ob['weather'],
				'code'        => 801,
			),
			$conditions
		);
		$this->assertEquals( $station['loc']['lat'], $conditions['latitude'] );
		$this->assertEquals( $station['profile']['elevM'], $conditions['altitude'] );
		$this->assertEquals( $station['relativeTo']['distanceKM'] * 1000, $conditions['distance'] );
	}

	/**
	 * Xweather (Aeris): errors in the body, HTTP errors and empty responses return a WP_Error.
	 */
	public function test_aeris_errors() {
		update_option( 'sloc_aeris_client_id', 'client' );
		update_option( 'sloc_aeris_client_secret', 'secret' );

		$this->mock_http(
			array(
				'data.api.xweather.com/observations/closest' => array(
					'body' => array(
						'success'  => false,
						'error'    => array(
							'code'        => 'invalid_client',
							'description' => 'The client provided is invalid.',
						),
						'response' => array(),
					),
				),
			)
		);
		$location = $this->provider( 'Weather_Provider_Aeris' )->get_conditions();
		$this->assertWPError( $location );
		$this->assertSame( 'invalid_client', $location->get_error_code() );
		$this->assertSame( 'The client provided is invalid.', $location->get_error_message() );

		$station = new Weather_Provider_Aeris();
		$station->set( array( 'station_id' => 'KPHL' ) );
		$this->assertSame( 'invalid_client', $station->get_conditions()->get_error_code() );

		$this->mock_http(
			array(
				'data.api.xweather.com/observations/closest' => array(
					'body' => array(
						'success'  => true,
						'error'    => array(
							'code'        => 'warn_no_data',
							'description' => 'No data available.',
						),
						'response' => array(),
					),
				),
			)
		);
		$this->assertWPError( $this->provider( 'Weather_Provider_Aeris' )->get_conditions() );

		$this->mock_http(
			array(
				'data.api.xweather.com/observations/closest' => array(
					'code' => 500,
					'body' => 'Server Error',
				),
			)
		);
		$this->assertWPError( $this->provider( 'Weather_Provider_Aeris' )->get_conditions() );
		$this->assertWPError( $station->get_conditions() );
	}

	/**
	 * Xweather (Aeris): an observation with missing fields converts without errors.
	 */
	public function test_aeris_partial_observation() {
		$provider = new Weather_Provider_Aeris();
		$this->assertSame( array(), $provider->convert_data( array( 'id' => 'KPHL' ) ) );
		$this->assertSame(
			array(
				'station_id'  => 'KPHL',
				'temperature' => 20.5,
			),
			$provider->convert_data(
				array(
					'id' => 'KPHL',
					'ob' => array( 'tempC' => 20.5 ),
				)
			)
		);
	}

	/**
	 * Xweather (Aeris): a station's latest observation.
	 */
	public function test_aeris_station() {
		update_option( 'sloc_aeris_client_id', 'client' );
		update_option( 'sloc_aeris_client_secret', 'secret' );
		$this->mock_http( array( 'data.api.xweather.com/observations/closest' => array( 'body' => $this->fixture( 'weather/aeris-observations.json' ) ) ) );
		$provider = new Weather_Provider_Aeris();
		$provider->set( array( 'station_id' => 'KPHL' ) );
		$conditions = $provider->get_conditions();
		$this->assertSame( 'KPHL', $this->last_request()['query']['p'] );
		$this->assertSame( 'KPHL', $conditions['station_id'] );
	}

	/**
	 * NWS: a station's latest observation with its name and timezone.
	 */
	public function test_nws_station() {
		$observation = $this->fixture( 'weather/nws-observation.json' );
		$station     = $this->fixture( 'weather/nws-station.json' );
		$this->mock_http(
			array(
				'api.weather.gov/stations/KPHL/observations/latest' => array( 'body' => $observation ),
				'api.weather.gov/stations/KPHL' => array( 'body' => $station ),
			)
		);
		$provider = new Weather_Provider_NWSUS();
		$provider->set( array( 'station_id' => 'KPHL' ) );
		$conditions = $provider->get_conditions();
		$this->assertCount( 2, $this->http_requests );

		$properties = $observation['properties'];
		$this->assertConditions(
			array(
				'station_id'  => 'KPHL',
				'temperature' => $properties['temperature']['value'],
				'dewpoint'    => $properties['dewpoint']['value'],
				'humidity'    => $properties['relativeHumidity']['value'],
				'winddegree'  => $properties['windDirection']['value'],
				'windspeed'   => $properties['windSpeed']['value'] / 3.6,
				'windgust'    => $properties['windGust']['value'] / 3.6,
				'pressure'    => $properties['barometricPressure']['value'] / 100,
				'visibility'  => $properties['visibility']['value'],
				'summary'     => $properties['textDescription'],
				'code'        => 802,
				'cloudiness'  => 44,
				'name'        => $station['properties']['name'],
				'timezone'    => $station['properties']['timeZone'],
			),
			$conditions
		);
		$this->assertEquals( $observation['geometry']['coordinates'][1], $conditions['latitude'] );
	}

	/**
	 * NWS: a location lookup uses the nearest station, wherever it is in the station list.
	 */
	public function test_nws_nearest_station() {
		$stations = array();
		foreach ( array(
			'KFAR' => array( -75.16, 40.5 ),
			'KMID' => array( -75.16, 40.1 ),
			'KNEA' => array( -75.17, 39.96 ),
		) as $id => $coordinates ) {
			$stations[] = array(
				'geometry'   => array( 'coordinates' => $coordinates ),
				'properties' => array( 'stationIdentifier' => $id ),
			);
		}
		$this->mock_http(
			array(
				'api.weather.gov/points/'           => array( 'body' => array( 'properties' => array( 'observationStations' => 'https://api.weather.gov/gridpoints/PHI/49,75/stations' ) ) ),
				'gridpoints/PHI/49,75/stations'     => array( 'body' => array( 'features' => $stations ) ),
				'stations/KNEA/observations/latest' => array( 'body' => $this->fixture( 'weather/nws-observation.json' ) ),
				'stations/KNEA'                     => array( 'body' => $this->fixture( 'weather/nws-station.json' ) ),
			)
		);
		$conditions = $this->provider( 'Weather_Provider_NWSUS' )->get_conditions();
		$this->assertSame( 'KNEA', $conditions['station_id'] );
		$this->assertStringContainsString( 'stations/KNEA/observations/latest', $this->http_requests[2]['url'] );
	}

	/**
	 * NWS: readings of 0 are kept, and readings NWS sends as null are left out instead of becoming 0.
	 */
	public function test_nws_zero_and_missing_values() {
		$observation = $this->fixture( 'weather/nws-observation.json' );
		foreach ( array( 'temperature', 'windSpeed', 'windDirection', 'precipitationLastHour' ) as $key ) {
			$observation['properties'][ $key ]['value'] = 0;
		}
		$observation['properties']['barometricPressure']['value'] = null;
		$this->mock_http(
			array(
				'observations/latest' => array( 'body' => $observation ),
				'stations/KPHL'       => array( 'body' => $this->fixture( 'weather/nws-station.json' ) ),
			)
		);
		$provider = new Weather_Provider_NWSUS();
		$provider->set( array( 'station_id' => 'KPHL' ) );
		$conditions = $provider->get_conditions();

		$this->assertSame( 0, $conditions['temperature'] );
		$this->assertSame( 0.0, $conditions['windspeed'] );
		$this->assertSame( 0, $conditions['winddegree'] );
		$this->assertSame( 0, $conditions['rain'] );
		// Null in the fixture: no reading, not 0.
		$this->assertArrayNotHasKey( 'pressure', $conditions );
		$this->assertArrayNotHasKey( 'windchill', $conditions );
		$this->assertArrayNotHasKey( 'heatindex', $conditions );
	}

	/**
	 * NWS: a thunderstorm in the present weather sets the condition code.
	 */
	public function test_nws_present_weather() {
		$observation                                 = $this->fixture( 'weather/nws-observation.json' );
		$observation['properties']['presentWeather'] = array(
			array(
				'intensity' => 'heavy',
				'modifier'  => null,
				'weather'   => 'thunderstorms',
				'rawString' => '+TS',
			),
		);
		$this->mock_http(
			array(
				'observations/latest' => array( 'body' => $observation ),
				'stations/KPHL'       => array( 'body' => $this->fixture( 'weather/nws-station.json' ) ),
			)
		);
		$provider = new Weather_Provider_NWSUS();
		$provider->set( array( 'station_id' => 'KPHL' ) );
		$this->assertSame( 212, $provider->get_conditions()['code'] );
	}

	/**
	 * NWS: wind and rainfall are converted from the unit each reading is reported in.
	 */
	public function test_nws_units() {
		$observation = $this->fixture( 'weather/nws-observation.json' );
		$observation['properties']['precipitationLastHour']['value'] = 2.5;
		$this->mock_http(
			array(
				'observations/latest' => array( 'body' => $observation ),
				'stations/KPHL'       => array( 'body' => $this->fixture( 'weather/nws-station.json' ) ),
			)
		);
		$provider = new Weather_Provider_NWSUS();
		$provider->set( array( 'station_id' => 'KPHL' ) );
		$conditions = $provider->get_conditions();
		$this->assertEqualsWithDelta( 22.3 / 3.6, $conditions['windgust'], 0.01, 'Gusts in km/h are converted to m/s.' );
		$this->assertEqualsWithDelta( 13 / 3.6, $conditions['windspeed'], 0.01 );
		$this->assertEqualsWithDelta( 2.5, $conditions['rain'], 0.01, 'Rainfall in mm is kept.' );

		// Readings in other units.
		$observation['properties']['precipitationLastHour'] = array(
			'unitCode' => 'wmoUnit:m',
			'value'    => 0.0025,
		);
		$observation['properties']['windGust']              = array(
			'unitCode' => 'wmoUnit:m_s-1',
			'value'    => 6.2,
		);
		$this->mock_http(
			array(
				'observations/latest' => array( 'body' => $observation ),
				'stations/KPHL'       => array( 'body' => $this->fixture( 'weather/nws-station.json' ) ),
			)
		);
		wp_cache_flush();
		$conditions = $provider->get_conditions();
		$this->assertEqualsWithDelta( 2.5, $conditions['rain'], 0.01, 'Rainfall in m is converted to mm.' );
		$this->assertEqualsWithDelta( 6.2, $conditions['windgust'], 0.01, 'Gusts in m/s are kept.' );
	}

	/**
	 * NWS: an observation without cloud layers or present weather has no condition code.
	 */
	public function test_nws_no_cloud_layers() {
		$observation = $this->fixture( 'weather/nws-observation.json' );
		foreach ( array( array(), null ) as $layers ) {
			$observation['properties']['cloudLayers'] = $layers;
			$this->mock_http(
				array(
					'observations/latest' => array( 'body' => $observation ),
					'stations/KPHL'       => array( 'body' => $this->fixture( 'weather/nws-station.json' ) ),
				)
			);
			wp_cache_flush();
			$provider = new Weather_Provider_NWSUS();
			$provider->set( array( 'station_id' => 'KPHL' ) );
			$conditions = $provider->get_conditions();
			$this->assertArrayNotHasKey( 'code', $conditions );
			$this->assertArrayNotHasKey( 'cloudiness', $conditions );
			$this->assertSame( $observation['properties']['temperature']['value'], $conditions['temperature'] );
		}
	}

	/**
	 * NWS: the cloud cover is the largest amount among the cloud layers, in the middle of its okta range (#657).
	 */
	public function test_nws_cloud_cover() {
		$layer = function ( $amount ) {
			return array(
				'base'   => array(
					'unitCode' => 'wmoUnit:m',
					'value'    => 1520,
				),
				'amount' => $amount,
			);
		};
		$this->assertSame( 0, Weather_Provider_NWSUS::cloud_cover( array( $layer( 'CLR' ) ) ) );
		$this->assertSame( 0, Weather_Provider_NWSUS::cloud_cover( array( $layer( 'SKC' ) ) ) );
		$this->assertSame( 19, Weather_Provider_NWSUS::cloud_cover( array( $layer( 'FEW' ) ) ) );
		$this->assertSame( 75, Weather_Provider_NWSUS::cloud_cover( array( $layer( 'FEW' ), $layer( 'SCT' ), $layer( 'BKN' ) ) ) );
		$this->assertSame( 100, Weather_Provider_NWSUS::cloud_cover( array( $layer( 'OVC' ), $layer( 'FEW' ) ) ) );
		$this->assertSame( 100, Weather_Provider_NWSUS::cloud_cover( array( $layer( 'VV' ) ) ) );
		$this->assertSame( 44, Weather_Provider_NWSUS::cloud_cover( array( $layer( 'unknown' ), $layer( 'SCT' ), 'junk' ) ) );
		$this->assertNull( Weather_Provider_NWSUS::cloud_cover( array( $layer( 'unknown' ) ) ) );
		$this->assertNull( Weather_Provider_NWSUS::cloud_cover( null ) );
	}

	/**
	 * NWS: outside the US there is no grid point, so an error; in the chain, NWS is skipped there without a request.
	 */
	public function test_nws_outside_us_fallback() {
		Weather_Provider_Sloc_Test::reset();
		$this->mock_http(
			array(
				'api.weather.gov/points/' => array(
					'code' => 404,
					'body' => array(
						'title'  => 'Data Unavailable For Requested Point',
						'status' => 404,
					),
				),
			)
		);
		$london = new Weather_Provider_NWSUS();
		$london->set( 51.5, -0.12 );
		$this->assertWPError( $london->get_conditions() );
		$this->assertFalse( $london->covers(), 'NWS covers the US only.' );
		$requests = count( $this->http_requests );

		update_option( 'sloc_weather_provider', 'nwsus' );
		update_option( 'sloc_weather_fallbacks', array( 'sloc_test_fallback' ) );
		$conditions = Sloc_Provider_Chain::weather( 51.5, -0.12 );
		$this->assertSame( 20, $conditions['temperature'] );
		$this->assertSame( 'sloc_test_fallback', $conditions['provider'] );
		$this->assertCount( $requests, $this->http_requests, 'NWS is not asked outside the US.' );
	}

	/**
	 * NWS: a failed request or empty answer anywhere in a location lookup is an error that isn't cached, and the
	 * chain uses the next provider.
	 */
	public function test_nws_lookup_failures_fallback() {
		Weather_Provider_Sloc_Test::reset();
		update_option( 'sloc_weather_provider', 'nwsus' );
		update_option( 'sloc_weather_fallbacks', array( 'sloc_test_fallback' ) );
		$points   = array( 'body' => array( 'properties' => array( 'observationStations' => 'https://api.weather.gov/gridpoints/PHI/49,75/stations' ) ) );
		$stations = array(
			'body' => array(
				'features' => array(
					array(
						'geometry'   => array( 'coordinates' => array( -75.17, 39.96 ) ),
						'properties' => array( 'stationIdentifier' => 'KPHL' ),
					),
				),
			),
		);
		$down     = new WP_Error( 'http_request_failed', 'Down' );
		$cases    = array(
			'grid point request fails'   => array( 'api.weather.gov/points/' => $down ),
			'station list request fails' => array(
				'api.weather.gov/points/' => $points,
				'stations'                => $down,
			),
			'station list is empty'      => array(
				'api.weather.gov/points/' => $points,
				'stations'                => array( 'body' => array( 'features' => array() ) ),
			),
			'observation request fails'  => array(
				'api.weather.gov/points/' => $points,
				'gridpoints'              => $stations,
				'observations/latest'     => $down,
			),
			'observation is empty'       => array(
				'api.weather.gov/points/' => $points,
				'gridpoints'              => $stations,
				'observations/latest'     => array( 'body' => array() ),
				'stations/KPHL'           => array( 'body' => array() ),
			),
		);
		foreach ( $cases as $case => $responses ) {
			$this->mock_http( $responses );
			$this->assertWPError( $this->provider( 'Weather_Provider_NWSUS' )->get_conditions(), $case );
			$this->assertWPError( $this->provider( 'Weather_Provider_NWSUS' )->get_conditions(), "$case: nothing was cached." );
			$conditions = Sloc_Provider_Chain::weather( self::LAT, self::LNG );
			$this->assertSame( 'sloc_test_fallback', $conditions['provider'], $case );
		}
	}

	/**
	 * Meteostat: a station's hourly data for the requested hour, through RapidAPI.
	 */
	public function test_meteostat_station() {
		$fixture = $this->fixture( 'weather/meteostat-hourly.json' );
		$this->mock_http( array( 'meteostat.p.rapidapi.com/stations/hourly' => array( 'body' => $fixture ) ) );
		$provider = new Weather_Provider_Meteostat( array( 'api' => 'ms-key' ) );
		$provider->set( array( 'station_id' => '72408' ) );
		$conditions = $provider->get_conditions( strtotime( '2025-07-01T14:00:00Z' ) );

		$request = $this->last_request();
		$this->assertSame( '72408', $request['query']['station'] );
		$this->assertSame( '2025-07-01', $request['query']['start'] );
		$this->assertSame( '2025-07-02', $request['query']['end'] );
		$this->assertSame( 'ms-key', $request['args']['headers']['x-rapidapi-key'] );

		$hour = $fixture['data'][14];
		$this->assertConditions(
			array(
				'station_id'  => '72408',
				'temperature' => $hour['temp'],
				'dewpoint'    => $hour['dwpt'],
				'humidity'    => $hour['rhum'],
				'pressure'    => $hour['pres'],
				'windspeed'   => $hour['wspd'] / 3.6,
				'windgust'    => $hour['wpgt'] / 3.6,
				'winddegree'  => $hour['wdir'],
				'summary'     => Weather_Provider_Meteostat::get_status( $hour['coco'] ),
				'code'        => 800,
			),
			$conditions
		);
		$this->assertSame( 'Fair', $conditions['summary'], 'The summary is the condition name, not its code.' );
	}

	/**
	 * Meteostat: a location lookup uses the nearest station from the bundled list, with its distance, and clears the station ID afterwards.
	 */
	public function test_meteostat_location() {
		$this->mock_http( array( 'meteostat.p.rapidapi.com/stations/hourly' => array( 'body' => $this->fixture( 'weather/meteostat-hourly.json' ) ) ) );
		$provider   = $this->provider(
			'Weather_Provider_Meteostat',
			array(
				'api'        => 'ms-key',
				'cache_time' => HOUR_IN_SECONDS,
			)
		);
		$time       = strtotime( '2025-07-01T14:00:00Z' );
		$conditions = $provider->get_conditions( $time );

		$this->assertSame( '72408', $this->last_request()['query']['station'], 'Philadelphia International is the nearest station.' );
		$this->assertSame( '72408', $conditions['station_id'] );
		$this->assertEqualsWithDelta( 11700, $conditions['distance'], 500 );
		$this->assertNull( $provider->get_station(), 'The station ID is cleared after a lookup.' );

		$cached = $provider->get_conditions( $time );
		$this->assertCount( 1, $this->http_requests, 'The second lookup comes from the cache.' );
		$this->assertSame( $conditions['temperature'], $cached['temperature'] );
		$this->assertNull( $provider->get_station() );
	}

	/**
	 * Cached conditions are kept per requested hour, so a later lookup for another past time isn't answered
	 * from the cache of an earlier one.
	 */
	public function test_cache_by_requested_time() {
		$this->mock_http( array( 'meteostat.p.rapidapi.com/stations/hourly' => array( 'body' => $this->fixture( 'weather/meteostat-hourly.json' ) ) ) );
		$provider = new Weather_Provider_Meteostat(
			array(
				'api'        => 'ms-key',
				'cache_time' => HOUR_IN_SECONDS,
			)
		);
		$provider->set( array( 'station_id' => '72408' ) );

		$provider->get_conditions( strtotime( '2025-07-01T14:00:00Z' ) );
		$provider->get_conditions( strtotime( '2024-01-15T09:00:00Z' ) );
		$this->assertCount( 2, $this->http_requests, 'Another past time is fetched, not answered from the first lookup.' );
		$this->assertSame( '2024-01-15', $this->last_request()['query']['start'] );

		$provider->get_conditions( strtotime( '2025-07-01T14:00:00Z' ) );
		$provider->get_conditions( strtotime( '2025-07-01T14:40:00Z' ) );
		$this->assertCount( 2, $this->http_requests, 'Each past hour is cached.' );
	}

	/**
	 * Current conditions keep their cache key, and a past time is cached under its own key.
	 */
	public function test_cache_key_for_current_and_past() {
		$fixture = $this->fixture( 'weather/visualcrossing-current.json' );
		$this->mock_http( array( 'weather.visualcrossing.com' => array( 'body' => $fixture ) ) );
		$provider = $this->provider(
			'Weather_Provider_VisualCrossing',
			array(
				'api'        => 'vc-key',
				'cache_time' => 600,
			)
		);
		$key      = 'Weather_Provider_VisualCrossing_' . md5( self::LAT . ',' . self::LNG );

		$provider->get_conditions();
		$provider->get_conditions( time() - 10 * MINUTE_IN_SECONDS );
		$this->assertCount( 1, $this->http_requests, 'A time within the hour is current.' );
		$this->assertIsArray( get_transient( $key ) );

		$past = strtotime( '2025-07-01T14:20:00Z' );
		$provider->get_conditions( $past );
		$this->assertCount( 2, $this->http_requests );
		$this->assertIsArray( get_transient( $key . '_2025070114' ) );
		$provider->get_conditions( $past );
		$this->assertCount( 2, $this->http_requests );
	}

	/**
	 * Meteostat: the nearest station within 100 km is preferred when it has hourly data for the date; otherwise
	 * the nearest station with data is used, and without one, the nearest station.
	 */
	public function test_meteostat_nearest_station() {
		$file = dirname( __DIR__ ) . '/tests/data/weather/meteostat-stations.csv';

		$station = Weather_Provider_Meteostat::nearest_station( self::LAT, self::LNG, '2012-06-01', $file );
		$this->assertSame( 'NEAR1', $station['id'], 'The nearest station has data for 2012.' );
		$this->assertSame(
			array(
				'id'        => 'NEAR1',
				'name'      => array( 'en' => 'Near, Closed Station' ),
				'country'   => 'US',
				'region'    => 'PA',
				'location'  => array(
					'latitude'  => 39.98,
					'longitude' => -75.16,
					'elevation' => 20,
				),
				'timezone'  => 'America/New_York',
				'inventory' => array(
					'hourly' => array(
						'start' => '2010-01-01',
						'end'   => '2015-12-31',
					),
				),
			),
			$station
		);

		$this->assertSame( 'FAR01', Weather_Provider_Meteostat::nearest_station( self::LAT, self::LNG, '2020-06-01', $file )['id'], 'The nearest station closed in 2015.' );
		$this->assertSame( 'FAR01', Weather_Provider_Meteostat::nearest_station( self::LAT, self::LNG, '2026-10-18', $file )['id'], 'A station reporting when the list was built covers later dates.' );
		$this->assertSame( 'FAR01', Weather_Provider_Meteostat::nearest_station( self::LAT, self::LNG, null, $file )['id'] );
		$this->assertSame( 'NODAT', Weather_Provider_Meteostat::nearest_station( self::LAT, self::LNG, '1980-06-01', $file )['id'], 'Without a station with data, the nearest station is used.' );
		$this->assertNull( Weather_Provider_Meteostat::nearest_station( 0, -140, null, $file ) );
		$this->assertSame( 'filesystem_error', Weather_Provider_Meteostat::nearest_station( self::LAT, self::LNG, null, $file . '.missing' )->get_error_code() );
	}

	/**
	 * Meteostat: the station found for a location and day is cached, including when there is none in range.
	 */
	public function test_meteostat_station_cached() {
		$this->mock_http( array( 'meteostat.p.rapidapi.com/stations/hourly' => array( 'body' => $this->fixture( 'weather/meteostat-hourly.json' ) ) ) );
		$time = strtotime( '2025-07-01T14:00:00Z' );
		$this->provider( 'Weather_Provider_Meteostat', array( 'api' => 'ms-key' ) )->get_conditions( $time );
		$key = 'sloc_meteostat_station_' . md5( implode( ',', array( round( self::LAT, 2 ), round( self::LNG, 2 ), '2025-07-01' ) ) );
		$this->assertSame( '72408', get_transient( $key )['id'] );

		// No station within 100 km: the empty result is cached, and the provider has no conditions to give.
		$provider = new Weather_Provider_Meteostat( array( 'api' => 'ms-key' ) );
		$provider->set( 0, -140 );
		$this->assertWPError( $provider->get_conditions( $time ) );
		$this->assertSame( '', get_transient( 'sloc_meteostat_station_' . md5( implode( ',', array( 0.0, -140.0, '2025-07-01' ) ) ) ) );
	}

	/**
	 * Meteostat: readings of 0 are kept, and missing readings are left out instead of becoming 0.
	 */
	public function test_meteostat_zero_and_missing_values() {
		$fixture                     = $this->fixture( 'weather/meteostat-hourly.json' );
		$fixture['data'][14]['temp'] = 0;
		$fixture['data'][14]['prcp'] = 0;
		$fixture['data'][14]['wspd'] = 0;
		$fixture['data'][14]['wpgt'] = null;
		$fixture['data'][14]['snow'] = null;
		$fixture['data'][14]['coco'] = null;
		$this->mock_http( array( 'meteostat.p.rapidapi.com/stations/hourly' => array( 'body' => $fixture ) ) );
		$provider = new Weather_Provider_Meteostat( array( 'api' => 'ms-key' ) );
		$provider->set( array( 'station_id' => '72408' ) );
		$conditions = $provider->get_conditions( strtotime( '2025-07-01T14:00:00Z' ) );

		// Rounded readings are floats, even when the response has the integer 0.
		$this->assertSame( 0.0, $conditions['temperature'] );
		$this->assertSame( 0.0, $conditions['rain'] );
		$this->assertSame( 0.0, $conditions['windspeed'] );
		$this->assertArrayNotHasKey( 'windgust', $conditions );
		$this->assertArrayNotHasKey( 'snow', $conditions );
		$this->assertArrayNotHasKey( 'summary', $conditions );
		$this->assertArrayNotHasKey( 'code', $conditions );
	}

	/**
	 * Meteostat condition codes map to names and plugin codes.
	 */
	public function test_meteostat_codes() {
		$this->assertSame( 'Thunderstorm', Weather_Provider_Meteostat::get_status( 25 ) );
		$this->assertSame( '', Weather_Provider_Meteostat::get_status( 99 ) );
		$this->assertSame( 211, Weather_Provider_Meteostat::code_map( 25 ) );
		$this->assertSame( '', Weather_Provider_Meteostat::code_map( 99 ) );
	}

	/**
	 * Custom station: fresh data from the station URL, with wind split up, N/A values dropped and the
	 * station's coordinates learned.
	 */
	public function test_custom_station() {
		update_option(
			'sloc_stations',
			array(
				array(
					'id'        => 'backyard',
					'url'       => 'https://station.example.com/data.json',
					'latitude'  => '',
					'longitude' => '',
				),
			)
		);
		$fixture              = $this->fixture( 'weather/station-custom.json' );
		$fixture['timestamp'] = time();
		$this->mock_http( array( 'station.example.com/data.json' => array( 'body' => $fixture ) ) );

		$provider = new Weather_Provider_Station();
		$provider->set( array( 'station_id' => 'backyard' ) );
		$conditions = $provider->get_conditions();

		$this->assertConditions(
			array(
				'temperature' => $fixture['temperature'],
				'humidity'    => $fixture['humidity'],
				'windspeed'   => $fixture['wind']['speed'],
				'winddegree'  => $fixture['wind']['degree'],
				'windgust'    => $fixture['wind']['gust'],
				'code'        => 802,
			),
			$conditions
		);
		$this->assertArrayNotHasKey( 'wind', $conditions );
		$this->assertArrayNotHasKey( 'rain', $conditions, 'N/A values are dropped.' );

		$stations = get_option( 'sloc_stations' );
		$this->assertEquals( $fixture['latitude'], $stations[0]['latitude'], 'Coordinates are learned from the station.' );
	}

	/**
	 * Providers with only current weather return an error for other times without a request, and the chain moves on.
	 */
	public function test_unsupported_times() {
		Weather_Provider_Sloc_Test::reset();
		$this->mock_http( array() );
		$past   = strtotime( '-2 days' );
		$future = strtotime( '+2 days' );
		foreach ( array( 'Weather_Provider_OpenWeatherMap', 'Weather_Provider_Weatherbit', 'Weather_Provider_Weatherstack', 'Weather_Provider_HERE', 'Weather_Provider_NWSUS' ) as $class ) {
			$provider = $this->provider( $class, array( 'api' => 'key' ) );
			foreach ( array( $past, $future ) as $time ) {
				$error = $provider->get_conditions( $time );
				$this->assertWPError( $error, $class );
				$this->assertSame( 'historic_unsupported', $error->get_error_code(), $class );
				$this->assertStringContainsString( $provider->get_name(), $error->get_error_message(), $class );
			}

			update_option( 'sloc_weather_provider', $provider->get_slug() );
			update_option( 'sloc_weather_fallbacks', array( 'sloc_test_fallback' ) );
			$conditions = Sloc_Provider_Chain::weather( self::LAT, self::LNG, $past );
			$this->assertSame( 'sloc_test_fallback', $conditions['provider'], $class );
			$this->assertSame( $past, Weather_Provider_Sloc_Test::$time, "$class: the time is passed to the next provider." );

			update_option( 'sloc_weather_fallbacks', array( $provider->get_slug() ) );
			Weather_Provider_Sloc_Test::$calls = array();
			Sloc_Provider_Chain::weather( self::LAT, self::LNG, $past );
			$this->assertSame( array(), Weather_Provider_Sloc_Test::$calls, "$class is never its own fallback." );
		}
		$this->assertSame( array(), $this->http_requests );
	}

	/**
	 * Custom station: stale data falls back to the fallback provider, which is an error when none is set.
	 */
	public function test_custom_station_stale() {
		update_option(
			'sloc_stations',
			array(
				array(
					'id'        => 'backyard',
					'url'       => 'https://station.example.com/data.json',
					'latitude'  => '39.95',
					'longitude' => '-75.16',
				),
			)
		);
		$fixture              = $this->fixture( 'weather/station-custom.json' );
		$fixture['timestamp'] = time() - DAY_IN_SECONDS;
		$this->mock_http( array( 'station.example.com' => array( 'body' => $fixture ) ) );
		$provider = new Weather_Provider_Station();
		$provider->set( array( 'station_id' => 'backyard' ) );
		$this->assertSame( 'station_stale', $provider->get_conditions()->get_error_code() );
		$this->assertTrue( Sloc_Provider_Errors::is_actionable( $provider->get_conditions(), $provider ), 'A station that stopped updating is for the administrator.' );
		$this->assertSame( 'historic_unsupported', $provider->get_conditions( strtotime( '-1 day' ) )->get_error_code(), 'Past times are an error without a request.' );

		// By location too, and a station that can't be read is its error, not empty weather.
		$provider = $this->provider( 'Weather_Provider_Station' );
		$this->assertSame( 'station_stale', $provider->get_conditions()->get_error_code() );
		$this->mock_http( array( 'station.example.com' => new WP_Error( 'http_request_failed', 'Down' ) ) );
		$this->assertSame( 'http_request_failed', $provider->get_conditions()->get_error_code() );
		$provider->set( 0, 0 );
		$this->assertSame( 'no_results', $provider->get_conditions()->get_error_code(), 'No station in range.' );
	}

	/**
	 * Custom station: a location lookup uses the nearest station within 10 km.
	 */
	public function test_custom_station_nearest() {
		$stations = array();
		foreach ( array(
			'far'  => '39.99',
			'near' => '39.951',
			'mid'  => '39.97',
		) as $id => $latitude ) {
			$stations[] = array(
				'id'        => $id,
				'url'       => 'https://station.example.com/' . $id . '.json',
				'latitude'  => $latitude,
				'longitude' => (string) self::LNG,
			);
		}
		update_option( 'sloc_stations', $stations );
		$fixture              = $this->fixture( 'weather/station-custom.json' );
		$fixture['timestamp'] = time();
		$this->mock_http( array( 'station.example.com/near.json' => array( 'body' => $fixture ) ) );

		$conditions = $this->provider( 'Weather_Provider_Station' )->get_conditions();
		$this->assertCount( 1, $this->http_requests );
		$this->assertSame( 'https://station.example.com/near.json', $this->http_requests[0]['url'] );
		$this->assertEqualsWithDelta( 156, $conditions['distance'], 2 );
	}

	/**
	 * The custom station settings list each station's fields.
	 */
	public function test_custom_station_settings() {
		update_option(
			'sloc_stations',
			array(
				array(
					'id'  => 'backyard',
					'url' => 'https://station.example.com/data.json',
				),
			)
		);
		ob_start();
		Weather_Provider_Station::stations_section();
		Weather_Provider_Station::stations_callback( array() );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'name="sloc_stations[0][id]"', $output );
		$this->assertStringContainsString( 'value="backyard"', $output );
		$this->assertStringContainsString( 'value="https://station.example.com/data.json"', $output );
		// An empty row adds a station, and each saved station can be removed, without scripts (#677).
		$this->assertStringContainsString( 'name="sloc_stations[1][id]" value=""', $output );
		$this->assertStringContainsString( 'name="sloc_stations[0][remove]"', $output );
		$this->assertStringNotContainsString( 'name="sloc_stations[1][remove]"', $output );
		$this->assertStringContainsString( 'name="sloc_stations[0][attribution]"', $output );
		$this->assertStringNotContainsString( 'add-location-stations-button', $output );
		$this->assertSame( '', Weather_Provider_Station::key( 'text', 'id' ) );
	}
}

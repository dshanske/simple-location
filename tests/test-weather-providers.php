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
		update_option( 'sloc_fallback_weather_provider', 'none' );
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
		foreach ( array( 'openweathermap', 'pirateweather', 'weatherbit', 'weatherstack', 'visualcrossing', 'aeris', 'nwsus', 'meteostat', 'metofficeuk', 'here', 'station' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $providers, $slug );
			$this->assertNotEmpty( $providers[ $slug ]['name'], $slug );
		}
	}

	/**
	 * Providers that need credentials return no conditions and make no request without them.
	 */
	public function test_missing_credentials() {
		$this->mock_http( array() );
		foreach ( array( 'OpenWeatherMap', 'PirateWeather', 'Weatherbit', 'Weatherstack', 'VisualCrossing', 'Meteostat', 'HERE' ) as $name ) {
			$class = 'Weather_Provider_' . $name;
			$this->assertSame( array(), $this->provider( $class, array( 'api' => '' ) )->get_conditions(), $name );
		}
		$this->assertSame( array(), $this->provider( 'Weather_Provider_Aeris' )->get_conditions(), 'Aeris' );
		$this->assertSame( array(), $this->http_requests );
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

		$this->assertSame( $conditions['temperature'], $provider->get_conditions()['temperature'] );
		$this->assertCount( 1, $this->http_requests, 'The second call is served from the cache.' );
	}

	/**
	 * OpenWeatherMap passes on request failures and returns nothing for a body without current conditions.
	 */
	public function test_openweathermap_errors() {
		$this->mock_http( array( 'openweathermap' => new WP_Error( 'http_request_failed', 'Timed out' ) ) );
		$this->assertWPError( $this->provider( 'Weather_Provider_OpenWeatherMap', array( 'api' => 'owm-key' ) )->get_conditions() );

		$this->mock_http( array( 'openweathermap' => array( 'body' => array( 'cod' => 401 ) ) ) );
		$this->assertSame( array(), array_diff_key( $this->provider( 'Weather_Provider_OpenWeatherMap', array( 'api' => 'owm-key' ) )->get_conditions(), array( 'raw' => 1 ) ) );
	}

	/**
	 * Pirate Weather: current conditions from the forecast endpoint, with SI units converted.
	 */
	public function test_pirateweather() {
		// The provider logs the site language on every call; keep it out of the test output.
		$log = ini_set( 'error_log', wp_tempnam( 'sloc-log' ) ); // phpcs:ignore WordPress.PHP.IniSet.Risky

		$fixture = $this->fixture( 'weather/pirateweather-forecast.json' );
		$this->mock_http( array( 'api.pirateweather.net/forecast/' => array( 'body' => $fixture ) ) );
		$conditions = $this->provider( 'Weather_Provider_PirateWeather', array( 'api' => 'pw-key' ) )->get_conditions();
		$request    = $this->last_request();
		$this->assertSame( 'https://api.pirateweather.net/forecast/pw-key/' . self::LAT . ',' . self::LNG, $request['base'] );
		$this->assertSame( 'si', $request['query']['units'] );

		$current = $fixture['currently'];
		$this->assertConditions(
			array(
				'temperature' => $current['temperature'],
				'humidity'    => $current['humidity'] * 100,
				'pressure'    => $current['pressure'],
				'cloudiness'  => $current['cloudCover'] * 100,
				'windspeed'   => $current['windSpeed'],
				'winddegree'  => $current['windBearing'],
				'visibility'  => $current['visibility'] * 1000,
				'summary'     => $current['summary'],
				'code'        => 803,
			),
			$conditions
		);

		// A time requests that moment.
		$time = strtotime( '-2 days' );
		$this->provider( 'Weather_Provider_PirateWeather', array( 'api' => 'pw-key' ) )->get_conditions( $time );
		$this->assertStringEndsWith( ',' . $time, $this->last_request()['base'] );

		ini_set( 'error_log', $log ); // phpcs:ignore WordPress.PHP.IniSet.Risky
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

		$this->assertSame( 0, $conditions['temperature'] );
		$this->assertSame( 0.0, $conditions['windspeed'] );
		$this->assertSame( 0, $conditions['rain'] );
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
				'pressure'    => $properties['barometricPressure']['value'] / 100,
				'visibility'  => $properties['visibility']['value'],
				'summary'     => $properties['textDescription'],
				'code'        => 802,
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
		$this->assertSame( 0.0, $conditions['rain'] );
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
	 * NWS: outside the US there is no grid point, so the fallback provider is used, or an error when none is set.
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

		update_option( 'sloc_fallback_weather_provider', 'sloc_test_fallback' );
		$this->assertSame( Weather_Provider_Sloc_Test::$conditions, $london->get_conditions() );
		$this->assertCount( 2, $this->http_requests, 'Only the grid point is requested from NWS.' );
	}

	/**
	 * NWS: a failed request or empty answer anywhere in a location lookup uses the fallback provider, and isn't cached.
	 */
	public function test_nws_lookup_failures_fallback() {
		Weather_Provider_Sloc_Test::reset();
		update_option( 'sloc_fallback_weather_provider', 'sloc_test_fallback' );
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
			$provider = $this->provider( 'Weather_Provider_NWSUS' );
			$this->assertSame( Weather_Provider_Sloc_Test::$conditions, $provider->get_conditions(), $case );

			update_option( 'sloc_fallback_weather_provider', 'none' );
			$this->assertWPError( $this->provider( 'Weather_Provider_NWSUS' )->get_conditions(), "$case: nothing was cached." );
			update_option( 'sloc_fallback_weather_provider', 'sloc_test_fallback' );
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
				'code'        => 800,
			),
			$conditions
		);
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
		$this->mock_http( array( 'meteostat.p.rapidapi.com/stations/hourly' => array( 'body' => $fixture ) ) );
		$provider = new Weather_Provider_Meteostat( array( 'api' => 'ms-key' ) );
		$provider->set( array( 'station_id' => '72408' ) );
		$conditions = $provider->get_conditions( strtotime( '2025-07-01T14:00:00Z' ) );

		// JSON has no 0.0, so the mocked response has the integer 0.
		$this->assertSame( 0, $conditions['temperature'] );
		$this->assertSame( 0, $conditions['rain'] );
		$this->assertSame( 0.0, $conditions['windspeed'] );
		$this->assertArrayNotHasKey( 'windgust', $conditions );
		$this->assertArrayNotHasKey( 'snow', $conditions );
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
		$this->assertWPError( $provider->get_conditions() );
		$this->assertWPError( $provider->get_conditions( strtotime( '-1 day' ) ), 'Past times go straight to the fallback.' );
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
		$this->assertStringContainsString( 'add-location-stations-button', $output );
		$this->assertSame( '', Weather_Provider_Station::key( 'text', 'id' ) );
	}
}

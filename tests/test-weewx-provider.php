<?php
/**
 * Tests for the WeeWX weather provider, which reads weewx-current-json files (#676).
 *
 * @package Simple_Location
 */

/**
 * WeeWX provider tests.
 */
class WeewxProviderTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

	/**
	 * Known settings for every test.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_measurements', 'metric' );
		delete_option( 'sloc_weather_fallbacks' );
		delete_option( 'sloc_weewx_stations' );
		global $wp_settings_errors;
		$wp_settings_errors = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Returns the current conditions fixture, observed now.
	 *
	 * @return array
	 */
	protected function document() {
		$document         = $this->fixture( 'weather/station-current-conditions.json' );
		$document['time'] = time();
		return $document;
	}

	/**
	 * Saves the station, and returns a provider for it.
	 *
	 * @return Weather_Provider_Weewx
	 */
	protected function history_station() {
		update_option(
			'sloc_weewx_stations',
			array(
				array(
					'id'        => 'weewx',
					'url'       => 'https://weather.example.com/data/current.json',
					'latitude'  => 39.95,
					'longitude' => -75.16,
				),
			)
		);
		$provider = new Weather_Provider_Weewx();
		$provider->set( array( 'station_id' => 'weewx' ) );
		return $provider;
	}

	/**
	 * A current.json whose history covers the last day in 30-minute intervals, ending now.
	 *
	 * @param array $more Other keys.
	 * @return array
	 */
	protected function document_with_history( $more = array() ) {
		$document            = $this->document();
		$start               = time() - DAY_IN_SECONDS;
		$count               = 48;
		$document['history'] = array(
			'start'       => $start,
			'interval'    => 1800,
			'temperature' => array_map( fn( $i ) => 10 + $i / 2, range( 0, $count - 1 ) ),
			'humidity'    => array_fill( 0, $count, 60 ),
			'rain'        => array_fill( 0, $count, 0.5 ),
			'windgust'    => array_fill( 0, $count, 4 ),
		);
		return array_merge( $document, $more );
	}

	/**
	 * A past time within the day is read from current.json's history: the interval the time is in, rain over the
	 * hour, the station and its credit, and no wind direction or AQI (#809).
	 */
	public function test_past_from_current_history() {
		$document = $this->document_with_history();
		$this->mock_http( array( 'weather.example.com/data/current.json' => array( 'body' => $document ) ) );
		$provider   = $this->history_station();
		$start      = $document['history']['start'];
		$time       = $start + 10 * 1800 + 60;
		$conditions = $provider->get_conditions( $time );

		$this->assertSame( 15.0, $conditions['temperature'] );
		$this->assertSame( 60.0, $conditions['humidity'] );
		$this->assertSame( 1.0, $conditions['rain'], 'Two 30-minute intervals make the hour.' );
		$this->assertSame( $start + 11 * 1800, $conditions['timestamp'], 'The end of the interval.' );
		$this->assertSame( 'backyard', $conditions['station_id'] );
		$this->assertSame( 'weewx', $conditions['provider'] );
		$this->assertArrayNotHasKey( 'winddegree', $conditions );
		$this->assertArrayNotHasKey( 'aqi', $conditions );
		$this->assertArrayHasKey( 'attribution', $conditions );

		// Another post at another time of the day doesn't fetch the file again.
		$provider->get_conditions( $start + 3 * 1800 );
		$this->assertCount( 1, $this->http_requests );
	}

	/**
	 * Older times are read from history-week.json, which the file names relative to itself, in hourly intervals.
	 */
	public function test_past_from_week() {
		$week_start = time() - WEEK_IN_SECONDS;
		$week       = array(
			'format'  => 'current-conditions-history',
			'version' => 1,
			'units'   => 'metric',
			'history' => array(
				'start'       => $week_start,
				'interval'    => 3600,
				'temperature' => array_fill( 0, 168, 8.5 ),
				'rain'        => array_fill( 0, 168, 2 ),
				'humidity'    => array_merge( array_fill( 0, 30, 70 ), array( null ), array_fill( 0, 137, 70 ) ),
			),
		);
		$this->mock_http(
			array(
				'weather.example.com/data/history-week.json' => array( 'body' => $week ),
				'weather.example.com/data/current.json' => array( 'body' => $this->document_with_history( array( 'archives' => array( 'week' => 'history-week.json' ) ) ) ),
			)
		);
		$provider   = $this->history_station();
		$conditions = $provider->get_conditions( $week_start + 30 * 3600 + 120 );
		$this->assertSame( 8.5, $conditions['temperature'] );
		$this->assertSame( 2.0, $conditions['rain'], 'One hourly interval is the hour.' );
		$this->assertArrayNotHasKey( 'humidity', $conditions, 'A null interval is left out.' );
		$this->assertSame( 'https://weather.example.com/data/history-week.json', $this->http_requests[1]['url'] );

		$provider->get_conditions( $week_start + 3 * 3600 );
		$this->assertCount( 2, $this->http_requests, 'Both files are kept.' );
	}

	/**
	 * Without a week's history, an older time gives nothing, so the next provider is tried; no station is an error.
	 */
	public function test_past_without_history() {
		$this->mock_http( array( 'weather.example.com/data/current.json' => array( 'body' => $this->document_with_history() ) ) );
		$provider = $this->history_station();
		$this->assertSame( array(), $provider->get_conditions( strtotime( '-3 days' ) ) );

		$this->mock_http( array( 'weather.example.com/data/current.json' => array( 'body' => $this->document() ) ) );
		delete_transient( 'sloc_weewx_' . md5( 'https://weather.example.com/data/current.json' ) );
		$this->assertSame( array(), $provider->get_conditions( strtotime( '-5 hours' ) ), 'A file without history.' );

		$this->assertWPError( $provider->get_conditions( strtotime( '-8 days' ) ), 'Beyond a week.' );

		$other = new Weather_Provider_Weewx();
		$other->set( array( 'station_id' => 'elsewhere' ) );
		$this->assertSame( 'no_results', $other->get_conditions( strtotime( '-5 hours' ) )->get_error_code() );
	}

	/**
	 * A week file that can't be read, or isn't one, is an error.
	 */
	public function test_past_week_errors() {
		$current = $this->document_with_history( array( 'archives' => array( 'week' => 'history-week.json' ) ) );
		$this->mock_http(
			array(
				'history-week.json'                     => array( 'body' => $this->document() ),
				'weather.example.com/data/current.json' => array( 'body' => $current ),
			)
		);
		$this->assertSame( 'station_format', $this->history_station()->get_conditions( strtotime( '-3 days' ) )->get_error_code() );

		$this->mock_http(
			array(
				'history-week.json'                     => new WP_Error( 'http_request_failed', 'Timed out' ),
				'weather.example.com/data/current.json' => array( 'body' => $current ),
			)
		);
		delete_transient( 'sloc_weewx_' . md5( 'https://weather.example.com/data/history-week.json' ) );
		$this->assertSame( 'http_request_failed', $this->history_station()->get_conditions( strtotime( '-4 days' ) )->get_error_code() );
	}

	/**
	 * The nearest station is used for a location, with its distance.
	 */
	public function test_past_nearest_station() {
		$this->mock_http( array( 'weather.example.com/data/current.json' => array( 'body' => $this->document_with_history() ) ) );
		$this->history_station();
		$provider = new Weather_Provider_Weewx();
		$provider->set( 39.951, -75.161 );
		$conditions = $provider->get_conditions( strtotime( '-5 hours' ) );
		$this->assertArrayHasKey( 'temperature', $conditions );
		$this->assertLessThan( 1000, $conditions['distance'] );
	}

	/**
	 * Reading a history: the interval a time is in, totals over the hour, US units, and what isn't covered.
	 */
	public function test_history_conditions() {
		$history  = array(
			'start'             => 1000,
			'interval'          => 600,
			'temperature'       => array( 50, 59, 68, null ),
			'rain'              => array( 0.1, null, 0.2, 0.3 ),
			'lightning_strikes' => array( 0, 0, 0, 0 ),
			'winddegree'        => array( 1, 2, 3, 4 ),
			'not_weather'       => array( 1, 2, 3, 4 ),
			'code'              => array( 800, 800, 800, 800 ),
		);
		$readings = Weather_Provider_Weewx::history_conditions( $history, 1000 + 2 * 600 + 5, 'us' );
		$this->assertEqualsWithDelta( 20, $readings['temperature'], 0.01, 'Fahrenheit to Celsius.' );
		$this->assertEqualsWithDelta( 7.62, $readings['rain'], 0.01, 'Six 10-minute intervals, but only three exist: 0.3 inches.' );
		$this->assertSame( 1000 + 3 * 600, $readings['timestamp'] );
		$this->assertArrayNotHasKey( 'lightning_strikes', $readings, 'No strikes.' );
		$this->assertArrayNotHasKey( 'winddegree', $readings );
		$this->assertArrayNotHasKey( 'not_weather', $readings );
		$this->assertArrayNotHasKey( 'code', $readings );

		$this->assertSame( array( 'rain', 'timestamp' ), array_keys( Weather_Provider_Weewx::history_conditions( $history, 1000 + 3 * 600 ) ), 'A null temperature is left out.' );
		$this->assertNull( Weather_Provider_Weewx::history_conditions( $history, 999 ), 'Before the history.' );
		$this->assertNull( Weather_Provider_Weewx::history_conditions( $history, 1000 + 4 * 600 ), 'After it.' );
		$this->assertSame(
			array(),
			Weather_Provider_Weewx::history_conditions(
				array(
					'start'       => 0,
					'interval'    => 60,
					'temperature' => array( null, 'x' ),
				),
				61
			)
		);
		foreach ( array(
			null,
			'history',
			array( 'start' => 0 ),
			array(
				'start'       => 0,
				'interval'    => 0,
				'temperature' => array( 1 ),
			),
			array(
				'start'    => 'x',
				'interval' => 60,
			),
			array(
				'start'    => 0,
				'interval' => 60,
			),
		) as $invalid ) {
			$this->assertNull( Weather_Provider_Weewx::history_conditions( $invalid, 30 ), wp_json_encode( $invalid ) );
		}
	}

	/**
	 * The station's day is read for the widget: today, the trend, rain, the webcam and alerts, sanitized (#810).
	 */
	public function test_extras() {
		$extras = Weather_Provider_Weewx::parse_extras(
			array(
				'today'    => array(
					'temperature_max'      => 23.1,
					'temperature_max_time' => 1761229800,
					'temperature_min'      => '11.4',
					'windgust_max'         => 'gusty',
					'rain'                 => 3.05,
				),
				'trend'    => array(
					'hours'    => 3,
					'pressure' => -1.2,
				),
				'rain'     => array(
					'last_24h'    => 3.05,
					'storm'       => 3.05,
					'storm_start' => 1761213600,
					'last'        => 1761228000,
				),
				'station'  => array( 'webcam' => 'webcam.jpg' ),
				'forecast' => array(
					'alerts' => array(
						array(
							'event'    => 'Heat <b>Advisory</b>',
							'headline' => 'Heat Advisory until 9 PM<script>alert(1)</script>',
							'severity' => 'Moderate',
							'ends'     => 1761264000,
							'expires'  => 'soon',
						),
						array( 'headline' => 'No event' ),
						'not an alert',
					),
				),
			)
		);
		$this->assertSame(
			array(
				'temperature_max'      => 23.1,
				'temperature_max_time' => 1761229800,
				'temperature_min'      => 11.4,
			),
			$extras['today'],
			'Only numbers, and only the keys the widget uses.'
		);
		$this->assertSame(
			array(
				'hours'    => 3.0,
				'pressure' => -1.2,
			),
			$extras['trend']
		);
		$this->assertSame(
			array(
				'last_24h'    => 3.05,
				'storm'       => 3.05,
				'storm_start' => 1761213600,
			),
			$extras['rain']
		);
		$this->assertSame( 'webcam.jpg', $extras['webcam'] );
		$this->assertCount( 1, $extras['alerts'] );
		$this->assertSame( 'Heat Advisory', $extras['alerts'][0]['event'] );
		$this->assertSame( 'Heat Advisory until 9 PM', $extras['alerts'][0]['headline'] );
		$this->assertSame( 1761264000, $extras['alerts'][0]['ends'] );
		$this->assertArrayNotHasKey( 'expires', $extras['alerts'][0] );

		$this->assertSame( array(), Weather_Provider_Weewx::parse_extras( array() ) );
		$this->assertArrayNotHasKey( 'webcam', Weather_Provider_Weewx::parse_extras( array( 'station' => array( 'webcam' => 'javascript:alert(1)' ) ) ) );
		$this->assertArrayNotHasKey( 'trend', Weather_Provider_Weewx::parse_extras( array( 'trend' => array( 'hours' => 3 ) ) ), 'A trend without changes.' );

		$many = array_fill( 0, 9, array( 'event' => 'Flood Watch' ) );
		$this->assertCount( Weather_Provider_Weewx::MAX_ALERTS, Weather_Provider_Weewx::parse_extras( array( 'forecast' => array( 'alerts' => $many ) ) )['alerts'] );
	}

	/**
	 * A file in US units has its day converted, a change without the scale's offset.
	 */
	public function test_extras_us_units() {
		$extras = Weather_Provider_Weewx::parse_extras(
			array(
				'units' => 'us',
				'today' => array(
					'temperature_max' => 86,
					'windgust_max'    => 10,
				),
				'trend' => array(
					'hours'       => 3,
					'pressure'    => -0.1,
					'temperature' => 9,
				),
				'rain'  => array( 'storm' => 1 ),
			)
		);
		$this->assertEqualsWithDelta( 30, $extras['today']['temperature_max'], 0.01 );
		$this->assertEqualsWithDelta( 4.47, $extras['today']['windgust_max'], 0.01 );
		$this->assertEqualsWithDelta( -3.39, $extras['trend']['pressure'], 0.01 );
		$this->assertEqualsWithDelta( 5, $extras['trend']['temperature'], 0.01 );
		$this->assertEqualsWithDelta( 25.4, $extras['rain']['storm'], 0.01 );
	}

	/**
	 * Current conditions carry the extras, with the webcam made absolute, which aren't stored with a post (#810).
	 */
	public function test_extras_in_conditions() {
		$document                      = $this->document();
		$document['today']             = array( 'temperature_max' => 23.1 );
		$document['station']['webcam'] = 'cam/latest.jpg';
		$this->mock_http( array( 'weather.example.com/data/current.json' => array( 'body' => $document ) ) );
		$conditions = $this->history_station()->get_conditions();
		$this->assertSame( 23.1, $conditions['station_extras']['today']['temperature_max'] );
		$this->assertSame( 'https://weather.example.com/data/cam/latest.jpg', $conditions['station_extras']['webcam'] );
		$this->assertArrayNotHasKey( 'station_health', $conditions );

		$post_id = self::factory()->post->create();
		set_post_weatherdata( $post_id, '', $conditions );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'weather_station_extras' ) );
		$this->assertEquals( $conditions['temperature'], get_post_meta( $post_id, 'weather_temperature', true ) );
	}

	/**
	 * A station's health warnings are shown to administrators as a provider error, and cleared when they go (#810).
	 */
	public function test_health_warnings() {
		delete_option( Sloc_Provider_Errors::OPTION );
		$this->assertSame(
			array( 'Wind sensor battery low', 'Weak signal' ),
			Weather_Provider_Weewx::health_warnings(
				array(
					'health' => array(
						'warnings' => array(
							array( 'message' => 'Wind sensor battery low' ),
							array( 'message' => 'Weak signal' ),
							array( 'message' => 'Weak signal' ),
							array( 'field' => 'none' ),
							'text',
						),
					),
				)
			)
		);
		$this->assertSame( array(), Weather_Provider_Weewx::health_warnings( array() ) );

		$document           = $this->document();
		$document['health'] = array( 'warnings' => array( array( 'message' => 'Wind sensor battery low' ) ) );
		$this->mock_http( array( 'weather.example.com/data/current.json' => array( 'body' => $document ) ) );
		$this->history_station()->get_conditions();
		$errors = Sloc_Provider_Errors::get_errors();
		$this->assertArrayHasKey( 'health:weewx', $errors );
		$this->assertSame( 'station_health', $errors['health:weewx']['code'] );
		$message = Sloc_Provider_Errors::message( $errors['health:weewx'] );
		$this->assertStringContainsString( 'WeeWX station needs attention', $message );
		$this->assertStringContainsString( 'Backyard Weather: Wind sensor battery low', $message );
		$this->assertStringContainsString( 'warnings have cleared', Sloc_Provider_Errors::message( $errors['health:weewx'], true ) );

		$this->mock_http( array( 'weather.example.com/data/current.json' => array( 'body' => $this->document() ) ) );
		$provider = $this->history_station();
		$provider->set_cache_time( 0 );
		$provider->get_conditions();
		$this->assertArrayNotHasKey( 'health:weewx', Sloc_Provider_Errors::get_errors() );
	}

	/**
	 * The provider is registered as a station provider.
	 */
	public function test_registered() {
		$providers = Loc_Config::weather_providers( true );
		$this->assertArrayHasKey( 'weewx', $providers );
		$this->assertSame( 'WeeWX', $providers['weewx']['name'] );
		$this->assertTrue( ( new Weather_Provider_Weewx() )->is_station() );
	}

	/**
	 * Saving reads each new station's file for its ID, name and location, and keeps the URL and credit.
	 */
	public function test_saving_reads_the_file() {
		$this->mock_http( array( 'weather.example.com/current.json' => array( 'body' => $this->document() ) ) );
		$stations = Weather_Provider_Weewx::sanitize_stations(
			array(
				array(
					'url'         => ' https://weather.example.com/current.json ',
					'attribution' => 'Weather from my backyard',
				),
				array( 'url' => '' ),
			)
		);
		$this->assertCount( 1, $stations );
		$this->assertSame( 'backyard', $stations[0]['id'] );
		$this->assertSame( 'Backyard Weather', $stations[0]['name'] );
		$this->assertSame( 39.9524, $stations[0]['latitude'] );
		$this->assertSame( -75.1636, $stations[0]['longitude'] );
		$this->assertSame( 'https://weather.example.com/current.json', $stations[0]['url'] );
		$this->assertSame( 'Weather from my backyard', $stations[0]['attribution'] );
		$this->assertCount( 1, $this->http_requests );

		// Saved again by the plugin, with an ID, it isn't read again.
		Weather_Provider_Weewx::sanitize_stations( $stations );
		$this->assertCount( 1, $this->http_requests );
	}

	/**
	 * A file that can't be read is reported, and the station kept with an ID from its URL.
	 */
	public function test_saving_unreadable_file() {
		$this->mock_http( array( 'weather.example.com' => array( 'body' => array( 'temperature' => 20 ) ) ) );
		$stations = Weather_Provider_Weewx::sanitize_stations( array( array( 'url' => 'https://weather.example.com/current.json' ) ) );
		$this->assertSame( 'weather-example-com', $stations[0]['id'] );
		$this->assertSame( '', $stations[0]['latitude'] );
		$errors = get_settings_errors( 'sloc_weewx_stations' );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'This is not a weewx-current-json file', $errors[0]['message'] );
	}

	/**
	 * Saving the URL of an archive file, such as history-week.json, says to enter current.json instead (#812).
	 */
	public function test_saving_archive_file() {
		global $wp_settings_errors;
		$wp_settings_errors = array();
		$this->mock_http(
			array(
				'weather.example.com' => array(
					'body' => array(
						'format'  => 'current-conditions-history',
						'version' => 1,
					),
				),
			)
		);
		Weather_Provider_Weewx::sanitize_stations( array( array( 'url' => 'https://weather.example.com/history-week.json' ) ) );
		$errors = get_settings_errors( 'sloc_weewx_stations' );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'https://weather.example.com/history-week.json', $errors[0]['message'] );
		$this->assertStringContainsString( 'archive files', $errors[0]['message'] );
		$this->assertStringContainsString( 'current.json', $errors[0]['message'] );
		$this->assertStringNotContainsString( 'unknown format', $errors[0]['message'] );
	}

	/**
	 * Two stations with the same ID get unique IDs, and removed rows are dropped.
	 */
	public function test_unique_ids() {
		$this->mock_http( array( 'example.com' => array( 'body' => $this->document() ) ) );
		$stations = Weather_Provider_Weewx::sanitize_stations(
			array(
				array( 'url' => 'https://one.example.com/current.json' ),
				array( 'url' => 'https://two.example.com/current.json' ),
				array(
					'url'    => 'https://three.example.com/current.json',
					'remove' => '1',
				),
			)
		);
		$this->assertSame( array( 'backyard', 'backyard-2' ), wp_list_pluck( $stations, 'id' ) );
	}

	/**
	 * A WeeWX station gives the weather by ID, or as the nearest station, with its credit.
	 */
	public function test_conditions() {
		$this->mock_http( array( 'weather.example.com' => array( 'body' => $this->document() ) ) );
		update_option( 'sloc_weewx_stations', Weather_Provider_Weewx::sanitize_stations( array( array( 'url' => 'https://weather.example.com/current.json' ) ) ) );

		$provider = new Weather_Provider_Weewx();
		$provider->set( array( 'station_id' => 'backyard' ) );
		$conditions = $provider->get_conditions();
		$this->assertSame( 21.5, $conditions['temperature'] );
		$this->assertSame( 503, $conditions['code'] );
		$this->assertSame( 'weewx', $conditions['provider'] );
		$this->assertSame( 'Weather from Backyard Weather', $conditions['attribution'] );

		$provider = new Weather_Provider_Weewx();
		$provider->set( 39.95, -75.16 );
		$conditions = $provider->get_conditions();
		$this->assertSame( 21.5, $conditions['temperature'] );
		$this->assertLessThan( 1000, $conditions['distance'] );
	}

	/**
	 * Only the current conditions format is accepted, not the earlier custom station format.
	 */
	public function test_requires_the_format() {
		$this->assertSame( 'station_format', Weather_Provider_Weewx::parse_document( array( 'temperature' => 20 ) )->get_error_code() );
		$this->assertSame( 21.5, Weather_Provider_Weewx::parse_document( $this->document() )['temperature'] );
	}

	/**
	 * The WeeWX stations have their own setting and section on the Stations tab, beside the custom stations.
	 */
	public function test_settings() {
		global $wp_settings_sections, $wp_settings_fields;
		require_once ABSPATH . 'wp-admin/includes/template.php';

		Weather_Provider_Weewx::init();
		Weather_Provider_Station::init();
		$settings = get_registered_settings();
		$this->assertSame( 'sloc_stations', $settings['sloc_weewx_stations']['group'] );
		$this->assertSame( 'sloc_stations', $settings['sloc_stations']['group'] );

		Weather_Provider_Weewx::admin_init();
		Weather_Provider_Station::admin_init();
		$this->assertSame( 'WeeWX Stations', $wp_settings_sections['sloc_stations']['sloc_weewx_stations']['title'] );
		$this->assertSame( 'Custom Station Data', $wp_settings_sections['sloc_stations']['sloc_stations']['title'] );
		$this->assertArrayHasKey( 'sloc_weewx_stations', $wp_settings_fields['sloc_stations']['sloc_weewx_stations'] );

		update_option(
			'sloc_weewx_stations',
			array(
				array(
					'id'   => 'backyard',
					'name' => 'Backyard Weather',
					'url'  => 'https://weather.example.com/current.json',
				),
			)
		);
		ob_start();
		Weather_Provider_Weewx::stations_section();
		Weather_Provider_Weewx::stations_callback( array() );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'name="sloc_weewx_stations[0][url]" value="https://weather.example.com/current.json"', $html );
		$this->assertStringContainsString( 'Backyard Weather (backyard)', $html );
		$this->assertStringContainsString( 'name="sloc_weewx_stations[1][url]" value=""', $html );
		$this->assertStringNotContainsString( '[id]', $html, 'The ID is read from the file, not entered.' );
	}
}

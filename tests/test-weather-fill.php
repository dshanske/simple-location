<?php
/**
 * Tests filling in weather readings from Open-Meteo, and the backfill of existing posts (#784).
 *
 * @package Simple_Location
 */

class WeatherFillTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

	/**
	 * A place, with more precision than is sent.
	 */
	const LAT = 39.952412;
	const LNG = -75.163611;

	public function set_up() {
		parent::set_up();
		update_option( 'sloc_measurements', 'metric' );
		update_option( Sloc_Weather_Fill::OPTION, array( 'conditions', 'snow', 'snowdepth', 'cloudiness' ) );
		delete_option( Sloc_Weather_Fill::STATE_OPTION );
		delete_option( 'sloc_openmeteo_api' );
		wp_clear_scheduled_hook( Sloc_Weather_Fill::HOOK );
		Weather_Provider_Sloc_Test::reset();
	}

	/**
	 * Returns an Open-Meteo hourly response covering the UTC days of two times, with the same values each hour.
	 *
	 * @param int   $start  First time.
	 * @param int   $end    Last time.
	 * @param array $values Values keyed by Open-Meteo variable.
	 * @return array Response body.
	 */
	protected function hourly( $start, $end, $values ) {
		$first  = strtotime( gmdate( 'Y-m-d 00:00:00', $start ) . ' UTC' );
		$last   = strtotime( gmdate( 'Y-m-d 23:00:00', $end ) . ' UTC' );
		$hourly = array( 'time' => array() );
		for ( $hour = $first; $hour <= $last; $hour += HOUR_IN_SECONDS ) {
			$hourly['time'][] = $hour;
			foreach ( $values as $variable => $value ) {
				$hourly[ $variable ][] = $value;
			}
		}
		return array( 'hourly' => $hourly );
	}

	/**
	 * Open-Meteo's current conditions.
	 *
	 * @param array $values Values keyed by Open-Meteo variable.
	 * @return array Response body.
	 */
	protected function current( $values ) {
		return array( 'current' => array_merge( array( 'time' => time() ), $values ) );
	}

	/**
	 * Station weather: a temperature, and a condition from the sunlight.
	 *
	 * @return array Weather.
	 */
	protected function station_weather() {
		return array(
			'temperature' => -1.5,
			'code'        => 802,
			'provider'    => 'weewx',
		);
	}

	public function test_sanitize_readings() {
		$this->assertSame( array( 'conditions', 'snow' ), Sloc_Weather_Fill::sanitize_readings( array( 'conditions', 'bogus', 'snow' ) ) );
		$this->assertSame( array(), Sloc_Weather_Fill::sanitize_readings( 'snow' ) );
		update_option( Sloc_Weather_Fill::OPTION, array( 'uv', 'nope' ) );
		$this->assertSame( array( 'uv' ), get_option( Sloc_Weather_Fill::OPTION ) );
	}

	public function test_needs() {
		$readings = array( 'conditions', 'snow' );
		$this->assertTrue( Sloc_Weather_Fill::needs( array( 'temperature' => 1, 'code' => 500 ), $readings ), 'No snow.' );
		$this->assertTrue( Sloc_Weather_Fill::needs( array( 'snow' => 0, 'code' => 800 ), $readings ), 'A sky condition could be precipitation.' );
		$this->assertFalse( Sloc_Weather_Fill::needs( array( 'snow' => 0, 'code' => 500 ), $readings ), '0 is a reading.' );
		$this->assertFalse( Sloc_Weather_Fill::needs( array( 'code' => 800, 'provider' => 'openmeteo' ), $readings ), 'Open-Meteo itself.' );
		$this->assertFalse( Sloc_Weather_Fill::needs( false, $readings ) );
		$this->assertFalse( Sloc_Weather_Fill::needs( array( 'temperature' => 1 ), array() ) );
	}

	public function test_merge_fills_only_missing() {
		$weather = Sloc_Weather_Fill::merge(
			array(
				'temperature' => -1.5,
				'snow'        => 0,
				'provider'    => 'weewx',
			),
			array(
				'temperature' => 3,
				'snow'        => 4,
				'snowdepth'   => 120,
				'cloudiness'  => 90,
				'radiation'   => 50,
			),
			array( 'snow', 'snowdepth', 'cloudiness' )
		);
		$this->assertSame( -1.5, $weather['temperature'], 'Not a chosen reading.' );
		$this->assertSame( 0, $weather['snow'], 'The station\'s 0 is kept.' );
		$this->assertSame( 120, $weather['snowdepth'] );
		$this->assertSame( 90, $weather['cloudiness'] );
		$this->assertArrayNotHasKey( 'radiation', $weather, 'Not chosen.' );
		$this->assertSame( 'openmeteo', $weather['supplement'] );
		$this->assertSame( 'weewx', $weather['provider'] );
	}

	public function test_merge_nothing_to_fill() {
		$weather = Sloc_Weather_Fill::merge( array( 'snow' => 1 ), array( 'snow' => 4 ), array( 'snow' ) );
		$this->assertSame( array( 'snow' => 1 ), $weather, 'No supplement without a change.' );
		$this->assertSame( array( 'snow' => 1 ), Sloc_Weather_Fill::merge( array( 'snow' => 1 ), array(), array( 'snow' ) ) );
	}

	/**
	 * @dataProvider conditions
	 */
	public function test_merge_conditions( $ours, $theirs, $expected ) {
		$weather = Sloc_Weather_Fill::merge(
			null === $ours ? array( 'temperature' => 1 ) : array(
				'code'    => $ours,
				'summary' => 'Ours',
			),
			array(
				'code'    => $theirs,
				'summary' => 'Theirs',
			),
			array( 'conditions' )
		);
		$this->assertSame( $expected, $weather['code'] ?? null );
	}

	public function conditions() {
		return array(
			'none, take theirs'          => array( null, 803, 803 ),
			'sky, theirs is snow'        => array( 802, 601, 601 ),
			'windy, theirs is fog'       => array( 400, 741, 741 ),
			'sky, theirs is sky'         => array( 802, 804, 802 ),
			'rain, theirs is snow'       => array( 500, 601, 500 ),
			'thunderstorm, theirs clear' => array( 211, 800, 211 ),
		);
	}

	public function test_fill_current_weather() {
		$this->mock_http(
			array(
				'api.open-meteo.com' => array(
					'body' => $this->current(
						array(
							'temperature_2m' => 0,
							'snowfall'       => 0.5,
							'snow_depth'     => 0.1,
							'cloud_cover'    => 100,
							'weather_code'   => 71,
						)
					),
				),
			)
		);
		$weather = Sloc_Weather_Fill::fill( $this->station_weather(), self::LAT, self::LNG );
		$this->assertSame( 600, $weather['code'], 'Slight snow fall replaces a sky condition.' );
		$this->assertEquals( 5, $weather['snow'] );
		$this->assertEquals( 100, $weather['snowdepth'] );
		$this->assertEquals( 100, $weather['cloudiness'] );
		$this->assertSame( -1.5, $weather['temperature'] );
		$this->assertSame( 'openmeteo', $weather['supplement'] );

		$query = $this->last_request();
		$this->assertEquals( 39.95, $query['query']['latitude'], 'Rounded to two decimal places.' );
		$this->assertEquals( -75.16, $query['query']['longitude'] );
	}

	public function test_fill_skipped() {
		$this->mock_http( array( '' => array( 'body' => $this->current( array( 'temperature_2m' => 1 ) ) ) ) );
		$complete = array(
			'code'       => 500,
			'snow'       => 0,
			'snowdepth'  => 0,
			'cloudiness' => 100,
		);
		$this->assertSame( $complete, Sloc_Weather_Fill::fill( $complete, self::LAT, self::LNG ), 'Nothing missing.' );

		delete_option( Sloc_Weather_Fill::OPTION );
		$this->assertSame( $this->station_weather(), Sloc_Weather_Fill::fill( $this->station_weather(), self::LAT, self::LNG ), 'Nothing chosen.' );

		$error = new WP_Error( 'x', 'x' );
		$this->assertSame( $error, Sloc_Weather_Fill::fill( $error, self::LAT, self::LNG ) );
		$this->assertCount( 0, $this->http_requests );
	}

	public function test_fill_keeps_weather_on_error() {
		$this->mock_http( array( 'api.open-meteo.com' => new WP_Error( 'down', 'Down' ) ) );
		$this->assertSame( $this->station_weather(), Sloc_Weather_Fill::fill( $this->station_weather(), self::LAT, self::LNG ) );
	}

	public function test_weather_chain_fills() {
		update_option( 'sloc_weather_provider', 'sloc_test' );
		update_option( 'sloc_weather_fallbacks', array() );
		Weather_Provider_Sloc_Test::$conditions = array(
			'temperature' => 2,
			'code'        => 801,
		);
		$this->mock_http( array( 'api.open-meteo.com' => array( 'body' => $this->current( array( 'cloud_cover' => 30, 'weather_code' => 2 ) ) ) ) );
		$weather = Sloc_Provider_Chain::weather( self::LAT, self::LNG );
		$this->assertEquals( 30, $weather['cloudiness'] );
		$this->assertSame( 801, $weather['code'], 'Open-Meteo\'s partly cloudy doesn\'t replace the station\'s sky.' );
		$this->assertSame( 'openmeteo', $weather['supplement'] );
		$this->assertSame( 'sloc_test', $weather['provider'] );
	}

	public function test_credit_includes_supplement() {
		$credit = Sloc_Weather_Data::get_weather_credit_markup(
			array(
				'provider'        => 'weewx',
				'attribution'     => 'Weather from Backyard',
				'attribution_url' => 'https://weather.example.com/',
				'supplement'      => 'openmeteo',
			)
		);
		$this->assertStringContainsString( 'Weather from Backyard', $credit );
		$this->assertStringContainsString( 'Open-Meteo', $credit );
		$this->assertStringNotContainsString( 'Open-Meteo', Sloc_Weather_Data::get_weather_credit_markup( array( 'attribution' => 'Weather from Backyard' ) ) );
	}

	public function test_supplement_stored() {
		$post_id = self::factory()->post->create();
		set_post_weatherdata(
			$post_id,
			'',
			array(
				'temperature' => 1,
				'supplement'  => 'openmeteo',
			)
		);
		$this->assertSame( 'openmeteo', get_post_weatherdata( $post_id, 'supplement' ) );
		$this->assertFalse( Sloc_Weather_Data::has_weather( 'post', self::factory()->post->create( array( 'meta_input' => array( 'weather_supplement' => 'openmeteo' ) ) ) ), 'The supplement alone isn\'t weather.' );
	}

	public function test_runs() {
		$old  = time() - 200 * DAY_IN_SECONDS;
		$runs = Sloc_Weather_Fill::runs(
			array(
				array( 'time' => $old ),
				array( 'time' => $old + 10 * DAY_IN_SECONDS ),
				array( 'time' => $old + 40 * DAY_IN_SECONDS ),
				array( 'time' => time() - 30 * DAY_IN_SECONDS ),
				array( 'time' => time() - DAY_IN_SECONDS ),
			)
		);
		$this->assertSame( array( 2, 1, 2 ), array_map( 'count', $runs ), 'Split after a month, and at the archive limit.' );
	}

	/**
	 * Creates a post at a place with station weather.
	 *
	 * @param int   $time    Post time.
	 * @param float $lat     Latitude.
	 * @param float $lng     Longitude.
	 * @param array $weather Weather. Default station weather.
	 * @return int Post ID.
	 */
	protected function weather_post( $time, $lat = self::LAT, $lng = self::LNG, $weather = null ) {
		$post_id = self::factory()->post->create( array( 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $time ), 'post_date' => gmdate( 'Y-m-d H:i:s', $time ) ) );
		update_post_meta( $post_id, 'geo_latitude', $lat );
		update_post_meta( $post_id, 'geo_longitude', $lng );
		update_post_meta( $post_id, 'geo_public', 0 );
		foreach ( $weather ?? $this->station_weather() as $key => $value ) {
			update_post_meta( $post_id, 'weather_' . $key, $value );
		}
		return $post_id;
	}

	public function test_backfill() {
		$start = strtotime( '-200 days 12:00' );
		$posts = array(
			$this->weather_post( $start ),
			$this->weather_post( $start + 3 * DAY_IN_SECONDS ),
			$this->weather_post( $start + 6 * DAY_IN_SECONDS, self::LAT + 0.001, self::LNG ), // Same rounded place.
		);
		$elsewhere = $this->weather_post( $start + DAY_IN_SECONDS, 40.44, -79.99 );
		$complete  = $this->weather_post(
			$start + 2 * DAY_IN_SECONDS,
			self::LAT,
			self::LNG,
			array(
				'temperature' => 5,
				'code'        => 500,
				'snow'        => 0,
				'snowdepth'   => 0,
				'cloudiness'  => 100,
			)
		);
		$this->mock_http(
			array(
				'archive-api.open-meteo.com' => array(
					'body' => $this->hourly(
						$start,
						$start + 6 * DAY_IN_SECONDS,
						array(
							'temperature_2m' => 0,
							'snowfall'       => 0.2,
							'snow_depth'     => 0.05,
							'cloud_cover'    => 100,
							'weather_code'   => 73,
						)
					),
				),
			)
		);
		$this->assertSame( 5, Sloc_Weather_Fill::remaining() );

		Sloc_Weather_Fill::start();
		$this->assertNotFalse( wp_next_scheduled( Sloc_Weather_Fill::HOOK ) );
		Sloc_Weather_Fill::run_batch();

		$this->assertCount( 2, $this->http_requests, 'One request per rounded place.' );
		foreach ( $this->http_requests as $request ) {
			$this->assertStringContainsString( 'archive-api.open-meteo.com', $request['url'] );
		}
		foreach ( $posts as $post_id ) {
			$this->assertEquals( 2, get_post_weatherdata( $post_id, 'snow' ), $post_id );
			$this->assertEquals( 50, get_post_weatherdata( $post_id, 'snowdepth' ) );
			$this->assertEquals( 601, get_post_weatherdata( $post_id, 'code' ), 'Moderate snow replaces the sunlight\'s scattered clouds.' );
			$this->assertSame( 'openmeteo', get_post_weatherdata( $post_id, 'supplement' ) );
			$this->assertEquals( -1.5, get_post_weatherdata( $post_id, 'temperature' ), 'The station\'s temperature is kept.' );
			$this->assertNotEmpty( get_post_meta( $post_id, Sloc_Weather_Fill::CHECKED_META, true ) );
		}
		$this->assertEquals( 0, get_post_weatherdata( $complete, 'snow' ) );
		$this->assertSame( '', get_post_meta( $complete, 'weather_supplement', true ), 'Nothing was missing.' );
		$this->assertNotEmpty( get_post_meta( $complete, Sloc_Weather_Fill::CHECKED_META, true ) );

		$state = Sloc_Weather_Fill::get_state();
		$this->assertSame( 'done', $state['status'] );
		$this->assertSame( 5, $state['checked'] );
		$this->assertSame( 4, $state['filled'] );
		$this->assertSame( 0, Sloc_Weather_Fill::remaining() );
		$this->assertNotEmpty( get_post_meta( $elsewhere, Sloc_Weather_Fill::CHECKED_META, true ) );
	}

	public function test_backfill_stops_on_error() {
		$post_id = $this->weather_post( strtotime( '-200 days 12:00' ) );
		$this->mock_http(
			array(
				'archive-api.open-meteo.com' => array(
					'code' => 429,
					'body' => array(
						'error'  => true,
						'reason' => 'Daily API request limit exceeded.',
					),
				),
			)
		);
		Sloc_Weather_Fill::start();
		Sloc_Weather_Fill::run_batch();
		$state = Sloc_Weather_Fill::get_state();
		$this->assertSame( 'stopped', $state['status'] );
		$this->assertSame( 'Daily API request limit exceeded.', $state['error'] );
		$this->assertSame( '', get_post_meta( $post_id, Sloc_Weather_Fill::CHECKED_META, true ), 'Still to check.' );
		$this->assertSame( 1, Sloc_Weather_Fill::remaining() );
	}

	public function test_backfill_needs_readings() {
		delete_option( Sloc_Weather_Fill::OPTION );
		Sloc_Weather_Fill::start();
		Sloc_Weather_Fill::run_batch();
		$this->assertSame( 'stopped', Sloc_Weather_Fill::get_state()['status'] );
	}

	public function test_backfill_batches() {
		$start = strtotime( '-200 days 12:00' );
		for ( $i = 0; $i < Sloc_Weather_Fill::BATCH + 1; $i++ ) {
			$this->weather_post( $start + $i * HOUR_IN_SECONDS );
		}
		$this->mock_http( array( 'archive-api.open-meteo.com' => array( 'body' => $this->hourly( $start, $start + 3 * DAY_IN_SECONDS, array( 'cloud_cover' => 10 ) ) ) ) );
		Sloc_Weather_Fill::start();
		wp_clear_scheduled_hook( Sloc_Weather_Fill::HOOK );
		Sloc_Weather_Fill::run_batch();
		$this->assertSame( 'running', Sloc_Weather_Fill::get_state()['status'] );
		$this->assertNotFalse( wp_next_scheduled( Sloc_Weather_Fill::HOOK ), 'The next batch is scheduled.' );
		$this->assertSame( 1, Sloc_Weather_Fill::remaining() );
		Sloc_Weather_Fill::run_batch();
		$this->assertSame( 'done', Sloc_Weather_Fill::get_state()['status'] );
	}

	public function test_stop_continue_and_recheck() {
		$post_id = $this->weather_post( strtotime( '-200 days 12:00' ) );
		update_post_meta( $post_id, Sloc_Weather_Fill::CHECKED_META, 1 );
		update_option(
			Sloc_Weather_Fill::STATE_OPTION,
			array(
				'status'  => 'running',
				'checked' => 7,
				'filled'  => 3,
				'error'   => '',
			)
		);
		Sloc_Weather_Fill::stop();
		$this->assertSame( 'stopped', Sloc_Weather_Fill::get_state()['status'] );
		$this->assertFalse( wp_next_scheduled( Sloc_Weather_Fill::HOOK ) );

		Sloc_Weather_Fill::start();
		$this->assertSame( 7, Sloc_Weather_Fill::get_state()['checked'], 'Continuing keeps the counts.' );
		$this->assertSame( 0, Sloc_Weather_Fill::remaining() );

		Sloc_Weather_Fill::start( true );
		$this->assertSame( 0, Sloc_Weather_Fill::get_state()['checked'] );
		$this->assertSame( 1, Sloc_Weather_Fill::remaining(), 'Checked posts are checked again.' );
		wp_clear_scheduled_hook( Sloc_Weather_Fill::HOOK );
	}

	public function test_handlers_need_permission() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->expectException( 'WPDieException' );
		Sloc_Weather_Fill::handle_start();
	}

	public function test_page() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		Sloc_Weather_Fill::page();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'name="action" value="sloc_weather_fill_start"', $html );
		$this->assertStringContainsString( 'Conditions (summary and icon)', $html );

		delete_option( Sloc_Weather_Fill::OPTION );
		ob_start();
		Sloc_Weather_Fill::page();
		$html = ob_get_clean();
		$this->assertStringNotContainsString( 'sloc_weather_fill_start', $html, 'Nothing to fill.' );
		$this->assertStringContainsString( 'notice-warning', $html );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		ob_start();
		Sloc_Weather_Fill::page();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_settings_field() {
		ob_start();
		Sloc_Weather_Fill::readings_callback();
		$html = ob_get_clean();
		// WordPress prints checked='checked', ClassicPress checked.
		$this->assertMatchesRegularExpression( '#name="sloc_weather_fill\[\]" value="snow"\s+checked#', $html );
		$this->assertMatchesRegularExpression( '#value="radiation"\s+/>#', $html );
	}
}

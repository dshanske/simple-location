<?php
/**
 * Tests for provider chains and provider errors (#490).
 *
 * @package Simple_Location
 */

/**
 * Provider chain tests.
 */
class ProviderChainTest extends WP_UnitTestCase {

	/**
	 * Philadelphia.
	 */
	const LAT = 39.9524;
	const LNG = -75.1636;

	/**
	 * Calls of the provider error actions: [ action, message, record ].
	 *
	 * @var array[]
	 */
	protected $notifications = array();

	/**
	 * The test providers in a chain, without recorded errors.
	 */
	public function set_up() {
		parent::set_up();
		Weather_Provider_Sloc_Test::reset();
		update_option( 'sloc_weather_provider', 'sloc_test' );
		update_option( 'sloc_weather_fallbacks', array( 'sloc_test_fallback' ) );
		delete_option( Sloc_Provider_Errors::OPTION );
		$this->notifications = array();
		add_action( 'sloc_provider_error', array( $this, 'notified_error' ), 10, 2 );
		add_action( 'sloc_provider_error_resolved', array( $this, 'notified_resolved' ), 10, 2 );
	}

	/**
	 * Restore the shared test providers.
	 */
	public function tear_down() {
		Loc_Config::weather_provider( 'sloc_test' )->set_region( false );
		Loc_Config::weather_provider( 'sloc_test_fallback' )->set_region( false );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * Records a `sloc_provider_error` call.
	 *
	 * @param string $message Message.
	 * @param array  $record  Record.
	 */
	public function notified_error( $message, $record ) {
		$this->notifications[] = array( 'error', $message, $record );
	}

	/**
	 * Records a `sloc_provider_error_resolved` call.
	 *
	 * @param string $message Message.
	 * @param array  $record  Record.
	 */
	public function notified_resolved( $message, $record ) {
		$this->notifications[] = array( 'resolved', $message, $record );
	}

	/**
	 * The chain is the provider and its fallbacks, in order, without repeats or unknown providers, at most three.
	 */
	public function test_slugs() {
		$this->assertSame( array( 'sloc_test', 'sloc_test_fallback' ), Sloc_Provider_Chain::slugs( 'weather' ) );

		// As if saved without the setting's sanitizing, such as by another plugin.
		remove_all_filters( 'sanitize_option_sloc_weather_fallbacks' );
		update_option( 'sloc_weather_fallbacks', array( 'gone', 'sloc_test', 'sloc_test_fallback', 'station' ) );
		$this->assertSame( array( 'sloc_test', 'sloc_test_fallback', 'station' ), Sloc_Provider_Chain::slugs( 'weather' ) );

		update_option( 'sloc_weather_provider', 'none' );
		update_option( 'sloc_weather_fallbacks', 'not a list' );
		$this->assertSame( array(), Sloc_Provider_Chain::slugs( 'weather' ) );
		$this->assertSame( array(), Sloc_Provider_Chain::slugs( 'maps' ), 'Maps are never chained.' );

		$filter = fn() => array( 'sloc_test_fallback', 'unknown', 'sloc_test', 'station', 'weewx' );
		add_filter( 'sloc_provider_chain', $filter );
		$this->assertSame( array( 'sloc_test_fallback', 'sloc_test', 'station' ), Sloc_Provider_Chain::slugs( 'weather' ) );
		remove_filter( 'sloc_provider_chain', $filter );
	}

	/**
	 * The first provider that answers wins; errors and empty answers move on to the next.
	 */
	public function test_first_answer_wins() {
		$conditions = Sloc_Provider_Chain::weather( self::LAT, self::LNG, 123 );
		$this->assertSame( 20, $conditions['temperature'] );
		$this->assertSame( 'sloc_test', $conditions['provider'] );
		$this->assertSame( array( 'sloc_test' ), Weather_Provider_Sloc_Test::$calls );
		$this->assertSame( 123, Weather_Provider_Sloc_Test::$time );
		$this->assertSame( self::LAT, Weather_Provider_Sloc_Test::$location['latitude'] );

		foreach ( array( new WP_Error( 'down', 'Down' ), array(), null ) as $first ) {
			Weather_Provider_Sloc_Test::$calls                  = array();
			Weather_Provider_Sloc_Test::$responses['sloc_test'] = $first;
			$conditions = Sloc_Provider_Chain::weather( self::LAT, self::LNG );
			$this->assertSame( 'sloc_test_fallback', $conditions['provider'] );
			$this->assertSame( array( 'sloc_test', 'sloc_test_fallback' ), Weather_Provider_Sloc_Test::$calls );
		}
	}

	/**
	 * When every provider fails, the last error is returned; with only empty answers, the empty answer.
	 */
	public function test_all_fail() {
		Weather_Provider_Sloc_Test::$responses = array(
			'sloc_test'          => new WP_Error( 'first', 'First' ),
			'sloc_test_fallback' => new WP_Error( 'second', 'Second' ),
		);
		$this->assertSame( 'second', Sloc_Provider_Chain::weather( self::LAT, self::LNG )->get_error_code() );

		Weather_Provider_Sloc_Test::$responses['sloc_test_fallback'] = array();
		$this->assertSame( 'first', Sloc_Provider_Chain::weather( self::LAT, self::LNG )->get_error_code(), 'An error beats an empty answer.' );

		Weather_Provider_Sloc_Test::$responses = array(
			'sloc_test'          => array(),
			'sloc_test_fallback' => array(),
		);
		$this->assertSame( array(), Sloc_Provider_Chain::weather( self::LAT, self::LNG ) );

		update_option( 'sloc_weather_provider', 'none' );
		delete_option( 'sloc_weather_fallbacks' );
		$this->assertSame( 'no_provider', Sloc_Provider_Chain::weather( self::LAT, self::LNG )->get_error_code() );
	}

	/**
	 * A provider is skipped without a request outside its region, or for a time it has no weather for.
	 */
	public function test_skips() {
		Loc_Config::weather_provider( 'sloc_test' )->set_region( 'GB' );
		$conditions = Sloc_Provider_Chain::weather( self::LAT, self::LNG );
		$this->assertSame( 'sloc_test_fallback', $conditions['provider'] );
		$this->assertSame( array( 'sloc_test_fallback' ), Weather_Provider_Sloc_Test::$calls, 'Philadelphia is outside GB.' );

		Weather_Provider_Sloc_Test::$calls = array();
		$this->assertSame( 'sloc_test', Sloc_Provider_Chain::weather( 51.5, -0.12 )['provider'], 'London is in GB.' );

		Loc_Config::weather_provider( 'sloc_test' )->set_region( array( 'us', 'CA' ) );
		$this->assertSame( 'sloc_test', Sloc_Provider_Chain::weather( self::LAT, self::LNG )['provider'] );

		Weather_Provider_Sloc_Test::$calls    = array();
		Weather_Provider_Sloc_Test::$supports = array( 'sloc_test' => false );
		$this->assertSame( 'sloc_test_fallback', Sloc_Provider_Chain::weather( self::LAT, self::LNG, strtotime( '-1 year' ) )['provider'] );
		$this->assertSame( array( 'sloc_test_fallback' ), Weather_Provider_Sloc_Test::$calls );

		Weather_Provider_Sloc_Test::$supports['sloc_test_fallback'] = false;
		$this->assertSame( 'out_of_range', Sloc_Provider_Chain::weather( self::LAT, self::LNG )->get_error_code() );
	}

	/**
	 * A lookup leaves the shared provider as it was: its station and cache time (#681).
	 */
	public function test_shared_provider_untouched() {
		$shared = Loc_Config::weather_provider( 'sloc_test' );
		$shared->set( array( 'station_id' => 'KNYC' ) );
		Sloc_Provider_Chain::weather( self::LAT, self::LNG, null, 5 );
		$this->assertSame( 'KNYC', $shared->get_station_id() );
		$shared->set( array( 'station_id' => '' ) );
	}

	/**
	 * The country of a location comes from the nearest timezone, without a request.
	 */
	public function test_country_for_location() {
		$this->assertSame( 'US', Sloc_Provider::country_for_location( self::LAT, self::LNG ) );
		$this->assertSame( 'GB', Sloc_Provider::country_for_location( 51.5, -0.12 ) );
		$this->assertSame( 'JP', Sloc_Provider::country_for_location( 35.68, 139.69 ) );
		$this->assertSame( '', Sloc_Provider::country_for_location( 0, -30 ), 'Mid-Atlantic has no country.' );

		$provider = new Weather_Provider_Sloc_Test();
		$provider->set_region( 'GB' );
		$this->assertTrue( $provider->covers(), 'Without a location, a provider is tried.' );
		$provider->set( 0, -30 );
		$this->assertTrue( $provider->covers(), 'Where the country is unknown, a provider is tried.' );
		$this->assertTrue( ( new Weather_Provider_NWSUS() )->covers() );
	}

	/**
	 * Station providers cover locations within range of a station, and only times near now.
	 */
	public function test_station_coverage() {
		update_option(
			'sloc_stations',
			array(
				array(
					'id'        => 'home',
					'url'       => 'https://home.example.com/current.json',
					'latitude'  => self::LAT,
					'longitude' => self::LNG,
				),
			)
		);
		$provider = new Weather_Provider_Station();
		$provider->set( self::LAT + 0.05, self::LNG );
		$this->assertTrue( $provider->covers() );
		$provider->set( self::LAT + 1, self::LNG );
		$this->assertFalse( $provider->covers(), 'About 110 km away.' );
		$provider->set( array( 'station_id' => 'home' ) );
		$this->assertTrue( $provider->covers(), 'A station asked for by ID is always tried.' );

		$this->assertTrue( $provider->supports_time() );
		$this->assertTrue( $provider->supports_time( time() - 1800 ) );
		$this->assertFalse( $provider->supports_time( strtotime( '-1 day' ) ) );
	}

	/**
	 * The fallbacks setting keeps up to two known providers, without repeats or None.
	 */
	public function test_sanitize_fallbacks() {
		$this->assertSame( array( 'sloc_test', 'station' ), Sloc_Provider_Chain::sanitize_weather_fallbacks( array( 'none', 'sloc_test', 'sloc_test', 'unknown', 'station', 'weewx' ) ) );
		$this->assertSame( array(), Sloc_Provider_Chain::sanitize_weather_fallbacks( 'sloc_test' ) );
		$this->assertSame( array(), Sloc_Provider_Chain::sanitize_weather_fallbacks( array( array( 'sloc_test' ), 5 ) ) );
	}

	/**
	 * The single fallback setting from before 6.1.0 becomes the fallbacks, once.
	 */
	public function test_migrate() {
		delete_option( 'sloc_weather_fallbacks' );
		update_option( 'sloc_fallback_weather_provider', 'sloc_test_fallback' );
		Sloc_Provider_Chain::migrate();
		$this->assertSame( array( 'sloc_test_fallback' ), get_option( 'sloc_weather_fallbacks' ) );
		$this->assertNull( get_option( 'sloc_fallback_weather_provider', null ) );

		delete_option( 'sloc_weather_fallbacks' );
		update_option( 'sloc_fallback_weather_provider', 'none' );
		Sloc_Provider_Chain::migrate();
		$this->assertSame( array(), get_option( 'sloc_weather_fallbacks' ) );

		update_option( 'sloc_weather_fallbacks', array( 'station' ) );
		update_option( 'sloc_fallback_weather_provider', 'sloc_test' );
		Sloc_Provider_Chain::migrate();
		$this->assertSame( array( 'station' ), get_option( 'sloc_weather_fallbacks' ), 'Fallbacks already set are kept.' );
		$this->assertNull( get_option( 'sloc_fallback_weather_provider', null ) );
	}

	/**
	 * The settings show a select for the 2nd and 3rd providers, with the saved ones selected.
	 */
	public function test_fallbacks_field() {
		update_option( 'sloc_weather_fallbacks', array( 'sloc_test_fallback' ) );
		ob_start();
		Sloc_Provider_Chain::fallbacks_callback(
			array(
				'label_for'   => 'sloc_weather_fallbacks',
				'type'        => 'weather',
				'description' => 'Tried in order.',
			)
		);
		$html = ob_get_clean();
		$this->assertSame( 2, substr_count( $html, '<select name="sloc_weather_fallbacks[]"' ) );
		$this->assertStringContainsString( 'id="sloc_weather_fallbacks"', $html );
		// WordPress writes selected='selected', ClassicPress a bare selected.
		$this->assertSame( 1, preg_match_all( '/<option value="sloc_test_fallback" selected(=\'selected\')?>/', $html ) );
		$this->assertSame( 1, preg_match_all( '/<option value="none" selected(=\'selected\')?>/', $html ), 'The 3rd is None.' );
		$this->assertStringContainsString( 'Tried in order.', $html );
	}

	/**
	 * Errors an administrator can fix: missing settings, a rejected key, payment or quota, and own stations' failures.
	 */
	public function test_actionable() {
		$provider = new Weather_Provider_Sloc_Test();
		$station  = new Weather_Provider_Station();
		$status   = fn( $code ) => new WP_Error( 'invalid_response', 'HTTP', array( 'status' => $code ) );
		$this->assertTrue( Sloc_Provider_Errors::is_actionable( new WP_Error( 'missing_api_key', 'No key' ), $provider ) );
		foreach ( array( 401, 402, 403, 429 ) as $code ) {
			$this->assertTrue( Sloc_Provider_Errors::is_actionable( $status( $code ), $provider ), $code );
		}
		$this->assertFalse( Sloc_Provider_Errors::is_actionable( $status( 500 ), $provider ) );
		$this->assertFalse( Sloc_Provider_Errors::is_actionable( new WP_Error( 'http_request_failed', 'Down' ), $provider ) );
		$this->assertFalse( Sloc_Provider_Errors::is_actionable( new WP_Error( 'no_results', 'None' ), $provider ) );
		$this->assertTrue( Sloc_Provider_Errors::is_actionable( new WP_Error( 'http_request_failed', 'Down' ), $station ) );
		$this->assertTrue( Sloc_Provider_Errors::is_actionable( $status( 500 ), new Weather_Provider_Weewx() ) );
		$this->assertTrue( Sloc_Provider_Errors::is_actionable( new WP_Error( 'station_format', 'Format' ), $station ) );

		add_filter( 'sloc_provider_error_actionable', '__return_true' );
		$this->assertTrue( Sloc_Provider_Errors::is_actionable( new WP_Error( 'no_results', 'None' ), $provider ) );
		remove_filter( 'sloc_provider_error_actionable', '__return_true' );
	}

	/**
	 * A failing provider's error is recorded and notified once, on one line, and cleared and notified when it answers.
	 */
	public function test_record_and_resolve() {
		Weather_Provider_Sloc_Test::$responses['sloc_test'] = new WP_Error( 'invalid_response', "The service returned HTTP status 401:\nInvalid key", array( 'status' => 401 ) );
		$this->assertSame( 'sloc_test_fallback', Sloc_Provider_Chain::weather( self::LAT, self::LNG )['provider'], 'The next provider is used.' );

		$errors = Sloc_Provider_Errors::get_errors();
		$this->assertSame( array( 'weather:sloc_test' ), array_keys( $errors ) );
		$this->assertSame( 'invalid_response', $errors['weather:sloc_test']['code'] );
		$this->assertSame( 1, $errors['weather:sloc_test']['count'] );
		$this->assertCount( 1, $this->notifications );
		list( $action, $message ) = $this->notifications[0];
		$this->assertSame( 'error', $action );
		$this->assertSame( 'Simple Location: Test Weather weather lookups are failing: The service returned HTTP status 401: Invalid key', $message );
		$this->assertStringNotContainsString( "\n", $message );
		$this->assertArrayNotHasKey( Sloc_Provider_Errors::OPTION, wp_load_alloptions(), 'The errors are not autoloaded.' );

		// Again within a minute: not written or notified again.
		Sloc_Provider_Chain::weather( self::LAT, self::LNG );
		$this->assertSame( 1, Sloc_Provider_Errors::get_errors()['weather:sloc_test']['count'] );
		$this->assertCount( 1, $this->notifications );

		// Later: counted, still not notified.
		$errors                              = Sloc_Provider_Errors::get_errors();
		$errors['weather:sloc_test']['last'] = time() - 120;
		update_option( Sloc_Provider_Errors::OPTION, $errors, false );
		Sloc_Provider_Chain::weather( self::LAT, self::LNG );
		$this->assertSame( 2, Sloc_Provider_Errors::get_errors()['weather:sloc_test']['count'] );
		$this->assertCount( 1, $this->notifications );

		// A different error is new.
		Weather_Provider_Sloc_Test::$responses['sloc_test'] = new WP_Error( 'missing_api_key', 'No key' );
		Sloc_Provider_Chain::weather( self::LAT, self::LNG );
		$this->assertSame( 1, Sloc_Provider_Errors::get_errors()['weather:sloc_test']['count'] );
		$this->assertCount( 2, $this->notifications );

		// Answering clears it.
		unset( Weather_Provider_Sloc_Test::$responses['sloc_test'] );
		$this->assertSame( 'sloc_test', Sloc_Provider_Chain::weather( self::LAT, self::LNG )['provider'] );
		$this->assertSame( array(), Sloc_Provider_Errors::get_errors() );
		$this->assertCount( 3, $this->notifications );
		$this->assertSame( array( 'resolved', 'Simple Location: Test Weather weather lookups work again.' ), array_slice( $this->notifications[2], 0, 2 ) );

		// Not again, without an error.
		Sloc_Provider_Chain::weather( self::LAT, self::LNG );
		$this->assertCount( 3, $this->notifications );
	}

	/**
	 * When the site's own station can't be read, the administrator is told and the next provider is used.
	 */
	public function test_station_down_reported() {
		update_option(
			'sloc_weewx_stations',
			array(
				array(
					'id'        => 'home',
					'name'      => 'Home',
					'url'       => 'https://home.example.com/current.json',
					'latitude'  => self::LAT,
					'longitude' => self::LNG,
				),
			)
		);
		update_option( 'sloc_weather_provider', 'weewx' );
		$block = fn() => new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect' );
		add_filter( 'pre_http_request', $block );
		$conditions = Sloc_Provider_Chain::weather( self::LAT, self::LNG );
		remove_filter( 'pre_http_request', $block );

		$this->assertSame( 'sloc_test_fallback', $conditions['provider'] );
		$this->assertArrayHasKey( 'weather:weewx', Sloc_Provider_Errors::get_errors() );
		$this->assertSame( 'Simple Location: WeeWX (beta) weather lookups are failing: cURL error 7: Failed to connect', $this->notifications[0][1] );
	}

	/**
	 * Errors that aren't for the administrator, such as a service being down, aren't recorded.
	 */
	public function test_other_errors_not_recorded() {
		Weather_Provider_Sloc_Test::$responses['sloc_test'] = new WP_Error( 'http_request_failed', 'Down' );
		Sloc_Provider_Chain::weather( self::LAT, self::LNG );
		$this->assertSame( array(), Sloc_Provider_Errors::get_errors() );
		$this->assertSame( array(), $this->notifications );
	}

	/**
	 * The one-line message can be filtered.
	 */
	public function test_message_filter() {
		$filter = fn( $message, $record, $resolved ) => ( $resolved ? 'OK ' : 'FAIL ' ) . $record['provider'];
		add_filter( 'sloc_provider_error_message', $filter, 10, 3 );
		Sloc_Provider_Errors::record( 'weather', new Weather_Provider_Sloc_Test(), new WP_Error( 'missing_api_key', 'No key' ) );
		Sloc_Provider_Errors::resolve( 'weather', new Weather_Provider_Sloc_Test() );
		remove_filter( 'sloc_provider_error_message', $filter );
		$this->assertSame( 'FAIL sloc_test', $this->notifications[0][1] );
		$this->assertSame( 'OK sloc_test', $this->notifications[1][1] );
	}

	/**
	 * Administrators see undismissed errors on the Dashboard and the plugin's settings; dismissing hides them until a new one.
	 */
	public function test_admin_notice() {
		Sloc_Provider_Errors::record( 'weather', new Weather_Provider_Sloc_Test(), new WP_Error( 'missing_api_key', 'No key' ) );
		$notice = function () {
			ob_start();
			Sloc_Provider_Errors::admin_notice();
			return ob_get_clean();
		};

		set_current_screen( 'dashboard' );
		$this->assertSame( '', $notice(), 'Not for users who cannot manage options.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$html = $notice();
		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'Test Weather weather lookups are failing: No key', $html );
		$this->assertStringContainsString( '(1 time, from ', $html );
		$this->assertStringContainsString( 'action=sloc_dismiss_provider_errors', $html );
		$this->assertStringContainsString( '_wpnonce=', $html );

		set_current_screen( 'edit-post' );
		$this->assertSame( '', $notice(), 'Only on the Dashboard and the settings.' );
		set_current_screen( 'settings_page_simloc' );
		$this->assertStringContainsString( 'notice-error', $notice() );

		Sloc_Provider_Errors::dismiss();
		$this->assertSame( '', $notice() );
		$this->assertCount( 1, Sloc_Provider_Errors::get_errors(), 'Dismissed errors are kept for Site Health.' );

		Sloc_Provider_Errors::record( 'weather', new Weather_Provider_Sloc_Test(), new WP_Error( 'invalid_response', 'Quota', array( 'status' => 429 ) ) );
		$this->assertStringContainsString( 'Quota', $notice(), 'A new error shows again.' );
	}

	/**
	 * Site Health reports recorded errors, dismissed or not.
	 */
	public function test_site_health() {
		$tests = Sloc_Provider_Errors::site_status_tests( array( 'direct' => array() ) );
		$this->assertArrayHasKey( 'sloc_providers', $tests['direct'] );

		$this->assertSame( 'good', Sloc_Provider_Errors::site_health_test()['status'] );

		Sloc_Provider_Errors::record( 'weather', new Weather_Provider_Sloc_Test(), new WP_Error( 'missing_api_key', 'No key' ) );
		Sloc_Provider_Errors::dismiss();
		$result = Sloc_Provider_Errors::site_health_test();
		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'Test Weather weather lookups are failing: No key', $result['description'] );
		$this->assertStringContainsString( 'page=simloc', $result['actions'] );
	}

	/**
	 * Weather lookups everywhere use the chain: by location, the weather widget, saving a post, and REST.
	 */
	public function test_callers_use_the_chain() {
		Weather_Provider_Sloc_Test::$responses['sloc_test'] = new WP_Error( 'down', 'Down' );

		$this->assertSame( 'sloc_test_fallback', Sloc_Weather_Data::get_weather_by_location( self::LAT, self::LNG )['provider'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$request = new WP_REST_Request( 'GET', '/sloc_geo/1.0/weather' );
		$request->set_param( 'latitude', self::LAT );
		$request->set_param( 'longitude', self::LNG );
		$this->assertSame( 'sloc_test_fallback', REST_Geo::weather( $request )['provider'] );

		$request->set_param( 'provider', 'sloc_test' );
		$this->assertSame( 'down', REST_Geo::weather( $request )->get_error_code(), 'A provider asked for by name is the only one tried.' );

		$post_id = self::factory()->post->create();
		set_post_geodata(
			$post_id,
			'',
			array(
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			)
		);
		$this->assertSame( $post_id, Geo_Data::bulk_edit_lookup_location( $post_id ) );
		$this->assertSame( 'sloc_test_fallback', get_post_weatherdata( $post_id, 'provider' ) );
	}
}

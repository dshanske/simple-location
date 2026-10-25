<?php
/**
 * Tests for the location providers that read a user's own location history service: Dawarich and GeoPulse,
 * and their base class, Location_Provider_Account.
 *
 * @package Simple_Location
 */

/**
 * Location account provider tests.
 */
class LocationAccountsTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

	/**
	 * An author with Dawarich and GeoPulse accounts, logged in.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Known settings and accounts for every test.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_zoom', 14 );
		$this->user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		update_user_meta( $this->user_id, 'dawarich_url', 'https://dawarich.example.com' );
		update_user_meta( $this->user_id, 'dawarich_api', 'DAWARICHKEY' );
		update_user_meta( $this->user_id, 'geopulse_url', 'https://geopulse.example.com' );
		update_user_meta( $this->user_id, 'geopulse_api', 'gp_sa_TOKEN' );
		wp_set_current_user( $this->user_id );
	}

	/**
	 * Clears the form.
	 */
	public function tear_down() {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Both providers are registered, and can update in the background.
	 */
	public function test_providers_registered() {
		$providers = Loc_Config::geolocation_providers();
		foreach ( array( 'dawarich', 'geopulse' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $providers );
		}
		$this->assertTrue( ( new Location_Provider_Dawarich() )->background() );
		$this->assertTrue( ( new Location_Provider_GeoPulse() )->background() );
	}

	/**
	 * Dawarich returns the user's latest point, with speed in meters per second and the point's time.
	 */
	public function test_dawarich_latest() {
		$fixture = $this->fixture( 'location/dawarich-points.json' );
		$this->mock_http( array( 'dawarich.example.com/api/v1/points' => array( 'body' => $fixture ) ) );
		$provider = new Location_Provider_Dawarich();
		$this->assertNull( $provider->retrieve() );

		$request = $this->last_request();
		$this->assertSame( 'https://dawarich.example.com/api/v1/points', $request['base'] );
		$this->assertSame( 'DAWARICHKEY', $request['query']['api_key'] );
		$this->assertSame( 'desc', $request['query']['order'] );
		$this->assertSame( '1', $request['query']['per_page'] );
		$this->assertArrayNotHasKey( 'end_at', $request['query'] );

		$point    = $fixture[0];
		$location = $provider->get();
		$this->assertSame( $point['latitude'], $location['latitude'] );
		$this->assertSame( $point['longitude'], $location['longitude'] );
		$this->assertSame( (float) $point['altitude'], $location['altitude'] );
		$this->assertSame( (float) $point['accuracy'], $location['accuracy'] );
		$this->assertSame( (float) $point['vertical_accuracy'], $location['altitude_accuracy'] );
		$this->assertSame( round( $point['velocity'] / 3.6, 2 ), $location['speed'] );
		$this->assertSame( gmdate( DATE_W3C, $point['timestamp'] ), $location['time'] );
	}

	/**
	 * Dawarich looks up the latest point before a time, in UTC, and accepts coordinates sent as strings.
	 */
	public function test_dawarich_before() {
		$fixture                 = $this->fixture( 'location/dawarich-points.json' );
		$fixture[0]['latitude']  = '39.9524';
		$fixture[0]['longitude'] = '-75.1636';
		$this->mock_http( array( 'dawarich.example.com' => array( 'body' => $fixture ) ) );
		$provider = new Location_Provider_Dawarich();
		$this->assertNull( $provider->retrieve( '2025-07-01T12:00:00-04:00' ) );
		$this->assertSame( '2025-07-01T16:00:00Z', $this->last_request()['query']['end_at'] );
		$this->assertSame( 39.9524, $provider->get()['latitude'] );

		$provider->retrieve( 1751385600 );
		$this->assertSame( '2025-07-01T16:00:00Z', $this->last_request()['query']['end_at'] );
		$this->assertSame( 'invalid_time', $provider->retrieve( 'not a time' )->get_error_code() );
	}

	/**
	 * Dawarich: no points, bad coordinates and refused requests aren't a location.
	 */
	public function test_dawarich_errors() {
		$provider = new Location_Provider_Dawarich();

		$this->mock_http( array( 'dawarich.example.com' => array( 'body' => array() ) ) );
		$this->assertFalse( $provider->retrieve() );
		$this->assertFalse( $provider->get_geouri() );

		$this->mock_http( array( 'dawarich.example.com' => array( 'body' => array( array( 'latitude' => 120, 'longitude' => 10 ) ) ) ) );
		$this->assertFalse( $provider->retrieve() );

		$this->mock_http(
			array(
				'dawarich.example.com' => array(
					'code' => 401,
					'body' => array( 'error' => 'API Key is invalid' ),
				),
			)
		);
		$error = $provider->retrieve();
		$this->assertWPError( $error );
		$this->assertSame( 'dawarich_error', $error->get_error_code() );
		$this->assertSame( 'API Key is invalid', $error->get_error_message() );
		$this->assertStringNotContainsString( 'DAWARICHKEY', wp_json_encode( $error->get_all_error_data() ) );

		$this->mock_http( array( 'dawarich.example.com' => new WP_Error( 'http_request_failed', 'Timed out' ) ) );
		$this->assertSame( 'http_request_failed', $provider->retrieve()->get_error_code() );
	}

	/**
	 * Dawarich queries return the track between two times as longitude, latitude pairs, reading every page.
	 */
	public function test_dawarich_query() {
		$fixture = $this->fixture( 'location/dawarich-track.json' );
		$this->mock_http( array( 'dawarich.example.com/api/v1/points' => array( 'body' => $fixture ) ) );
		$provider = new Location_Provider_Dawarich();
		$track    = $provider->query( '2025-07-01T11:00:00-04:00', new DateTime( '2025-07-01T12:00:00-04:00' ) );

		$request = $this->last_request();
		$this->assertSame( '2025-07-01T15:00:00Z', $request['query']['start_at'] );
		$this->assertSame( '2025-07-01T16:00:00Z', $request['query']['end_at'] );
		$this->assertSame( 'asc', $request['query']['order'] );
		$this->assertCount( 1, $this->http_requests );
		$this->assertSame( array_map( fn( $point ) => array( $point['longitude'], $point['latitude'] ), $fixture ), $track );

		// A full page asks for the next one, up to MAX_PAGES.
		$page = array_fill( 0, Location_Provider_Dawarich::PER_PAGE, $fixture[0] );
		$this->mock_http( array( 'dawarich.example.com' => array( 'body' => $page ) ) );
		$track = $provider->query( '2025-07-01T00:00:00Z', '2025-07-02T00:00:00Z' );
		$this->assertCount( Location_Provider_Dawarich::MAX_PAGES, $this->http_requests );
		$this->assertSame( (string) Location_Provider_Dawarich::MAX_PAGES, $this->last_request()['query']['page'] );
		$this->assertCount( Location_Provider_Dawarich::PER_PAGE * Location_Provider_Dawarich::MAX_PAGES, $track );

		$this->mock_http( array( 'dawarich.example.com' => array( 'body' => array() ) ) );
		$this->assertFalse( $provider->query( '2025-07-01T00:00:00Z', '2025-07-02T00:00:00Z' ) );
	}

	/**
	 * GeoPulse returns the latest point, sending the token in the Authorization header rather than the URL.
	 */
	public function test_geopulse_latest() {
		$fixture = $this->fixture( 'location/geopulse-points.json' );
		$this->mock_http( array( 'geopulse.example.com/api/v1/gps/points' => array( 'body' => $fixture ) ) );
		$provider = new Location_Provider_GeoPulse();
		$this->assertNull( $provider->retrieve( '2025-07-01T12:00:00-04:00' ) );

		$request = $this->last_request();
		$this->assertSame( 'https://geopulse.example.com/api/v1/gps/points', $request['base'] );
		$this->assertSame( 'Bearer gp_sa_TOKEN', $request['args']['headers']['Authorization'] );
		$this->assertStringNotContainsString( 'gp_sa_TOKEN', $request['url'] );
		$this->assertSame( '1', $request['query']['size'] );
		$this->assertSame( 'desc', $request['query']['sortDirection'] );
		$this->assertSame( '2025-07-01T16:00:00Z', $request['query']['to'] );

		$point    = $fixture['items'][0];
		$location = $provider->get();
		$this->assertSame( $point['coordinates']['lat'], $location['latitude'] );
		$this->assertSame( $point['coordinates']['lng'], $location['longitude'] );
		$this->assertSame( $point['altitude'], $location['altitude'] );
		$this->assertSame( $point['accuracy'], $location['accuracy'] );
		$this->assertSame( round( $point['velocity'] / 3.6, 2 ), $location['speed'] );
		$this->assertSame( '2025-07-01T16:00:00+00:00', $location['time'] );

		$provider->retrieve();
		$this->assertArrayNotHasKey( 'to', $this->last_request()['query'] );
	}

	/**
	 * GeoPulse: no points is not a location, and a refused request carries GeoPulse's problem detail.
	 */
	public function test_geopulse_errors() {
		$provider = new Location_Provider_GeoPulse();
		$this->mock_http( array( 'geopulse.example.com' => array( 'body' => array( 'items' => array(), 'page' => 1 ) ) ) );
		$this->assertFalse( $provider->retrieve() );

		$this->mock_http(
			array(
				'geopulse.example.com' => array(
					'code' => 403,
					'body' => array(
						'title'  => 'Forbidden',
						'status' => 403,
						'detail' => 'API token does not have the read scope',
					),
				),
			)
		);
		$error = $provider->retrieve();
		$this->assertSame( 'geopulse_error', $error->get_error_code() );
		$this->assertSame( 'API token does not have the read scope', $error->get_error_message() );
		$this->assertSame( array( 'status' => 403 ), $error->get_error_data() );
	}

	/**
	 * GeoPulse queries return GeoPulse's simplified path between two times.
	 */
	public function test_geopulse_query() {
		$fixture = $this->fixture( 'location/geopulse-path.json' );
		$this->mock_http( array( 'geopulse.example.com/api/v1/gps/points/path' => array( 'body' => $fixture ) ) );
		$provider = new Location_Provider_GeoPulse();
		$track    = $provider->query( '2025-07-01T11:00:00-04:00', '2025-07-01T12:00:00-04:00' );

		$request = $this->last_request();
		$this->assertSame( '2025-07-01T15:00:00Z', $request['query']['from'] );
		$this->assertSame( '2025-07-01T16:00:00Z', $request['query']['to'] );
		$this->assertSame( 'true', $request['query']['simplify'] );
		$this->assertSame( array_map( fn( $point ) => array( $point['longitude'], $point['latitude'] ), $fixture['points'] ), $track );

		$this->mock_http( array( 'geopulse.example.com' => array( 'body' => array( 'points' => array() ) ) ) );
		$this->assertFalse( $provider->query( '2025-07-01T11:00:00Z', '2025-07-01T12:00:00Z' ) );
	}

	/**
	 * Without a user, URL or key, nothing is requested.
	 */
	public function test_not_configured() {
		$this->mock_http( array() );
		foreach ( array( 'Location_Provider_Dawarich' => 'dawarich', 'Location_Provider_GeoPulse' => 'geopulse' ) as $class => $slug ) {
			$provider = new $class();
			delete_user_meta( $this->user_id, $slug . '_api' );
			$this->assertNull( $provider->retrieve(), $class );
			$this->assertNull( $provider->query( '2025-07-01T00:00:00Z', '2025-07-02T00:00:00Z' ), $class );

			update_user_meta( $this->user_id, $slug . '_api', 'KEY' );
			update_user_meta( $this->user_id, $slug . '_url', 'not a url' );
			$this->assertNull( $provider->retrieve(), $class );
		}
		wp_set_current_user( 0 );
		$this->assertNull( ( new Location_Provider_Dawarich() )->retrieve() );
		$this->assertEmpty( $this->http_requests );
	}

	/**
	 * Another user's account is only used by someone who can edit that user.
	 */
	public function test_other_users_account() {
		$fixture  = array( 'dawarich.example.com' => array( 'body' => $this->fixture( 'location/dawarich-points.json' ) ) );
		$provider = new Location_Provider_Dawarich();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->mock_http( $fixture );
		$provider->set_user( $this->user_id );
		$error = $provider->retrieve();
		$this->assertSame( 'dawarich_not_allowed', $error->get_error_code() );
		$this->assertSame( 'dawarich_not_allowed', $provider->query( '2025-07-01T00:00:00Z', '2025-07-02T00:00:00Z' )->get_error_code() );
		$this->assertEmpty( $this->http_requests );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->mock_http( $fixture );
		$this->assertNull( $provider->retrieve() );
		$this->assertSame( 'DAWARICHKEY', $this->last_request()['query']['api_key'] );
	}

	/**
	 * Every account provider is on the profile, the one in use first and open, the others closed (#762).
	 */
	public function test_profile_all_accounts() {
		foreach ( array( 'show_user_profile', 'edit_user_profile' ) as $hook ) {
			$this->assertSame( 13, has_action( $hook, array( 'Location_Provider_Account', 'user_profiles' ) ) );
		}
		foreach ( array( 'personal_options_update', 'edit_user_profile_update' ) as $hook ) {
			$this->assertSame( 13, has_action( $hook, array( 'Location_Provider_Account', 'save_user_profiles' ) ) );
		}
		$user = get_userdata( $this->user_id );

		update_option( 'sloc_geolocation_provider', 'geopulse' );
		$this->assertSame( array( 'geopulse', 'dawarich' ), array_map( fn( $provider ) => $provider->get_slug(), Location_Provider_Account::account_providers() ) );
		ob_start();
		Location_Provider_Account::user_profiles( $user );
		$html = ob_get_clean();
		$this->assertSame( 1, substr_count( $html, '<h2>Location History</h2>' ) );
		$this->assertMatchesRegularExpression( '#<details class="sloc-api-group" id="sloc-account-geopulse" open>\s*<summary>\s*GeoPulse\s*<span class="sloc-api-in-use">In use</span>#', $html );
		$this->assertMatchesRegularExpression( '#<details class="sloc-api-group" id="sloc-account-dawarich">\s*<summary>\s*Dawarich\s*</summary>#', $html );
		$this->assertLessThan( strpos( $html, 'sloc-account-dawarich' ), strpos( $html, 'sloc-account-geopulse' ) );
		$this->assertStringContainsString( 'name="dawarich_account_nonce"', $html );
		$this->assertStringContainsString( 'name="geopulse_account_nonce"', $html );
		$this->assertStringNotContainsString( 'DAWARICHKEY', $html );

		// With neither in use, both are closed, by name.
		update_option( 'sloc_geolocation_provider', 'HTML5' );
		ob_start();
		Location_Provider_Account::user_profiles( $user );
		$html = ob_get_clean();
		$this->assertStringNotContainsString( ' open>', $html );
		$this->assertStringNotContainsString( 'In use', $html );
		$this->assertLessThan( strpos( $html, 'sloc-account-geopulse' ), strpos( $html, 'sloc-account-dawarich' ) );

		// Saving the profile saves each account shown.
		$_POST = array(
			'dawarich_account_nonce' => wp_create_nonce( 'dawarich_account' ),
			'dawarich_url'           => 'https://dawarich.example.net',
			'geopulse_account_nonce' => wp_create_nonce( 'geopulse_account' ),
			'geopulse_url'           => 'https://pulse.example.net',
			'geopulse_api'           => 'gp_sa_SAVED',
		);
		Location_Provider_Account::save_user_profiles( $this->user_id );
		$_POST = array();
		$this->assertSame( 'https://dawarich.example.net', get_user_meta( $this->user_id, 'dawarich_url', true ) );
		$this->assertSame( 'DAWARICHKEY', get_user_meta( $this->user_id, 'dawarich_api', true ), 'An empty key field keeps the key.' );
		$this->assertSame( 'https://pulse.example.net', get_user_meta( $this->user_id, 'geopulse_url', true ) );
		$this->assertSame( 'gp_sa_SAVED', get_user_meta( $this->user_id, 'geopulse_api', true ) );

		// Nothing for a user the current user can't edit.
		ob_start();
		Location_Provider_Account::user_profiles( get_userdata( self::factory()->user->create( array( 'role' => 'author' ) ) ) );
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * A provider's profile fields never print the saved key.
	 */
	public function test_profile_fields() {
		update_option( 'sloc_geolocation_provider', 'dawarich' );
		$user = get_userdata( $this->user_id );
		ob_start();
		Location_Provider_Dawarich::user_profile( $user );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'value="https://dawarich.example.com"', $html );
		$this->assertStringContainsString( 'type="password" name="dawarich_api" id="dawarich_api" value=""', $html );
		$this->assertStringContainsString( 'A key is saved.', $html );
		$this->assertStringContainsString( 'name="dawarich_api_remove"', $html );
		$this->assertStringContainsString( 'name="dawarich_account_nonce"', $html );
		$this->assertStringNotContainsString( 'DAWARICHKEY', $html );

		delete_user_meta( $this->user_id, 'dawarich_api' );
		ob_start();
		Location_Provider_Dawarich::user_profile( $user );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'from the Account page in Dawarich', $html );
		$this->assertStringNotContainsString( 'dawarich_api_remove', $html );

		// Nothing for a user the current user can't edit.
		ob_start();
		Location_Provider_Dawarich::user_profile( get_userdata( self::factory()->user->create( array( 'role' => 'author' ) ) ) );
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * Saving the profile stores the URL and a new key, keeps the key when the field is empty, and can remove it.
	 */
	public function test_save_profile() {
		$_POST = array(
			'geopulse_account_nonce' => wp_create_nonce( 'geopulse_account' ),
			'geopulse_url'           => ' https://pulse.example.org/ ',
			'geopulse_api'           => ' gp_sa_NEW ',
		);
		Location_Provider_GeoPulse::save_user_profile( $this->user_id );
		$this->assertSame( 'https://pulse.example.org', get_user_meta( $this->user_id, 'geopulse_url', true ) );
		$this->assertSame( 'gp_sa_NEW', get_user_meta( $this->user_id, 'geopulse_api', true ) );

		// An empty key field keeps the saved key; a URL that isn't http or https is removed.
		$_POST['geopulse_api'] = '';
		$_POST['geopulse_url'] = 'javascript:alert(1)';
		Location_Provider_GeoPulse::save_user_profile( $this->user_id );
		$this->assertSame( 'gp_sa_NEW', get_user_meta( $this->user_id, 'geopulse_api', true ) );
		$this->assertSame( '', get_user_meta( $this->user_id, 'geopulse_url', true ) );

		$_POST['geopulse_api_remove'] = '1';
		Location_Provider_GeoPulse::save_user_profile( $this->user_id );
		$this->assertSame( '', get_user_meta( $this->user_id, 'geopulse_api', true ) );
	}

	/**
	 * The profile isn't saved without the nonce, or for a user the current user can't edit.
	 */
	public function test_save_profile_refused() {
		$_POST = array(
			'dawarich_url' => 'https://evil.example.org',
			'dawarich_api' => 'EVIL',
		);
		Location_Provider_Dawarich::save_user_profile( $this->user_id );
		$this->assertSame( 'DAWARICHKEY', get_user_meta( $this->user_id, 'dawarich_api', true ) );

		$other = self::factory()->user->create( array( 'role' => 'author' ) );
		update_user_meta( $other, 'dawarich_api', 'OTHER' );
		$_POST['dawarich_account_nonce'] = wp_create_nonce( 'dawarich_account' );
		Location_Provider_Dawarich::save_user_profile( $other );
		$this->assertSame( 'OTHER', get_user_meta( $other, 'dawarich_api', true ) );
		$this->assertSame( '', get_user_meta( $other, 'dawarich_url', true ) );
	}
}

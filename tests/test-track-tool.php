<?php
/**
 * Tests for Tools → Location Track, which builds a track map shortcode from the location provider (#669).
 *
 * @package Simple_Location
 */

/**
 * Track tool tests.
 */
class TrackToolTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

	/**
	 * The current user, an author with a Dawarich account.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * The registered Dawarich provider, restored after each test.
	 *
	 * @var Location_Provider|null
	 */
	protected $registered;

	/**
	 * Points from Dawarich: leaving home, then along the Parkway.
	 *
	 * @var array
	 */
	protected $points = array(
		array( 39.9400, -75.1500 ),
		array( 39.9401, -75.1502 ),
		array( 39.9524, -75.1636 ),
		array( 39.9567, -75.1712 ),
		array( 39.9656, -75.1810 ),
	);

	/**
	 * Uses Dawarich, with the author's account, as the location provider.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'America/New_York' );
		update_option( 'sloc_geolocation_provider', 'dawarich' );
		update_option( 'sloc_map_provider', 'none' );
		$this->user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		update_user_meta( $this->user_id, 'dawarich_url', 'https://dawarich.example.com' );
		update_user_meta( $this->user_id, 'dawarich_api', 'KEY' );
		wp_set_current_user( $this->user_id );
		$this->registered = Loc_Config::geolocation_provider( 'dawarich' );
		Loc_Config::register_provider( new Location_Provider_Dawarich() );
	}

	/**
	 * Restores the provider and the form.
	 */
	public function tear_down() {
		if ( $this->registered ) {
			Loc_Config::register_provider( $this->registered );
		}
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Mocks Dawarich with the test points.
	 *
	 * @param array|null $points Points as array( latitude, longitude ). Default the test points.
	 */
	protected function mock_dawarich( $points = null ) {
		$body = array();
		foreach ( $points ?? $this->points as $index => $point ) {
			$body[] = array(
				'id'        => $index + 1,
				'latitude'  => $point[0],
				'longitude' => $point[1],
				'timestamp' => 1751385600 + $index * 60,
			);
		}
		$this->mock_http( array( 'dawarich.example.com/api/v1/points' => array( 'body' => $body ) ) );
	}

	/**
	 * Creates a venue at the first point, home, with the given visibility for posts made there.
	 *
	 * @param string $visibility Visibility of posts made there.
	 * @return int Venue ID.
	 */
	protected function home( $visibility ) {
		$venue_id = self::factory()->post->create( array( 'post_type' => 'venue' ) );
		set_post_geodata(
			$venue_id,
			'',
			array(
				'latitude'  => 39.9400,
				'longitude' => -75.1500,
			)
		);
		update_post_meta( $venue_id, 'venue_post_visibility', $visibility );
		return $venue_id;
	}

	/**
	 * The start and end of the test track, in the site's timezone.
	 *
	 * @return DateTimeImmutable[]
	 */
	protected function times() {
		return array(
			new DateTimeImmutable( '2025-07-01T12:00', wp_timezone() ),
			new DateTimeImmutable( '2025-07-01T13:00', wp_timezone() ),
		);
	}

	/**
	 * The page is under Tools, for users who can edit posts.
	 */
	public function test_menu() {
		global $submenu;
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		Sloc_Track_Tool::admin_menu();
		$this->assertContains( Sloc_Track_Tool::PAGE, wp_list_pluck( $submenu['tools.php'], 2 ) );
		$this->assertSame( 10, has_action( 'admin_menu', array( 'Sloc_Track_Tool', 'admin_menu' ) ) );
	}

	/**
	 * A track is built from the provider's points for the current user, as an encoded polyline and a shortcode.
	 */
	public function test_build() {
		$this->mock_dawarich();
		list( $start, $end ) = $this->times();
		$result              = Sloc_Track_Tool::build( $start, $end );

		$request = $this->last_request();
		$this->assertSame( '2025-07-01T16:00:00Z', $request['query']['start_at'] );
		$this->assertSame( '2025-07-01T17:00:00Z', $request['query']['end_at'] );

		$this->assertSame( 5, $result['found'] );
		$this->assertSame( 0, $result['private'] );
		$this->assertSame( 5, $result['kept'] );
		$this->assertSame( $this->points, Sloc_Track::decode( $result['polyline'] ) );
		$this->assertSame( '[sloc_map track="' . $result['polyline'] . '"]', $result['shortcode'] );

		$this->assertSame( '[sloc_map track="' . $result['polyline'] . '" markers="none"]', Sloc_Track_Tool::build( $start, $end, 'none' )['shortcode'] );
	}

	/**
	 * Points inside venues whose posts are private or protected are left out; other venues don't matter.
	 */
	public function test_private_places() {
		$this->mock_dawarich();
		list( $start, $end ) = $this->times();

		$this->home( 'public' );
		$this->assertSame( 0, Sloc_Track_Tool::build( $start, $end )['private'] );

		$this->home( 'private' );
		$result = Sloc_Track_Tool::build( $start, $end );
		$this->assertSame( 2, $result['private'], 'The two points at home, 50 m around it by default.' );
		$this->assertSame( array_slice( $this->points, 2 ), Sloc_Track::decode( $result['polyline'] ) );

		$places = Sloc_Track_Tool::get_private_places();
		$this->assertCount( 1, $places );
		$this->assertSame( array( 39.94, -75.15, 50.0 ), $places[0] );

		// A whole track at home has nothing to show.
		$this->mock_dawarich( array_slice( $this->points, 0, 2 ) );
		$this->assertSame( 'track_private', Sloc_Track_Tool::build( $start, $end )->get_error_code() );
	}

	/**
	 * Without a provider that gives tracks, an account, points, or a valid range, there is an error instead.
	 */
	public function test_errors() {
		list( $start, $end ) = $this->times();
		$this->assertSame( 'track_times', Sloc_Track_Tool::build( $end, $start )->get_error_code() );

		$this->mock_dawarich( array() );
		$this->assertSame( 'track_empty', Sloc_Track_Tool::build( $start, $end )->get_error_code() );

		delete_user_meta( $this->user_id, 'dawarich_api' );
		$this->assertSame( 'track_account', Sloc_Track_Tool::build( $start, $end )->get_error_code() );

		update_option( 'sloc_geolocation_provider', 'dummy' );
		$this->assertSame( 'track_unsupported', Sloc_Track_Tool::build( $start, $end )->get_error_code() );
	}

	/**
	 * Times come from the form's datetime-local fields, in the site's timezone.
	 */
	public function test_parse_time() {
		$this->assertSame( '2025-07-01T12:30:00-04:00', Sloc_Track_Tool::parse_time( '2025-07-01T12:30' )->format( DATE_W3C ) );
		$this->assertNull( Sloc_Track_Tool::parse_time( 'yesterday' ) );
		$this->assertNull( Sloc_Track_Tool::parse_time( array() ) );
	}

	/**
	 * The page shows the form, and after a valid submission the counts and the shortcode to copy.
	 */
	public function test_page() {
		ob_start();
		Sloc_Track_Tool::page();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'name="sloc_track_start"', $html );
		$this->assertStringContainsString( 'name="sloc_track_nonce"', $html );
		$this->assertStringNotContainsString( 'sloc_track_shortcode', $html );

		$this->mock_dawarich();
		$_POST = array(
			'sloc_track_nonce'   => wp_create_nonce( 'sloc_track' ),
			'sloc_track_start'   => '2025-07-01T12:00',
			'sloc_track_end'     => '2025-07-01T13:00',
			'sloc_track_markers' => 'none',
		);
		ob_start();
		Sloc_Track_Tool::page();
		$html = ob_get_clean();
		$this->assertStringContainsString( '5 points found, 0 left out in private places, 5 kept for the map.', $html );
		$this->assertMatchesRegularExpression( '#<textarea id="sloc_track_shortcode"[^>]*>\[sloc_map track=&quot;[^<]+&quot; markers=&quot;none&quot;\]</textarea>#', $html );

		// Without a map provider, the preview says how to get one.
		$this->assertStringContainsString( 'Choose a map provider in Settings to see the map here.', $html );

		// With one, the preview is the map the shortcode draws: the track, with its markers setting.
		update_option( 'sloc_map_provider', 'google' );
		$google = Loc_Config::map_provider( 'google' );
		Loc_Config::register_provider( new Map_Provider_Google( array( 'api' => 'KEY' ) ) );
		ob_start();
		Sloc_Track_Tool::page();
		$html = ob_get_clean();
		Loc_Config::register_provider( $google );
		$this->assertMatchesRegularExpression( '#<div class="sloc-track-preview">\s*<div class="sloc-map-shortcode"><img class="archive-map sloc-map" src="https://maps\.googleapis\.com/[^"]+path=#', $html );
		$this->assertStringNotContainsString( 'Choose a map provider', $html );

		// A bad nonce does nothing, and an error is shown for missing times.
		$_POST['sloc_track_nonce'] = 'bad';
		ob_start();
		Sloc_Track_Tool::page();
		$this->assertStringNotContainsString( 'sloc_track_shortcode', ob_get_clean() );

		$_POST = array(
			'sloc_track_nonce' => wp_create_nonce( 'sloc_track' ),
			'sloc_track_start' => '',
			'sloc_track_end'   => '2025-07-01T13:00',
		);
		ob_start();
		Sloc_Track_Tool::page();
		$this->assertStringContainsString( 'Enter a start and an end.', ob_get_clean() );

		// Users who can't edit posts get nothing.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		ob_start();
		Sloc_Track_Tool::page();
		$this->assertSame( '', ob_get_clean() );
	}
}

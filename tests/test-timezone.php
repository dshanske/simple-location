<?php
/**
 * Tests for Loc_Timezone and Timezone_Result: timezone lookup by coordinates, per-object timezone
 * overrides, the date filters, the timezone metabox and its save handler.
 *
 * @package Simple_Location
 */

/**
 * Timezone tests.
 */
class TimezoneTest extends WP_UnitTestCase {

	/**
	 * Site timezone for every test.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'timezone_string', 'America/New_York' );
		update_option( 'date_format', 'Y-m-d' );
		update_option( 'time_format', 'H:i' );
	}

	/**
	 * Clears the request data used by the save handler.
	 */
	public function tear_down() {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Creates a post at 12:00 UTC on 2025-07-01 with an optional timezone override.
	 *
	 * @param string $timezone Timezone to store, if any.
	 * @return int Post ID.
	 */
	protected function post_with_timezone( $timezone = '' ) {
		$post_id = self::factory()->post->create(
			array(
				'post_date'     => '2025-07-01 08:00:00',
				'post_date_gmt' => '2025-07-01 12:00:00',
			)
		);
		if ( $timezone ) {
			update_post_meta( $post_id, 'geo_timezone', $timezone );
		}
		return $post_id;
	}

	/**
	 * Timezones are equal when their offsets match at the given time.
	 */
	public function test_compare_timezones() {
		$new_york = new DateTimeZone( 'America/New_York' );
		$toronto  = new DateTimeZone( 'America/Toronto' );
		$phoenix  = new DateTimeZone( 'America/Phoenix' );
		$denver   = new DateTimeZone( 'America/Denver' );

		$this->assertTrue( Loc_Timezone::compare_timezones( $new_york, $toronto ) );
		$this->assertFalse( Loc_Timezone::compare_timezones( $new_york, $phoenix ) );
		// Phoenix has no daylight saving time, so it matches Denver only in winter.
		$this->assertTrue( Loc_Timezone::compare_timezones( $phoenix, $denver, '2025-01-15T12:00:00Z' ) );
		$this->assertFalse( Loc_Timezone::compare_timezones( $phoenix, $denver, '2025-07-15T12:00:00Z' ) );
		$this->assertTrue( Loc_Timezone::compare_timezones( $phoenix, $denver, new DateTimeImmutable( '2025-01-15T12:00:00Z' ) ) );
		$this->assertFalse( Loc_Timezone::compare_timezones( 'America/New_York', $new_york ) );
		$this->assertFalse( Loc_Timezone::compare_timezones( $new_york, null ) );
	}

	/**
	 * The nearest listed city within 200 km gives the timezone.
	 */
	public function test_timezone_for_location() {
		$result = Loc_Timezone::timezone_for_location( 39.9524, -75.1636 );
		$this->assertInstanceOf( 'Timezone_Result', $result );
		$this->assertSame( 'America/New_York', $result->name );
		$this->assertSame( 'America/New_York', $result->timezone->getName() );

		$this->assertSame( 'Asia/Tokyo', Loc_Timezone::timezone_for_location( 35.6812, 139.7671 )->name );
		$this->assertSame( 'Europe/London', Loc_Timezone::timezone_for_location( 51.5072, -0.1276 )->name );
		$this->assertSame( 'Australia/Sydney', Loc_Timezone::timezone_for_location( -33.8568, 151.2153 )->name );

		// The middle of the Atlantic has no city within 200 km.
		$this->assertNull( Loc_Timezone::timezone_for_location( 30.0, -40.0 ) );
	}

	/**
	 * The result reports the offset at the given date, or now.
	 */
	public function test_timezone_result() {
		$summer = Loc_Timezone::timezone_for_location( 39.9524, -75.1636, '2025-07-01T12:00:00Z' );
		$this->assertSame( '-04:00', $summer->offset );
		$this->assertSame( -14400, $summer->seconds );
		$this->assertSame( '2025-07-01T08:00:00-04:00', $summer->localtime );
		$this->assertSame( 'America/New_York', (string) $summer );

		$winter = new Timezone_Result( 'America/New_York', '2025-01-15T12:00:00Z' );
		$this->assertSame( '-05:00', $winter->offset );
		$this->assertSame( -18000, $winter->seconds );
		$this->assertNull( $winter->unknown );

		$now = new Timezone_Result( 'UTC' );
		$this->assertSame( '+00:00', $now->offset );
		$this->assertEqualsWithDelta( time(), strtotime( $now->localtime ), 5 );
	}

	/**
	 * Without an override, objects use the site timezone; with one, they use it.
	 */
	public function test_get_timezone() {
		$post_id = $this->post_with_timezone();
		$this->assertSame( 'America/New_York', Loc_Timezone::get_timezone( $post_id )->getName() );

		$post_id = $this->post_with_timezone( 'Asia/Tokyo' );
		$this->assertSame( 'Asia/Tokyo', Loc_Timezone::get_timezone( $post_id )->getName() );
		$this->assertSame( 'Asia/Tokyo', Loc_Timezone::get_timezone( get_post( $post_id ) )->getName() );

		// The global post is the default.
		$GLOBALS['post'] = get_post( $post_id );
		$this->assertSame( 'Asia/Tokyo', Loc_Timezone::get_timezone()->getName() );
		unset( $GLOBALS['post'] );

		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'geo_timezone', 'Europe/Paris' );
		$this->assertSame( 'Europe/Paris', Loc_Timezone::get_timezone( get_user_by( 'id', $user_id ) )->getName() );

		$term_id = self::factory()->term->create( array( 'taxonomy' => 'category' ) );
		update_term_meta( $term_id, 'geo_timezone', 'Africa/Cairo' );
		$this->assertSame( 'Africa/Cairo', Loc_Timezone::get_timezone( get_term( $term_id ) )->getName() );
	}

	/**
	 * Manual offsets in the old "UTC+n" format are ignored, and one-character values are removed.
	 */
	public function test_get_timezone_unusable_values() {
		$post_id = $this->post_with_timezone( 'UTC+5' );
		$this->assertNull( Loc_Timezone::get_timezone( $post_id ) );

		$post_id = $this->post_with_timezone( 'UTC' );
		$this->assertSame( 'UTC', Loc_Timezone::get_timezone( $post_id )->getName() );

		$post_id = $this->post_with_timezone( '1' );
		$this->assertNull( Loc_Timezone::get_timezone( $post_id ) );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_timezone', true ) );

		// Offsets saved by the timezone dropdown work.
		$post_id = $this->post_with_timezone( '+05:30' );
		$this->assertSame( '+05:30', Loc_Timezone::get_timezone( $post_id )->getName() );
	}

	/**
	 * Post dates and times are shown in the post's timezone.
	 */
	public function test_post_date_filters() {
		$this->assertSame( 9, has_filter( 'get_the_date', array( 'Loc_Timezone', 'get_the_date' ) ) );

		$post_id = $this->post_with_timezone( 'Asia/Tokyo' );
		$this->assertSame( '2025-07-01', get_the_date( '', $post_id ) );
		$this->assertSame( '21:00', get_the_time( '', $post_id ) );
		$this->assertSame( '2025-07-01T21:00:00+09:00', get_the_time( 'c', $post_id ) );
		$this->assertSame( '2025-07-02 02:00', get_the_date( 'Y-m-d H:i', $this->post_with_timezone( 'Pacific/Kiritimati' ) ) );

		$modified = get_post_timestamp( $post_id, 'modified' );
		$this->assertSame( wp_date( 'c', $modified, new DateTimeZone( 'Asia/Tokyo' ) ), get_the_modified_time( 'c', $post_id ) );
		$this->assertSame( wp_date( 'Y-m-d', $modified, new DateTimeZone( 'Asia/Tokyo' ) ), get_the_modified_date( '', $post_id ) );
		$this->assertSame( wp_date( 'H:i', $modified, new DateTimeZone( 'Asia/Tokyo' ) ), get_the_modified_time( '', $post_id ) );

		// Without an override, the site timezone applies.
		$post_id = $this->post_with_timezone();
		$this->assertSame( '08:00', get_the_time( '', $post_id ) );
	}

	/**
	 * The filters leave the value alone when there is no post or the override is unusable.
	 */
	public function test_post_date_filters_pass_through() {
		$this->assertSame( 'unchanged', Loc_Timezone::get_the_date( 'unchanged', '', 0 ) );
		$this->assertSame( 'unchanged', Loc_Timezone::get_the_time( 'unchanged', '', 0 ) );
		$this->assertSame( 'unchanged', Loc_Timezone::get_the_modified_date( 'unchanged', '', 0 ) );
		$this->assertSame( 'unchanged', Loc_Timezone::get_the_modified_time( 'unchanged', '', 0 ) );

		$post_id = $this->post_with_timezone( 'UTC+5' );
		$this->assertSame( 'unchanged', Loc_Timezone::get_the_date( 'unchanged', '', $post_id ) );
		$this->assertSame( 'unchanged', Loc_Timezone::get_the_time( 'unchanged', '', $post_id ) );
		$this->assertSame( 'unchanged', Loc_Timezone::get_the_modified_date( 'unchanged', '', $post_id ) );
		$this->assertSame( 'unchanged', Loc_Timezone::get_the_modified_time( 'unchanged', '', $post_id ) );
	}

	/**
	 * The posts list shows the timezone abbreviation only for posts in another timezone.
	 */
	public function test_post_date_column_time() {
		$post = get_post( $this->post_with_timezone() );
		$this->assertSame( 'unchanged', Loc_Timezone::post_date_column_time( 'unchanged', $post ) );

		$post = get_post( $this->post_with_timezone( 'Asia/Tokyo' ) );
		$this->assertSame( '2025/07/01 at 9:00 pm JST', Loc_Timezone::post_date_column_time( 'unchanged', $post ) );
	}

	/**
	 * Micropub posts get the timezone of their coordinates, or the one given.
	 */
	public function test_after_micropub() {
		$this->assertSame( 10, has_action( 'after_micropub', array( 'Loc_Timezone', 'after_micropub' ) ) );

		$post_id = self::factory()->post->create();
		Loc_Timezone::after_micropub(
			array(),
			array(
				'ID'         => $post_id,
				'meta_input' => array(
					'geo_latitude'  => 35.6812,
					'geo_longitude' => 139.7671,
				),
			)
		);
		$this->assertSame( 'Asia/Tokyo', get_post_meta( $post_id, 'geo_timezone', true ) );

		$post_id = self::factory()->post->create();
		Loc_Timezone::after_micropub(
			array(),
			array(
				'ID'       => $post_id,
				'timezone' => 'Europe/Paris',
			)
		);
		$this->assertSame( 'Europe/Paris', get_post_meta( $post_id, 'geo_timezone', true ) );

		// Neither coordinates nor a timezone: nothing is saved.
		$post_id = self::factory()->post->create();
		Loc_Timezone::after_micropub( array(), array( 'ID' => $post_id ) );
		Loc_Timezone::after_micropub( array(), null );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_timezone', true ) );
	}

	/**
	 * The timezone dropdown groups zones by continent and adds UTC and manual offsets.
	 */
	public function test_wp_timezone_choice() {
		$html = Loc_Timezone::wp_timezone_choice( 'Asia/Tokyo' );
		$this->assertStringContainsString( '<optgroup label="Asia">', $html );
		$this->assertStringContainsString( '<option selected="selected" value="Asia/Tokyo">Tokyo</option>', $html );
		$this->assertStringContainsString( '<option value="America/Argentina/Buenos_Aires">Argentina - Buenos Aires</option>', $html );
		$this->assertStringContainsString( '<option value="UTC">UTC</option>', $html );
		$this->assertStringContainsString( '<option value="+5:30">UTC+5:30</option>', $html );
		$this->assertStringContainsString( '<option value="-9:30">UTC-9:30</option>', $html );
		$this->assertStringContainsString( '<option value="+12:45">UTC+12:45</option>', $html );
		$this->assertStringNotContainsString( 'Select a city', $html );
		$this->assertSame( substr_count( $html, '<optgroup' ), substr_count( $html, '</optgroup>' ) );

		$this->assertStringContainsString( '<option selected="selected" value="UTC">', Loc_Timezone::wp_timezone_choice( 'UTC' ) );
		$this->assertStringContainsString( '<option selected="selected" value="-3:30">', Loc_Timezone::wp_timezone_choice( '-3:30' ) );
		$this->assertStringContainsString( '<option selected="selected" value="">Select a city</option>', Loc_Timezone::wp_timezone_choice( '' ) );
	}

	/**
	 * The metabox shows the current timezone, a nonce and the dropdown.
	 */
	public function test_submitbox() {
		$this->assertSame( 10, has_action( 'simple_location_sidebox', array( 'Loc_Timezone', 'submitbox' ) ) );

		set_current_screen( 'post' );
		$GLOBALS['post'] = get_post( $this->post_with_timezone( 'Asia/Tokyo' ) );
		ob_start();
		Loc_Timezone::submitbox( get_current_screen(), $GLOBALS['post'], array() );
		$html = ob_get_clean();
		unset( $GLOBALS['post'] );
		set_current_screen( 'front' );

		// load_template() uses require_once, so another test may already have rendered it.
		if ( '' !== $html ) {
			$this->assertStringContainsString( 'name="timezone_override_nonce"', $html );
			$this->assertStringContainsString( '<span id="post-timezone-label">', $html );
			$this->assertStringContainsString( 'Asia/Tokyo', $html );
			$this->assertStringContainsString( '<option selected="selected" value="Asia/Tokyo">', $html );
		}
		$this->assertContains( realpath( dirname( __DIR__ ) . '/templates/timezone-metabox.php' ), get_included_files() );
	}

	/**
	 * Saving stores a timezone that differs from the site's and removes one that matches it.
	 */
	public function test_save_post_meta() {
		$this->assertSame( 10, has_action( 'save_post', array( 'Loc_Timezone', 'postbox_save_post_meta' ) ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post_id = $this->post_with_timezone();

		$_POST = array(
			'timezone_override_nonce' => wp_create_nonce( 'timezone_override_metabox' ),
			'post_timezone'           => 'Asia/Tokyo',
		);
		Loc_Timezone::postbox_save_post_meta( $post_id );
		$this->assertSame( 'Asia/Tokyo', get_post_meta( $post_id, 'geo_timezone', true ) );

		// The same offset as the site removes the override.
		$_POST['post_timezone'] = 'America/Toronto';
		Loc_Timezone::postbox_save_post_meta( $post_id );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_timezone', true ) );

		// Manual offsets are saved by name.
		$_POST['post_timezone'] = '+5:30';
		Loc_Timezone::postbox_save_post_meta( $post_id );
		$this->assertSame( '+05:30', get_post_meta( $post_id, 'geo_timezone', true ) );

		// Pages are checked with edit_page.
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$_POST   = array(
			'timezone_override_nonce' => wp_create_nonce( 'timezone_override_metabox' ),
			'post_type'               => 'page',
			'post_timezone'           => 'Asia/Tokyo',
		);
		Loc_Timezone::postbox_save_post_meta( $page_id );
		$this->assertSame( 'Asia/Tokyo', get_post_meta( $page_id, 'geo_timezone', true ) );
	}

	/**
	 * Nothing is saved without a valid nonce, during autosave, or without permission.
	 */
	public function test_save_post_meta_refused() {
		$post_id = $this->post_with_timezone();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$_POST = array( 'post_timezone' => 'Asia/Tokyo' );
		Loc_Timezone::postbox_save_post_meta( $post_id );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_timezone', true ) );

		$_POST['timezone_override_nonce'] = 'invalid';
		Loc_Timezone::postbox_save_post_meta( $post_id );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_timezone', true ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST['timezone_override_nonce'] = wp_create_nonce( 'timezone_override_metabox' );
		Loc_Timezone::postbox_save_post_meta( $post_id );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_timezone', true ) );

		$page_id                = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$_POST['post_type']     = 'page';
		$_POST['post_timezone'] = 'Asia/Tokyo';
		Loc_Timezone::postbox_save_post_meta( $page_id );
		$this->assertSame( '', get_post_meta( $page_id, 'geo_timezone', true ) );
	}

	/**
	 * REST responses give the post date in the post's timezone, and GMT dates in W3C format.
	 */
	public function test_rest_post_date() {
		$this->assertSame( 10, has_filter( 'rest_prepare_post', array( 'Loc_Timezone', 'rest_prepare_post' ) ) );
		$post_id  = $this->post_with_timezone( 'Asia/Tokyo' );
		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id ) );
		$data     = $response->get_data();
		$this->assertSame( '2025-07-01T21:00:00+09:00', $data['date'] );
		$this->assertSame( '2025-07-01T12:00:00+00:00', $data['date_gmt'] );
	}

	/**
	 * REST comment responses give the GMT date in W3C format.
	 */
	public function test_rest_comment_date_gmt() {
		$this->assertSame( 10, has_filter( 'rest_prepare_comment', array( 'Loc_Timezone', 'rest_prepare_comment' ) ) );
		$post_id    = $this->post_with_timezone();
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => 1,
				'comment_date'     => '2025-07-01 08:00:00',
				'comment_date_gmt' => '2025-07-01 12:00:00',
			)
		);
		$data       = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/comments/' . $comment_id ) )->get_data();
		$this->assertSame( '2025-07-01T12:00:00+00:00', $data['date_gmt'] );
		$this->assertSame( '2025-07-01T08:00:00-04:00', $data['date'] );
	}
}

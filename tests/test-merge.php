<?php
/**
 * Tests for merging duplicates (#242).
 *
 * @package Simple_Location
 */

/**
 * Merge tests.
 */
class MergeTest extends WP_UnitTestCase {

	/**
	 * Start as an administrator, with no request data.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_GET  = array();
		$_POST = array();
	}

	/**
	 * Reset request data.
	 */
	public function tear_down() {
		$_GET  = array();
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Creates a venue.
	 *
	 * @param string $title Title.
	 * @param string $date  Date.
	 * @param array  $args  Other post fields.
	 * @return int Venue ID.
	 */
	protected function venue( $title, $date = '2026-01-01 00:00:00', $args = array() ) {
		$venue_id = self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => 'venue',
					'post_title'  => $title,
					'post_date'   => $date,
					'post_status' => 'publish',
				),
				$args
			)
		);
		set_post_geodata(
			$venue_id,
			'',
			array(
				'latitude'   => 39.9524,
				'longitude'  => -75.1636,
				'visibility' => 'public',
			)
		);
		return $venue_id;
	}

	/**
	 * Posts, photos, child venues, identifiers, pages, names and types move to the venue kept; the duplicate is
	 * deleted.
	 */
	public function test_merge_venues() {
		$keep      = $this->venue( 'City Hall', '2025-01-01 00:00:00' );
		$duplicate = $this->venue( 'Philadelphia City Hall' );
		update_post_meta( $keep, 'venue_url', 'https://www.phila.gov/' );
		update_post_meta( $duplicate, 'venue_url', 'https://cityhall.example.com/' );
		update_post_meta( $duplicate, 'venue_alternate', array( 'https://www.openstreetmap.org/way/31414402' ) );
		Post_Venue::add_venue_sources(
			$duplicate,
			array(
				'uid'      => 'https://www.openstreetmap.org/way/31414402',
				'provider' => 'overpass',
			),
			array()
		);
		add_post_meta( $duplicate, Post_Venue::ALIAS_KEY, 'Old City Hall' );
		$type = self::factory()->term->create( array( 'taxonomy' => 'venue_type' ) );
		wp_set_object_terms( $duplicate, array( $type ), 'venue_type' );
		$child = $this->venue( 'Mayor\'s Office', '2026-01-01 00:00:00', array( 'post_parent' => $duplicate ) );

		$old_link = get_permalink( $duplicate );
		$post_id  = self::factory()->post->create();
		Post_Venue::set_post_venue( $post_id, $duplicate );
		update_post_meta(
			$post_id,
			'mf2_checkin',
			array(
				'type'       => array( 'h-card' ),
				'properties' => array(
					'name' => array( 'Philadelphia City Hall' ),
					'url'  => array( $old_link ),
				),
			)
		);
		$photo = self::factory()->attachment->create();
		update_post_meta( $photo, 'venue_id', $duplicate );

		$this->assertSame( 1, Post_Venue::merge_venues( $keep, array( $keep, $duplicate ) ) );

		$this->assertNull( get_post( $duplicate ), 'Deleted outright.' );
		$this->assertEquals( $keep, get_post_meta( $post_id, 'venue_id', true ) );
		$this->assertEquals( $keep, get_post_meta( $photo, 'venue_id', true ) );
		$checkin = get_post_meta( $post_id, 'mf2_checkin', true );
		$this->assertSame( array( get_permalink( $keep ) ), $checkin['properties']['url'] );
		$this->assertSame( array( 'City Hall' ), $checkin['properties']['name'] );
		$this->assertSame( $keep, wp_get_post_parent_id( $child ) );

		$this->assertSame( 'City Hall', get_the_title( $keep ) );
		$this->assertSame( 'https://www.phila.gov/', get_post_meta( $keep, 'venue_url', true ), 'The kept website.' );
		$this->assertSame( array( 'https://cityhall.example.com/', 'https://www.openstreetmap.org/way/31414402' ), get_post_meta( $keep, 'venue_alternate', true ) );
		$this->assertSame( array( 'https://www.openstreetmap.org/way/31414402' ), get_post_meta( $keep, Post_Venue::UID_KEY ) );
		$this->assertSame( 'overpass', Post_Venue::get_venue_sources( $keep )[0]['provider'] );
		$this->assertSame( array( 'Philadelphia City Hall', 'Old City Hall' ), get_post_meta( $keep, Post_Venue::ALIAS_KEY ) );
		$this->assertSame( array( $type ), wp_get_object_terms( $keep, 'venue_type', array( 'fields' => 'ids' ) ) );

		// Check-ins by the duplicate's name, identifier or address find the kept venue.
		$this->assertSame(
			$keep,
			Post_Venue::find_venue(
				array(
					'name'      => 'Philadelphia City Hall',
					'latitude'  => 39.9525,
					'longitude' => -75.1636,
				)
			)
		);
		$this->assertSame( $keep, Post_Venue::find_venue( array( 'uid' => 'https://www.openstreetmap.org/way/31414402' ) ) );
		$this->assertSame( get_permalink( $keep ), Sloc_Merge::redirect_url( $old_link ) );
	}

	/**
	 * A venue kept from inside the duplicate takes its place; addresses that redirected to a duplicate redirect to
	 * the venue kept.
	 */
	public function test_merge_venues_chain() {
		$outer = $this->venue( 'Mall', '2025-01-01 00:00:00' );
		$first = $this->venue( 'Food Court', '2025-02-01 00:00:00', array( 'post_parent' => $outer ) );
		$inner = $this->venue( 'Food Hall', '2025-03-01 00:00:00', array( 'post_parent' => $first ) );
		$link  = get_permalink( $inner );
		$this->assertSame( 1, Post_Venue::merge_venues( $first, array( $inner ) ) );

		$second = $this->venue( 'Mall Food Court' );
		$this->assertSame( 1, Post_Venue::merge_venues( $second, array( $first ) ) );
		$this->assertSame( get_permalink( $second ), Sloc_Merge::redirect_url( $link ), 'The first merge\'s address too.' );
		$this->assertContains( 'Food Hall', get_post_meta( $second, Post_Venue::ALIAS_KEY ) );
		$this->assertContains( 'Food Court', get_post_meta( $second, Post_Venue::ALIAS_KEY ) );

		$third = $this->venue( 'Court' );
		wp_update_post(
			array(
				'ID'          => $third,
				'post_parent' => $second,
			)
		);
		$this->assertSame( 1, Post_Venue::merge_venues( $third, array( $second ) ) );
		$this->assertSame( 0, wp_get_post_parent_id( $third ), 'In the duplicate\'s place.' );
	}

	/**
	 * Only venues the user can delete are merged, into a venue the user can edit.
	 */
	public function test_merge_venues_permissions() {
		$keep  = $this->venue( 'A' );
		$other = $this->venue( 'B' );
		$this->assertSame( 0, Post_Venue::merge_venues( $keep, array( $keep, self::factory()->post->create() ) ), 'Not a venue.' );
		$this->assertSame( 0, Post_Venue::merge_venues( self::factory()->post->create(), array( $other ) ), 'Not kept as a venue.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertSame( 0, Post_Venue::merge_venues( $keep, array( $other ) ) );
		$this->assertNotNull( get_post( $other ) );
		wp_set_current_user( 0 );
		$this->assertSame( 0, Post_Venue::merge_venues( $keep, array( $other ) ) );
	}

	/**
	 * The Merge bulk action sends two or more venues to the merge screen, with a nonce.
	 */
	public function test_bulk_action() {
		$this->assertArrayHasKey( Sloc_Merge::ACTION, apply_filters( 'bulk_actions-edit-venue', array() ) );
		$url = apply_filters( 'handle_bulk_actions-edit-venue', 'edit.php?post_type=venue', Sloc_Merge::ACTION, array( '7', 5, 7 ) );
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$this->assertSame( Sloc_Merge::PAGE, $query['page'] );
		$this->assertSame( 'venue', $query['type'] );
		$this->assertSame( '7,5', $query['ids'] );
		$this->assertSame( 1, wp_verify_nonce( $query['_wpnonce'], Sloc_Merge::ACTION ) );

		$this->assertStringContainsString( 'sloc_merged=few', apply_filters( 'handle_bulk_actions-edit-venue', 'edit.php?post_type=venue', Sloc_Merge::ACTION, array( 5 ) ) );
		$this->assertSame( 'edit.php', apply_filters( 'handle_bulk_actions-edit-venue', 'edit.php', 'trash', array( 5, 7 ) ), 'Other actions.' );
	}

	/**
	 * The merge screen lists the venues oldest first, with the oldest chosen to keep, and needs a valid link.
	 */
	public function test_page() {
		$newer = $this->venue( 'Newer', '2026-05-01 00:00:00' );
		$older = $this->venue( 'Older <b>', '2024-05-01 00:00:00' );
		$items = Sloc_Merge::items( 'venue', array( $newer, $older, self::factory()->post->create() ) );
		$this->assertSame( array( $older, $newer ), wp_list_pluck( $items, 'id' ), 'Oldest first; not posts.' );

		$_GET = array(
			'type'     => 'venue',
			'ids'      => $newer . ',' . $older,
			'_wpnonce' => wp_create_nonce( Sloc_Merge::ACTION ),
		);
		ob_start();
		Sloc_Merge::page();
		$html = ob_get_clean();
		$this->assertMatchesRegularExpression( '/value="' . $older . '" checked="checked"/', $html );
		$this->assertStringNotContainsString( 'value="' . $newer . '" checked', $html );
		$this->assertStringContainsString( 'Older &lt;b&gt;', $html );
		$this->assertStringContainsString( 'name="sloc_merge_nonce"', $html );

		$_GET['_wpnonce'] = 'bad';
		$this->expectException( WPDieException::class );
		Sloc_Merge::page();
	}

	/**
	 * Merging needs the screen's nonce and the venue to keep among those chosen, then returns to the list.
	 */
	public function test_handle_merge() {
		$keep      = $this->venue( 'Keep', '2024-01-01 00:00:00' );
		$duplicate = $this->venue( 'Duplicate' );
		$this->assertSame( 0, Sloc_Merge::merge( 'venue', self::factory()->post->create(), array( $duplicate ) ), 'Keep one of those chosen.' );
		$this->assertSame( 0, Sloc_Merge::merge( 'unknown', $keep, array( $keep, $duplicate ) ) );

		$_POST = array(
			'type'             => 'venue',
			'keep'             => (string) $keep,
			'ids'              => $keep . ',' . $duplicate,
			'sloc_merge_nonce' => wp_create_nonce( Sloc_Merge::ACTION . '_venue' ),
		);
		add_filter(
			'wp_redirect',
			function ( $location ) {
				throw new RuntimeException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		);
		try {
			Sloc_Merge::handle_merge();
			$this->fail( 'No redirect.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'edit.php?post_type=venue', $e->getMessage() );
			$this->assertStringContainsString( 'sloc_merged=1', $e->getMessage() );
		}
		$this->assertNull( get_post( $duplicate ) );

		$_POST['sloc_merge_nonce'] = wp_create_nonce( Sloc_Merge::ACTION . '_location' );
		$this->expectException( WPDieException::class );
		Sloc_Merge::handle_merge();
	}

	/**
	 * Notices say how many were merged.
	 */
	public function test_admin_notices() {
		$messages = array();
		foreach ( array( '2', '0', 'few' ) as $count ) {
			$_GET['sloc_merged'] = $count;
			ob_start();
			Sloc_Merge::admin_notices();
			$messages[] = ob_get_clean();
		}
		$this->assertStringContainsString( 'Merged 2', $messages[0] );
		$this->assertStringContainsString( 'Nothing was merged', $messages[1] );
		$this->assertStringContainsString( 'two or more', $messages[2] );
		unset( $_GET['sloc_merged'] );
		ob_start();
		Sloc_Merge::admin_notices();
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * Redirects match the path and query of an address, without slashes at the ends.
	 */
	public function test_redirect_key() {
		$this->assertSame( 'venue/cafe', Sloc_Merge::redirect_key( 'https://example.com/venue/cafe/' ) );
		$this->assertSame( '?venue=cafe', Sloc_Merge::redirect_key( 'https://example.com/?venue=cafe' ) );
		$this->assertSame( 'venue/cafe', Sloc_Merge::redirect_key( '/venue/cafe' ) );
		$this->assertFalse( Sloc_Merge::redirect_url( '/' ) );
		$this->assertFalse( Sloc_Merge::redirect_url( '/venue/nothing-merged/' ) );
	}
}

<?php
/**
 * Tests for a venue's details in the Micropub source query (#840).
 *
 * @package Simple_Location
 */

/**
 * Venue source tests.
 */
class VenueSourceTest extends WP_UnitTestCase {

	/**
	 * Philadelphia City Hall.
	 */
	const LAT = 39.9524;
	const LNG = -75.1636;

	/**
	 * Public locations by default, and plain permalinks.
	 */
	public function set_up() {
		parent::set_up();
		// Plain permalinks, which other tests may have changed without rules to match.
		$this->set_permalink_structure( '' );
		update_option( 'geo_public', 1 );
	}

	/**
	 * Clean up.
	 */
	public function tear_down() {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Creates a venue.
	 *
	 * @param string $title      Title.
	 * @param string $visibility Location visibility.
	 * @param array  $post       Other post fields.
	 * @return int Venue ID.
	 */
	protected function venue( $title, $visibility = 'public', $post = array() ) {
		$venue_id = self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => 'venue',
					'post_title'  => $title,
					'post_author' => self::factory()->user->create( array( 'role' => 'editor' ) ),
				),
				$post
			)
		);
		set_post_geodata(
			$venue_id,
			'',
			array(
				'latitude'   => self::LAT,
				'longitude'  => self::LNG,
				'visibility' => $visibility,
			)
		);
		return $venue_id;
	}

	/**
	 * Creates a post at a venue with an image attached.
	 *
	 * @param int    $venue_id   Venue ID.
	 * @param array  $post       Other post fields.
	 * @param string $visibility Location visibility, or empty for the site default.
	 * @return array Post ID and image URL.
	 */
	protected function visit( $venue_id, $post = array(), $visibility = '' ) {
		$post_id = self::factory()->post->create( $post );
		update_post_meta( $post_id, 'venue_id', $venue_id );
		if ( $visibility ) {
			set_post_geodata( $post_id, 'visibility', $visibility );
		}
		$image = self::factory()->attachment->create(
			array(
				'file'           => 'photo-' . $post_id . '.jpg',
				'post_parent'    => $post_id,
				'post_mime_type' => 'image/jpeg',
			)
		);
		return array( $post_id, wp_get_attachment_url( $image ) );
	}

	/**
	 * Runs a source query.
	 *
	 * @param mixed $resp  The Micropub plugin's response.
	 * @param array $input Input, without q.
	 * @return mixed
	 */
	protected function query( $resp, $input ) {
		return Location_Plugins::micropub_query_source( $resp, array( 'q' => 'source' ) + $input );
	}

	/**
	 * A venue's source is an h-card with its details, over what the Micropub plugin found.
	 */
	public function test_venue_source() {
		$parent = $this->venue( 'Market' );
		$venue  = $this->venue( 'Cafe', 'public', array( 'post_parent' => $parent ) );
		$child  = $this->venue( 'Counter', 'public', array( 'post_parent' => $venue ) );
		$this->venue( 'Back Room', 'private', array( 'post_parent' => $venue ) );
		$this->venue( 'Hidden', 'public', array( 'post_parent' => $venue ) );
		update_post_meta( $venue, 'venue_street_address', '1 Penn Square' );
		update_post_meta( $venue, 'venue_url', 'https://cafe.example.com/' );
		$hidden = get_posts(
			array(
				'post_type' => 'venue',
				'title'     => 'Hidden',
				'fields'    => 'ids',
			)
		);
		update_post_meta( $hidden[0], Post_Venue::EXCLUDE_KEY, '1' );

		list( , $seen )    = $this->visit( $venue );
		list( , $private ) = $this->visit( $venue, array(), 'private' );

		$base = array(
			'properties' => array(
				'name'      => array( 'Cafe' ),
				'content'   => array( 'Good coffee.' ),
				'published' => array( '2026-01-01T00:00:00+00:00' ),
			),
		);
		$resp = $this->query( $base, array( 'url' => get_permalink( $venue ) ) );
		$this->assertSame( array( 'h-card' ), $resp['type'] );
		$props = $resp['properties'];
		$this->assertSame( array( 'Good coffee.' ), $props['content'], 'The Micropub plugin\'s properties are kept.' );
		$this->assertSame( array( get_permalink( $venue ), 'https://cafe.example.com/' ), $props['url'] );
		$this->assertSame( array( self::LAT ), $props['latitude'] );
		$this->assertSame( array( '1 Penn Square' ), $props['street-address'] );
		$this->assertSame( array( 1 ), $props['visits'], 'The private post doesn\'t count.' );
		$this->assertSame( array( get_permalink( $parent ) ), $props['parent'] );
		$this->assertSame( array( $seen ), $props['photo'], 'Only the photo of a post anyone could see.' );
		$this->assertNotContains( $private, $props['photo'] );
		$this->assertArrayNotHasKey( 'distance', $props );
		$this->assertCount( 1, $resp['children'], 'Not the private or excluded venues inside.' );
		$this->assertSame( array( 'h-card' ), $resp['children'][0]['type'] );
		$this->assertSame( array( get_permalink( $child ) ), $resp['children'][0]['properties']['url'] );

		// Only the properties asked for.
		$resp = $this->query(
			$base,
			array(
				'url'        => get_permalink( $venue ),
				'properties' => array( 'visits', 'content', 7 ),
			)
		);
		$this->assertSame(
			array(
				'properties' => array(
					'content' => array( 'Good coffee.' ),
					'visits'  => array( 1 ),
				),
			),
			$resp
		);
		$this->assertSame(
			array( 'name' => array( 'Cafe' ) ),
			$this->query(
				$base,
				array(
					'url'        => get_permalink( $venue ),
					'properties' => 'name',
				)
			)['properties']
		);
	}

	/**
	 * A venue whose location is protected has no location or photos; one the user may not see is left alone.
	 */
	public function test_venue_access() {
		$venue = $this->venue( 'Office', 'protected' );
		$this->visit( $venue );
		$props = $this->query( array(), array( 'url' => get_permalink( $venue ) ) )['properties'];
		$this->assertSame( array( 'Office' ), $props['name'] );
		$this->assertArrayNotHasKey( 'latitude', $props );
		$this->assertArrayNotHasKey( 'photo', $props );

		$venue = $this->venue( 'Home', 'private' );
		$base  = array( 'properties' => array( 'name' => array( 'Home' ) ) );
		$this->assertSame( $base, $this->query( $base, array( 'url' => get_permalink( $venue ) ) ) );

		wp_set_current_user( (int) get_post_field( 'post_author', $venue ) );
		$this->assertSame( array( self::LAT ), $this->query( $base, array( 'url' => get_permalink( $venue ) ) )['properties']['latitude'], 'Its editor sees it.' );
	}

	/**
	 * A venue's page the Micropub plugin didn't find is answered; other posts, queries and errors are left alone.
	 */
	public function test_other_responses() {
		$venue = $this->venue( 'Cafe' );
		$error = new WP_REST_Response( array( 'error' => 'invalid_request' ), 400 );
		$resp  = $this->query( $error, array( 'url' => add_query_arg( 'venue', get_post_field( 'post_name', $venue ), home_url( '/' ) ) ) );
		$this->assertSame( array( 'Cafe' ), $resp['properties']['name'], 'A plain permalink.' );

		$post_id = self::factory()->post->create();
		$base    = array( 'properties' => array( 'name' => array( 'A post' ) ) );
		$this->assertSame( $base, $this->query( $base, array( 'url' => get_permalink( $post_id ) ) ) );
		$this->assertSame( $error, $this->query( $error, array( 'url' => 'https://example.com/nowhere' ) ) );
		$this->assertSame( $base, $this->query( $base, array() ) );
		$this->assertSame(
			$base,
			Location_Plugins::micropub_query_source(
				$base,
				array(
					'q'   => 'config',
					'url' => get_permalink( $venue ),
				)
			)
		);
		$wp_error = new WP_Error( 'forbidden', 'No' );
		$this->assertSame( $wp_error, $this->query( $wp_error, array( 'url' => get_permalink( $venue ) ) ) );
	}
}

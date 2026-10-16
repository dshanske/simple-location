<?php
/**
 * Tests for the ActivityPub integration in Location_Plugins.
 *
 * The ActivityPub plugin isn't loaded; its hooks are called directly with the arguments it passes.
 *
 * @package Simple_Location
 */

/**
 * ActivityPub integration tests.
 */
class ActivityPubTest extends WP_UnitTestCase {

	/**
	 * Sample location.
	 *
	 * @var array
	 */
	protected static $geo = array(
		'latitude'  => 45.01,
		'longitude' => -75.44,
		'address'   => 'Test Location',
	);

	/**
	 * Reset the site default and the current user before each test.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'geo_public', 1 );
		wp_set_current_user( 0 );
	}

	/**
	 * Creates a post with the sample location and the given visibility.
	 *
	 * @param string $visibility Visibility, or 'inherit' for none stored.
	 * @return int Post ID.
	 */
	protected function post_with_location( $visibility ) {
		$post_id           = self::factory()->post->create();
		$geo               = static::$geo;
		$geo['visibility'] = $visibility;
		set_post_geodata( $post_id, '', $geo );
		return $post_id;
	}

	/**
	 * Runs the activitypub_post_location filter with the Place the ActivityPub plugin builds from the geo meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Filtered Place.
	 */
	protected function federated_place( $post_id ) {
		$place = array(
			'type'      => 'Place',
			'latitude'  => (float) get_post_meta( $post_id, 'geo_latitude', true ),
			'longitude' => (float) get_post_meta( $post_id, 'geo_longitude', true ),
			'name'      => get_post_meta( $post_id, 'geo_address', true ),
		);
		return apply_filters( 'activitypub_post_location', $place, get_post( $post_id ), $post_id );
	}

	/**
	 * A public location is federated unchanged.
	 */
	public function test_place_public() {
		$this->assertSame(
			array(
				'type'      => 'Place',
				'latitude'  => 45.01,
				'longitude' => -75.44,
				'name'      => 'Test Location',
			),
			$this->federated_place( $this->post_with_location( 'public' ) )
		);
	}

	/**
	 * A private location is not federated.
	 */
	public function test_place_private() {
		$this->assertNull( $this->federated_place( $this->post_with_location( 'private' ) ) );
	}

	/**
	 * With no visibility stored, the site default applies; the ActivityPub plugin alone would federate it.
	 */
	public function test_place_default_visibility() {
		$post_id = $this->post_with_location( 'inherit' );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_public', true ) );

		update_option( 'geo_public', 0 );
		$this->assertNull( $this->federated_place( $post_id ) );

		update_option( 'geo_public', 2 );
		$this->assertSame(
			array(
				'type' => 'Place',
				'name' => 'Test Location',
			),
			$this->federated_place( $post_id )
		);
	}

	/**
	 * A protected location keeps its name but not its coordinates; without a name there is nothing to share.
	 */
	public function test_place_protected() {
		$post_id = $this->post_with_location( 'protected' );
		$this->assertSame(
			array(
				'type' => 'Place',
				'name' => 'Test Location',
			),
			$this->federated_place( $post_id )
		);

		delete_post_meta( $post_id, 'geo_address' );
		$this->assertNull( $this->federated_place( $post_id ) );
	}

	/**
	 * Values other than a Place are passed through.
	 */
	public function test_place_not_array() {
		$post_id = $this->post_with_location( 'public' );
		$this->assertNull( apply_filters( 'activitypub_post_location', null, get_post( $post_id ), $post_id ) );
	}

	/**
	 * Someone who can read a hidden location sees it marked as hidden, unless the filter says otherwise.
	 */
	public function test_get_location_hidden_for_author() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$post_id = $this->post_with_location( 'private' );

		$location = Geo_Data::get_location( 'post', $post_id );
		$this->assertStringContainsString( 'Hidden: Test Location', $location );
		$this->assertStringContainsString( '45.01', $location );

		add_filter( 'sloc_show_hidden_location', '__return_false' );
		$this->assertSame( '', Geo_Data::get_location( 'post', $post_id ) );
	}

	/**
	 * Content rendered for ActivityPub by the logged-in author shows what a visitor sees, and the author's
	 * own view is restored afterwards.
	 */
	public function test_content_rendered_for_activitypub() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$private   = $this->post_with_location( 'private' );
		$protected = $this->post_with_location( 'protected' );

		do_action( 'activitypub_before_get_content', get_post( $private ) );
		$this->assertSame( '', Geo_Data::get_location( 'post', $private ) );
		$location = Geo_Data::get_location( 'post', $protected );
		$this->assertStringContainsString( 'Test Location', $location );
		$this->assertStringNotContainsString( 'Hidden', $location );
		$this->assertStringNotContainsString( '45.01', $location );
		$this->assertSame( 'content', apply_filters( 'activitypub_the_content', 'content', get_post( $private ) ) );

		$this->assertStringContainsString( 'Hidden: Test Location', Geo_Data::get_location( 'post', $private ) );
	}
}

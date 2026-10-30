<?php
/**
 * Tests the location and map added to post content, and the filter to leave them out (#807).
 *
 * @package Simple_Location
 */

/**
 * Content location tests.
 */
class ContentLocationTest extends WP_UnitTestCase {

	/**
	 * Post with a public location and address.
	 *
	 * @var int
	 */
	protected $post_id;

	/**
	 * A post with a public location, as the current post, with the test map provider and the map on every view.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_map_provider', 'sloc_test' );
		update_option( 'sloc_map_display', 1 );
		$this->post_id = self::factory()->post->create();
		set_post_geodata(
			$this->post_id,
			'',
			array(
				'latitude'   => 45.01,
				'longitude'  => -75.44,
				'address'    => 'Test Location',
				'visibility' => 'public',
			)
		);
		$GLOBALS['post'] = get_post( $this->post_id );
		setup_postdata( $GLOBALS['post'] );
	}

	/**
	 * Clean up.
	 */
	public function tear_down() {
		wp_reset_postdata();
		delete_option( 'sloc_map_display' );
		parent::tear_down();
	}

	/**
	 * The callbacks are hooked to the content.
	 */
	public function test_hooks() {
		$this->assertSame( 11, has_filter( 'the_content', array( 'Geo_Data', 'content_map' ) ) );
		if ( ! current_theme_supports( 'simple-location' ) ) {
			$this->assertSame( 12, has_filter( 'the_content', array( 'Geo_Data', 'location_content' ) ) );
		}
	}

	/**
	 * By default the map and the location are added.
	 */
	public function test_added() {
		$this->assertTrue( Geo_Data::add_to_content() );
		$map = Geo_Data::content_map( 'Text' );
		$this->assertStringStartsWith( 'Text', $map );
		$this->assertSame( 'Text' . Geo_Data::get_map( 'post', $this->post_id ), $map );
		$this->assertNotSame( 'Text', $map );
		$location = Geo_Data::location_content( 'Text' );
		$this->assertStringContainsString( 'Test Location', $location );
	}

	/**
	 * The filter returning false leaves the content as it is, for the post it is given.
	 */
	public function test_filter_leaves_content() {
		$seen   = array();
		$filter = function ( $add, $post ) use ( &$seen ) {
			$this->assertTrue( $add );
			$seen[] = $post instanceof WP_Post ? $post->ID : null;
			return false;
		};
		add_filter( 'sloc_content_location', $filter, 10, 2 );
		$this->assertSame( 'Text', Geo_Data::content_map( 'Text' ) );
		$this->assertSame( 'Text', Geo_Data::location_content( 'Text' ) );
		$this->assertSame( array( $this->post_id, $this->post_id ), $seen );
		remove_filter( 'sloc_content_location', $filter, 10 );

		// Only for the posts it says; other posts are unchanged.
		$other = self::factory()->post->create();
		add_filter( 'sloc_content_location', fn( $add, $post ) => $post && $post->ID !== $this->post_id, 10, 2 );
		$this->assertSame( 'Text', Geo_Data::location_content( 'Text' ) );
		$this->assertFalse( Geo_Data::add_to_content() );
		$GLOBALS['post'] = get_post( $other );
		$this->assertTrue( Geo_Data::add_to_content() );
	}

	/**
	 * A value that isn't a boolean is read as one.
	 */
	public function test_filter_value_is_boolean() {
		add_filter( 'sloc_content_location', '__return_zero' );
		$this->assertFalse( Geo_Data::add_to_content() );
		remove_filter( 'sloc_content_location', '__return_zero' );
		add_filter( 'sloc_content_location', fn() => 'yes' );
		$this->assertTrue( Geo_Data::add_to_content() );
	}

	/**
	 * Without a current post, the filter gets null.
	 */
	public function test_without_post() {
		$GLOBALS['post'] = null;
		$received        = 'unset';
		add_filter(
			'sloc_content_location',
			function ( $add, $post ) use ( &$received ) {
				$received = $post;
				return $add;
			},
			10,
			2
		);
		Geo_Data::add_to_content();
		$this->assertNull( $received );
	}
}

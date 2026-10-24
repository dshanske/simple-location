<?php
/**
 * Tests for the GeoJSON feed (#129).
 *
 * @package Simple_Location
 */

/**
 * GeoJSON feed tests.
 */
class GeoJSONTest extends WP_UnitTestCase {

	/**
	 * Creates a post with a location.
	 *
	 * @param string $visibility Location visibility.
	 * @param array  $post       Post fields. Optional.
	 * @param array  $geo        Extra geo data. Optional.
	 * @return int Post ID.
	 */
	protected function post_at( $visibility, $post = array(), $geo = array() ) {
		$post_id = self::factory()->post->create( $post );
		set_post_geodata(
			$post_id,
			'',
			array_merge(
				array(
					'latitude'   => 39.9524,
					'longitude'  => -75.1636,
					'visibility' => $visibility,
				),
				$geo
			)
		);
		return $post_id;
	}

	/**
	 * Runs the feed for the current query and decodes it.
	 *
	 * @return array FeatureCollection.
	 */
	protected function run_feed() {
		ob_start();
		Sloc_GeoJSON::feed();
		return json_decode( ob_get_clean(), true );
	}

	/**
	 * The feed is registered.
	 */
	public function test_registered() {
		global $wp_rewrite;
		$this->assertContains( 'geojson', $wp_rewrite->feeds );
		$this->assertSame( 10, has_action( 'do_feed_geojson', array( 'Sloc_GeoJSON', 'feed' ) ) );
		$this->assertSame( 10, has_action( 'wp_head', array( 'Sloc_GeoJSON', 'link_tag' ) ) );
	}

	/**
	 * A post with a public location is a Point, with its altitude, name, URL, time and address.
	 */
	public function test_feature() {
		$post_id = $this->post_at(
			'public',
			array(
				'post_title' => 'Lunch at <em>City Hall</em>',
				'post_date'  => '2024-07-01 12:00:00',
			),
			array(
				'altitude' => 12.5,
				'address'  => 'City Hall, Philadelphia',
			)
		);
		$feature = Sloc_GeoJSON::feature( $post_id );
		$this->assertSame( 'Feature', $feature['type'] );
		$this->assertSame(
			array(
				'type'        => 'Point',
				'coordinates' => array( -75.1636, 39.9524, 12.5 ),
			),
			$feature['geometry'],
			'Longitude first, as GeoJSON has it.'
		);
		$this->assertSame( 'Lunch at City Hall', $feature['properties']['name'] );
		$this->assertSame( get_permalink( $post_id ), $feature['properties']['url'] );
		$this->assertStringStartsWith( '2024-07-01T12:00:00', $feature['properties']['published'] );
		$this->assertSame( 'City Hall, Philadelphia', $feature['properties']['address'] );
	}

	/**
	 * Posts without a public location, or with a password, are left out.
	 */
	public function test_feature_left_out() {
		$this->assertNull( Sloc_GeoJSON::feature( $this->post_at( 'private' ) ) );
		$this->assertNull( Sloc_GeoJSON::feature( $this->post_at( 'protected' ) ) );
		$this->assertNull( Sloc_GeoJSON::feature( self::factory()->post->create() ) );
		$this->assertNull( Sloc_GeoJSON::feature( $this->post_at( 'public', array( 'post_password' => 'secret' ) ) ) );
		$this->assertNull( Sloc_GeoJSON::feature( 0 ) );

		$post_id = $this->post_at( 'public' );
		add_filter( 'sloc_geojson_feature', '__return_null' );
		$this->assertNull( Sloc_GeoJSON::feature( $post_id ), 'A filter can leave a post out.' );
		remove_filter( 'sloc_geojson_feature', '__return_null' );
	}

	/**
	 * The site feed has the public locations of the latest posts.
	 */
	public function test_site_feed() {
		$public  = $this->post_at( 'public' );
		$private = $this->post_at( 'private' );
		self::factory()->post->create();
		$this->go_to( get_feed_link( 'geojson' ) );
		$this->assertTrue( is_feed( 'geojson' ) );

		$collection = $this->run_feed();
		$this->assertSame( 'FeatureCollection', $collection['type'] );
		$this->assertSame( array( get_permalink( $public ) ), wp_list_pluck( wp_list_pluck( $collection['features'], 'properties' ), 'url' ) );
		$this->assertNotContains( get_permalink( $private ), wp_list_pluck( wp_list_pluck( $collection['features'], 'properties' ), 'url' ) );
	}

	/**
	 * A post's own feed has that post, and a location's feed has its posts.
	 */
	public function test_post_and_location_feeds() {
		$post_id = $this->post_at( 'public' );
		$this->post_at( 'public' );
		$this->go_to( get_post_comments_feed_link( $post_id, 'geojson' ) );
		$collection = $this->run_feed();
		$this->assertCount( 1, $collection['features'] );
		$this->assertSame( get_permalink( $post_id ), $collection['features'][0]['properties']['url'] );

		$term = self::factory()->term->create( array( 'taxonomy' => 'location' ) );
		wp_set_object_terms( $post_id, $term, 'location' );
		// With plain permalinks, the link has &amp; for HTML.
		$this->go_to( html_entity_decode( get_term_feed_link( $term, 'location', 'geojson' ) ) );
		$this->assertTrue( is_tax( 'location' ) );
		$collection = $this->run_feed();
		$this->assertCount( 1, $collection['features'] );
		$this->assertSame( get_permalink( $post_id ), $collection['features'][0]['properties']['url'] );
	}

	/**
	 * The collection can be filtered.
	 */
	public function test_collection_filter() {
		$post_id = $this->post_at( 'public' );
		$filter  = function ( $collection, $posts ) {
			$collection['count'] = count( $posts );
			return $collection;
		};
		add_filter( 'sloc_geojson', $filter, 10, 2 );
		$collection = Sloc_GeoJSON::collection( array( get_post( $post_id ), get_post( self::factory()->post->create() ) ) );
		remove_filter( 'sloc_geojson', $filter, 10 );
		$this->assertCount( 1, $collection['features'] );
		$this->assertSame( 2, $collection['count'] );
	}

	/**
	 * Pages link to their GeoJSON feed: a post with a public location, the home page and location archives.
	 */
	public function test_link_tag() {
		$public = $this->post_at( 'public' );
		$this->go_to( get_permalink( $public ) );
		$this->assertStringContainsString( 'type="application/geo+json"', get_echo( array( 'Sloc_GeoJSON', 'link_tag' ) ) );
		$this->assertStringContainsString( esc_url( get_post_comments_feed_link( $public, 'geojson' ) ), get_echo( array( 'Sloc_GeoJSON', 'link_tag' ) ) );

		$this->go_to( get_permalink( $this->post_at( 'private' ) ) );
		$this->assertSame( '', get_echo( array( 'Sloc_GeoJSON', 'link_tag' ) ), 'Not for a hidden location.' );

		$this->go_to( home_url( '/' ) );
		$this->assertStringContainsString( esc_url( get_feed_link( 'geojson' ) ), get_echo( array( 'Sloc_GeoJSON', 'link_tag' ) ) );

		$term = self::factory()->term->create( array( 'taxonomy' => 'location' ) );
		wp_set_object_terms( $public, $term, 'location' );
		$this->go_to( get_term_link( $term, 'location' ) );
		$this->assertStringContainsString( esc_url( get_term_feed_link( $term, 'location', 'geojson' ) ), get_echo( array( 'Sloc_GeoJSON', 'link_tag' ) ) );
	}

	/**
	 * Rewrite rules without the feed, such as after an update, are flushed once to add it.
	 */
	public function test_maybe_flush_rewrite_rules() {
		$this->set_permalink_structure( '/%postname%/' );
		$has_feed = function () {
			return (bool) preg_grep( '/geojson/', array_keys( (array) get_option( 'rewrite_rules' ) ) );
		};
		$this->assertTrue( $has_feed() );

		$rules = get_option( 'rewrite_rules' );
		foreach ( array_keys( $rules ) as $rule ) {
			if ( false !== strpos( $rule, 'geojson' ) ) {
				unset( $rules[ $rule ] );
			}
		}
		update_option( 'rewrite_rules', $rules );
		delete_transient( 'sloc_geojson_flushed' );
		Sloc_GeoJSON::maybe_flush_rewrite_rules();
		$this->assertTrue( $has_feed() );

		update_option( 'rewrite_rules', $rules );
		Sloc_GeoJSON::maybe_flush_rewrite_rules();
		$this->assertFalse( $has_feed(), 'At most once a day.' );
		delete_transient( 'sloc_geojson_flushed' );
		$this->set_permalink_structure( '' );
	}
}

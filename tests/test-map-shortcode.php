<?php
/**
 * Tests for the [sloc_map] shortcode.
 *
 * @package Simple_Location
 */

/**
 * Map shortcode tests.
 */
class MapShortcodeTest extends WP_UnitTestCase {

	/**
	 * The registered Google map provider, restored after each test.
	 *
	 * @var Map_Provider|null
	 */
	protected $registered;

	/**
	 * Uses Google, which builds its map URLs without requests, as the map provider.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_width', 600 );
		update_option( 'sloc_aspect', 1.5 );
		update_option( 'sloc_zoom', 15 );
		update_option( 'sloc_map_provider', 'google' );
		$this->registered = Loc_Config::map_provider( 'google' );
		Loc_Config::register_provider(
			new Map_Provider_Google(
				array(
					'api'   => 'KEY',
					'style' => 'roadmap',
				)
			)
		);
	}

	/**
	 * Restores the registered provider.
	 */
	public function tear_down() {
		if ( $this->registered ) {
			Loc_Config::register_provider( $this->registered );
		}
		parent::tear_down();
	}

	/**
	 * Creates a published post or attachment with a location.
	 *
	 * @param float  $latitude   Latitude.
	 * @param float  $longitude  Longitude.
	 * @param string $visibility Visibility. Default public.
	 * @param array  $args       Post arguments.
	 * @return int Post ID.
	 */
	protected function located( $latitude, $longitude, $visibility = 'public', $args = array() ) {
		$post_id = self::factory()->post->create( $args );
		set_post_geodata(
			$post_id,
			'',
			array(
				'latitude'   => $latitude,
				'longitude'  => $longitude,
				'visibility' => $visibility,
			)
		);
		return $post_id;
	}

	/**
	 * The shortcode is registered.
	 */
	public function test_registered() {
		$this->assertTrue( shortcode_exists( 'sloc_map' ) );
	}

	/**
	 * Points given in the shortcode are drawn without links, and invalid points are skipped.
	 */
	public function test_points() {
		$html = do_shortcode( '[sloc_map points="39.9524,-75.1636 | 40.7128,-74.006|95,10|nonsense"]' );
		$this->assertStringStartsWith( '<div class="sloc-map-shortcode"><img class="archive-map sloc-map" src="https://maps.googleapis.com/', $html );
		$this->assertStringContainsString( '39.9524%2C-75.1636', $html );
		$this->assertStringContainsString( '40.7128%2C-74.006', $html );
		$this->assertStringNotContainsString( '95%2C10', $html );
		$this->assertStringNotContainsString( 'sloc-archive-map-marker', $html );

		$this->assertCount( 2, Sloc_Map_Shortcode::parse_points( '39.9524,-75.1636|40.7128,-74.006|1,2,3|,' ) );
		$this->assertSame( '', do_shortcode( '[sloc_map points="nonsense"]' ) );
	}

	/**
	 * Posts chosen by ID are drawn when their location is public and they are published, each linked to its post.
	 */
	public function test_ids() {
		$city_hall = $this->located( 39.9524, -75.1636 );
		$museum    = $this->located( 39.9656, -75.1810 );
		$private   = $this->located( 39.9496, -75.1503, 'private' );
		$draft     = $this->located( 39.9489, -75.1500, 'public', array( 'post_status' => 'draft' ) );
		$html      = do_shortcode( sprintf( '[sloc_map ids="%1$d, %2$d,%3$d,%4$d,999999"]', $city_hall, $museum, $private, $draft ) );

		$this->assertStringContainsString( '<div class="sloc-archive-map">', $html );
		$this->assertStringContainsString( 'href="' . get_permalink( $city_hall ) . '"', $html );
		$this->assertStringContainsString( 'href="' . get_permalink( $museum ) . '"', $html );
		$this->assertSame( 2, substr_count( $html, 'sloc-archive-map-marker' ) );
		$this->assertStringNotContainsString( '39.9496', $html );
		$this->assertStringNotContainsString( '39.9489', $html );

		$this->assertSame( '', do_shortcode( sprintf( '[sloc_map ids="%d"]', $private ) ) );
	}

	/**
	 * Without points or IDs, the current post's attached images with a public location are drawn.
	 */
	public function test_attached_images() {
		$post_id = $this->located( 39.9524, -75.1636 );
		$photo   = self::factory()->attachment->create_object(
			'photo.jpg',
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);
		set_post_geodata(
			$photo,
			'',
			array(
				'latitude'  => 39.9656,
				'longitude' => -75.1810,
			)
		);
		// An image without a location isn't drawn.
		self::factory()->attachment->create_object(
			'nogps.jpg',
			$post_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$this->go_to( get_permalink( $post_id ) );
		$this->assertTrue( have_posts() );
		the_post();
		$html = do_shortcode( '[sloc_map]' );
		$this->assertStringContainsString( '39.9656%2C-75.181', $html );
		$this->assertStringContainsString( 'href="' . get_permalink( $photo ) . '"', $html );

		// Images inherit their post's visibility, so a private post's images aren't drawn.
		set_post_geodata( $post_id, 'visibility', 'private' );
		$this->assertSame( '', do_shortcode( '[sloc_map]' ) );
		wp_reset_postdata();
	}

	/**
	 * The size can be set, and doesn't change the provider's size for the page's other maps.
	 */
	public function test_size() {
		$html = do_shortcode( '[sloc_map points="39.9524,-75.1636|40.7128,-74.006" width="300" height="200"]' );
		$this->assertStringContainsString( 'size=300x200', $html );
		$map = Loc_Config::map_provider();
		$map->set(
			array(
				'latitude'  => 39.9524,
				'longitude' => -75.1636,
			)
		);
		$this->assertStringContainsString( 'size=600x400', $map->get_the_static_map() );
	}

	/**
	 * Without a map provider there is no map.
	 */
	public function test_no_provider() {
		update_option( 'sloc_map_provider', 'none' );
		$this->assertSame( '', do_shortcode( '[sloc_map points="39.9524,-75.1636"]' ) );
	}

	/**
	 * At most MAX_POINTS points are drawn.
	 */
	public function test_max_points() {
		$points = implode( '|', array_fill( 0, Sloc_Map_Shortcode::MAX_POINTS + 5, '39.9524,-75.1636' ) );
		$this->assertCount( Sloc_Map_Shortcode::MAX_POINTS, Sloc_Map_Shortcode::parse_points( $points ) );
	}
}

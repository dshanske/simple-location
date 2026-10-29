<?php
/**
 * Tests the static map output options (#765), static map data (#766) and durable image URLs (#764).
 *
 * @package Simple_Location
 */

class StaticMapDataTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

	/**
	 * A 1x1 PNG image.
	 */
	const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

	/**
	 * Philadelphia City Hall.
	 */
	const LAT = 39.9524;
	const LNG = -75.1636;

	public function set_up() {
		parent::set_up();
		update_option( 'sloc_width', 600 );
		update_option( 'sloc_aspect', 1.5 );
		update_option( 'sloc_zoom', 15 );
		update_option( 'sloc_map_cache', 0 );
		update_option( 'sloc_geoapify_api', 'SECRETKEY' );
		delete_option( 'sloc_map_style' );
		delete_option( 'sloc_map_dark_mode' );
		delete_option( 'sloc_attribution' );
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$filesystem = new WP_Filesystem_Direct( null );
		$filesystem->rmdir( wp_upload_dir()['basedir'] . '/' . Sloc_Map_Cache::DIRECTORY, true );
		parent::tear_down();
	}

	protected function mapbox( $args = array() ) {
		return new Map_Provider_Mapbox(
			array_merge(
				array(
					'api'       => 'KEY',
					'latitude'  => self::LAT,
					'longitude' => self::LNG,
				),
				$args
			)
		);
	}

	protected function geoapify() {
		return new Map_Provider_Geoapify(
			array(
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
				'style'     => 'osm-bright',
			)
		);
	}

	protected function mock_geoapify() {
		$this->mock_http(
			array(
				'maps.geoapify.com' => array(
					'body'    => base64_decode( self::PNG ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
					'headers' => array( 'content-type' => 'image/png' ),
				),
			)
		);
	}

	protected function request( $url ) {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		unset( $query['rest_route'] );
		$request = new WP_REST_Request( 'GET', '/sloc_geo/1.0/map-image' );
		$request->set_query_params( $query );
		return rest_get_server()->dispatch( $request );
	}

	protected function public_post() {
		$post_id = self::factory()->post->create();
		set_post_geodata(
			$post_id,
			'',
			array(
				'latitude'   => self::LAT,
				'longitude'  => self::LNG,
				'address'    => 'City Hall',
				'visibility' => 'public',
			)
		);
		return $post_id;
	}

	public function test_default_output_unchanged() {
		update_option( 'sloc_map_dark_mode', 1 );
		$html = $this->mapbox()->get_the_static_map_html( true );
		$this->assertStringContainsString( '<picture>', $html );
		$this->assertStringContainsString( ' srcset="', $html );
		$this->assertStringContainsString( ' loading="lazy"', $html );
	}

	public function test_output_options_off() {
		update_option( 'sloc_map_dark_mode', 1 );
		$map = $this->mapbox();
		$map->set(
			array(
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
				'dark'      => false,
				'srcset'    => false,
				'loading'   => false,
			)
		);
		$html = $map->get_the_static_map_html( true );
		$this->assertMatchesRegularExpression( '#^<a target="_blank" href="[^"]+"><img class="sloc-map" src="[^"]+" width="600" height="400" alt="[^"]+" /></a>$#', $html );
	}

	public function test_each_option_alone() {
		update_option( 'sloc_map_dark_mode', 1 );
		$html = $this->mapbox( array( 'dark' => false ) )->get_the_static_map_html( true );
		$this->assertStringNotContainsString( '<picture>', $html );
		$this->assertStringContainsString( ' srcset="', $html );

		$html = $this->mapbox( array( 'srcset' => 'false' ) )->get_the_static_map_html( true );
		$this->assertStringContainsString( '<picture>', $html );
		$this->assertDoesNotMatchRegularExpression( '#<img [^>]*srcset=#', $html );
		// The dark source is a single image without high-density candidates.
		$this->assertDoesNotMatchRegularExpression( '#<source [^>]*srcset="[^"]* 2x#', $html );

		$html = $this->mapbox( array( 'loading' => 0 ) )->get_the_static_map_html( true );
		$this->assertStringNotContainsString( 'loading=', $html );
	}

	public function test_options_reset_for_next_map() {
		update_option( 'sloc_map_dark_mode', 1 );
		$map = $this->mapbox();
		$map->set(
			array(
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
				'dark'      => false,
			)
		);
		$this->assertNull( $map->get_dark_variant() );
		$map->set(
			array(
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			)
		);
		$this->assertInstanceOf( 'Map_Provider_Mapbox', $map->get_dark_variant() );
	}

	public function test_static_map_data() {
		update_option( 'sloc_map_dark_mode', 1 );
		$map  = $this->mapbox( array( 'location' => 'City Hall' ) );
		$data = $map->get_static_map_data();
		$this->assertSame( array( 'src', 'srcset', 'dark_src', 'dark_srcset', 'link', 'alt', 'width', 'height', 'attribution' ), array_keys( $data ) );
		$this->assertStringContainsString( '/streets-v11/static/', $data['src'] );
		$this->assertStringContainsString( ' 2x', $data['srcset'] );
		$this->assertStringContainsString( '/dark-v10/static/', $data['dark_src'] );
		$this->assertStringContainsString( ' 2x', $data['dark_srcset'] );
		$this->assertSame( $map->get_the_map_url(), $data['link'] );
		$this->assertSame( 'Map showing City Hall', $data['alt'] );
		$this->assertSame( 600, $data['width'] );
		$this->assertSame( 400, $data['height'] );
		$this->assertSame( '', $data['attribution'] );
		// Not escaped for HTML.
		$this->assertStringNotContainsString( '&#038;', $data['src'] . $data['srcset'] );
		// The HTML uses the same image.
		$this->assertStringContainsString( 'src="' . esc_url( $data['src'] ) . '"', $map->get_the_static_map_html( true ) );
	}

	public function test_static_map_data_options() {
		update_option( 'sloc_map_dark_mode', 1 );
		$data = $this->mapbox(
			array(
				'dark'   => false,
				'srcset' => false,
			)
		)->get_static_map_data();
		$this->assertSame( '', $data['srcset'] );
		$this->assertSame( '', $data['dark_src'] );
		$this->assertSame( '', $data['dark_srcset'] );
	}

	public function test_static_map_data_attribution_below() {
		update_option( 'sloc_attribution', 'below' );
		$data = $this->mapbox()->get_static_map_data();
		$this->assertStringContainsString( '© Mapbox', $data['attribution'] );
	}

	public function test_static_map_data_error() {
		$data = $this->mapbox( array( 'api' => '' ) )->get_static_map_data();
		$this->assertWPError( $data );
	}

	public function test_get_post_static_map() {
		update_option( 'sloc_map_provider', 'sloc_test' );
		$post_id = $this->public_post();
		$data    = get_post_static_map( $post_id );
		$this->assertIsArray( $data );
		$this->assertStringStartsWith( 'https://maps.example.com/static?c=39.9524,-75.1636', $data['src'] );
		$this->assertStringContainsString( '@2x 2x', $data['srcset'] );
		$this->assertSame( 'Map showing City Hall', $data['alt'] );

		$data = get_post_static_map( $post_id, array( 'srcset' => false ) );
		$this->assertSame( '', $data['srcset'] );
	}

	public function test_get_post_static_map_visibility() {
		update_option( 'sloc_map_provider', 'sloc_test' );
		$post_id = $this->public_post();
		foreach ( array( 'private', 'protected' ) as $visibility ) {
			set_post_geodata( $post_id, 'visibility', $visibility );
			$this->assertSame( '', get_post_static_map( $post_id ), $visibility );
		}
		$this->assertSame( '', get_post_static_map( self::factory()->post->create() ), 'No location.' );
		$this->assertFalse( get_post_static_map( PHP_INT_MAX ) );
	}

	public function test_get_static_map_without_provider() {
		update_option( 'sloc_map_provider', 'none' );
		$this->assertSame( '', get_post_static_map( $this->public_post() ) );
	}

	public function test_other_object_types() {
		update_option( 'sloc_map_provider', 'sloc_test' );
		$geo        = array(
			'latitude'   => self::LAT,
			'longitude'  => self::LNG,
			'visibility' => 'public',
		);
		$comment_id = self::factory()->comment->create();
		set_comment_geodata( $comment_id, '', $geo );
		$this->assertIsArray( get_comment_static_map( $comment_id ) );

		$user_id = self::factory()->user->create();
		set_user_geodata( $user_id, '', $geo );
		$this->assertIsArray( get_user_static_map( $user_id ) );
		$this->assertFalse( get_user_static_map( PHP_INT_MAX ) );

		$this->assertSame( '', get_term_static_map( self::factory()->term->create() ) );
	}

	public function test_get_post_map_passes_output_options() {
		update_option( 'sloc_map_provider', 'sloc_test' );
		$post_id = $this->public_post();
		$this->assertSame( get_post_map( $post_id ), Geo_Data::get_map( 'post', $post_id ) );
		$this->assertNotSame( '', get_post_map( $post_id, array( 'dark' => false ) ) );
	}

	public function test_durable_url() {
		update_option( 'sloc_map_cache', 1 );
		$this->mock_geoapify();
		$map   = $this->geoapify();
		$route = Sloc_Map_Cache::get_url( $map );
		$this->assertStringContainsString( 'map-image', $route );

		// Once stored, pages use the stored file, and durable copies keep the route.
		$location = $this->request( $route )->get_headers()['Location'];
		$this->assertSame( $location, Sloc_Map_Cache::get_url( $map ) );
		$this->assertSame( $route, Sloc_Map_Cache::get_url( $map, null, true ) );

		// While the provider's own URL is used, durable copies still get the route.
		$sig = Sloc_Map_Cache::sign( $map->get_cache_args() );
		set_transient( Sloc_Map_Cache::PASSTHROUGH . $sig, 'https://maps.example.com/provider.png', 60 );
		$this->assertSame( 'https://maps.example.com/provider.png', Sloc_Map_Cache::get_url( $map ) );
		$this->assertSame( $route, Sloc_Map_Cache::get_url( $map, null, true ) );
	}

	public function test_durable_static_map() {
		update_option( 'sloc_map_cache', 1 );
		$this->mock_geoapify();
		$map   = $this->geoapify();
		$route = Sloc_Map_Cache::get_url( $map );
		$this->request( $route );

		$map->set(
			array(
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
				'durable'   => true,
			)
		);
		$data = $map->get_static_map_data();
		$this->assertSame( $route, $data['src'] );
		$this->assertStringNotContainsString( 'SECRETKEY', $data['src'] . $data['srcset'] );
		$this->assertStringContainsString( 'map-image', $data['srcset'] );
		$this->assertSame( 302, $this->request( $data['src'] )->get_status(), 'The route still serves the image.' );

		$map->set(
			array(
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			)
		);
		$this->assertStringNotContainsString( 'map-image', $map->get_static_map_data()['src'], 'Pages use the stored file.' );
	}

	public function test_durable_without_cache() {
		// Providers whose images the site doesn't store have only their own URL.
		$data = $this->mapbox( array( 'durable' => true ) )->get_static_map_data();
		$this->assertStringContainsString( 'api.mapbox.com', $data['src'] );
	}
}

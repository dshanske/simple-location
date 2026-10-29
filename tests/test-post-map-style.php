<?php
/**
 * Tests the per-post map style family (#35).
 *
 * @package Simple_Location
 */

class PostMapStyleTest extends WP_UnitTestCase {

	const LAT = 39.9524;
	const LNG = -75.1636;

	/**
	 * Registered map providers replaced by these tests, to put back.
	 *
	 * @var Map_Provider[]
	 */
	protected $replaced = array();

	public function set_up() {
		parent::set_up();
		update_option( 'sloc_width', 600 );
		update_option( 'sloc_aspect', 1.5 );
		update_option( 'sloc_map_cache', 0 );
		delete_option( 'sloc_map_style' );
		delete_option( 'sloc_map_dark_mode' );
		foreach ( array( 'mapbox', 'geoapify' ) as $slug ) {
			delete_option( 'sloc_' . $slug . '_style' );
		}
		// Registered copies with keys, so the site's map functions can draw them.
		foreach ( array( 'Map_Provider_Mapbox', 'Map_Provider_Geoapify' ) as $class ) {
			$map                                 = new $class( array( 'api' => 'KEY' ) );
			$this->replaced[ $map->get_slug() ] = Loc_Config::map_provider( $map->get_slug() );
			Loc_Config::register_provider( $map );
		}
	}

	public function tear_down() {
		foreach ( $this->replaced as $map ) {
			if ( $map ) {
				Loc_Config::register_provider( $map );
			}
		}
		parent::tear_down();
	}

	protected function mapbox() {
		return new Map_Provider_Mapbox(
			array(
				'api'       => 'KEY',
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			)
		);
	}

	protected function post( $family = null ) {
		$post_id = self::factory()->post->create();
		set_post_geodata(
			$post_id,
			'',
			array(
				'latitude'   => self::LAT,
				'longitude'  => self::LNG,
				'visibility' => 'public',
			)
		);
		if ( null !== $family ) {
			update_post_meta( $post_id, 'geo_map_style', $family );
		}
		return $post_id;
	}

	public function test_set_style_family() {
		$map       = $this->mapbox();
		$default   = $map->get_the_static_map();
		$satellite = $map->get_family_style( 'satellite' );
		$this->assertTrue( $map->set_style_family( 'satellite' ) );
		$this->assertSame( 'satellite', $map->get_style_family() );
		$this->assertStringContainsString( '/' . $satellite . '/static/', $map->get_the_static_map() );

		// The next map without a family gets the default style back.
		$map->set(
			array(
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			)
		);
		$this->assertSame( '', $map->get_style_family() );
		$this->assertSame( $default, $map->get_the_static_map() );

		$this->assertTrue( $map->set_style_family( '' ), 'The default is always available.' );
		$this->assertFalse( $map->set_style_family( 'bogus' ) );
		$this->assertSame( $default, $map->get_the_static_map() );
	}

	public function test_missing_family_falls_back() {
		$map = new Map_Provider_Geoapify(
			array(
				'api'       => 'KEY',
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			)
		);
		$default = $map->get_the_static_map();
		$this->assertFalse( $map->set_style_family( 'satellite' ), 'Geoapify has no satellite style.' );
		$this->assertSame( '', $map->get_style_family() );
		$this->assertSame( $default, $map->get_the_static_map(), 'The Map Style setting\'s style instead.' );
	}

	public function test_post_style_and_provider_switch() {
		$post_id = $this->post( 'satellite' );

		update_option( 'sloc_map_provider', 'mapbox' );
		$satellite = Loc_Config::map_provider( 'mapbox' )->get_family_style( 'satellite' );
		$this->assertStringContainsString( '/' . $satellite . '/static/', get_post_static_map( $post_id )['src'] );

		// A provider without satellite draws the default style, and the post keeps its choice.
		update_option( 'sloc_map_provider', 'geoapify' );
		$plain = get_post_static_map( $this->post() );
		$this->assertSame( $plain['src'], get_post_static_map( $post_id )['src'] );
		$this->assertSame( 'satellite', get_post_meta( $post_id, 'geo_map_style', true ), 'Not erased.' );

		// And it applies again with a provider that has satellite.
		update_option( 'sloc_map_provider', 'mapbox' );
		$this->assertStringContainsString( '/' . $satellite . '/static/', get_post_static_map( $post_id )['src'] );
	}

	public function test_argument_overrides_post() {
		update_option( 'sloc_map_provider', 'mapbox' );
		$post_id = $this->post( 'satellite' );
		$terrain = Loc_Config::map_provider( 'mapbox' )->get_family_style( 'terrain' );
		$this->assertStringContainsString( '/' . $terrain . '/static/', get_post_static_map( $post_id, array( 'style_family' => 'terrain' ) )['src'] );
		$this->assertStringContainsString( '/' . $terrain . '/', get_post_map( $post_id, array( 'style_family' => 'terrain' ) ) );
	}

	public function test_dark_mode() {
		update_option( 'sloc_map_dark_mode', 1 );
		$map = $this->mapbox();
		$this->assertInstanceOf( 'Map_Provider_Mapbox', $map->get_dark_variant(), 'The default style has a dark variant.' );
		$map->set_style_family( 'standard' );
		$this->assertInstanceOf( 'Map_Provider_Mapbox', $map->get_dark_variant(), 'So does standard.' );
		$map->set_style_family( 'satellite' );
		$this->assertNull( $map->get_dark_variant(), 'Satellite stays satellite in dark mode.' );
	}

	public function test_meta_sanitized() {
		Geo_Data::register_meta();
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'geo_map_style', 'neon' );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_map_style', true ) );
		update_post_meta( $post_id, 'geo_map_style', 'terrain' );
		$this->assertSame( 'terrain', get_post_meta( $post_id, 'geo_map_style', true ) );
		$this->assertSame( 'terrain', get_post_geodata( $post_id, 'map_style' ) );
	}

	public function test_save_meta() {
		$post_id = self::factory()->post->create();
		$_POST   = array(
			'latitude'  => '39.95',
			'longitude' => '-75.16',
			'map_style' => 'satellite',
		);
		Geo_Base::save_meta( 'post', $post_id );
		$this->assertSame( 'satellite', get_post_meta( $post_id, 'geo_map_style', true ) );

		$_POST['map_style'] = '';
		Geo_Base::save_meta( 'post', $post_id );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_map_style', true ), 'The default removes it.' );

		$_POST['map_style'] = 'neon';
		Geo_Base::save_meta( 'post', $post_id );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_map_style', true ) );
		$_POST = array();
	}

	public function test_rest_map_style_family() {
		update_option( 'sloc_map_provider', 'mapbox' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
		$request = new WP_REST_Request( 'GET', '/sloc_geo/1.0/map' );
		$request->set_query_params(
			array(
				'latitude'     => self::LAT,
				'longitude'    => self::LNG,
				'style_family' => 'satellite',
			)
		);
		$data = rest_get_server()->dispatch( $request )->get_data();
		$this->assertStringContainsString( '/' . Loc_Config::map_provider( 'mapbox' )->get_family_style( 'satellite' ) . '/static/', $data['map'] );
		$wp_rest_server = null;
	}

	public function test_family_names() {
		$this->assertSame( Map_Provider::STYLE_FAMILIES, array_keys( Map_Provider::get_style_family_names() ) );
	}
}

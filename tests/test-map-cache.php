<?php
/**
 * Tests for Sloc_Map_Cache, which serves map images from the site for providers whose terms allow it.
 *
 * @package Simple_Location
 */

/**
 * Map image cache tests.
 */
class MapCacheTest extends WP_UnitTestCase {
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

	/**
	 * Two points for archive maps: City Hall and Independence Hall.
	 *
	 * @var array
	 */
	protected $locations = array(
		array( 39.9524, -75.1636 ),
		array( 39.9489, -75.15 ),
	);

	/**
	 * Known map settings, a Geoapify key, and a REST server.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_width', 600 );
		update_option( 'sloc_aspect', 1.5 );
		update_option( 'sloc_zoom', 15 );
		update_option( 'sloc_geoapify_api', 'SECRETKEY' );
		update_option( 'sloc_google_api', 'SECRETKEY' );
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Removes stored images and the REST server.
	 */
	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$filesystem = new WP_Filesystem_Direct( null );
		$filesystem->rmdir( wp_upload_dir()['basedir'] . '/' . Sloc_Map_Cache::DIRECTORY, true );
		parent::tear_down();
	}

	/**
	 * Creates a Geoapify map provider at City Hall.
	 *
	 * @return Map_Provider_Geoapify
	 */
	protected function geoapify() {
		return new Map_Provider_Geoapify(
			array(
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
				'style'     => 'osm-bright',
			)
		);
	}

	/**
	 * Mocks the Geoapify API with an image or another response.
	 *
	 * @param array $response Response. Default a PNG image.
	 */
	protected function mock_geoapify( $response = null ) {
		$this->mock_http(
			array(
				'maps.geoapify.com' => $response ?? array(
					'body'    => base64_decode( self::PNG ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
					'headers' => array( 'content-type' => 'image/png' ),
				),
			)
		);
	}

	/**
	 * Returns the src and srcset URLs of a static map's HTML.
	 *
	 * @param string $html Static map HTML.
	 * @return string[] URLs, src first.
	 */
	protected function image_urls( $html ) {
		preg_match( '/ src="([^"]*)"/', $html, $src );
		preg_match( '/srcset="([^"]*)"/', $html, $srcset );
		preg_match_all( '/([^\s,]\S*) \dx/', html_entity_decode( $srcset[1] ?? '' ), $images );
		return array_merge( array( html_entity_decode( $src[1] ) ), $images[1] );
	}

	/**
	 * Sends a map image URL from a page to the REST server.
	 *
	 * @param string $url Map image URL.
	 * @return WP_REST_Response
	 */
	protected function request( $url ) {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		unset( $query['rest_route'] );
		$request = new WP_REST_Request( 'GET', '/sloc_geo/1.0/map-image' );
		$request->set_query_params( $query );
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * The map image route is registered.
	 */
	public function test_route_registered() {
		$this->assertArrayHasKey( '/sloc_geo/1.0/map-image', rest_get_server()->get_routes() );
	}

	/**
	 * Geoapify maps point to the site, with no API key in the page.
	 */
	public function test_html_served_from_site() {
		$html = $this->geoapify()->get_the_static_map_html( true );
		$this->assertStringNotContainsString( 'SECRETKEY', $html );
		$this->assertStringNotContainsString( 'geoapify.com', $html );

		$urls = $this->image_urls( $html );
		$this->assertCount( 3, $urls );
		foreach ( $urls as $url ) {
			$this->assertStringContainsString( 'sloc_geo/1.0/map-image', rawurldecode( $url ) );
			$this->assertMatchesRegularExpression( '/[?&]sig=[0-9a-f]{32}(&|$)/', $url );
		}
		$this->assertStringContainsString( 'scale=2', $urls[2] );
	}

	/**
	 * Providers whose terms don't allow it keep linking to the provider.
	 */
	public function test_other_providers_unchanged() {
		$map = new Map_Provider_Google(
			array(
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			)
		);
		$this->assertFalse( $map->is_cacheable() );
		$this->assertStringContainsString( 'maps.googleapis.com', $map->get_the_static_map_html() );
		$this->assertStringContainsString( 'maps.googleapis.com', $map->get_archive_map_src( $this->locations ) );
		$this->assertTrue( $this->geoapify()->is_cacheable() );
	}

	/**
	 * The first request fetches the image from the provider and stores it; pages then link to the stored file.
	 */
	public function test_fetch_and_store() {
		$this->mock_geoapify();
		$map  = $this->geoapify();
		$urls = $this->image_urls( $map->get_the_static_map_html() );

		$response = $this->request( $urls[0] );
		$this->assertSame( 302, $response->get_status() );
		$location = $response->get_headers()['Location'];
		$this->assertMatchesRegularExpression( '#/uploads/sloc-maps/[0-9a-f]{32}\.png$#', $location );
		$this->assertSame( 'public, max-age=86400', $response->get_headers()['Cache-Control'] );

		// The provider was asked for the map with the key, which never left the server.
		$this->assertCount( 1, $this->http_requests );
		$this->assertStringContainsString( 'apiKey=SECRETKEY', $this->http_requests[0]['url'] );
		$this->assertStringContainsString( 'center=lonlat:-75.1636,39.9524', $this->http_requests[0]['url'] );

		$directory = wp_upload_dir()['basedir'] . '/sloc-maps';
		$this->assertFileExists( $directory . '/' . basename( $location ) );
		$this->assertFileExists( $directory . '/index.html' );
		$this->assertSame( base64_decode( self::PNG ), file_get_contents( $directory . '/' . basename( $location ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		// Pages now link to the file, and a repeated request doesn't fetch again.
		$this->assertSame( $location, $this->image_urls( $map->get_the_static_map_html() )[0] );
		$this->assertSame( $location, $this->request( $urls[0] )->get_headers()['Location'] );
		$this->assertCount( 1, $this->http_requests );
	}

	/**
	 * Archive maps are served from the site, and the endpoint rebuilds them from the signed points.
	 */
	public function test_archive_map() {
		$this->mock_geoapify();
		$url = $this->geoapify()->get_archive_map_src( $this->locations );
		$this->assertStringNotContainsString( 'SECRETKEY', $url );
		$this->assertStringContainsString( 'points=', $url );

		$this->assertSame( 302, $this->request( $url )->get_status() );
		$this->assertStringContainsString( 'area=rect:', $this->http_requests[0]['url'] );
		$this->assertStringContainsString( 'marker=lonlat:-75.1636,39.9524;size:small;color:red|lonlat:-75.15,39.9489;size:small;color:red', $this->http_requests[0]['url'] );

		// No locations, no map.
		$this->assertSame( '', $this->geoapify()->get_archive_map_src( array() ) );
	}

	/**
	 * A changed parameter or a missing signature is refused before anything is fetched.
	 */
	public function test_signature_required() {
		$this->mock_geoapify();
		$url = $this->image_urls( $this->geoapify()->get_the_static_map_html() )[0];

		$this->assertSame( 403, $this->request( str_replace( 'lat=39.9524', 'lat=40.7128', $url ) )->get_status() );
		$this->assertSame( 403, $this->request( preg_replace( '/&sig=[0-9a-f]+/', '', $url ) )->get_status() );
		$this->assertSame( 403, $this->request( preg_replace( '/sig=[0-9a-f]+/', 'sig=' . str_repeat( '0', 32 ), $url ) )->get_status() );
		$this->assertCount( 0, $this->http_requests );
	}

	/**
	 * Even a correctly signed request can't fetch from a provider whose terms don't allow storing its maps.
	 */
	public function test_provider_must_be_cacheable() {
		$this->mock_http( array() );
		$args = array(
			'provider' => 'google',
			'lat'      => '39.9524',
			'lng'      => '-75.1636',
			'width'    => '600',
			'height'   => '400',
			'zoom'     => '15',
			'style'    => 'roadmap',
			'scale'    => '1',
		);
		foreach ( array( $args, array_merge( $args, array( 'provider' => 'nonexistent' ) ) ) as $map ) {
			$request = new WP_REST_Request( 'GET', '/sloc_geo/1.0/map-image' );
			$request->set_query_params( array_merge( $map, array( 'sig' => Sloc_Map_Cache::sign( $map ) ) ) );
			$this->assertSame( 404, rest_get_server()->dispatch( $request )->get_status() );
		}
		$this->assertCount( 0, $this->http_requests );
	}

	/**
	 * Responses that aren't images, and provider errors, aren't stored.
	 */
	public function test_bad_responses_not_stored() {
		$url = $this->image_urls( $this->geoapify()->get_the_static_map_html() )[0];

		$this->mock_geoapify(
			array(
				'body'    => '<html>Not an image</html>',
				'headers' => array( 'content-type' => 'image/png' ),
			)
		);
		$this->assertSame( 502, $this->request( $url )->get_status() );

		$this->mock_geoapify(
			array(
				'code'    => 401,
				'body'    => base64_decode( self::PNG ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
				'headers' => array( 'content-type' => 'image/png' ),
			)
		);
		$this->assertSame( 502, $this->request( $url )->get_status() );

		$this->mock_geoapify( new WP_Error( 'http_request_failed', 'timeout' ) );
		$this->assertSame( 502, $this->request( $url )->get_status() );

		$this->assertSame( array(), (array) glob( wp_upload_dir()['basedir'] . '/sloc-maps/*.png' ) );
	}

	/**
	 * Without an API key there is no map to serve.
	 */
	public function test_missing_key() {
		$url = $this->image_urls( $this->geoapify()->get_the_static_map_html() )[0];
		update_option( 'sloc_geoapify_api', '' );
		$this->mock_http( array() );
		$this->assertSame( 404, $this->request( $url )->get_status() );
		$this->assertCount( 0, $this->http_requests );
	}

	/**
	 * Signatures depend on every map parameter and on the site's secret, but not on the parameters' order.
	 */
	public function test_sign() {
		$args = $this->geoapify()->get_cache_args();
		$sig  = Sloc_Map_Cache::sign( $args );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $sig );
		$this->assertSame( $sig, Sloc_Map_Cache::sign( array_reverse( $args, true ) ) );
		foreach ( array_keys( $args ) as $key ) {
			$this->assertNotSame( $sig, Sloc_Map_Cache::sign( array_merge( $args, array( $key => $args[ $key ] . '1' ) ) ), $key );
		}
		// Parameters that aren't map parameters aren't signed.
		$this->assertSame( $sig, Sloc_Map_Cache::sign( array_merge( $args, array( 'other' => 'x' ) ) ) );
	}

	/**
	 * The scale can only be set to a density the provider can draw.
	 */
	public function test_set_scale() {
		$map = $this->geoapify();
		$map->set_scale( 3 );
		$this->assertSame( '1', $map->get_cache_args()['scale'] );
		$map->set_scale( 2 );
		$this->assertSame( '2', $map->get_cache_args()['scale'] );
	}
}

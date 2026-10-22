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
	 * Fetches and stores the City Hall map, and returns its stored file's path and the map's route URL.
	 *
	 * @return string[] With 'path' and 'route'.
	 */
	protected function store_map() {
		$this->mock_geoapify();
		$route    = $this->image_urls( $this->geoapify()->get_the_static_map_html() )[0];
		$location = $this->request( $route )->get_headers()['Location'];
		return array(
			'path'  => str_replace( wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], strtok( $location, '?' ) ),
			'route' => $route,
		);
	}

	/**
	 * Makes a stored file look older.
	 *
	 * @param string $path File path.
	 * @param int    $days Age in days.
	 */
	protected function age( $path, $days ) {
		touch( $path, time() - $days * DAY_IN_SECONDS ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch
		clearstatcache();
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
		$this->assertMatchesRegularExpression( '#/uploads/sloc-maps/[0-9a-f]{32}\.png\?v=\d+$#', $location );
		$file = strtok( basename( $location ), '?' );
		$this->assertSame( 'public, max-age=86400', $response->get_headers()['Cache-Control'] );

		// The provider was asked for the map with the key, which never left the server.
		$this->assertCount( 1, $this->http_requests );
		$this->assertStringContainsString( 'apiKey=SECRETKEY', $this->http_requests[0]['url'] );
		$this->assertStringContainsString( 'center=lonlat:-75.1636,39.9524', $this->http_requests[0]['url'] );

		$directory = wp_upload_dir()['basedir'] . '/sloc-maps';
		$this->assertFileExists( $directory . '/' . $file );
		$this->assertFileExists( $directory . '/index.html' );
		$this->assertSame( base64_decode( self::PNG ), file_get_contents( $directory . '/' . $file ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

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

	/**
	 * A stored map older than the refresh age is fetched again and replaced in place, under the same file name.
	 */
	public function test_refresh_after_max_age() {
		$stored = $this->store_map();
		$map    = $this->geoapify();

		$this->age( $stored['path'], 29 );
		$this->assertStringContainsString( '/uploads/sloc-maps/', $this->image_urls( $map->get_the_static_map_html() )[0] );

		$this->age( $stored['path'], 31 );
		$old = filemtime( $stored['path'] );
		$this->assertSame( $stored['route'], $this->image_urls( $map->get_the_static_map_html() )[0] );

		$location = $this->request( $stored['route'] )->get_headers()['Location'];
		$this->assertCount( 2, $this->http_requests );
		clearstatcache();
		$this->assertGreaterThan( $old, filemtime( $stored['path'] ) );
		// The refreshed image gets a new link, but the file name stays the same, so old links keep working.
		$this->assertSame( basename( $stored['path'] ) . '?v=' . filemtime( $stored['path'] ), basename( $location ) );
		$this->assertStringContainsString( '/uploads/sloc-maps/', $this->image_urls( $map->get_the_static_map_html() )[0] );
	}

	/**
	 * With a refresh age of 0, a stored map is never fetched again.
	 */
	public function test_never_refresh() {
		update_option( 'sloc_map_cache_days', 0 );
		$stored = $this->store_map();
		$this->age( $stored['path'], 3650 );
		$this->assertStringContainsString( '/uploads/sloc-maps/', $this->image_urls( $this->geoapify()->get_the_static_map_html() )[0] );
		$this->request( $stored['route'] );
		$this->assertCount( 1, $this->http_requests );
	}

	/**
	 * When a refresh fails, the stored map is still served, and the next request tries again.
	 */
	public function test_failed_refresh_serves_stored_map() {
		$stored = $this->store_map();
		$this->age( $stored['path'], 31 );
		$old = filemtime( $stored['path'] );

		$this->mock_geoapify( array( 'code' => 500 ) );
		$response = $this->request( $stored['route'] );
		$this->assertSame( 302, $response->get_status() );
		$this->assertSame( basename( $stored['path'] ) . '?v=' . $old, basename( $response->get_headers()['Location'] ) );
		clearstatcache();
		$this->assertSame( $old, filemtime( $stored['path'] ) );

		$this->request( $stored['route'] );
		$this->assertCount( 2, $this->http_requests );
	}

	/**
	 * The refresh age comes from the setting and the filter, and a provider's own limit caps it.
	 */
	public function test_max_age() {
		$map = $this->geoapify();
		$this->assertSame( 0, $map->get_max_cache_age() );
		$this->assertSame( 30, Sloc_Map_Cache::get_max_age( $map ) );
		update_option( 'sloc_map_cache_days', 0 );
		$this->assertSame( 0, Sloc_Map_Cache::get_max_age( $map ) );
		update_option( 'sloc_map_cache_days', 90 );
		$this->assertSame( 90, Sloc_Map_Cache::get_max_age( $map ) );

		$filter = function ( $days, $provider ) use ( $map ) {
			$this->assertSame( 90, $days );
			$this->assertSame( $map, $provider );
			return 7;
		};
		add_filter( 'sloc_map_cache_max_age', $filter, 10, 2 );
		$this->assertSame( 7, Sloc_Map_Cache::get_max_age( $map ) );
		remove_filter( 'sloc_map_cache_max_age', $filter, 10 );

		// A provider whose terms allow storing maps for 10 days.
		$limited = new class( array() ) extends Map_Provider_Geoapify {
			/**
			 * Storage limit in days.
			 *
			 * @var int
			 */
			protected $max_cache_age = 10;
		};
		$this->assertSame( 10, $limited->get_max_cache_age() );
		$this->assertSame( 10, Sloc_Map_Cache::get_max_age( $limited ) );
		update_option( 'sloc_map_cache_days', 0 );
		$this->assertSame( 10, Sloc_Map_Cache::get_max_age( $limited ) );
		update_option( 'sloc_map_cache_days', 5 );
		$this->assertSame( 5, Sloc_Map_Cache::get_max_age( $limited ) );
		add_filter(
			'sloc_map_cache_max_age',
			function () {
				return 0;
			}
		);
		$this->assertSame( 10, Sloc_Map_Cache::get_max_age( $limited ) );
	}

	/**
	 * Turning the setting off links maps to the provider again.
	 */
	public function test_setting_off() {
		update_option( 'sloc_map_cache', '0' );
		$map = $this->geoapify();
		$this->assertFalse( Sloc_Map_Cache::is_enabled( $map ) );
		$this->assertStringContainsString( 'maps.geoapify.com', $map->get_the_static_map_html() );
		$this->assertStringContainsString( 'maps.geoapify.com', $map->get_archive_map_src( $this->locations ) );
		update_option( 'sloc_map_cache', '1' );
		$this->assertTrue( Sloc_Map_Cache::is_enabled( $map ) );
	}

	/**
	 * A filter can move the stored maps to another directory.
	 */
	public function test_directory_filter() {
		$uploads = wp_upload_dir();
		add_filter(
			'sloc_map_cache_directory',
			function () use ( $uploads ) {
				return array(
					'path' => $uploads['basedir'] . '/sloc-maps/elsewhere/',
					'url'  => $uploads['baseurl'] . '/sloc-maps/elsewhere/',
				);
			}
		);
		$stored = $this->store_map();
		$this->assertSame( $uploads['basedir'] . '/sloc-maps/elsewhere', dirname( $stored['path'] ) );
		$this->assertFileExists( $stored['path'] );
		$this->assertStringStartsWith( $uploads['baseurl'] . '/sloc-maps/elsewhere/', $this->image_urls( $this->geoapify()->get_the_static_map_html() )[0] );

		// A filter that returns something unusable turns storing off rather than writing anywhere.
		add_filter( 'sloc_map_cache_directory', '__return_false', 20 );
		$this->assertSame( 0, Sloc_Map_Cache::clear() );
		$this->assertFileExists( $stored['path'] );
	}

	/**
	 * Clearing deletes every stored image but keeps the index file.
	 */
	public function test_clear() {
		$stored = $this->store_map();
		$this->request( $this->geoapify()->get_archive_map_src( $this->locations ) );
		$this->assertSame( 2, Sloc_Map_Cache::clear() );
		$this->assertFileDoesNotExist( $stored['path'] );
		$this->assertFileExists( dirname( $stored['path'] ) . '/index.html' );
		$this->assertSame( 0, Sloc_Map_Cache::clear() );
	}

	/**
	 * The Clear Stored Maps button needs a valid nonce and an administrator.
	 */
	public function test_handle_clear() {
		$stored = $this->store_map();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		try {
			Sloc_Map_Cache::handle_clear();
			$this->fail( 'A missing nonce should stop the request.' );
		} catch ( WPDieException $e ) {
			$this->assertFileExists( $stored['path'] );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'sloc_clear_map_cache' );
		try {
			Sloc_Map_Cache::handle_clear();
			$this->fail( 'An editor should not be able to clear maps.' );
		} catch ( WPDieException $e ) {
			$this->assertFileExists( $stored['path'] );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'sloc_clear_map_cache' );
		add_filter(
			'wp_redirect',
			function ( $location ) {
				throw new RuntimeException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		);
		try {
			Sloc_Map_Cache::handle_clear();
			$this->fail( 'The handler should redirect.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'sloc_maps_cleared=1', $e->getMessage() );
		}
		unset( $_REQUEST['_wpnonce'] );
		$this->assertFileDoesNotExist( $stored['path'] );
	}

	/**
	 * The notice after clearing shows the count to administrators.
	 */
	public function test_cleared_notice() {
		$_GET['sloc_maps_cleared'] = '3';
		ob_start();
		Sloc_Map_Cache::cleared_notice();
		$this->assertSame( '', ob_get_clean() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		Sloc_Map_Cache::cleared_notice();
		$this->assertStringContainsString( 'Deleted 3 stored map images.', ob_get_clean() );
		unset( $_GET['sloc_maps_cleared'] );
	}

	/**
	 * Creates a custom static map provider at City Hall, signing its URLs.
	 *
	 * The map image route rebuilds the provider from the settings, so the server and client are settings.
	 *
	 * @param array $args Optional. Constructor arguments that override the settings.
	 * @return Map_Provider_StaticMap
	 */
	protected function staticmap( $args = array() ) {
		update_option( 'sloc_map_provider', 'staticmap' );
		update_option( 'sloc_staticmap_url', 'https://maps.example.org/' );
		update_option( 'sloc_staticmap_client_id', 'blog' );
		update_option( 'sloc_staticmap_client_secret', 'the-secret' );
		return new Map_Provider_StaticMap(
			array_merge(
				array(
					'latitude'  => self::LAT,
					'longitude' => self::LNG,
					'style'     => 'esri/topo',
				),
				$args
			)
		);
	}

	/**
	 * Mocks the custom static map server: its query API fails, and maps are a PNG image with the given headers.
	 *
	 * @param array $headers Optional. More response headers.
	 */
	protected function mock_staticmap( $headers = array() ) {
		$this->mock_http(
			array(
				'/api/v1/'         => new WP_Error( 'http_request_failed', 'No query API in this test' ),
				'maps.example.org' => array(
					'body'    => base64_decode( self::PNG ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
					'headers' => array_merge( array( 'content-type' => 'image/png' ), $headers ),
				),
			)
		);
	}

	/**
	 * Returns the requests made to the custom static map server for map images.
	 *
	 * @return array[] Requests.
	 */
	protected function staticmap_image_requests() {
		return array_values( array_filter( $this->http_requests, fn( $request ) => false === strpos( $request['url'], '/api/v1/' ) ) );
	}

	/**
	 * Custom static maps are stored and served from the site, fetched with the signed URL.
	 */
	public function test_staticmap_served_from_site() {
		$this->mock_staticmap( array( 'cache-control' => 'public, max-age=604800' ) );
		$map = $this->staticmap();
		$this->assertTrue( Sloc_Map_Cache::is_enabled( $map ) );

		$route = $this->image_urls( $map->get_the_static_map_html() )[0];
		$this->assertStringContainsString( 'map-image', $route );
		$this->assertStringNotContainsString( 'maps.example.org', $route );
		$this->assertStringContainsString( 'options=', $route );

		$response = $this->request( $route );
		$this->assertSame( 302, $response->get_status() );
		$this->assertMatchesRegularExpression( '#/uploads/sloc-maps/[0-9a-f]{32}\.png\?v=\d+$#', $response->get_headers()['Location'] );

		$requests = $this->staticmap_image_requests();
		$this->assertCount( 1, $requests );
		$this->assertSame( $map->get_the_static_map(), $requests[0]['url'] );
		$this->assertStringContainsString( '&signature=', $requests[0]['url'] );

		// Archive maps too.
		$this->assertSame( 302, $this->request( $map->get_archive_map_src( $this->locations ) )->get_status() );
		$this->assertCount( 2, $this->staticmap_image_requests() );
	}

	/**
	 * A changed drawing setting gives the map a new image instead of the stored one.
	 */
	public function test_staticmap_options_change_url() {
		$map   = $this->staticmap();
		$route = Sloc_Map_Cache::get_url( $map );
		$this->assertSame( $route, Sloc_Map_Cache::get_url( $this->staticmap() ) );
		foreach ( array(
			'marker_color' => '#43a047',
			'format'       => 'webp',
			'overlays'     => array( 'railways' ),
			'attribution'  => 'text',
		) as $setting => $value ) {
			$this->assertNotSame( $route, Sloc_Map_Cache::get_url( $this->staticmap( array( $setting => $value ) ) ), $setting );
		}
		$this->assertNotSame( $route, Sloc_Map_Cache::get_url( $this->staticmap( array( 'client_id' => 'other' ) ) ) );
	}

	/**
	 * With only a plain client key, the map server marks every image private, so maps aren't served from the site.
	 */
	public function test_staticmap_plain_key_not_cached() {
		$map = $this->staticmap(
			array(
				'client_id'     => '',
				'client_secret' => '',
				'client_key'    => 'plain-key',
			)
		);
		$this->assertFalse( Sloc_Map_Cache::is_enabled( $map ) );
		$this->assertStringStartsWith( 'https://maps.example.org/?', $this->image_urls( $map->get_the_static_map_html() )[0] );

		// Without credentials at all, maps are free and are stored.
		$this->assertTrue( Sloc_Map_Cache::is_enabled( $this->staticmap( array( 'client_secret' => '' ) ) ) );
	}

	/**
	 * Images the map server marks private aren't stored: visitors are sent to the signed URL, for a day.
	 */
	public function test_staticmap_private_passthrough() {
		$this->mock_staticmap( array( 'cache-control' => 'private, max-age=604800' ) );
		$map   = $this->staticmap();
		$route = Sloc_Map_Cache::get_url( $map );

		$response = $this->request( $route );
		$this->assertSame( 302, $response->get_status() );
		$this->assertSame( $map->get_the_static_map(), $response->get_headers()['Location'] );
		$this->assertSame( 'private, max-age=300', $response->get_headers()['Cache-Control'] );
		$this->assertSame( array(), (array) glob( wp_upload_dir()['basedir'] . '/sloc-maps/*.png' ) );

		// Pages link to the map server directly now, and the route doesn't fetch the image again.
		$this->assertSame( $map->get_the_static_map(), Sloc_Map_Cache::get_url( $map ) );
		$this->assertSame( $map->get_the_static_map(), $this->request( $route )->get_headers()['Location'] );
		$this->assertCount( 1, $this->staticmap_image_requests() );

		$sig = Sloc_Map_Cache::sign( $map->get_cache_args() );
		$this->assertEqualsWithDelta( time() + DAY_IN_SECONDS, (int) get_option( '_transient_timeout_' . Sloc_Map_Cache::PASSTHROUGH . $sig ), 5 );
	}

	/**
	 * When the map server drew its default map instead, it isn't stored, and the map is asked for again after 5 minutes.
	 */
	public function test_staticmap_fallback_passthrough() {
		$this->mock_staticmap(
			array(
				'cache-control'  => 'public, max-age=300',
				'x-map-fallback' => 'unavailable; requested=carto/dark; drawn=osm/streets',
			)
		);
		$map = $this->staticmap();
		$this->assertSame( $map->get_the_static_map(), $this->request( Sloc_Map_Cache::get_url( $map ) )->get_headers()['Location'] );
		$this->assertSame( array(), (array) glob( wp_upload_dir()['basedir'] . '/sloc-maps/*.png' ) );

		$sig = Sloc_Map_Cache::sign( $map->get_cache_args() );
		$this->assertEqualsWithDelta( time() + 5 * MINUTE_IN_SECONDS, (int) get_option( '_transient_timeout_' . Sloc_Map_Cache::PASSTHROUGH . $sig ), 5 );

		// Other providers' images are stored whatever their headers say.
		$response = array( 'headers' => array( 'cache-control' => 'private' ) );
		$this->assertSame( 0, $this->geoapify()->get_passthrough_time( $response ) );
	}

	/**
	 * Archive maps with a URL too long for the map server are sent as a POST request with a JSON body.
	 */
	public function test_staticmap_long_archive_map_posted() {
		$this->mock_staticmap();
		$locations = array();
		for ( $i = 0; $i < 120; $i++ ) {
			$locations[] = array( round( 39.9 + $i / 1000, 4 ), round( -75.2 + $i / 1000, 4 ) );
		}
		update_option( 'sloc_staticmap_client_key', 'plain-key' );
		$map = $this->staticmap();
		$this->assertGreaterThan( Map_Provider_StaticMap::MAX_GET_LENGTH, strlen( $map->get_archive_map( $locations ) ) );

		$this->assertSame( 302, $this->request( $map->get_archive_map_src( $locations ) )->get_status() );
		$request = $this->staticmap_image_requests()[0];
		$this->assertSame( 'https://maps.example.org/', $request['url'] );
		$this->assertSame( 'POST', $request['args']['method'] );
		$this->assertSame( 'application/json', $request['args']['headers']['Content-Type'] );
		$body = json_decode( $request['args']['body'], true );
		$this->assertSame( 'plain-key', $body['key'] );
		$this->assertArrayNotHasKey( 'signature', $body );
		$this->assertCount( 120, $body['marker'] );
		$this->assertSame( 'enc:' . Sloc_Polyline::encode( $locations ), $body['polyline'] );
		$this->assertSame( array( 'esri', 'topo' ), array( $body['provider'], $body['style'] ) );

		// The server marks images for a plain key private, and the signed URL is too long to redirect to, so the
		// image is sent for this request, and nothing is stored.
		Sloc_Map_Cache::clear();
		$this->mock_staticmap( array( 'cache-control' => 'private, max-age=604800' ) );
		$route = $map->get_archive_map_src( $locations );
		$response            = $this->request( $route );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'image/png', $response->get_headers()['Content-Type'] );
		$this->assertSame( 'private, max-age=300', $response->get_headers()['Cache-Control'] );
		ob_start();
		$served = apply_filters( 'rest_pre_serve_request', false, $response, new WP_REST_Request(), rest_get_server() );
		$this->assertTrue( $served );
		$this->assertSame( base64_decode( self::PNG ), ob_get_clean() ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$this->assertFalse( apply_filters( 'rest_pre_serve_request', false, new WP_REST_Response(), new WP_REST_Request(), rest_get_server() ), 'Other responses are left alone.' );
		$this->assertSame( $route, $map->get_archive_map_src( $locations ), 'Pages keep linking to the route.' );
		$this->assertSame( array(), (array) glob( wp_upload_dir()['basedir'] . '/sloc-maps/*.png' ) );

		// A short archive map is fetched with its signed URL.
		$this->mock_staticmap();
		$this->assertSame( 302, $this->request( $map->get_archive_map_src( $this->locations ) )->get_status() );
		$request = $this->staticmap_image_requests()[0];
		$this->assertSame( $map->get_archive_map( $this->locations ), $request['url'] );
	}

	/**
	 * The settings are registered, and their fields are shown only when the map provider allows storing its maps.
	 */
	public function test_settings() {
		global $wp_settings_fields;
		require_once ABSPATH . 'wp-admin/includes/template.php';

		Sloc_Map_Cache::init();
		$settings = get_registered_settings();
		$this->assertTrue( $settings['sloc_map_cache']['default'] );
		$this->assertSame( 30, $settings['sloc_map_cache_days']['default'] );
		$this->assertSame( 0, sanitize_option( 'sloc_map_cache_days', '-5' ) );
		$this->assertSame( 12, sanitize_option( 'sloc_map_cache_days', '12' ) );
		$this->assertSame( 30, sanitize_option( 'sloc_map_cache_days', 'soon' ) );

		$wp_settings_fields = array();
		update_option( 'sloc_map_provider', 'google' );
		Sloc_Map_Cache::admin_init();
		$this->assertEmpty( $wp_settings_fields['simloc']['sloc_map'] ?? array() );

		update_option( 'sloc_map_provider', 'geoapify' );
		Sloc_Map_Cache::admin_init();
		$fields = $wp_settings_fields['simloc']['sloc_map'];
		$this->assertArrayHasKey( 'sloc_map_cache', $fields );
		$this->assertArrayHasKey( 'sloc_map_cache_days', $fields );

		$this->store_map();
		ob_start();
		call_user_func( $fields['sloc_map_cache_days']['callback'], $fields['sloc_map_cache_days']['args'] );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'name="sloc_map_cache_days"', $html );
		$this->assertStringContainsString( 'action=sloc_clear_map_cache', $html );
		$this->assertStringContainsString( '_wpnonce=', $html );
		$this->assertStringContainsString( '1 map image is stored.', $html );

		ob_start();
		call_user_func( $fields['sloc_map_cache']['callback'], $fields['sloc_map_cache']['args'] );
		$this->assertStringContainsString( 'GeoApify allows its map images to be stored', ob_get_clean() );
	}
}

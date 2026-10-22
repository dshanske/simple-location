<?php
/**
 * Tests for the map providers in includes/map/ and the Map_Provider base class.
 *
 * Map providers build image and link URLs without making requests, except Mapbox's style list.
 *
 * @package Simple_Location
 */

/**
 * Map provider tests.
 */
class MapProvidersTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

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
	 * Known map settings for every test.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_width', 600 );
		update_option( 'sloc_aspect', 1.5 );
		update_option( 'sloc_zoom', 15 );
	}

	/**
	 * Creates a provider at City Hall with a key.
	 *
	 * @param string $class Provider class.
	 * @param array  $args  Constructor arguments.
	 * @return Map_Provider
	 */
	protected function provider( $class, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'api'       => 'KEY',
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			)
		);
		return new $class( $args );
	}

	/**
	 * Splits a URL into its base and decoded query.
	 *
	 * @param string $url URL.
	 * @return array With 'base' and 'query'.
	 */
	protected function parse( $url ) {
		$this->assertIsString( $url );
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		return array(
			'base'  => strtok( $url, '?' ),
			'query' => $query,
		);
	}

	/**
	 * Returns the URLs in a static map's srcset, keyed by pixel density.
	 *
	 * @param Map_Provider $map Provider.
	 * @return array URLs keyed by density, or an empty array without a srcset.
	 */
	protected function srcset( $map ) {
		$html = $map->get_the_static_map_html( true );
		if ( ! preg_match( '/srcset="([^"]*)"/', $html, $match ) ) {
			return array();
		}
		preg_match_all( '/([^\s,]\S*) (\d)x/', html_entity_decode( $match[1] ), $images, PREG_SET_ORDER );
		$urls = array();
		foreach ( $images as $image ) {
			$urls[ (int) $image[2] ] = $image[1];
		}
		return $urls;
	}

	/**
	 * Every bundled map provider is registered with its slug and name.
	 */
	public function test_providers_registered() {
		$providers = Loc_Config::map_providers();
		foreach ( array( 'geoapify', 'google', 'here', 'locationiq', 'mapbox', 'mapquest', 'staticmap', 'tomtom' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $providers );
			$this->assertNotEmpty( $providers[ $slug ]['name'], $slug );
		}
	}

	/**
	 * The size and zoom come from the settings, and arguments override them.
	 */
	public function test_defaults_from_settings() {
		$map = $this->provider( 'Map_Provider_Sloc_Test' );
		$this->assertSame( 'https://maps.example.com/static?c=39.9524,-75.1636&z=15&s=600x400', $map->get_the_static_map() );

		$map = $this->provider(
			'Map_Provider_Sloc_Test',
			array(
				'width'    => 300,
				'height'   => 200,
				'map_zoom' => 10,
			)
		);
		$this->assertSame( 'https://maps.example.com/static?c=39.9524,-75.1636&z=10&s=300x200', $map->get_the_static_map() );

		// A width that isn't a number falls back to 1024.
		update_option( 'sloc_width', 'wide' );
		$map = $this->provider( 'Map_Provider_Sloc_Test' );
		$this->assertStringEndsWith( '&s=1024x683', $map->get_the_static_map() );
	}

	/**
	 * Without a usable width setting, including before the setting is registered on init, maps use the theme's
	 * content width, and 1024 pixels without one.
	 */
	public function test_default_width_without_setting() {
		global $content_width;
		$saved = $content_width;
		unregister_setting( 'simloc', 'sloc_width' );
		delete_option( 'sloc_width' );

		$content_width = 700; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->assertStringEndsWith( '&s=700x467', $this->provider( 'Map_Provider_Sloc_Test' )->get_the_static_map() );

		update_option( 'sloc_width', 0 );
		$this->assertStringEndsWith( '&s=700x467', $this->provider( 'Map_Provider_Sloc_Test' )->get_the_static_map() );

		$content_width = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->assertStringEndsWith( '&s=1024x683', $this->provider( 'Map_Provider_Sloc_Test' )->get_the_static_map() );

		// A saved width wins over the content width.
		$content_width = 700; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		update_option( 'sloc_width', 500 );
		$this->assertStringEndsWith( '&s=500x333', $this->provider( 'Map_Provider_Sloc_Test' )->get_the_static_map() );

		$content_width = $saved; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		Loc_Config::register_map_settings();
	}

	/**
	 * The default map width is the content width, or 1024 without one.
	 */
	public function test_get_default_map_width() {
		global $content_width;
		$saved = $content_width;
		foreach ( array(
			array( 640, 640 ),
			array( '800', 800 ),
			array( 0, 1024 ),
			array( 1, 1024 ),
			array( null, 1024 ),
			array( 'wide', 1024 ),
		) as $case ) {
			$content_width = $case[0]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$this->assertSame( $case[1], Loc_Config::get_default_map_width(), var_export( $case[0], true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		}
		$content_width = $saved; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Setting an array changes the size, zoom and coordinates together.
	 */
	public function test_set_array() {
		$map = $this->provider( 'Map_Provider_Sloc_Test' );
		$this->assertTrue(
			$map->set(
				array(
					'latitude'  => 40.7128,
					'longitude' => -74.006,
					'width'     => 100,
					'height'    => 50,
					'map_zoom'  => 9,
				)
			)
		);
		$this->assertSame( 'https://maps.example.com/static?c=40.7128,-74.006&z=9&s=100x50', $map->get_the_static_map() );
	}

	/**
	 * A provider is active when it is the chosen map provider.
	 */
	public function test_is_active() {
		update_option( 'sloc_map_provider', 'google' );
		$this->assertTrue( $this->provider( 'Map_Provider_Google' )->is_active() );
		$this->assertFalse( $this->provider( 'Map_Provider_Mapbox' )->is_active() );
	}

	/**
	 * The static map HTML links the image to the map, with a srcset when asked.
	 */
	public function test_static_map_html() {
		$map  = $this->provider( 'Map_Provider_Sloc_Test' );
		$html = $map->get_the_static_map_html();
		$this->assertSame( '<a target="_blank" href="https://maps.example.com/?c=39.9524,-75.1636"><img class="sloc-map" src="https://maps.example.com/static?c=39.9524,-75.1636&#038;z=15&#038;s=600x400" width="600" height="400" alt="Map showing this location" loading="lazy" /></a>', $html );

		$html = $map->get_the_static_map_html( true );
		$this->assertStringContainsString( 'srcset="https://maps.example.com/static?c=39.9524,-75.1636&#038;z=15&#038;s=600x400 1x,https://maps.example.com/static?c=39.9524,-75.1636&#038;z=15&#038;s=600x400@2x 2x"', $html );
		$this->assertStringContainsString( 'width="600" height="400"', $html );
	}

	/**
	 * The map image describes the location by its address, which also names the link around it.
	 */
	public function test_alt_text() {
		$map = $this->provider( 'Map_Provider_Sloc_Test' );
		$map->set(
			array(
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
				'address'   => 'City Hall, <b>Philadelphia</b> "PA"',
			)
		);
		$this->assertSame( 'Map showing City Hall, Philadelphia "PA"', $map->get_alt_text() );
		$this->assertStringContainsString( 'alt="Map showing City Hall, Philadelphia &quot;PA&quot;"', $map->get_the_static_map_html( true ) );

		// A shared provider doesn't keep the last map's address for a map without one.
		$map->set(
			array(
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			)
		);
		$this->assertSame( 'Map showing this location', $map->get_alt_text() );

		$map->set( array( 'location' => 'Independence Hall' ) );
		$this->assertSame( 'Map showing Independence Hall', $map->get_alt_text() );

		$filter = function ( $alt, $provider ) use ( $map ) {
			$this->assertSame( $map, $provider );
			return 'Custom: ' . $alt;
		};
		add_filter( 'sloc_map_alt', $filter, 10, 2 );
		$this->assertStringContainsString( 'alt="Custom: Map showing Independence Hall"', $map->get_the_static_map_html() );
		remove_filter( 'sloc_map_alt', $filter, 10 );
	}

	/**
	 * Post maps use the post's address as their alternative text.
	 */
	public function test_post_map_alt_text() {
		update_option( 'sloc_map_provider', 'google' );
		update_option( 'sloc_google_api', 'KEY' );
		$first  = self::factory()->post->create();
		$second = self::factory()->post->create();
		foreach ( array( $first, $second ) as $post_id ) {
			set_post_geodata(
				$post_id,
				'',
				array(
					'latitude'   => self::LAT,
					'longitude'  => self::LNG,
					'visibility' => 'public',
				)
			);
		}
		set_post_geodata( $first, 'address', 'Lancaster, PA' );

		$this->assertStringContainsString( 'alt="Map showing Lancaster, PA"', Geo_Data::get_map( 'post', $first ) );
		$this->assertStringContainsString( 'alt="Map showing this location"', Geo_Data::get_map( 'post', $second ) );
	}

	/**
	 * A srcset doesn't change the size or density of the provider's later maps.
	 */
	public function test_static_map_html_keeps_size() {
		$map = $this->provider( 'Map_Provider_Sloc_Test' );
		$map->get_the_static_map_html( true );
		$this->assertSame( 'https://maps.example.com/static?c=39.9524,-75.1636&z=15&s=600x400', $map->get_the_static_map() );
	}

	/**
	 * Providers without high-density maps get no srcset, rather than larger maps of a wider area.
	 */
	public function test_static_map_html_without_scales() {
		$map  = $this->provider( 'Map_Provider_TomTom', array( 'style' => 'main' ) );
		$html = $map->get_the_static_map_html( true );
		$this->assertStringContainsString( 'width="600" height="400"', $html );
		$this->assertStringNotContainsString( 'srcset', $html );
	}

	/**
	 * Maps larger than the provider's limit shrink to fit, keeping their shape.
	 */
	public function test_fit_size() {
		update_option( 'sloc_width', 1024 );
		update_option( 'sloc_aspect', 16 / 9 );
		$map = $this->provider( 'Map_Provider_Google', array( 'style' => 'roadmap' ) );
		$this->assertSame( '640x360', $this->parse( $map->get_the_static_map() )['query']['size'] );

		$map->set(
			array(
				'width'  => 2000,
				'height' => 500,
			)
		);
		$this->assertSame( '640x160', $this->parse( $map->get_the_static_map() )['query']['size'] );

		// A tall map is limited by its height.
		$map = $this->provider(
			'Map_Provider_Mapbox',
			array(
				'style'  => 'streets-v11',
				'width'  => 1000,
				'height' => 3000,
			)
		);
		$this->assertStringContainsString( '/427x1280?', $map->get_the_static_map() );

		// A map within the limit is unchanged.
		$map = $this->provider(
			'Map_Provider_Mapbox',
			array(
				'style'  => 'streets-v11',
				'width'  => 1280,
				'height' => 720,
			)
		);
		$this->assertStringContainsString( '/1280x720?', $map->get_the_static_map() );
	}

	/**
	 * Each provider's high-density map shows the same area at twice the pixel density.
	 */
	public function test_high_density_maps() {
		$urls = $this->srcset( $this->provider( 'Map_Provider_Google', array( 'style' => 'roadmap' ) ) );
		$this->assertSame( array( 1, 2 ), array_keys( $urls ) );
		$this->assertSame( '1', $this->parse( $urls[1] )['query']['scale'] );
		$this->assertSame( '2', $this->parse( $urls[2] )['query']['scale'] );
		$this->assertSame( '600x400', $this->parse( $urls[2] )['query']['size'] );

		$urls = $this->srcset( $this->provider( 'Map_Provider_Mapbox', array( 'style' => 'streets-v11' ) ) );
		$this->assertStringContainsString( '/600x400?', $urls[1] );
		$this->assertStringContainsString( '/600x400@2x?', $urls[2] );

		$urls = $this->srcset( $this->provider( 'Map_Provider_Mapquest', array( 'style' => 'dark' ) ) );
		$this->assertSame( '600,400', $this->parse( $urls[1] )['query']['size'] );
		$this->assertSame( '600,400@2x', $this->parse( $urls[2] )['query']['size'] );

		// Geoapify's srcset is served from the site (see MapCacheTest), so check the provider's own URLs.
		$map = $this->provider( 'Map_Provider_Geoapify', array( 'style' => 'osm-bright' ) );
		$this->assertArrayNotHasKey( 'scaleFactor', $this->parse( $map->get_the_static_map() )['query'] );
		$map->set_scale( 2 );
		$this->assertSame( '2', $this->parse( $map->get_the_static_map() )['query']['scaleFactor'] );
		$this->assertSame( '600', $this->parse( $map->get_the_static_map() )['query']['width'] );

		// HERE asks for twice the pixels at scale 2.
		$urls = $this->srcset( $this->provider( 'Map_Provider_Here', array( 'style' => 'explore.day' ) ) );
		$this->assertStringContainsString( '/600x400/png8', $urls[1] );
		$this->assertArrayNotHasKey( 'scale', $this->parse( $urls[1] )['query'] );
		$this->assertStringContainsString( '/1200x800/png8', $urls[2] );
		$this->assertSame( '2', $this->parse( $urls[2] )['query']['scale'] );
	}

	/**
	 * HERE's limit counts image pixels, so there is no high-density map when twice the size won't fit.
	 */
	public function test_here_high_density_limit() {
		$map = $this->provider(
			'Map_Provider_Here',
			array(
				'style'  => 'explore.day',
				'width'  => 1100,
				'height' => 600,
			)
		);
		$this->assertSame( array( 1 ), $map->get_scales() );
		$this->assertSame( array(), $this->srcset( $map ) );
	}

	/**
	 * The custom static map server has a default size limit that a filter can change or remove.
	 */
	public function test_staticmap_max_size() {
		update_option( 'sloc_staticmap_url', 'https://maps.example.org/' );
		$args = array(
			'api'    => null,
			'width'  => 2000,
			'height' => 1000,
		);
		$url  = $this->parse( $this->provider( 'Map_Provider_StaticMap', $args )->get_the_static_map() );
		$this->assertSame( array( '1280', '640' ), array( $url['query']['width'], $url['query']['height'] ) );

		$filter = function () {
			return array(
				'width'  => 800,
				'height' => 800,
			);
		};
		add_filter( 'sloc_staticmap_max_size', $filter );
		$url = $this->parse( $this->provider( 'Map_Provider_StaticMap', $args )->get_the_static_map() );
		$this->assertSame( array( '800', '400' ), array( $url['query']['width'], $url['query']['height'] ) );
		remove_filter( 'sloc_staticmap_max_size', $filter );

		$filter = function () {
			return array(
				'width'  => 0,
				'height' => 0,
			);
		};
		add_filter( 'sloc_staticmap_max_size', $filter );
		$url = $this->parse( $this->provider( 'Map_Provider_StaticMap', $args )->get_the_static_map() );
		$this->assertSame( array( '2000', '1000' ), array( $url['query']['width'], $url['query']['height'] ) );
		remove_filter( 'sloc_staticmap_max_size', $filter );

		// The server's maps get wider, not sharper, so there is no srcset.
		$this->assertSame( array(), $this->srcset( $this->provider( 'Map_Provider_StaticMap', $args ) ) );
	}

	/**
	 * The map server's own size limits, from its query API, replace the default, and the filter still applies.
	 */
	public function test_staticmap_server_limits() {
		$info                         = $this->fixture( 'map/staticmap-api.json' );
		$info['limits']['max_width']  = 1000;
		$info['limits']['max_height'] = 900;
		$this->mock_http( array( 'maps.example.org/api/v1/' => array( 'body' => $info ) ) );

		$args = array(
			'api'    => 'https://maps.example.org/',
			'width'  => 2000,
			'height' => 1000,
		);
		$map  = $this->provider( 'Map_Provider_StaticMap', $args );
		$this->assertSame( array(), $this->http_requests, 'Creating the provider contacts the map server.' );

		$url = $this->parse( $map->get_the_static_map() );
		$this->assertSame( array( '1000', '500' ), array( $url['query']['width'], $url['query']['height'] ) );
		$this->assertSame( 'https://maps.example.org/api/v1/', $this->last_request()['url'] );

		// The limits are stored, so the next map doesn't ask again.
		$this->provider( 'Map_Provider_StaticMap', $args )->get_the_static_map();
		$this->assertCount( 1, $this->http_requests );

		$filter = function ( $max_size ) {
			$this->assertSame(
				array(
					'width'  => 1000,
					'height' => 900,
				),
				$max_size
			);
			return array(
				'width'  => 600,
				'height' => 600,
			);
		};
		add_filter( 'sloc_staticmap_max_size', $filter );
		$url = $this->parse( $this->provider( 'Map_Provider_StaticMap', $args )->get_the_static_map() );
		remove_filter( 'sloc_staticmap_max_size', $filter );
		$this->assertSame( array( '600', '300' ), array( $url['query']['width'], $url['query']['height'] ) );
	}

	/**
	 * The map URLs and sizes in the static map HTML are escaped.
	 */
	public function test_static_map_html_escaped() {
		$map = new class() extends Map_Provider_Sloc_Test {
			/**
			 * A static map URL that tries to break out of the attribute.
			 *
			 * @return string
			 */
			public function get_the_static_map() {
				return 'https://maps.example.com/static?x="><script>alert(1)</script>';
			}

			/**
			 * A map link with a script URL.
			 *
			 * @return string
			 */
			public function get_the_map_url() {
				return 'javascript:alert(2)';
			}
		};
		$map->set( self::LAT, self::LNG );
		foreach ( array( $map->get_the_static_map_html(), $map->get_the_static_map_html( true ) ) as $html ) {
			$this->assertStringNotContainsString( '<script>', $html );
			$this->assertStringNotContainsString( '">', substr( $html, 0, strpos( $html, '<img' ) - 1 ) );
			$this->assertStringNotContainsString( 'javascript:', $html );
		}
	}

	/**
	 * Geo_Data::get_map() renders the chosen provider's map for public locations only.
	 */
	public function test_get_map_uses_chosen_provider() {
		update_option( 'sloc_map_provider', 'sloc_test' );
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
		$this->assertStringContainsString( 'c=39.9524,-75.1636', Geo_Data::get_map( 'post', $post_id ) );

		set_post_geodata( $post_id, 'visibility', 'protected' );
		$this->assertSame( '', Geo_Data::get_map( 'post', $post_id ) );
	}

	/**
	 * Geoapify puts longitude first in its center and markers.
	 */
	public function test_geoapify() {
		$map = $this->provider( 'Map_Provider_Geoapify', array( 'style' => 'osm-bright' ) );
		$this->assertArrayHasKey( 'osm-carto', $map->get_styles() );

		$url = $this->parse( $map->get_the_static_map() );
		$this->assertSame( 'https://maps.geoapify.com/v1/staticmap', $url['base'] );
		$this->assertSame(
			array(
				'apiKey' => 'KEY',
				'center' => 'lonlat:-75.1636,39.9524',
				'format' => 'jpeg',
				'width'  => '600',
				'height' => '400',
				'zoom'   => '15',
				'style'  => 'osm-bright',
				'marker' => 'lonlat:-75.1636,39.9524;size:small;color:red',
			),
			$url['query']
		);

		// The plugin chooses the view, so there is no area, which would override it.
		$url = $this->parse( $map->get_archive_map( $this->locations ) );
		$this->assertArrayNotHasKey( 'area', $url['query'] );
		$this->assertSame( 'lonlat:-75.1568,39.95065', $url['query']['center'] );
		$this->assertSame( '15', $url['query']['zoom'] );
		$this->assertSame( 'lonlat:-75.1636,39.9524;size:small;color:red|lonlat:-75.15,39.9489;size:small;color:red', $url['query']['marker'] );

		$this->assertSame( 'https://www.openstreetmap.org/?mlat=39.9524&mlon=-75.1636#map=15/39.9524/-75.1636', $map->get_the_map_url() );

		$map = $this->provider( 'Map_Provider_Geoapify', array( 'api' => '' ) );
		$this->assertSame( 'missing_api_key', $map->get_the_static_map()->get_error_code() );
		$this->assertSame( '', $map->get_archive_map( $this->locations ) );
	}

	/**
	 * Google builds Static Maps API URLs.
	 */
	public function test_google() {
		$map = $this->provider( 'Map_Provider_Google', array( 'style' => 'roadmap' ) );
		$this->assertArrayHasKey( 'hybrid', $map->get_styles() );

		$url = $this->parse( $map->get_the_static_map() );
		$this->assertSame( 'https://maps.googleapis.com/maps/api/staticmap', $url['base'] );
		$this->assertSame( 'color:red|label:X|39.9524,-75.1636', $url['query']['markers'] );
		$this->assertSame( '600x400', $url['query']['size'] );
		$this->assertSame( 'roadmap', $url['query']['maptype'] );
		$this->assertSame( get_bloginfo( 'language' ), $url['query']['language'] );
		$this->assertSame( '15', $url['query']['zoom'] );
		$this->assertSame( '1', $url['query']['scale'] );
		$this->assertSame( 'KEY', $url['query']['key'] );

		$url = $this->parse( $map->get_archive_map( $this->locations ) );
		$this->assertSame( 'size:tiny|color:red|label:P|39.9524,-75.1636|39.9489,-75.15', $url['query']['markers'] );
		$this->assertSame( 'color:0xff0000ff|weight:5|enc:' . Sloc_Polyline::encode( $this->locations ), $url['query']['path'] );

		$this->assertSame( 'https://www.google.com/maps/search/?api=1&query=39.9524%2C-75.1636', $map->get_the_map_url() );

		$map = $this->provider( 'Map_Provider_Google', array( 'api' => '' ) );
		$this->assertSame( '', $map->get_the_static_map() );
		$this->assertSame( '', $map->get_archive_map( $this->locations ) );
	}

	/**
	 * Google URL signing matches the example in Google's documentation.
	 */
	public function test_google_sign_url() {
		$this->assertSame(
			'https://maps.googleapis.com/maps/api/geocode/json?address=New+York&client=clientID&signature=chaRF2hTJKOScPr-RQCEhZbSzIE=',
			Map_Provider_Google::sign_url( 'https://maps.googleapis.com/maps/api/geocode/json?address=New+York&client=clientID', 'vNIXE0xscrmjlyV-12Nj_BvUPaw=' )
		);
		$this->assertFalse( Map_Provider_Google::sign_url( 'https://maps.googleapis.com/maps/api/staticmap?key=KEY', 'not base64!' ) );
		$this->assertFalse( Map_Provider_Google::sign_url( 'https://maps.googleapis.com/maps/api/staticmap?key=KEY', '' ) );
	}

	/**
	 * With a signing secret, every Google map URL is signed: single maps, each srcset density and archive maps.
	 */
	public function test_google_signed_maps() {
		$secret = 'vNIXE0xscrmjlyV-12Nj_BvUPaw=';
		update_option( 'sloc_google_map_secret', $secret );
		$map = $this->provider( 'Map_Provider_Google' );

		$urls = array_merge( array( $map->get_the_static_map(), $map->get_archive_map( $this->locations ) ), array_values( $this->srcset( $map ) ) );
		$this->assertCount( 4, $urls );
		foreach ( $urls as $url ) {
			$this->assertMatchesRegularExpression( '/&signature=[A-Za-z0-9_-]+=*$/', $url );
			$unsigned = preg_replace( '/&signature=.*$/', '', $url );
			$this->assertSame( $url, Map_Provider_Google::sign_url( $unsigned, $secret ) );
		}

		// The query is encoded as it is signed, so the URL Google receives matches the signature.
		$this->assertStringContainsString( '?markers=color%3Ared%7Clabel%3AX%7C39.9524%2C-75.1636&', $urls[0] );

		// A secret passed in wins over the setting, and an empty one turns signing off.
		$map = $this->provider( 'Map_Provider_Google', array( 'secret' => '' ) );
		$this->assertStringNotContainsString( 'signature=', $map->get_the_static_map() );
	}

	/**
	 * A signing secret that isn't valid gives no map, and tells administrators why.
	 */
	public function test_google_invalid_signing_secret() {
		$map = $this->provider( 'Map_Provider_Google', array( 'secret' => 'not base64!' ) );
		$this->assertSame( 'invalid_signing_secret', $map->get_the_static_map()->get_error_code() );
		$this->assertSame( '', $map->get_archive_map( $this->locations ) );
		$this->assertSame( '', $map->get_the_map() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( 'The Google URL signing secret is not valid. Copy it again from the Google Cloud console.', $map->get_the_map() );
	}

	/**
	 * The Google map provider registers the signing secret setting and adds its field to the API settings.
	 */
	public function test_google_signing_setting() {
		global $wp_settings_fields;
		require_once ABSPATH . 'wp-admin/includes/template.php';

		Map_Provider_Google::init();
		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'sloc_google_map_secret', $settings );
		$this->assertSame( 'sloc_api', $settings['sloc_google_map_secret']['group'] );

		Map_Provider_Google::admin_init();
		$field = $wp_settings_fields['sloc_api']['sloc_api']['sloc_google_map_secret'];
		$this->assertSame( 'password', $field['args']['type'] );
		$this->assertStringContainsString( 'Unsigned requests per day', $field['args']['description'] );
	}

	/**
	 * Without a style setting, both Google maps use the roadmap style.
	 */
	public function test_google_default_style() {
		delete_option( 'sloc_google_style' );
		$map = $this->provider( 'Map_Provider_Google' );
		$this->assertSame( 'roadmap', $this->parse( $map->get_the_static_map() )['query']['maptype'] );
		$this->assertSame( 'roadmap', $this->parse( $map->get_archive_map( $this->locations ) )['query']['maptype'] );

		$map = $this->provider( 'Map_Provider_Google', array( 'style' => '' ) );
		$this->assertSame( 'roadmap', $this->parse( $map->get_archive_map( $this->locations ) )['query']['maptype'] );
	}

	/**
	 * LocationIQ builds v3 static map URLs: centered with a marker, or with a marker per location and a path.
	 */
	public function test_locationiq() {
		$map = $this->provider( 'Map_Provider_LocationIQ' );
		$this->assertSame( array(), $map->get_styles() );

		$url = $this->parse( $map->get_the_static_map() );
		$this->assertSame( 'https://maps.locationiq.com/v3/staticmap', $url['base'] );
		$this->assertSame(
			array(
				'key'     => 'KEY',
				'center'  => '39.9524,-75.1636',
				'zoom'    => '15',
				'size'    => '600x400',
				'markers' => 'icon:large-red-cutout|39.9524,-75.1636',
			),
			$url['query']
		);

		$this->assertSame(
			'https://maps.locationiq.com/v3/staticmap?key=KEY&format=png&size=600x400&center=39.95065,-75.1568&zoom=15&markers=icon:large-red-cutout|39.9524,-75.1636&markers=icon:large-red-cutout|39.9489,-75.15&path=weight:2|color:blue|39.9524,-75.1636|39.9489,-75.15',
			$map->get_archive_map( $this->locations )
		);

		// One location is a marker without a path.
		$this->assertSame(
			'https://maps.locationiq.com/v3/staticmap?key=KEY&format=png&size=600x400&center=39.9524,-75.1636&zoom=15&markers=icon:large-red-cutout|39.9524,-75.1636',
			$map->get_archive_map( array( $this->locations[0] ) )
		);

		$this->assertSame( 'https://www.openstreetmap.org/?mlat=39.9524&mlon=-75.1636#map=15/39.9524/-75.1636', $map->get_the_map_url() );

		$map = $this->provider( 'Map_Provider_LocationIQ', array( 'api' => '' ) );
		$this->assertSame( 'missing_api_key', $map->get_the_static_map()->get_error_code() );
		$this->assertSame( '', $map->get_archive_map( $this->locations ) );
	}

	/**
	 * Mapbox uses its own account for built-in styles and the user's account otherwise.
	 */
	public function test_mapbox_static_map() {
		$map = $this->provider(
			'Map_Provider_Mapbox',
			array(
				'user'  => 'someone',
				'style' => 'streets-v11',
			)
		);
		$this->assertSame( 'https://api.mapbox.com/styles/v1/mapbox/streets-v11/static/pin-s(-75.1636,39.9524)/-75.1636,39.9524,15,0,0/600x400?access_token=KEY', $map->get_the_static_map() );

		$archive = $map->get_archive_map( $this->locations );
		$this->assertSame(
			sprintf(
				'https://api.mapbox.com/styles/v1/mapbox/streets-v11/static/pin-s(-75.1636,39.9524),pin-s(-75.15,39.9489),path-5+f44-0.5(%s)/-75.1568,39.95065,14.75/600x400?access_token=KEY',
				rawurlencode( Sloc_Polyline::encode( $this->locations ) )
			),
			$archive
		);

		$map = $this->provider(
			'Map_Provider_Mapbox',
			array(
				'user'  => 'someone',
				'style' => 'custom123',
			)
		);
		$this->assertStringStartsWith( 'https://api.mapbox.com/styles/v1/someone/custom123/static/', $map->get_the_static_map() );
		$this->assertSame( 'https://www.openstreetmap.org/?mlat=39.9524&mlon=-75.1636#map=15/39.9524/-75.1636', $map->get_the_map_url() );

		$map = $this->provider( 'Map_Provider_Mapbox', array( 'style' => '' ) );
		$this->assertSame( '', $map->get_the_static_map() );
		$this->assertSame( '', $map->get_archive_map( $this->locations ) );
	}

	/**
	 * Mapbox lists the built-in styles plus the user's own styles.
	 */
	public function test_mapbox_styles() {
		$this->assertSame( array(), $this->provider( 'Map_Provider_Mapbox', array( 'user' => '' ) )->get_styles() );

		$map = $this->provider( 'Map_Provider_Mapbox', array( 'user' => 'mapbox' ) );
		$this->assertSame( $map->default_styles(), $map->get_styles() );

		$fixture = $this->fixture( 'map/mapbox-styles.json' );
		$this->mock_http( array( 'api.mapbox.com/styles/v1/someone' => array( 'body' => $fixture ) ) );
		$map    = $this->provider( 'Map_Provider_Mapbox', array( 'user' => 'someone' ) );
		$styles = $map->get_styles();
		$this->assertSame( 'KEY', $this->last_request()['query']['access_token'] );
		$this->assertArrayHasKey( 'streets-v11', $styles );
		foreach ( $fixture as $style ) {
			$this->assertSame( $style['name'], $styles[ $style['id'] ] );
		}
	}

	/**
	 * A user without styles of their own gets the built-in styles.
	 */
	public function test_mapbox_styles_empty() {
		$this->mock_http( array( 'api.mapbox.com/styles/v1/someone' => array( 'body' => '[]' ) ) );
		$map = $this->provider( 'Map_Provider_Mapbox', array( 'user' => 'someone' ) );
		$this->assertSame( $map->default_styles(), $map->get_styles() );
	}

	/**
	 * Mapbox errors carry the HTTP status and Mapbox's message, and a non-JSON error page doesn't warn.
	 */
	public function test_mapbox_styles_errors() {
		$this->mock_http(
			array(
				'api.mapbox.com/styles/v1/someone' => array(
					'code' => 401,
					'body' => array( 'message' => 'Not Authorized - Invalid Token' ),
				),
			)
		);
		$styles = $this->provider( 'Map_Provider_Mapbox', array( 'user' => 'someone' ) )->get_styles();
		$this->assertWPError( $styles );
		$this->assertSame( 'Not Authorized - Invalid Token', $styles->get_error_message() );
		$this->assertSame( array( 'status' => 401 ), $styles->get_error_data() );

		$this->mock_http(
			array(
				'api.mapbox.com/styles/v1/someone' => array(
					'code'    => 502,
					'body'    => '<html><body>Bad Gateway</body></html>',
					'headers' => array( 'content-type' => 'text/html' ),
				),
			)
		);
		$styles = $this->provider( 'Map_Provider_Mapbox', array( 'user' => 'someone' ) )->get_styles();
		$this->assertWPError( $styles );
		$this->assertSame( 'Mapbox styles could not be loaded (HTTP status 502).', $styles->get_error_message() );
		$this->assertSame( array( 'status' => 502 ), $styles->get_error_data() );
	}

	/**
	 * MapQuest builds static map v5 URLs for a location and for a route.
	 */
	public function test_mapquest() {
		$map = $this->provider( 'Map_Provider_Mapquest', array( 'style' => 'dark' ) );
		$this->assertArrayHasKey( 'hyb', $map->get_styles() );

		$url = $this->parse( $map->get_the_static_map() );
		$this->assertSame( 'https://www.mapquestapi.com/staticmap/v5/map', $url['base'] );
		$this->assertSame(
			array(
				'key'       => 'KEY',
				'center'    => '39.9524,-75.1636',
				'size'      => '600,400',
				'type'      => 'dark',
				'locations' => '39.9524,-75.1636',
			),
			$url['query']
		);
		$this->assertSame( 'https://www.mapquest.com/?center=39.9524,-75.1636&zoom=15', $map->get_the_map_url() );

		$archive = $this->parse( $map->get_archive_map( $this->locations ) );
		$this->assertSame( 'https://www.mapquestapi.com/staticmap/v5/map', $archive['base'] );
		$this->assertSame(
			array(
				'key'       => 'KEY',
				'size'      => '600,400',
				'type'      => 'dark',
				'locations' => '39.9524,-75.1636||39.9489,-75.15',
				'shape'     => 'cmp|enc:' . Sloc_Polyline::encode( $this->locations ),
				'center'    => '39.95065,-75.1568',
				'zoom'      => '15',
			),
			$archive['query']
		);

		$map = $this->provider( 'Map_Provider_Mapquest', array( 'api' => '' ) );
		$this->assertSame( '', $map->get_the_static_map() );
		$this->assertSame( '', $map->get_archive_map( $this->locations ) );
	}

	/**
	 * Without a style setting, both MapQuest maps use the basic map style.
	 */
	public function test_mapquest_default_style() {
		delete_option( 'sloc_mapquest_style' );
		$map = $this->provider( 'Map_Provider_Mapquest' );
		$this->assertSame( 'map', $this->parse( $map->get_the_static_map() )['query']['type'] );
		$this->assertSame( 'map', $this->parse( $map->get_archive_map( $this->locations ) )['query']['type'] );

		$map = $this->provider( 'Map_Provider_Mapquest', array( 'style' => '' ) );
		$this->assertSame( 'map', $this->parse( $map->get_archive_map( $this->locations ) )['query']['type'] );
	}

	/**
	 * The custom static map provider sends its parameters to the configured URL.
	 */
	public function test_staticmap() {
		update_option( 'sloc_staticmap_url', 'https://maps.example.org/' );
		$map = $this->provider(
			'Map_Provider_StaticMap',
			array(
				'api'   => null,
				'style' => 'esri/topo',
			)
		);

		$url = $this->parse( $map->get_the_static_map() );
		$this->assertSame( 'https://maps.example.org/', $url['base'] );
		$this->assertSame(
			array(
				'width'     => '600',
				'height'    => '400',
				'zoom'      => '15',
				'latitude'  => '39.9524',
				'longitude' => '-75.1636',
				'marker'    => 'lat:39.9524;lng:-75.1636;icon:pin;size:medium;color:d32f2f',
				'provider'  => 'esri',
				'style'     => 'topo',
			),
			$url['query']
		);

		$archive = $map->get_archive_map( $this->locations );
		$url     = $this->parse( $archive );
		$this->assertSame( 'enc:' . Sloc_Polyline::encode( $this->locations ), $url['query']['polyline'] );
		$this->assertSame( $this->locations, array_chunk( Sloc_Polyline::decode( substr( $url['query']['polyline'], 4 ) ), 2 ) );
		$this->assertArrayNotHasKey( 'path', $url['query'] );
		$this->assertSame( array( 'esri', 'topo' ), array( $url['query']['provider'], $url['query']['style'] ) );
		$this->assertArrayNotHasKey( 'basemap', $url['query'] );
		$this->assertArrayNotHasKey( 'attribution', $url['query'] );
		$this->assertSame( array( 'lat:39.9524;lng:-75.1636;icon:dot;size:small;color:d32f2f', 'lat:39.9489;lng:-75.15;icon:dot;size:small;color:d32f2f' ), $url['query']['marker'] );
		foreach ( array( 'format', 'scale', 'overlay', 'scalebar', 'scalebar_position' ) as $name ) {
			$this->assertArrayNotHasKey( $name, $url['query'], 'Default settings send no ' . $name . '.' );
		}
		$this->assertArrayNotHasKey( 'key', $url['query'] );

		// Without a style, the map server draws its default map.
		$map = $this->provider(
			'Map_Provider_StaticMap',
			array(
				'api'   => null,
				'style' => '',
			)
		);
		$url = $this->parse( $map->get_the_static_map() );
		$this->assertArrayNotHasKey( 'provider', $url['query'] );
		$this->assertArrayNotHasKey( 'style', $url['query'] );

		update_option( 'sloc_staticmap_url', '' );
		$map = $this->provider( 'Map_Provider_StaticMap', array( 'api' => null ) );
		$this->assertSame( 'missing_url', $map->get_the_static_map()->get_error_code() );
		$this->assertSame( '', $map->get_archive_map( $this->locations ) );
	}

	/**
	 * A map server URL passed to the custom static map provider is used instead of the setting.
	 */
	public function test_staticmap_url_argument() {
		update_option( 'sloc_staticmap_url', 'https://opt.example/' );
		$map = $this->provider( 'Map_Provider_StaticMap', array( 'api' => 'https://arg.example/' ) );
		$this->assertSame( 'https://arg.example/', $this->parse( $map->get_the_static_map() )['base'] );

		$map = $this->provider( 'Map_Provider_StaticMap', array( 'api' => null ) );
		$this->assertSame( 'https://opt.example/', $this->parse( $map->get_the_static_map() )['base'] );
	}

	/**
	 * Signs a path and query as the README of Static Maps API 1.0.0 shows, to check the provider's signatures.
	 *
	 * @param string $path_and_query Path and query, as requested.
	 * @param string $secret         Signing secret.
	 * @return string Signature.
	 */
	protected function staticmap_signature( $path_and_query, $secret ) {
		return rtrim( strtr( base64_encode( hash_hmac( 'sha256', $path_and_query, $secret, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * With a plain client key, every map URL carries it as `key`, unsigned.
	 */
	public function test_staticmap_client_key() {
		update_option( 'sloc_staticmap_url', 'https://maps.example.org/' );
		update_option( 'sloc_staticmap_client_key', ' plain-key ' );
		$map = $this->provider( 'Map_Provider_StaticMap', array( 'api' => null ) );

		$url = $this->parse( $map->get_the_static_map() );
		$this->assertSame( 'plain-key', $url['query']['key'] );
		$this->assertArrayNotHasKey( 'signature', $url['query'] );
		$this->assertSame( 'plain-key', $this->parse( $map->get_archive_map( $this->locations ) )['query']['key'] );

		// A client ID without a signing secret doesn't sign, and the plain key is still used.
		update_option( 'sloc_staticmap_client_id', 'blog' );
		$url = $this->parse( $this->provider( 'Map_Provider_StaticMap', array( 'api' => null ) )->get_the_static_map() );
		$this->assertSame( 'plain-key', $url['query']['key'] );
		$this->assertArrayNotHasKey( 'signature', $url['query'] );
	}

	/**
	 * With a client ID and signing secret, map URLs carry the ID as `key` and end in a signature of their path and query.
	 */
	public function test_staticmap_signed_urls() {
		$map = $this->provider(
			'Map_Provider_StaticMap',
			array(
				'api'           => 'https://example.org/maps/',
				'style'         => 'carto/dark',
				'client_id'     => 'blog',
				'client_secret' => 'the-secret',
				'client_key'    => 'plain-key',
			)
		);

		foreach ( array( $map->get_the_static_map(), $map->get_archive_map( $this->locations ) ) as $image ) {
			$this->assertStringStartsWith( 'https://example.org/maps/?', $image );
			$this->assertStringNotContainsString( 'the-secret', $image );
			$this->assertStringNotContainsString( 'plain-key', $image );
			$this->assertMatchesRegularExpression( '/^[^?]+\?[A-Za-z0-9%._~&=-]+&signature=[A-Za-z0-9_-]+$/', $image, 'Every character is percent-encoded and the signature comes last.' );

			list( $signed, $signature ) = explode( '&signature=', substr( $image, strlen( 'https://example.org' ) ) );
			$this->assertSame( $this->staticmap_signature( $signed, 'the-secret' ), $signature );

			$url = $this->parse( $image );
			$this->assertSame( 'blog', $url['query']['key'] );
			$this->assertSame( array( 'carto', 'dark' ), array( $url['query']['provider'], $url['query']['style'] ) );
		}
		$this->assertSame( 'lat:39.9524;lng:-75.1636;icon:pin;size:medium;color:d32f2f', $this->parse( $map->get_the_static_map() )['query']['marker'] );

		// Without the map cache, the page shows the image URL exactly as signed.
		update_option( 'sloc_map_cache', 0 );
		preg_match( '/src="([^"]+)"/', $map->get_the_static_map_html(), $match );
		$this->assertSame( $map->get_the_static_map(), html_entity_decode( $match[1] ) );
	}

	/**
	 * The query API is asked with the client's credentials, signed like the maps, and each client's answers are stored apart.
	 */
	public function test_staticmap_api_with_credentials() {
		$this->mock_http( array( 'maps.example.org/api/v1/providers' => array( 'body' => $this->fixture( 'map/staticmap-providers.json' ) ) ) );
		$args = array(
			'api'           => 'https://maps.example.org/',
			'client_id'     => 'blog',
			'client_secret' => 'the-secret',
		);
		$this->provider( 'Map_Provider_StaticMap', $args )->get_styles();
		$request = $this->last_request();
		$this->assertSame( 'https://maps.example.org/api/v1/providers?key=blog&signature=' . $this->staticmap_signature( '/api/v1/providers?key=blog', 'the-secret' ), $request['url'] );

		$this->provider( 'Map_Provider_StaticMap', $args )->get_styles();
		$this->assertCount( 1, $this->http_requests );

		$this->provider( 'Map_Provider_StaticMap', array( 'api' => 'https://maps.example.org/' ) )->get_styles();
		$this->assertCount( 2, $this->http_requests );
		$this->assertSame( 'https://maps.example.org/api/v1/providers', $this->last_request()['url'] );

		$this->provider(
			'Map_Provider_StaticMap',
			array(
				'api'        => 'https://maps.example.org/',
				'client_key' => 'plain-key',
			)
		)->get_styles();
		$this->assertCount( 3, $this->http_requests );
		$this->assertSame( 'https://maps.example.org/api/v1/providers?key=plain-key', $this->last_request()['url'] );
	}

	/**
	 * The client settings are registered, with fields on the API settings page.
	 */
	public function test_staticmap_client_settings() {
		global $wp_settings_fields;
		Map_Provider_StaticMap::init();
		Map_Provider_StaticMap::admin_init();
		$settings = get_registered_settings();
		foreach ( array( 'sloc_staticmap_client_id', 'sloc_staticmap_client_secret', 'sloc_staticmap_client_key' ) as $option ) {
			$this->assertArrayHasKey( $option, $settings );
			$this->assertArrayHasKey( $option, $wp_settings_fields['sloc_api']['sloc_api'] );
		}
		$this->assertSame( 'password', $wp_settings_fields['sloc_api']['sloc_api']['sloc_staticmap_client_secret']['args']['type'] );
		$this->assertSame( 'password', $wp_settings_fields['sloc_api']['sloc_api']['sloc_staticmap_client_key']['args']['type'] );
		$this->assertSame( 'blog', sanitize_option( 'sloc_staticmap_client_id', ' blog ' ) );
	}

	/**
	 * The marker, image format, overlay and scale bar settings are sent with every map.
	 */
	public function test_staticmap_display_settings() {
		update_option( 'sloc_staticmap_url', 'https://maps.example.org/' );
		update_option( 'sloc_staticmap_format', 'webp' );
		update_option( 'sloc_staticmap_marker', 'dot' );
		update_option( 'sloc_staticmap_marker_color', '#43A047' );
		update_option( 'sloc_staticmap_marker_size', 'large' );
		update_option( 'sloc_staticmap_scalebar', 'both' );
		update_option( 'sloc_staticmap_scalebar_position', 'topright' );
		update_option( 'sloc_staticmap_overlays', array( 'railways', 'seamarks' ) );
		$map = $this->provider( 'Map_Provider_StaticMap', array( 'api' => null ) );

		$url = $this->parse( $map->get_the_static_map() )['query'];
		$this->assertSame( 'lat:39.9524;lng:-75.1636;icon:dot;size:large;color:43a047', $url['marker'] );
		$this->assertSame( 'webp', $url['format'] );
		$this->assertSame( 'railways,seamarks', $url['overlay'] );
		$this->assertSame( array( 'both', 'topright' ), array( $url['scalebar'], $url['scalebar_position'] ) );

		// Archive maps draw small dots in the marker color, with the same settings.
		$url = $this->parse( $map->get_archive_map( $this->locations ) )['query'];
		$this->assertSame( 'lat:39.9489;lng:-75.15;icon:dot;size:small;color:43a047', $url['marker'][1] );
		$this->assertSame( array( 'webp', 'railways,seamarks', 'both' ), array( $url['format'], $url['overlay'], $url['scalebar'] ) );

		// Arguments override the settings.
		$map = $this->provider(
			'Map_Provider_StaticMap',
			array(
				'api'      => null,
				'format'   => 'jpeg',
				'overlays' => 'seamarks',
				'scalebar' => '',
			)
		);
		$url = $this->parse( $map->get_the_static_map() )['query'];
		$this->assertSame( array( 'jpg', 'seamarks' ), array( $url['format'], $url['overlay'] ) );
		$this->assertArrayNotHasKey( 'scalebar', $url );
	}

	/**
	 * A scale bar in the site's units follows the Units to Display setting.
	 */
	public function test_staticmap_scalebar_site_units() {
		update_option( 'sloc_staticmap_url', 'https://maps.example.org/' );
		update_option( 'sloc_staticmap_scalebar', 'site' );

		update_option( 'sloc_measurements', 'imperial' );
		$url = $this->parse( $this->provider( 'Map_Provider_StaticMap', array( 'api' => null ) )->get_the_static_map() )['query'];
		$this->assertSame( array( 'imperial', 'bottomleft' ), array( $url['scalebar'], $url['scalebar_position'] ) );

		update_option( 'sloc_measurements', 'si' );
		$url = $this->parse( $this->provider( 'Map_Provider_StaticMap', array( 'api' => null ) )->get_the_static_map() )['query'];
		$this->assertSame( 'metric', $url['scalebar'] );
	}

	/**
	 * The display settings are sanitized to what the map server accepts.
	 */
	public function test_staticmap_sanitize_display_settings() {
		$this->assertSame( 'png8', Map_Provider_StaticMap::sanitize_format( ' PNG8 ' ) );
		$this->assertSame( 'jpg', Map_Provider_StaticMap::sanitize_format( 'jpeg' ) );
		$this->assertSame( '', Map_Provider_StaticMap::sanitize_format( 'gif' ) );
		$this->assertSame( '', Map_Provider_StaticMap::sanitize_format( null ) );

		$this->assertSame( 'dot', Map_Provider_StaticMap::sanitize_marker( 'dot' ) );
		$this->assertSame( 'pin', Map_Provider_StaticMap::sanitize_marker( 'small-red-cutout' ) );

		$this->assertSame( '#43a047', Map_Provider_StaticMap::sanitize_marker_color( '43A047' ) );
		$this->assertSame( '#43a047', Map_Provider_StaticMap::sanitize_marker_color( '#43a047' ) );
		$this->assertSame( '#d32f2f', Map_Provider_StaticMap::sanitize_marker_color( 'red' ) );
		$this->assertSame( '#d32f2f', Map_Provider_StaticMap::sanitize_marker_color( '#43a047;icon:http' ) );
		$this->assertSame( '#d32f2f', Map_Provider_StaticMap::sanitize_marker_color( array() ) );

		$this->assertSame( 'large', Map_Provider_StaticMap::sanitize_marker_size( 'LARGE' ) );
		$this->assertSame( 'medium', Map_Provider_StaticMap::sanitize_marker_size( 'huge' ) );

		$this->assertSame( 'site', Map_Provider_StaticMap::sanitize_scalebar( 'site' ) );
		$this->assertSame( '', Map_Provider_StaticMap::sanitize_scalebar( 'nautical' ) );
		$this->assertSame( 'topleft', Map_Provider_StaticMap::sanitize_scalebar_position( 'topleft' ) );
		$this->assertSame( 'bottomleft', Map_Provider_StaticMap::sanitize_scalebar_position( 'middle' ) );

		$this->assertSame( array( 'railways', 'seamarks' ), Map_Provider_StaticMap::sanitize_overlays( array( 'railways', '', 'Seamarks', 'railways', 'bad id', 3, array() ) ) );
		$this->assertSame( array( 'railways', 'seamarks' ), Map_Provider_StaticMap::sanitize_overlays( 'railways, seamarks' ) );
		$this->assertSame( array(), Map_Provider_StaticMap::sanitize_overlays( null ) );
	}

	/**
	 * High-density maps are offered when the map server draws them and twice the map's size fits its limits.
	 */
	public function test_staticmap_scales() {
		// The srcset shows the map server's URLs without the map cache.
		update_option( 'sloc_map_cache', 0 );
		$this->mock_http( array( 'maps.example.org/api/v1/' => array( 'body' => $this->fixture( 'map/staticmap-api.json' ) ) ) );
		$args = array(
			'api'    => 'https://maps.example.org/',
			'width'  => 600,
			'height' => 400,
		);
		$map  = $this->provider( 'Map_Provider_StaticMap', $args );
		$urls = $this->srcset( $map );
		$this->assertSame( array( 1, 2 ), array_keys( $urls ) );
		$this->assertArrayNotHasKey( 'scale', $this->parse( $urls[1] )['query'] );
		$this->assertSame( '2', $this->parse( $urls[2] )['query']['scale'] );
		$this->assertSame( array( '600', '400' ), array( $this->parse( $urls[2] )['query']['width'], $this->parse( $urls[2] )['query']['height'] ) );

		// Twice 700 pixels is over the server's limit of 1280.
		$args['width'] = 700;
		$this->assertSame( array(), $this->srcset( $this->provider( 'Map_Provider_StaticMap', $args ) ) );

		// A server that only draws normal maps.
		$info           = $this->fixture( 'map/staticmap-api.json' );
		$info['scales'] = array( 1 );
		set_transient( 'sloc_staticmap_' . md5( 'https://maps.example.org/api/v1/|' ), $info );
		$args['width'] = 600;
		$this->assertSame( array(), $this->srcset( $this->provider( 'Map_Provider_StaticMap', $args ) ) );
	}

	/**
	 * The overlays field lists the map server's overlays, and keeps chosen ones the server doesn't offer now.
	 */
	public function test_staticmap_overlays_field() {
		update_option( 'sloc_staticmap_overlays', array( 'seamarks', 'gone' ) );
		$this->mock_http( array( 'maps.example.org/api/v1/overlays' => array( 'body' => $this->fixture( 'map/staticmap-overlays.json' ) ) ) );
		$args = array(
			'label_for' => 'sloc_staticmap_overlays',
			'provider'  => $this->provider( 'Map_Provider_StaticMap', array( 'api' => 'https://maps.example.org/' ) ),
		);
		ob_start();
		Map_Provider_StaticMap::overlays_field( $args );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<input type="hidden" name="sloc_staticmap_overlays[]" value="" />', $html );
		$this->assertMatchesRegularExpression( '#value="railways" /> OpenRailwayMap</label>#', $html );
		$this->assertMatchesRegularExpression( '#value="seamarks" checked(=["\']checked["\'])? /> OpenSeaMap seamarks</label>#', $html );
		$this->assertMatchesRegularExpression( '#value="gone" checked(=["\']checked["\'])? /> gone</label>#', $html );
		$this->assertStringContainsString( 'draws at most 3 overlays', $html );

		// The hidden empty value saves an empty list when every box is unchecked.
		$this->assertSame( array(), Map_Provider_StaticMap::sanitize_overlays( array( '' ) ) );

		$this->mock_http( array( 'other.example.org' => new WP_Error( 'http_request_failed', 'Connection refused' ) ) );
		delete_option( 'sloc_staticmap_overlays' );
		$args['provider'] = $this->provider( 'Map_Provider_StaticMap', array( 'api' => 'https://other.example.org/' ) );
		ob_start();
		Map_Provider_StaticMap::overlays_field( $args );
		$this->assertStringContainsString( 'offers no overlays', ob_get_clean() );
	}

	/**
	 * The display settings are registered, with fields on the map settings page.
	 */
	public function test_staticmap_display_settings_registered() {
		global $wp_settings_fields;
		Map_Provider_StaticMap::init();
		Map_Provider_StaticMap::admin_init();
		$settings = get_registered_settings();
		foreach ( array( 'format', 'marker', 'marker_color', 'marker_size', 'scalebar', 'scalebar_position', 'overlays' ) as $name ) {
			$this->assertArrayHasKey( 'sloc_staticmap_' . $name, $settings );
			$this->assertArrayHasKey( 'sloc_staticmap_' . $name, $wp_settings_fields['simloc']['sloc_map'] );
		}
		$this->assertSame( 'color', $wp_settings_fields['simloc']['sloc_map']['sloc_staticmap_marker_color']['args']['type'] );
		$this->assertSame( '#43a047', sanitize_option( 'sloc_staticmap_marker_color', '43A047' ) );
		$this->assertSame( array( 'seamarks' ), sanitize_option( 'sloc_staticmap_overlays', array( '', 'seamarks' ) ) );
	}

	/**
	 * By default the map server draws the credit on the image; it can draw text only, in another corner.
	 */
	public function test_staticmap_attribution_on_image() {
		update_option( 'sloc_staticmap_url', 'https://maps.example.org/' );
		$map = $this->provider( 'Map_Provider_StaticMap', array( 'api' => null ) );
		$url = $this->parse( $map->get_the_static_map() )['query'];
		$this->assertArrayNotHasKey( 'attribution', $url );
		$this->assertArrayNotHasKey( 'attribution_position', $url );
		$this->assertSame( '', $map->get_attribution_html() );

		update_option( 'sloc_staticmap_attribution', 'text' );
		update_option( 'sloc_staticmap_attribution_position', 'topleft' );
		$map = $this->provider( 'Map_Provider_StaticMap', array( 'api' => null ) );
		foreach ( array( $map->get_the_static_map(), $map->get_archive_map( $this->locations ) ) as $image ) {
			$url = $this->parse( $image )['query'];
			$this->assertSame( array( 'text', 'topleft' ), array( $url['attribution'], $url['attribution_position'] ) );
		}
		$this->assertSame( '', $map->get_attribution_html() );

		// Other providers draw their credit on the image.
		$this->assertSame( '', $this->provider( 'Map_Provider_Geoapify' )->get_attribution_html() );
	}

	/**
	 * Under the map, the credit is the map server's, for the map's provider, style and overlays, with only links kept,
	 * and the image is drawn without one.
	 */
	public function test_staticmap_attribution_below() {
		$fixture         = $this->fixture( 'map/staticmap-attribution.json' );
		$fixture['html'] = '<script>alert(1)</script><b>Powered by</b> <a href="https://www.esri.com" onclick="x()">Esri</a> | ' . $fixture['html'];
		$this->mock_http( array( 'maps.example.org/api/v1/attribution' => array( 'body' => $fixture ) ) );
		$map = $this->provider(
			'Map_Provider_StaticMap',
			array(
				'api'                  => 'https://maps.example.org/',
				'style'                => 'esri/topo',
				'overlays'             => array( 'railways', 'seamarks' ),
				'attribution'          => 'below',
				'attribution_position' => 'topleft',
			)
		);

		$html = $map->get_the_map();
		$this->assertMatchesRegularExpression( '#^<a target="_blank" href="[^"]+"><img [^>]+></a><p class="sloc-map-attribution">.+</p>$#', $html );
		$this->assertStringContainsString( 'Powered by <a href="https://www.esri.com">Esri</a> |', $html );
		$this->assertStringContainsString( '<a href="https://www.openrailwaymap.org/">OpenRailwayMap</a>', $html );
		$this->assertStringNotContainsString( 'script', $html );
		$this->assertStringNotContainsString( 'onclick', $html );
		$this->assertStringNotContainsString( '<b>', $html );

		$attribution_requests = array_values( array_filter( $this->http_requests, fn( $request ) => false !== strpos( $request['url'], '/api/v1/attribution' ) ) );
		$this->assertCount( 1, $attribution_requests );
		parse_str( (string) wp_parse_url( $attribution_requests[0]['url'], PHP_URL_QUERY ), $query );
		$this->assertSame(
			array(
				'provider' => 'esri',
				'style'    => 'topo',
				'overlay'  => 'railways,seamarks',
			),
			$query
		);

		$url = $this->parse( $map->get_the_static_map() )['query'];
		$this->assertSame( 'none', $url['attribution'] );
		$this->assertArrayNotHasKey( 'attribution_position', $url );
		$this->assertSame( 'none', $this->parse( $map->get_archive_map( $this->locations ) )['query']['attribution'] );

		// The credit is stored, so drawing the maps didn't fetch it again.
		$attribution_requests = array_filter( $this->http_requests, fn( $request ) => false !== strpos( $request['url'], '/api/v1/attribution' ) );
		$this->assertCount( 1, $attribution_requests );

		// A map without a location shows no credit either.
		update_option( 'sloc_staticmap_url', '' );
		$map = $this->provider(
			'Map_Provider_StaticMap',
			array(
				'api'         => null,
				'attribution' => 'below',
			)
		);
		$this->assertSame( '', $map->get_the_map() );
	}

	/**
	 * When the map server can't supply the credit, it is drawn on the image instead, so the map is never without one.
	 */
	public function test_staticmap_attribution_below_fallback() {
		$this->mock_http( array( 'maps.example.org' => new WP_Error( 'http_request_failed', 'Connection refused' ) ) );
		$map = $this->provider(
			'Map_Provider_StaticMap',
			array(
				'api'                  => 'https://maps.example.org/',
				'attribution'          => 'below',
				'attribution_position' => 'topleft',
			)
		);
		$this->assertSame( '', $map->get_attribution_html() );
		$url = $this->parse( $map->get_the_static_map() )['query'];
		$this->assertArrayNotHasKey( 'attribution', $url );
		$this->assertSame( 'topleft', $url['attribution_position'] );
		$this->assertStringEndsWith( '</a>', $map->get_the_map() );

		// An answer without HTML counts the same.
		$this->mock_http( array( 'other.example.org' => array( 'body' => array( 'text' => 'Credit' ) ) ) );
		$map = $this->provider(
			'Map_Provider_StaticMap',
			array(
				'api'         => 'https://other.example.org/',
				'attribution' => 'below',
			)
		);
		$this->assertSame( '', $map->get_attribution_html() );
	}

	/**
	 * The attribution settings are sanitized, registered and shown with a description.
	 */
	public function test_staticmap_attribution_settings() {
		global $wp_settings_fields;
		$this->assertSame( 'below', Map_Provider_StaticMap::sanitize_attribution( 'BELOW' ) );
		$this->assertSame( 'image', Map_Provider_StaticMap::sanitize_attribution( 'none' ) );
		$this->assertSame( 'topleft', Map_Provider_StaticMap::sanitize_attribution_position( 'topleft' ) );
		$this->assertSame( 'bottomright', Map_Provider_StaticMap::sanitize_attribution_position( 'center' ) );

		Map_Provider_StaticMap::init();
		Map_Provider_StaticMap::admin_init();
		$this->assertArrayHasKey( 'sloc_staticmap_attribution', get_registered_settings() );
		$this->assertArrayHasKey( 'sloc_staticmap_attribution_position', get_registered_settings() );
		$field = $wp_settings_fields['simloc']['sloc_map']['sloc_staticmap_attribution'];
		ob_start();
		call_user_func( $field['callback'], $field['args'] );
		$html = ob_get_clean();
		$this->assertStringContainsString( '<option value="below"', $html );
		$this->assertStringContainsString( '<p class="description">The map providers&#039; terms require their credit.', $html );
	}

	/**
	 * Archive map markers are placed from the map server's plan, asked for with the image's parameters and stored.
	 */
	public function test_staticmap_archive_marker_boxes() {
		$this->mock_http(
			array(
				'maps.example.org/api/v1/plan' => array( 'body' => $this->fixture( 'map/staticmap-plan.json' ) ),
				'maps.example.org/api/v1/'     => array( 'body' => $this->fixture( 'map/staticmap-api.json' ) ),
			)
		);
		$map = $this->provider(
			'Map_Provider_StaticMap',
			array(
				'api'   => 'https://maps.example.org/',
				'style' => 'esri/topo',
			)
		);

		$boxes = $map->get_archive_marker_boxes( $this->locations );
		$this->assertSame( array( 0, 1 ), array_keys( $boxes ) );
		// Marker 1's box is 134,139 to 149,154 on a 600x400 image.
		$this->assertEqualsWithDelta( array( 22.333, 34.75, 24.833, 38.5 ), $boxes[0], 0.001 );
		$this->assertEqualsWithDelta( array( 75.167, 61.25, 77.667, 65 ), $boxes[1], 0.001 );

		$plan_requests = array_values( array_filter( $this->http_requests, fn( $request ) => false !== strpos( $request['url'], '/api/v1/plan' ) ) );
		$this->assertCount( 1, $plan_requests );
		$this->assertSame( 'GET', $plan_requests[0]['args']['method'] );
		// The plan is for the same map as the image.
		$this->assertSame(
			wp_parse_url( $map->get_archive_map( $this->locations ), PHP_URL_QUERY ),
			wp_parse_url( $plan_requests[0]['url'], PHP_URL_QUERY )
		);

		// The plan is stored.
		$map->get_archive_marker_boxes( $this->locations );
		$this->assertCount( 1, array_filter( $this->http_requests, fn( $request ) => false !== strpos( $request['url'], '/api/v1/plan' ) ) );
	}

	/**
	 * Markers partly outside the image are clipped to it, and markers wholly outside it, or that aren't a location's, are
	 * left out.
	 */
	public function test_staticmap_archive_marker_boxes_outside() {
		$plan            = $this->fixture( 'map/staticmap-plan.json' );
		$plan['markers'] = array(
			array(
				'n'   => 1,
				'box' => array( -10, 390, 10, 410 ),
			),
			array(
				'n'   => 2,
				'box' => array( 610, 10, 625, 25 ),
			),
			array(
				'n'   => 3,
				'box' => array( 100, 100, 115, 115 ),
			),
			array( 'n' => 1 ),
			'marker',
		);
		$this->mock_http( array( 'maps.example.org' => array( 'body' => $plan ) ) );
		$map   = $this->provider( 'Map_Provider_StaticMap', array( 'api' => 'https://maps.example.org/' ) );
		$boxes = $map->get_archive_marker_boxes( $this->locations );
		$this->assertSame( array( 0 ), array_keys( $boxes ) );
		$this->assertEqualsWithDelta( array( 0, 97.5, 1.667, 100 ), $boxes[0], 0.001 );
	}

	/**
	 * Without a usable plan, archive maps have no marker positions.
	 */
	public function test_staticmap_archive_marker_boxes_unavailable() {
		$map = $this->provider( 'Map_Provider_StaticMap', array( 'api' => null ) );
		$this->assertSame( array(), $map->get_archive_marker_boxes( $this->locations ) );

		$this->mock_http( array( 'maps.example.org' => new WP_Error( 'http_request_failed', 'Connection refused' ) ) );
		$map = $this->provider( 'Map_Provider_StaticMap', array( 'api' => 'https://maps.example.org/' ) );
		$this->assertSame( array(), $map->get_archive_marker_boxes( $this->locations ) );
		$this->assertSame( array(), $map->get_archive_marker_boxes( array() ) );

		// A plan without an image size.
		$plan = $this->fixture( 'map/staticmap-plan.json' );
		unset( $plan['width'] );
		$this->mock_http( array( 'other.example.org' => array( 'body' => $plan ) ) );
		$map = $this->provider( 'Map_Provider_StaticMap', array( 'api' => 'https://other.example.org/' ) );
		$this->assertSame( array(), $map->get_archive_marker_boxes( $this->locations ) );

		// The query API only answers GET requests, so a map too long for a URL isn't planned.
		$this->mock_http( array( 'third.example.org' => array( 'body' => $this->fixture( 'map/staticmap-plan.json' ) ) ) );
		$locations = array();
		for ( $i = 0; $i < 300; $i++ ) {
			$locations[] = array( round( 39.94 + $i / 5000, 4 ), round( -75.18 + $i / 5000, 4 ) );
		}
		$map = $this->provider( 'Map_Provider_StaticMap', array( 'api' => 'https://third.example.org/' ) );
		$this->assertSame( array(), $map->get_archive_marker_boxes( $locations ) );
		$this->assertCount( 0, array_filter( $this->http_requests, fn( $request ) => false !== strpos( $request['url'], '/api/v1/plan' ) ) );
	}

	/**
	 * The archive map links each marker to its post, positioned in percent over the image.
	 */
	public function test_archive_map_html_links() {
		$this->mock_http(
			array(
				'maps.example.org/api/v1/plan' => array( 'body' => $this->fixture( 'map/staticmap-plan.json' ) ),
				'maps.example.org/api/v1/'     => array( 'body' => $this->fixture( 'map/staticmap-api.json' ) ),
			)
		);
		$map   = $this->provider( 'Map_Provider_StaticMap', array( 'api' => 'https://maps.example.org/' ) );
		$posts = array(
			self::factory()->post->create( array( 'post_title' => 'City Hall' ) ),
			self::factory()->post->create( array( 'post_title' => 'Independence "Hall" & Park' ) ),
		);
		$html = $map->get_archive_map_html( $this->locations, $posts );
		$this->assertMatchesRegularExpression( '#^<div class="sloc-archive-map"><img class="archive-map sloc-map" src="[^"]+" srcset="[^"]+ 1x, [^"]+ 2x" alt="Map of the locations listed on this page" /><a [^>]+></a><a [^>]+></a></div>$#', $html );
		$this->assertStringContainsString(
			sprintf( '<a class="sloc-archive-map-marker" href="%s" style="left:22.333%%;top:34.75%%;width:2.5%%;height:3.75%%" title="City Hall" aria-label="City Hall"></a>', get_permalink( $posts[0] ) ),
			$html
		);
		$this->assertStringContainsString( 'href="' . get_permalink( $posts[1] ) . '" style="left:75.167%;top:61.25%;', $html );
		$this->assertStringContainsString( 'title="Independence &#8220;Hall&#8221; &amp; Park"', $html );

		// Without the posts, or with a different number of them, the map isn't clickable.
		$plain = '#^<img class="archive-map sloc-map" src="[^"]+"( srcset="[^"]+")? alt="[^"]+" />$#';
		$this->assertMatchesRegularExpression( $plain, $map->get_archive_map_html( $this->locations ) );
		$this->assertMatchesRegularExpression( $plain, $map->get_archive_map_html( $this->locations, array( $posts[0] ) ) );
	}

	/**
	 * Providers whose archive maps the plugin centres, with their marker box in pixels from the location.
	 *
	 * @return array
	 */
	public function data_centred_providers() {
		return array(
			'Google'     => array( 'Map_Provider_Google', array( -8, -22, 8, 1 ) ),
			'Mapbox'     => array( 'Map_Provider_Mapbox', array( -12, -28, 12, 2 ) ),
			'HERE'       => array( 'Map_Provider_Here', array( -10, -10, 10, 10 ) ),
			'Geoapify'   => array( 'Map_Provider_Geoapify', array( -12, -28, 12, 2 ) ),
			'LocationIQ' => array( 'Map_Provider_LocationIQ', array( -16, -48, 16, 2 ) ),
			'MapQuest'   => array( 'Map_Provider_MapQuest', array( -12, -28, 12, 2 ) ),
		);
	}

	/**
	 * The plugin's view fits every location inside the padding, as closely as the provider's zoom levels allow, and
	 * each marker's box is around its location.
	 *
	 * @dataProvider data_centred_providers
	 *
	 * @param string $class      Provider class.
	 * @param int[]  $marker_box Marker box in pixels from the location.
	 */
	public function test_archive_view_fits_locations( $class, $marker_box ) {
		// Philadelphia, New York and Baltimore: too far apart for the site's zoom.
		$locations = array(
			array( 39.9524, -75.1636 ),
			array( 40.7128, -74.006 ),
			array( 39.2904, -76.6122 ),
		);
		$map       = $this->provider( $class, array( 'style' => 'streets-v11' ) );
		$view      = $map->get_archive_view( $locations );
		$this->assertIsArray( $view );
		$this->assertLessThan( 15, $view['zoom'] );

		$boxes = $map->get_archive_marker_boxes( $locations );
		$this->assertSame( array( 0, 1, 2 ), array_keys( $boxes ) );
		$xs = array();
		$ys = array();
		foreach ( $boxes as $box ) {
			$xs[] = $box[0] * 6 - $marker_box[0];
			$ys[] = $box[1] * 4 - $marker_box[1];
			$this->assertEqualsWithDelta( $marker_box[2] - $marker_box[0], ( $box[2] - $box[0] ) * 6, 0.001 );
		}
		foreach ( array_merge( $xs, $ys ) as $pixel ) {
			$this->assertGreaterThanOrEqual( Map_Provider::ARCHIVE_PADDING - 0.01, $pixel );
		}
		$this->assertLessThanOrEqual( 600 - Map_Provider::ARCHIVE_PADDING + 0.01, max( $xs ) );
		$this->assertLessThanOrEqual( 400 - Map_Provider::ARCHIVE_PADDING + 0.01, max( $ys ) );
		// The view is centred on the locations.
		$this->assertEqualsWithDelta( 300, ( max( $xs ) + min( $xs ) ) / 2, 0.01 );
		$this->assertEqualsWithDelta( 200, ( max( $ys ) + min( $ys ) ) / 2, 0.01 );
		// One zoom step closer (a whole level, or 0.01 for fractional zooms) wouldn't fit.
		$step = is_int( $view['zoom'] ) ? 2 : pow( 2, 0.01 );
		$this->assertTrue( ( max( $xs ) - min( $xs ) ) * $step > 536 || ( max( $ys ) - min( $ys ) ) * $step > 336 );
	}

	/**
	 * Mapbox takes fractional zooms, rounded down to two decimals; the others take whole zooms.
	 */
	public function test_archive_view_zoom() {
		$map = $this->provider( 'Map_Provider_Mapbox', array( 'style' => 'streets-v11' ) );
		$this->assertSame(
			array(
				'latitude'  => 39.95065,
				'longitude' => -75.1568,
				'zoom'      => 14.75,
			),
			$map->get_archive_view( $this->locations )
		);
		$view = $this->provider( 'Map_Provider_Google' )->get_archive_view( $this->locations );
		$this->assertSame( 15, $view['zoom'] );

		// One location is shown at the site's zoom, no closer than the provider allows.
		$this->assertSame(
			array(
				'latitude'  => 39.9524,
				'longitude' => -75.1636,
				'zoom'      => 15,
			),
			$this->provider( 'Map_Provider_Google' )->get_archive_view( array( $this->locations[0] ) )
		);
		$view = $this->provider( 'Map_Provider_LocationIQ', array( 'map_zoom' => 21 ) )->get_archive_view( array( $this->locations[0] ) );
		$this->assertSame( 18, $view['zoom'] );
	}

	/**
	 * Without a view the plugin can choose, the provider fits the archive map itself and its markers aren't clickable.
	 */
	public function test_archive_view_unavailable() {
		$map = $this->provider( 'Map_Provider_Google' );
		// Either side of the antimeridian.
		$fiji = array( array( -17.7, 178.1 ), array( -13.8, -171.8 ) );
		$this->assertNull( $map->get_archive_view( $fiji ) );
		$this->assertSame( array(), $map->get_archive_marker_boxes( $fiji ) );
		$this->assertArrayNotHasKey( 'center', $this->parse( $map->get_archive_map( $fiji ) )['query'] );

		$this->assertNull( $map->get_archive_view( array() ) );
		$this->assertNull( $map->get_archive_view( array( array( 'north', -75.1 ) ) ) );
		$this->assertNull( $this->provider( 'Map_Provider_Google', array( 'width' => 0 ) )->get_archive_view( $this->locations ) );
		$this->assertNull( $this->provider( 'Map_Provider_StaticMap', array( 'api' => 'https://maps.example.org/' ) )->get_archive_view( $this->locations ) );
	}

	/**
	 * Google archive maps link their markers to their posts, like the custom static map.
	 */
	public function test_archive_map_html_links_google() {
		$map   = $this->provider( 'Map_Provider_Google' );
		$posts = array(
			self::factory()->post->create( array( 'post_title' => 'City Hall' ) ),
			self::factory()->post->create( array( 'post_title' => 'Independence Hall' ) ),
		);
		$html  = $map->get_archive_map_html( $this->locations, $posts );
		$this->assertMatchesRegularExpression( '#^<div class="sloc-archive-map"><img class="archive-map sloc-map" src="https://maps\.googleapis\.com/[^"]+" srcset="[^"]+" alt="[^"]+" /><a [^>]+></a><a [^>]+></a></div>$#', $html );
		$this->assertStringContainsString( 'href="' . get_permalink( $posts[0] ) . '"', $html );
		$this->assertStringContainsString( 'title="Independence Hall"', $html );
	}

	/**
	 * Archive maps have a 2x image in their srcset showing the same area, for each provider that draws one.
	 *
	 * @dataProvider data_hidpi_archive_providers
	 *
	 * @param string $class    Provider class.
	 * @param string $expected What the 2x URL has that the 1x URL doesn't.
	 */
	public function test_archive_srcset( $class, $expected ) {
		update_option( 'sloc_map_cache', 0 );
		$map    = $this->provider( $class, array( 'style' => 'streets-v11' ) );
		$srcset = $map->get_archive_srcset( $this->locations );
		$this->assertMatchesRegularExpression( '#^(\S+) 1x, (\S+) 2x$#', $srcset );
		preg_match( '#^(\S+) 1x, (\S+) 2x$#', $srcset, $urls );
		$this->assertSame( $map->get_archive_map( $this->locations ), $urls[1] );
		$this->assertStringNotContainsString( $expected, $urls[1] );
		$this->assertStringContainsString( $expected, $urls[2] );
		// The 2x image is fitted to the same view.
		$view = $map->get_archive_view( $this->locations );
		$map->set_scale( 2 );
		$this->assertSame( $view, $map->get_archive_view( $this->locations ) );
		$this->assertSame( $urls[2], $map->get_archive_map( $this->locations ) );
		$map->set_scale( 1 );

		// The srcset is in the archive map's HTML, and the provider is back at 1x afterwards.
		$this->assertStringContainsString( ' srcset="' . esc_attr( $srcset ) . '"', $map->get_archive_map_html( $this->locations ) );
		$this->assertSame( $urls[1], $map->get_archive_map( $this->locations ) );
	}

	/**
	 * Providers with high-density archive maps, and what their 2x URL has.
	 *
	 * @return array
	 */
	public function data_hidpi_archive_providers() {
		return array(
			'Google'   => array( 'Map_Provider_Google', 'scale=2' ),
			'Mapbox'   => array( 'Map_Provider_Mapbox', '/600x400@2x?' ),
			'HERE'     => array( 'Map_Provider_Here', '/1200x800/png8?' ),
			'Geoapify' => array( 'Map_Provider_Geoapify', 'scaleFactor=2' ),
			'MapQuest' => array( 'Map_Provider_MapQuest', 'size=600,400@2x' ),
		);
	}

	/**
	 * Providers without a high-density image, and maps that can't be drawn, have no archive srcset.
	 */
	public function test_archive_srcset_unavailable() {
		$map = $this->provider( 'Map_Provider_LocationIQ' );
		$this->assertSame( '', $map->get_archive_srcset( $this->locations ) );
		$this->assertStringNotContainsString( 'srcset', $map->get_archive_map_html( $this->locations ) );

		$map = $this->provider( 'Map_Provider_Google', array( 'api' => '' ) );
		$this->assertSame( '', $map->get_archive_srcset( $this->locations ) );
		$this->assertStringNotContainsString( 'srcset', $map->get_archive_map_html( $this->locations ) );

		// HERE can't draw twice the size past its limit.
		$map = $this->provider( 'Map_Provider_Here', array( 'width' => 1500, 'height' => 1000 ) );
		$this->assertSame( '', $map->get_archive_srcset( $this->locations ) );
	}

	/**
	 * Cached archive maps have a srcset of the site's map image URLs, one per density.
	 */
	public function test_archive_srcset_cached() {
		update_option( 'sloc_map_cache', 1 );
		$map    = $this->provider( 'Map_Provider_Geoapify' );
		$srcset = $map->get_archive_srcset( $this->locations );
		$this->assertStringNotContainsString( 'geoapify.com', $srcset );
		$this->assertStringContainsString( 'scale=1', $srcset );
		$this->assertMatchesRegularExpression( '#scale=2&[^ ]+ 2x$#', $srcset );
	}

	/**
	 * Geoapify archive images stored before the plugin chose their view aren't reused.
	 */
	public function test_geoapify_archive_cache_args() {
		$map = $this->provider( 'Map_Provider_Geoapify' );
		$this->assertSame( 'view', $map->get_cache_args( $this->locations )['options'] );
		$this->assertArrayNotHasKey( 'options', $map->get_cache_args() );
	}

	/**
	 * Providers that fit their own archive maps, and don't say where markers are, draw a plain archive map, as before.
	 */
	public function test_archive_map_html_plain() {
		$map  = new class( array(
			'api'   => 'KEY',
			'style' => 'streets-v11',
		) ) extends Map_Provider_Mapbox {
			/**
			 * The provider fits its own archive maps.
			 *
			 * @var int
			 */
			protected $tile_size = 0;
		};
		$post = self::factory()->post->create();
		$this->assertNull( $map->get_archive_view( $this->locations ) );
		$this->assertSame( array(), $map->get_archive_marker_boxes( $this->locations ) );
		$this->assertStringContainsString( '/auto/600x400', $map->get_archive_map( $this->locations ) );
		$this->assertMatchesRegularExpression( '#^<img class="archive-map sloc-map" src="https://api\.mapbox\.com/[^"]+" srcset="[^"]+" alt="[^"]+" />$#', $map->get_archive_map_html( $this->locations, array( $post, $post ) ) );

		// Without a map, the image is empty, as it was.
		$map = $this->provider( 'Map_Provider_StaticMap', array( 'api' => null ) );
		$this->assertSame( '<img class="archive-map sloc-map" src="" alt="Map of the locations listed on this page" />', $map->get_archive_map_html( $this->locations, array( $post, $post ) ) );
	}

	/**
	 * Map server URLs from before 1.0.0, which drew maps at img.php, draw them at the server's root.
	 */
	public function test_staticmap_url_img_php() {
		$this->mock_http( array( 'example.org/maps/api/v1/' => array( 'body' => $this->fixture( 'map/staticmap-api.json' ) ) ) );
		foreach ( array( 'https://example.org/maps/img.php', 'https://example.org/maps/index.php', 'https://example.org/maps' ) as $api ) {
			$map = $this->provider( 'Map_Provider_StaticMap', array( 'api' => $api ) );
			$this->assertSame( 'https://example.org/maps/', $this->parse( $map->get_the_static_map() )['base'], $api );
		}
		$this->assertSame( 'https://example.org/maps/api/v1/', $this->http_requests[0]['url'] );
	}

	/**
	 * Style settings from before 6.1.0 are converted to the provider and style the map server now calls them.
	 */
	public function test_staticmap_sanitize_style() {
		$this->assertSame( 'osm/streets', Map_Provider_StaticMap::sanitize_style( 'osm' ) );
		$this->assertSame( 'opentopomap/topo', Map_Provider_StaticMap::sanitize_style( 'otm' ) );
		$this->assertSame( 'esri/light-nolabels', Map_Provider_StaticMap::sanitize_style( 'gray-background' ) );
		$this->assertSame( 'esri/natgeo', Map_Provider_StaticMap::sanitize_style( 'national-geographic' ) );
		$this->assertSame( 'carto/streets', Map_Provider_StaticMap::sanitize_style( 'carto-voyager' ) );

		// Stamen's tiles are gone, so its basemaps get the server's default map.
		$this->assertSame( '', Map_Provider_StaticMap::sanitize_style( 'stamen-watercolor' ) );

		$this->assertSame( 'stadia/toner-lite', Map_Provider_StaticMap::sanitize_style( ' Stadia/toner-lite ' ) );
		$this->assertSame( 'nasa-gibs/bluemarble', Map_Provider_StaticMap::sanitize_style( 'nasa-gibs/bluemarble' ) );
		$this->assertSame( '', Map_Provider_StaticMap::sanitize_style( 'unknown' ) );
		$this->assertSame( '', Map_Provider_StaticMap::sanitize_style( 'a/b/c' ) );
		$this->assertSame( '', Map_Provider_StaticMap::sanitize_style( 'osm/<script>' ) );
		$this->assertSame( '', Map_Provider_StaticMap::sanitize_style( array( 'osm' ) ) );
		$this->assertSame( '', Map_Provider_StaticMap::sanitize_style( null ) );

		// A stored basemap ID is converted when the provider reads it.
		update_option( 'sloc_staticmap_url', 'https://maps.example.org/' );
		update_option( 'sloc_staticmap_style', 'otm' );
		$url = $this->parse( $this->provider( 'Map_Provider_StaticMap', array( 'api' => null ) )->get_the_static_map() );
		$this->assertSame( array( 'opentopomap', 'topo' ), array( $url['query']['provider'], $url['query']['style'] ) );
	}

	/**
	 * The style list comes from the map server's query API, and is stored.
	 */
	public function test_staticmap_styles_from_server() {
		$this->mock_http( array( 'maps.example.org/api/v1/providers' => array( 'body' => $this->fixture( 'map/staticmap-providers.json' ) ) ) );
		$map = $this->provider( 'Map_Provider_StaticMap', array( 'api' => 'https://maps.example.org/' ) );

		$this->assertSame(
			array(
				''                 => 'Default map of the map server',
				'osm/streets'      => 'OpenStreetMap',
				'esri/streets'     => 'Esri: World Street Map',
				'esri/topo'        => 'Esri: World Topographic Map',
				'opentopomap/topo' => 'OpenTopoMap',
			),
			$map->get_styles()
		);
		$map->get_styles();
		$this->assertCount( 1, $this->http_requests );

		// Asking for a fresh copy fetches it again.
		$map->get_api( 'providers', true );
		$this->assertCount( 2, $this->http_requests );
	}

	/**
	 * Without an answer from the map server, the styles every 1.0.0 server ships without keys are offered,
	 * and the failure is remembered so pages don't keep waiting on the server.
	 */
	public function test_staticmap_styles_fallback() {
		$this->mock_http( array( 'maps.example.org' => new WP_Error( 'http_request_failed', 'Connection refused' ) ) );
		$map    = $this->provider( 'Map_Provider_StaticMap', array( 'api' => 'https://maps.example.org/' ) );
		$styles = $map->get_styles();

		$this->assertSame( 'Default map of the map server', $styles[''] );
		$this->assertSame( 'Esri: National Geographic World Map', $styles['esri/natgeo'] );
		$this->assertSame( 'swisstopo: SWISSIMAGE', $styles['swisstopo/satellite'] );
		$this->assertArrayNotHasKey( 'carto/light', $styles );
		$this->assertArrayNotHasKey( 'osm', $styles );

		$map->get_styles();
		$this->assertCount( 1, $this->http_requests );
		$this->assertSame( 'Connection refused', $map->get_api( 'providers' )->get_error_message() );

		// Something other than a list of providers falls back too.
		delete_transient( 'sloc_staticmap_' . md5( 'https://other.example.org/api/v1/providers' ) );
		$this->mock_http( array( 'other.example.org' => array( 'body' => array( 'providers' => array( array( 'id' => 'x' ), 'y' ) ) ) ) );
		$map = $this->provider( 'Map_Provider_StaticMap', array( 'api' => 'https://other.example.org/' ) );
		$this->assertArrayHasKey( 'osm/streets', $map->get_styles() );
	}

	/**
	 * A server that answers, but not with the query API (one older than 1.0.0), counts as an error.
	 */
	public function test_staticmap_api_not_query_api() {
		$this->mock_http(
			array(
				'maps.example.org' => array(
					'body'    => '<html>Static Maps</html>',
					'headers' => array( 'content-type' => 'text/html' ),
				),
			)
		);
		$map = $this->provider( 'Map_Provider_StaticMap', array( 'api' => 'https://maps.example.org/' ) );
		$this->assertSame( 'not_json_response', $map->get_api( '', true )->get_error_code() );

		$this->mock_http( array( 'maps.example.org' => array( 'body' => array( 'name' => 'Something else' ) ) ) );
		$this->assertSame( 'sloc_staticmap_api', $map->get_api( '', true )->get_error_code() );

		$this->assertSame( 'missing_url', $this->provider( 'Map_Provider_StaticMap', array( 'api' => '' ) )->get_api( '' )->get_error_code() );
	}

	/**
	 * The style field lists the server's styles, and warns when the server doesn't answer its query API.
	 */
	public function test_staticmap_style_field() {
		update_option( 'sloc_staticmap_style', 'esri/topo' );
		$this->mock_http(
			array(
				'maps.example.org/api/v1/providers' => array( 'body' => $this->fixture( 'map/staticmap-providers.json' ) ),
				'maps.example.org/api/v1/'          => array( 'body' => $this->fixture( 'map/staticmap-api.json' ) ),
			)
		);
		$args = array(
			'label_for' => 'sloc_staticmap_style',
			'provider'  => $this->provider( 'Map_Provider_StaticMap', array( 'api' => 'https://maps.example.org/' ) ),
		);
		ob_start();
		Map_Provider_StaticMap::style_field( $args );
		$html = ob_get_clean();
		// How the selected attribute is written depends on the WordPress or ClassicPress version.
		$this->assertMatchesRegularExpression( '#<option value="esri/topo" selected(=["\']selected["\'])?>Esri: World Topographic Map</option>#', $html );
		$this->assertStringNotContainsString( 'description', $html );
		$this->assertCount( 2, $this->http_requests );

		$this->mock_http( array( 'maps.example.org' => array( 'code' => 404 ) ) );
		ob_start();
		Map_Provider_StaticMap::style_field( $args );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'needs version 1.0.0 or later', $html );
		$this->assertStringContainsString( 'HTTP status 404', $html );
		$this->assertStringContainsString( '<option value="esri/natgeo"', $html );

		// Credentials the server refuses get their own warning.
		$this->mock_http(
			array(
				'maps.example.org' => array(
					'code' => 403,
					'body' => 'The signature is not valid',
				),
			)
		);
		ob_start();
		Map_Provider_StaticMap::style_field( $args );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'refused the client ID, signing secret or client key', $html );
		$this->assertStringContainsString( 'The signature is not valid', $html );
		$this->assertStringNotContainsString( 'needs version 1.0.0', $html );

		// Without a URL there is nothing to warn about.
		$args['provider'] = $this->provider( 'Map_Provider_StaticMap', array( 'api' => '' ) );
		ob_start();
		Map_Provider_StaticMap::style_field( $args );
		$this->assertStringNotContainsString( 'description', ob_get_clean() );
	}

	/**
	 * HERE builds Map Image API v3 URLs: centered with a marker, or fitted to a route.
	 */
	public function test_here() {
		$map = $this->provider( 'Map_Provider_Here', array( 'style' => 'topo.day' ) );
		$this->assertArrayHasKey( 'explore.day', $map->get_styles() );

		$url = $this->parse( $map->get_the_static_map() );
		$this->assertSame( 'https://image.maps.hereapi.com/mia/v3/base/mc/center:39.9524,-75.1636;zoom=15/600x400/png8', $url['base'] );
		$this->assertSame(
			array(
				'apiKey'  => 'KEY',
				'style'   => 'topo.day',
				'overlay' => 'point:39.9524,-75.1636',
			),
			$url['query']
		);

		$archive = $map->get_archive_map( $this->locations );
		$this->assertStringStartsWith( 'https://image.maps.hereapi.com/mia/v3/base/mc/center:39.95065,-75.1568;zoom=15/600x400/png8?', $archive );
		$this->assertStringContainsString( 'overlay=point:39.9524,-75.1636|39.9489,-75.15', $archive );
		$this->assertStringContainsString( '&overlay=line:39.9524,-75.1636,39.9489,-75.15;width=4', $archive );

		// One location is a marker without a line.
		$single = $map->get_archive_map( array( $this->locations[0] ) );
		$this->assertStringNotContainsString( 'line:', $single );

		$this->assertSame( 'https://wego.here.com/?map=39.9524,-75.1636,15', $map->get_the_map_url() );

		$map = $this->provider( 'Map_Provider_Here', array( 'api' => '' ) );
		$this->assertSame( '', $map->get_the_static_map() );
		$this->assertSame( '', $map->get_archive_map( $this->locations ) );
	}

	/**
	 * HERE styles from the retired API, and a missing style, fall back to the default v3 style.
	 */
	public function test_here_style_fallback() {
		update_option( 'sloc_here_style', 'alps' );
		$url = $this->parse( $this->provider( 'Map_Provider_Here' )->get_the_static_map() );
		$this->assertSame( 'explore.day', $url['query']['style'] );

		update_option( 'sloc_here_style', 'lite.night' );
		$url = $this->parse( $this->provider( 'Map_Provider_Here' )->get_the_static_map() );
		$this->assertSame( 'lite.night', $url['query']['style'] );

		delete_option( 'sloc_here_style' );
		$url = $this->parse( $this->provider( 'Map_Provider_Here' )->get_the_static_map() );
		$this->assertSame( 'explore.day', $url['query']['style'] );
	}

	/**
	 * TomTom builds static image URLs with longitude first and has no archive map.
	 */
	public function test_tomtom() {
		$map = $this->provider( 'Map_Provider_TomTom', array( 'style' => 'night' ) );
		$this->assertArrayHasKey( 'main', $map->get_styles() );

		$url = $this->parse( $map->get_the_static_map() );
		$this->assertSame( 'https://api.tomtom.com/map/1/staticimage', $url['base'] );
		$this->assertSame(
			array(
				'key'      => 'KEY',
				'center'   => '-75.1636,39.9524',
				'zoom'     => '15',
				'width'    => '600',
				'height'   => '400',
				'language' => get_bloginfo( 'language' ),
				'layer'    => 'night',
			),
			$url['query']
		);
		$this->assertSame( '', $map->get_archive_map( $this->locations ) );

		$map = $this->provider( 'Map_Provider_TomTom', array( 'api' => '' ) );
		$this->assertSame( '', $map->get_the_static_map() );
	}

	/**
	 * Providers that need a key or URL.
	 *
	 * @return array Provider class and the message shown to administrators.
	 */
	public function data_missing_key() {
		return array(
			'Geoapify'   => array( 'Map_Provider_Geoapify', 'You have not set an API key for GeoApify' ),
			'LocationIQ' => array( 'Map_Provider_LocationIQ', 'You have not set an API key for Location IQ' ),
			'StaticMap'  => array( 'Map_Provider_StaticMap', 'You have not set a URL for your custom map endpoint' ),
		);
	}

	/**
	 * Without a key or URL, visitors get no map and administrators get the reason, instead of a fatal error.
	 *
	 * @dataProvider data_missing_key
	 *
	 * @param string $class   Provider class.
	 * @param string $message Message shown to administrators.
	 */
	public function test_static_map_html_missing_key( $class, $message ) {
		update_option( 'sloc_staticmap_url', '' );
		$map = $this->provider( $class, array( 'api' => '' ) );

		$this->assertSame( '', $map->get_the_map() );
		$this->assertSame( '', $map->get_the_static_map_html( true ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( $message, $map->get_the_map() );
		$this->assertSame( $message, $map->get_the_static_map_html( true ) );
	}

	/**
	 * Vendors whose map key is shared with server-side providers, with the URL parameter that carries the key.
	 *
	 * @return array
	 */
	public function data_map_api_key() {
		return array(
			'Google'     => array( 'Map_Provider_Google', 'google', 'key' ),
			'HERE'       => array( 'Map_Provider_Here', 'here', 'apiKey' ),
			'LocationIQ' => array( 'Map_Provider_LocationIQ', 'locationiq', 'key' ),
			'MapQuest'   => array( 'Map_Provider_Mapquest', 'mapquest', 'key' ),
		);
	}

	/**
	 * Maps use the vendor's map key when one is set, and the vendor's main key otherwise.
	 *
	 * @dataProvider data_map_api_key
	 *
	 * @param string $class  Provider class.
	 * @param string $vendor Vendor slug.
	 * @param string $param  URL parameter holding the key.
	 */
	public function test_map_api_key( $class, $vendor, $param ) {
		$location = array(
			'latitude'  => self::LAT,
			'longitude' => self::LNG,
		);
		update_option( 'sloc_' . $vendor . '_api', 'MAIN' );
		$map = new $class( $location );
		$this->assertSame( 'MAIN', $this->parse( $map->get_the_static_map() )['query'][ $param ] );

		update_option( 'sloc_' . $vendor . '_map_api', 'MAPONLY' );
		$map = new $class( $location );
		$this->assertSame( 'MAPONLY', $this->parse( $map->get_the_static_map() )['query'][ $param ] );
		$this->assertStringContainsString( 'MAPONLY', $map->get_archive_map( $this->locations ) );

		// An API key passed in still wins.
		$map = new $class( array_merge( $location, array( 'api' => 'PASSED' ) ) );
		$this->assertSame( 'PASSED', $this->parse( $map->get_the_static_map() )['query'][ $param ] );
	}

	/**
	 * The map key is only for maps: Google geocoding, elevation and venues keep using the main key.
	 */
	public function test_map_api_key_not_used_by_lookups() {
		update_option( 'sloc_google_api', 'MAIN' );
		update_option( 'sloc_google_map_api', 'MAPONLY' );
		$api = new ReflectionProperty( 'Sloc_Provider', 'api' );
		if ( PHP_VERSION_ID < 80100 ) {
			$api->setAccessible( true );
		}
		foreach ( array( 'Geo_Provider_Google', 'Elevation_Provider_Google', 'Venue_Provider_Google' ) as $class ) {
			$this->assertSame( 'MAIN', $api->getValue( new $class() ), $class );
		}
	}

	/**
	 * A vendor's map provider registers the map key setting and adds its field, with a description, to the API settings.
	 */
	public function test_map_api_setting() {
		global $wp_settings_fields;
		require_once ABSPATH . 'wp-admin/includes/template.php';

		Map_Provider_Here::init();
		$settings = get_registered_settings();
		$this->assertArrayHasKey( 'sloc_here_map_api', $settings );
		$this->assertSame( 'sloc_api', $settings['sloc_here_map_api']['group'] );
		$this->assertSame( 'MAPONLY', sanitize_option( 'sloc_here_map_api', ' MAPONLY ' ) );

		Map_Provider_Here::admin_init();
		$field = $wp_settings_fields['sloc_api']['sloc_api']['sloc_here_map_api'];
		$this->assertSame( 'HERE Map API Key', $field['title'] );
		$this->assertSame( 'password', $field['args']['type'] );

		update_option( 'sloc_here_map_api', 'MAPONLY' );
		ob_start();
		call_user_func( $field['callback'], $field['args'] );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'type="password"', $html );
		$this->assertStringContainsString( '<p class="description">Optional. Map images show this key to every visitor', $html );
		$this->assertStringContainsString( 'Leave empty to use the HERE API key.', $html );

		// Geocoding providers using the same vendor don't add a map key.
		Geo_Provider_Here::admin_init();
		unset( $wp_settings_fields['sloc_api']['sloc_api']['sloc_here_map_api'] );
		Geo_Provider_Here::admin_init();
		$this->assertArrayNotHasKey( 'sloc_here_map_api', $wp_settings_fields['sloc_api']['sloc_api'] );
	}
}

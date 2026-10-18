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
		$this->assertSame( '<a target="_blank" href="https://maps.example.com/?c=39.9524,-75.1636"><img class="sloc-map" src="https://maps.example.com/static?c=39.9524,-75.1636&#038;z=15&#038;s=600x400" width="600" height="400" loading="lazy" /></a>', $html );

		$html = $map->get_the_static_map_html( true );
		$this->assertStringContainsString( 'srcset="https://maps.example.com/static?c=39.9524,-75.1636&#038;z=15&#038;s=600x400 1x,https://maps.example.com/static?c=39.9524,-75.1636&#038;z=15&#038;s=600x400@2x 2x"', $html );
		$this->assertStringContainsString( 'width="600" height="400"', $html );
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

		$url = $this->parse( $map->get_archive_map( $this->locations ) );
		$this->assertSame( 'rect:-75.1636,39.9489,-75.15,39.9524', $url['query']['area'] );
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
			'https://maps.locationiq.com/v3/staticmap?key=KEY&format=png&size=600x400&markers=icon:large-red-cutout|39.9524,-75.1636&markers=icon:large-red-cutout|39.9489,-75.15&path=weight:2|color:blue|39.9524,-75.1636|39.9489,-75.15',
			$map->get_archive_map( $this->locations )
		);

		// One location is a marker without a path.
		$this->assertSame(
			'https://maps.locationiq.com/v3/staticmap?key=KEY&format=png&size=600x400&markers=icon:large-red-cutout|39.9524,-75.1636',
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
				'https://api.mapbox.com/styles/v1/mapbox/streets-v11/static/pin-s(-75.1636,39.9524),pin-s(-75.15,39.9489),path-5+f44-0.5(%s)/auto/600x400?access_token=KEY',
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
				'style' => 'osm',
			)
		);
		$this->assertArrayHasKey( 'carto-dark', $map->get_styles() );

		$url = $this->parse( $map->get_the_static_map() );
		$this->assertSame( 'https://maps.example.org/', $url['base'] );
		$this->assertSame(
			array(
				'width'       => '600',
				'height'      => '400',
				'zoom'        => '15',
				'basemap'     => 'osm',
				'latitude'    => '39.9524',
				'longitude'   => '-75.1636',
				'marker'      => 'lat:39.9524;lng:-75.1636;icon:small-red-cutout',
				'attribution' => 'osm',
			),
			$url['query']
		);

		$archive = $map->get_archive_map( $this->locations );
		$url     = $this->parse( $archive );
		$this->assertSame( '[-75.1636,39.9524],[-75.15,39.9489]', $url['query']['path'][0] );
		$this->assertStringEndsWith( '&marker[]=lat:39.9524;lng:-75.1636;icon:dot-small-red&marker[]=lat:39.9489;lng:-75.15;icon:dot-small-red', $archive );

		// Esri basemaps get Esri attribution.
		$map = $this->provider(
			'Map_Provider_StaticMap',
			array(
				'api'   => null,
				'style' => 'topo',
			)
		);
		$this->assertSame( 'esri', $this->parse( $map->get_the_static_map() )['query']['attribution'] );

		update_option( 'sloc_staticmap_url', '' );
		$map = $this->provider( 'Map_Provider_StaticMap', array( 'api' => null ) );
		$this->assertSame( 'missing_url', $map->get_the_static_map()->get_error_code() );
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
		$this->assertStringStartsWith( 'https://image.maps.hereapi.com/mia/v3/base/mc/overlay:padding=32/600x400/png8?', $archive );
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

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
		$this->assertStringContainsString( 'srcset="https://maps.example.com/static?c=39.9524,-75.1636&#038;z=15&#038;s=300x200 0.5x,https://maps.example.com/static?c=39.9524,-75.1636&#038;z=15&#038;s=600x400 1x,https://maps.example.com/static?c=39.9524,-75.1636&#038;z=15&#038;s=1200x800 2x,https://maps.example.com/static?c=39.9524,-75.1636&#038;z=15&#038;s=1800x1200 3x"', $html );
		$this->assertStringContainsString( 'width="600" height="400"', $html );
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
	 * Google builds Static Maps API URLs at double scale.
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
		$this->assertSame( '2', $url['query']['scale'] );
		$this->assertSame( 'KEY', $url['query']['key'] );

		$url = $this->parse( $map->get_archive_map( $this->locations ) );
		$this->assertSame( 'color:0xff0000ff|weight:5|enc:' . Sloc_Polyline::encode( $this->locations ), $url['query']['path'] );

		$this->assertStringContainsString( 'q=loc:39.9524,-75.1636', $map->get_the_map_url() );

		$map = $this->provider( 'Map_Provider_Google', array( 'api' => '' ) );
		$this->assertSame( '', $map->get_the_static_map() );
	}

	/**
	 * LocationIQ builds v3 static map URLs with a marker.
	 */
	public function test_locationiq() {
		$map = $this->provider( 'Map_Provider_LocationIQ' );
		$this->assertSame( array(), $map->get_styles() );

		$url = $this->parse( $map->get_the_static_map() );
		$this->assertSame( 'https://maps.locationiq.com/v3/staticmap', $url['base'] );
		$this->assertSame( 'KEY', $url['query']['key'] );
		$this->assertSame( '600x400', $url['query']['size'] );
		$this->assertSame( 'size:small|color:red|39.9524,-75.1636', $url['query']['markers'] );

		$url = $this->parse( $map->get_archive_map( $this->locations ) );
		$this->assertSame( 'icon:small-red-blank|size:small|color:red|39.9524,-75.1636|39.9489,-75.15', $url['query']['markers'] );

		$map = $this->provider( 'Map_Provider_LocationIQ', array( 'api' => '' ) );
		$this->assertSame( 'missing_api_key', $map->get_the_static_map()->get_error_code() );
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
	 * MapQuest builds static map v5 URLs.
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

		$map = $this->provider( 'Map_Provider_Mapquest', array( 'api' => '' ) );
		$this->assertSame( '', $map->get_the_static_map() );
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
}

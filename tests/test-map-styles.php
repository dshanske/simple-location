<?php
/**
 * Tests for map style families and dark mode maps (#218).
 *
 * @package Simple_Location
 */

/**
 * Map style tests.
 */
class MapStylesTest extends WP_UnitTestCase {

	/**
	 * Philadelphia City Hall.
	 */
	const LAT = 39.9524;
	const LNG = -75.1636;

	/**
	 * Known map settings, with no provider style chosen and maps not served from the site.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_width', 600 );
		update_option( 'sloc_aspect', 1.5 );
		update_option( 'sloc_zoom', 15 );
		update_option( 'sloc_map_cache', 0 );
		delete_option( 'sloc_map_style' );
		delete_option( 'sloc_map_dark_mode' );
		foreach ( array( 'google', 'mapbox', 'here', 'geoapify', 'mapquest', 'tomtom', 'stadia', 'staticmap' ) as $slug ) {
			delete_option( 'sloc_' . $slug . '_style' );
		}
	}

	/**
	 * Creates a provider at City Hall with a key, without a style argument, so it reads the settings.
	 *
	 * @param string $class Provider class.
	 * @return Map_Provider
	 */
	protected function provider( $class ) {
		return new $class(
			array(
				'api'       => 'KEY',
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			)
		);
	}

	/**
	 * Returns a provider's style.
	 *
	 * @param Map_Provider $map Provider.
	 * @return string Style.
	 */
	protected function style( $map ) {
		$style = new ReflectionProperty( 'Map_Provider', 'style' );
		if ( PHP_VERSION_ID < 80100 ) {
			$style->setAccessible( true );
		}
		return $style->getValue( $map );
	}

	/**
	 * Each provider's style for each family.
	 *
	 * @return array
	 */
	public function data_family_styles() {
		return array(
			'Google'     => array( 'Map_Provider_Google', 'google', array( 'roadmap', 'light', 'dark', 'hybrid', 'terrain' ), 'satellite' ),
			'Mapbox'     => array( 'Map_Provider_Mapbox', 'mapbox', array( 'streets-v11', 'light-v10', 'dark-v10', 'satellite-streets-v11', 'outdoors-v11' ), 'navigation-day-v1' ),
			'HERE'       => array( 'Map_Provider_Here', 'here', array( 'explore.day', 'lite.day', 'explore.night', 'explore.satellite.day', 'topo.day' ), 'logistics.day' ),
			'Geoapify'   => array( 'Map_Provider_Geoapify', 'geoapify', array( 'osm-carto', 'positron', 'dark-matter', 'osm-carto', 'osm-carto' ), 'toner' ),
			'MapQuest'   => array( 'Map_Provider_Mapquest', 'mapquest', array( 'map', 'light', 'dark', 'hyb', 'map' ), 'sat' ),
			'TomTom'     => array( 'Map_Provider_TomTom', 'tomtom', array( 'main', 'main', 'night', 'main', 'main' ), 'main' ),
			'Stadia'     => array( 'Map_Provider_Stadia', 'stadia', array( 'alidade_smooth', 'stamen_toner_lite', 'alidade_smooth_dark', 'alidade_satellite', 'outdoors' ), 'stamen_watercolor' ),
			'Static map' => array( 'Map_Provider_StaticMap', 'staticmap', array( '', 'esri/light', 'esri/dark', 'esri/hybrid', 'esri/terrain' ), 'esri/streets' ),
		);
	}

	/**
	 * Without its own style setting, each provider draws its style for the Map Style family, and the standard style for a
	 * family it doesn't have. Its own style setting is used instead when set.
	 *
	 * @dataProvider data_family_styles
	 *
	 * @param string   $class  Provider class.
	 * @param string   $slug   Provider slug.
	 * @param string[] $styles Styles for standard, light, dark, satellite and terrain.
	 * @param string   $own    A style of the provider's own, for its style setting.
	 */
	public function test_family_styles( $class, $slug, $styles, $own ) {
		$this->assertSame( $styles[0], $this->style( $this->provider( $class ) ), 'No Map Style setting is standard.' );
		foreach ( Map_Provider::STYLE_FAMILIES as $index => $family ) {
			update_option( 'sloc_map_style', $family );
			$this->assertSame( $styles[ $index ], $this->style( $this->provider( $class ) ), $family );
		}

		update_option( 'sloc_map_style', 'dark' );
		update_option( 'sloc_' . $slug . '_style', $own );
		$this->assertSame( $own, $this->style( $this->provider( $class ) ), 'The provider\'s own style wins.' );
	}

	/**
	 * Unknown families are standard, and providers without styles have no family styles.
	 */
	public function test_family_sanitize() {
		$this->assertSame( 'dark', Map_Provider::sanitize_style_family( 'dark' ) );
		$this->assertSame( 'standard', Map_Provider::sanitize_style_family( 'neon' ) );
		$this->assertSame( 'standard', Map_Provider::sanitize_style_family( null ) );
		$this->assertSame( array(), $this->provider( 'Map_Provider_LocationIQ' )->get_family_styles() );
		$this->assertSame( '', $this->provider( 'Map_Provider_LocationIQ' )->get_family_style( 'dark' ) );
	}

	/**
	 * Google's light and dark styles are its roadmap with styling rules, each its own parameter, before the signature.
	 */
	public function test_google_styled_maps() {
		update_option( 'sloc_map_style', 'dark' );
		$url = $this->provider( 'Map_Provider_Google' )->get_the_static_map();
		$this->assertStringContainsString( 'maptype=roadmap', $url );
		$this->assertSame( count( Map_Provider_Google::get_style_rules()['dark'] ), substr_count( $url, '&style=' ) );
		$this->assertStringContainsString( '&style=element%3Ageometry%7Ccolor%3A0x242f3e', $url );

		$map = new Map_Provider_Google(
			array(
				'api'       => 'KEY',
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
				'style'     => 'light',
				'secret'    => 'vNIXE0xscrmjlyV-12Nj_BvUPaw=',
			)
		);
		$url = $map->get_the_static_map();
		$this->assertMatchesRegularExpression( '#&style=[^&]+&signature=[A-Za-z0-9_-]+=*$#', $url );
		$this->assertSame( $url, Map_Provider_Google::sign_url( substr( $url, 0, strpos( $url, '&signature=' ) ), 'vNIXE0xscrmjlyV-12Nj_BvUPaw=' ) );
		$this->assertStringContainsString( 'maptype=roadmap', $map->get_archive_map( array( array( self::LAT, self::LNG ), array( 39.9489, -75.15 ) ) ) );

		update_option( 'sloc_map_style', 'satellite' );
		$this->assertStringNotContainsString( '&style=', $this->provider( 'Map_Provider_Google' )->get_the_static_map() );
	}

	/**
	 * With Dark Mode Maps on, maps add the provider's dark style for visitors in dark mode.
	 */
	public function test_dark_mode_single_map() {
		$html = $this->provider( 'Map_Provider_Mapbox' )->get_the_static_map_html( true );
		$this->assertStringNotContainsString( '<picture>', $html, 'Off by default.' );

		update_option( 'sloc_map_dark_mode', 1 );
		$html = $this->provider( 'Map_Provider_Mapbox' )->get_the_static_map_html( true );
		$this->assertMatchesRegularExpression( '#^<a target="_blank" href="[^"]+"><picture><source media="\(prefers-color-scheme: dark\)" srcset="[^"]*/dark-v10/static/[^"]* 1x,[^"]*/dark-v10/static/[^"]*@2x[^"]* 2x" /><img class="sloc-map" src="[^"]*/streets-v11/static/#', $html );
		$this->assertStringEndsWith( '</picture></a>', $html );

		// Nothing to add when the map is already dark, or the provider has no dark style.
		update_option( 'sloc_map_style', 'dark' );
		$this->assertStringNotContainsString( '<picture>', $this->provider( 'Map_Provider_Mapbox' )->get_the_static_map_html( true ) );
		$this->assertNull( $this->provider( 'Map_Provider_LocationIQ' )->get_dark_variant() );

		// A provider without high-density maps gives a single dark image.
		update_option( 'sloc_map_style', 'standard' );
		$html = $this->provider( 'Map_Provider_TomTom' )->get_the_static_map_html( true );
		$this->assertMatchesRegularExpression( '#<source media="\(prefers-color-scheme: dark\)" srcset="[^" ]+layer=night[^" ]*" />#', $html );

		// The dark variant is a copy: the provider still draws its own style.
		$map = $this->provider( 'Map_Provider_Mapbox' );
		$map->get_the_static_map_html( true );
		$this->assertStringContainsString( '/streets-v11/static/', $map->get_the_static_map() );
	}

	/**
	 * Archive maps add the dark style too, keeping the marker links of the same view.
	 */
	public function test_dark_mode_archive_map() {
		update_option( 'sloc_map_dark_mode', 1 );
		$posts = self::factory()->post->create_many( 2 );
		$html  = $this->provider( 'Map_Provider_Here' )->get_archive_map_html( array( array( self::LAT, self::LNG ), array( 39.9489, -75.15 ) ), $posts );
		$this->assertStringStartsWith( '<div class="sloc-archive-map"><picture><source media="(prefers-color-scheme: dark)" srcset="', $html );
		$this->assertStringContainsString( 'explore.night', $html );
		$this->assertSame( 2, substr_count( $html, 'sloc-archive-map-marker' ) );
	}

	/**
	 * Maps served from the site store the dark style as its own image.
	 */
	public function test_dark_mode_cached() {
		update_option( 'sloc_map_cache', 1 );
		update_option( 'sloc_map_dark_mode', 1 );
		$map  = $this->provider( 'Map_Provider_Geoapify' );
		$html = $map->get_the_static_map_html( true );
		$this->assertStringNotContainsString( 'geoapify.com', $html );
		preg_match( '#<source [^>]*srcset="([^" ]+)#', $html, $dark );
		$this->assertStringContainsString( 'style=dark-matter', html_entity_decode( $dark[1] ) );
		$this->assertNotSame( Sloc_Map_Cache::get_url( $map ), html_entity_decode( $dark[1] ) );
	}

	/**
	 * The settings are registered, and each provider's style list starts with the Map Style setting.
	 */
	public function test_settings() {
		global $wp_settings_fields;
		require_once ABSPATH . 'wp-admin/includes/template.php';

		Loc_Config::register_map_settings();
		$this->assertSame( 'standard', get_option( 'sloc_map_style' ) );
		$this->assertSame( 'standard', sanitize_option( 'sloc_map_style', 'neon' ) );
		$this->assertFalse( get_option( 'sloc_map_dark_mode' ) );

		Loc_Config::admin_init();
		$fields = $wp_settings_fields['simloc']['sloc_map'];
		$this->assertSame( Map_Provider::STYLE_FAMILIES, array_keys( $fields['sloc_map_style']['args']['list'] ) );
		$this->assertArrayHasKey( 'sloc_map_dark_mode', $fields );

		ob_start();
		Loc_Config::style_callback(
			array(
				'label_for' => 'sloc_here_style',
				'provider'  => $this->provider( 'Map_Provider_Here' ),
			)
		);
		$html = ob_get_clean();
		$this->assertMatchesRegularExpression( '#<select name="sloc_here_style"><option value=""\s+selected(=.selected.)?>Use the Map Style setting</option>#', $html );
	}

	/**
	 * The plugin's allowed HTML keeps the dark mode source.
	 */
	public function test_kses_keeps_source() {
		$html = '<picture><source media="(prefers-color-scheme: dark)" srcset="https://example.com/dark.png 1x" /><img src="https://example.com/map.png" alt="Map" /></picture>';
		$this->assertStringContainsString( '<source media="(prefers-color-scheme: dark)" srcset="https://example.com/dark.png 1x" />', wp_kses( $html, Simple_Location_Plugin::kses_clean() ) );
	}
}

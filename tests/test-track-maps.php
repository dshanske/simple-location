<?php
/**
 * Tests for track maps: tracks as encoded polylines, drawn by every provider and by [sloc_map track="…"] (#668).
 *
 * @package Simple_Location
 */

/**
 * Track map tests.
 */
class TrackMapsTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

	/**
	 * A walk from City Hall to the Art Museum along the Parkway.
	 *
	 * @var array
	 */
	protected $track = array(
		array( 39.9524, -75.1636 ),
		array( 39.9541, -75.1680 ),
		array( 39.9567, -75.1712 ),
		array( 39.9602, -75.1751 ),
		array( 39.9656, -75.1810 ),
	);

	/**
	 * Known map settings, without requests or maps served from the site.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_width', 600 );
		update_option( 'sloc_aspect', 1.5 );
		update_option( 'sloc_zoom', 15 );
		update_option( 'sloc_map_cache', 0 );
		update_option( 'sloc_staticmap_url', 'https://maps.example.org/' );
		$this->mock_http( array() );
	}

	/**
	 * Creates a provider with a key and a style.
	 *
	 * @param string $class Provider class.
	 * @return Map_Provider
	 */
	protected function provider( $class ) {
		return new $class(
			array(
				'api'   => 'Map_Provider_StaticMap' === $class ? null : 'KEY',
				'style' => 'Map_Provider_StaticMap' === $class ? '' : 'streets-v11',
			)
		);
	}

	/**
	 * Providers, with a pattern matching each marker in the archive map URL and text found only in its line.
	 *
	 * @return array
	 */
	public function data_providers() {
		return array(
			'Google'     => array( 'Map_Provider_Google', '#(?:markers=[^&]*?|%7C)39\.\d+%2C-75\.\d+#', 'path=' ),
			'Mapbox'     => array( 'Map_Provider_Mapbox', '#pin-s\+#', 'path-5+f44' ),
			'HERE'       => array( 'Map_Provider_Here', '#(?:point:|\|)39\.\d+,-75\.\d+#', 'overlay=line:' ),
			'LocationIQ' => array( 'Map_Provider_LocationIQ', '#&markers=#', '&path=' ),
			'MapQuest'   => array( 'Map_Provider_Mapquest', '#(?:locations=|\|\|)39\.\d+,-75\.\d+#', 'shape=cmp' ),
			'Geoapify'   => array( 'Map_Provider_Geoapify', '#lonlat:-75\.\d+,39\.\d+;size:small#', 'geometry=polyline:' ),
			'Stadia'     => array( 'Map_Provider_Stadia', '#&m=#', 'line_precision=5' ),
			'Static map' => array( 'Map_Provider_StaticMap', '#marker%5B%5D=#', 'polyline=enc' ),
		);
	}

	/**
	 * A track has a line through its points, with markers at its start and end, or none.
	 *
	 * @dataProvider data_providers
	 *
	 * @param string $class  Provider class.
	 * @param string $marker Pattern matching each marker.
	 * @param string $line   Text found only in the line.
	 */
	public function test_track_markers( $class, $marker, $line ) {
		$map = $this->provider( $class );
		$this->assertFalse( $map->is_track() );
		$archive = $map->get_archive_map( $this->track );
		$this->assertSame( count( $this->track ), preg_match_all( $marker, $archive ), 'An archive map marks every point.' );

		$map->set_track();
		$this->assertTrue( $map->is_track() );
		$url = $map->get_archive_map( $this->track );
		$this->assertSame( 2, preg_match_all( $marker, $url ), 'A track marks its start and end.' );
		$this->assertStringContainsString( $line, $url );

		$map->set_track( 'none' );
		$url = $map->get_archive_map( $this->track );
		$this->assertSame( 0, preg_match_all( $marker, $url ), 'No markers.' );
		$this->assertStringContainsString( $line, $url );
	}

	/**
	 * Stadia draws the line from an encoded polyline with five decimal places, and Geoapify from longitude, latitude pairs.
	 */
	public function test_line_formats() {
		$stadia = $this->provider( 'Map_Provider_Stadia' );
		$stadia->set_track();
		$url = $stadia->get_archive_map( $this->track );
		$this->assertStringContainsString( '?l=' . rawurlencode( Sloc_Polyline::encode( $this->track ) . ',d32f2f,3' ), $url );
		$this->assertStringContainsString( '&line_precision=5', $url );

		$geoapify = $this->provider( 'Map_Provider_Geoapify' );
		$geoapify->set_track();
		$this->assertStringContainsString( 'geometry=polyline:-75.1636,39.9524,-75.168,39.9541,', $geoapify->get_archive_map( $this->track ) );
	}

	/**
	 * Encoded tracks decode into points, skipping anything that isn't one.
	 */
	public function test_decode() {
		$this->assertSame( $this->track, Sloc_Track::decode( Sloc_Track::encode( $this->track ) ) );
		$this->assertSame( array( array( 38.5, -120.2 ), array( 40.7, -120.95 ), array( 43.252, -126.453 ) ), Sloc_Track::decode( '_p~iF~ps|U_ulLnnqC_mqNvxq`@' ) );
		$this->assertSame( array(), Sloc_Track::decode( 'not a polyline!' ) );
		$this->assertSame( array(), Sloc_Track::decode( array( 'x' ) ) );
		$this->assertSame( array(), Sloc_Track::decode( '' ) );
	}

	/**
	 * Long tracks are simplified to the limit, keeping their ends and corners.
	 */
	public function test_simplify() {
		// A straight line of 500 points, then a turn, and another straight line.
		$points = array();
		for ( $i = 0; $i < 500; $i++ ) {
			$points[] = array( 39.9 + $i * 0.0001, -75.2 );
		}
		for ( $i = 1; $i <= 500; $i++ ) {
			$points[] = array( 39.9499, -75.2 + $i * 0.0001 );
		}
		$simple = Sloc_Track::simplify( $points );
		$this->assertLessThanOrEqual( Sloc_Track::MAX_POINTS, count( $simple ) );
		$this->assertSame( $points[0], $simple[0] );
		$this->assertSame( end( $points ), end( $simple ) );
		$this->assertContains( $points[499], $simple, 'The corner is kept.' );
		$this->assertLessThan( 10, count( $simple ), 'Straight lines need few points.' );

		$this->assertSame( $this->track, Sloc_Track::simplify( $this->track ), 'Short tracks are unchanged.' );
		$this->assertCount( 2, Sloc_Track::simplify( $points, 1 ), 'At least the ends are kept.' );
	}

	/**
	 * [sloc_map track="…"] draws the track without marker links, with the markers attribute.
	 */
	public function test_shortcode() {
		update_option( 'sloc_map_provider', 'google' );
		$registered = Loc_Config::map_provider( 'google' );
		Loc_Config::register_provider( new Map_Provider_Google( array( 'api' => 'KEY' ) ) );

		$encoded = Sloc_Track::encode( $this->track );
		$html    = do_shortcode( '[sloc_map track="' . $encoded . '"]' );
		$this->assertStringStartsWith( '<div class="sloc-map-shortcode"><img class="archive-map sloc-map" src="https://maps.googleapis.com/', $html );
		$this->assertStringContainsString( 'path=', $html );
		$this->assertStringNotContainsString( 'sloc-archive-map-marker', $html );
		$this->assertCount( 2, $this->google_markers( $html ) );

		$html = do_shortcode( '[sloc_map track="' . $encoded . '" markers="none"]' );
		$this->assertCount( 0, $this->google_markers( $html ) );

		// A track takes precedence over points, and a track that isn't one, or a single point, draws nothing.
		$this->assertStringContainsString( 'path=', do_shortcode( '[sloc_map track="' . $encoded . '" points="40.7128,-74.006"]' ) );
		$this->assertSame( '', do_shortcode( '[sloc_map track="not a polyline!"]' ) );
		$this->assertSame( '', do_shortcode( '[sloc_map track="' . Sloc_Track::encode( array( $this->track[0] ) ) . '"]' ) );

		if ( $registered ) {
			Loc_Config::register_provider( $registered );
		}
	}

	/**
	 * Returns the marked points of the first Google map image in some HTML.
	 *
	 * @param string $html HTML.
	 * @return string[] Points.
	 */
	protected function google_markers( $html ) {
		preg_match( '/ src="([^"]+)"/', $html, $src );
		parse_str( (string) wp_parse_url( html_entity_decode( $src[1] ), PHP_URL_QUERY ), $query );
		if ( empty( $query['markers'] ) ) {
			return array();
		}
		return preg_grep( '/^-?\d+\.?\d*,-?\d+\.?\d*$/', explode( '|', $query['markers'] ) );
	}

	/**
	 * A track served from the site is signed with its markers, and the stored image is drawn as a track again.
	 */
	public function test_cached_track() {
		update_option( 'sloc_map_cache', 1 );
		update_option( 'sloc_geoapify_api', 'SECRETKEY' );
		$map = new Map_Provider_Geoapify( array( 'style' => 'osm-bright' ) );
		$this->assertArrayNotHasKey( 'track', $map->get_cache_args( $this->track ) );
		$plain = Sloc_Map_Cache::sign( $map->get_cache_args( $this->track ) );

		$map->set_track( 'none' );
		$args = $map->get_cache_args( $this->track );
		$this->assertSame( 'none', $args['track'] );
		$this->assertNotSame( $plain, Sloc_Map_Cache::sign( $args ) );
		$this->assertArrayNotHasKey( 'track', $map->get_cache_args(), 'A single map is never a track.' );

		$rebuild = new ReflectionMethod( 'Sloc_Map_Cache', 'provider' );
		if ( PHP_VERSION_ID < 80100 ) {
			$rebuild->setAccessible( true );
		}
		Loc_Config::register_provider( new Map_Provider_Geoapify() );
		$rebuilt = $rebuild->invoke( null, $args );
		$this->assertTrue( $rebuilt->is_track() );
		$url = $rebuilt->get_archive_map( array_chunk( Sloc_Polyline::decode( $args['points'] ), 2 ) );
		$this->assertStringContainsString( 'geometry=polyline:', $url );
		$this->assertStringNotContainsString( 'marker=', $url );
	}
}

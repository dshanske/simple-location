<?php
/**
 * Tests for the offline country, place and timezone lookup: Sloc_Geo_Grid, Sloc_Offline_Geo, Sloc_Geo_Data_Builder
 * and Geo_Provider_Offline.
 *
 * @package Simple_Location
 */

require_once dirname( __DIR__ ) . '/includes/class-sloc-geo-data-builder.php';

/**
 * Offline geo tests.
 */
class OfflineGeoTest extends WP_UnitTestCase {

	/**
	 * Temporary directory for built files.
	 *
	 * @var string
	 */
	protected $dir = '';

	/**
	 * Fresh lookups for every test.
	 */
	public function set_up() {
		parent::set_up();
		Sloc_Offline_Geo::reset();
		update_option( 'sloc_country', '' );
	}

	/**
	 * Removes built files.
	 */
	public function tear_down() {
		if ( $this->dir && is_dir( $this->dir ) ) {
			foreach ( glob( $this->dir . '/*' ) as $file ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
			rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
		Sloc_Offline_Geo::reset();
		parent::tear_down();
	}

	/**
	 * Returns a new temporary directory.
	 *
	 * @return string Path, without a trailing slash.
	 */
	protected function temp_dir() {
		$this->dir = untrailingslashit( get_temp_dir() ) . '/sloc-geo-' . wp_generate_password( 8, false );
		mkdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		return $this->dir;
	}

	/**
	 * A square polygon feature.
	 *
	 * @param string  $code  Area code.
	 * @param float   $west  West edge.
	 * @param float   $south South edge.
	 * @param float   $east  East edge.
	 * @param float   $north North edge.
	 * @param array[] $holes Optional rings to cut out.
	 * @return array Feature.
	 */
	protected function square( $code, $west, $south, $east, $north, $holes = array() ) {
		return array(
			'type'       => 'Feature',
			'properties' => array( 'tzid' => $code ),
			'geometry'   => array(
				'type'        => 'Polygon',
				'coordinates' => array_merge( array( array( array( $west, $south ), array( $east, $south ), array( $east, $north ), array( $west, $north ), array( $west, $south ) ) ), $holes ),
			),
		);
	}

	/**
	 * Builds a small grid: area A from 10 to 12°E and B from 12 to 13.25°E, both 50 to 51°N, A with a hole.
	 *
	 * @return Sloc_Geo_Grid Grid.
	 */
	protected function small_grid() {
		$hole    = array( array( 10.6, 50.6 ), array( 10.6, 50.65 ), array( 10.65, 50.65 ), array( 10.65, 50.6 ), array( 10.6, 50.6 ) );
		$geojson = array(
			'type'     => 'FeatureCollection',
			'features' => array(
				$this->square( 'A', 10, 50, 12.1, 51, array( $hole ) ),
				$this->square( 'B', 12.1, 50, 13.25, 51 ),
			),
		);
		$counts  = Sloc_Geo_Data_Builder::build_grid( $geojson, array( 'Sloc_Geo_Data_Builder', 'timezone_label' ), $this->temp_dir() . '/small', 0 );
		$this->assertSame( 2, $counts['names'] );
		$this->assertSame( Sloc_Geo_Grid::WIDTH * Sloc_Geo_Grid::HEIGHT * 4, $counts['idx'] );
		$this->assertGreaterThan( 0, $counts['uniform'] );
		$this->assertGreaterThan( 0, $counts['border'] );
		return new Sloc_Geo_Grid( $this->dir . '/small' );
	}

	/**
	 * A built grid finds areas in whole cells, on both sides of a border inside a cell, and not in a hole or outside.
	 */
	public function test_grid_lookup() {
		$grid = $this->small_grid();
		$this->assertTrue( $grid->exists() );
		$this->assertSame( 'A', $grid->lookup( 50.25, 10.25 )['code'], 'A cell inside A.' );
		$this->assertSame( 'A', $grid->lookup( 50.25, 12.05 )['code'], 'West of the border inside the cell.' );
		$this->assertSame( 'B', $grid->lookup( 50.25, 12.15 )['code'], 'East of the border inside the cell.' );
		$this->assertSame( 'B', $grid->lookup( 50.75, 13.1 )['code'], 'Part of a cell, at the east edge.' );
		$this->assertNull( $grid->lookup( 50.62, 10.62 ), 'In the hole.' );
		$this->assertSame( 'A', $grid->lookup( 50.55, 10.62 )['code'], 'Beside the hole, in the same cell.' );
		$this->assertNull( $grid->lookup( 50.75, 13.4 ), 'East of B, in its cell.' );
		$this->assertNull( $grid->lookup( 40, 10.25 ), 'Far away.' );
		$this->assertNull( $grid->lookup( 'north', 10 ) );
	}

	/**
	 * Without its files, a grid has no answers.
	 */
	public function test_grid_missing() {
		$grid = new Sloc_Geo_Grid( __DIR__ . '/data/missing-geo-data/countries' );
		$this->assertFalse( $grid->exists() );
		$this->assertNull( $grid->lookup( 39.9524, -75.1636 ) );
	}

	/**
	 * Ray casting finds points inside a ring, including a concave one.
	 */
	public function test_in_ring() {
		// An L shape: the square from 0 to 100 without its north-east quarter.
		$ring = array( 0, 0, 100, 0, 100, 50, 50, 50, 50, 100, 0, 100 );
		$this->assertTrue( Sloc_Geo_Grid::in_ring( $ring, 25, 25 ) );
		$this->assertTrue( Sloc_Geo_Grid::in_ring( $ring, 25, 75 ) );
		$this->assertTrue( Sloc_Geo_Grid::in_ring( $ring, 75, 25 ) );
		$this->assertFalse( Sloc_Geo_Grid::in_ring( $ring, 75, 75 ) );
		$this->assertFalse( Sloc_Geo_Grid::in_ring( $ring, 150, 25 ) );
	}

	/**
	 * Simplifying drops points close to the line, clipping keeps one side of a line, and area ignores direction.
	 */
	public function test_builder_geometry() {
		$line = array( array( 0, 0 ), array( 1, 0.001 ), array( 2, 0 ), array( 3, 0.5 ), array( 4, 0 ), array( 5, 0 ) );
		$this->assertSame( array( array( 0, 0 ), array( 2, 0 ), array( 3, 0.5 ), array( 4, 0 ), array( 5, 0 ) ), Sloc_Geo_Data_Builder::simplify( $line, 0.01 ) );
		$this->assertSame( array_slice( $line, 0, 3 ), Sloc_Geo_Data_Builder::simplify( array_slice( $line, 0, 3 ), 1 ), 'Short rings are kept.' );

		$square = array( array( 0, 0 ), array( 2, 0 ), array( 2, 2 ), array( 0, 2 ) );
		$west   = Sloc_Geo_Data_Builder::clip( $square, 0, 1, true );
		$this->assertEqualsWithDelta( 2.0, Sloc_Geo_Data_Builder::area( $west ), 1e-9 );
		$this->assertLessThanOrEqual( 1, max( array_column( $west, 0 ) ) );
		$north = Sloc_Geo_Data_Builder::clip( $square, 1, 1.5, false );
		$this->assertEqualsWithDelta( 1.0, Sloc_Geo_Data_Builder::area( $north ), 1e-9 );
		$this->assertSame( array(), Sloc_Geo_Data_Builder::clip( $square, 0, 5, false ), 'Nothing on the far side.' );
		$this->assertEqualsWithDelta( 4.0, Sloc_Geo_Data_Builder::area( array_reverse( $square ) ), 1e-9 );
	}

	/**
	 * Countries get their ISO code, and places without one an empty code; zones get their name.
	 */
	public function test_labels() {
		$this->assertSame(
			array(
				'code' => 'FR',
				'name' => 'France',
			),
			Sloc_Geo_Data_Builder::country_label(
				array(
					'ISO_A2_EH' => 'FR',
					'ISO_A2'    => '-99',
					'NAME'      => 'France',
				)
			)
		);
		$this->assertSame( 'US', Sloc_Geo_Data_Builder::country_label( array( 'ISO_A2' => 'us' ) )['code'] );
		$this->assertSame(
			'',
			Sloc_Geo_Data_Builder::country_label(
				array(
					'ISO_A2_EH' => '-99',
					'ISO_A2'    => '-99',
					'NAME'      => 'N. Cyprus',
				)
			)['code']
		);
		$this->assertSame( array( 'code' => 'Europe/Paris' ), Sloc_Geo_Data_Builder::timezone_label( array( 'tzid' => 'Europe/Paris' ) ) );
	}

	/**
	 * The places are sorted by cell with an index of offsets, and the regions are listed; the lookups read them.
	 */
	public function test_build_places() {
		$dir    = $this->temp_dir();
		$row    = function ( $name, $lat, $lng, $feature, $country, $admin1, $population ) {
			return implode( "\t", array( 1, $name, $name, '', $lat, $lng, 'P', $feature, $country, '', $admin1, '', '', '', $population, '', '', '', 'UTC', '2026-01-01' ) );
		};
		$cities = array(
			$row( 'Smalltown', '40.05', '-75.05', 'PPL', 'US', 'PA', 1500 ),
			$row( 'Bigcity', '40.2', '-75.2', 'PPLA', 'US', 'PA', 900000 ),
			$row( 'Old Quarter', '40.21', '-75.21', 'PPLX', 'US', 'PA', 5000 ),
			$row( 'Faraway', '-33.9', '151.2', 'PPL', 'AU', '02', 2000 ),
			'not a row',
		);
		file_put_contents( $dir . '/cities.txt', implode( "\n", $cities ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $dir . '/admin1.txt', "US.PA\tPennsylvania\tPennsylvania\t6254927\nAU.02\tNew South Wales\tNew South Wales\t2155400\nbad line\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$counts = Sloc_Geo_Data_Builder::build_places( $dir . '/cities.txt', $dir . '/admin1.txt', $dir );
		$this->assertSame(
			array(
				'places'  => 3,
				'regions' => 2,
			),
			$counts
		);
		$lines = file( $dir . '/places.csv', FILE_IGNORE_NEW_LINES );
		$this->assertSame( 'Faraway,-33.9,151.2,AU,02,2000', $lines[0], 'The southern cell comes first.' );
		$this->assertSame( 'Bigcity,40.2,-75.2,US,PA,900000', $lines[1], 'Within a cell, the biggest place comes first.' );
		$this->assertCount( 3, $lines, 'Sections of places are left out.' );
		$this->assertSame( ( Sloc_Offline_Geo::PLACE_COLUMNS * Sloc_Offline_Geo::PLACE_ROWS + 1 ) * 4, filesize( $dir . '/places.cells' ) );
		$this->assertSame( array( 'AU,02,"New South Wales"', 'US,PA,Pennsylvania' ), file( $dir . '/regions.csv', FILE_IGNORE_NEW_LINES ) );

		add_filter( 'sloc_offline_geo_dir', fn() => $dir );
		$place = Sloc_Offline_Geo::place( 40.19, -75.19 );
		$this->assertSame( 'Bigcity', $place['name'] );
		$this->assertSame( 'Pennsylvania', $place['region'] );
		$this->assertSame( 'Smalltown', Sloc_Offline_Geo::place( 40.05, -75.05 )['name'], 'A place you are in beats a bigger one 20 km away.' );
		$this->assertSame( 'Faraway', Sloc_Offline_Geo::nearest_place( -33.95, 151.25, 15000 )['name'] );
		$this->assertNull( Sloc_Offline_Geo::place( 10, 10 ) );
		$this->assertSame( 'New South Wales', Sloc_Offline_Geo::region_name( 'au', '02' ) );
		$this->assertSame( '', Sloc_Offline_Geo::region_name( 'US', 'ZZ' ) );
	}

	/**
	 * Countries come from borders, right on both sides of a border; at sea from a place within 15 km.
	 */
	public function test_country() {
		$this->assertTrue( Sloc_Offline_Geo::has_countries() );
		$this->assertSame( 'US', Sloc_Offline_Geo::country( 39.9524, -75.1636 ) );
		$this->assertSame( 'US', Sloc_Offline_Geo::country( 42.3314, -83.0458 ), 'Detroit.' );
		$this->assertSame( 'CA', Sloc_Offline_Geo::country( 42.3149, -83.0364 ), 'Windsor, across the river.' );
		$this->assertSame( 'FR', Sloc_Offline_Geo::country( 48.8566, 2.3522 ) );
		$this->assertSame( 'PT', Sloc_Offline_Geo::country( 38.69, -9.25 ), 'In the Tagus estuary, near Lisbon.' );
		$this->assertSame( 'US', Sloc_Offline_Geo::country( 44.055454, -68.423863 ), 'Swan\'s Island, Maine, outside the borders, from its timezone.' );
		$this->assertSame( '', Sloc_Offline_Geo::country( 30, -40 ), 'The mid-Atlantic.' );
		$this->assertSame( '', Sloc_Offline_Geo::country( 95, 0 ) );
		$this->assertSame( '', Sloc_Offline_Geo::country( null, 0 ) );

		// Cached.
		$this->assertSame( 'US', wp_cache_get( 'country:39.9524,-75.1636', Sloc_Offline_Geo::CACHE_GROUP ) );
	}

	/**
	 * A timezone's country, and none for nautical or unknown timezones.
	 */
	public function test_timezone_country() {
		$this->assertSame( 'US', Sloc_Offline_Geo::timezone_country( 'America/New_York' ) );
		$this->assertSame( 'MC', Sloc_Offline_Geo::timezone_country( 'Europe/Monaco' ) );
		$this->assertSame( '', Sloc_Offline_Geo::timezone_country( 'Etc/GMT+5' ) );
		$this->assertSame( '', Sloc_Offline_Geo::timezone_country( 'UTC' ) );
		$this->assertSame( '', Sloc_Offline_Geo::timezone_country( 'Not/AZone' ) );
		$this->assertSame( '', Sloc_Offline_Geo::timezone_country( '' ) );
	}

	/**
	 * Timezones come from boundaries, and at sea are nautical.
	 */
	public function test_timezone() {
		$this->assertTrue( Sloc_Offline_Geo::has_timezones() );
		$this->assertSame( 'America/New_York', Sloc_Offline_Geo::timezone( 39.9524, -75.1636 ) );
		$this->assertSame( 'Asia/Kolkata', Sloc_Offline_Geo::timezone( 28.6139, 77.209 ) );
		$this->assertSame( 'Etc/GMT-9', Sloc_Offline_Geo::timezone( 0, 140 ), 'The Pacific north of New Guinea, nine hours ahead of UTC.' );
		$this->assertSame( '', Sloc_Offline_Geo::timezone( 'x', 0 ) );

		add_filter( 'sloc_offline_geo_dir', fn() => __DIR__ . '/data/missing-geo-data/' );
		$this->assertFalse( Sloc_Offline_Geo::has_timezones() );
		$this->assertSame( '', Sloc_Offline_Geo::timezone( 39.9524, -75.1636 ) );
		$this->assertSame( '', Sloc_Offline_Geo::country( 39.9524, -75.1630 ) );
		$this->assertNull( Sloc_Offline_Geo::place( 39.9524, -75.1630 ) );
		$this->assertNull( Sloc_Offline_Geo::find_place( 'Philadelphia' ) );
	}

	/**
	 * The place weighted by population: the city you are in, not a neighborhood nearer its point.
	 */
	public function test_place() {
		$place = Sloc_Offline_Geo::place( 40.758, -73.9855 );
		$this->assertSame( 'New York City', $place['name'], 'Times Square.' );
		$this->assertSame( 'New York', $place['region'] );
		$this->assertSame( 'US', $place['country'] );
		$this->assertGreaterThan( 0, $place['distance'] );
		$this->assertSame( 'Philadelphia', Sloc_Offline_Geo::place( 39.9524, -75.1636 )['name'] );
		$this->assertNull( Sloc_Offline_Geo::place( 39.5, -116.5 ), 'Rural Nevada has no place within reach.' );
		$this->assertNull( Sloc_Offline_Geo::place( 91, 0 ) );

		// Only places in a country.
		$this->assertSame( 'Detroit', Sloc_Offline_Geo::place( 42.3149, -83.0364 )['name'], 'Downtown Detroit is closer in weight.' );
		$this->assertSame( 'CA', Sloc_Offline_Geo::place( 42.3149, -83.0364, 'ca' )['country'] );
	}

	/**
	 * Places are found by name ignoring case and accents, the hints picking among places of the same name.
	 */
	public function test_find_place() {
		$this->assertSame( 'IL', Sloc_Offline_Geo::find_place( 'Springfield', array( 'Illinois' ) )['region_code'] );
		$this->assertSame( 'MA', Sloc_Offline_Geo::find_place( 'springfield', array( 'MA', 'US' ) )['region_code'] );
		$this->assertSame( 'US', Sloc_Offline_Geo::find_place( 'Philadelphia', array( 'PA' ) )['country'], 'PA is Pennsylvania here, not Panama.' );
		$zurich = Sloc_Offline_Geo::find_place( 'ZURICH' );
		$this->assertSame( 'Zürich', $zurich['name'] );
		$this->assertSame( 0, $zurich['distance'] );
		$this->assertNull( Sloc_Offline_Geo::find_place( 'Nowhere Town Zzyzx' ) );
		$this->assertNull( Sloc_Offline_Geo::find_place( '  ' ) );
	}

	/**
	 * The provider is registered.
	 */
	public function test_provider_registered() {
		$providers = Loc_Config::geo_providers();
		$this->assertArrayHasKey( 'offline', $providers );
		$this->assertNotEmpty( $providers['offline']['name'] );
	}

	/**
	 * Reverse lookup gives the town, region and country with the timezone.
	 */
	public function test_reverse_lookup() {
		$provider = new Geo_Provider_Offline();
		$provider->set( 39.9524, -75.1636 );
		$addr = $provider->reverse_lookup();
		$this->assertSame( 'Philadelphia', $addr['locality'] );
		$this->assertSame( 'Pennsylvania', $addr['region'] );
		$this->assertSame( 'PA', $addr['region-code'] );
		$this->assertSame( 'United States', $addr['country-name'] );
		$this->assertSame( 'US', $addr['country-code'] );
		$this->assertSame( 'America/New_York', $addr['timezone'] );
		$this->assertSame( 'Philadelphia, PA, US', $addr['display-name'], 'With the country, as the site has none set.' );
		$this->assertArrayNotHasKey( 'street-address', $addr );

		// Windsor gets a Canadian place, not Detroit across the river.
		$provider->set( 42.3149, -83.0364 );
		$addr = $provider->reverse_lookup();
		$this->assertSame( 'CA', $addr['country-code'] );
		$this->assertSame( 'ON', $addr['region-code'] );
		$this->assertNotSame( 'Detroit', $addr['locality'] );

		// A GeoNames region code that isn't the ISO code isn't used as one: Lisbon's is 14, ISO's 14 is Santarém.
		$provider->set( 38.7223, -9.1393 );
		$addr = $provider->reverse_lookup();
		$this->assertSame( 'PT', $addr['country-code'] );
		$this->assertNotSame( '14', $addr['region-code'] ?? null );

		// Without a place nearby, only the country.
		$provider->set( 39.5, -116.5 );
		$addr = $provider->reverse_lookup();
		$this->assertSame( 'US', $addr['country-code'] );
		$this->assertArrayNotHasKey( 'locality', $addr );
		$this->assertSame( 'America/Los_Angeles', $addr['timezone'] );

		// At sea, nothing.
		$provider->set( 30, -40 );
		$this->assertSame( 'no_results', $provider->reverse_lookup()->get_error_code() );
	}

	/**
	 * Geocoding finds a place by name from an address, skipping a street address, and gives its coordinates.
	 */
	public function test_geocode() {
		$provider = new Geo_Provider_Offline();
		$addr     = $provider->geocode( '1400 John F Kennedy Blvd, Philadelphia, PA 19107' );
		$this->assertSame( 'Philadelphia', $addr['locality'] );
		$this->assertSame( 'PA', $addr['region-code'] );
		$this->assertEqualsWithDelta( 39.95, $addr['latitude'], 0.05 );
		$this->assertEqualsWithDelta( -75.16, $addr['longitude'], 0.05 );
		$this->assertSame( 'America/New_York', $addr['timezone'] );

		$addr = $provider->geocode( 'Springfield, Illinois, United States' );
		$this->assertSame( 'IL', $addr['region-code'] );

		$this->assertSame( 'no_results', $provider->geocode( 'Nowhere Town Zzyzx' )->get_error_code() );
		$this->assertSame( 'no_results', $provider->geocode( '' )->get_error_code() );
	}

	/**
	 * Regional providers' coverage uses the country from borders.
	 */
	public function test_country_for_location() {
		$this->assertSame( 'CA', Sloc_Provider::country_for_location( 42.3149, -83.0364 ) );
		$this->assertSame( 'US', Sloc_Provider::country_for_location( 42.3314, -83.0458 ) );

		// Without the country data, the timezone's country.
		add_filter( 'sloc_offline_geo_dir', fn() => __DIR__ . '/data/missing-geo-data/' );
		$this->assertSame( '', Sloc_Provider::country_for_location( 42.3149, -83.0364 ), 'Without timezones either, nothing.' );
	}
}

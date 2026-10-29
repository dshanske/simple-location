<?php
/**
 * Tests the snow depth and snow rate readings, and the move of snow depth stored as snowfall (#783).
 *
 * @package Simple_Location
 */

class SnowDepthTest extends WP_UnitTestCase {

	public function test_properties() {
		$this->assertContains( 'snowdepth', Sloc_Weather_Data::$properties );
		$this->assertContains( 'snowrate', Sloc_Weather_Data::$properties );
		Sloc_Weather_Data::register_meta();
		$keys = get_registered_meta_keys( 'post' );
		$this->assertArrayHasKey( 'weather_snowdepth', $keys );
		$this->assertArrayHasKey( 'weather_snowrate', $keys );
	}

	public function test_names() {
		$depth = Weather_Provider::get_names( 'snowdepth' );
		$this->assertSame( 'Snow Depth', $depth['name'] );
		$this->assertSame( 'mm', $depth['unit'] );
		$this->assertSame( 'millimeters on the ground', $depth['label'] );
		$this->assertSame( 'in', Weather_Provider::get_names( 'snowdepth', true )['unit'] );
		$this->assertSame( 'Snowfall', Weather_Provider::get_names( 'snow' )['name'], 'Snowfall is unchanged.' );
		$this->assertSame( 'mm/h', Weather_Provider::get_names( 'snowrate' )['unit'] );
		$this->assertSame( 'in/h', Weather_Provider::get_names( 'snowrate', true )['unit'] );
	}

	public function test_imperial_conversion() {
		$imperial = Weather_Provider::metric_to_imperial(
			array(
				'snowdepth' => 254,
				'snowrate'  => 25.4,
			)
		);
		$this->assertEqualsWithDelta( 10, $imperial['snowdepth'], 0.01 );
		$this->assertEqualsWithDelta( 1, $imperial['snowrate'], 0.01 );
		$metric = Weather_Provider::imperial_to_metric( $imperial );
		$this->assertEqualsWithDelta( 254, $metric['snowdepth'], 0.1 );
	}

	public function test_meteostat_depth_not_snowfall() {
		$conditions = ( new Weather_Provider_Meteostat() )->convert_data(
			array(
				'temp' => -2,
				'snow' => 150,
			)
		);
		$this->assertSame( 150.0, (float) $conditions['snowdepth'], 'Already millimeters, not multiplied by 10.' );
		$this->assertArrayNotHasKey( 'snow', $conditions );
	}

	public function test_aeris_depth_not_snowfall() {
		$conditions = ( new Weather_Provider_Aeris() )->convert_data(
			array(
				'ob' => array(
					'tempC'       => -2,
					'snowDepthCM' => 15,
				),
			)
		);
		$this->assertSame( 150.0, (float) $conditions['snowdepth'] );
		$this->assertArrayNotHasKey( 'snow', $conditions );
	}

	public function test_visualcrossing_depth() {
		$conditions = ( new Weather_Provider_Visualcrossing() )->convert_data(
			array(
				'temp'      => -2,
				'snow'      => 1.2,
				'snowdepth' => 20,
			)
		);
		$this->assertEqualsWithDelta( 12, $conditions['snow'], 0.001, 'Snowfall, cm to mm.' );
		$this->assertEqualsWithDelta( 200, $conditions['snowdepth'], 0.001, 'Depth, cm to mm.' );
	}

	public function test_openmeteo_depth() {
		$this->assertContains( 'snow_depth', Weather_Provider_OpenMeteo::VARIABLES );
		$conditions = ( new Weather_Provider_OpenMeteo() )->convert_data(
			array(
				'temperature_2m' => -2,
				'snowfall'       => 0.7,
				'snow_depth'     => 0.32,
			)
		);
		$this->assertEqualsWithDelta( 7, $conditions['snow'], 0.001 );
		$this->assertSame( 320.0, $conditions['snowdepth'], 'Meters to millimeters.' );
		$this->assertArrayNotHasKey( 'snowdepth', ( new Weather_Provider_OpenMeteo() )->convert_data( array( 'temperature_2m' => 5 ) ) );
	}

	public function test_station_file() {
		$conditions = Weather_Provider_Station::parse_document(
			array(
				'format'       => 'current-conditions',
				'version'      => 1,
				'time'         => time(),
				'units'        => 'metric',
				'station'      => array( 'id' => 'backyard' ),
				'observations' => array(
					'temperature' => -1,
					'snow'        => 2.5,
					'snowdepth'   => 120,
					'snowrate'    => 1.5,
				),
			)
		);
		$this->assertEquals( 2.5, $conditions['snow'] );
		$this->assertEquals( 120, $conditions['snowdepth'] );
		$this->assertEquals( 1.5, $conditions['snowrate'] );
	}

	/**
	 * Weather saved by Meteostat and Xweather before 6.2.0 has its snow depth moved from snowfall.
	 */
	public function test_migrate_snow_depth() {
		$meteostat = self::factory()->post->create();
		update_post_meta( $meteostat, 'weather_provider', 'meteostat' );
		update_post_meta( $meteostat, 'weather_snow', 1500 );

		$aeris = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		update_post_meta( $aeris, 'weather_provider', 'aeris' );
		update_post_meta( $aeris, 'weather_snow', 150 );

		$snowfall = self::factory()->post->create();
		update_post_meta( $snowfall, 'weather_provider', 'openmeteo' );
		update_post_meta( $snowfall, 'weather_snow', 7 );

		$kept = self::factory()->post->create();
		update_post_meta( $kept, 'weather_provider', 'meteostat' );
		update_post_meta( $kept, 'weather_snow', 1500 );
		update_post_meta( $kept, 'weather_snowdepth', 140 );

		$comment = self::factory()->comment->create();
		update_comment_meta( $comment, 'weather_provider', 'meteostat' );
		update_comment_meta( $comment, 'weather_snow', 300 );

		$this->assertSame( 3, Sloc_Weather_Data::migrate_snow_depth() );

		$this->assertEquals( 150, get_post_meta( $meteostat, 'weather_snowdepth', true ), 'Meteostat divided by ten.' );
		$this->assertSame( '', get_post_meta( $meteostat, 'weather_snow', true ) );
		$this->assertEquals( 150, get_post_meta( $aeris, 'weather_snowdepth', true ), 'Xweather moved as it is, drafts too.' );
		$this->assertEquals( 7, get_post_meta( $snowfall, 'weather_snow', true ), 'Real snowfall is left alone.' );
		$this->assertSame( '', get_post_meta( $snowfall, 'weather_snowdepth', true ) );
		$this->assertEquals( 140, get_post_meta( $kept, 'weather_snowdepth', true ), 'An existing depth is kept.' );
		$this->assertSame( '', get_post_meta( $kept, 'weather_snow', true ) );
		$this->assertEquals( 30, get_comment_meta( $comment, 'weather_snowdepth', true ), 'Comments too.' );

		$this->assertSame( 0, Sloc_Weather_Data::migrate_snow_depth(), 'Nothing left to move.' );
	}

	public function test_data_cleanup_moves_snow_depth() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'weather_provider', 'meteostat' );
		update_post_meta( $post_id, 'weather_snow', 800 );
		delete_option( Loc_Config::DATA_CLEANUP_OPTION );
		Loc_Config::run_data_cleanup();
		$this->assertEquals( 80, get_post_meta( $post_id, 'weather_snowdepth', true ) );
		$this->assertEquals( Loc_Config::DATA_CLEANUP_VERSION, get_option( Loc_Config::DATA_CLEANUP_OPTION ) );
	}
}

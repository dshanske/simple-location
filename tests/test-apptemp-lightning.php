<?php
/**
 * Tests the apparent temperature, cloud base and lightning readings (#813).
 *
 * @package Simple_Location
 */

/**
 * Apparent temperature, cloud base and lightning tests.
 */
class ApptempLightningTest extends WP_UnitTestCase {

	/**
	 * A station file in the current conditions format.
	 *
	 * @param array  $observations Observations.
	 * @param string $units        metric or us.
	 * @return array
	 */
	protected function document( $observations, $units = 'metric' ) {
		return array(
			'format'       => 'current-conditions',
			'version'      => 1,
			'time'         => time(),
			'units'        => $units,
			'station'      => array( 'id' => 'backyard' ),
			'observations' => $observations,
		);
	}

	/**
	 * The readings are weather properties with registered meta.
	 */
	public function test_properties() {
		Sloc_Weather_Data::register_meta();
		$keys = get_registered_meta_keys( 'post' );
		foreach ( array( 'apptemp', 'cloudbase', 'lightning_strikes', 'lightning_distance' ) as $property ) {
			$this->assertContains( $property, Sloc_Weather_Data::$properties );
			$this->assertArrayHasKey( 'weather_' . $property, $keys );
			$this->assertSame( 'number', $keys[ 'weather_' . $property ]['type'] );
		}
	}

	/**
	 * Each reading has a name and units, metric and imperial.
	 */
	public function test_names() {
		$this->assertSame( 'Feels Like', Weather_Provider::get_names( 'apptemp' )['name'] );
		$this->assertSame( '&deg;C', Weather_Provider::get_names( 'apptemp' )['unit'] );
		$this->assertSame( '&deg;F', Weather_Provider::get_names( 'apptemp', true )['unit'] );
		$this->assertSame( 'Cloud Base', Weather_Provider::get_names( 'cloudbase' )['name'] );
		$this->assertSame( 'm', Weather_Provider::get_names( 'cloudbase' )['unit'] );
		$this->assertSame( 'ft', Weather_Provider::get_names( 'cloudbase', true )['unit'] );
		$this->assertSame( 'Lightning Strikes', Weather_Provider::get_names( 'lightning_strikes' )['name'] );
		$this->assertSame( '', Weather_Provider::get_names( 'lightning_strikes', true )['unit'], 'A count has no unit.' );
		$this->assertSame( 'wi-lightning', Weather_Provider::get_names( 'lightning_strikes' )['icon'] );
		$this->assertSame( 'km', Weather_Provider::get_names( 'lightning_distance' )['unit'] );
		$this->assertSame( 'mi', Weather_Provider::get_names( 'lightning_distance', true )['unit'] );
	}

	/**
	 * The readings convert between metric and imperial; the strike count doesn't.
	 */
	public function test_conversion() {
		$imperial = Weather_Provider::metric_to_imperial(
			array(
				'apptemp'            => 30,
				'cloudbase'          => 1000,
				'lightning_strikes'  => 4,
				'lightning_distance' => 16.09,
			)
		);
		$this->assertEqualsWithDelta( 86, $imperial['apptemp'], 0.01 );
		$this->assertEqualsWithDelta( 3280.84, $imperial['cloudbase'], 0.01 );
		$this->assertSame( 4, $imperial['lightning_strikes'] );
		$this->assertEqualsWithDelta( 10, $imperial['lightning_distance'], 0.01 );

		$metric = Weather_Provider::imperial_to_metric( $imperial );
		$this->assertEqualsWithDelta( 30, $metric['apptemp'], 0.1 );
		$this->assertEqualsWithDelta( 1000, $metric['cloudbase'], 0.1 );
		$this->assertEqualsWithDelta( 16.09, $metric['lightning_distance'], 0.01 );
		$this->assertSame( array( 'cloudbase' => 'n/a' ), Weather_Provider::metric_to_imperial( array( 'cloudbase' => 'n/a' ) ), 'Not a number.' );
	}

	/**
	 * A station file's readings are read, converted from US units.
	 */
	public function test_station_file() {
		$conditions = Weather_Provider_Station::parse_document(
			$this->document(
				array(
					'temperature'        => 30,
					'apptemp'            => 33.5,
					'cloudbase'          => 1200,
					'lightning_strikes'  => 3,
					'lightning_distance' => 12.5,
				)
			)
		);
		$this->assertEquals( 33.5, $conditions['apptemp'] );
		$this->assertEquals( 1200, $conditions['cloudbase'] );
		$this->assertEquals( 3, $conditions['lightning_strikes'] );
		$this->assertEquals( 12.5, $conditions['lightning_distance'] );

		$conditions = Weather_Provider_Station::parse_document(
			$this->document(
				array(
					'temperature'        => 86,
					'apptemp'            => 86,
					'cloudbase'          => 3280.84,
					'lightning_strikes'  => 3,
					'lightning_distance' => 10,
				),
				'us'
			)
		);
		$this->assertEqualsWithDelta( 30, $conditions['apptemp'], 0.1 );
		$this->assertEqualsWithDelta( 1000, $conditions['cloudbase'], 0.1 );
		$this->assertEqualsWithDelta( 16.09, $conditions['lightning_distance'], 0.01 );
		$this->assertEquals( 3, $conditions['lightning_strikes'] );
	}

	/**
	 * No strikes in the last hour isn't kept, from a station file, the legacy format, or when saving (#813).
	 */
	public function test_no_strikes() {
		$conditions = Weather_Provider_Station::parse_document(
			$this->document(
				array(
					'temperature'        => 20,
					'lightning_strikes'  => 0,
					'lightning_distance' => 8,
				)
			)
		);
		$this->assertArrayNotHasKey( 'lightning_strikes', $conditions );
		$this->assertArrayNotHasKey( 'lightning_distance', $conditions );
		$this->assertEquals( 20, $conditions['temperature'] );

		$legacy = Weather_Provider_Station::parse_document(
			array(
				'temperature'       => 20,
				'lightning_strikes' => '0',
			)
		);
		$this->assertArrayNotHasKey( 'lightning_strikes', $legacy );

		$this->assertSame( array( 'temperature' => 1 ), Sloc_Weather_Data::without_quiet_lightning( array( 'temperature' => 1 ) ) );
		$this->assertSame( 'text', Sloc_Weather_Data::without_quiet_lightning( 'text' ) );
		$this->assertSame(
			array(
				'lightning_strikes'  => 2,
				'lightning_distance' => 5,
			),
			Sloc_Weather_Data::without_quiet_lightning(
				array(
					'lightning_strikes'  => 2,
					'lightning_distance' => 5,
				)
			)
		);
	}

	/**
	 * Saving weather keeps strikes; no strikes removes the lightning saved before; other weather leaves it.
	 */
	public function test_save_lightning() {
		$post_id = self::factory()->post->create();
		set_post_weatherdata(
			$post_id,
			'',
			array(
				'temperature'        => 20,
				'apptemp'            => 21,
				'cloudbase'          => 900,
				'lightning_strikes'  => 5,
				'lightning_distance' => 3.2,
			)
		);
		$this->assertEquals( 5, get_post_meta( $post_id, 'weather_lightning_strikes', true ) );
		$this->assertEquals( 3.2, get_post_meta( $post_id, 'weather_lightning_distance', true ) );
		$this->assertEquals( 21, get_post_meta( $post_id, 'weather_apptemp', true ) );
		$this->assertEquals( 900, get_post_meta( $post_id, 'weather_cloudbase', true ) );

		set_post_weatherdata( $post_id, '', array( 'snowdepth' => 10 ) );
		$this->assertEquals( 5, get_post_meta( $post_id, 'weather_lightning_strikes', true ), 'Other weather leaves it.' );

		set_post_weatherdata( $post_id, '', array( 'lightning_strikes' => 0 ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'weather_lightning_strikes' ) );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'weather_lightning_distance' ) );

		set_post_weatherdata( $post_id, 'lightning_strikes', 2 );
		$this->assertEquals( 2, get_post_meta( $post_id, 'weather_lightning_strikes', true ) );
		set_post_weatherdata( $post_id, 'lightning_strikes', 0 );
		$this->assertFalse( metadata_exists( 'post', $post_id, 'weather_lightning_strikes' ) );
	}

	/**
	 * The weather widget lists the readings with their names and units.
	 */
	public function test_markup() {
		$markup = Weather_Provider::markup_value( 'cloudbase', 1200, array( 'units' => 'metric' ) );
		$this->assertStringContainsString( 'sloc-cloudbase', $markup );
		$this->assertStringContainsString( 'value="m"', $markup );
		$this->assertStringContainsString( 'value="1200"', $markup );
		$this->assertStringContainsString( 'value="ft"', Weather_Provider::markup_value( 'cloudbase', 3937, array( 'units' => 'imperial' ) ) );
	}

	/**
	 * The apparent temperature can be filled in from Open-Meteo.
	 */
	public function test_fill_reading() {
		$this->assertSame( array( 'apptemp' ), Sloc_Weather_Fill::get_readings()['apptemp']['keys'] );
	}
}

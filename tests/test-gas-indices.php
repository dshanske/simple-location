<?php
/**
 * Tests the VOC and NOx indices of gas sensors such as the Sensirion SGP41 (#788).
 *
 * @package Simple_Location
 */

class GasIndicesTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

	public function set_up() {
		parent::set_up();
		update_option( 'sloc_measurements', 'metric' );
		delete_option( 'sloc_weather_fallbacks' );
		delete_option( 'sloc_weewx_stations' );
	}

	public function test_properties() {
		$this->assertContains( 'voc_index', Sloc_Weather_Data::$properties );
		$this->assertContains( 'nox_index', Sloc_Weather_Data::$properties );
	}

	public function test_names() {
		foreach ( array( false, true ) as $imperial ) {
			$voc = Weather_Provider::get_names( 'voc_index', $imperial );
			$this->assertSame( 'VOC Index', $voc['name'] );
			$this->assertSame( '', $voc['unit'], 'An index has no unit.' );
			$this->assertSame( 'index', $voc['label'] );
			$this->assertSame( 'NOx Index', Weather_Provider::get_names( 'nox_index', $imperial )['name'] );
		}
	}

	public function test_meta_registered() {
		Sloc_Weather_Data::register_meta();
		$keys = get_registered_meta_keys( 'post' );
		$this->assertArrayHasKey( 'weather_voc_index', $keys );
		$this->assertArrayHasKey( 'weather_nox_index', $keys );
	}

	public function test_stored_and_read() {
		$post_id = self::factory()->post->create();
		set_post_weatherdata(
			$post_id,
			'',
			array(
				'temperature' => 20,
				'voc_index'   => 112,
				'nox_index'   => 1,
			)
		);
		$this->assertEquals( 112, get_post_weatherdata( $post_id, 'voc_index' ) );
		$this->assertEquals( 1, get_post_weatherdata( $post_id, 'nox_index' ) );
		$this->assertEquals( 112, get_post_meta( $post_id, 'weather_voc_index', true ) );
	}

	public function test_not_converted_to_imperial() {
		$imperial = Weather_Provider::metric_to_imperial(
			array(
				'voc_index' => 112,
				'nox_index' => 3,
			)
		);
		$this->assertEquals( 112, $imperial['voc_index'] );
		$this->assertEquals( 3, $imperial['nox_index'] );
	}

	public function test_markup() {
		$markup = Weather_Provider::markup_value( 'voc_index', 112, array( 'markup' => false ) );
		$this->assertStringContainsString( 'VOC Index: 112', $markup );
	}

	public function test_read_from_weewx_file() {
		$document                                = $this->fixture( 'weather/station-current-conditions.json' );
		$document['time']                        = time();
		$document['observations']['voc_index']   = 112;
		$document['observations']['nox_index']   = 1;
		$document['observations']['co2']         = 421;
		$this->mock_http( array( 'weather.example.com' => array( 'body' => $document ) ) );
		update_option( 'sloc_weewx_stations', Weather_Provider_Weewx::sanitize_stations( array( array( 'url' => 'https://weather.example.com/current.json' ) ) ) );

		$provider = new Weather_Provider_Weewx();
		$provider->set( array( 'station_id' => 'backyard' ) );
		$conditions = $provider->get_conditions();
		$this->assertEquals( 112, $conditions['voc_index'] );
		$this->assertEquals( 1, $conditions['nox_index'] );
		$this->assertEquals( 421, $conditions['co2'] );
	}
}

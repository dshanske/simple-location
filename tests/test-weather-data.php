<?php

use DMS\PHPUnitExtensions\ArraySubset\Assert;

class WeatherDataTest extends WP_UnitTestCase {
	public static $weather = array(
		'temperature' => '45.01',
		'humidity'  => '45.10',
		'winddegree' => '140',
		'windspeed' => '42'
	);

	public static $oldweather = array(
		'temperature' => '45.01',
		'humidity'  => '45.10',
		'wind' => array(
			'degree' => '140',
			'speed' => '42'
		)
	);

	public function test_set_and_get_post_temperature() {
		$post_id = $this->factory()->post->create();
		set_post_weatherdata( $post_id, 'temperature', 45.2 );
		$return = get_post_weatherdata( $post_id, 'temperature' );
		$this->assertEquals( 45.2, $return, wp_json_encode( $return ) );
	}

	public function test_set_and_get_post_weatherdata() {
		$post_id = $this->factory()->post->create();
		set_post_weatherdata( $post_id, '', static::$weather );
		$this->assertEquals( static::$weather, get_post_weatherdata( $post_id ) );
	}

	public function test_set_and_get_old_post_weatherdata() {
		$post_id = $this->factory()->post->create();
		add_post_meta( $post_id, 'geo_weather', static::$oldweather );
		$this->assertEquals( static::$weather, get_post_weatherdata( $post_id ) );
	}

	public function test_save_meta_from_POST() {
		$_POST = static::$weather;
		$post_id = $this->factory()->post->create();
		Geo_Base::save_meta( 'post', $post_id, false );
		Assert::assertArraySubset( static::$weather, get_post_weatherdata( $post_id ) );		
	}

}


<?php
/**
 * Tests the plain-text weather output (#767).
 *
 * @package Simple_Location
 */

class WeatherTextTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		update_option( 'sloc_measurements', 'metric' );
	}

	public function test_no_weather() {
		$post_id = self::factory()->post->create();
		$this->assertSame( '', Sloc_Weather_Data::get_the_weather_text( 'post', $post_id ) );
		$this->assertSame( '', get_post_weather_text( $post_id ) );
		$this->assertSame( '', Sloc_Weather_Data::get_the_weather( 'post', $post_id, array( 'style' => 'text' ) ) );
	}

	public function test_temperature_and_summary() {
		$post_id = self::factory()->post->create();
		set_post_weatherdata(
			$post_id,
			'',
			array(
				'temperature' => 21.4,
				'code'        => 800,
			)
		);
		$this->assertSame( '21°C Clear Sky', get_post_weather_text( $post_id ) );
		$this->assertSame( '21°C Clear Sky', Sloc_Weather_Data::get_the_weather( 'post', $post_id, array( 'style' => 'text' ) ) );
	}

	public function test_no_markup_or_icon() {
		$post_id = self::factory()->post->create();
		set_post_weatherdata(
			$post_id,
			'',
			array(
				'temperature' => 10,
				'summary'     => '<b>Light &amp; Rain</b>',
				'icon'        => 'wi-rain',
				'name'        => 'Station 1',
				'station_id'  => 'abc',
			)
		);
		$text = get_post_weather_text( $post_id );
		$this->assertSame( '10°C Light & Rain', $text );
		$this->assertStringNotContainsString( '<', $text );
		$this->assertStringNotContainsString( 'Station', $text );
	}

	public function test_imperial() {
		update_option( 'sloc_measurements', 'imperial' );
		$post_id = self::factory()->post->create();
		set_post_weatherdata( $post_id, 'temperature', 22.2 );
		$this->assertSame( '72°F', get_post_weather_text( $post_id ) );
	}

	public function test_zero_temperature() {
		$comment_id = self::factory()->comment->create();
		set_comment_weatherdata( $comment_id, 'temperature', 0 );
		$this->assertSame( '0°C', get_comment_weather_text( $comment_id ) );
	}

	public function test_summary_only() {
		$post_id = self::factory()->post->create();
		set_post_weatherdata( $post_id, 'code', 800 );
		$this->assertSame( 'Clear Sky', get_post_weather_text( $post_id ) );
	}

	public function test_missing_post() {
		$this->assertSame( '', get_post_weather_text( PHP_INT_MAX ) );
	}

	public function test_filter() {
		$post_id = self::factory()->post->create();
		set_post_weatherdata( $post_id, 'temperature', 5 );
		add_filter(
			'sloc_weather_text',
			function ( $text, $weather, $type, $id ) use ( $post_id ) {
				$this->assertSame( 'post', $type );
				$this->assertSame( $post_id, $id );
				return 'Cold: ' . $text;
			},
			10,
			4
		);
		$this->assertSame( 'Cold: 5°C', get_post_weather_text( $post_id ) );
	}
}

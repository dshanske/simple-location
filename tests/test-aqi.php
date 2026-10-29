<?php
/**
 * Tests the air quality index category, color and display (#790).
 *
 * @package Simple_Location
 */

class AqiTest extends WP_UnitTestCase {

	/**
	 * @dataProvider categories
	 */
	public function test_category( $aqi, $slug, $name, $color, $text_color ) {
		$category = Weather_Provider::aqi_category( $aqi );
		$this->assertSame( $slug, $category['slug'], (string) $aqi );
		$this->assertSame( $name, $category['name'] );
		$this->assertSame( $color, $category['color'] );
		$this->assertSame( $text_color, $category['text_color'] );
	}

	public function categories() {
		return array(
			array( 0, 'good', 'Good', '#00e400', '#000000' ),
			array( 50, 'good', 'Good', '#00e400', '#000000' ),
			array( 51, 'moderate', 'Moderate', '#ffff00', '#000000' ),
			array( 100, 'moderate', 'Moderate', '#ffff00', '#000000' ),
			array( 101, 'unhealthy-sensitive', 'Unhealthy for Sensitive Groups', '#ff7e00', '#000000' ),
			array( 150, 'unhealthy-sensitive', 'Unhealthy for Sensitive Groups', '#ff7e00', '#000000' ),
			array( 151, 'unhealthy', 'Unhealthy', '#ff0000', '#ffffff' ),
			array( 200, 'unhealthy', 'Unhealthy', '#ff0000', '#ffffff' ),
			array( 201, 'very-unhealthy', 'Very Unhealthy', '#8f3f97', '#ffffff' ),
			array( 300, 'very-unhealthy', 'Very Unhealthy', '#8f3f97', '#ffffff' ),
			array( 301, 'hazardous', 'Hazardous', '#7e0023', '#ffffff' ),
			array( 650, 'hazardous', 'Hazardous', '#7e0023', '#ffffff' ),
			array( '72', 'moderate', 'Moderate', '#ffff00', '#000000' ),
			array( 50.4, 'good', 'Good', '#00e400', '#000000' ),
			array( 50.6, 'moderate', 'Moderate', '#ffff00', '#000000' ),
		);
	}

	public function test_no_category() {
		foreach ( array( null, '', 'bad', -1, array() ) as $aqi ) {
			$this->assertNull( Weather_Provider::aqi_category( $aqi ), wp_json_encode( $aqi ) );
		}
		$this->assertSame( Weather_Provider::aqi_category( 72 ), Sloc_Weather_Data::aqi_category( 72 ), 'The same on stored weather.' );
	}

	public function test_filter() {
		add_filter(
			'sloc_aqi_category',
			function ( $category, $aqi ) {
				$category['color'] = 72 === $aqi ? '#123456' : $category['color'];
				return $category;
			},
			10,
			2
		);
		$this->assertSame( '#123456', Weather_Provider::aqi_category( 72 )['color'] );
		add_filter( 'sloc_aqi_category', '__return_null', 20 );
		$this->assertNull( Weather_Provider::aqi_category( 72 ) );
	}

	public function test_badge() {
		$post_id = self::factory()->post->create();
		set_post_weatherdata( $post_id, 'aqi', 72 );
		$badge = get_post_aqi( $post_id );
		$this->assertSame(
			'<span class="sloc-aqi sloc-aqi-moderate" style="background-color:#ffff00;color:#000000" title="Air Quality Index 72: Moderate"><span class="sloc-aqi-label">AQI</span> <data class="p-aqi" value="72">72</data> <span class="sloc-aqi-category">Moderate</span></span>',
			$badge
		);
		$this->assertSame( $badge, wp_kses( $badge, Simple_Location_Plugin::kses_clean() ), 'Survives the plugin\'s allowed HTML.' );
	}

	public function test_text() {
		$comment_id = self::factory()->comment->create();
		set_comment_weatherdata( $comment_id, 'aqi', 163.6 );
		$this->assertSame( 'AQI 164 Unhealthy', get_comment_aqi( $comment_id, array( 'style' => 'text' ) ) );
		$this->assertSame( 'Air quality 164 Unhealthy', get_comment_aqi( $comment_id, array( 'style' => 'text', 'label' => 'Air quality' ) ) );
	}

	public function test_without_aqi() {
		$post_id = self::factory()->post->create();
		$this->assertSame( '', get_post_aqi( $post_id ) );
		set_post_weatherdata( $post_id, 'temperature', 20 );
		$this->assertSame( '', get_post_aqi( $post_id ) );
		$this->assertSame( '', get_post_aqi( PHP_INT_MAX ) );
	}

	public function test_complete_weather() {
		$post_id = self::factory()->post->create();
		set_post_weatherdata(
			$post_id,
			'',
			array(
				'temperature' => 20,
				'aqi'         => 42,
			)
		);
		$this->assertStringContainsString( 'sloc-aqi-good', Sloc_Weather_Data::get_the_weather( 'post', $post_id, array( 'style' => 'complete' ) ) );
		$this->assertStringNotContainsString( 'sloc-aqi', Sloc_Weather_Data::get_the_weather( 'post', $post_id ), 'Only the complete style.' );
	}

	public function test_station_scale() {
		$document = array(
			'format'       => 'current-conditions',
			'version'      => 1,
			'time'         => time(),
			'units'        => 'metric',
			'station'      => array( 'id' => 'backyard' ),
			'observations' => array(
				'temperature'  => 20,
				'aqi'          => 46,
				'aqi_category' => 'Good',
				'aqi_color'    => '#00e400',
			),
			'derived'      => array( 'aqi' => 'us_epa' ),
		);
		$conditions = Weather_Provider_Station::parse_document( $document );
		$this->assertEquals( 46, $conditions['aqi'] );
		$this->assertArrayNotHasKey( 'aqi_category', $conditions, 'Derived by the plugin, not stored.' );

		$document['derived']['aqi'] = 'eu_caqi';
		$this->assertArrayNotHasKey( 'aqi', Weather_Provider_Station::parse_document( $document ), 'Another scale isn\'t stored as the AQI.' );

		unset( $document['derived'] );
		$this->assertEquals( 46, Weather_Provider_Station::parse_document( $document )['aqi'], 'Without a scale, as before.' );
	}
}

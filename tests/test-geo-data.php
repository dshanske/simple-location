<?php

use DMS\PHPUnitExtensions\ArraySubset\Assert;

class GeoDataTest extends WP_UnitTestCase {
	public static $geo = array(
		'latitude' => '45.01',
		'longitude' => '-75.44',
		'address' => 'Test Location'
	);
	public function test_set_and_get_post_geodata() {
		$post_id = $this->factory()->post->create();
		set_post_geodata( $post_id, '', static::$geo );
		Assert::assertArraySubset( static::$geo, get_post_geodata( $post_id ), );
	}

	public function test_set_geodata_and_get_post_geopoint() {
		$post_id = $this->factory()->post->create();
		set_post_geodata( $post_id, '', static::$geo );
		$this->assertEquals( array( 45.01, -75.44 ), Geo_Data::get_geopoint( 'post', $post_id ), );
	}

	public function test_set_geodata_and_get_post_geouri() {
		$post_id = $this->factory()->post->create();
		set_post_geodata( $post_id, '', static::$geo );
		$this->assertEquals( 'geo:45.01,-75.44', Geo_Data::get_geouri( 'post', $post_id ), );
	}


	public function test_set_and_get_post_geopoint_with_altitude() {
		$post_id = $this->factory()->post->create();
		$geo = static::$geo;
		$geo['altitude'] = 1000;
		set_post_geodata( $post_id, '', $geo );
		$this->assertEquals( array( 45.01, -75.44, 1000 ), Geo_Data::get_geopoint( 'post', $post_id ), );
	}

	public function test_set_and_get_post_latitude() {
		$post_id = $this->factory()->post->create();
		set_post_geodata( $post_id, 'latitude', 45.2 );
		$return = get_post_geodata( $post_id, 'latitude' );
		$this->assertEquals( 45.2, $return, wp_json_encode( $return ) );
	}

	public function test_set_and_get_post_visibility() {
		$post_id = $this->factory()->post->create();
		set_post_geodata( $post_id, 'visibility', 'protected' );
		$return = get_post_geodata( $post_id, 'visibility' );
		$this->assertEquals( 'protected', $return, wp_json_encode( $return ) );
	}

	public function test_set_and_get_post_default_visibility() {
		$post_id = $this->factory()->post->create();
		$this->assertEquals( 'public', Geo_Data::get_default_visibility() );
		$return = get_post_geodata( $post_id, 'visibility' );
		$this->assertNotFalse( $return );
		$this->assertEquals( Geo_Data::get_default_visibility(), $return );
	}

	public function test_save_meta_from_POST() {
		$_POST = static::$geo;
		$post_id = $this->factory()->post->create();
		Geo_Base::save_meta( 'post', $post_id );
		Assert::assertArraySubset( static::$geo, get_post_geodata( $post_id ), );		
	}

	public function test_set_and_get_term_latitude() {
		$term_id = $this->factory()->term->create( array( 'taxonomy' => 'venue' ) );
		set_term_geodata( $term_id, 'latitude', 45.2 );
		$return = get_term_geodata( $term_id, 'latitude' );
		$this->assertEquals( 45.2, $return, wp_json_encode( $return ) );
	}


}


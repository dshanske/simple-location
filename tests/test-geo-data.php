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
		set_post_geodata( $post_id, static::$geo );
		Assert::assertArraySubset( static::$geo, get_post_geodata( $post_id ), );
	}

}


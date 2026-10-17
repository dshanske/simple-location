<?php
/**
 * Tests for the public data API in includes/data-functions.php and the Geo_Data helpers behind it.
 *
 * @package Simple_Location
 */

use DMS\PHPUnitExtensions\ArraySubset\Assert;

/**
 * Data API tests.
 */
class DataFunctionsTest extends WP_UnitTestCase {

	/**
	 * Sample location.
	 *
	 * @var array
	 */
	public static $geo = array(
		'latitude'  => 45.01,
		'longitude' => -75.44,
		'address'   => 'Test Location',
	);

	/**
	 * Comment geodata round-trips through the wrappers.
	 */
	public function test_comment_geodata() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );
		$this->assertTrue( set_comment_geodata( $comment_id, '', static::$geo ) );
		Assert::assertArraySubset( static::$geo, get_comment_geodata( $comment_id ) );
		$this->assertFalse( set_comment_geodata( 999999, '', static::$geo ) );
	}

	/**
	 * Term geodata round-trips through the wrappers.
	 */
	public function test_term_geodata() {
		$term_id = self::factory()->term->create();
		$this->assertTrue( set_term_geodata( $term_id, '', static::$geo ) );
		Assert::assertArraySubset( static::$geo, get_term_geodata( $term_id ) );
	}

	/**
	 * User geodata round-trips through the wrappers.
	 */
	public function test_user_geodata() {
		$user_id = self::factory()->user->create();
		$this->assertTrue( set_user_geodata( $user_id, '', static::$geo ) );
		Assert::assertArraySubset( static::$geo, get_user_geodata( $user_id ) );
	}

	/**
	 * Setting user geodata for a user that doesn't exist fails cleanly.
	 */
	public function test_user_geodata_invalid_user() {
		$this->assertFalse( set_user_geodata( 999999, '', static::$geo ) );
	}

	/**
	 * User geodata stored directly can be read through the wrapper.
	 */
	public function test_get_user_geodata() {
		$user_id = self::factory()->user->create();
		Geo_Data::set_geodata( 'user', $user_id, '', static::$geo );
		Assert::assertArraySubset( static::$geo, get_user_geodata( $user_id ) );
		$this->assertFalse( get_user_geodata( 999999 ) );
	}

	/**
	 * Invalid posts return empty values instead of errors.
	 */
	public function test_invalid_post() {
		$this->assertFalse( set_post_geodata( 999999, '', static::$geo ) );
		$this->assertSame( array(), get_post_geodata( 999999 ) );
		$this->assertSame( array(), has_post_location( 999999 ) );
		$this->assertFalse( get_post_location( 999999 ) );
		$this->assertFalse( get_post_map( 999999 ) );
	}

	/**
	 * Objects without location data.
	 */
	public function test_no_location() {
		$post_id = self::factory()->post->create();
		$this->assertNull( get_post_geodata( $post_id ) );
		$this->assertFalse( has_post_location( $post_id ) );
		$this->assertFalse( Geo_Data::get_geopoint( 'post', $post_id ) );
		$this->assertSame( '', get_post_location( $post_id ) );
	}

	/**
	 * A geo URI is only produced when there is a location.
	 */
	public function test_get_geouri_without_location() {
		$post_id = self::factory()->post->create();
		$this->assertFalse( Geo_Data::get_geouri( 'post', $post_id ) );
	}

	/**
	 * Keys outside the supported properties are rejected.
	 */
	public function test_unknown_keys_rejected() {
		$post_id = self::factory()->post->create();
		$this->assertFalse( set_post_geodata( $post_id, 'favorite_color', 'blue' ) );
		$this->assertFalse( get_post_geodata( $post_id, 'favorite_color' ) );
		$this->assertFalse( Geo_Data::delete_geodata( 'post', $post_id, 'favorite_color' ) );
		$this->assertFalse( Geo_Data::set_geodata( '', $post_id, 'latitude', 1.1 ) );
		$this->assertFalse( Geo_Data::get_geodata( 'post', 'abc' ) );
	}

	/**
	 * The map_zoom key is stored as zoom and returned under both names.
	 */
	public function test_map_zoom_alias() {
		$post_id = self::factory()->post->create();
		set_post_geodata( $post_id, 'map_zoom', 12 );
		$this->assertEquals( 12, get_post_meta( $post_id, 'geo_zoom', true ) );
		$geo             = static::$geo;
		$geo['map_zoom'] = 9;
		set_post_geodata( $post_id, '', $geo );
		$geodata = get_post_geodata( $post_id );
		$this->assertEquals( 9, $geodata['zoom'] );
		$this->assertEquals( 9, $geodata['map_zoom'] );
	}

	/**
	 * Empty values in a full update remove the stored value.
	 */
	public function test_empty_values_delete() {
		$post_id = self::factory()->post->create();
		set_post_geodata( $post_id, '', static::$geo );
		set_post_geodata( $post_id, '', array( 'address' => '' ) );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_address', true ) );
		$this->assertEquals( 45.01, get_post_geodata( $post_id, 'latitude' ) );
	}

	/**
	 * A single key can be deleted.
	 */
	public function test_delete_geodata() {
		$post_id = self::factory()->post->create();
		set_post_geodata( $post_id, '', static::$geo );
		$this->assertTrue( Geo_Data::delete_geodata( 'post', $post_id, 'address' ) );
		$this->assertSame( '', get_post_geodata( $post_id, 'address' ) );
	}

	/**
	 * Deleting visibility removes the stored geo_public value.
	 */
	public function test_delete_visibility() {
		$this->markTestSkipped( 'Known bug, see #314' );
		$post_id = self::factory()->post->create();
		set_post_geodata( $post_id, 'visibility', 'protected' );
		Geo_Data::delete_geodata( 'post', $post_id, 'visibility' );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_public', true ) );
	}

	/**
	 * The default icon is filled in when none is stored.
	 */
	public function test_default_icon() {
		$post_id = self::factory()->post->create();
		set_post_geodata( $post_id, '', static::$geo );
		$this->assertSame( Geo_Data::get_default_icon(), get_post_geodata( $post_id )['icon'] );
	}

	/**
	 * The get, set, and delete short-circuit filters work.
	 */
	public function test_short_circuit_filters() {
		$post_id = self::factory()->post->create();

		$filter = function () {
			return 'short-circuited';
		};
		add_filter( 'get_post_geodata', $filter );
		$this->assertSame( 'short-circuited', get_post_geodata( $post_id, 'latitude' ) );
		remove_filter( 'get_post_geodata', $filter );

		add_filter( 'set_post_geodata', $filter );
		$this->assertSame( 'short-circuited', set_post_geodata( $post_id, 'latitude', 1.5 ) );
		remove_filter( 'set_post_geodata', $filter );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_latitude', true ) );

		add_filter( 'delete_post_geodata', $filter );
		$this->assertSame( 'short-circuited', Geo_Data::delete_geodata( 'post', $post_id, 'latitude' ) );
		remove_filter( 'delete_post_geodata', $filter );
	}

	/**
	 * Geo URIs are parsed into properties.
	 */
	public function test_parse_geo_uri() {
		$this->assertSame(
			array(
				'latitude'  => '37.786971',
				'longitude' => '-122.399677',
				'accuracy'  => '35',
			),
			Geo_Data::parse_geo_uri( 'geo:37.786971,-122.399677;u=35' )
		);
		$parsed = Geo_Data::parse_geo_uri( 'geo:37.78,-122.39,100;name=Home%20Base' );
		$this->assertSame( '100', $parsed['altitude'] );
		$this->assertSame( 'Home Base', $parsed['address'] );
		// Anything that isn't a geo URI is returned unchanged.
		$this->assertSame( 'https://example.com', Geo_Data::parse_geo_uri( 'https://example.com' ) );
		$this->assertSame( array( 1 ), Geo_Data::parse_geo_uri( array( 1 ) ) );
	}

	/**
	 * Malformed geo URIs don't raise warnings.
	 */
	public function test_parse_geo_uri_malformed() {
		$this->assertFalse( Geo_Data::parse_geo_uri( 'geo:37.78' ), 'No longitude.' );

		$parsed = Geo_Data::parse_geo_uri( 'geo:37.78,-122.39;crs;;u=35' );
		$this->assertSame( '37.78', $parsed['latitude'] );
		$this->assertSame( '-122.39', $parsed['longitude'] );
		$this->assertSame( '', $parsed['crs'], 'A parameter without a value.' );
		$this->assertSame( '35', $parsed['accuracy'] );

		$parsed = Geo_Data::parse_geo_uri( 'geo:37.78,-122.39;name=a=b' );
		$this->assertSame( 'a=b', $parsed['address'], 'Only the first = separates the value.' );
	}

	/**
	 * Map output is empty without a map provider, and for non-public locations.
	 */
	public function test_get_post_map_without_provider() {
		update_option( 'sloc_map_provider', 'none' );
		$post_id           = self::factory()->post->create();
		$geo               = static::$geo;
		$geo['visibility'] = 'public';
		set_post_geodata( $post_id, '', $geo );
		$this->assertSame( '', get_post_map( $post_id ) );
	}

	/**
	 * The legacy get_simple_location() works with any object.
	 */
	public function test_get_simple_location() {
		$post              = self::factory()->post->create_and_get();
		$geo               = static::$geo;
		$geo['visibility'] = 'public';
		set_post_geodata( $post->ID, '', $geo );
		$this->assertStringContainsString( 'Test Location', get_simple_location( $post ) );
	}
}

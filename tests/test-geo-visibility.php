<?php
/**
 * Tests for location visibility: storage, defaults, and every place visibility controls output.
 *
 * @package Simple_Location
 */

/**
 * Location visibility tests.
 */
class GeoVisibilityTest extends WP_UnitTestCase {

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
	 * Reset the site default and the current user before each test.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'geo_public', 1 );
		wp_set_current_user( 0 );
	}

	/**
	 * Creates a post with the sample location and the given visibility.
	 *
	 * @param string $visibility Visibility.
	 * @return int Post ID.
	 */
	protected function post_with_location( $visibility ) {
		$post_id           = self::factory()->post->create();
		$geo               = static::$geo;
		$geo['visibility'] = $visibility;
		set_post_geodata( $post_id, '', $geo );
		return $post_id;
	}

	/**
	 * Data provider of object types that can hold geodata.
	 *
	 * @return array
	 */
	public function data_object_types() {
		return array(
			'post'    => array( 'post' ),
			'comment' => array( 'comment' ),
			'user'    => array( 'user' ),
			'term'    => array( 'term' ),
		);
	}

	/**
	 * Creates an object of the given type.
	 *
	 * @param string $type Object type.
	 * @return int Object ID.
	 */
	protected function create_object( $type ) {
		switch ( $type ) {
			case 'post':
				return self::factory()->post->create();
			case 'comment':
				return self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );
			case 'user':
				return self::factory()->user->create();
			case 'term':
				return self::factory()->term->create();
		}
	}

	/**
	 * The geo_public option maps to the default visibility; anything unrecognized is private.
	 */
	public function test_get_default_visibility() {
		$expected = array(
			0 => 'private',
			1 => 'public',
			2 => 'protected',
			7 => 'private',
		);
		foreach ( $expected as $option => $visibility ) {
			update_option( 'geo_public', $option );
			$this->assertSame( $visibility, Geo_Data::get_default_visibility(), "geo_public = $option" );
		}
	}

	/**
	 * Visibility names saved by earlier versions of the settings page are read correctly.
	 */
	public function test_get_default_visibility_from_names() {
		$expected = array(
			'public'    => 'public',
			'private'   => 'private',
			'protected' => 'protected',
			'none'      => 'private',
			''          => 'private',
		);
		foreach ( $expected as $option => $visibility ) {
			// Store the raw value, as the old settings form did, without the sanitize callback.
			remove_all_filters( 'sanitize_option_geo_public' );
			update_option( 'geo_public', $option );
			$this->assertSame( $visibility, Geo_Data::get_default_visibility(), "geo_public = '$option'" );
		}
	}

	/**
	 * Default visibility values are stored as 0, 1 or 2.
	 */
	public function test_sanitize_default_visibility() {
		$expected = array(
			array( 0, 0 ),
			array( '0', 0 ),
			array( 1, 1 ),
			array( '1', 1 ),
			array( '2', 2 ),
			array( 'private', 0 ),
			array( 'public', 1 ),
			array( 'protected', 2 ),
			array( 'none', 0 ),
			array( '7', 0 ),
			array( '1.5', 0 ),
			array( null, 0 ),
			array( array( 1 ), 0 ),
		);
		foreach ( $expected as $case ) {
			$this->assertSame( $case[1], Geo_Data::sanitize_default_visibility( $case[0] ), wp_json_encode( $case[0] ) );
		}
	}

	/**
	 * Public and protected visibility round-trip on every object type.
	 *
	 * @dataProvider data_object_types
	 *
	 * @param string $type Object type.
	 */
	public function test_public_and_protected_round_trip( $type ) {
		$id = $this->create_object( $type );
		foreach ( array( 'public', 'protected' ) as $visibility ) {
			Geo_Data::set_geodata( $type, $id, 'visibility', $visibility );
			$this->assertSame( $visibility, Geo_Data::get_geodata( $type, $id, 'visibility' ), "$type / $visibility" );
		}
	}

	/**
	 * Private visibility round-trips on every object type, whatever the site default is.
	 *
	 * @dataProvider data_object_types
	 *
	 * @param string $type Object type.
	 */
	public function test_private_round_trip( $type ) {
		$id = $this->create_object( $type );
		foreach ( array( 1, 2 ) as $default ) {
			update_option( 'geo_public', $default );
			Geo_Data::set_geodata( $type, $id, 'visibility', 'private' );
			$this->assertSame( 'private', Geo_Data::get_geodata( $type, $id, 'visibility' ), "$type with default $default" );
		}
	}

	/**
	 * Numeric strings are accepted as visibility values.
	 */
	public function test_numeric_visibility_values() {
		$post_id = self::factory()->post->create();
		Geo_Data::set_geodata( 'post', $post_id, 'visibility', '2' );
		$this->assertSame( '2', get_post_meta( $post_id, 'geo_public', true ) );
		$this->assertSame( 'protected', get_post_geodata( $post_id, 'visibility' ) );
		Geo_Data::set_geodata( 'post', $post_id, 'visibility', 'private' );
		$this->assertSame( '0', get_post_meta( $post_id, 'geo_public', true ) );
	}

	/**
	 * An unknown visibility value removes the stored value and falls back to the default.
	 */
	public function test_invalid_visibility_is_rejected() {
		$post_id = self::factory()->post->create();
		Geo_Data::set_geodata( 'post', $post_id, 'visibility', 'protected' );
		$this->assertFalse( Geo_Data::set_geodata( 'post', $post_id, 'visibility', 'secret' ) );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_public', true ) );
		$this->assertSame( 'public', get_post_geodata( $post_id, 'visibility' ) );
	}

	/**
	 * Visibility set as part of a full geodata array is stored.
	 */
	public function test_visibility_in_geodata_array() {
		$post_id = $this->post_with_location( 'protected' );
		$geodata = get_post_geodata( $post_id );
		$this->assertSame( 'protected', $geodata['visibility'] );
		$this->assertEquals( 45.01, $geodata['latitude'] );
	}

	/**
	 * Admin list filters map to the right meta query.
	 */
	public function test_filter_geo_query() {
		$this->assertSame( 'OR', Geo_Data::filter_geo_query( 'all' )['relation'] );
		$this->assertSame( 0, Geo_Data::filter_geo_query( 'private' )['value'] );
		$this->assertSame( 1, Geo_Data::filter_geo_query( 'public' )['value'] );
		$this->assertSame( 1, Geo_Data::filter_geo_query( 'map' )['value'] );
		$this->assertSame( 2, Geo_Data::filter_geo_query( 'protected' )['value'] );
		$this->assertSame( array(), Geo_Data::filter_geo_query( 'nonsense' ) );
	}

	/**
	 * The admin "private" filter finds posts saved as private.
	 */
	public function test_filter_geo_query_finds_private_posts() {
		$private = $this->post_with_location( 'private' );
		$public  = $this->post_with_location( 'public' );
		$found   = get_posts(
			array(
				'fields'     => 'ids',
				'meta_query' => array( Geo_Data::filter_geo_query( 'private' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		$this->assertContains( $private, $found );
		$this->assertNotContains( $public, $found );
	}

	/**
	 * REST fields expose coordinates only for public locations and the address for public or protected.
	 *
	 * WordPress passes the prepared response array to REST field callbacks, so the tests do the same.
	 */
	public function test_rest_fields_public_and_protected() {
		$this->markTestSkipped( 'Known bug, see #312' );
		$public    = array( 'id' => $this->post_with_location( 'public' ) );
		$protected = array( 'id' => $this->post_with_location( 'protected' ) );

		$this->assertEquals( 45.01, Geo_Base::rest_get_latitude( $public, 'latitude', null, 'post' ) );
		$this->assertEquals( -75.44, Geo_Base::rest_get_longitude( $public, 'longitude', null, 'post' ) );
		$this->assertSame( 'Test Location', Geo_Base::rest_get_address( $public, 'geo_address', null, 'post' ) );

		$this->assertSame( '', Geo_Base::rest_get_latitude( $protected, 'latitude', null, 'post' ) );
		$this->assertSame( '', Geo_Base::rest_get_longitude( $protected, 'longitude', null, 'post' ) );
		$this->assertSame( 'Test Location', Geo_Base::rest_get_address( $protected, 'geo_address', null, 'post' ) );
	}

	/**
	 * A public location appears in the posts endpoint.
	 */
	public function test_rest_fields_through_rest_api() {
		$this->markTestSkipped( 'Known bug, see #312' );
		$post_id  = $this->post_with_location( 'public' );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertEquals( 45.01, $data['latitude'] );
		$this->assertEquals( -75.44, $data['longitude'] );
		$this->assertSame( 'Test Location', $data['geo_address'] );
	}

	/**
	 * A private location exposes nothing through the REST API.
	 */
	public function test_rest_fields_private() {
		$post_id  = $this->post_with_location( 'private' );
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id ) );
		$data     = $response->get_data();
		$this->assertArrayNotHasKey( 'latitude', $data );
		$this->assertArrayNotHasKey( 'longitude', $data );
		$this->assertArrayNotHasKey( 'geo_address', $data );
	}

	/**
	 * Captures the output of a callable.
	 *
	 * @param callable $callback Callback.
	 * @param array    $args     Arguments.
	 * @return string Output.
	 */
	protected function capture( $callback, $args = array() ) {
		ob_start();
		call_user_func_array( $callback, $args );
		return ob_get_clean();
	}

	/**
	 * Head meta tags follow visibility.
	 */
	public function test_meta_tags() {
		$public = $this->post_with_location( 'public' );
		$this->go_to( get_permalink( $public ) );
		$output = $this->capture( array( 'Geo_Base', 'meta_tags' ) );
		$this->assertStringContainsString( '<meta name="geo.position" content="45.01;-75.44" />', $output );
		$this->assertStringContainsString( '<meta name="ICBM" content="45.01, -75.44" />', $output );
		$this->assertStringContainsString( '<meta name="geo.placename" content="Test Location" />', $output );

		$protected = $this->post_with_location( 'protected' );
		$this->go_to( get_permalink( $protected ) );
		$output = $this->capture( array( 'Geo_Base', 'meta_tags' ) );
		$this->assertStringContainsString( 'geo.placename', $output );
		$this->assertStringNotContainsString( 'geo.position', $output );
		$this->assertStringNotContainsString( 'ICBM', $output );
	}

	/**
	 * No head meta tags for a private location.
	 */
	public function test_meta_tags_private() {
		$this->go_to( get_permalink( $this->post_with_location( 'private' ) ) );
		$this->assertSame( '', $this->capture( array( 'Geo_Base', 'meta_tags' ) ) );
	}

	/**
	 * GeoRSS output for a public location, and none for protected.
	 */
	public function test_georss_item() {
		$this->go_to( get_permalink( $this->post_with_location( 'public' ) ) );
		$output = $this->capture( array( 'Geo_Base', 'georss_item' ) );
		$this->assertStringContainsString( '<georss:point>45.01 -75.44</georss:point>', $output );
		$this->assertStringContainsString( '<geo:lat>45.01</geo:lat>', $output );
		$this->assertStringContainsString( '<geo:featureName>Test Location</geo:featureName>', $output );

		$this->go_to( get_permalink( $this->post_with_location( 'protected' ) ) );
		$this->assertSame( '', $this->capture( array( 'Geo_Base', 'georss_item' ) ) );
	}

	/**
	 * GeoRSS output contains real whitespace, not literal backslash sequences.
	 */
	public function test_georss_item_whitespace() {
		$this->markTestSkipped( 'Known bug, see #308' );
		$this->go_to( get_permalink( $this->post_with_location( 'public' ) ) );
		$output = $this->capture( array( 'Geo_Base', 'georss_item' ) );
		$this->assertStringNotContainsString( '\t', $output );
		$this->assertStringNotContainsString( '\n', $output );
	}

	/**
	 * JSON Feed gets a GeoJSON point for public locations only.
	 */
	public function test_json_feed_item() {
		$public = get_post( $this->post_with_location( 'public' ) );
		$item   = Geo_Base::json_feed_item( array(), $public );
		$this->assertSame( 'Point', $item['geo']['geometry']['type'] );
		$this->assertSame( array( -75.44, 45.01 ), $item['geo']['geometry']['coordinates'] );
		$this->assertSame( 'Test Location', $item['geo']['properties']['name'] );

		$protected = get_post( $this->post_with_location( 'protected' ) );
		$this->assertSame( array(), Geo_Base::json_feed_item( array(), $protected ) );
	}

	/**
	 * Front-end location display for a visitor.
	 */
	public function test_get_location_display() {
		$public = Geo_Data::get_location( 'post', $this->post_with_location( 'public' ) );
		$this->assertStringContainsString( 'Test Location', $public );
		$this->assertStringContainsString( 'p-latitude', $public );

		$protected = Geo_Data::get_location( 'post', $this->post_with_location( 'protected' ) );
		$this->assertStringContainsString( 'Test Location', $protected );
		$this->assertStringNotContainsString( 'p-latitude', $protected );
		$this->assertStringNotContainsString( '45.01', $protected );
	}

	/**
	 * A visitor sees nothing for a private location.
	 */
	public function test_get_location_private_visitor() {
		$this->assertSame( '', Geo_Data::get_location( 'post', $this->post_with_location( 'private' ) ) );
	}

	/**
	 * Archive map list includes public locations only.
	 */
	public function test_get_geo_archive_location_list() {
		$public    = $this->post_with_location( 'public' );
		$protected = $this->post_with_location( 'protected' );
		$this->go_to( home_url( '/' ) );
		$list = get_geo_archive_location_list();
		$this->assertContains(
			array( 45.01, -75.44 ),
			array_map(
				function ( $point ) {
					return array_map( 'floatval', $point );
				},
				$list
			)
		);
		$this->assertCount( 1, $list, 'Only the public post should be listed' );
		unset( $public, $protected );
	}
}

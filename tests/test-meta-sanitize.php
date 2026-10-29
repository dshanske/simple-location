<?php
/**
 * Tests the sanitize callbacks of the geo and venue meta keys (#769).
 *
 * @package Simple_Location
 */

class MetaSanitizeTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		// The test case unregisters every meta key after each test.
		Geo_Data::register_meta();
		Post_Venue::register_meta();
	}

	public function test_meta_sanitize_callbacks_are_callable() {
		foreach ( array( 'geo_address', 'geo_public', 'geo_icon' ) as $key ) {
			foreach ( array( 'post', 'comment', 'user' ) as $type ) {
				$keys = get_registered_meta_keys( $type );
				$this->assertArrayHasKey( $key, $keys, "$type $key is registered." );
				$this->assertTrue( is_callable( $keys[ $key ]['sanitize_callback'] ), "$type $key has a callable sanitizer." );
			}
		}
		$keys = get_registered_meta_keys( 'post', 'venue' );
		$this->assertTrue( is_callable( $keys['venue_url']['sanitize_callback'] ) );
	}

	public function test_geo_public_registered_with_sanitizer() {
		// geo_public used to be registered a second time without one.
		$keys = get_registered_meta_keys( 'post' );
		$this->assertSame( array( 'Geo_Data', 'sanitize_visibility' ), $keys['geo_public']['sanitize_callback'] );
	}

	public function test_address_strips_tags() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'geo_address', ' <script>alert(1)</script><b>Main Street</b> ' );
		$this->assertSame( 'Main Street', get_post_meta( $post_id, 'geo_address', true ) );
	}

	public function test_address_keeps_ampersand() {
		$comment_id = self::factory()->comment->create();
		update_comment_meta( $comment_id, 'geo_address', 'Barnes & Noble' );
		$this->assertSame( 'Barnes & Noble', get_comment_meta( $comment_id, 'geo_address', true ) );
	}

	public function test_address_via_set_post_geodata() {
		$post_id = self::factory()->post->create();
		set_post_geodata( $post_id, 'address', '<i>Ottawa</i>' );
		$this->assertSame( 'Ottawa', get_post_geodata( $post_id, 'address' ) );
	}

	public function test_address_rejects_arrays() {
		$this->assertSame( '', Geo_Data::sanitize_address( array( 'Main Street' ) ) );
	}

	/**
	 * @dataProvider visibility_values
	 */
	public function test_sanitize_visibility( $value, $expected ) {
		$this->assertSame( $expected, Geo_Data::sanitize_visibility( $value ) );
	}

	public function visibility_values() {
		return array(
			'private'        => array( '0', '0' ),
			'public'         => array( '1', '1' ),
			'protected'      => array( '2', '2' ),
			'integer'        => array( 2, '2' ),
			'name'           => array( 'protected', '2' ),
			'empty'          => array( '', '' ),
			'null'           => array( null, '' ),
			'unknown number' => array( '5', '0' ),
			'unknown word'   => array( 'abc', '0' ),
		);
	}

	public function test_unknown_visibility_is_private() {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'geo_public', 'abc' );
		$this->assertSame( '0', get_user_meta( $user_id, 'geo_public', true ) );
		$this->assertSame( 'private', get_user_geodata( $user_id, 'visibility' ) );
	}

	public function test_set_visibility_still_stored() {
		$post_id = self::factory()->post->create();
		set_post_geodata( $post_id, 'visibility', 'protected' );
		$this->assertSame( '2', get_post_meta( $post_id, 'geo_public', true ) );
	}

	/**
	 * @dataProvider icon_values
	 */
	public function test_sanitize_icon( $value, $expected ) {
		$this->assertSame( $expected, Geo_Data::sanitize_icon( $value ) );
	}

	public function icon_values() {
		return array(
			'listed'          => array( 'fa-plane', 'fa-plane' ),
			'none'            => array( 'none', 'none' ),
			'svg file only'   => array( 'aerlingus', 'aerlingus' ),
			'path'            => array( '../../wp-config', '' ),
			'path to listed'  => array( 'dir/fa-plane', 'fa-plane' ),
			'markup'          => array( '"><script>', '' ),
			'unknown'         => array( 'fa-not-an-icon', '' ),
			'empty'           => array( '', '' ),
			'not a string'    => array( array( 'fa-plane' ), '' ),
		);
	}

	public function test_icon_meta_is_sanitized() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'geo_icon', '"><script>' );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_icon', true ) );
		update_post_meta( $post_id, 'geo_icon', 'fa-car' );
		$this->assertSame( 'fa-car', get_post_meta( $post_id, 'geo_icon', true ) );
	}

	/**
	 * @dataProvider url_values
	 */
	public function test_sanitize_venue_url( $value, $expected ) {
		$this->assertSame( $expected, Post_Venue::sanitize_url( $value ) );
	}

	public function url_values() {
		return array(
			'https'      => array( 'https://cafe.example.com/', 'https://cafe.example.com/' ),
			'http'       => array( ' http://cafe.example.com/menu ', 'http://cafe.example.com/menu' ),
			'javascript' => array( 'javascript:alert(1)', '' ),
			'word'       => array( 'cafe', '' ),
			'empty'      => array( '', '' ),
			'not string' => array( array( 'https://cafe.example.com/' ), '' ),
		);
	}

	public function test_venue_url_meta_is_sanitized() {
		$venue_id = self::factory()->post->create( array( 'post_type' => 'venue' ) );
		update_post_meta( $venue_id, 'venue_url', 'javascript:alert(1)' );
		$this->assertSame( '', get_post_meta( $venue_id, 'venue_url', true ) );
		update_post_meta( $venue_id, 'venue_url', 'https://cafe.example.com/' );
		$this->assertSame( 'https://cafe.example.com/', get_post_meta( $venue_id, 'venue_url', true ) );
	}

	public function test_venue_alternate_unchanged() {
		$this->assertSame(
			array( 'https://a.example.com/', 'http://b.example.com/' ),
			Post_Venue::sanitize_alternate( "https://a.example.com/\nhttp://b.example.com/\ncafe\nhttps://a.example.com/" )
		);
	}
}

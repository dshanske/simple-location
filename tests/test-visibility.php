<?php

class VisibilityTest extends WP_UnitTestCase {
	public function test_set_and_get_post_id_visibility() {
		$post_id = self::factory()->post->create();
		WP_Geo_Data::set_visibility( 'post', $post_id, 'private' );
		$this->assertEquals( 'private', WP_Geo_Data::get_visibility( 'post', $post_id ) );
	}
	public function test_set_and_get_comment_visibility() {
		$comment = self::factory()->comment->create();
		WP_Geo_Data::set_visibility( 'comment', $comment, 'private' );
		$this->assertEquals( 'private', WP_Geo_Data::get_visibility( 'comment', $comment ) );
	}

	public function test_get_default_visibility() {
		$option = delete_option( 'geo_public' );
		$get = WP_Geo_Data::get_visibility();
		$this->assertEquals( 'public', $get );
	}

	public function test_get_visibility_option() {
		$option = update_option( 'geo_public', 2 );
		$get = WP_Geo_Data::get_visibility();
		$this->assertEquals( 'protected', $get );
	}

}


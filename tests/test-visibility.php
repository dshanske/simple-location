<?php

class VisibilityTest extends WP_UnitTestCase {
	public function test_set_and_get_post_visibility() {
		$post = self::factory()->post->create();
		WP_Geo_Data::set_visibility( 'post', $post, 'private' );
		$this->assertEquals( 'private', WP_Geo_Data::get_visibility( 'post', $post ) );
	}
	public function test_set_and_get_comment_visibility() {
		$comment = self::factory()->comment->create();
		WP_Geo_Data::set_visibility( 'comment', $comment, 'private' );
		$this->assertEquals( 'private', WP_Geo_Data::get_visibility( 'comment', $comment ) );
	}
}


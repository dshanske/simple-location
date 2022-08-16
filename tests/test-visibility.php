<?php

class VisibilityTest extends WP_UnitTestCase {
	public function test_set_and_get_post_id_visibility() {
		$post_id = $this->factory()->post->create();
		set_post_geo_visibility( $post_id, 'protected' );
		$this->assertEquals( 'protected', get_post_geo_visibility( $post_id ) );
	}
	public function test_set_and_get_comment_visibility() {
		$comment = $this->factory()->comment->create();
		set_post_geo_visibility( $comment, 'private' );
		$this->assertEquals( 'private', get_comment_geo_visibility( $comment ) );
	}

	public function test_set_and_get_user_visibility() {
		$user_id = $this->factory()->user->create();
		set_user_geo_visibility( $user_id, 'private' );
		$this->assertEquals( 'private', get_user_geo_visibility( $user_id ) );
	}

	public function test_get_default_visibility() {
		$option = delete_option( 'geo_public' );
		$get = get_post_geo_visibility( );
		$this->assertEquals( 'public', $get );
	}

	public function test_get_visibility_option() {
		$option = update_option( 'geo_public', 2 );
		$get = get_post_geo_visibility();
		$this->assertEquals( 'protected', $get );
	}

}


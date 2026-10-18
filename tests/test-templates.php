<?php
/**
 * Tests for the metabox templates in templates/.
 *
 * The templates are included directly, because load_template() loads each one only once per request;
 * the post or comment is set as load_template() would set it.
 *
 * @package Simple_Location
 */

/**
 * Metabox template tests.
 */
class TemplatesTest extends WP_UnitTestCase {

	/**
	 * Reset the screen and globals.
	 */
	public function tear_down() {
		unset( $GLOBALS['comment'] );
		wp_reset_postdata();
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * Renders a template.
	 *
	 * @param string $name Template file name.
	 * @return string Output.
	 */
	protected function render( $name ) {
		ob_start();
		include dirname( __DIR__ ) . '/templates/' . $name;
		return ob_get_clean();
	}

	/**
	 * Sets the current post, as the post editor does.
	 *
	 * @param int $post_id Post ID.
	 */
	protected function edit_post( $post_id ) {
		set_current_screen( 'post' );
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );
	}

	/**
	 * Sets the current comment, as the comment editor does.
	 *
	 * @param int $comment_id Comment ID.
	 */
	protected function edit_comment( $comment_id ) {
		set_current_screen( 'comment' );
		$GLOBALS['comment'] = get_comment( $comment_id );
	}

	/**
	 * The timezone metabox shows the post's timezone.
	 */
	public function test_timezone_metabox_post() {
		$post_id = self::factory()->post->create();
		set_post_geodata( $post_id, 'timezone', 'Europe/Paris' );
		$this->edit_post( $post_id );
		$this->assertStringContainsString( 'value="Europe/Paris"', $this->render( 'timezone-metabox.php' ) );
	}

	/**
	 * A timezone in the old _timezone meta is shown and moved to the geo data.
	 */
	public function test_timezone_metabox_legacy_meta() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_timezone', 'Asia/Kolkata' );
		$this->edit_post( $post_id );
		$this->assertStringContainsString( 'value="Asia/Kolkata"', $this->render( 'timezone-metabox.php' ) );
		$this->assertSame( 'Asia/Kolkata', get_post_geodata( $post_id, 'timezone' ) );
		$this->assertSame( '', get_post_meta( $post_id, '_timezone', true ) );
	}

	/**
	 * The timezone metabox shows the comment's timezone on the comment screen.
	 */
	public function test_timezone_metabox_comment() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );
		set_comment_geodata( $comment_id, 'timezone', 'Asia/Tokyo' );
		$this->edit_comment( $comment_id );
		$this->assertStringContainsString( 'value="Asia/Tokyo"', $this->render( 'timezone-metabox.php' ) );
	}

	/**
	 * The location metabox shows the comment's location on the comment screen.
	 */
	public function test_location_metabox_comment() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );
		set_comment_geodata(
			$comment_id,
			'',
			array(
				'latitude'  => 39.9524,
				'longitude' => -75.1636,
			)
		);
		$this->edit_comment( $comment_id );
		$output = $this->render( 'loc-metabox.php' );
		$this->assertStringContainsString( 'name="latitude"', $output );
		$this->assertStringContainsString( '39.9524', $output );
	}

	/**
	 * Without a stored visibility, the location metabox selects the site's default visibility.
	 */
	public function test_location_metabox_default_visibility() {
		foreach ( array(
			0 => 'private',
			1 => 'public',
			2 => 'protected',
		) as $option => $visibility ) {
			update_option( 'geo_public', $option );
			$this->edit_post( self::factory()->post->create() );
			$output = $this->render( 'loc-metabox.php' );
			// ClassicPress writes a bare selected attribute, WordPress selected='selected'.
			$this->assertMatchesRegularExpression( '/<option value="' . $visibility . '"\s+selected[=>\s]/', $output, $visibility );
			$this->assertStringContainsString( '<span id="location-visibility-label">' . Geo_Base::geo_public()[ $visibility ] . '</span>', $output );
			$this->assertStringContainsString( 'id="hidden_location_visibility" value="' . $visibility . '"', $output );
		}
	}

	/**
	 * The weather metabox shows the comment's weather on the comment screen.
	 */
	public function test_weather_metabox_comment() {
		update_option( 'sloc_measurements', 'metric' );
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );
		set_comment_weatherdata( $comment_id, 'temperature', 21.5 );
		$this->edit_comment( $comment_id );
		$this->assertStringContainsString( '21.5', $this->render( 'weather-metabox.php' ) );
	}
}

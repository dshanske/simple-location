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
	 * Without an address, the location metabox's label says None.
	 */
	public function test_location_metabox_without_address() {
		$post_id = self::factory()->post->create();
		set_post_geodata(
			$post_id,
			'',
			array(
				'latitude'   => 39.9524,
				'longitude'  => -75.1636,
				'visibility' => 'public',
			)
		);
		$this->edit_post( $post_id );
		$this->assertStringContainsString( '<span id="location-label">None</span>', $this->render( 'loc-metabox.php' ) );
	}

	/**
	 * Asserts that each number field's value fits its step, as the browser requires to save the form.
	 *
	 * @param string $html Form.
	 */
	protected function assertValuesFitSteps( $html ) {
		preg_match_all( '/<input[^>]*type="number"[^>]*>/', $html, $inputs );
		$this->assertNotEmpty( $inputs[0] );
		foreach ( $inputs[0] as $input ) {
			preg_match( '/value="([^"]*)"/', $input, $value );
			preg_match( '/step="([^"]*)"/', $input, $step );
			preg_match( '/name="([^"]*)"/', $input, $name );
			if ( empty( $value[1] ) ) {
				continue;
			}
			$step     = (float) ( $step[1] ?? 1 );
			$multiple = (float) $value[1] / $step;
			$this->assertEqualsWithDelta( round( $multiple ), $multiple, 1e-6, $name[1] . ' = ' . $value[1] . ' fits step ' . $step );
		}
	}

	/**
	 * Weather and altitude are shown rounded to what their fields allow, so the post can be saved (#741).
	 */
	public function test_metabox_values_fit_their_fields() {
		update_option( 'sloc_measurements', 'metric' );
		$post_id = self::factory()->post->create();
		set_post_weatherdata(
			$post_id,
			'',
			array(
				'temperature' => 12.34567,
				'humidity'    => 55.555,
				'winddegree'  => 247.567,
				'windspeed'   => 3.14159,
				'pressure'    => 1013.256,
				'visibility'  => 9.87654,
				'cloudiness'  => 33.3333,
				'rain'        => 0.12345,
				'snow'        => 1.005,
			)
		);
		set_post_geodata(
			$post_id,
			'',
			array(
				'latitude'  => 39.9524,
				'longitude' => -75.1636,
				'altitude'  => 12.3456,
			)
		);
		$this->edit_post( $post_id );
		$weather = $this->render( 'weather-metabox.php' );
		$this->assertValuesFitSteps( $weather );
		// Each at the precision weather stations usually report.
		$this->assertStringContainsString( 'name="winddegree" id="winddegree" step="1" value="248"', $weather );
		$this->assertStringContainsString( 'name="cloudiness" id="cloudiness" step="1" value="33"', $weather );
		$this->assertStringContainsString( 'name="humidity" id="humidity" step="1" value="56"', $weather );
		$this->assertStringContainsString( 'step="0.1" id="temperature" value="12.3"', $weather );
		$this->assertStringContainsString( 'step="0.1" value="1013.3"', $weather );
		$this->assertStringContainsString( 'name="weather_exact[humidity]"', $weather );

		update_option( 'sloc_measurements', 'imperial' );
		$imperial = $this->render( 'weather-metabox.php' );
		$this->assertValuesFitSteps( $imperial );
		$this->assertStringContainsString( 'step="0.01" value="29.92"', $imperial, 'Pressure in inHg.' );
		$this->assertSame( '0.01', Weather_Provider::field_step( 'dewpoint' ), 'Two places for fields without a precision.' );
		$this->assertSame( 2, Weather_Provider::field_precision( 'pressure', true ) );

		$location = $this->render( 'loc-metabox.php' );
		$this->assertValuesFitSteps( $location );
		$this->assertStringContainsString( 'value="12.35"', $location );
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

	/**
	 * The venue editor offers the type of the venue's category, as new or existing (#729).
	 */
	public function test_location_metabox_suggested_type() {
		$venue_id = self::factory()->post->create( array( 'post_type' => 'venue' ) );
		$this->edit_post( $venue_id );
		$this->assertStringNotContainsString( 'venue_type_suggestion', $this->render( 'loc-metabox.php' ) );

		update_post_meta( $venue_id, 'venue_category', 'food' );
		$this->assertStringContainsString( 'Add the new type Food', $this->render( 'loc-metabox.php' ) );
		self::factory()->term->create(
			array(
				'taxonomy' => 'venue_type',
				'name'     => 'Food',
			)
		);
		$output = $this->render( 'loc-metabox.php' );
		$this->assertStringContainsString( 'name="venue_type_suggestion"', $output );
		$this->assertStringContainsString( 'Add the type Food', $output );
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
}

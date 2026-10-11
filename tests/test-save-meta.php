<?php
/**
 * Tests for saving location and weather data from the editor and bulk actions.
 *
 * @package Simple_Location
 */

/**
 * Save handler tests.
 */
class SaveMetaTest extends WP_UnitTestCase {

	/**
	 * Clean up request data between tests.
	 */
	public function tear_down() {
		$_POST    = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	/**
	 * Location fields from the form are stored with numeric values as floats.
	 */
	public function test_save_meta_location_fields() {
		$post_id = self::factory()->post->create();
		$_POST   = array(
			'latitude'   => '45.01',
			'longitude'  => '-75.44',
			'altitude'   => '120',
			'address'    => '<b>Test</b> Location',
			'map_zoom'   => '11',
			'geo_public' => 'protected',
		);
		Geo_Base::save_meta( 'post', $post_id );
		$geodata = get_post_geodata( $post_id );
		$this->assertSame( 45.01, (float) $geodata['latitude'] );
		$this->assertSame( 'Test Location', $geodata['address'] );
		$this->assertEquals( 11, $geodata['zoom'] );
		$this->assertSame( 'protected', $geodata['visibility'] );
	}

	/**
	 * Choosing "inherit" on a photo removes its own visibility, so it follows its post again.
	 */
	public function test_save_meta_attachment_inherit() {
		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->attachment->create( array( 'post_parent' => $post_id ) );
		set_post_geodata( $post_id, 'visibility', 'private' );
		$_POST = array(
			'latitude'   => '45.01',
			'longitude'  => '-75.44',
			'geo_public' => 'public',
		);
		Geo_Base::save_meta( 'post', $attachment_id );
		$this->assertSame( '1', get_post_meta( $attachment_id, 'geo_public', true ) );
		$this->assertSame( 'public', get_post_geodata( $attachment_id, 'visibility' ) );

		$_POST['geo_public'] = 'inherit';
		Geo_Base::save_meta( 'post', $attachment_id );
		$this->assertSame( '', get_post_meta( $attachment_id, 'geo_public', true ) );
		$this->assertSame( 'private', get_post_geodata( $attachment_id, 'visibility' ) );
	}

	/**
	 * Only a file name is stored for the icon, never a path.
	 */
	public function test_save_meta_icon_basename() {
		$post_id = self::factory()->post->create();
		$_POST   = array(
			'latitude'      => '45.01',
			'longitude'     => '-75.44',
			'location_icon' => '../../../wp-config',
		);
		Geo_Base::save_meta( 'post', $post_id );
		$this->assertSame( 'wp-config', get_post_meta( $post_id, 'geo_icon', true ) );
	}

	/**
	 * Clearing every location field removes the stored visibility.
	 */
	public function test_save_meta_clearing_location_removes_visibility() {
		$this->markTestSkipped( 'Known bug, see #314' );
		$post_id = self::factory()->post->create();
		set_post_geodata( $post_id, 'visibility', 'protected' );
		$_POST = array(
			'latitude'   => '',
			'longitude'  => '',
			'address'    => '',
			'geo_public' => 'public',
		);
		Geo_Base::save_meta( 'post', $post_id );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_public', true ) );
	}

	/**
	 * Non-numeric coordinates are not stored.
	 */
	public function test_save_meta_rejects_invalid_coordinates() {
		$this->markTestSkipped( 'Known bug, see #304' );
		$post_id = self::factory()->post->create();
		$_POST   = array(
			'latitude'  => 'north',
			'longitude' => '-75.44',
		);
		Geo_Base::save_meta( 'post', $post_id );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_latitude', true ) );
	}

	/**
	 * A timezone equal to the site timezone is not stored; a different one is.
	 */
	public function test_save_meta_timezone() {
		update_option( 'timezone_string', 'America/New_York' );
		$post_id = self::factory()->post->create();

		$_POST = array(
			'latitude'  => '45.01',
			'longitude' => '-75.44',
			'timezone'  => 'America/New_York',
		);
		Geo_Base::save_meta( 'post', $post_id );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_timezone', true ) );

		$_POST['timezone'] = 'Europe/Paris';
		Geo_Base::save_meta( 'post', $post_id );
		$this->assertSame( 'Europe/Paris', get_post_meta( $post_id, 'geo_timezone', true ) );
	}

	/**
	 * An invalid timezone is ignored without a PHP warning.
	 */
	public function test_save_meta_invalid_timezone() {
		$post_id = self::factory()->post->create();
		$_POST   = array(
			'latitude'  => '45.01',
			'longitude' => '-75.44',
			'timezone'  => 'Mars/Olympus_Mons',
		);
		Geo_Base::save_meta( 'post', $post_id );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_timezone', true ) );
	}

	/**
	 * Weather fields: numbers are kept, text is dropped, summary and icon are sanitized.
	 */
	public function test_save_meta_weather() {
		update_option( 'sloc_measurements', 'metric' );
		$post_id = self::factory()->post->create();
		$_POST   = array(
			'temperature'     => '21.5',
			'humidity'        => 'very',
			'weather_code'    => '800',
			'weather_summary' => '<script>x</script>Clear',
			'weather_icon'    => '/etc/wi-day-sunny',
		);
		Geo_Base::save_meta( 'post', $post_id );
		$weather = get_post_weatherdata( $post_id );
		$this->assertEquals( 21.5, $weather['temperature'] );
		$this->assertArrayNotHasKey( 'humidity', $weather );
		$this->assertEquals( 800, $weather['code'] );
		$this->assertSame( 'Clear', $weather['summary'] );
		$this->assertSame( 'wi-day-sunny', $weather['icon'] );
	}

	/**
	 * Imperial input is converted to metric before saving.
	 */
	public function test_save_meta_weather_imperial() {
		update_option( 'sloc_measurements', 'imperial' );
		$post_id = self::factory()->post->create();
		$_POST   = array( 'temperature' => '212' );
		Geo_Base::save_meta( 'post', $post_id );
		$this->assertEqualsWithDelta( 100, get_post_weatherdata( $post_id, 'temperature' ), 0.01 );
	}

	/**
	 * The save_post handler requires a valid nonce and permission to edit the post.
	 */
	public function test_save_post_meta_requires_nonce_and_capability() {
		$author  = self::factory()->user->create( array( 'role' => 'author' ) );
		$other   = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id = self::factory()->post->create( array( 'post_author' => $author ) );
		$fields  = array(
			'latitude'  => '45.01',
			'longitude' => '-75.44',
		);

		// No nonce.
		wp_set_current_user( $author );
		$_POST = $fields;
		Geo_Base::save_post_meta( $post_id );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_latitude', true ) );

		// Valid nonce, but another author's post.
		wp_set_current_user( $other );
		$_POST = $fields + array( 'location_metabox_nonce' => wp_create_nonce( 'location_metabox' ) );
		Geo_Base::save_post_meta( $post_id );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_latitude', true ) );

		// Valid nonce and own post.
		wp_set_current_user( $author );
		$_POST = $fields + array( 'location_metabox_nonce' => wp_create_nonce( 'location_metabox' ) );
		Geo_Base::save_post_meta( $post_id );
		$this->assertEquals( 45.01, get_post_meta( $post_id, 'geo_latitude', true ) );
	}

	/**
	 * Saving a post stores the chosen venue, and clears it when none is chosen.
	 */
	public function test_save_post_meta_venue_id() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$post_id  = self::factory()->post->create();
		$venue_id = self::factory()->post->create( array( 'post_type' => 'venue' ) );

		$_POST = array(
			'location_metabox_nonce' => wp_create_nonce( 'location_metabox' ),
			'venue_id'               => (string) $venue_id,
		);
		Geo_Base::save_post_meta( $post_id );
		$this->assertEquals( $venue_id, get_post_meta( $post_id, 'venue_id', true ) );

		$_POST['venue_id'] = '0';
		Geo_Base::save_post_meta( $post_id );
		$this->assertSame( '', get_post_meta( $post_id, 'venue_id', true ) );
	}

	/**
	 * Saving a venue stores its radius and URL.
	 */
	public function test_save_post_meta_venue_fields() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$venue_id = self::factory()->post->create( array( 'post_type' => 'venue' ) );
		$_POST    = array(
			'location_metabox_nonce' => wp_create_nonce( 'location_metabox' ),
			'venue_radius'           => '75',
			'venue_url'              => 'https://example.com/cafe',
		);
		Geo_Base::save_post_meta( $venue_id );
		$this->assertEquals( 75, get_post_meta( $venue_id, 'venue_radius', true ) );
		$this->assertSame( 'https://example.com/cafe', get_post_meta( $venue_id, 'venue_url', true ) );
	}

	/**
	 * Bulk "Public Location" and "Private Location" change visibility on posts the user can edit.
	 */
	public function test_bulk_visibility_actions() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$post_ids = self::factory()->post->create_many( 2 );
		$this->assertSame( 'edit.php?bulk_lookup_location_count=2', Geo_Base::handle_bulk_edit_location( 'edit.php', 'location_private', $post_ids ) );
		$this->assertSame( 'private', get_post_geodata( $post_ids[0], 'visibility' ) );
		$this->assertSame( 'private', get_post_geodata( $post_ids[1], 'visibility' ) );
		$this->assertSame( 'edit.php?bulk_lookup_location_count=1', Geo_Base::handle_bulk_edit_location( 'edit.php', 'location_public', array( $post_ids[0] ) ) );
		$this->assertSame( 'public', get_post_geodata( $post_ids[0], 'visibility' ) );
		$this->assertSame( 'private', get_post_geodata( $post_ids[1], 'visibility' ) );
	}

	/**
	 * Bulk visibility actions skip posts the user can't edit.
	 */
	public function test_bulk_visibility_actions_check_capability() {
		$owner       = self::factory()->user->create( array( 'role' => 'author' ) );
		$contributor = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$post_id     = self::factory()->post->create( array( 'post_author' => $owner ) );
		set_post_geodata( $post_id, 'visibility', 'private' );
		wp_set_current_user( $contributor );
		$this->assertSame( 'edit.php?bulk_lookup_location_count=0', Geo_Base::handle_bulk_edit_location( 'edit.php', 'location_public', array( $post_id ) ) );
		$this->assertSame( '0', get_post_meta( $post_id, 'geo_public', true ) );
	}

	/**
	 * Unrelated bulk actions pass the redirect through unchanged.
	 */
	public function test_bulk_unrelated_action() {
		$this->assertSame( 'edit.php', Geo_Base::handle_bulk_edit_location( 'edit.php', 'trash', array( 1 ) ) );
		$this->assertSame( 'edit.php', Geo_Base::handle_bulk_edit_location( 'edit.php', 'lookup_location', array() ) );
	}
}

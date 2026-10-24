<?php
/**
 * Tests for venues (the venue post type) in includes/class-post-venue.php.
 *
 * @package Simple_Location
 */

/**
 * Venue tests.
 */
class PostVenueTest extends WP_UnitTestCase {

	/**
	 * Start logged out, with a public default visibility.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'geo_public', 1 );
		update_option( 'sloc_country', '' );
		wp_set_current_user( 0 );
	}

	/**
	 * Address fields for a venue.
	 *
	 * @return array
	 */
	protected function address() {
		return array(
			'country-name' => 'United States',
			'country-code' => 'US',
			'region'       => 'New York',
			'region-code'  => 'NY',
			'locality'     => 'New York',
		);
	}

	/**
	 * Reset request data and the admin screen.
	 */
	public function tear_down() {
		$_GET     = array();
		$_REQUEST = array();
		set_current_screen( 'front' );
		// main_query() replaces the main query; put back one shared with $wp_query, as WordPress sets it up.
		$GLOBALS['wp_the_query'] = new WP_Query();
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
		parent::tear_down();
	}

	/**
	 * Captures the output of a callback.
	 *
	 * @param callable $callback Callback.
	 * @param mixed    ...$args  Arguments.
	 * @return string
	 */
	protected function capture( $callback, ...$args ) {
		ob_start();
		call_user_func_array( $callback, $args );
		return ob_get_clean();
	}

	/**
	 * Creates a venue at a location.
	 *
	 * @param float $lat   Latitude.
	 * @param float $lng   Longitude.
	 * @param array $args  Post arguments.
	 * @return int Venue ID.
	 */
	protected function venue( $lat = 40.7128, $lng = -74.006, $args = array() ) {
		$venue_id = self::factory()->post->create(
			array_merge(
				array(
					'post_type'  => 'venue',
					'post_title' => 'Corner Cafe',
				),
				$args
			)
		);
		set_post_geodata(
			$venue_id,
			'',
			array(
				'latitude'   => $lat,
				'longitude'  => $lng,
				'visibility' => 'public',
			)
		);
		return $venue_id;
	}

	/**
	 * Creates posts checked in at a venue.
	 *
	 * @param int $venue_id Venue ID.
	 * @param int $count    Number of posts.
	 * @return int[] Post IDs.
	 */
	protected function checkins( $venue_id, $count = 1 ) {
		$ids = self::factory()->post->create_many( $count );
		foreach ( $ids as $post_id ) {
			Post_Venue::set_post_venue( $post_id, $venue_id );
		}
		return $ids;
	}

	/**
	 * The venue post type, venue type taxonomy and venue meta are registered.
	 */
	public function test_register() {
		$post_type = get_post_type_object( 'venue' );
		$this->assertInstanceOf( WP_Post_Type::class, $post_type );
		$this->assertTrue( $post_type->public );
		$this->assertTrue( $post_type->hierarchical );
		$this->assertTrue( $post_type->show_in_rest );
		$this->assertTrue( post_type_supports( 'venue', 'geo-location' ) );
		$this->assertTrue( taxonomy_exists( 'venue_type' ) );

		// The test framework unregisters meta keys after each test, so register again.
		Post_Venue::register_meta();
		$this->assertTrue( registered_meta_key_exists( 'post', 'venue_url', 'venue' ) );
		$this->assertTrue( registered_meta_key_exists( 'post', 'venue_alternate', 'venue' ) );
		$this->assertTrue( registered_meta_key_exists( 'post', 'venue_id', 'post' ) );
		$this->assertTrue( registered_meta_key_exists( 'post', 'venue_id', 'attachment' ) );
		$this->assertSame( 50, get_registered_meta_keys( 'post', 'venue' )['venue_radius']['default'] );
		$this->assertTrue( registered_meta_key_exists( 'post', 'venue_post_visibility', 'venue' ) );
	}

	/**
	 * A venue's visibility for posts made there is one of the three visibilities, or none (#143).
	 */
	public function test_post_visibility() {
		$venue_id = self::factory()->post->create( array( 'post_type' => 'venue' ) );
		$this->assertSame( '', Post_Venue::get_post_visibility( $venue_id ) );
		foreach ( array( 'public', 'protected', 'private' ) as $visibility ) {
			update_post_meta( $venue_id, 'venue_post_visibility', $visibility );
			$this->assertSame( $visibility, Post_Venue::get_post_visibility( $venue_id ) );
		}
		update_post_meta( $venue_id, 'venue_post_visibility', 'secret' );
		$this->assertSame( '', Post_Venue::get_post_visibility( $venue_id ) );
		$this->assertSame( '', Post_Venue::get_post_visibility( false ) );
		$this->assertSame( '', Post_Venue::get_post_visibility( 0 ) );
		$this->assertSame( '', Post_Venue::sanitize_post_visibility( array( 'private' ) ) );
	}

	/**
	 * A location found in the background at a venue takes the venue's visibility for posts made there.
	 */
	public function test_post_visibility_background_lookup() {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		// set_user_geodata() doesn't save (#305), so store the location directly.
		Geo_Data::set_geodata(
			'user',
			$author,
			'',
			array(
				'latitude'  => 39.9524,
				'longitude' => -75.1636,
			)
		);
		update_option( 'sloc_geolocation_provider', 'dummy' );
		update_option( 'sloc_geo_provider', 'sloc_test' );
		Geo_Provider_Sloc_Test::reset();
		Geo_Provider_Sloc_Test::$reverse = array(
			'display-name' => 'Home',
			'locality'     => 'Philadelphia',
			'region'       => 'Pennsylvania',
			'region-code'  => 'PA',
			'country-name' => 'United States',
			'country-code' => 'US',
		);
		$venue_id                        = self::factory()->post->create( array( 'post_type' => 'venue' ) );
		update_post_meta( $venue_id, 'venue_post_visibility', 'private' );
		$at_venue = function () use ( $venue_id ) {
			return $venue_id;
		};
		add_filter( 'pre_at_venue', $at_venue );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$post_id = self::factory()->post->create( array( 'post_author' => $author ) );
		Geo_Data::bulk_edit_lookup_location( $post_id );
		remove_filter( 'pre_at_venue', $at_venue );

		$this->assertEquals( $venue_id, get_post_meta( $post_id, 'venue_id', true ) );
		$this->assertSame( 'private', get_post_geodata( $post_id, 'visibility' ) );
	}

	/**
	 * The admin list hooks are registered.
	 */
	public function test_hooks_registered() {
		$this->assertSame( 10, has_filter( 'manage_venue_posts_columns', array( 'Post_Venue', 'add_checkins_admin_column' ) ) );
		$this->assertSame( 10, has_filter( 'bulk_actions-edit-venue', array( 'Post_Venue', 'register_bulk_edit' ) ) );
		$this->assertSame( 10, has_action( 'pre_get_posts', array( 'Post_Venue', 'post__in' ) ) );
		$this->assertSame( 12, has_action( 'restrict_manage_posts', array( 'Post_Venue', 'venue_types_dropdown' ) ) );
	}

	/**
	 * Makes a query the main query, as edit.php's list query is.
	 *
	 * @return WP_Query
	 */
	protected function main_query() {
		$GLOBALS['wp_the_query'] = new WP_Query();
		return $GLOBALS['wp_the_query'];
	}

	/**
	 * In the admin, ?post__in limits the post list to those IDs.
	 */
	public function test_post__in() {
		$query = $this->main_query();
		Post_Venue::post__in( $query );
		$this->assertSame( '', $query->get( 'post__in' ), 'Not in the admin.' );

		set_current_screen( 'edit-post' );
		Post_Venue::post__in( $query );
		$this->assertSame( '', $query->get( 'post__in' ), 'No parameter.' );

		$_GET['post__in'] = array( '3', '5x' );
		Post_Venue::post__in( $query );
		$this->assertSame( array( 3, 5 ), $query->get( 'post__in' ) );
	}

	/**
	 * A single ID or a comma-separated list works too; nothing usable leaves the query alone.
	 */
	public function test_post__in_string() {
		set_current_screen( 'edit-post' );
		$query            = $this->main_query();
		$_GET['post__in'] = '5';
		Post_Venue::post__in( $query );
		$this->assertSame( array( 5 ), $query->get( 'post__in' ) );

		$_GET['post__in'] = '3,7';
		Post_Venue::post__in( $query );
		$this->assertSame( array( 3, 7 ), $query->get( 'post__in' ) );

		$query            = $this->main_query();
		$_GET['post__in'] = 'none';
		Post_Venue::post__in( $query );
		$this->assertSame( '', $query->get( 'post__in' ) );
	}

	/**
	 * Other queries on the page, and other admin screens, are left alone.
	 */
	public function test_post__in_only_main_list_query() {
		$_GET['post__in'] = '5';
		set_current_screen( 'edit-post' );
		$main = $this->main_query();

		// A secondary query, such as one a widget or another plugin runs.
		$query = new WP_Query();
		Post_Venue::post__in( $query );
		$this->assertSame( '', $query->get( 'post__in' ) );

		$posts = self::factory()->post->create_many( 2 );
		$found = get_posts(
			array(
				'post__in'         => $posts,
				'suppress_filters' => false,
			)
		);
		$this->assertCount( 2, $found );

		// The main query on a screen that isn't a post list.
		set_current_screen( 'dashboard' );
		Post_Venue::post__in( $main );
		$this->assertSame( '', $main->get( 'post__in' ) );

		set_current_screen( 'edit-venue' );
		Post_Venue::post__in( $main );
		$this->assertSame( array( 5 ), $main->get( 'post__in' ) );
	}

	/**
	 * The venue list has a Checkins column linking to the checked-in posts, and no Date column.
	 */
	public function test_admin_columns() {
		$columns = Post_Venue::add_checkins_admin_column(
			Post_Venue::remove_date_admin_column(
				array(
					'title' => 'Title',
					'date'  => 'Date',
				)
			)
		);
		$this->assertSame( array( 'title', 'checkins' ), array_keys( $columns ) );

		$venue_id = $this->venue();
		$ids      = $this->checkins( $venue_id, 2 );
		$output   = $this->capture( array( 'Post_Venue', 'manage_checkins_admin_column' ), 'checkins', $venue_id );
		$this->assertStringContainsString( '>2</a>', $output );
		foreach ( $ids as $post_id ) {
			$this->assertStringContainsString( (string) $post_id, $output );
		}
		$this->assertStringContainsString( '>0</a>', $this->capture( array( 'Post_Venue', 'manage_checkins_admin_column' ), 'checkins', $this->venue() ) );
		$this->assertSame( '', $this->capture( array( 'Post_Venue', 'manage_checkins_admin_column' ), 'title', $venue_id ) );
	}

	/**
	 * The Checkins column shows "None" for a row that isn't a venue.
	 */
	public function test_admin_column_not_a_venue() {
		$this->assertSame( 'None', $this->capture( array( 'Post_Venue', 'manage_checkins_admin_column' ), 'checkins', self::factory()->post->create() ) );
	}

	/**
	 * The bulk action is registered and its notice reports the count.
	 */
	public function test_bulk_edit_registration_and_notice() {
		$this->assertSame( array( 'venue_children' ), array_keys( Post_Venue::register_bulk_edit( array() ) ) );

		$this->assertSame( '', $this->capture( array( 'Post_Venue', 'bulk_action_admin_notices' ) ) );
		$_REQUEST['bulk_venue_children'] = '0';
		$this->assertStringContainsString( 'None of the Venues Were Updated.', $this->capture( array( 'Post_Venue', 'bulk_action_admin_notices' ) ) );
		$_REQUEST['bulk_venue_children'] = '2';
		$this->assertStringContainsString( 'Updated 2 venues.', $this->capture( array( 'Post_Venue', 'bulk_action_admin_notices' ) ) );
	}

	/**
	 * Venues that already have a location aren't changed by the bulk update, and other actions pass through.
	 */
	public function test_handle_bulk_edit() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$venue_id = $this->venue();
		$this->assertSame( 'edit.php?bulk_venue_children=0', Post_Venue::handle_bulk_edit( 'edit.php', 'venue_children', array( $venue_id ) ) );
		$this->assertEquals( 40.7128, get_post_geodata( $venue_id, 'latitude' ) );
		$this->assertSame( 'edit.php', Post_Venue::handle_bulk_edit( 'edit.php', 'trash', array( $venue_id ) ) );
	}

	/**
	 * The bulk update gives a venue without a location the location of a post checked in there.
	 */
	public function test_handle_bulk_edit_venue_without_location() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$empty    = self::factory()->post->create( array( 'post_type' => 'venue' ) );
		$venue_id = self::factory()->post->create( array( 'post_type' => 'venue' ) );
		$post_id  = self::factory()->post->create();
		update_post_meta( $post_id, 'venue_id', $venue_id );
		set_post_geodata(
			$post_id,
			'',
			array(
				'latitude'  => 40.7128,
				'longitude' => -74.006,
			)
		);
		$this->assertSame( 'edit.php?bulk_venue_children=1', Post_Venue::handle_bulk_edit( 'edit.php', 'venue_children', array( $empty, $venue_id ) ) );
		$this->assertEquals( 40.7128, get_post_geodata( $venue_id, 'latitude' ) );
		$this->assertNull( get_post_geodata( $empty ) );
	}

	/**
	 * The venue type filter lists every type, only on the venue list.
	 */
	public function test_venue_types_dropdown() {
		wp_insert_term( 'Restaurant', 'venue_type' );
		wp_insert_term( 'Park', 'venue_type' );
		$output = $this->capture( array( 'Post_Venue', 'venue_types_dropdown' ), 'venue', 'top' );
		$this->assertStringContainsString( '<select id="venue_type" name="venue_type">', $output );
		$this->assertStringContainsString( 'All Types', $output );
		$this->assertStringContainsString( 'value="restaurant"', $output );
		$this->assertStringContainsString( '>Park</option>', $output );
		$this->assertSame( '', $this->capture( array( 'Post_Venue', 'venue_types_dropdown' ), 'post', 'top' ) );

		$_REQUEST['venue_type'] = 'park';
		$output                 = $this->capture( array( 'Post_Venue', 'venue_types_dropdown' ), 'venue', 'top' );
		unset( $_REQUEST['venue_type'] );
		$this->assertStringStartsWith( '<select', $output );
		$this->assertMatchesRegularExpression( '#<option value="park" selected(=["\']selected["\'])?>Park</option>#', $output );
		$this->assertSame( 1, substr_count( $output, ' selected' ) );
	}

	/**
	 * Nearby venues are found within the radius; others aren't.
	 */
	public function test_nearby() {
		$here  = $this->venue( 40.7128, -74.006 );
		$close = $this->venue( 40.7130, -74.0062 );
		$far   = $this->venue( 40.8, -74.1 );
		$found = Post_Venue::nearby( 40.7128, -74.006 );
		sort( $found );
		$this->assertSame( array( $here, $close ), $found );
		$this->assertNotContains( $far, Post_Venue::nearby( 40.7128, -74.006, 1000 ) );
		$this->assertSame( array(), Post_Venue::nearby( 10, 10 ) );
	}

	/**
	 * The pre_nearby_venue filter short-circuits the lookup.
	 */
	public function test_nearby_filter() {
		$filter = function () {
			return array( 42 );
		};
		add_filter( 'pre_nearby_venue', $filter );
		$this->assertSame( array( 42 ), Post_Venue::nearby( 1, 1 ) );
		remove_filter( 'pre_nearby_venue', $filter );
	}

	/**
	 * The nearby select lists nearby venues with the current one selected, and can echo.
	 */
	public function test_nearby_select() {
		$venue_id = $this->venue();
		$select   = Post_Venue::nearby_select( 40.7128, -74.006, $venue_id, false );
		$this->assertStringContainsString( '<select name="venue_id" id="venue_id">', $select );
		$this->assertStringContainsString( '<option value="0" >None</option>', $select );
		$this->assertMatchesRegularExpression( '/value="' . $venue_id . '"\s+selected/', $select );
		$this->assertStringContainsString( '>Corner Cafe</option>', $select );

		$this->assertSame( $select, $this->capture( array( 'Post_Venue', 'nearby_select' ), 40.7128, -74.006, $venue_id ) );
	}

	/**
	 * A location is at a venue when it is within the venue's radius (default 50 m).
	 */
	public function test_at_venue() {
		$venue_id = $this->venue( 40.7128, -74.006 );
		$this->assertSame( $venue_id, Post_Venue::at_venue( 40.7128, -74.006 ) );
		// About 33 m away.
		$this->assertSame( $venue_id, Post_Venue::at_venue( 40.7131, -74.006 ) );
		// About 89 m away: outside the default radius, inside a larger one.
		$this->assertFalse( Post_Venue::at_venue( 40.7136, -74.006 ) );
		update_post_meta( $venue_id, 'venue_radius', 100 );
		$this->assertSame( $venue_id, Post_Venue::at_venue( 40.7136, -74.006 ) );
		$this->assertFalse( Post_Venue::at_venue( 41, -74 ) );
	}

	/**
	 * The pre_at_venue filter short-circuits the check.
	 */
	public function test_at_venue_filter() {
		$filter = function () {
			return 7;
		};
		add_filter( 'pre_at_venue', $filter );
		$this->assertSame( 7, Post_Venue::at_venue( 1, 1 ) );
		remove_filter( 'pre_at_venue', $filter );
	}

	/**
	 * A post's venue can be set and read; non-numeric IDs are refused.
	 */
	public function test_post_venue() {
		$post_id  = self::factory()->post->create();
		$venue_id = $this->venue();
		$this->assertSame( '', Post_Venue::get_post_venue( $post_id ) );
		$this->assertFalse( Post_Venue::set_post_venue( $post_id, 'cafe' ) );
		$this->assertNotFalse( Post_Venue::set_post_venue( $post_id, $venue_id ) );
		$this->assertEquals( $venue_id, Post_Venue::get_post_venue( $post_id ) );
	}

	/**
	 * Setting a venue gives a post without a location the venue's location, and keeps a post's own location.
	 */
	public function test_set_post_venue_location() {
		$venue_id = $this->venue();
		$term     = Location_Taxonomy::get_location( $this->address(), true );
		Location_Taxonomy::set_location( $venue_id, $term );

		$post_id = self::factory()->post->create();
		Post_Venue::set_post_venue( $post_id, $venue_id );
		$this->assertSame( $term, Location_Taxonomy::get_post_location( $post_id ) );

		$own   = Location_Taxonomy::get_location(
			array(
				'country-name' => 'Canada',
				'country-code' => 'CA',
				'region'       => 'Ontario',
				'region-code'  => 'ON',
				'locality'     => 'Toronto',
			),
			true
		);
		$other = self::factory()->post->create();
		Location_Taxonomy::set_location( $other, $own );
		Post_Venue::set_post_venue( $other, $venue_id );
		$this->assertSame( $own, Location_Taxonomy::get_post_location( $other ) );
	}

	/**
	 * Showing a post's location never changes its location term.
	 */
	public function test_location_display_does_not_set_location() {
		$term    = Location_Taxonomy::get_location( $this->address(), true );
		$post_id = self::factory()->post->create();
		set_post_geodata(
			$post_id,
			'',
			array(
				'latitude'   => 40.7128,
				'longitude'  => -74.006,
				'visibility' => 'public',
			)
		);
		Location_Taxonomy::set_location( $post_id, $term );

		// The global post has a different location.
		$own       = Location_Taxonomy::get_location(
			array(
				'country-name' => 'Canada',
				'country-code' => 'CA',
				'region'       => 'Ontario',
				'region-code'  => 'ON',
				'locality'     => 'Toronto',
			),
			true
		);
		$global_id = self::factory()->post->create();
		Location_Taxonomy::set_location( $global_id, $own );
		$GLOBALS['post'] = get_post( $global_id );

		get_post_location( $post_id );
		$GLOBALS['post'] = null;
		$this->assertSame( $term, Location_Taxonomy::get_post_location( $post_id ) );
	}

	/**
	 * A post with a venue but no location term shows the venue's location without saving it.
	 */
	public function test_location_display_uses_venue_location() {
		$venue_id = $this->venue();
		$term     = Location_Taxonomy::get_location( $this->address(), true );
		Location_Taxonomy::set_location( $venue_id, $term );

		$post_id = self::factory()->post->create();
		set_post_geodata(
			$post_id,
			'',
			array(
				'latitude'   => 40.7128,
				'longitude'  => -74.006,
				'visibility' => 'public',
			)
		);
		update_post_meta( $post_id, 'venue_id', $venue_id );

		$html = get_post_location( $post_id );
		$this->assertStringContainsString( 'Corner Cafe', $html );
		$this->assertStringContainsString( 'New York', $html );
		$this->assertFalse( Location_Taxonomy::get_post_location( $post_id ) );
	}

	/**
	 * A new venue is created from address data with its location, term, URL and public visibility.
	 */
	public function test_add_new_venue() {
		$venue_id = Post_Venue::add_new_venue(
			array(
				'name'           => 'Corner Cafe',
				'url'            => array( 'https://cafe.example.com/' ),
				'street-address' => '1 Main St',
				'latitude'       => 40.7128,
				'longitude'      => -74.006,
			) + $this->address()
		);
		$this->assertIsInt( $venue_id );
		$venue = get_post( $venue_id );
		$this->assertSame( 'venue', $venue->post_type );
		$this->assertSame( 'publish', $venue->post_status );
		$this->assertSame( 'Corner Cafe', $venue->post_title );
		$this->assertStringStartsWith( 'corner-cafe-new-york-united-states', $venue->post_name, 'New York is named once (#240).' );
		$this->assertStringNotContainsString( '%', $venue->post_name, 'No encoded flag emoji.' );
		$this->assertSame( 'https://cafe.example.com/', get_post_meta( $venue_id, 'venue_url', true ) );
		$this->assertSame( '1 Main St', get_post_meta( $venue_id, 'venue_street_address', true ) );

		$geodata = get_post_geodata( $venue_id );
		$this->assertEquals( 40.7128, $geodata['latitude'] );
		$this->assertSame( 'public', $geodata['visibility'] );
		$this->assertEquals( 18, $geodata['zoom'] );
		$this->assertSame( 'New York', Location_Taxonomy::get_location_taxonomy( $venue_id )->name );
	}

	/**
	 * A venue without a country is created without a location term.
	 */
	public function test_add_new_venue_without_country() {
		$venue_id = Post_Venue::add_new_venue(
			array(
				'name'      => 'Somewhere Cafe',
				'locality'  => 'Springfield',
				'latitude'  => 40.7128,
				'longitude' => -74.006,
			)
		);
		$this->assertIsInt( $venue_id );
		$this->assertSame( 'Somewhere Cafe', get_the_title( $venue_id ) );
		$this->assertFalse( Location_Taxonomy::get_location_taxonomy( $venue_id ) );
		$this->assertSame(
			array(),
			get_terms(
				array(
					'taxonomy'   => 'location',
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			)
		);
	}

	/**
	 * A label is used when there is no name, and a zoom level is kept.
	 */
	public function test_add_new_venue_label_and_zoom() {
		$venue_id = Post_Venue::add_new_venue(
			array(
				'label'     => 'The Park',
				'latitude'  => 40.78,
				'longitude' => -73.97,
				'zoom'      => 15,
			) + $this->address()
		);
		$this->assertSame( 'The Park', get_the_title( $venue_id ) );
		$this->assertEquals( 15, get_post_geodata( $venue_id, 'zoom' ) );
	}

	/**
	 * A venue needs a name or label.
	 */
	public function test_add_new_venue_without_name() {
		$this->assertFalse( Post_Venue::add_new_venue( array( 'latitude' => 1.5 ) ) );
	}

	/**
	 * An existing venue with the same URL is returned instead of a new venue.
	 */
	public function test_add_new_venue_existing_url() {
		$venue_id = $this->venue();
		update_post_meta( $venue_id, 'venue_url', 'https://cafe.example.com/' );
		$this->assertSame(
			$venue_id,
			Post_Venue::add_new_venue(
				array(
					'name' => 'Cafe',
					'url'  => 'https://cafe.example.com/',
				)
			)
		);
	}

	/**
	 * An existing venue with the URL among its alternate URLs is returned instead of a new venue.
	 */
	public function test_add_new_venue_alternate_url() {
		$venue_id = $this->venue();
		update_post_meta( $venue_id, 'venue_alternate', 'https://old.example.com/cafe' );
		$this->assertSame(
			$venue_id,
			Post_Venue::add_new_venue(
				array(
					'name' => 'Cafe',
					'url'  => 'https://old.example.com/cafe',
				)
			)
		);
	}

	/**
	 * A check-in with the identifier of a venue is that venue, whatever its name; the venue keeps where it came from (#729).
	 */
	public function test_add_new_venue_uid() {
		$card     = array(
			'name'      => 'Philadelphia City Hall',
			'uid'       => 'here:pds:place:840dr4ux-cityhall',
			'provider'  => 'here',
			'latitude'  => 39.9524,
			'longitude' => -75.1636,
		);
		$venue_id = Post_Venue::add_new_venue( $card );
		$this->assertIsInt( $venue_id );
		$sources = Post_Venue::get_venue_sources( $venue_id );
		$this->assertCount( 1, $sources );
		$this->assertSame( 'here', $sources[0]['provider'] );
		$this->assertSame( 'here:pds:place:840dr4ux-cityhall', $sources[0]['uid'] );
		$this->assertNotFalse( strtotime( $sources[0]['retrieved'] ) );
		$this->assertSame( '', get_post_meta( $venue_id, 'venue_alternate', true ), 'An identifier that is not a web address is not a link.' );

		$this->assertSame(
			$venue_id,
			Post_Venue::add_new_venue(
				array(
					'name'      => 'City Hall',
					'latitude'  => 40,
					'longitude' => -75,
				) + $card
			)
		);
		$this->assertCount( 1, Post_Venue::get_venue_sources( $venue_id ), 'The same identifier is kept once.' );
	}

	/**
	 * Pages about a venue on map and listing sites are alternate URLs; the first other URL is its website (#729).
	 */
	public function test_venue_urls() {
		$this->assertSame(
			array(
				'url'       => 'https://cafe.example.com/',
				'alternate' => array( 'https://foursquare.com/v/cafe/123', 'https://cafe.example.org/', 'https://www.openstreetmap.org/node/1' ),
			),
			Post_Venue::venue_urls(
				array(
					'url' => array( 'https://foursquare.com/v/cafe/123', 'https://cafe.example.com/', 'https://cafe.example.org/', 'https://cafe.example.com/', 'javascript:alert(1)' ),
					'uid' => 'https://www.openstreetmap.org/node/1',
				)
			)
		);
		$this->assertSame(
			array(
				'url'       => '',
				'alternate' => array( 'https://en.wikipedia.org/wiki/Cafe' ),
			),
			Post_Venue::venue_urls(
				array(
					'url' => 'https://en.wikipedia.org/wiki/Cafe',
					'uid' => 'tomtom:abc',
				)
			)
		);
		$this->assertSame(
			array(
				'url'       => '',
				'alternate' => array(),
			),
			Post_Venue::venue_urls( array( 'uid' => '123' ) ),
			'An identifier without a scheme is not a URL.'
		);

		$this->assertTrue( Post_Venue::is_alternate_url( 'https://www.google.com/maps/place/?q=place_id:X' ) );
		$this->assertTrue( Post_Venue::is_alternate_url( 'https://WEGO.HERE.COM/p/x' ) );
		$this->assertFalse( Post_Venue::is_alternate_url( 'https://notgoogle.com/' ) );
		$this->assertFalse( Post_Venue::is_alternate_url( 'not a url' ) );
		add_filter( 'sloc_venue_alternate_hosts', fn( $hosts ) => array_merge( $hosts, array( 'untappd.com' ) ) );
		$this->assertTrue( Post_Venue::is_alternate_url( 'https://untappd.com/v/cafe/1' ) );
	}

	/**
	 * A check-in matches a venue with one of its URLs, as a whole URL among the alternates (#729).
	 */
	public function test_find_venue_by_url() {
		$venue_id = $this->venue();
		update_post_meta( $venue_id, 'venue_alternate', array( 'https://foursquare.com/v/cafe/1234' ) );
		$this->assertFalse( Post_Venue::find_venue( array( 'url' => 'https://foursquare.com/v/cafe/123' ) ), 'Not a part of another URL.' );
		$this->assertSame( $venue_id, Post_Venue::find_venue( array( 'url' => 'https://foursquare.com/v/cafe/1234' ) ) );
		$this->assertSame( $venue_id, Post_Venue::find_venue( array( 'uid' => 'https://foursquare.com/v/cafe/1234' ) ) );
	}

	/**
	 * A check-in matches a venue with the same name within the venue's radius, or MATCH_RADIUS (#729).
	 */
	public function test_find_venue_by_name() {
		$venue_id = $this->venue( 40.7128, -74.006 );
		$checkin  = array(
			'name'      => 'Corner Cafe',
			'latitude'  => 40.7133,
			'longitude' => -74.006,
		);
		$this->assertSame( $venue_id, Post_Venue::find_venue( $checkin ), 'About 55 meters away.' );
		$this->assertFalse( Post_Venue::find_venue( array( 'name' => 'Other Cafe' ) + $checkin ) );
		$this->assertFalse( Post_Venue::find_venue( array( 'latitude' => 40.72 ) + $checkin ), 'About 800 meters away.' );
		update_post_meta( $venue_id, 'venue_radius', 1000 );
		$this->assertSame( $venue_id, Post_Venue::find_venue( array( 'latitude' => 40.72 ) + $checkin ), 'Within a larger venue radius.' );
		$this->assertFalse( Post_Venue::find_venue( array( 'name' => 'Corner Cafe' ) ), 'A name alone is not enough.' );

		add_filter( 'sloc_find_venue', fn() => 42 );
		$this->assertSame( 42, Post_Venue::find_venue( array() ) );
	}

	/**
	 * A matched venue gains the check-in's identifier and pages, and keeps the website the user set (#729).
	 */
	public function test_add_new_venue_adds_sources() {
		$venue_id = $this->venue();
		update_post_meta( $venue_id, 'venue_url', 'https://mine.example.com/' );
		$this->assertSame(
			$venue_id,
			Post_Venue::add_new_venue(
				array(
					'name'      => 'Corner Cafe',
					'url'       => array( 'https://cafe.example.com/', 'https://foursquare.com/v/cafe/1' ),
					'uid'       => 'https://www.openstreetmap.org/node/1',
					'provider'  => 'overpass',
					'latitude'  => 40.7128,
					'longitude' => -74.006,
				)
			)
		);
		$this->assertSame( 'https://mine.example.com/', get_post_meta( $venue_id, 'venue_url', true ) );
		$this->assertSame(
			array( 'https://cafe.example.com/', 'https://foursquare.com/v/cafe/1', 'https://www.openstreetmap.org/node/1' ),
			get_post_meta( $venue_id, 'venue_alternate', true )
		);
		$this->assertSame( 'overpass', Post_Venue::get_venue_sources( $venue_id )[0]['provider'] );
		$this->assertSame( $venue_id, Post_Venue::find_venue( array( 'uid' => 'https://www.openstreetmap.org/node/1' ) ) );

		// A venue without a website gets one.
		delete_post_meta( $venue_id, 'venue_url' );
		Post_Venue::add_venue_sources( $venue_id, array(), Post_Venue::venue_urls( array( 'url' => 'https://cafe.example.com/' ) ) );
		$this->assertSame( 'https://cafe.example.com/', get_post_meta( $venue_id, 'venue_url', true ) );
	}

	/**
	 * A new venue keeps its website and its pages on other sites apart (#729).
	 */
	public function test_add_new_venue_alternates() {
		$venue_id = Post_Venue::add_new_venue(
			array(
				'name'      => 'Swarm Cafe',
				'url'       => 'https://foursquare.com/v/swarm-cafe/abc',
				'latitude'  => 40.7128,
				'longitude' => -74.006,
			)
		);
		$this->assertSame( '', get_post_meta( $venue_id, 'venue_url', true ) );
		$this->assertSame( array( 'https://foursquare.com/v/swarm-cafe/abc' ), get_post_meta( $venue_id, 'venue_alternate', true ) );
		$this->assertSame( array(), Post_Venue::get_venue_sources( $venue_id ) );
	}

	/**
	 * Child venues are found among venues, not posts, and all of them are returned.
	 */
	public function test_get_venue_children() {
		$mall     = $this->venue();
		$children = array();
		for ( $i = 0; $i < 6; $i++ ) {
			$children[] = $this->venue( 40.7128, -74.006, array( 'post_parent' => $mall ) );
		}
		self::factory()->post->create( array( 'post_parent' => $mall ) );
		$found = Post_Venue::get_venue_children( $mall );
		sort( $found );
		$this->assertSame( $children, $found );
		$this->assertSame( array(), Post_Venue::get_venue_children( $children[0] ) );
	}

	/**
	 * Venue type, parents and ancestors.
	 */
	public function test_venue_hierarchy_and_type() {
		$mall  = $this->venue();
		$shop  = $this->venue( 40.7128, -74.006, array( 'post_parent' => $mall ) );
		$kiosk = $this->venue( 40.7128, -74.006, array( 'post_parent' => $shop ) );

		$this->assertTrue( Post_Venue::has_parent_venue( $shop ) );
		$this->assertFalse( Post_Venue::has_parent_venue( $mall ) );
		$this->assertSame( $mall, Post_Venue::get_parent_venue( $shop ) );
		$this->assertSame( array( $shop, $mall ), Post_Venue::get_venue_ancestors( $kiosk ) );
		$this->assertFalse( Post_Venue::get_venue_ancestors( 999999 ) );
		$this->assertFalse( Post_Venue::get_venue_children( 999999 ) );

		$this->assertFalse( Post_Venue::get_venue_type( $mall ) );
		$type = wp_insert_term( 'Shopping', 'venue_type' );
		wp_set_object_terms( $mall, $type['term_id'], 'venue_type' );
		$this->assertSame( array( $type['term_id'] ), Post_Venue::get_venue_type( $mall ) );
		$this->assertSame( '', Post_Venue::get_venue_type( self::factory()->post->create() ) );
	}

	/**
	 * The posts checked in at a venue, and the photos attached to them.
	 */
	public function test_venue_posts_and_photos() {
		$venue_id = $this->venue();
		$this->assertSame( array(), Post_Venue::get_venue_posts( $venue_id ) );
		$this->assertSame( array(), Post_Venue::get_attached_photos( $venue_id ) );
		$this->assertFalse( Post_Venue::get_venue_posts( self::factory()->post->create() ) );

		$ids = $this->checkins( $venue_id, 2 );
		$this->checkins( $this->venue(), 1 );
		$found = Post_Venue::get_venue_posts( $venue_id );
		sort( $found );
		$this->assertSame( $ids, $found );

		$photo = self::factory()->attachment->create_object(
			array(
				'file'           => 'photo.jpg',
				'post_parent'    => $ids[0],
				'post_mime_type' => 'image/jpeg',
			)
		);
		$this->assertSame( array( $photo ), Post_Venue::get_attached_photos( $venue_id ) );
		$this->assertStringContainsString( 'gallery', Post_Venue::get_venue_gallery( $venue_id ) );
	}
}

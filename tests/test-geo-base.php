<?php
/**
 * Tests for the admin, capability, feed, and REST plumbing in includes/class-geo-base.php.
 *
 * Saving location fields is covered in test-save-meta.php and visibility output in
 * test-geo-visibility.php.
 *
 * @package Simple_Location
 */

// phpcs:disable WordPress.WP.Capabilities.Unknown -- The plugin's custom capabilities are mapped in Geo_Base::map_meta_cap().

/**
 * Geo_Base tests.
 */
class GeoBaseTest extends WP_UnitTestCase {

	/**
	 * Users by role.
	 *
	 * @var int[]
	 */
	protected static $users = array();

	/**
	 * Create one user per role.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		foreach ( array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ) as $role ) {
			self::$users[ $role ] = $factory->user->create( array( 'role' => $role ) );
		}
	}

	/**
	 * Use the fake providers and start logged out.
	 */
	public function set_up() {
		parent::set_up();
		foreach ( array( 'geo', 'weather', 'elevation' ) as $type ) {
			update_option( 'sloc_' . $type . '_provider', 'sloc_test' );
		}
		update_option( 'geo_public', 1 );
		Geo_Provider_Sloc_Test::reset();
		Weather_Provider_Sloc_Test::reset();
		wp_set_current_user( 0 );
	}

	/**
	 * Reset request data and the admin screen.
	 */
	public function tear_down() {
		$_POST    = array();
		$_REQUEST = array();
		set_current_screen( 'front' );
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
	 * Decodes the options passed to the admin script.
	 *
	 * @return array
	 */
	protected function localized_options() {
		// Each call to wp_localize_script() appends a line; the last one is current.
		$lines = explode( "\n", trim( wp_scripts()->get_data( 'sloc_location', 'data' ) ) );
		preg_match( '/^var slocOptions = (.*);$/', end( $lines ), $matches );
		return json_decode( $matches[1], true );
	}

	/**
	 * Creates a post with a public location.
	 *
	 * @param array $args Post arguments.
	 * @return int Post ID.
	 */
	protected function post_with_location( $args = array() ) {
		$post_id = self::factory()->post->create( $args );
		set_post_geodata(
			$post_id,
			'',
			array(
				'latitude'   => 45.01,
				'longitude'  => -75.44,
				'address'    => 'Test Location',
				'visibility' => 'public',
			)
		);
		return $post_id;
	}

	/**
	 * The main hooks are registered.
	 */
	public function test_hooks_registered() {
		$this->assertSame( 1, has_filter( 'map_meta_cap', array( 'Geo_Base', 'map_meta_cap' ) ) );
		$this->assertSame( 10, has_action( 'save_post', array( 'Geo_Base', 'save_post_meta' ) ) );
		$this->assertSame( 10, has_action( 'edit_comment', array( 'Geo_Base', 'save_comment_meta' ) ) );
		$this->assertSame( 12, has_action( 'personal_options_update', array( 'Geo_Base', 'save_user_meta' ) ) );
		$this->assertSame( 10, has_filter( 'bulk_actions-edit-post', array( 'Geo_Base', 'register_bulk_edit_location' ) ) );
		$this->assertSame( 10, has_filter( 'manage_post_posts_columns', array( 'Geo_Base', 'add_location_admin_column' ) ) );
		$this->assertSame( 10, has_action( 'wp_head', array( 'Geo_Base', 'meta_tags' ) ) );
		$this->assertSame( 11, has_filter( 'jetpack_tools_to_include', array( 'Geo_Base', 'jetpack_remove' ) ) );
	}

	/**
	 * Location is supported on posts, pages, attachments and venues, and the list can be filtered.
	 */
	public function test_screens() {
		$screens = Geo_Base::screens();
		foreach ( array( 'post', 'page', 'attachment', 'venue' ) as $post_type ) {
			$this->assertContains( $post_type, $screens );
		}
		$filter = function () {
			return array( 'post' );
		};
		add_filter( 'sloc_post_types', $filter );
		$this->assertSame( array( 'post' ), Geo_Base::screens() );
		remove_filter( 'sloc_post_types', $filter );
	}

	/**
	 * The location metabox is added to supported post types and comments.
	 */
	public function test_add_meta_boxes() {
		global $wp_meta_boxes;
		Geo_Base::admin_init();
		$this->assertSame( 10, has_action( 'add_meta_boxes', array( 'Geo_Base', 'add_meta_boxes' ) ) );
		Geo_Base::add_meta_boxes();
		$this->assertArrayHasKey( 'locationsidebox', $wp_meta_boxes['post']['side']['default'] );
		$this->assertArrayHasKey( 'locationsidebox', $wp_meta_boxes['page']['side']['default'] );
		$this->assertArrayHasKey( 'locationsidebox', $wp_meta_boxes['comment']['normal']['default'] );
	}

	/**
	 * The post metabox is shown to users who can edit the location.
	 *
	 * Note that load_template() uses require_once, so the template renders only once per test
	 * run; the refused cases come first and the sidebox action shows whether it was attempted.
	 */
	public function test_metabox_post() {
		set_current_screen( 'post' );
		$post_id         = $this->post_with_location();
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		wp_set_current_user( self::$users['subscriber'] );
		$this->assertSame( '', $this->capture( array( 'Geo_Base', 'metabox' ), get_post( $post_id ), array() ) );
		$this->assertSame( 0, did_action( 'simple_location_sidebox' ) );

		wp_set_current_user( self::$users['administrator'] );
		$output = $this->capture( array( 'Geo_Base', 'metabox' ), get_post( $post_id ), array() );
		$this->assertStringContainsString( 'name="latitude"', $output );
		$this->assertStringContainsString( '45.01', $output );
		$this->assertStringContainsString( 'location_metabox_nonce', $output );
		$this->assertSame( 1, did_action( 'simple_location_sidebox' ) );
		wp_reset_postdata();
	}

	/**
	 * Authors get the location metabox on their own posts.
	 */
	public function test_metabox_own_post() {
		set_current_screen( 'post' );
		$post_id = self::factory()->post->create( array( 'post_author' => self::$users['author'] ) );
		wp_set_current_user( self::$users['author'] );
		$this->capture( array( 'Geo_Base', 'metabox' ), get_post( $post_id ), array() );
		$this->assertSame( 1, did_action( 'simple_location_sidebox' ) );
	}

	/**
	 * The user profile section is shown to users who can edit that user's location.
	 */
	public function test_user_profile() {
		// load_template() uses require_once, so only the first allowed call renders.
		$subscriber = get_userdata( self::$users['subscriber'] );
		wp_set_current_user( self::$users['author'] );
		$this->assertSame( '', $this->capture( array( 'Geo_Base', 'user_profile' ), $subscriber ) );

		wp_set_current_user( self::$users['administrator'] );
		$output = $this->capture( array( 'Geo_Base', 'user_profile' ), $subscriber );
		$this->assertStringContainsString( 'Last Reported Location', $output );
		$this->assertStringContainsString( 'name="latitude"', $output );
	}

	/**
	 * Profile text fields show the stored value.
	 */
	public function test_profile_text_field() {
		$user = get_userdata( self::$users['author'] );
		update_user_meta( $user->ID, 'geo_address', 'Somewhere' );
		$output = $this->capture( array( 'Geo_Base', 'profile_text_field' ), $user, 'address', 'Address', 'Where you are' );
		$this->assertStringContainsString( 'name="address"', $output );
		$this->assertStringContainsString( 'value="Somewhere"', $output );
		$this->assertStringContainsString( 'Where you are', $output );
	}

	/**
	 * The user visibility row selects the current value.
	 */
	public function test_geo_public_user() {
		$output = $this->capture( array( 'Geo_Base', 'geo_public_user' ), 'protected' );
		$this->assertStringContainsString( 'name="geo_public"', $output );
		$this->assertMatchesRegularExpression( '/value="protected"\s+selected/', $output );
	}

	/**
	 * The admin script is enqueued and configured on the screens that need it, and only there.
	 */
	public function test_enqueue() {
		set_current_screen( 'post' );
		Geo_Base::enqueue( 'post.php' );
		$this->assertTrue( wp_script_is( 'sloc_location', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'sloc_admin', 'enqueued' ) );
		$options = $this->localized_options();
		$this->assertSame( 'yes', $options['weather'] );
		$this->assertStringContainsString( 'sloc_geo/1.0', $options['api_url'] );
		$this->assertSame( array( 'private', 'public', 'protected' ), array_keys( $options['visibility_options'] ) );
		wp_dequeue_script( 'sloc_location' );
		wp_dequeue_style( 'sloc_admin' );

		set_current_screen( 'page' );
		Geo_Base::enqueue( 'post.php' );
		$this->assertSame( 'no', $this->localized_options()['weather'] );
		wp_dequeue_script( 'sloc_location' );

		set_current_screen( 'dashboard' );
		Geo_Base::enqueue( 'index.php' );
		$this->assertFalse( wp_script_is( 'sloc_location', 'enqueued' ) );

		Geo_Base::enqueue( 'profile.php' );
		$this->assertTrue( wp_script_is( 'sloc_location', 'enqueued' ) );
	}

	/**
	 * Map requests on archives use the plugin's map template.
	 */
	public function test_map_archive_template() {
		$this->assertSame( 'index.php', Geo_Base::map_archive_template( 'index.php' ) );

		$category = self::factory()->category->create();
		self::factory()->post->create( array( 'post_category' => array( $category ) ) );
		$this->go_to( add_query_arg( 'map', '1', get_category_link( $category ) ) );
		$this->assertStringEndsWith( 'templates/map-archive.php', Geo_Base::map_archive_template( 'index.php' ) );

		$this->go_to( add_query_arg( 'map', '1', get_permalink( self::factory()->post->create() ) ) );
		$this->assertSame( 'index.php', Geo_Base::map_archive_template( 'index.php' ) );
	}

	/**
	 * The bulk actions are registered.
	 */
	public function test_register_bulk_edit_location() {
		$actions = Geo_Base::register_bulk_edit_location( array( 'trash' => 'Trash' ) );
		$this->assertSame( array( 'trash', 'location_public', 'location_private', 'lookup_location' ), array_keys( $actions ) );
	}

	/**
	 * The bulk lookup notice reports how many posts were updated.
	 */
	public function test_bulk_action_admin_notices() {
		$this->assertSame( '', $this->capture( array( 'Geo_Base', 'bulk_action_admin_notices' ) ) );
		$_REQUEST['bulk_lookup_location_count'] = '0';
		$this->assertStringContainsString( 'None of the Posts Were Updated.', $this->capture( array( 'Geo_Base', 'bulk_action_admin_notices' ) ) );
		$_REQUEST['bulk_lookup_location_count'] = '3';
		$this->assertStringContainsString( 'Updated 3 posts.', $this->capture( array( 'Geo_Base', 'bulk_action_admin_notices' ) ) );
		$_REQUEST['bulk_lookup_location_count'] = '1';
		$this->assertStringContainsString( 'Updated 1 post.', $this->capture( array( 'Geo_Base', 'bulk_action_admin_notices' ) ) );
	}

	/**
	 * Bulk lookup adds weather to posts with a location and reports the count.
	 */
	public function test_bulk_lookup_location() {
		wp_set_current_user( self::$users['administrator'] );
		$post_id  = $this->post_with_location();
		$redirect = Geo_Base::handle_bulk_edit_location( 'edit.php', 'lookup_location', array( $post_id ) );
		$this->assertSame( 'edit.php?bulk_lookup_location_count=1', $redirect );
		$this->assertEquals( 20, get_post_weatherdata( $post_id, 'temperature' ) );
	}

	/**
	 * Bulk lookup fills in a post's location from its author's profile.
	 */
	public function test_bulk_lookup_location_from_author() {
		update_option( 'sloc_geolocation_provider', 'dummy' );
		$author = self::$users['author'];
		Geo_Data::set_geodata(
			'user',
			$author,
			'',
			array(
				'latitude'  => 40.71,
				'longitude' => -74.01,
			)
		);
		wp_set_current_user( self::$users['administrator'] );
		$post_id = self::factory()->post->create( array( 'post_author' => $author ) );
		$this->assertSame( 'edit.php?bulk_lookup_location_count=1', Geo_Base::handle_bulk_edit_location( 'edit.php', 'lookup_location', array( $post_id ) ) );
		$geodata = get_post_geodata( $post_id );
		$this->assertEquals( 40.71, $geodata['latitude'] );
		$this->assertSame( 'Test Address', $geodata['address'] );
	}

	/**
	 * Bulk lookup skips posts the user can't edit.
	 */
	public function test_bulk_lookup_location_capability() {
		$post_id = $this->post_with_location( array( 'post_author' => self::$users['editor'] ) );
		wp_set_current_user( self::$users['contributor'] );
		$this->assertSame( 'edit.php?bulk_lookup_location_count=0', Geo_Base::handle_bulk_edit_location( 'edit.php', 'lookup_location', array( $post_id ) ) );
		$this->assertEmpty( get_post_weatherdata( $post_id ) );
	}

	/**
	 * The admin list shows a Location column with the visibility.
	 */
	public function test_location_admin_column() {
		$this->assertSame( 'Location', Geo_Base::add_location_admin_column( array() )['location'] );

		$this->assertSame( 'None', $this->capture( array( 'Geo_Base', 'manage_location_admin_column' ), 'location', self::factory()->post->create() ) );
		$post_id = $this->post_with_location();
		$this->assertSame( 'Public', $this->capture( array( 'Geo_Base', 'manage_location_admin_column' ), 'location', $post_id ) );
		set_post_geodata( $post_id, 'visibility', 'protected' );
		$this->assertSame( 'Protected', $this->capture( array( 'Geo_Base', 'manage_location_admin_column' ), 'location', $post_id ) );
		$this->assertSame( '', $this->capture( array( 'Geo_Base', 'manage_location_admin_column' ), 'title', $post_id ) );
	}

	/**
	 * Jetpack's geolocation module is removed.
	 */
	public function test_jetpack_remove() {
		$this->assertSame(
			array(
				0 => 'a.php',
				2 => 'b.php',
			),
			Geo_Base::jetpack_remove( array( 'a.php', 'geo-location.php', 'b.php' ) )
		);
		$this->assertSame( array( 'a.php' ), Geo_Base::jetpack_remove( array( 'a.php' ) ) );
	}

	/**
	 * Visibility labels and the select options built from them.
	 */
	public function test_geo_public_select() {
		$this->assertSame( array( 'private', 'public', 'protected' ), array_keys( Geo_Base::geo_public() ) );
		$this->assertSame(
			array(
				'option' => array(
					'value'    => array(),
					'selected' => array(),
				),
			),
			Geo_Base::kses_option()
		);

		$options = Geo_Base::geo_public_select( 'public' );
		$this->assertSame( 3, substr_count( $options, '<option' ) );
		$this->assertMatchesRegularExpression( '/value="public"\s+selected/', $options );
		$echoed = $this->capture( array( 'Geo_Base', 'geo_public_select' ), 'public', true );
		$this->assertSame( 3, substr_count( $echoed, '<option' ) );
		$this->assertMatchesRegularExpression( '/value="public"\s+selected/', $echoed );

		$options = Geo_Base::geo_public_select( 'inherit', false, Geo_Base::geo_public() + array( 'inherit' => 'Inherit' ) );
		$this->assertSame( 4, substr_count( $options, '<option' ) );
		$this->assertMatchesRegularExpression( '/value="inherit"\s+selected/', $options );
	}

	/**
	 * Attachments get an "inherit" choice labeled with what they currently inherit; other posts don't.
	 */
	public function test_visibility_choices() {
		$this->assertSame( Geo_Base::geo_public(), Geo_Base::visibility_choices() );
		$this->assertSame( Geo_Base::geo_public(), Geo_Base::visibility_choices( self::factory()->post->create() ) );

		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->attachment->create( array( 'post_parent' => $post_id ) );
		set_post_geodata( $post_id, 'visibility', 'protected' );
		$choices = Geo_Base::visibility_choices( $attachment_id );
		$this->assertSame( array( 'private', 'public', 'protected', 'inherit' ), array_keys( $choices ) );
		$this->assertSame( 'Inherit (currently Protected)', $choices['inherit'] );
	}

	/**
	 * The post and comment list filters list every visibility, only for supported post types.
	 */
	public function test_geo_dropdowns() {
		$output = $this->capture( array( 'Geo_Base', 'geo_posts_dropdown' ), 'post', 'top' );
		foreach ( array( 'none', 'all', 'private', 'public', 'protected' ) as $value ) {
			$this->assertStringContainsString( 'value="' . $value . '"', $output );
		}
		$this->assertStringContainsString( 'All Posts', $output );
		$this->assertSame( '', $this->capture( array( 'Geo_Base', 'geo_posts_dropdown' ), 'wp_block', 'top' ) );

		$output = $this->capture( array( 'Geo_Base', 'geo_comments_dropdown' ) );
		$this->assertStringContainsString( 'All Comments', $output );
		$this->assertStringContainsString( 'value="protected"', $output );
	}

	/**
	 * Feeds declare the GeoRSS and W3C geo namespaces.
	 */
	public function test_georss_namespace() {
		$output = $this->capture( array( 'Geo_Base', 'georss_namespace' ) );
		$this->assertStringContainsString( 'xmlns:georss="http://www.georss.org/georss"', $output );
		$this->assertStringContainsString( 'xmlns:geo="http://www.w3.org/2003/01/geo/wgs84_pos#"', $output );
	}

	/**
	 * The geo and map endpoints and the map/location rewrite rules are registered.
	 */
	public function test_rewrite_and_query_vars() {
		global $wp_rewrite;
		$this->set_permalink_structure( '/%postname%/' );
		Geo_Base::rewrite();
		$wp_rewrite->flush_rules();
		$rules = $wp_rewrite->wp_rewrite_rules();
		$this->assertArrayHasKey( 'map/location/(.+?)/?$', $rules );
		$this->assertSame( 'index.php?location=$matches[1]&map=1', $rules['map/location/(.+?)/?$'] );

		$vars = Geo_Base::query_var( array( 'p' ) );
		$this->assertSame( array( 'p', 'geo', 'map', 'sloc_units' ), $vars );
	}

	/**
	 * The REST fields are registered for posts, comments, users and terms.
	 */
	public function test_rest_fields_registered() {
		global $wp_rest_additional_fields;
		Geo_Base::rest_location();
		foreach ( array( 'post', 'comment', 'user', 'term' ) as $type ) {
			foreach ( array( 'latitude', 'longitude', 'geo_address', 'timezone' ) as $field ) {
				$this->assertArrayHasKey( $field, $wp_rest_additional_fields[ $type ], "$type $field" );
			}
		}
	}

	/**
	 * The REST timezone is the location's own when it's visible, otherwise the site timezone.
	 */
	public function test_rest_get_timezone() {
		update_option( 'timezone_string', 'America/Toronto' );
		// Post objects are passed because REST passes an array, which is #312.
		$post = get_post( $this->post_with_location() );
		$this->assertSame( 'America/Toronto', Geo_Base::rest_get_timezone( $post, 'timezone', null, 'post' ) );

		set_post_geodata( $post->ID, 'timezone', 'Europe/Paris' );
		$this->assertSame( 'Europe/Paris', Geo_Base::rest_get_timezone( $post, 'timezone', null, 'post' ) );
		set_post_geodata( $post->ID, 'visibility', 'protected' );
		$this->assertSame( 'Europe/Paris', Geo_Base::rest_get_timezone( $post, 'timezone', null, 'post' ) );

		$this->assertSame( '', Geo_Base::rest_get_timezone( self::factory()->post->create_and_get(), 'timezone', null, 'post' ) );
	}

	/**
	 * Addresses are stripped of disallowed HTML and trimmed; empty ones become null.
	 */
	public function test_sanitize_address() {
		$this->assertSame( '<span>Main St</span>', Geo_Base::sanitize_address( '  <span onclick="x">Main St</span> ' ) );
		$this->assertSame( '<strong>Main</strong> St', Geo_Base::sanitize_address( '<strong>Main</strong> St' ) );
		$this->assertNull( Geo_Base::sanitize_address( '   ' ) );
	}

	/**
	 * Empty location fields are removed from REST responses.
	 */
	public function test_rest_prepare_removes_empty_fields() {
		foreach ( array( 'rest_prepare_post', 'rest_prepare_comment', 'rest_prepare_user' ) as $method ) {
			$response = new WP_REST_Response(
				array(
					'id'          => 1,
					'latitude'    => '',
					'longitude'   => '',
					'geo_address' => 'Test Location',
					'timezone'    => '',
				)
			);
			$data     = Geo_Base::$method( $response, null, null )->get_data();
			$this->assertSame(
				array(
					'id'          => 1,
					'geo_address' => 'Test Location',
				),
				$data,
				$method
			);
		}
	}

	/**
	 * Data provider of location capabilities on another user's content: role => expected access.
	 *
	 * @return array
	 */
	public function data_capabilities_on_others() {
		return array(
			'administrator' => array( 'administrator', true, true, true ),
			'editor'        => array( 'editor', true, true, false ),
			'author'        => array( 'author', false, false, false ),
			'contributor'   => array( 'contributor', false, false, false ),
			'subscriber'    => array( 'subscriber', false, false, false ),
		);
	}

	/**
	 * Reading and editing another user's post location needs the private-posts and edit-others
	 * capabilities, and another user's profile location needs edit_users.
	 *
	 * @dataProvider data_capabilities_on_others
	 *
	 * @param string $role       Role.
	 * @param bool   $read_post  Can read the post location.
	 * @param bool   $edit_post  Can edit the post location.
	 * @param bool   $edit_user  Can edit or read the other user's location.
	 */
	public function test_capabilities_on_others( $role, $read_post, $edit_post, $edit_user ) {
		$owner   = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id = self::factory()->post->create( array( 'post_author' => $owner ) );
		wp_set_current_user( self::$users[ $role ] );
		$this->assertSame( $read_post, current_user_can( 'read_posts_location', $post_id ), 'read post' );
		$this->assertSame( $edit_post, current_user_can( 'edit_posts_location', $post_id ), 'edit post' );
		$this->assertSame( $edit_user, current_user_can( 'read_users_location', $owner ), 'read user' );
		$this->assertSame( $edit_user, current_user_can( 'edit_users_location', $owner ), 'edit user' );
	}

	/**
	 * Every user can read and edit their own profile location.
	 */
	public function test_capabilities_own_profile() {
		foreach ( self::$users as $role => $user_id ) {
			wp_set_current_user( $user_id );
			$this->assertTrue( current_user_can( 'read_users_location', $user_id ), $role );
			$this->assertTrue( current_user_can( 'edit_users_location', $user_id ), $role );
		}
	}

	/**
	 * Comment locations: moderators can edit any, readers of private posts can read any.
	 */
	public function test_capabilities_comments() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );
		wp_set_current_user( self::$users['editor'] );
		$this->assertTrue( current_user_can( 'read_comments_location', $comment_id ) );
		$this->assertTrue( current_user_can( 'edit_comments_location', $comment_id ) );
		wp_set_current_user( self::$users['author'] );
		$this->assertFalse( current_user_can( 'read_comments_location', $comment_id ) );
		$this->assertFalse( current_user_can( 'edit_comments_location', $comment_id ) );
	}

	/**
	 * Authors can read and edit the location of their own posts.
	 */
	public function test_capabilities_own_post() {
		$post_id = self::factory()->post->create( array( 'post_author' => self::$users['author'] ) );
		wp_set_current_user( self::$users['author'] );
		$this->assertTrue( current_user_can( 'read_posts_location', $post_id ) );
		$this->assertTrue( current_user_can( 'edit_posts_location', $post_id ) );
	}

	/**
	 * Commenters can read and edit the location of their own comments.
	 */
	public function test_capabilities_own_comment() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::factory()->post->create(),
				'user_id'         => self::$users['author'],
			)
		);
		wp_set_current_user( self::$users['author'] );
		$this->assertTrue( current_user_can( 'read_comments_location', $comment_id ) );
		$this->assertTrue( current_user_can( 'edit_comments_location', $comment_id ) );
	}

	/**
	 * A comment left without an account isn't treated as the current visitor's own comment.
	 */
	public function test_capabilities_guest_comment() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::factory()->post->create(),
				'user_id'         => 0,
			)
		);
		$this->assertSame( array( 'read_private_posts' ), Geo_Base::map_meta_cap( array(), 'read_comments_location', 0, array( $comment_id ) ) );
		$this->assertSame( array( 'moderate_comments' ), Geo_Base::map_meta_cap( array(), 'edit_comments_location', 0, array( $comment_id ) ) );
	}

	/**
	 * Term locations: editing follows the term's own edit capability, and reading private ones needs read_private_posts.
	 */
	public function test_capabilities_terms() {
		$term_id = self::factory()->term->create( array( 'taxonomy' => 'location' ) );
		wp_set_current_user( self::$users['editor'] );
		$this->assertTrue( current_user_can( 'read_terms_location', $term_id ) );
		$this->assertSame( current_user_can( 'edit_term', $term_id ), current_user_can( 'edit_terms_location', $term_id ) );
		$this->assertTrue( current_user_can( 'edit_terms_location', $term_id ) );
		wp_set_current_user( self::$users['author'] );
		$this->assertFalse( current_user_can( 'read_terms_location', $term_id ) );
		$this->assertFalse( current_user_can( 'edit_terms_location', $term_id ) );
	}

	/**
	 * Other capabilities pass through unchanged.
	 */
	public function test_map_meta_cap_other() {
		$this->assertSame( array( 'edit_posts' ), Geo_Base::map_meta_cap( array( 'edit_posts' ), 'edit_posts', 1, array() ) );
	}

	/**
	 * The read and edit helpers pick the capability for each object type.
	 */
	public function test_current_user_can_helpers() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );
		$user       = get_userdata( self::$users['subscriber'] );

		wp_set_current_user( self::$users['administrator'] );
		foreach ( array( get_post( $post_id ), $post_id, get_comment( $comment_id ), $user ) as $object ) {
			$this->assertTrue( Geo_Base::current_user_can_read( $object ) );
			$this->assertTrue( Geo_Base::current_user_can_edit( $object ) );
		}
		$this->assertFalse( Geo_Base::current_user_can_read( 'text' ) );
		$this->assertFalse( Geo_Base::current_user_can_edit( 999999 ) );

		$GLOBALS['post'] = get_post( $post_id );
		$this->assertTrue( Geo_Base::current_user_can_read( null ) );
		$this->assertTrue( Geo_Base::current_user_can_edit( null ) );
		unset( $GLOBALS['post'] );

		wp_set_current_user( self::$users['subscriber'] );
		$this->assertTrue( Geo_Base::current_user_can_read( $user ) );
		$this->assertFalse( Geo_Base::current_user_can_read( get_post( $post_id ) ) );
		$this->assertFalse( Geo_Base::current_user_can_edit( get_comment( $comment_id ) ) );
	}

	/**
	 * Saving a comment requires the metabox nonce and permission to edit the comment.
	 */
	public function test_save_comment_meta() {
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) );
		$fields     = array(
			'latitude'  => '45.01',
			'longitude' => '-75.44',
		);

		wp_set_current_user( self::$users['administrator'] );
		$_POST = $fields;
		Geo_Base::save_comment_meta( $comment_id );
		$this->assertSame( '', get_comment_meta( $comment_id, 'geo_latitude', true ), 'No nonce.' );

		$_POST = $fields + array( 'location_metabox_nonce' => 'invalid' );
		Geo_Base::save_comment_meta( $comment_id );
		$this->assertSame( '', get_comment_meta( $comment_id, 'geo_latitude', true ), 'Invalid nonce.' );

		wp_set_current_user( self::$users['author'] );
		$_POST = $fields + array( 'location_metabox_nonce' => wp_create_nonce( 'location_metabox' ) );
		Geo_Base::save_comment_meta( $comment_id );
		$this->assertSame( '', get_comment_meta( $comment_id, 'geo_latitude', true ), 'No permission.' );

		wp_set_current_user( self::$users['administrator'] );
		$_POST = $fields + array( 'location_metabox_nonce' => wp_create_nonce( 'location_metabox' ) );
		Geo_Base::save_comment_meta( $comment_id );
		$this->assertEquals( 45.01, get_comment_meta( $comment_id, 'geo_latitude', true ) );
	}

	/**
	 * Saving a profile requires permission to edit the user.
	 */
	public function test_save_user_meta() {
		$_POST = array(
			'latitude'  => '45.01',
			'longitude' => '-75.44',
		);
		wp_set_current_user( self::$users['author'] );
		Geo_Base::save_user_meta( self::$users['subscriber'] );
		$this->assertSame( '', get_user_meta( self::$users['subscriber'], 'geo_latitude', true ) );

		wp_set_current_user( self::$users['subscriber'] );
		Geo_Base::save_user_meta( self::$users['subscriber'] );
		$this->assertEquals( 45.01, get_user_meta( self::$users['subscriber'], 'geo_latitude', true ) );
	}

	/**
	 * Saving a page checks edit_page.
	 */
	public function test_save_post_meta_page() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_author' => self::$users['editor'],
			)
		);
		$_POST   = array(
			'post_type'              => 'page',
			'latitude'               => '45.01',
			'longitude'              => '-75.44',
			'location_metabox_nonce' => wp_create_nonce( 'location_metabox' ),
		);

		wp_set_current_user( self::$users['author'] );
		$_POST['location_metabox_nonce'] = wp_create_nonce( 'location_metabox' );
		Geo_Base::save_post_meta( $page_id );
		$this->assertSame( '', get_post_meta( $page_id, 'geo_latitude', true ) );

		wp_set_current_user( self::$users['editor'] );
		$_POST['location_metabox_nonce'] = wp_create_nonce( 'location_metabox' );
		Geo_Base::save_post_meta( $page_id );
		$this->assertEquals( 45.01, get_post_meta( $page_id, 'geo_latitude', true ) );
	}
}

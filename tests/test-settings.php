<?php
/**
 * Tests for plugin startup (Simple_Location_Plugin) and the settings page and provider registry
 * (Loc_Config).
 *
 * @package Simple_Location
 */

/**
 * Settings and bootstrap tests.
 */
class SettingsTest extends WP_UnitTestCase {

	/**
	 * Loads the admin functions the settings page uses.
	 */
	public static function wpSetUpBeforeClass() {
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	/**
	 * Clears request data.
	 */
	public function tear_down() {
		unset( $_GET['tab'] );
		parent::tear_down();
	}

	/**
	 * Captures output.
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
	 * The plugin records its version and path at startup.
	 */
	public function test_version_and_path() {
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', Simple_Location_Plugin::$version );
		$this->assertSame( trailingslashit( dirname( __DIR__ ) ), Simple_Location_Plugin::$path );
		$this->assertSame( 10, has_action( 'plugins_loaded', array( 'Simple_Location_Plugin', 'init' ) ) );
		$this->assertSame( 10, has_action( 'wp_enqueue_scripts', array( 'Simple_Location_Plugin', 'style_load' ) ) );
		$this->assertSame( 10, has_action( 'admin_init', array( 'Simple_Location_Plugin', 'privacy_declaration' ) ) );
	}

	/**
	 * File names map to class names, if the class exists.
	 */
	public function test_filename_to_classname() {
		$this->assertSame( 'Weather_Provider_Nwsus', Simple_Location_Plugin::filename_to_classname( 'class-weather-provider-nwsus.php' ) );
		$this->assertSame( 'Geo_Provider_Nominatim', Simple_Location_Plugin::filename_to_classname( 'class-geo-provider-nominatim.php' ) );
		$this->assertFalse( Simple_Location_Plugin::filename_to_classname( 'class-not-a-class.php' ) );
	}

	/**
	 * Providers are loaded and registered from their files; problems are reported with wp_trigger_error().
	 */
	public function test_register_providers() {
		$this->assertNull( Simple_Location_Plugin::register_providers( array() ) );

		Simple_Location_Plugin::register_providers( array( 'class-weather-provider-sloc-test-fallback.php' ), 'tests/includes' );
		$this->assertInstanceOf( 'Weather_Provider_Sloc_Test_Fallback', Loc_Config::weather_provider( 'sloc_test_fallback' ) );

		$log     = wp_tempnam( 'sloc-error-log' );
		$old     = ini_set( 'error_log', $log ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		$notices = array();
		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
			function ( $level, $message ) use ( &$notices ) {
				$notices[] = $message;
				return true;
			},
			E_USER_NOTICE
		);
		Simple_Location_Plugin::register_providers( array( 'class-missing-provider.php' ), 'tests/includes/' );
		// A provider file whose class doesn't match its name; it's already loaded, so requiring it again does nothing.
		Simple_Location_Plugin::register_providers( array( 'load-test-providers.php' ), 'tests/includes/' );
		Simple_Location_Plugin::load( array( 'missing-file.php' ) );
		restore_error_handler();
		ini_set( 'error_log', $old ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		$logged = file_get_contents( $log ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		unlink( $log ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

		$this->assertCount( 3, $notices );
		$this->assertStringStartsWith( 'Simple_Location_Plugin::register_providers(): Cannot find ', $notices[0] );
		$this->assertStringContainsString( 'tests/includes/class-missing-provider.php', $notices[0] );
		$this->assertStringStartsWith( 'Simple_Location_Plugin::register_providers(): Cannot register a provider from ', $notices[1] );
		$this->assertStringContainsString( 'tests/includes/load-test-providers.php', $notices[1] );
		$this->assertStringStartsWith( 'Simple_Location_Plugin::load(): Cannot find ', $notices[2] );
		$this->assertStringContainsString( 'includes/missing-file.php', $notices[2] );
		$this->assertSame( '', $logged, 'Nothing is written to the error log directly.' );

		$this->assertNull( Simple_Location_Plugin::load( array() ) );
	}

	/**
	 * Activation adds the map and geo endpoints and flushes rewrite rules.
	 */
	public function test_activate() {
		global $wp_rewrite;
		$this->set_permalink_structure( '/%year%/%postname%/' );
		Simple_Location_Plugin::activate();
		$rules = $wp_rewrite->wp_rewrite_rules();
		$this->assertArrayHasKey( 'map/location/(.+?)/page/?([0-9]{1,})/?$', $rules );
		$this->assertNotEmpty( preg_grep( '/map=/', $rules ) );

		$flushed = 0;
		$count   = function () use ( &$flushed ) {
			++$flushed;
		};
		add_action( 'generate_rewrite_rules', $count );
		Simple_Location_Plugin::deactivate();
		$this->assertSame( 1, $flushed );
		remove_action( 'generate_rewrite_rules', $count );
	}

	/**
	 * Rewrite rules are flushed when this plugin is updated, and not for other updates.
	 */
	public function test_upgrader_process_complete() {
		$this->set_permalink_structure( '/%year%/%postname%/' );
		$flushed = 0;
		$count   = function () use ( &$flushed ) {
			++$flushed;
		};
		add_action( 'generate_rewrite_rules', $count );
		$self = plugin_basename( dirname( __DIR__ ) . '/simple-location.php' );

		Simple_Location_Plugin::upgrader_process_complete(
			null,
			array(
				'action'  => 'update',
				'type'    => 'plugin',
				'plugins' => array( 'other/other.php' ),
			)
		);
		Simple_Location_Plugin::upgrader_process_complete(
			null,
			array(
				'action' => 'update',
				'type'   => 'theme',
			)
		);
		$this->assertSame( 0, $flushed );

		Simple_Location_Plugin::upgrader_process_complete(
			null,
			array(
				'action'  => 'update',
				'type'    => 'plugin',
				'plugins' => array( 'other/other.php', $self ),
			)
		);
		$this->assertSame( 1, $flushed );
		remove_action( 'generate_rewrite_rules', $count );
	}

	/**
	 * The plugins screen links to the settings page.
	 */
	public function test_settings_link() {
		$links = Simple_Location_Plugin::settings_link( array( '<a href="#">Deactivate</a>' ) );
		$this->assertCount( 2, $links );
		$this->assertSame( '<a href="admin.php?page=simloc">Settings</a>', $links[1] );
		$this->assertSame( 10, has_filter( 'plugin_action_links_' . plugin_basename( dirname( __DIR__ ) . '/simple-location.php' ), array( 'Simple_Location_Plugin', 'settings_link' ) ) );
	}

	/**
	 * The front-end stylesheet is enqueued with the plugin version.
	 */
	public function test_style_load() {
		Simple_Location_Plugin::style_load();
		$this->assertTrue( wp_style_is( 'simple-location', 'enqueued' ) );
		$this->assertStringEndsWith( 'css/location.min.css', wp_styles()->registered['simple-location']->src );
		$this->assertSame( Simple_Location_Plugin::$version, wp_styles()->registered['simple-location']->ver );
	}

	/**
	 * The plugin adds suggested privacy policy text.
	 */
	public function test_privacy_declaration() {
		global $wp_current_filter;
		set_current_screen( 'dashboard' );
		// Core only accepts policy text during admin_init.
		$wp_current_filter[] = 'admin_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		Simple_Location_Plugin::privacy_declaration();
		array_pop( $wp_current_filter );
		set_current_screen( 'front' );
		$plugins = wp_list_pluck( WP_Privacy_Policy_Content::get_suggested_policy_text(), 'plugin_name' );
		$this->assertContains( 'Simple Location', $plugins );
	}

	/**
	 * The plugin's allowed HTML keeps icons, maps and times, and strips scripts.
	 */
	public function test_kses_clean() {
		$allowed = Simple_Location_Plugin::kses_clean();
		foreach ( array( 'svg', 'path', 'img', 'time', 'span', 'ul', 'li', 'a' ) as $tag ) {
			$this->assertArrayHasKey( $tag, $allowed, $tag );
		}
		$html = '<span class="x" aria-hidden="true"><svg viewbox="0 0 30 30"><path d="M1,1" /></svg></span><time datetime="2025-07-01">July</time><script>alert(1)</script><img src="https://example.com/m.png" srcset="a 1x" onerror="x" />';
		$this->assertSame(
			'<span class="x" aria-hidden="true"><svg viewbox="0 0 30 30"><path d="M1,1" /></svg></span><time datetime="2025-07-01">July</time>alert(1)<img src="https://example.com/m.png" srcset="a 1x" />',
			wp_kses( $html, $allowed )
		);
	}

	/**
	 * Settings are registered with their defaults.
	 */
	public function test_registered_settings() {
		$settings = get_registered_settings();
		$expected = array(
			'geo_public'                => 1,
			'sloc_last_report'          => true,
			'sloc_auto_micropub'        => false,
			'sloc_taxonomy_display'     => false,
			'sloc_country'              => 'US',
			'sloc_map_display'          => false,
			'sloc_map_provider'         => 'none',
			'sloc_geolocation_provider' => 'HTML5',
			'sloc_zoom'                 => 14,
		);
		foreach ( $expected as $name => $default ) {
			$this->assertArrayHasKey( $name, $settings, $name );
			$this->assertSame( $default, $settings[ $name ]['default'], $name );
		}
		foreach ( array( 'sloc_geo_provider', 'sloc_weather_provider', 'sloc_elevation_provider', 'sloc_venue_provider', 'sloc_width', 'sloc_aspect', 'sloc_measurements' ) as $name ) {
			$this->assertArrayHasKey( $name, $settings, $name );
		}
		$this->assertSame( 'simloc', $settings['geo_public']['group'] );
		$this->assertSame( 'sloc_providers', $settings['sloc_map_provider']['group'] );
	}

	/**
	 * Map width defaults to the theme's content width.
	 */
	public function test_map_width_default() {
		global $content_width;
		$saved         = $content_width;
		$content_width = 640; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		Loc_Config::register_map_settings();
		$this->assertSame( 640, get_registered_settings()['sloc_width']['default'] );

		$content_width = 0; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		Loc_Config::register_map_settings();
		$this->assertSame( 1024, get_registered_settings()['sloc_width']['default'] );
		$content_width = $saved; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * US English sites default to imperial units.
	 */
	public function test_measurement_default() {
		$locale = function () {
			return 'en_US';
		};
		add_filter( 'locale', $locale );
		$this->assertSame( 'imperial', Loc_Config::measurement_default() );
		remove_filter( 'locale', $locale );

		$locale = function () {
			return 'en_GB';
		};
		add_filter( 'locale', $locale );
		$this->assertSame( 'si', Loc_Config::measurement_default() );
		remove_filter( 'locale', $locale );
	}

	/**
	 * Without the IndieWeb plugin, the settings page is under Settings.
	 */
	public function test_admin_menu() {
		global $submenu;
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );
		Loc_Config::admin_menu();
		set_current_screen( 'front' );
		$this->assertContains( 'simloc', wp_list_pluck( $submenu['options-general.php'], 2 ) );
	}

	/**
	 * The admin stylesheet and script load on the settings page only.
	 */
	public function test_admin_enqueue() {
		// Start from empty queues, since other tests may have loaded the settings page.
		$GLOBALS['wp_styles']  = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_scripts'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		Loc_Config::admin_enqueue( 'edit.php' );
		$this->assertFalse( wp_style_is( 'sloc_admin', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'sloc_password', 'enqueued' ) );

		Loc_Config::admin_enqueue( 'indieweb_page_simloc' );
		$this->assertTrue( wp_style_is( 'sloc_admin', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'sloc_password', 'enqueued' ) );

		// Without the IndieWeb plugin the page is under Settings.
		$GLOBALS['wp_styles']  = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_scripts'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		Loc_Config::admin_enqueue( 'settings_page_simloc' );
		$this->assertTrue( wp_style_is( 'sloc_admin', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'sloc_password', 'enqueued' ) );
	}

	/**
	 * Until it's saved, the aspect ratio is the first ratio in the list, and the settings page selects it.
	 */
	public function test_aspect_ratio_default() {
		delete_option( 'sloc_aspect' );
		Loc_Config::init();
		$this->assertSame( '1.77777777778', (string) get_option( 'sloc_aspect' ) );

		Loc_Config::admin_init();
		$html = $this->capture( array( 'Loc_Config', 'simloc_options' ) );
		$this->assertMatchesRegularExpression( "/<option value=\"1\.77777777778\"\s+selected(=['\"]selected['\"])?>/", $html );
	}

	/**
	 * The settings page shows the tabs and the active tab's settings.
	 */
	public function test_options_page() {
		Loc_Config::admin_init();
		$html = $this->capture( array( 'Loc_Config', 'simloc_options' ) );
		$this->assertStringContainsString( 'nav-tab-wrapper', $html );
		foreach ( array( 'general', 'providers', 'stations', 'api' ) as $tab ) {
			$this->assertStringContainsString( 'tab=' . $tab, $html, $tab );
		}
		$this->assertMatchesRegularExpression( '/class="nav-tab nav-tab-active">General</', $html );
		$this->assertStringContainsString( "name='option_page' value='simloc'", $html );
		$this->assertStringContainsString( 'Default Map Width', $html );
		$this->assertStringContainsString( 'name="sloc_country"', $html );

		$_GET['tab'] = 'providers';
		$html        = $this->capture( array( 'Loc_Config', 'simloc_options' ) );
		$this->assertMatchesRegularExpression( '/class="nav-tab nav-tab-active">Providers</', $html );
		$this->assertStringContainsString( "name='option_page' value='sloc_providers'", $html );
		foreach ( array( 'sloc_map_provider', 'sloc_geo_provider', 'sloc_geolocation_provider', 'sloc_venue_provider', 'sloc_elevation_provider', 'sloc_weather_provider', 'sloc_fallback_weather_provider' ) as $name ) {
			$this->assertStringContainsString( '<select name="' . $name . '">', $html, $name );
		}

		$_GET['tab'] = 'api';
		$this->assertStringContainsString( "name='option_page' value='sloc_api'", $this->capture( array( 'Loc_Config', 'simloc_options' ) ) );
		$_GET['tab'] = 'stations';
		$this->assertStringContainsString( "name='option_page' value='sloc_stations'", $this->capture( array( 'Loc_Config', 'simloc_options' ) ) );
	}

	/**
	 * The data cleanup removes the visibility setting from posts without a location, and the settings page no longer does.
	 */
	public function test_data_cleanup_removes_orphan_visibility() {
		// Posts need an author because of #305 (saving a post updates its author's last location).
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$orphan = self::factory()->post->create(
			array(
				'post_author' => $author,
				'post_status' => 'draft',
				'meta_input'  => array( 'geo_public' => 1 ),
			)
		);
		$kept   = self::factory()->post->create(
			array(
				'post_author' => $author,
				'meta_input'  => array(
					'geo_public'  => 1,
					'geo_address' => 'Philadelphia',
				),
			)
		);
		update_option( 'sloc_aspect', '1' );
		$this->capture( array( 'Loc_Config', 'simloc_options' ) );
		$this->assertSame( '1', get_post_meta( $orphan, 'geo_public', true ), 'Viewing the settings page changes nothing.' );

		Loc_Config::run_data_cleanup();
		$this->assertSame( '', get_post_meta( $orphan, 'geo_public', true ), 'Drafts are cleaned up too.' );
		$this->assertSame( '1', get_post_meta( $kept, 'geo_public', true ) );
		$this->assertEquals( 1, get_option( Loc_Config::DATA_CLEANUP_OPTION ) );
		$this->assertFalse( wp_next_scheduled( Loc_Config::DATA_CLEANUP_HOOK ) );
	}

	/**
	 * The cleanup is scheduled once, and not after it has finished.
	 */
	public function test_schedule_data_cleanup() {
		wp_clear_scheduled_hook( Loc_Config::DATA_CLEANUP_HOOK );
		delete_option( Loc_Config::DATA_CLEANUP_OPTION );

		Loc_Config::schedule_data_cleanup();
		$scheduled = wp_next_scheduled( Loc_Config::DATA_CLEANUP_HOOK );
		$this->assertNotFalse( $scheduled );
		Loc_Config::schedule_data_cleanup();
		$this->assertSame( $scheduled, wp_next_scheduled( Loc_Config::DATA_CLEANUP_HOOK ), 'Not scheduled twice.' );

		wp_clear_scheduled_hook( Loc_Config::DATA_CLEANUP_HOOK );
		update_option( Loc_Config::DATA_CLEANUP_OPTION, 1 );
		Loc_Config::schedule_data_cleanup();
		$this->assertFalse( wp_next_scheduled( Loc_Config::DATA_CLEANUP_HOOK ), 'Not scheduled once finished.' );
	}

	/**
	 * A full batch schedules the next run instead of finishing.
	 */
	public function test_data_cleanup_batches() {
		wp_clear_scheduled_hook( Loc_Config::DATA_CLEANUP_HOOK );
		delete_option( Loc_Config::DATA_CLEANUP_OPTION );
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		self::factory()->post->create_many(
			Loc_Config::DATA_CLEANUP_BATCH + 1,
			array(
				'post_author' => $author,
				'meta_input'  => array( 'geo_public' => 1 ),
			)
		);

		Loc_Config::run_data_cleanup();
		$this->assertFalse( get_option( Loc_Config::DATA_CLEANUP_OPTION ) );
		$this->assertNotFalse( wp_next_scheduled( Loc_Config::DATA_CLEANUP_HOOK ), 'The next batch is scheduled.' );

		wp_clear_scheduled_hook( Loc_Config::DATA_CLEANUP_HOOK );
		Loc_Config::run_data_cleanup();
		$this->assertEquals( 1, get_option( Loc_Config::DATA_CLEANUP_OPTION ) );
		$this->assertFalse( wp_next_scheduled( Loc_Config::DATA_CLEANUP_HOOK ) );
	}

		/**
	 * Tab links point at the settings page and mark the active tab.
	 */
	public function test_tab_link() {
		$html = $this->capture( array( 'Loc_Config', 'tab_link' ), 'api', 'API Keys', 'api' );
		$this->assertStringContainsString( 'tab=api', $html );
		$this->assertStringContainsString( 'class="nav-tab nav-tab-active">API Keys</a>', $html );
		$this->assertStringContainsString( 'class="nav-tab">General</a>', $this->capture( array( 'Loc_Config', 'tab_link' ), 'general', 'General', 'api' ) );
	}

	/**
	 * Field callbacks print inputs holding the saved values.
	 */
	public function test_field_callbacks() {
		update_option( 'sloc_test_option', '1' );
		$html = $this->capture( array( 'Loc_Config', 'checkbox_callback' ), array( 'label_for' => 'sloc_test_option' ) );
		$this->assertStringContainsString( '<input name="sloc_test_option" type="hidden" value="0" />', $html );
		$this->assertMatchesRegularExpression( '/<input name="sloc_test_option" type="checkbox" value="1"\s+checked/', $html );

		update_option( 'sloc_test_option', 42 );
		$this->assertSame( '<input name="sloc_test_option" type="number" min="0" step="1" size="4" class="small-text" value="42" />', $this->capture( array( 'Loc_Config', 'number_callback' ), array( 'label_for' => 'sloc_test_option' ) ) );

		update_option( 'sloc_test_option', 'a "quoted" value' );
		$html = $this->capture( array( 'Loc_Config', 'string_callback' ), array( 'label_for' => 'sloc_test_option' ) );
		$this->assertStringContainsString( 'type="text" value="a &quot;quoted&quot; value"', $html );
		$html = $this->capture(
			array( 'Loc_Config', 'string_callback' ),
			array(
				'label_for' => 'sloc_test_option',
				'type'      => 'url',
			)
		);
		$this->assertStringContainsString( 'type="url"', $html );
		$html = $this->capture(
			array( 'Loc_Config', 'string_callback' ),
			array(
				'label_for' => 'sloc_test_option',
				'type'      => 'password',
			)
		);
		$this->assertStringContainsString( 'type="password"', $html );
		$this->assertStringContainsString( 'data-lpignore="true"', $html );

		update_option( 'sloc_test_option', '<b>bold</b><script>x</script>' );
		$this->assertSame( '<textarea name="sloc_test_option" class="regular-text"><b>bold</b>x</textarea>', $this->capture( array( 'Loc_Config', 'textarea_callback' ), array( 'label_for' => 'sloc_test_option' ) ) );
	}

	/**
	 * Provider lists start with None, mark the saved provider, and describe it.
	 */
	public function test_provider_callback() {
		update_option( 'sloc_map_provider', 'google' );
		$html = $this->capture(
			array( 'Loc_Config', 'provider_callback' ),
			array(
				'label_for'   => 'sloc_map_provider',
				'description' => 'Pick one',
				'providers'   => Loc_Config::map_providers(),
			)
		);
		$this->assertMatchesRegularExpression( '/^<select name="sloc_map_provider"><option value="none"\s*>None<\/option>/', $html );
		$this->assertMatchesRegularExpression( '/<option value="google"\s+selected/', $html );
		$this->assertStringContainsString( '<p class="description">Pick one</p>', $html );
		$this->assertStringContainsString( '<a href="https://developers.google.com/maps/">Google Maps</a>', $html );

		// Providers given as plain names.
		$html = $this->capture(
			array( 'Loc_Config', 'provider_callback' ),
			array(
				'label_for' => 'sloc_geolocation_provider',
				'providers' => array( 'HTML5' => 'Browser' ),
			)
		);
		$this->assertMatchesRegularExpression( '/<option value="HTML5"[^>]*>Browser<\/option>/', $html );
	}

	/**
	 * A provider's description is escaped.
	 */
	public function test_provider_callback_escapes_description() {
		update_option( 'sloc_map_provider', 'evil' );
		$html = $this->capture(
			array( 'Loc_Config', 'provider_callback' ),
			array(
				'label_for' => 'sloc_map_provider',
				'providers' => array(
					'evil' => array(
						'name'        => 'Evil',
						'url'         => 'https://evil.example.com/',
						'description' => '<script>alert(1)</script>',
					),
				),
			)
		);
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
	}

	/**
	 * The default visibility field offers private, public and protected as stored numbers, with the saved one selected.
	 */
	public function test_visibility_callback() {
		update_option( 'geo_public', 2 );
		$html = $this->capture( array( 'Loc_Config', 'visibility_callback' ), array( 'label_for' => 'geo_public' ) );
		$this->assertStringContainsString( '<select name="geo_public">', $html );
		$this->assertMatchesRegularExpression( '/<option value="0"\s*>Private<\/option>/', $html );
		$this->assertMatchesRegularExpression( '/<option value="1"\s*>Public<\/option>/', $html );
		$this->assertMatchesRegularExpression( '/<option value="2"\s+selected(=["\']selected["\'])?>Protected<\/option>/', $html );
		$this->assertStringNotContainsString( 'None', $html );

		// A name saved by the old form selects the matching number.
		remove_all_filters( 'sanitize_option_geo_public' );
		update_option( 'geo_public', 'public' );
		$html = $this->capture( array( 'Loc_Config', 'visibility_callback' ), array( 'label_for' => 'geo_public' ) );
		$this->assertMatchesRegularExpression( '/<option value="1"\s+selected(=["\']selected["\'])?>Public<\/option>/', $html );
	}

	/**
	 * Saving the default visibility stores a number, whatever form it arrives in.
	 */
	public function test_visibility_saved_as_number() {
		Loc_Config::register_general_settings();
		foreach ( array(
			'1'         => 1,
			'0'         => 0,
			'protected' => 2,
			'public'    => 1,
			'none'      => 0,
		) as $input => $stored ) {
			update_option( 'geo_public', (string) $input );
			$this->assertSame( $stored, get_option( 'geo_public' ), "Saving '$input'" );
		}
	}

	/**
	 * Unit, country, style, value and select lists mark the saved choice.
	 */
	public function test_select_callbacks() {
		update_option( 'sloc_measurements', 'imperial' );
		$html = $this->capture( array( 'Loc_Config', 'measure_callback' ), array() );
		$this->assertMatchesRegularExpression( '/<option value="imperial"\s+selected/', $html );
		$this->assertStringContainsString( '<option value="si"', $html );

		update_option( 'sloc_country', 'CA' );
		$html = $this->capture( array( 'Loc_Config', 'country_callback' ), array( 'label_for' => 'sloc_country' ) );
		$this->assertMatchesRegularExpression( '/<option value="CA"\s+selected/', $html );
		$this->assertStringContainsString( 'Canada</option>', $html );
		$this->assertStringContainsString( '<option value="US"', $html );

		update_option( 'sloc_google_style', 'terrain' );
		$html = $this->capture(
			array( 'Loc_Config', 'style_callback' ),
			array(
				'label_for' => 'sloc_google_style',
				'provider'  => new Map_Provider_Google(),
			)
		);
		$this->assertMatchesRegularExpression( '/<option value="terrain"\s+selected/', $html );

		update_option( 'sloc_zoom', 16 );
		$html = $this->capture(
			array( 'Loc_Config', 'values_callback' ),
			array(
				'label_for' => 'sloc_zoom',
				'list'      => Loc_Config::get_zoom_levels(),
			)
		);
		$this->assertMatchesRegularExpression( '/<option value="16"\s+selected(=["\']selected["\'])?>Street<\/option>/', $html );

		$html = $this->capture(
			array( 'Loc_Config', 'values_callback' ),
			array(
				'label_for' => 'sloc_zoom',
				'list'      => new WP_Error( 'no_list', 'No list available' ),
			)
		);
		$this->assertSame( 'No list available', $html );

		$html = $this->capture(
			array( 'Loc_Config', 'select_callback' ),
			'pick',
			'b',
			array(
				'a' => 'A & a',
				'b' => 'B',
			)
		);
		$this->assertMatchesRegularExpression( '/^<select name="pick"><option value="a"\s*>A &amp; a<\/option>/', $html );
		$this->assertMatchesRegularExpression( '/<option value="b"\s+selected(=["\']selected["\'])?>B<\/option>/', $html );
	}

	/**
	 * Style lists that fail show the error.
	 */
	public function test_style_callback_error() {
		$provider = $this->getMockBuilder( 'Map_Provider_Google' )->onlyMethods( array( 'get_styles' ) )->getMock();
		$provider->method( 'get_styles' )->willReturn( new WP_Error( 'denied', 'Styles unavailable' ) );
		$html = $this->capture(
			array( 'Loc_Config', 'style_callback' ),
			array(
				'label_for' => 'sloc_google_style',
				'provider'  => $provider,
			)
		);
		$this->assertSame( 'Styles unavailable', $html );
	}

	/**
	 * Section headers print their introductions.
	 */
	public function test_section_headers() {
		$this->assertSame( '', $this->capture( array( 'Loc_Config', 'sloc_general_settings' ) ) );
		$this->assertStringContainsString( 'Third Party Services', $this->capture( array( 'Loc_Config', 'sloc_provider_settings' ) ) );
		$this->assertStringContainsString( 'display of maps', $this->capture( array( 'Loc_Config', 'sloc_map_settings' ) ) );
		$this->assertStringContainsString( 'API key', $this->capture( array( 'Loc_Config', 'sloc_api_settings' ) ) );
	}

	/**
	 * Zoom levels and aspect ratios have labels, and aspect ratios can be filtered.
	 */
	public function test_zoom_levels_and_aspect_ratios() {
		$this->assertSame( 'Street', Loc_Config::get_zoom_levels()['16'] );
		$this->assertArrayHasKey( '1.77777777778', Loc_Config::get_default_aspect_ratio() );

		$filter = function ( $ratios ) {
			$ratios['2'] = 'Wide';
			return $ratios;
		};
		add_filter( 'default_sloc_aspect_ratios', $filter );
		$this->assertSame( 'Wide', Loc_Config::get_default_aspect_ratio()['2'] );
		remove_filter( 'default_sloc_aspect_ratios', $filter );

		$this->assertSame( Geo_Base::geo_public(), Loc_Config::geo_public() );
		$this->assertArrayHasKey( 'option', Loc_Config::kses_option() );
	}

	/**
	 * Only provider objects can be registered.
	 */
	public function test_register_provider() {
		$this->assertFalse( Loc_Config::register_provider( new stdClass() ) );
		$this->assertFalse( Loc_Config::register_provider( 'Weather_Provider_Nwsus' ) );
	}

	/**
	 * Station lists include only providers that support stations.
	 */
	public function test_weather_providers_stations() {
		$all      = Loc_Config::weather_providers();
		$stations = Loc_Config::weather_providers( true );
		$this->assertArrayHasKey( 'openweathermap', $all );
		$this->assertArrayHasKey( 'nwsus', $stations );
		$this->assertArrayHasKey( 'station', $stations );
		$this->assertLessThan( count( $all ), count( $stations ) );
		foreach ( array_keys( $stations ) as $slug ) {
			$this->assertTrue( Loc_Config::weather_provider( $slug )->is_station(), $slug );
		}
	}

	/**
	 * The chosen provider of each type is returned, and an unknown choice is cleared.
	 */
	public function test_provider_getters() {
		$getters = array(
			'map_provider'         => array( 'sloc_map_provider', 'sloc_test', 'Map_Provider_Sloc_Test' ),
			'geo_provider'         => array( 'sloc_geo_provider', 'sloc_test', 'Geo_Provider_Sloc_Test' ),
			'geolocation_provider' => array( 'sloc_geolocation_provider', 'dummy', 'Location_Provider_Dummy' ),
			'weather_provider'     => array( 'sloc_weather_provider', 'sloc_test', 'Weather_Provider_Sloc_Test' ),
			'elevation_provider'   => array( 'sloc_elevation_provider', 'sloc_test', 'Elevation_Provider_Sloc_Test' ),
			'venue_provider'       => array( 'sloc_venue_provider', 'sloc_test', 'Venue_Provider_Sloc_Test' ),
		);
		foreach ( $getters as $method => list( $option, $slug, $class ) ) {
			update_option( $option, $slug );
			$this->assertInstanceOf( $class, call_user_func( array( 'Loc_Config', $method ) ), $method );

			update_option( $option, 'not-a-provider' );
			$this->assertNull( call_user_func( array( 'Loc_Config', $method ) ), $method );
			$this->assertNotSame( 'not-a-provider', get_option( $option ), $method );
		}

		// Providers can also be asked for by slug.
		$this->assertInstanceOf( 'Geo_Provider_Nominatim', Loc_Config::geo_provider( 'nominatim' ) );
		$this->assertInstanceOf( 'Weather_Provider_Nwsus', Loc_Config::weather_provider( 'nwsus' ) );
		$this->assertInstanceOf( 'Elevation_Provider_Google', Loc_Config::elevation_provider( 'google' ) );
		$this->assertInstanceOf( 'Venue_Provider_Nominatim', Loc_Config::venue_provider( 'nominatim' ) );
		$this->assertInstanceOf( 'Location_Provider_Compass', Loc_Config::geolocation_provider( 'compass' ) );

		// The browser provider has no server-side object.
		update_option( 'sloc_geolocation_provider', 'HTML5' );
		$this->assertNull( Loc_Config::geolocation_provider() );
		$this->assertSame( 'HTML5', get_option( 'sloc_geolocation_provider' ) );
		$this->assertNull( Loc_Config::geolocation_provider( 'HTML5' ) );
	}

	/**
	 * Sites that chose a MapQuest elevation provider, which were removed, have the choice cleared.
	 */
	public function test_removed_mapquest_elevation_providers() {
		foreach ( array( 'mapquest', 'openmapquest' ) as $slug ) {
			update_option( 'sloc_elevation_provider', $slug );
			$this->assertNull( Loc_Config::elevation_provider(), $slug );
			$this->assertNotSame( $slug, get_option( 'sloc_elevation_provider' ), $slug );
		}
		// MapQuest is still a map and geocoding provider.
		$this->assertArrayHasKey( 'mapquest', Loc_Config::map_providers() );
		$this->assertInstanceOf( 'Geo_Provider_Mapquest', Loc_Config::geo_provider( 'mapquest' ) );
	}

	/**
	 * Sites that chose Bing, which was removed, have the choice cleared and show no map.
	 */
	public function test_removed_bing_provider() {
		foreach ( array( 'map_provider', 'geo_provider', 'elevation_provider' ) as $method ) {
			$option = 'sloc_' . $method;
			update_option( $option, 'bing' );
			$this->assertNull( call_user_func( array( 'Loc_Config', $method ) ), $method );
			$this->assertNotSame( 'bing', get_option( $option ), $method );
		}

		update_option( 'sloc_map_provider', 'bing' );
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
		$this->assertSame( '', Geo_Data::get_map( 'post', $post_id ) );
	}

	/**
	 * Asking for a provider that isn't registered returns nothing and leaves the site's choice alone.
	 */
	public function test_provider_getters_unknown_slug() {
		$getters = array(
			'geo_provider'         => array( 'sloc_geo_provider', 'sloc_test' ),
			'geolocation_provider' => array( 'sloc_geolocation_provider', 'dummy' ),
			'weather_provider'     => array( 'sloc_weather_provider', 'sloc_test' ),
			'elevation_provider'   => array( 'sloc_elevation_provider', 'sloc_test' ),
			'venue_provider'       => array( 'sloc_venue_provider', 'sloc_test' ),
		);
		foreach ( $getters as $method => list( $option, $slug ) ) {
			update_option( $option, $slug );
			$this->assertNull( call_user_func( array( 'Loc_Config', $method ), 'not-a-provider' ), $method );
			$this->assertNull( call_user_func( array( 'Loc_Config', $method ), array( 'not', 'a', 'slug' ) ), $method );
			$this->assertSame( $slug, get_option( $option ), $method );
		}
	}

	/**
	 * The marker settings are registered with the map settings, for every map provider, with fields.
	 */
	public function test_marker_settings() {
		global $wp_settings_fields;
		require_once ABSPATH . 'wp-admin/includes/template.php';

		Loc_Config::register_map_settings();
		$settings = get_registered_settings();
		$expected = array(
			'sloc_marker'       => 'pin',
			'sloc_marker_color' => '#d32f2f',
			'sloc_marker_size'       => 'medium',
			'sloc_scalebar'          => '',
			'sloc_scalebar_position' => 'bottomleft',
		);
		foreach ( $expected as $name => $default ) {
			$this->assertArrayHasKey( $name, $settings, $name );
			$this->assertSame( 'simloc', $settings[ $name ]['group'], $name );
			$this->assertSame( $default, $settings[ $name ]['default'], $name );
		}
		$this->assertSame( 'dot', sanitize_option( 'sloc_marker', 'Dot' ) );
		$this->assertSame( '#43a047', sanitize_option( 'sloc_marker_color', '43A047' ) );
		$this->assertSame( 'medium', sanitize_option( 'sloc_marker_size', 'huge' ) );

		Loc_Config::admin_init();
		$fields = $wp_settings_fields['simloc']['sloc_map'];
		$this->assertSame( array( 'pin', 'dot' ), array_keys( $fields['sloc_marker']['args']['list'] ) );
		$this->assertSame( array( 'small', 'medium', 'large' ), array_keys( $fields['sloc_marker_size']['args']['list'] ) );
		$this->assertSame( 'color', $fields['sloc_marker_color']['args']['type'] );
		$this->assertSame( array( '', 'site', 'metric', 'imperial', 'both' ), array_keys( $fields['sloc_scalebar']['args']['list'] ) );
		$this->assertSame( array( 'bottomleft', 'bottomright', 'topleft', 'topright' ), array_keys( $fields['sloc_scalebar_position']['args']['list'] ) );
		$this->assertSame( 'metric', sanitize_option( 'sloc_scalebar', 'Metric' ) );
		$this->assertSame( 'bottomleft', sanitize_option( 'sloc_scalebar_position', 'middle' ) );
	}

	/**
	 * The custom static map's marker, scale bar and attribution settings from 6.1.0 development move to the settings every provider uses.
	 */
	public function test_migrate_map_settings() {
		// The settings are registered, with defaults, as on a real site.
		Loc_Config::register_map_settings();
		update_option( 'sloc_staticmap_marker', 'dot' );
		update_option( 'sloc_staticmap_marker_color', '#43a047' );
		update_option( 'sloc_staticmap_marker_size', 'large' );
		update_option( 'sloc_staticmap_scalebar', 'both' );
		update_option( 'sloc_staticmap_scalebar_position', 'topright' );
		update_option( 'sloc_staticmap_attribution', 'below' );
		update_option( 'sloc_staticmap_attribution_position', 'topleft' );
		// A setting already saved under the new name is kept.
		update_option( 'sloc_marker_size', 'small' );

		Loc_Config::migrate_map_settings();
		$this->assertSame( 'dot', get_option( 'sloc_marker' ) );
		$this->assertSame( '#43a047', get_option( 'sloc_marker_color' ) );
		$this->assertSame( 'small', get_option( 'sloc_marker_size' ) );
		$this->assertSame( 'both', get_option( 'sloc_scalebar' ) );
		$this->assertSame( 'topright', get_option( 'sloc_scalebar_position' ) );
		$this->assertSame( 'below', get_option( 'sloc_attribution' ) );
		$this->assertSame( 'topleft', get_option( 'sloc_attribution_position' ) );
		foreach ( array( 'marker', 'marker_color', 'marker_size', 'scalebar', 'scalebar_position', 'attribution', 'attribution_position' ) as $name ) {
			$this->assertNull( get_option( 'sloc_staticmap_' . $name, null ), $name );
		}

		// Without old settings, nothing is added.
		delete_option( 'sloc_marker' );
		Loc_Config::migrate_map_settings();
		$this->assertNull( get_option( 'sloc_marker', null ) );
	}
}

<?php
/**
 * Tests for the API Keys settings tab.
 *
 * @package Simple_Location
 */

/**
 * API Keys tab tests.
 */
class ApiSettingsTest extends WP_UnitTestCase {

	/**
	 * The API Keys tab, as an administrator.
	 */
	public function set_up() {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/template.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'sloc_weather_provider', 'weatherbit' );
		update_option( 'sloc_geo_provider', 'nominatim' );
		update_option( 'sloc_map_provider', 'none' );
		update_option( 'sloc_elevation_provider', 'none' );
		$_GET = array(
			'page' => 'simloc',
			'tab'  => 'api',
		);
	}

	/**
	 * Clean up the request.
	 */
	public function tear_down() {
		global $pagenow;
		$_GET    = array();
		$_POST   = array();
		$pagenow = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		parent::tear_down();
	}

	/**
	 * Echoes the tab's sections after loading every provider's settings.
	 *
	 * @return string HTML.
	 */
	protected function render() {
		Loc_Config::load_api_settings();
		Loc_Config::admin_init();
		ob_start();
		Loc_Config::api_settings_sections();
		return ob_get_clean();
	}

	/**
	 * A setting's group is the word after sloc_ in its option.
	 */
	public function test_api_group() {
		$this->assertSame( 'google', Sloc_Provider::api_group( 'sloc_google_map_secret' ) );
		$this->assertSame( 'aeris', Sloc_Provider::api_group( 'sloc_aeris_pws' ) );
		$this->assertSame( 'openweathermap', Sloc_Provider::api_group( 'sloc_openweathermap_api' ) );
		$this->assertSame( 'other', Sloc_Provider::api_group( 'other' ) );
	}

	/**
	 * Only the API Keys tab, and saving its form, is an API settings request.
	 */
	public function test_is_api_settings_request() {
		global $pagenow;
		$this->assertTrue( Loc_Config::is_api_settings_request() );
		$_GET['tab'] = 'providers';
		$this->assertFalse( Loc_Config::is_api_settings_request() );
		$_GET = array();
		$this->assertFalse( Loc_Config::is_api_settings_request() );

		$pagenow = 'options.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$_POST   = array( 'option_page' => 'sloc_api' );
		$this->assertTrue( Loc_Config::is_api_settings_request() );
		$_POST['option_page'] = 'simloc';
		$this->assertFalse( Loc_Config::is_api_settings_request() );
	}

	/**
	 * Every provider's API settings are registered on the API Keys tab, not only the ones in use; not elsewhere.
	 */
	public function test_all_settings_registered() {
		global $wp_registered_settings;
		// Removed directly: on WordPress 6.2 and ClassicPress, unregister_setting() warns for a group not saved yet.
		unset( $wp_registered_settings['sloc_weatherbit_api'] );
		$_GET['tab'] = 'general';
		Loc_Config::load_api_settings();
		$this->assertArrayNotHasKey( 'sloc_weatherbit_api', get_registered_settings(), 'Not on other tabs.' );

		$_GET['tab'] = 'api';
		Loc_Config::load_api_settings();
		$settings = get_registered_settings();
		foreach ( array( 'sloc_weatherbit_api', 'sloc_openweathermap_api', 'sloc_google_api', 'sloc_mapbox_api', 'sloc_here_api', 'sloc_aeris_client_id', 'sloc_staticmap_url' ) as $option ) {
			$this->assertArrayHasKey( $option, $settings, $option );
			$this->assertSame( 'sloc_api', $settings[ $option ]['group'], $option );
		}
	}

	/**
	 * Each service is a section that opens and closes; those in use are open and first, then the others by name.
	 */
	public function test_sections() {
		$html = $this->render();
		$this->assertStringContainsString( '<h2>API Keys</h2>', $html );

		$this->assertMatchesRegularExpression( '#<details class="sloc-api-group" id="sloc-api-weatherbit" open><summary>WeatherBit <span class="sloc-api-in-use">In use</span></summary>#', $html );
		$this->assertMatchesRegularExpression( '#<details class="sloc-api-group" id="sloc-api-openweathermap"><summary>OpenWeatherMap</summary>#', $html );
		$this->assertStringContainsString( 'name="sloc_openweathermap_api"', $html );
		$this->assertStringContainsString( 'name="sloc_weatherbit_api"', $html );

		preg_match_all( '#<details class="sloc-api-group" id="sloc-api-([a-z]+)"( open)?><summary>([^<]+)#', $html, $matches );
		$this->assertSame( 'weatherbit', $matches[1][0], 'In use first.' );
		$names = array_map( 'trim', array_slice( $matches[3], 1 ) );
		$this->assertSame( array( 'WeatherBit' ), array_map( 'trim', array_slice( $matches[3], 0, 1 ) ) );
		$sorted = $names;
		usort( $sorted, fn( $a, $b ) => strcmp( strtolower( $a ), strtolower( $b ) ) );
		$this->assertSame( $sorted, $names, 'The others by name.' );
		$this->assertSame( count( $matches[1] ), count( array_unique( $matches[1] ) ), 'One section per service.' );
		$this->assertSame( 1, substr_count( $html, ' open>' ), 'Only the services in use are open.' );

		// Every API field is inside a section, so every registered API setting is in the form and saved.
		$this->assertSame( substr_count( $html, '<details' ), substr_count( $html, '</details>' ) );
		$this->assertStringNotContainsString( '<table class="form-table" role="presentation"></table>', $html );
	}

	/**
	 * A service's settings share its section, such as Google's key and signing secret, and Xweather's options.
	 */
	public function test_service_settings_together() {
		$html = $this->render();
		preg_match( '#<details class="sloc-api-group" id="sloc-api-google".*?</details>#s', $html, $google );
		$this->assertStringContainsString( 'sloc_google_api', $google[0] );
		$this->assertStringContainsString( 'sloc_google_map_secret', $google[0] );

		// #686: the personal weather stations option shows, with Xweather's credentials.
		preg_match( '#<details class="sloc-api-group" id="sloc-api-aeris".*?</details>#s', $html, $aeris );
		$this->assertStringContainsString( '<summary>Xweather</summary>', $aeris[0] );
		$this->assertStringContainsString( 'sloc_aeris_client_id', $aeris[0] );
		$this->assertStringContainsString( 'sloc_aeris_client_secret', $aeris[0] );
		$this->assertStringContainsString( 'sloc_aeris_pws', $aeris[0] );
		$this->assertSame( 'sloc_api', get_registered_settings()['sloc_aeris_pws']['group'], 'It is saved with the API settings.' );
	}

	/**
	 * A service is in use when any provider in use is from it, such as Google for maps but not geocoding.
	 */
	public function test_in_use() {
		update_option( 'sloc_map_provider', 'google' );
		$html = $this->render();
		$this->assertStringContainsString( 'id="sloc-api-google" open><summary>Google <span class="sloc-api-in-use">', $html );
		$this->assertStringContainsString( 'id="sloc-api-weatherbit" open>', $html );
		$this->assertSame( 2, substr_count( $html, ' open>' ) );
	}

	/**
	 * The settings page shows the sections on the API Keys tab.
	 */
	public function test_options_page() {
		Loc_Config::load_api_settings();
		Loc_Config::admin_init();
		ob_start();
		Loc_Config::simloc_options();
		$html = ob_get_clean();
		$this->assertStringContainsString( "name='option_page' value='sloc_api'", $html );
		$this->assertStringContainsString( '<details class="sloc-api-group"', $html );
	}
}

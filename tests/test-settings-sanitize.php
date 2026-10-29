<?php
/**
 * Tests the sanitize callbacks of the plugin settings (#770).
 *
 * @package Simple_Location
 */

class SettingsSanitizeTest extends WP_UnitTestCase {

	public function test_every_setting_has_a_sanitize_callback() {
		// Settings that register only when their provider is the active one.
		Map_Provider_Google::init();
		Geo_Provider_Pelias::init();
		Weather_Provider_Aeris::init();
		Sloc_Map_Cache::init();
		$settings = get_registered_settings();
		foreach ( $settings as $name => $setting ) {
			if ( ! in_array( $setting['group'], array( 'simloc', 'sloc_providers', 'sloc_api' ), true ) ) {
				continue;
			}
			$this->assertTrue( is_callable( $setting['sanitize_callback'] ?? null ), $name );
		}
		foreach ( array( 'sloc_google_style', 'sloc_pelias_url', 'sloc_aeris_pws', 'sloc_map_cache', 'sloc_last_report', 'sloc_country', 'sloc_measurements', 'sloc_map_provider', 'sloc_geolocation_provider', 'sloc_width', 'sloc_aspect', 'sloc_zoom', 'sloc_altitude', 'sloc_map_dark_mode' ) as $name ) {
			$this->assertArrayHasKey( $name, $settings, $name );
		}
	}

	/**
	 * @dataProvider boolean_settings
	 */
	public function test_boolean_settings( $option ) {
		update_option( $option, 'false' );
		$this->assertFalse( (bool) get_option( $option ), $option );
		update_option( $option, '1' );
		$this->assertTrue( (bool) get_option( $option ), $option );
	}

	public function boolean_settings() {
		return array(
			array( 'sloc_last_report' ),
			array( 'sloc_auto_micropub' ),
			array( 'sloc_taxonomy_display' ),
			array( 'sloc_map_display' ),
			array( 'sloc_map_dark_mode' ),
			array( 'sloc_zoom_always' ),
		);
	}

	/**
	 * @dataProvider provider_settings
	 */
	public function test_provider_settings( $option, $valid ) {
		update_option( $option, $valid );
		$this->assertSame( $valid, get_option( $option ), $option );
		update_option( $option, 'none' );
		$this->assertSame( 'none', get_option( $option ), $option );
		// An unknown provider never becomes another service.
		update_option( $option, '<b>not-a-provider</b>' );
		$this->assertSame( 'none', get_option( $option ), $option );
		update_option( $option, array( $valid ) );
		$this->assertSame( 'none', get_option( $option ), $option );
	}

	public function provider_settings() {
		return array(
			array( 'sloc_map_provider', 'staticmap' ),
			array( 'sloc_geo_provider', 'nominatim' ),
			array( 'sloc_geolocation_provider', 'HTML5' ),
			array( 'sloc_geolocation_provider', 'dummy' ),
			array( 'sloc_elevation_provider', 'opentopodata' ),
			array( 'sloc_venue_provider', 'overpass' ),
			array( 'sloc_weather_provider', 'openmeteo' ),
		);
	}

	public function test_provider_kept_before_providers_load() {
		$method = new ReflectionMethod( 'Loc_Config', 'sanitize_provider' );
		if ( PHP_VERSION_ID < 80100 ) {
			// Needed for a protected method before PHP 8.1, deprecated in 8.5.
			$method->setAccessible( true );
		}
		$this->assertSame( 'nominatim', $method->invoke( null, 'nominatim', array() ) );
		$this->assertSame( 'none', $method->invoke( null, 'nominatim', array( 'photon' => array() ) ) );
	}

	/**
	 * @dataProvider country_values
	 */
	public function test_sanitize_country( $value, $expected ) {
		$this->assertSame( $expected, Loc_Config::sanitize_country( $value ) );
	}

	public function country_values() {
		return array(
			'code'       => array( 'CA', 'CA' ),
			'lower case' => array( ' gb ', 'GB' ),
			'none'       => array( '', '' ),
			'unknown'    => array( 'ZZ', 'US' ),
			'too long'   => array( 'USA', 'US' ),
			'markup'     => array( '<b>', 'US' ),
			'not string' => array( array( 'CA' ), '' ),
		);
	}

	public function test_country_option() {
		update_option( 'sloc_country', 'de' );
		$this->assertSame( 'DE', get_option( 'sloc_country' ) );
	}

	public function test_sanitize_measurements() {
		$this->assertSame( 'si', Loc_Config::sanitize_measurements( 'si' ) );
		$this->assertSame( 'metric', Loc_Config::sanitize_measurements( 'metric' ) );
		$this->assertSame( 'imperial', Loc_Config::sanitize_measurements( 'imperial' ) );
		$this->assertSame( Loc_Config::measurement_default(), Loc_Config::sanitize_measurements( 'furlongs' ) );
		$this->assertSame( Loc_Config::measurement_default(), Loc_Config::sanitize_measurements( null ) );
	}

	public function test_sanitize_width() {
		$this->assertSame( 640, Loc_Config::sanitize_width( '640' ) );
		$this->assertSame( 641, Loc_Config::sanitize_width( '640.6' ) );
		$this->assertSame( Loc_Config::get_default_map_width(), Loc_Config::sanitize_width( '0' ) );
		$this->assertSame( Loc_Config::get_default_map_width(), Loc_Config::sanitize_width( '-5' ) );
		$this->assertSame( Loc_Config::get_default_map_width(), Loc_Config::sanitize_width( 'wide' ) );
	}

	public function test_sanitize_aspect() {
		$this->assertSame( '1.77777777778', Loc_Config::sanitize_aspect( '1.77777777778' ) );
		$this->assertSame( '1', Loc_Config::sanitize_aspect( 1 ) );
		$this->assertSame( '1.77777777778', Loc_Config::sanitize_aspect( '0' ) );
		$this->assertSame( '1.77777777778', Loc_Config::sanitize_aspect( 'square' ) );
		// The stored value still selects its choice in the list.
		update_option( 'sloc_aspect', '1.333333333' );
		$this->assertArrayHasKey( get_option( 'sloc_aspect' ), Loc_Config::get_default_aspect_ratio() );
	}

	public function test_sanitize_zoom() {
		$this->assertSame( 16, Loc_Config::sanitize_zoom( '16' ) );
		$this->assertSame( 20, Loc_Config::sanitize_zoom( '25' ) );
		$this->assertSame( 0, Loc_Config::sanitize_zoom( '-1' ) );
		$this->assertSame( 14, Loc_Config::sanitize_zoom( 'abc' ) );
	}

	public function test_sanitize_altitude() {
		$this->assertSame( 1000, Loc_Config::sanitize_altitude( '1000' ) );
		$this->assertSame( 0, Loc_Config::sanitize_altitude( '-20' ) );
		$this->assertSame( 500, Loc_Config::sanitize_altitude( 'high' ) );
	}

	public function test_number_options() {
		update_option( 'sloc_zoom', 'abc' );
		$this->assertSame( 14, (int) get_option( 'sloc_zoom' ) );
		update_option( 'sloc_altitude', '<script>' );
		$this->assertSame( 500, (int) get_option( 'sloc_altitude' ) );
	}

	public function test_sanitize_style() {
		$this->assertSame( 'mapbox/streets-v12', Map_Provider::sanitize_style( ' mapbox/streets-v12 ' ) );
		$this->assertSame( '', Map_Provider::sanitize_style( '' ) );
		$this->assertSame( '', Map_Provider::sanitize_style( '<script></script>' ) );
		$this->assertSame( '', Map_Provider::sanitize_style( array( 'x' ) ) );
	}

	public function test_style_option() {
		Map_Provider_Google::init();
		update_option( 'sloc_google_style', '<b>roadmap</b>' );
		$this->assertSame( 'roadmap', get_option( 'sloc_google_style' ) );
	}

	public function test_sanitize_pws() {
		$this->assertSame( 1, Weather_Provider_Aeris::sanitize_pws( '1' ) );
		$this->assertSame( 0, Weather_Provider_Aeris::sanitize_pws( '' ) );
		$this->assertSame( 0, Weather_Provider_Aeris::sanitize_pws( null ) );
	}

	public function test_pelias_settings_on_api_page() {
		Geo_Provider_Pelias::init();
		$settings = get_registered_settings();
		$this->assertSame( 'sloc_api', $settings['sloc_pelias_url']['group'] );
		update_option( 'sloc_pelias_url', ' https://pelias.example.com/v1 ' );
		$this->assertSame( 'https://pelias.example.com/v1', get_option( 'sloc_pelias_url' ) );
	}

	public function test_map_cache_and_nominatim_fallback_are_boolean() {
		Sloc_Map_Cache::init();
		update_option( 'sloc_map_cache', 'false' );
		$this->assertFalse( (bool) get_option( 'sloc_map_cache' ) );
		update_option( 'sloc_nominatim_fallback', 'yes' );
		$this->assertTrue( (bool) get_option( 'sloc_nominatim_fallback' ) );
	}
}

<?php
/**
 * Tests for the WeeWX weather provider, which reads weewx-current-json files (#676).
 *
 * @package Simple_Location
 */

/**
 * WeeWX provider tests.
 */
class WeewxProviderTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

	/**
	 * Known settings for every test.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_measurements', 'metric' );
		delete_option( 'sloc_weather_fallbacks' );
		delete_option( 'sloc_weewx_stations' );
		global $wp_settings_errors;
		$wp_settings_errors = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Returns the current conditions fixture, observed now.
	 *
	 * @return array
	 */
	protected function document() {
		$document         = $this->fixture( 'weather/station-current-conditions.json' );
		$document['time'] = time();
		return $document;
	}

	/**
	 * The provider is registered as a station provider.
	 */
	public function test_registered() {
		$providers = Loc_Config::weather_providers( true );
		$this->assertArrayHasKey( 'weewx', $providers );
		$this->assertSame( 'WeeWX', $providers['weewx']['name'] );
		$this->assertTrue( ( new Weather_Provider_Weewx() )->is_station() );
	}

	/**
	 * Saving reads each new station's file for its ID, name and location, and keeps the URL and credit.
	 */
	public function test_saving_reads_the_file() {
		$this->mock_http( array( 'weather.example.com/current.json' => array( 'body' => $this->document() ) ) );
		$stations = Weather_Provider_Weewx::sanitize_stations(
			array(
				array(
					'url'         => ' https://weather.example.com/current.json ',
					'attribution' => 'Weather from my backyard',
				),
				array( 'url' => '' ),
			)
		);
		$this->assertCount( 1, $stations );
		$this->assertSame( 'backyard', $stations[0]['id'] );
		$this->assertSame( 'Backyard Weather', $stations[0]['name'] );
		$this->assertSame( 39.9524, $stations[0]['latitude'] );
		$this->assertSame( -75.1636, $stations[0]['longitude'] );
		$this->assertSame( 'https://weather.example.com/current.json', $stations[0]['url'] );
		$this->assertSame( 'Weather from my backyard', $stations[0]['attribution'] );
		$this->assertCount( 1, $this->http_requests );

		// Saved again by the plugin, with an ID, it isn't read again.
		Weather_Provider_Weewx::sanitize_stations( $stations );
		$this->assertCount( 1, $this->http_requests );
	}

	/**
	 * A file that can't be read is reported, and the station kept with an ID from its URL.
	 */
	public function test_saving_unreadable_file() {
		$this->mock_http( array( 'weather.example.com' => array( 'body' => array( 'temperature' => 20 ) ) ) );
		$stations = Weather_Provider_Weewx::sanitize_stations( array( array( 'url' => 'https://weather.example.com/current.json' ) ) );
		$this->assertSame( 'weather-example-com', $stations[0]['id'] );
		$this->assertSame( '', $stations[0]['latitude'] );
		$errors = get_settings_errors( 'sloc_weewx_stations' );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'This is not a weewx-current-json file', $errors[0]['message'] );
	}

	/**
	 * Saving the URL of an archive file, such as history-week.json, says to enter current.json instead (#812).
	 */
	public function test_saving_archive_file() {
		global $wp_settings_errors;
		$wp_settings_errors = array();
		$this->mock_http(
			array(
				'weather.example.com' => array(
					'body' => array(
						'format'  => 'current-conditions-history',
						'version' => 1,
					),
				),
			)
		);
		Weather_Provider_Weewx::sanitize_stations( array( array( 'url' => 'https://weather.example.com/history-week.json' ) ) );
		$errors = get_settings_errors( 'sloc_weewx_stations' );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'https://weather.example.com/history-week.json', $errors[0]['message'] );
		$this->assertStringContainsString( 'archive files', $errors[0]['message'] );
		$this->assertStringContainsString( 'current.json', $errors[0]['message'] );
		$this->assertStringNotContainsString( 'unknown format', $errors[0]['message'] );
	}

	/**
	 * Two stations with the same ID get unique IDs, and removed rows are dropped.
	 */
	public function test_unique_ids() {
		$this->mock_http( array( 'example.com' => array( 'body' => $this->document() ) ) );
		$stations = Weather_Provider_Weewx::sanitize_stations(
			array(
				array( 'url' => 'https://one.example.com/current.json' ),
				array( 'url' => 'https://two.example.com/current.json' ),
				array(
					'url'    => 'https://three.example.com/current.json',
					'remove' => '1',
				),
			)
		);
		$this->assertSame( array( 'backyard', 'backyard-2' ), wp_list_pluck( $stations, 'id' ) );
	}

	/**
	 * A WeeWX station gives the weather by ID, or as the nearest station, with its credit.
	 */
	public function test_conditions() {
		$this->mock_http( array( 'weather.example.com' => array( 'body' => $this->document() ) ) );
		update_option( 'sloc_weewx_stations', Weather_Provider_Weewx::sanitize_stations( array( array( 'url' => 'https://weather.example.com/current.json' ) ) ) );

		$provider = new Weather_Provider_Weewx();
		$provider->set( array( 'station_id' => 'backyard' ) );
		$conditions = $provider->get_conditions();
		$this->assertSame( 21.5, $conditions['temperature'] );
		$this->assertSame( 503, $conditions['code'] );
		$this->assertSame( 'weewx', $conditions['provider'] );
		$this->assertSame( 'Weather from Backyard Weather', $conditions['attribution'] );

		$provider = new Weather_Provider_Weewx();
		$provider->set( 39.95, -75.16 );
		$conditions = $provider->get_conditions();
		$this->assertSame( 21.5, $conditions['temperature'] );
		$this->assertLessThan( 1000, $conditions['distance'] );
	}

	/**
	 * Only the current conditions format is accepted, not the earlier custom station format.
	 */
	public function test_requires_the_format() {
		$this->assertSame( 'station_format', Weather_Provider_Weewx::parse_document( array( 'temperature' => 20 ) )->get_error_code() );
		$this->assertSame( 21.5, Weather_Provider_Weewx::parse_document( $this->document() )['temperature'] );
	}

	/**
	 * The WeeWX stations have their own setting and section on the Stations tab, beside the custom stations.
	 */
	public function test_settings() {
		global $wp_settings_sections, $wp_settings_fields;
		require_once ABSPATH . 'wp-admin/includes/template.php';

		Weather_Provider_Weewx::init();
		Weather_Provider_Station::init();
		$settings = get_registered_settings();
		$this->assertSame( 'sloc_stations', $settings['sloc_weewx_stations']['group'] );
		$this->assertSame( 'sloc_stations', $settings['sloc_stations']['group'] );

		Weather_Provider_Weewx::admin_init();
		Weather_Provider_Station::admin_init();
		$this->assertSame( 'WeeWX Stations', $wp_settings_sections['sloc_stations']['sloc_weewx_stations']['title'] );
		$this->assertSame( 'Custom Station Data', $wp_settings_sections['sloc_stations']['sloc_stations']['title'] );
		$this->assertArrayHasKey( 'sloc_weewx_stations', $wp_settings_fields['sloc_stations']['sloc_weewx_stations'] );

		update_option(
			'sloc_weewx_stations',
			array(
				array(
					'id'   => 'backyard',
					'name' => 'Backyard Weather',
					'url'  => 'https://weather.example.com/current.json',
				),
			)
		);
		ob_start();
		Weather_Provider_Weewx::stations_section();
		Weather_Provider_Weewx::stations_callback( array() );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'name="sloc_weewx_stations[0][url]" value="https://weather.example.com/current.json"', $html );
		$this->assertStringContainsString( 'Backyard Weather (backyard)', $html );
		$this->assertStringContainsString( 'name="sloc_weewx_stations[1][url]" value=""', $html );
		$this->assertStringNotContainsString( '[id]', $html, 'The ID is read from the file, not entered.' );
	}
}

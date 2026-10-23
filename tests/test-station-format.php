<?php
/**
 * Tests for the custom station provider's formats, settings and credit (#661, #672-#675, #677).
 *
 * @package Simple_Location
 */

/**
 * Station format tests.
 */
class StationFormatTest extends WP_UnitTestCase {
	use Sloc_Http_Mock;

	/**
	 * Philadelphia.
	 */
	const LAT = 39.9524;
	const LNG = -75.1636;

	/**
	 * Known settings for every test.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_measurements', 'metric' );
		delete_option( 'sloc_weather_fallbacks' );
		delete_option( 'sloc_weather_attribution' );
		delete_option( 'sloc_stations' );
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
	 * Saves one station.
	 *
	 * @param array $station Station fields.
	 */
	protected function station( $station = array() ) {
		update_option(
			'sloc_stations',
			array(
				array_merge(
					array(
						'id'  => 'backyard',
						'url' => 'https://station.example.com/current.json',
					),
					$station
				),
			)
		);
	}

	/**
	 * The current conditions format is read: observations in the plugin's units, the time, the code and the station.
	 */
	public function test_current_conditions() {
		$document = $this->document();
		$result   = Weather_Provider_Station::parse_document( $document );
		$this->assertSame( 21.5, $result['temperature'] );
		$this->assertSame( 3.05, $result['rainrate'] );
		$this->assertSame( 503, $result['code'] );
		$this->assertSame( $document['time'], $result['timestamp'] );
		$this->assertSame( 'backyard', $result['station_id'] );
		$this->assertSame( 'Backyard Weather', $result['name'] );
		$this->assertSame( 39.9524, $result['latitude'] );
		$this->assertSame( 12.0, $result['altitude'] );
		$this->assertSame( 'Weather from Backyard Weather', $result['attribution'] );
		$this->assertSame( 'https://weather.example.com/', $result['attribution_url'] );
		$this->assertArrayNotHasKey( 'derived', $result );
		$this->assertArrayNotHasKey( 'generator', $result );
	}

	/**
	 * US units are converted, unknown codes are ignored, and a summary gives a code when there is none.
	 */
	public function test_current_conditions_units_and_codes() {
		$document                 = $this->document();
		$document['units']        = 'us';
		$document['observations'] = array(
			'temperature' => 70.7,
			'pressure'    => 29.98,
			'windspeed'   => 8,
			'rain'        => 0.05,
			'rainrate'    => 0.12,
			'code'        => 999,
			'summary'     => 'Partly Cloudy',
			'bogus'       => 1,
		);
		$result                   = Weather_Provider_Station::parse_document( $document );
		$this->assertEqualsWithDelta( 21.5, $result['temperature'], 0.01 );
		$this->assertEqualsWithDelta( 1015.2, $result['pressure'], 0.1 );
		$this->assertEqualsWithDelta( 3.58, $result['windspeed'], 0.01 );
		$this->assertEqualsWithDelta( 1.27, $result['rain'], 0.01 );
		$this->assertEqualsWithDelta( 3.05, $result['rainrate'], 0.01 );
		$this->assertSame( 802, $result['code'], 'The unknown code is ignored, and the summary gives one.' );
		$this->assertArrayNotHasKey( 'bogus', $result );
	}

	/**
	 * Other formats and later versions are errors, not misread weather.
	 */
	public function test_unknown_formats() {
		$this->assertSame( 'station_format', Weather_Provider_Station::parse_document( array( 'format' => 'other' ) )->get_error_code() );
		$this->assertSame( 'station_format_version', Weather_Provider_Station::parse_document( array_merge( $this->document(), array( 'version' => 2 ) ) )->get_error_code() );
		$this->assertSame( 'station_format', Weather_Provider_Station::parse_document( 'text' )->get_error_code() );
	}

	/**
	 * The earlier format still works, and a wind object missing values doesn't warn (#674).
	 */
	public function test_legacy_format() {
		$result = Weather_Provider_Station::parse_document(
			array(
				'temperature' => 21.5,
				'wind'        => array(
					'speed'  => 3.6,
					'degree' => 230,
				),
				'rain'        => 'N/A',
				'summary'     => 'Overcast',
				'timestamp'   => 1000,
			)
		);
		$this->assertSame( 3.6, $result['windspeed'] );
		$this->assertSame( 230, $result['winddegree'] );
		$this->assertArrayNotHasKey( 'windgust', $result );
		$this->assertArrayNotHasKey( 'rain', $result );
		$this->assertSame( 804, $result['code'] );
		$this->assertSame( 1000, $result['timestamp'] );
	}

	/**
	 * A station in the current conditions format gives the weather, with its credit and coordinates learned.
	 */
	public function test_station_weather() {
		$this->station();
		$this->mock_http( array( 'station.example.com/current.json' => array( 'body' => $this->document() ) ) );
		$provider = new Weather_Provider_Station();
		$provider->set( array( 'station_id' => 'backyard' ) );
		$conditions = $provider->get_conditions();

		$this->assertSame( 21.5, $conditions['temperature'] );
		$this->assertSame( 'Weather from Backyard Weather', $conditions['attribution'] );
		$this->assertSame( 'station', $conditions['provider'] );
		$stations = get_option( 'sloc_stations' );
		$this->assertSame( 39.9524, $stations[0]['latitude'], 'Coordinates are learned from the station.' );
	}

	/**
	 * A credit in the settings replaces the station's own.
	 */
	public function test_settings_credit() {
		$this->station(
			array(
				'attribution'     => 'Weather from the club',
				'attribution_url' => 'https://club.example.org/',
			)
		);
		$this->mock_http( array( 'station.example.com' => array( 'body' => $this->document() ) ) );
		$provider = new Weather_Provider_Station();
		$provider->set( array( 'station_id' => 'backyard' ) );
		$conditions = $provider->get_conditions();
		$this->assertSame( 'Weather from the club', $conditions['attribution'] );
		$this->assertSame( 'https://club.example.org/', $conditions['attribution_url'] );
	}

	/**
	 * A station's credit is stored with its weather and shown with it, or in the footer.
	 */
	public function test_credit_display() {
		$post_id = self::factory()->post->create();
		set_post_weatherdata(
			$post_id,
			'',
			array(
				'temperature'     => 21.5,
				'rainrate'        => 3.05,
				'provider'        => 'station',
				'attribution'     => 'Weather from Backyard Weather',
				'attribution_url' => 'https://weather.example.com/',
			)
		);
		$this->assertSame( 'Weather from Backyard Weather', get_post_meta( $post_id, 'weather_attribution', true ) );
		$this->assertSame( 3.05, (float) get_post_meta( $post_id, 'weather_rainrate', true ) );
		$html = Sloc_Weather_Data::get_the_weather( 'post', $post_id );
		$this->assertStringContainsString( '<span class="sloc-weather-attribution"><a href="https://weather.example.com/">Weather from Backyard Weather</a></span>', $html );

		update_option( 'sloc_weather_attribution', 'footer' );
		$this->assertStringNotContainsString( 'Backyard Weather', Sloc_Weather_Data::get_the_weather( 'post', $post_id ) );
		ob_start();
		Sloc_Weather_Data::footer_credits();
		$this->assertStringContainsString( 'Weather from Backyard Weather', ob_get_clean() );

		// A credit alone isn't weather.
		$other = self::factory()->post->create();
		update_post_meta( $other, 'weather_attribution', 'Weather from Backyard Weather' );
		$this->assertFalse( Sloc_Weather_Data::has_weather( 'post', $other ) );
	}

	/**
	 * The rain rate has a name and units, metric and imperial.
	 */
	public function test_rain_rate() {
		$this->assertSame( 'Rain Rate', Weather_Provider::get_names( 'rainrate' )['name'] );
		$this->assertSame( 'in/h', Weather_Provider::get_names( 'rainrate', true )['unit'] );
		$this->assertEqualsWithDelta( 0.12, Weather_Provider::metric_to_imperial( array( 'rainrate' => 3.05 ) )['rainrate'], 0.01 );
	}

	/**
	 * Saving the stations trims IDs (#672), drops empty and removed rows (#677), and keeps coordinates only as a pair.
	 */
	public function test_sanitize_stations() {
		$stations = Weather_Provider_Station::sanitize_stations(
			array(
				array(
					'id'              => ' backyard ',
					'url'             => ' https://station.example.com/current.json ',
					'latitude'        => '39.95',
					'longitude'       => '',
					'attribution'     => ' <b>Club</b> ',
					'attribution_url' => 'javascript:alert(1)',
				),
				array(
					'id'     => 'old',
					'url'    => 'https://old.example.com/',
					'remove' => '1',
				),
				array(
					'id'  => '',
					'url' => '',
				),
				'junk',
			)
		);
		$this->assertSame(
			array(
				array(
					'id'              => 'backyard',
					'url'             => 'https://station.example.com/current.json',
					'latitude'        => '',
					'longitude'       => '',
					'attribution'     => 'Club',
					'attribution_url' => '',
				),
			),
			$stations
		);
		$this->assertSame( array(), Weather_Provider_Station::sanitize_stations( 'nothing' ) );
	}

	/**
	 * A saved ID with stray spaces still matches (#672).
	 */
	public function test_station_id_with_spaces() {
		$this->station( array( 'id' => ' backyard ' ) );
		$this->mock_http( array( 'station.example.com' => array( 'body' => $this->document() ) ) );
		$provider = new Weather_Provider_Station();
		$provider->set( array( 'station_id' => 'backyard' ) );
		$this->assertSame( 21.5, $provider->get_conditions()['temperature'] );

		$provider->set( array( 'station_id' => 'elsewhere' ) );
		$this->assertSame( 'station_not_found', $provider->get_conditions()->get_error_code() );
	}

	/**
	 * Without stations, or without stations with coordinates, a location has no station, without warnings (#673).
	 */
	public function test_nearest_without_stations() {
		$this->mock_http( array() );
		$provider = new Weather_Provider_Station();
		$provider->set( self::LAT, self::LNG );
		$this->assertSame( 'no_results', $provider->get_conditions()->get_error_code() );

		update_option( 'sloc_stations', 'not an array' );
		$this->assertSame( 'no_results', $provider->get_conditions()->get_error_code() );

		// A station without coordinates isn't treated as being at 0,0.
		$this->station();
		$this->assertSame( 'no_results', $provider->get_conditions()->get_error_code() );
		$this->assertEmpty( $this->http_requests );
	}

	/**
	 * Station ID lookups are cached like location lookups (#675).
	 */
	public function test_station_id_cached() {
		$this->station();
		$this->mock_http( array( 'station.example.com' => array( 'body' => $this->document() ) ) );
		$provider = new Weather_Provider_Station( array( 'cache_time' => 600 ) );
		$provider->set( array( 'station_id' => 'backyard' ) );
		$provider->get_conditions();
		$provider->get_conditions();
		$this->assertCount( 1, $this->http_requests );
	}
}

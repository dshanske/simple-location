<?php
/**
 * Tests for the classic widgets in includes/widgets/: weather, weather station, airport and last seen.
 *
 * @package Simple_Location
 */

/**
 * Widget tests.
 */
class WidgetsTest extends WP_UnitTestCase {

	/**
	 * Philadelphia City Hall.
	 */
	const LAT = 39.9524;
	const LNG = -75.1636;

	/**
	 * Sidebar arguments.
	 *
	 * @var array
	 */
	protected $args = array(
		'before_widget' => '<section class="widget">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	);

	/**
	 * Known providers and settings for every test.
	 */
	public function set_up() {
		parent::set_up();
		Weather_Provider_Sloc_Test::reset();
		update_option( 'sloc_weather_provider', 'sloc_test' );
		update_option( 'sloc_map_provider', 'sloc_test' );
		update_option( 'sloc_measurements', 'metric' );
		update_option( 'timezone_string', 'America/New_York' );
		update_option( 'time_format', 'g:i a' );
	}

	/**
	 * Captures a widget's output.
	 *
	 * @param WP_Widget $widget   Widget.
	 * @param array     $instance Settings.
	 * @return string
	 */
	protected function render( $widget, $instance ) {
		ob_start();
		$widget->widget( $this->args, $instance );
		return ob_get_clean();
	}

	/**
	 * Captures a widget's settings form.
	 *
	 * @param WP_Widget $widget   Widget.
	 * @param array     $instance Settings.
	 * @return string
	 */
	protected function form( $widget, $instance ) {
		$widget->_set( 2 );
		ob_start();
		$widget->form( $instance );
		return ob_get_clean();
	}

	/**
	 * Gives a user a saved location.
	 *
	 * @param string $visibility Location visibility.
	 * @return int User ID.
	 */
	protected function user_with_location( $visibility = 'public' ) {
		$user_id = self::factory()->user->create();
		// set_user_geodata() doesn't save (#305), so store the location directly.
		Geo_Data::set_geodata(
			'user',
			$user_id,
			'',
			array(
				'latitude'   => self::LAT,
				'longitude'  => self::LNG,
				'address'    => 'Philadelphia City Hall',
				'visibility' => $visibility,
			)
		);
		return $user_id;
	}

	/**
	 * All four widgets are registered.
	 */
	public function test_registered() {
		global $wp_widget_factory;
		foreach ( array( 'Sloc_Weather_Widget', 'Sloc_Station_Widget', 'Sloc_Airport_Widget', 'Sloc_Lastseen_Widget' ) as $class ) {
			$this->assertArrayHasKey( $class, $wp_widget_factory->widgets, $class );
			$this->assertTrue( $wp_widget_factory->widgets[ $class ]->widget_options['show_instance_in_rest'], $class );
		}
	}

	/**
	 * Saved settings are trimmed.
	 */
	public function test_update_trims() {
		$new      = array(
			'title'     => '  Weather ',
			'latitude'  => ' 39.9524 ',
			'showastro' => 1,
		);
		$expected = array(
			'title'     => 'Weather',
			'latitude'  => '39.9524',
			'showastro' => 1,
		);
		foreach ( array( 'Sloc_Weather_Widget', 'Sloc_Station_Widget', 'Sloc_Airport_Widget', 'Sloc_Lastseen_Widget' ) as $class ) {
			$this->assertSame( $expected, ( new $class() )->update( $new, array() ), $class );
		}
	}

	/**
	 * The weather widget shows current conditions for the coordinates set.
	 */
	public function test_weather_by_coordinates() {
		$html = $this->render(
			new Sloc_Weather_Widget(),
			array(
				'title'      => 'Outside',
				'user'       => '-1',
				'latitude'   => self::LAT,
				'longitude'  => self::LNG,
				'cache_time' => 600,
			)
		);
		$this->assertStringStartsWith( '<section class="widget"><h2 class="widget-title">Outside</h2>', $html );
		$this->assertStringContainsString( '<ul class="sloc-weather-display">', $html );
		$this->assertStringContainsString( 'Clear', $html );
		$this->assertStringContainsString( '20', $html );
		$this->assertStringEndsWith( '</section>', $html );
	}

	/**
	 * A widget set to a user shows the weather at the user's saved location.
	 */
	public function test_weather_by_user() {
		$html = $this->render(
			new Sloc_Weather_Widget(),
			array(
				'title' => 'Where I am',
				'user'  => (string) $this->user_with_location(),
			)
		);
		$this->assertStringContainsString( '<ul class="sloc-weather-display">', $html );
		$this->assertStringContainsString( 'Clear', $html );
		$this->assertStringEndsWith( '</section>', $html );
	}

	/**
	 * A user's private location shows nothing unless the widget allows it, and then only the conditions.
	 */
	public function test_weather_by_user_private() {
		Weather_Provider_Sloc_Test::$conditions = array(
			'temperature' => 28,
			'code'        => 800,
			'name'        => 'Backyard',
			'station_id'  => 'KPHL',
			'latitude'    => self::LAT,
			'longitude'   => self::LNG,
		);
		$instance                               = array(
			'title'     => 'Where I am',
			'user'      => (string) $this->user_with_location( 'private' ),
			'showastro' => '1',
		);
		$this->assertSame( '', $this->render( new Sloc_Weather_Widget(), $instance ) );

		$instance['showprivate'] = '1';
		$html                    = $this->render( new Sloc_Weather_Widget(), $instance );
		$this->assertStringContainsString( 'Where I am', $html );
		$this->assertStringContainsString( '28', $html );
		$this->assertStringNotContainsString( 'Backyard', $html );
		$this->assertStringNotContainsString( 'KPHL', $html );
		$this->assertStringNotContainsString( 'Sunrise', $html );
		$this->assertStringEndsWith( '</section>', $html );
	}

	/**
	 * A user's protected location shows the full weather without the private-location option.
	 */
	public function test_weather_by_user_protected() {
		Weather_Provider_Sloc_Test::$conditions = array(
			'temperature' => 28,
			'name'        => 'Backyard',
		);
		$html                                   = $this->render(
			new Sloc_Weather_Widget(),
			array(
				'title' => '',
				'user'  => (string) $this->user_with_location( 'protected' ),
			)
		);
		$this->assertStringContainsString( 'Backyard</li>', $html );
	}

	/**
	 * Provider errors are shown in full to administrators, and as a general message to everyone else.
	 */
	public function test_weather_errors() {
		Weather_Provider_Sloc_Test::$conditions = new WP_Error( 'down', 'The service returned HTTP status 500: internal.example.com' );
		$instance                               = array(
			'title'     => '',
			'latitude'  => self::LAT,
			'longitude' => self::LNG,
		);
		$html                                   = $this->render( new Sloc_Weather_Widget(), $instance );
		$this->assertStringContainsString( 'Weather is not available right now.', $html );
		$this->assertStringNotContainsString( 'internal.example.com', $html );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertStringContainsString( 'internal.example.com', $this->render( new Sloc_Weather_Widget(), $instance ) );
	}

	/**
	 * Without a provider or a location, the weather widget says so and still closes its wrapper.
	 */
	public function test_weather_without_input() {
		$html = $this->render( new Sloc_Weather_Widget(), array( 'title' => '' ) );
		$this->assertStringContainsString( 'No Input Provided', $html );
		$this->assertStringEndsWith( '</section>', $html );

		update_option( 'sloc_weather_provider', 'none' );
		$html = $this->render( new Sloc_Weather_Widget(), array( 'title' => '' ) );
		$this->assertStringContainsString( 'No Weather Provider Set!', $html );
		$this->assertStringEndsWith( '</section>', $html );
	}

	/**
	 * The cache expiry note is part of the conditions list, not printed ahead of it.
	 */
	public function test_weather_cache_comment() {
		Weather_Provider_Sloc_Test::$conditions['_expires_at'] = '2026-10-17T12:00:00+00:00';
		$html = $this->render(
			new Sloc_Weather_Widget(),
			array(
				'title'     => '',
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			)
		);
		$this->assertSame( 1, substr_count( $html, 'Cache Expires At' ) );
		$this->assertGreaterThan( strpos( $html, 'Clear' ), strpos( $html, 'Cache Expires At' ), 'The note comes with the conditions, not before them.' );
	}

	/**
	 * Measurements are converted to imperial units when the site uses them, and wind is unpacked.
	 */
	public function test_weather_imperial_and_wind() {
		update_option( 'sloc_measurements', 'imperial' );
		Weather_Provider_Sloc_Test::$conditions = array(
			'temperature' => 20,
			'summary'     => 'Clear',
			'wind'        => array(
				'speed'  => 10,
				'degree' => 90,
			),
		);
		$html                                   = $this->render(
			new Sloc_Weather_Widget(),
			array(
				'title'     => '',
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			)
		);
		$this->assertStringContainsString( '68', $html, '20°C is 68°F.' );
		$this->assertStringContainsString( '22', $html, '10 m/s is about 22 mph.' );
		$this->assertStringContainsString( 'E', $html, 'A 90° wind is from the east.' );
	}

	/**
	 * Station and place names are listed with the widget's icon.
	 */
	public function test_weather_name() {
		Weather_Provider_Sloc_Test::$conditions = array(
			'temperature' => 20,
			'name'        => 'Backyard',
		);
		$html                                   = $this->render(
			new Sloc_Weather_Widget(),
			array(
				'title'     => '',
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			)
		);
		$this->assertStringContainsString( 'Backyard</li>', $html );

		Weather_Provider_Sloc_Test::$conditions = array(
			'temperature' => 20,
			'station_id'  => 'KPHL',
		);
		$html                                   = $this->render(
			new Sloc_Weather_Widget(),
			array(
				'title'     => '',
				'latitude'  => self::LAT,
				'longitude' => self::LNG,
			)
		);
		$this->assertStringContainsString( 'KPHL</li>', $html );
	}

	/**
	 * Astronomical details are added when asked for and the weather has coordinates.
	 */
	public function test_weather_astro() {
		Weather_Provider_Sloc_Test::$conditions = array(
			'temperature' => 20,
			'latitude'    => self::LAT,
			'longitude'   => self::LNG,
		);
		$instance                               = array(
			'title'     => '',
			'latitude'  => self::LAT,
			'longitude' => self::LNG,
		);
		$this->assertStringNotContainsString( 'Sunrise', $this->render( new Sloc_Weather_Widget(), $instance ) );

		$instance['showastro'] = '1';
		$html                  = $this->render( new Sloc_Weather_Widget(), $instance );
		foreach ( array( 'Sunrise', 'Sunset', 'Moonrise', 'Moonset', 'Moon Phase' ) as $label ) {
			$this->assertStringContainsString( $label . ':', $html, $label );
		}
		$this->assertMatchesRegularExpression( '/<time datetime="\d{4}-\d\d-\d\dT[\d:]+-0[45]:00">\d{1,2}:\d\d [ap]m<\/time>/', $html );
		$this->assertMatchesRegularExpression( '/\(\d{1,3}%\)/', $html );
	}

	/**
	 * The weather widget form has fields for each setting.
	 */
	public function test_weather_form() {
		$user_id = self::factory()->user->create( array( 'display_name' => 'Weather Person' ) );
		$html    = $this->form(
			new Sloc_Weather_Widget(),
			array(
				'user'      => $user_id,
				'latitude'  => self::LAT,
				'showastro' => 1,
			)
		);
		$this->assertStringContainsString( 'name="widget-sloc_weather_widget[2][latitude]"', $html );
		$this->assertStringContainsString( 'value="39.9524"', $html );
		$this->assertStringContainsString( 'name="widget-sloc_weather_widget[2][longitude]"', $html );
		$this->assertStringContainsString( 'name="widget-sloc_weather_widget[2][cache_time]"', $html );
		$this->assertMatchesRegularExpression( '/name="widget-sloc_weather_widget\[2\]\[showastro\]" id="widget-sloc_weather_widget-2-showastro" type="checkbox" value="1"\s+checked/', $html );
		$this->assertMatchesRegularExpression( '/name="widget-sloc_weather_widget\[2\]\[showprivate\]" id="widget-sloc_weather_widget-2-showprivate" type="checkbox" value="1"\s+\/>/', $html );
		$this->assertMatchesRegularExpression( '/<option value=[\'"]' . $user_id . '[\'"]\s+selected/', $html );
		$this->assertStringContainsString( 'Weather Person', $html );
	}

	/**
	 * The station widget shows the station's current conditions.
	 */
	public function test_station() {
		$html = $this->render(
			new Sloc_Station_Widget(),
			array(
				'title'    => 'Backyard',
				'station'  => 'backyard',
				'provider' => 'sloc_test',
			)
		);
		$this->assertStringStartsWith( '<section class="widget"><h2 class="widget-title">Backyard</h2>', $html );
		$this->assertStringContainsString( 'Clear', $html );
		$this->assertStringEndsWith( '</section>', $html, 'The widget wrapper is closed.' );
		$this->assertSame( 'backyard', Loc_Config::weather_provider( 'sloc_test' )->get_station_id() );
	}

	/**
	 * Without a station the widget is empty, without warnings, and still closed.
	 */
	public function test_station_without_station() {
		$html = $this->render( new Sloc_Station_Widget(), array( 'title' => '' ) );
		$this->assertSame( '<section class="widget"></section>', $html );
	}

	/**
	 * Station errors say when the station can't be reached, and otherwise show details only to administrators.
	 */
	public function test_station_errors() {
		$instance = array(
			'title'    => '',
			'station'  => 'backyard',
			'provider' => 'sloc_test',
		);

		Weather_Provider_Sloc_Test::$conditions = new WP_Error( 'http_request_failed', 'cURL error 28' );
		$html                                   = $this->render( new Sloc_Station_Widget(), $instance );
		$this->assertStringContainsString( 'Unable to Connect to Station', $html );
		$this->assertStringNotContainsString( 'cURL', $html );
		$this->assertStringEndsWith( '</section>', $html );

		Weather_Provider_Sloc_Test::$conditions = new WP_Error( 'not_found', 'Station not found' );
		$html                                   = $this->render( new Sloc_Station_Widget(), $instance );
		$this->assertStringContainsString( 'Weather is not available right now.', $html );
		$this->assertStringNotContainsString( 'Station not found', $html );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertStringContainsString( 'Station not found', $this->render( new Sloc_Station_Widget(), $instance ) );
	}

	/**
	 * The station widget form lists providers that support stations.
	 */
	public function test_station_form() {
		$html = $this->form(
			new Sloc_Station_Widget(),
			array(
				'station'  => 'KPHL',
				'provider' => 'nwsus',
			)
		);
		$this->assertStringContainsString( '<select name="widget-sloc_station_widget[2][provider]">', $html );
		$this->assertMatchesRegularExpression( '/<option value="nwsus"\s+selected/', $html );
		foreach ( array_keys( Loc_Config::weather_providers( true ) ) as $slug ) {
			$this->assertStringContainsString( 'value="' . $slug . '"', $html );
		}
		$this->assertStringContainsString( 'name="widget-sloc_station_widget[2][station]"', $html );
		$this->assertStringContainsString( 'value="KPHL"', $html );
	}

	/**
	 * The airport widget shows the weather at an airport.
	 */
	public function test_airport() {
		$html = $this->render(
			new Sloc_Airport_Widget(),
			array(
				'title'   => '',
				'airport' => 'PHL',
			)
		);
		$this->assertStringContainsString( 'Clear', $html );
		$this->assertStringContainsString( 'Philadelphia', $html );
		$this->assertStringEndsWith( '</section>', $html );
	}

	/**
	 * Without an airport, or with a code of the wrong length, the widget closes its wrapper; only administrators see why.
	 */
	public function test_airport_invalid() {
		$this->assertSame( '<section class="widget"></section>', $this->render( new Sloc_Airport_Widget(), array( 'title' => '' ) ) );

		$html = $this->render(
			new Sloc_Airport_Widget(),
			array(
				'title'   => '',
				'airport' => 'PH',
			)
		);
		$this->assertStringContainsString( 'Weather is not available right now.', $html );
		$this->assertStringEndsWith( '</section>', $html );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$html = $this->render(
			new Sloc_Airport_Widget(),
			array(
				'title'   => '',
				'airport' => 'PH',
			)
		);
		$this->assertStringContainsString( 'Airport codes have 3 (IATA) or 4 (ICAO) characters.', $html );
		wp_set_current_user( 0 );

		$html = $this->render(
			new Sloc_Airport_Widget(),
			array(
				'title'   => '',
				'airport' => 'ZZZ',
			)
		);
		$this->assertStringEndsWith( '</section>', $html, 'An unknown airport closes the wrapper.' );
	}

	/**
	 * The airport widget form has the airport code field.
	 */
	public function test_airport_form() {
		$html = $this->form( new Sloc_Airport_Widget(), array( 'airport' => 'PHL' ) );
		$this->assertStringContainsString( 'name="widget-sloc_airport_widget[2][airport]"', $html );
		$this->assertStringContainsString( 'value="PHL"', $html );
		$this->assertStringContainsString( 'name="widget-sloc_airport_widget[2][cache_time]"', $html );
	}

	/**
	 * The last seen widget shows the user's location as text and a map.
	 */
	public function test_lastseen() {
		$html = $this->render(
			new Sloc_Lastseen_Widget(),
			array(
				'title'    => 'Last Seen',
				'user'     => $this->user_with_location(),
				'showtext' => '1',
				'showmap'  => '1',
			)
		);
		$this->assertStringStartsWith( '<section class="widget"><h2 class="widget-title">Last Seen</h2><ul class="sloc-lastseen-data">', $html );
		$this->assertStringContainsString( 'Philadelphia City Hall', $html );
		$this->assertStringContainsString( 'https://maps.example.com/static?c=39.9524,-75.1636', $html );
		$this->assertStringContainsString( 'Sunrise:', $html );
		$this->assertStringEndsWith( '</ul></section>', $html );
	}

	/**
	 * The Last Seen widget escapes the user's address.
	 */
	public function test_lastseen_escaped() {
		$user_id = $this->user_with_location();
		remove_all_filters( 'sanitize_user_meta_geo_address' );
		update_user_meta( $user_id, 'geo_address', '<script>alert(1)</script>' );
		$html = $this->render(
			new Sloc_Lastseen_Widget(),
			array(
				'title'    => '',
				'user'     => $user_id,
				'showtext' => '1',
				'showmap'  => '1',
			)
		);
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		$this->assertStringContainsString( '<img src="https://maps.example.com/static?', $html );
	}

	/**
	 * The station widget's provider list marks the saved provider inside its option, with no stray text.
	 */
	public function test_station_provider_list() {
		foreach ( array( 'Sloc_Station_Widget', 'Sloc_Airport_Widget' ) as $class ) {
			ob_start();
			$class::provider_list( 'nwsus', 'provider', 'provider' );
			$html = ob_get_clean();
			$this->assertStringStartsWith( '<select name="provider"><option', $html, $class );
			$this->assertMatchesRegularExpression( "/<option value=\"nwsus\"\s+selected(='selected')?>[^<]+<\\/option>/", $html, $class );
		}
	}

	/**
	 * The last seen widget can hide the text and map.
	 */
	public function test_lastseen_without_text_or_map() {
		$html = $this->render(
			new Sloc_Lastseen_Widget(),
			array(
				'title'    => '',
				'user'     => $this->user_with_location(),
				'showtext' => '0',
				'showmap'  => '0',
			)
		);
		$this->assertStringNotContainsString( 'Philadelphia City Hall', $html );
		$this->assertStringNotContainsString( 'maps.example.com', $html );
	}

	/**
	 * The last seen widget shows the local time and the sun and moon only when asked.
	 */
	public function test_lastseen_time_and_astro() {
		$user_id = $this->user_with_location();
		update_user_meta( $user_id, 'geo_timezone', 'Asia/Tokyo' );

		$html = $this->render(
			new Sloc_Lastseen_Widget(),
			array(
				'title'     => '',
				'user'      => $user_id,
				'showtime'  => '1',
				'showastro' => '0',
			)
		);
		$this->assertStringContainsString( 'datetime="' . wp_date( 'Y-m-d', null, new DateTimeZone( 'Asia/Tokyo' ) ), $html );
		$this->assertStringContainsString( '+09:00"', $html, 'The time is in the user\'s timezone.' );
		$this->assertStringNotContainsString( 'Sunrise:', $html );

		$html = $this->render(
			new Sloc_Lastseen_Widget(),
			array(
				'title'     => '',
				'user'      => $user_id,
				'showtime'  => '0',
				'showastro' => '1',
			)
		);
		$this->assertStringNotContainsString( '<time datetime="' . wp_date( 'Y-m-d', null, new DateTimeZone( 'Asia/Tokyo' ) ), $html );
		$this->assertStringContainsString( 'Sunrise:', $html );
	}

	/**
	 * A widget saved before the display options existed shows the sun and moon, as it did, without warnings.
	 */
	public function test_lastseen_older_instance() {
		$html = $this->render(
			new Sloc_Lastseen_Widget(),
			array(
				'title' => '',
				'user'  => $this->user_with_location(),
			)
		);
		$this->assertStringContainsString( 'Sunrise:', $html );
		$this->assertStringNotContainsString( 'Philadelphia City Hall', $html );
		$this->assertStringEndsWith( '</ul></section>', $html );
	}

	/**
	 * Users without a location are reported as such, and the widget needs a user.
	 */
	public function test_lastseen_without_location() {
		$html = $this->render(
			new Sloc_Lastseen_Widget(),
			array(
				'title'    => '',
				'user'     => self::factory()->user->create(),
				'showtext' => '1',
				'showmap'  => '0',
			)
		);
		$this->assertStringContainsString( 'No current location information available', $html );

		$this->assertStringContainsString( 'No User Set', $this->render( new Sloc_Lastseen_Widget(), array( 'title' => '' ) ) );
	}

	/**
	 * The last seen widget form lists users and the display options.
	 */
	public function test_lastseen_form() {
		$user_id = self::factory()->user->create( array( 'display_name' => 'Traveler' ) );
		$html    = $this->form(
			new Sloc_Lastseen_Widget(),
			array(
				'user'    => $user_id,
				'showmap' => 1,
			)
		);
		$this->assertMatchesRegularExpression( '/name=[\'"]widget-sloc_lastseen_widget\[2\]\[user\][\'"]/', $html );
		$this->assertStringContainsString( 'Traveler', $html );
		foreach ( array( 'showtime', 'showastro', 'showtext', 'showmap' ) as $field ) {
			$this->assertStringContainsString( 'name="widget-sloc_lastseen_widget[2][' . $field . ']" id="widget-sloc_lastseen_widget-2-' . $field . '" type="checkbox"', $html, $field );
		}
		$this->assertMatchesRegularExpression( '/\[showmap\]" id="widget-sloc_lastseen_widget-2-showmap" type="checkbox" value="1"\s+checked/', $html );
	}

	/**
	 * Every widget.
	 *
	 * @return array Widget class.
	 */
	public function data_widgets() {
		return array(
			'Weather'   => array( 'Sloc_Weather_Widget' ),
			'Station'   => array( 'Sloc_Station_Widget' ),
			'Airport'   => array( 'Sloc_Airport_Widget' ),
			'Last Seen' => array( 'Sloc_Lastseen_Widget' ),
		);
	}

	/**
	 * Widget forms submit the title, give every field an id, and point every label at a field.
	 *
	 * @dataProvider data_widgets
	 *
	 * @param string $class Widget class.
	 */
	public function test_form_fields( $class ) {
		$widget = new $class();
		$html   = $this->form( $widget, array( 'title' => 'My title' ) );

		$dom      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$dom->loadHTML( '<html><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$xpath = new DOMXPath( $dom );

		$title = $xpath->query( '//input[@id="' . $widget->get_field_id( 'title' ) . '"]' );
		$this->assertSame( 1, $title->length, 'Title input' );
		$this->assertSame( $widget->get_field_name( 'title' ), $title->item( 0 )->getAttribute( 'name' ) );
		$this->assertSame( 'My title', $title->item( 0 )->getAttribute( 'value' ) );

		foreach ( $xpath->query( '//*[@id]' ) as $element ) {
			$this->assertNotSame( '', $element->getAttribute( 'id' ), 'Empty id on ' . $element->getAttribute( 'name' ) );
		}

		$labels = $xpath->query( '//label[@for]' );
		$this->assertGreaterThan( 0, $labels->length );
		foreach ( $labels as $label ) {
			$for = $label->getAttribute( 'for' );
			$this->assertSame( 1, $xpath->query( '//*[@id="' . $for . '"]' )->length, "Label for \"$for\" has a field" );
		}
	}
}

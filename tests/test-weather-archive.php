<?php
/**
 * Tests for the weather views of archives: /temperature/ and /weather/ (#216).
 *
 * @package Simple_Location
 */

/**
 * Weather archive tests.
 */
class WeatherArchiveTest extends WP_UnitTestCase {

	/**
	 * Author of the test posts, whose archive the tests use.
	 *
	 * @var int
	 */
	protected $author = 0;

	/**
	 * Directory standing in for the theme, for template overrides.
	 *
	 * @var string
	 */
	protected $theme_dir = '';

	/**
	 * Metric units and the weather views' rewrite rules.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_measurements', 'metric' );
		update_option( 'date_format', 'Y-m-d' );
		$this->author = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );
		Geo_Base::rewrite();
		flush_rewrite_rules();
	}

	/**
	 * Returns the URL of the author archive of the test posts, with a view.
	 *
	 * @param string $view View: 'temperature', 'weather', or empty for the plain archive.
	 * @return string URL.
	 */
	protected function archive( $view ) {
		return get_author_posts_url( $this->author ) . ( '' === $view ? '' : $view . '/' );
	}

	/**
	 * Removes the stand-in theme directory.
	 */
	public function tear_down() {
		if ( $this->theme_dir ) {
			array_map( 'unlink', (array) glob( $this->theme_dir . '/*' ) );
			rmdir( $this->theme_dir );
		}
		parent::tear_down();
	}

	/**
	 * Creates a post from July 2025 with weather and a location of the given visibility.
	 *
	 * @param float|null $temperature Temperature in Celsius, or null for weather without one.
	 * @param string     $visibility  Location visibility.
	 * @param string     $date        Post date.
	 * @return int Post ID.
	 */
	protected function weather_post( $temperature, $visibility = 'public', $date = '2025-07-01 12:00:00' ) {
		$post_id = self::factory()->post->create(
			array(
				'post_date'   => $date,
				'post_author' => $this->author,
			)
		);
		set_post_geodata(
			$post_id,
			'',
			array(
				'latitude'   => 39.9524,
				'longitude'  => -75.1636,
				'visibility' => $visibility,
			)
		);
		set_post_weatherdata(
			$post_id,
			'',
			array_filter(
				array(
					'temperature' => $temperature,
					'code'        => 800,
				),
				function ( $value ) {
					return null !== $value;
				}
			)
		);
		return $post_id;
	}

	/**
	 * The views have pretty URLs on archives, including location archives, with prefixed query variables.
	 */
	public function test_urls() {
		$this->weather_post( 20 );
		$this->go_to( $this->archive( 'temperature' ) );
		$this->assertSame( 'sloc_temperature', Sloc_Weather_Archive::get_view() );
		$this->assertTrue( is_archive() );

		$this->go_to( $this->archive( 'weather' ) );
		$this->assertSame( 'sloc_weather', Sloc_Weather_Archive::get_view() );

		$this->go_to( $this->archive( '' ) );
		$this->assertSame( '', Sloc_Weather_Archive::get_view() );

		$rules = get_option( 'rewrite_rules' );
		$this->assertArrayHasKey( 'temperature/location/(.+?)/?$', $rules );
		$this->assertArrayHasKey( 'weather/location/(.+?)/?$', $rules );
		$this->assertContains( 'sloc_temperature', $GLOBALS['wp']->public_query_vars );
	}

	/**
	 * The temperature view has posts with a temperature whose location isn't private, oldest first.
	 */
	public function test_temperature_query() {
		$warm       = $this->weather_post( 30, 'public', '2025-07-03 12:00:00' );
		$cool       = $this->weather_post( 18, 'protected', '2025-07-01 12:00:00' );
		$private    = $this->weather_post( 25, 'private', '2025-07-02 12:00:00' );
		$no_temp    = $this->weather_post( null, 'public', '2025-07-02 13:00:00' );
		$no_weather = self::factory()->post->create(
			array(
				'post_date'   => '2025-07-02 14:00:00',
				'post_author' => $this->author,
			)
		);

		$this->go_to( $this->archive( 'temperature' ) );
		$this->assertSame( array( $cool, $warm ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
		$this->assertSame( array( $cool, $warm ), array_keys( Sloc_Weather_Archive::get_temperatures() ) );

		// The weather view also has posts with weather but no temperature.
		$this->go_to( $this->archive( 'weather' ) );
		$ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
		$this->assertContains( $no_temp, $ids );
		$this->assertNotContains( $private, $ids );
		$this->assertNotContains( $no_weather, $ids );
	}

	/**
	 * Views use the plugin's templates, or the theme's when it has one, and only on archives.
	 */
	public function test_templates() {
		$this->weather_post( 20 );
		$this->go_to( $this->archive( 'temperature' ) );
		$this->assertStringEndsWith( 'templates/temperature-archive.php', Sloc_Weather_Archive::template( 'index.php' ) );
		$this->go_to( $this->archive( 'weather' ) );
		$this->assertStringEndsWith( 'templates/weather-archive.php', Sloc_Weather_Archive::template( 'index.php' ) );

		$this->theme_dir = get_temp_dir() . 'sloc-theme-' . wp_generate_password( 6, false );
		wp_mkdir_p( $this->theme_dir );
		file_put_contents( $this->theme_dir . '/weather-archive.php', '<?php // Theme template.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$theme = function () {
			return $this->theme_dir;
		};
		add_filter( 'theme_file_path', $theme );
		$this->assertSame( $this->theme_dir . '/weather-archive.php', Sloc_Weather_Archive::template( 'index.php' ) );
		remove_filter( 'theme_file_path', $theme );

		$this->go_to( $this->archive( '' ) );
		$this->assertSame( 'index.php', Sloc_Weather_Archive::template( 'index.php' ) );
	}

	/**
	 * The chart draws a point per temperature, labeled with the range and dates, in the site's units.
	 */
	public function test_temperature_chart() {
		$this->weather_post( 10, 'public', '2025-07-01 12:00:00' );
		$this->weather_post( 30, 'public', '2025-07-11 12:00:00' );
		$this->weather_post( 20, 'public', '2025-07-21 12:00:00' );
		$this->go_to( $this->archive( 'temperature' ) );
		$chart = Sloc_Weather_Archive::get_temperature_chart( Sloc_Weather_Archive::get_temperatures() );

		$this->assertStringStartsWith( '<svg class="sloc-temperature-chart" viewBox="0 0 600 240" role="img" aria-label="Temperatures of 3 posts, from 10°C to 30°C">', $chart );
		$this->assertSame( 3, substr_count( $chart, '<circle ' ) );
		$this->assertMatchesRegularExpression( '#<polyline [^>]*points="56,208 320,16 584,112" />#', $chart );
		$this->assertStringContainsString( '<title>2025-07-11: 30°C</title>', $chart );
		$this->assertStringContainsString( '>2025-07-01</text>', $chart );
		$this->assertStringContainsString( '>2025-07-21</text>', $chart );

		update_option( 'sloc_measurements', 'imperial' );
		$chart = Sloc_Weather_Archive::get_temperature_chart( Sloc_Weather_Archive::get_temperatures() );
		$this->assertStringContainsString( 'from 50°F to 86°F', $chart );
	}

	/**
	 * A single temperature is a point in the middle, and no temperatures is no chart.
	 */
	public function test_temperature_chart_edges() {
		$chart = Sloc_Weather_Archive::get_temperature_chart(
			array(
				1 => array(
					'time'        => strtotime( '2025-07-01 12:00:00' ),
					'temperature' => 21.5,
				),
			)
		);
		$this->assertStringContainsString( 'Temperature of 1 post, from 21.5°C to 21.5°C', $chart );
		$this->assertStringContainsString( '<circle cx="320" cy="16" r="3">', $chart );
		$this->assertSame( '', Sloc_Weather_Archive::get_temperature_chart( array() ) );
	}

	/**
	 * The templates show the chart and list, and the weather of each post.
	 */
	public function test_template_output() {
		$this->weather_post( 21.5 );
		$this->go_to( $this->archive( 'temperature' ) );
		ob_start();
		include plugin_dir_path( __DIR__ ) . 'templates/temperature-archive.php';
		$html = ob_get_clean();
		$this->assertStringContainsString( '<svg class="sloc-temperature-chart"', $html );
		$this->assertStringContainsString( '21.5°C', $html );

		$this->go_to( $this->archive( 'weather' ) );
		ob_start();
		include plugin_dir_path( __DIR__ ) . 'templates/weather-archive.php';
		$html = ob_get_clean();
		$this->assertStringContainsString( '<ul class="sloc-weather-list">', $html );
		$this->assertStringContainsString( 'class="sloc-weather"', $html );
	}

	/**
	 * Creates a post from July 2025 with rain and snow readings and a public location.
	 *
	 * @param array  $weather    Weather, such as rain and snow in millimeters.
	 * @param string $date       Post date.
	 * @param string $visibility Location visibility.
	 * @return int Post ID.
	 */
	protected function precipitation_post( $weather, $date = '2025-07-01 12:00:00', $visibility = 'public' ) {
		$post_id = $this->weather_post( null, $visibility, $date );
		set_post_weatherdata( $post_id, '', array_merge( array( 'code' => 500 ), $weather ) );
		return $post_id;
	}

	/**
	 * The rain and snow views have pretty URLs, including on location archives.
	 */
	public function test_precipitation_urls() {
		$this->precipitation_post( array( 'rain' => 1 ) );
		$this->go_to( $this->archive( 'rain' ) );
		$this->assertSame( 'sloc_rain', Sloc_Weather_Archive::get_view() );
		$this->go_to( $this->archive( 'snow' ) );
		$this->assertSame( 'sloc_snow', Sloc_Weather_Archive::get_view() );

		$rules = get_option( 'rewrite_rules' );
		$this->assertArrayHasKey( 'rain/location/(.+?)/?$', $rules );
		$this->assertArrayHasKey( 'snow/location/(.+?)/' . $GLOBALS['wp_rewrite']->pagination_base . '/?([0-9]{1,})/?$', $rules );
		$this->assertContains( 'sloc_rain', $GLOBALS['wp']->public_query_vars );
		$this->assertContains( 'sloc_snow', $GLOBALS['wp']->public_query_vars );
	}

	/**
	 * The rain view has posts with more than 0 rain whose location isn't private, oldest first.
	 */
	public function test_precipitation_query() {
		$wet     = $this->precipitation_post( array( 'rain' => 2.5 ), '2025-07-03 12:00:00' );
		$drizzle = $this->precipitation_post( array( 'rain' => 0.1 ), '2025-07-01 12:00:00', 'protected' );
		$dry     = $this->precipitation_post( array( 'rain' => 0 ), '2025-07-01 18:00:00' );
		$private = $this->precipitation_post( array( 'rain' => 4 ), '2025-07-02 12:00:00', 'private' );
		$snowy   = $this->precipitation_post( array( 'snow' => 10 ), '2025-07-02 13:00:00' );
		$this->weather_post( 20, 'public', '2025-07-02 14:00:00' );

		$this->go_to( $this->archive( 'rain' ) );
		$this->assertSame( array( $drizzle, $wet ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ), 'A post with 0 rain is left out.' );
		$readings = Sloc_Weather_Archive::get_readings( 'rain' );
		$this->assertSame( array( $drizzle, $wet ), array_keys( $readings ) );
		$this->assertNotContains( $dry, array_keys( $readings ) );
		$this->assertSame( 2.5, $readings[ $wet ]['rain'] );
		$this->assertNotContains( $private, array_keys( $readings ) );

		$this->go_to( $this->archive( 'snow' ) );
		$this->assertSame( array( $snowy ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );

		// get_readings() leaves out 0 itself, for queries that didn't filter it.
		$this->go_to( $this->archive( 'weather' ) );
		$this->assertNotContains( $dry, array_keys( Sloc_Weather_Archive::get_readings( 'rain' ) ) );
		$this->assertContains( $dry, wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ), 'The weather view still lists it.' );

		// The temperature readings are unchanged.
		$this->go_to( $this->archive( 'temperature' ) );
		$this->assertSame( Sloc_Weather_Archive::get_readings( 'temperature' ), Sloc_Weather_Archive::get_temperatures() );
	}

	/**
	 * The bar chart has a bar per reading, scaled to the largest, labeled with the amounts and dates.
	 */
	public function test_precipitation_chart() {
		$this->precipitation_post( array( 'rain' => 1 ), '2025-07-01 12:00:00' );
		$this->precipitation_post( array( 'rain' => 0 ), '2025-07-05 12:00:00' );
		$this->precipitation_post( array( 'rain' => 5 ), '2025-07-11 12:00:00' );
		$this->precipitation_post( array( 'rain' => 2.5 ), '2025-07-21 12:00:00' );
		$this->go_to( $this->archive( 'rain' ) );
		$chart = Sloc_Weather_Archive::get_precipitation_chart( Sloc_Weather_Archive::get_readings( 'rain' ), 'rain' );

		$this->assertStringStartsWith( '<svg class="sloc-precipitation-chart sloc-rain-chart" viewBox="0 0 600 240" role="img" aria-label="Rainfall of 3 posts, up to 5 mm">', $chart );
		$this->assertSame( 3, substr_count( $chart, '<rect ' ) );
		// The dry post isn't charted. Bars stay inside the plot, from x 56 to 584; the largest fills the plot, from y 16
		// to the axis at 208.
		$this->assertStringNotContainsString( '2025-07-05', $chart );
		$this->assertStringContainsString( '<rect x="56" y="169.6" width="12" height="38.4"><title>2025-07-01: 1 mm</title></rect>', $chart );
		$this->assertStringContainsString( '<rect x="314" y="16" width="12" height="192"><title>2025-07-11: 5 mm</title></rect>', $chart );
		$this->assertStringContainsString( '<rect x="572" y="112" width="12" height="96"><title>2025-07-21: 2.5 mm</title></rect>', $chart );
		$this->assertStringContainsString( '>5 mm</text>', $chart );
		$this->assertStringContainsString( '>0 mm</text>', $chart );
		$this->assertStringContainsString( '>2025-07-01</text>', $chart );
		$this->assertStringContainsString( '>2025-07-21</text>', $chart );

		update_option( 'sloc_measurements', 'imperial' );
		$chart = Sloc_Weather_Archive::get_precipitation_chart( Sloc_Weather_Archive::get_readings( 'rain' ), 'rain' );
		$this->assertStringContainsString( 'up to 0.2 in', $chart );
		$this->assertStringContainsString( '<title>2025-07-21: 0.1 in</title>', $chart );
	}

	/**
	 * A single reading is a bar in the middle; only zeros give no bars; no readings or another property is no chart.
	 */
	public function test_precipitation_chart_edges() {
		$one   = array(
			1 => array(
				'time' => strtotime( '2025-07-01 12:00:00' ),
				'snow' => 12,
			),
		);
		$chart = Sloc_Weather_Archive::get_precipitation_chart( $one, 'snow' );
		$this->assertStringContainsString( 'aria-label="Snowfall of 1 post, up to 12 mm"', $chart );
		$this->assertStringContainsString( 'class="sloc-precipitation-chart sloc-snow-chart"', $chart );
		$this->assertStringContainsString( '<rect x="314" y="16" width="12" height="192">', $chart );

		$one[1]['snow'] = 0;
		$chart          = Sloc_Weather_Archive::get_precipitation_chart( $one, 'snow' );
		$this->assertStringContainsString( 'height="0"', $chart );
		$this->assertStringNotContainsString( 'NAN', strtoupper( $chart ) );

		$this->assertSame( '', Sloc_Weather_Archive::get_precipitation_chart( array(), 'rain' ) );
		$this->assertSame( '', Sloc_Weather_Archive::get_precipitation_chart( $one, 'temperature' ) );
	}

	/**
	 * Many readings get narrower bars, at least 2 pixels wide.
	 */
	public function test_precipitation_chart_bar_width() {
		$readings = array();
		for ( $i = 0; $i < 400; $i++ ) {
			$readings[ $i + 1 ] = array(
				'time' => strtotime( '2025-07-01 12:00:00' ) + $i * HOUR_IN_SECONDS,
				'rain' => $i % 3,
			);
		}
		$chart = Sloc_Weather_Archive::get_precipitation_chart( $readings, 'rain' );
		$this->assertSame( 400, substr_count( $chart, '<rect ' ) );
		$this->assertStringContainsString( 'width="2"', $chart );
	}

	/**
	 * The rain and snow views use their templates, which a theme can override, and show the chart and list.
	 */
	public function test_precipitation_templates() {
		$this->precipitation_post(
			array(
				'rain' => 1.25,
				'snow' => 3,
			)
		);
		foreach ( array( 'rain', 'snow' ) as $view ) {
			$this->go_to( $this->archive( $view ) );
			$this->assertStringEndsWith( 'templates/' . $view . '-archive.php', Sloc_Weather_Archive::template( 'index.php' ), $view );
			ob_start();
			include plugin_dir_path( __DIR__ ) . 'templates/' . $view . '-archive.php';
			$html = ob_get_clean();
			$this->assertStringContainsString( '<svg class="sloc-precipitation-chart sloc-' . $view . '-chart"', $html, $view );
			$this->assertStringContainsString( '<ul class="sloc-precipitation-list sloc-' . $view . '-list">', $html, $view );
		}
		$this->assertStringContainsString( '3 mm', $html );

		$this->go_to( $this->archive( 'rain' ) );
		$this->theme_dir = get_temp_dir() . 'sloc-theme-' . wp_generate_password( 6, false );
		wp_mkdir_p( $this->theme_dir );
		file_put_contents( $this->theme_dir . '/rain-archive.php', '<?php // Theme template.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$theme = function () {
			return $this->theme_dir;
		};
		add_filter( 'theme_file_path', $theme );
		$this->assertSame( $this->theme_dir . '/rain-archive.php', Sloc_Weather_Archive::template( 'index.php' ) );
		remove_filter( 'theme_file_path', $theme );
	}

	/**
	 * An archive without rain says so.
	 */
	public function test_precipitation_template_empty() {
		$this->weather_post( 20 );
		$this->go_to( $this->archive( 'rain' ) );
		ob_start();
		include plugin_dir_path( __DIR__ ) . 'templates/rain-archive.php';
		$html = ob_get_clean();
		$this->assertStringContainsString( 'There are no posts with rainfall here.', $html );
	}
}

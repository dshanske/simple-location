<?php
/**
 * Tests for weather storage and display (includes/class-weather-data.php), the shared weather
 * helpers (includes/trait-weather-info.php) and the weather provider base class
 * (includes/class-weather-provider.php).
 *
 * @package Simple_Location
 */

use DMS\PHPUnitExtensions\ArraySubset\Assert;

/**
 * Weather tests.
 */
class WeatherDataTest extends WP_UnitTestCase {

	/**
	 * Sample weather.
	 *
	 * @var array
	 */
	public static $weather = array(
		'temperature' => '45.01',
		'humidity'    => '45.10',
		'winddegree'  => '140',
		'windspeed'   => '42',
	);

	/**
	 * Sample weather in the legacy single-meta format.
	 *
	 * @var array
	 */
	public static $oldweather = array(
		'temperature' => '45.01',
		'humidity'    => '45.10',
		'wind'        => array(
			'degree' => '140',
			'speed'  => '42',
		),
	);

	/**
	 * Use the fake weather provider, metric units and a known timezone.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_weather_provider', 'sloc_test' );
		delete_option( 'sloc_weather_fallbacks' );
		update_option( 'sloc_measurements', 'metric' );
		update_option( 'timezone_string', 'America/New_York' );
		Weather_Provider_Sloc_Test::reset();
	}

	/**
	 * Clean up request data.
	 */
	public function tear_down() {
		$_POST = array();
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
	 * A single property round-trips.
	 */
	public function test_set_and_get_post_temperature() {
		$post_id = self::factory()->post->create();
		set_post_weatherdata( $post_id, 'temperature', 45.2 );
		$this->assertEquals( 45.2, get_post_weatherdata( $post_id, 'temperature' ) );
	}

	/**
	 * A full set of properties round-trips.
	 */
	public function test_set_and_get_post_weatherdata() {
		$post_id = self::factory()->post->create();
		set_post_weatherdata( $post_id, '', static::$weather );
		$this->assertEquals( static::$weather, get_post_weatherdata( $post_id ) );
	}

	/**
	 * Legacy geo_weather meta is migrated to individual keys when read.
	 */
	public function test_set_and_get_old_post_weatherdata() {
		$post_id = self::factory()->post->create();
		add_post_meta( $post_id, 'geo_weather', static::$oldweather );
		$this->assertEquals( static::$weather, get_post_weatherdata( $post_id ) );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_weather', true ), 'The legacy meta is removed.' );
	}

	/**
	 * Weather fields saved from the editor form are stored.
	 */
	public function test_save_meta_from_post() {
		$_POST   = static::$weather;
		$post_id = self::factory()->post->create();
		Geo_Base::save_meta( 'post', $post_id, false );
		Assert::assertArraySubset( static::$weather, get_post_weatherdata( $post_id ) );
	}

	/**
	 * Weather works on comments, users and terms through the shared storage.
	 */
	public function test_other_object_types() {
		$objects = array(
			'comment' => self::factory()->comment->create( array( 'comment_post_ID' => self::factory()->post->create() ) ),
			'user'    => self::factory()->user->create(),
			'term'    => self::factory()->term->create(),
		);
		foreach ( $objects as $type => $id ) {
			$this->assertTrue( Sloc_Weather_Data::set_object_weatherdata( $type, $id, '', static::$weather ), $type );
			$this->assertEquals( static::$weather, Sloc_Weather_Data::get_object_weatherdata( $type, $id ), $type );
		}
	}

	/**
	 * Empty values in a full update delete the stored value; unknown keys are dropped.
	 */
	public function test_set_full_update() {
		$post_id = self::factory()->post->create();
		set_post_weatherdata( $post_id, '', static::$weather );
		Sloc_Weather_Data::set_object_weatherdata(
			'post',
			$post_id,
			'',
			array(
				'humidity' => '',
				'feelings' => 'happy',
			)
		);
		$weather = get_post_weatherdata( $post_id );
		$this->assertArrayNotHasKey( 'humidity', $weather );
		$this->assertArrayNotHasKey( 'feelings', $weather );
		$this->assertEquals( 45.01, $weather['temperature'] );
	}

	/**
	 * Readings of 0 are saved and returned, including when they replace another value.
	 */
	public function test_zero_values_saved() {
		$post_id = self::factory()->post->create();
		set_post_weatherdata( $post_id, '', static::$weather );
		set_post_weatherdata(
			$post_id,
			'',
			array(
				'temperature' => 0,
				'windspeed'   => 0.0,
				'rain'        => '0',
				'humidity'    => '',
			)
		);
		$this->assertSame( '0', get_post_meta( $post_id, 'weather_temperature', true ) );
		$weather = get_post_weatherdata( $post_id );
		$this->assertSame( '0', $weather['temperature'] );
		$this->assertSame( '0', $weather['windspeed'] );
		$this->assertSame( '0', $weather['rain'] );
		// An empty value still deletes the stored value.
		$this->assertArrayNotHasKey( 'humidity', $weather );

		// A post at exactly 0 °C shows its temperature.
		$this->assertStringContainsString( '<data class="p-num" value="0">0</data>', Sloc_Weather_Data::get_the_weather( 'post', $post_id ) );
	}

	/**
	 * Only null, '', false and empty arrays count as no value.
	 */
	public function test_has_value() {
		foreach ( array( 0, 0.0, '0', 'Clear', 12.5 ) as $value ) {
			$this->assertTrue( Sloc_Weather_Data::has_value( $value ), wp_json_encode( $value ) );
		}
		foreach ( array( null, '', false, array() ) as $value ) {
			$this->assertFalse( Sloc_Weather_Data::has_value( $value ), wp_json_encode( $value ) );
		}
	}

	/**
	 * Old-format weather with no wind keeps a wind speed of 0 when migrated.
	 */
	public function test_migrate_weather_keeps_zero() {
		$post_id = self::factory()->post->create();
		update_post_meta(
			$post_id,
			'geo_weather',
			array(
				'temperature' => 0,
				'wind'        => array(
					'speed'  => 0,
					'degree' => 90,
				),
			)
		);
		$weather = get_post_weatherdata( $post_id );
		$this->assertSame( '0', $weather['temperature'] );
		$this->assertSame( '0', $weather['windspeed'] );
		$this->assertSame( '90', $weather['winddegree'] );
		$this->assertArrayNotHasKey( 'windgust', $weather );
	}

	/**
	 * Invalid types, IDs and keys are refused.
	 */
	public function test_invalid_arguments() {
		$post_id = self::factory()->post->create();
		foreach ( array( 'set', 'get', 'delete' ) as $action ) {
			$method = $action . '_object_weatherdata';
			$this->assertFalse( Sloc_Weather_Data::$method( '', $post_id, 'temperature', 1 ), "$action type" );
			$this->assertFalse( Sloc_Weather_Data::$method( 'post', 'abc', 'temperature', 1 ), "$action id" );
			$this->assertFalse( Sloc_Weather_Data::$method( 'post', 0, 'temperature', 1 ), "$action zero" );
			$this->assertFalse( Sloc_Weather_Data::$method( 'post', $post_id, 'mood', 1 ), "$action key" );
		}
	}

	/**
	 * A single property can be deleted.
	 */
	public function test_delete() {
		$post_id = self::factory()->post->create();
		set_post_weatherdata( $post_id, '', static::$weather );
		$this->assertTrue( Sloc_Weather_Data::delete_object_weatherdata( 'post', $post_id, 'humidity' ) );
		$this->assertSame( '', get_post_weatherdata( $post_id, 'humidity' ) );
	}

	/**
	 * The get, set and delete short-circuit filters work.
	 */
	public function test_short_circuit_filters() {
		$post_id = self::factory()->post->create();
		$filter  = function () {
			return 'short-circuited';
		};
		foreach ( array( 'get', 'set', 'delete' ) as $action ) {
			add_filter( "{$action}_post_weatherdata", $filter );
		}
		$this->assertSame( 'short-circuited', Sloc_Weather_Data::get_object_weatherdata( 'post', $post_id ) );
		$this->assertSame( 'short-circuited', Sloc_Weather_Data::set_object_weatherdata( 'post', $post_id, 'temperature', 1 ) );
		$this->assertSame( 'short-circuited', Sloc_Weather_Data::delete_object_weatherdata( 'post', $post_id, 'temperature' ) );
		foreach ( array( 'get', 'set', 'delete' ) as $action ) {
			remove_filter( "{$action}_post_weatherdata", $filter );
		}
		$this->assertSame( '', get_post_meta( $post_id, 'weather_temperature', true ) );
	}

	/**
	 * Bulk migration converts legacy weather on posts and comments.
	 */
	public function test_bulk_migrate_weather() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );
		add_post_meta( $post_id, 'geo_weather', static::$oldweather );
		add_comment_meta( $comment_id, 'geo_weather', static::$oldweather );
		Sloc_Weather_Data::bulk_migrate_weather();
		$this->assertEquals( '140', get_post_meta( $post_id, 'weather_winddegree', true ) );
		$this->assertEquals( '42', get_comment_meta( $comment_id, 'weather_windspeed', true ) );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_weather', true ) );
	}

	/**
	 * Bulk migration covers every post status and type, honors a limit and reports what it did.
	 */
	public function test_bulk_migrate_weather_all_posts() {
		$draft      = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$private    = self::factory()->post->create( array( 'post_status' => 'private' ) );
		$attachment = self::factory()->attachment->create();
		foreach ( array( $draft, $private, $attachment ) as $post_id ) {
			add_post_meta( $post_id, 'geo_weather', static::$oldweather );
		}

		$this->assertSame(
			array(
				'found'    => 2,
				'migrated' => 2,
			),
			Sloc_Weather_Data::bulk_migrate_weather( 2 )
		);
		$this->assertSame(
			array(
				'found'    => 1,
				'migrated' => 1,
			),
			Sloc_Weather_Data::bulk_migrate_weather( 2 )
		);
		foreach ( array( $draft, $private, $attachment ) as $post_id ) {
			$this->assertEquals( '140', get_post_meta( $post_id, 'weather_winddegree', true ) );
		}
		$this->assertSame(
			array(
				'found'    => 0,
				'migrated' => 0,
			),
			Sloc_Weather_Data::bulk_migrate_weather( 2 )
		);
	}

	/**
	 * Objects with weather report it, and objects without it don't.
	 */
	public function test_has_weather() {
		$post_id = self::factory()->post->create();
		$this->assertFalse( Sloc_Weather_Data::has_weather( 'post', $post_id ), 'No weather stored.' );
		set_post_weatherdata( $post_id, '', static::$weather );
		$this->assertTrue( Sloc_Weather_Data::has_weather( 'post', $post_id ) );
		$this->assertFalse( Sloc_Weather_Data::has_weather( 'post', 'abc' ) );
	}

	/**
	 * Summary and icon meta are sanitized however they're saved, and every numeric property is described.
	 */
	public function test_meta_sanitized() {
		// The test framework clears registered meta between tests.
		Sloc_Weather_Data::register_meta();
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'weather_summary', '<script>x</script>Clear' );
		update_post_meta( $post_id, 'weather_icon', 'wi-day-sunny" onload="x' );
		$this->assertSame( 'Clear', get_post_meta( $post_id, 'weather_summary', true ) );
		$this->assertSame( 'wi-day-sunnyonloadx', get_post_meta( $post_id, 'weather_icon', true ) );

		$registered = get_registered_meta_keys( 'post' );
		$this->assertSame( 'Temperature', $registered['weather_temperature']['description'] );
		$this->assertSame( 'Wind Gust', $registered['weather_windgust']['description'] );
	}

	/**
	 * The simple display shows the icon, temperature and summary from the condition code.
	 */
	public function test_get_the_weather_simple() {
		$post_id = self::factory()->post->create();
		$this->assertSame( '', Sloc_Weather_Data::get_the_weather( 'post', $post_id ) );

		set_post_weatherdata(
			$post_id,
			'',
			array(
				'temperature' => 21.4,
				'code'        => 800,
			)
		);
		$output = Sloc_Weather_Data::get_the_weather( 'post', $post_id );
		$this->assertStringStartsWith( '<p class="sloc-weather">', $output );
		$this->assertStringContainsString( 'sloc-icon-wi-day-sunny', $output );
		$this->assertStringContainsString( '<data class="p-num" value="21">21</data>', $output );
		$this->assertStringContainsString( '<span class="p-weather">Clear Sky</span>', $output );
	}

	/**
	 * Imperial units convert the temperature; the complete style adds extras; graphic shows only the icon.
	 */
	public function test_get_the_weather_styles() {
		$post_id = self::factory()->post->create();
		set_post_weatherdata(
			$post_id,
			'',
			array(
				'temperature' => 20,
				'humidity'    => 50,
				'cloudiness'  => 10,
				'visibility'  => 1609,
				'summary'     => 'Fair',
			)
		);
		update_option( 'sloc_measurements', 'imperial' );
		$output = Sloc_Weather_Data::get_the_weather(
			'post',
			$post_id,
			array(
				'style'         => 'complete',
				'wrapper-type'  => 'div',
				'wrapper-class' => array( 'one', 'two' ),
			)
		);
		$this->assertStringStartsWith( '<div class="one two">', $output );
		$this->assertStringContainsString( 'value="68">68</data>', $output );
		$this->assertStringContainsString( 'sloc-icon-wi-thermometer', $output );
		$this->assertStringContainsString( '<ul>', $output );
		$this->assertStringContainsString( 'sloc-humidity', $output );
		$this->assertStringContainsString( 'value="1">1</data>', $output, 'Visibility in miles.' );

		// Extras that aren't stored are left out instead of warning.
		$sparse = self::factory()->post->create();
		set_post_weatherdata( $sparse, '', array( 'temperature' => 20 ) );
		$output = Sloc_Weather_Data::get_the_weather( 'post', $sparse, array( 'style' => 'complete' ) );
		$this->assertStringContainsString( 'value="68">68</data>', $output );
		$this->assertStringNotContainsString( '<ul>', $output );

		$graphic = Sloc_Weather_Data::get_the_weather( 'post', $post_id, array( 'style' => 'graphic' ) );
		$this->assertStringContainsString( 'sloc-weather-icon', $graphic );
		$this->assertStringNotContainsString( 'p-weather', $graphic );
		$this->assertStringNotContainsString( 'sloc-temperature', $graphic );
	}

	/**
	 * Current weather is looked up by location, by user and by station, with icons from the code.
	 */
	public function test_weather_lookups() {
		Weather_Provider_Sloc_Test::$conditions = array(
			'temperature' => 15,
			'code'        => 500,
		);
		$weather                                = Sloc_Weather_Data::get_weather_by_location( 40.71, -74.01, 300 );
		$this->assertSame( 'wi-day-rain', $weather['icon'] );
		$this->assertSame( 'light rain', $weather['summary'] );

		$this->assertSame( '', Sloc_Weather_Data::get_weather_by_user( 'nobody' ) );
		$this->assertSame( '', Sloc_Weather_Data::get_weather_by_user( self::factory()->user->create() ) );

		$user_id = self::factory()->user->create();
		Geo_Data::set_geodata(
			'user',
			$user_id,
			'',
			array(
				'latitude'  => 39.9524,
				'longitude' => -75.1636,
			)
		);
		$this->assertSame( 'light rain', Sloc_Weather_Data::get_weather_by_user( $user_id )['summary'] );
		$this->assertSame( 'light rain', Sloc_Weather_Data::get_weather_by_user( new WP_User( $user_id ) )['summary'] );
		$this->assertSame( 39.9524, Weather_Provider_Sloc_Test::$location['latitude'] );

		$this->assertSame( 'light rain', Sloc_Weather_Data::get_weather_by_station( 'KNYC', null, 60 )['summary'] );
		$this->assertSame( 'KNYC', Weather_Provider_Sloc_Test::$station );
		$this->assertEmpty( Loc_Config::weather_provider()->get_station(), 'The shared provider is left as it was (#681).' );

		Weather_Provider_Sloc_Test::$conditions = new WP_Error( 'down', 'Down' );
		$this->assertWPError( Sloc_Weather_Data::get_weather_by_station( 'KNYC' ) );

		// No weather provider set.
		update_option( 'sloc_weather_provider', 'none' );
		foreach ( array(
			Sloc_Weather_Data::get_weather_by_location( 40.71, -74.01 ),
			Sloc_Weather_Data::get_weather_by_user( $user_id ),
			Sloc_Weather_Data::get_weather_by_station( 'KNYC' ),
		) as $weather ) {
			$this->assertWPError( $weather );
			$this->assertSame( 'no_provider', $weather->get_error_code() );
		}
	}

	/**
	 * The condition code select lists every code with the current one selected.
	 */
	public function test_code_select() {
		$options = Sloc_Weather_Data::code_select( '800' );
		$this->assertMatchesRegularExpression( '/value="800"\s+selected/', $options );
		$this->assertStringContainsString( 'value="none"', $options );
		$echoed = $this->capture( array( 'Sloc_Weather_Data', 'code_select' ), '800', true );
		$this->assertMatchesRegularExpression( '/value="800"\s+selected/', $echoed );
	}

	/**
	 * The weather section of the metabox appears only for post types that support weather and when a provider is set.
	 */
	public function test_submitbox() {
		$this->assertSame( 12, has_action( 'simple_location_sidebox', array( 'Sloc_Weather_Data', 'submitbox' ) ) );
		$this->assertTrue( post_type_supports( 'post', 'weather' ) );

		$page = (object) array( 'post_type' => 'page' );
		$this->assertSame( '', $this->capture( array( 'Sloc_Weather_Data', 'submitbox' ), $page, null, array() ) );

		update_option( 'sloc_weather_provider', 'none' );
		$post = (object) array( 'post_type' => 'post' );
		$this->assertSame( '', $this->capture( array( 'Sloc_Weather_Data', 'submitbox' ), $post, null, array() ) );

		update_option( 'sloc_weather_provider', 'sloc_test' );
		set_current_screen( 'post' );
		$GLOBALS['post'] = self::factory()->post->create_and_get();
		$this->capture( array( 'Sloc_Weather_Data', 'submitbox' ), $post, null, array() );
		unset( $GLOBALS['post'] );
		// load_template() uses require_once, so another test may already have rendered it.
		$this->assertContains( realpath( dirname( __DIR__ ) . '/templates/weather-metabox.php' ), get_included_files() );
	}

	/**
	 * Weather meta is registered for every object type.
	 */
	public function test_register_meta() {
		// The test framework unregisters meta keys after each test, so register again.
		Sloc_Weather_Data::register_meta();
		foreach ( array( 'post', 'comment', 'user', 'term' ) as $type ) {
			foreach ( array( 'geo_weather', 'weather_temperature', 'weather_code', 'weather_summary', 'weather_icon' ) as $key ) {
				$this->assertTrue( registered_meta_key_exists( $type, $key ), "$type $key" );
			}
		}
	}

	/**
	 * Unit conversions.
	 */
	public function test_conversions() {
		$this->assertSame( 212.0, Weather_Provider::celsius_to_fahrenheit( 100 ) );
		$this->assertSame( 100.0, Weather_Provider::fahrenheit_to_celsius( 212 ) );
		$this->assertSame( 29.91, Weather_Provider::hpa_to_inhg( 1013 ) );
		$this->assertEqualsWithDelta( 1013.2, Weather_Provider::inhg_to_hpa( 29.92 ), 0.1 );

		$this->assertSame( 'C', Weather_Provider::temp_unit() );
		update_option( 'sloc_measurements', 'imperial' );
		$this->assertSame( 'F', Weather_Provider::temp_unit() );
	}

	/**
	 * Metric conditions convert to imperial and back.
	 */
	public function test_metric_imperial_round_trip() {
		$metric   = array(
			'temperature' => 20,
			'dewpoint'    => 10,
			'pressure'    => 1000,
			'visibility'  => 16090,
			'windspeed'   => 10,
			'windgust'    => 20,
			'rain'        => 25.4,
			'summary'     => 'Fair',
		);
		$imperial = Weather_Provider::metric_to_imperial( $metric );
		$this->assertEquals( 68, $imperial['temperature'] );
		$this->assertEquals( 50, $imperial['dewpoint'] );
		$this->assertEquals( 10, $imperial['visibility'] );
		$this->assertEqualsWithDelta( 22.37, $imperial['windspeed'], 0.01 );
		$this->assertEqualsWithDelta( 1, $imperial['rain'], 0.001 );
		$this->assertSame( 'Fair', $imperial['summary'] );

		$back = Weather_Provider::imperial_to_metric( $imperial );
		foreach ( array( 'temperature', 'dewpoint', 'pressure', 'visibility', 'windspeed', 'windgust', 'rain' ) as $key ) {
			$this->assertEqualsWithDelta( $metric[ $key ], $back[ $key ], 0.05, $key );
		}
		$this->assertSame( 'text', Weather_Provider::imperial_to_metric( 'text' ) );
	}

	/**
	 * Property names, units and labels in each system.
	 */
	public function test_get_names() {
		$metric = Weather_Provider::get_names( 'temperature' );
		$this->assertSame( '&deg;C', $metric['unit'] );
		$this->assertSame( 'Temperature', $metric['name'] );
		$this->assertSame( '&deg;F', Weather_Provider::get_names( 'temperature', true )['unit'] );
		$this->assertSame( '%', Weather_Provider::get_names( 'humidity', true )['unit'], 'Units with no imperial form keep the metric unit.' );
		$this->assertSame( 'inHg', Weather_Provider::get_names( 'pressure', true )['unit'] );
		$this->assertFalse( Weather_Provider::get_names( 'mood' ) );
		$this->assertArrayHasKey( 'windspeed', Weather_Provider::get_names( null ) );
	}

	/**
	 * Values are marked up as h-measure, or as plain labeled text.
	 */
	public function test_markup_value() {
		$markup = Weather_Provider::markup_value( 'temperature', 21.456 );
		$this->assertStringStartsWith( '<li class="sloc-temperature p-temperature h-measure">', $markup );
		$this->assertStringContainsString( '<data class="p-num" value="21.46">21.46</data>', $markup );
		$this->assertStringContainsString( '<data class="p-unit" value="&deg;C">&deg;C</data>', $markup );
		$this->assertStringContainsString( 'value="degrees celsius"', $markup );

		$this->assertStringContainsString( 'value="21">21</data>', Weather_Provider::markup_value( 'temperature', 21.456, array( 'round' => true ) ) );
		$this->assertStringContainsString( 'value="21.5">21.5</data>', Weather_Provider::markup_value( 'temperature', 21.456, array( 'round' => 1 ) ) );
		$this->assertStringContainsString( '&deg;F', Weather_Provider::markup_value( 'temperature', 70, array( 'units' => 'imperial' ) ) );

		// Cloudiness is a whole percentage, including values saved with decimals, unless a precision is asked for.
		$this->assertStringContainsString( 'value="56">56</data>', Weather_Provider::markup_value( 'cloudiness', 55.6 ) );
		$this->assertStringContainsString( 'value="55.6">55.6</data>', Weather_Provider::markup_value( 'cloudiness', 55.6, array( 'round' => 1 ) ) );

		$plain = Weather_Provider::markup_value(
			'humidity',
			50,
			array(
				'markup'    => false,
				'container' => 'span',
			)
		);
		$this->assertStringStartsWith( '<span class="sloc-humidity">', $plain );
		$this->assertStringContainsString( 'Humidity: 50%</span>', $plain );
		$this->assertSame( '', Weather_Provider::markup_value( 'mood', 1 ) );
	}

	/**
	 * Form labels name the property and its unit.
	 */
	public function test_get_form_label() {
		$this->assertSame( 'Pressure(<span aria-label="Pressure measured in inches of mercury">inHg</span>):', Weather_Provider::get_form_label( 'pressure', true ) );
		$this->assertSame( '', Weather_Provider::get_form_label( 'mood', false ) );
	}

	/**
	 * Icons are inline SVGs with an accessible label; unknown icons and 'none' are empty.
	 */
	public function test_get_icon() {
		$icon = Weather_Provider::get_icon( 'wi-day-sunny', 'Sunny' );
		$this->assertStringContainsString( '<span class="sloc-weather-icon sloc-icon-wi-day-sunny" aria-hidden="true" aria-label="Sunny" title="Sunny" >', $icon );
		$this->assertStringContainsString( '<svg', $icon );
		$this->assertStringContainsString( 'title="wi-day-sunny"', Weather_Provider::get_icon( 'wi-day-sunny' ) );
		$this->assertSame( '', Weather_Provider::get_icon( 'none' ) );
		$this->assertSame( '', Weather_Provider::get_icon( 'not-an-icon' ) );
	}

	/**
	 * Condition codes map to summaries and to day or night icons.
	 */
	public function test_condition_codes_and_icons() {
		$this->assertSame( 'Clear Sky', Weather_Provider::weather_condition_codes( '800' ) );
		$this->assertSame( '', Weather_Provider::weather_condition_codes( '1234' ) );
		$this->assertArrayHasKey( '200', Weather_Provider::weather_condition_codes() );

		$this->assertSame( 'wi-day-sunny', Weather_Provider::weather_condition_icons( '800' ) );
		$this->assertSame( 'wi-night-clear', Weather_Provider::weather_condition_icons( '800', false ) );
		$this->assertSame( 'wi-night-thunderstorm', Weather_Provider::weather_condition_icons( '211', false ) );
		$this->assertSame( 'wi-tornado', Weather_Provider::weather_condition_icons( '781' ) );
		$this->assertSame( '', Weather_Provider::weather_condition_icons( '1234' ) );

		// Every code has an icon, and every icon has an SVG.
		foreach ( array_keys( Weather_Provider::weather_condition_codes() ) as $code ) {
			if ( 'none' === $code ) {
				continue;
			}
			foreach ( array( true, false ) as $is_day ) {
				$icon = Weather_Provider::weather_condition_icons( (string) $code, $is_day );
				$this->assertNotSame( '', $icon, "Code $code" );
				$this->assertFileExists( dirname( __DIR__ ) . "/svgs/$icon.svg", "Code $code" );
			}
		}
	}

	/**
	 * The icon select lists None plus every icon, with the current one selected.
	 */
	public function test_icon_select() {
		$list = Weather_Provider::get_iconlist();
		$this->assertArrayHasKey( 'wi-day-sunny', $list );
		$this->assertArrayHasKey( 'wi-moon-full', $list );
		foreach ( array_keys( $list ) as $icon ) {
			$this->assertSame( sanitize_html_class( $icon ), $icon, 'Every icon is a usable class name.' );
		}

		$options = Weather_Provider::icon_select( '' );
		$this->assertMatchesRegularExpression( '/value="none"\s+selected/', $options );
		$this->assertSame( count( $list ) + 1, substr_count( $options, '<option' ) );
		$this->assertMatchesRegularExpression( '/value="wi-fog"\s+selected/', $this->capture( array( 'Weather_Provider', 'icon_select' ), 'wi-fog', true ) );
	}

	/**
	 * The provider tracks station and units, and its active state follows the options.
	 */
	public function test_provider_settings() {
		$provider = new Weather_Provider_Sloc_Test(
			array(
				'station_id' => 'KJFK',
				'units'      => 'imperial',
			)
		);
		$this->assertSame( 'KJFK', $provider->get_station() );
		$this->assertTrue( $provider->is_station() );
		$this->assertTrue( $provider->is_active() );

		$provider->set(
			array(
				'station_id' => 'KLGA',
				'units'      => 'metric',
				'latitude'   => 40.77,
				'longitude'  => -73.87,
			)
		);
		$this->assertSame( 'KLGA', $provider->get_station() );

		update_option( 'sloc_weather_provider', 'none' );
		$this->assertFalse( $provider->is_active() );
		update_option( 'sloc_weather_fallbacks', array( 'sloc_test_fallback', 'sloc_test' ) );
		$this->assertTrue( $provider->is_active(), 'A fallback provider is active.' );
	}

	/**
	 * The current temperature and condition use the provider's units.
	 */
	public function test_current_condition() {
		$provider = new Weather_Provider_Sloc_Test();
		$this->assertSame( '20&deg;C', $provider->get_current_temperature() );
		$condition = $provider->get_current_condition();
		$this->assertStringStartsWith( '<div class="sloc-weather">', $condition );
		$this->assertStringContainsString( '20&deg;C', $condition );

		update_option( 'sloc_measurements', 'imperial' );
		$provider = new Weather_Provider_Sloc_Test( array( 'units' => 'imperial' ) );
		$this->assertSame( '68&deg;F', $provider->get_current_temperature() );

		Weather_Provider_Sloc_Test::$conditions = array();
		$this->assertSame( '', $provider->get_current_temperature() );

		Weather_Provider_Sloc_Test::$conditions = new WP_Error( 'down', 'Down' );
		$this->assertSame( '', $provider->get_current_temperature() );
		$this->assertStringStartsWith( '<div class="sloc-weather">', $provider->get_current_condition(), 'An error shows no weather.' );
	}

	/**
	 * The current temperature and condition work with a provider that follows the abstract signature, with no default time.
	 */
	public function test_current_condition_required_time() {
		$provider = new class() extends Weather_Provider {
			/**
			 * Returns 10 °C for current conditions.
			 *
			 * @param mixed $time Time.
			 * @return array
			 */
			public function get_conditions( $time ) {
				return array( 'temperature' => null === $time ? 10 : 0 );
			}
		};
		$this->assertSame( '10&deg;C', $provider->get_current_temperature() );
		$this->assertStringContainsString( '10&deg;C', $provider->get_current_condition() );
	}

	/**
	 * Conditions are cached per station or location only when a cache time is set.
	 */
	public function test_cache() {
		$provider = new Weather_Provider_Sloc_Test();
		$provider->set( 40.71, -74.01 );
		$this->assertFalse( $provider->set_cache( array( 'temperature' => 1 ) ), 'No cache time.' );
		$this->assertFalse( $provider->get_cache() );

		$provider->set_cache_time( 600 );
		$this->assertFalse( $provider->set_cache( 'text' ) );
		$this->assertTrue( $provider->set_cache( array( 'temperature' => 1 ) ) );
		$cached = $provider->get_cache();
		$this->assertSame( 1, $cached['temperature'] );
		$this->assertArrayHasKey( '_expires_at', $cached );

		$provider->set( array( 'station_id' => 'KJFK' ) );
		$this->assertFalse( $provider->get_cache(), 'Stations have their own cache entry.' );
	}

	/**
	 * Extra data adds sun and moon times, day or night, and the local time at the location.
	 */
	public function test_extra_data() {
		$provider  = new Weather_Provider_Sloc_Test();
		$timestamp = strtotime( '2024-07-01T16:00:00Z' );
		$data      = $provider->extra_data(
			array(
				'latitude'    => 40.7128,
				'longitude'   => -74.006,
				'temperature' => 20,
			),
			$timestamp
		);
		$this->assertSame( '2024-07-01T12:00:00-04:00', $data['localtime'] );
		$this->assertTrue( $data['day'] );
		foreach ( array( 'sunrise', 'sunset', 'moonrise', 'moonset' ) as $key ) {
			$this->assertMatchesRegularExpression( '/^\d{4}-\d\d-\d\dT/', $data[ $key ], $key );
		}
		$this->assertSame( 20, $data['temperature'] );
	}

	/**
	 * Extra data keeps readings of 0 and `day` at night, and drops values that aren't set.
	 */
	public function test_extra_data_keeps_zero_and_night() {
		$provider = new Weather_Provider_Sloc_Test();
		$data     = $provider->extra_data(
			array(
				'latitude'    => 40.7128,
				'longitude'   => -74.006,
				'temperature' => 0,
				'rain'        => 0.0,
				'humidity'    => '0',
				'windgust'    => false,
				'snow'        => null,
				'summary'     => '',
			),
			strtotime( '2024-07-01T06:00:00Z' )
		);
		$this->assertFalse( $data['day'] );
		$this->assertSame( 0, $data['temperature'] );
		$this->assertSame( 0.0, $data['rain'] );
		$this->assertSame( '0', $data['humidity'] );
		foreach ( array( 'windgust', 'snow', 'summary' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $data, $key );
		}
	}

	/**
	 * Unit conversions return null for values that aren't numbers, instead of 0.
	 */
	public function test_unit_conversions_not_a_number() {
		$helpers = array( 'mm_to_inches', 'inches_to_mm', 'feet_to_meters', 'meters_to_feet', 'meters_to_miles', 'miles_to_meters', 'km_to_meters', 'kmh_to_ms', 'm_to_mm', 'cm_to_mm', 'miph_to_mps', 'mps_to_miph', 'mph_to_mps' );
		foreach ( $helpers as $helper ) {
			foreach ( array( null, false, '', 'abc', array() ) as $value ) {
				$this->assertNull( call_user_func( array( 'Sloc_Provider', $helper ), $value ), $helper . ' ' . wp_json_encode( $value ) );
			}
			$this->assertEquals( 0, call_user_func( array( 'Sloc_Provider', $helper ), 0 ), $helper );
		}
		$this->assertSame( 10.0, Sloc_Provider::kmh_to_ms( '36' ) );
		$this->assertSame( 400.0, Sloc_Provider::km_to_meters( 0.4 ) );
		$this->assertSame( 25.0, Sloc_Provider::cm_to_mm( 2.5 ) );
	}

	/**
	 * A provider's fallback conditions are an error, without calling another provider: the chain does that (#490).
	 */
	public function test_fallback_conditions() {
		$provider = new Weather_Provider_Sloc_Test();
		$method   = new ReflectionMethod( $provider, 'get_fallback_conditions' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		update_option( 'sloc_weather_fallbacks', array( 'sloc_test_fallback' ) );
		$error = $method->invoke( $provider, 123 );
		$this->assertSame( 'no_results', $error->get_error_code() );
		$this->assertStringContainsString( 'Test Weather', $error->get_error_message() );
		$this->assertSame( array(), Weather_Provider_Sloc_Test::$calls );
	}
}

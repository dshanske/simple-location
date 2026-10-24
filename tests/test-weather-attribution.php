<?php
/**
 * Tests for weather provider credits: each provider's credit, storing the provider with the weather, and showing the
 * credit next to the weather or in the footer.
 *
 * @package Simple_Location
 */

/**
 * Weather attribution tests.
 */
class WeatherAttributionTest extends WP_UnitTestCase {

	/**
	 * Known settings for every test.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_measurements', 'metric' );
		delete_option( 'sloc_weather_attribution' );
	}

	/**
	 * Clears credits waiting for the footer.
	 */
	public function tear_down() {
		ob_start();
		Sloc_Weather_Data::footer_credits();
		ob_end_clean();
		remove_action( 'wp_footer', array( 'Sloc_Weather_Data', 'footer_credits' ) );
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Providers, with the start of their credit and whether it must be next to the weather.
	 *
	 * @return array
	 */
	public function data_credits() {
		return array(
			'Open-Meteo'     => array( 'openmeteo', '<a href="https://open-meteo.com/">Weather data by Open-Meteo.com</a>', true ),
			'MET Norway'     => array( 'metno', '<a href="https://www.met.no/en">Weather data from MET Norway</a>', true ),
			'OpenWeather'    => array( 'openweathermap', '<a href="https://openweathermap.org/">Weather data provided by OpenWeather</a>', false ),
			'Xweather'       => array( 'aeris', '<a href="https://www.xweather.com/">Powered by Vaisala Xweather</a>', false ),
			'Weatherbit'     => array( 'weatherbit', '<a href="https://www.weatherbit.io/">Weather data by Weatherbit.io</a>', false ),
			'Meteostat'      => array( 'meteostat', '<a href="https://meteostat.net/">Source: Meteostat and its data providers</a>', false ),
		);
	}

	/**
	 * Each provider gives its credit as a link, and says whether its terms require it next to the weather.
	 *
	 * @dataProvider data_credits
	 *
	 * @param string $slug     Provider slug.
	 * @param string $html     Credit HTML.
	 * @param bool   $adjacent Whether the credit must be next to the weather.
	 */
	public function test_provider_credit( $slug, $html, $adjacent ) {
		$provider = Loc_Config::weather_provider( $slug );
		$this->assertInstanceOf( 'Weather_Provider', $provider, $slug );
		$this->assertSame( $html, $provider->get_attribution_html() );
		$this->assertSame( $adjacent, $provider->get_attribution()['adjacent'] );
	}

	/**
	 * Providers that don't require a credit have none: the National Weather Service's data is public domain, no
	 * requirement was found for HERE, Visual Crossing, weatherstack or Pirate Weather, and custom stations will be
	 * credited separately (#661).
	 */
	public function test_providers_without_credit() {
		foreach ( array( 'nwsus', 'station', 'here', 'visualcrossing', 'weatherstack', 'pirateweather' ) as $slug ) {
			$this->assertSame( array(), Loc_Config::weather_provider( $slug )->get_attribution(), $slug );
			$this->assertSame( '', Loc_Config::weather_provider( $slug )->get_attribution_html(), $slug );
			$this->assertSame( '', Sloc_Weather_Data::get_attribution_markup( $slug ), $slug );
		}
		$this->assertSame( '', Sloc_Weather_Data::get_attribution_markup( 'retired-provider' ) );
		$this->assertSame( '', Sloc_Weather_Data::get_attribution_markup( '' ) );
	}

	/**
	 * The sloc_weather_credit filter can remove a credit, such as on a paid plan, or add one.
	 */
	public function test_credit_filter() {
		$filter = function ( $credit, $provider ) {
			if ( 'openweathermap' === $provider->get_slug() ) {
				return array();
			}
			if ( 'here' === $provider->get_slug() ) {
				return array(
					'text'     => 'Weather from HERE',
					'url'      => 'https://www.here.com/',
					'adjacent' => false,
				);
			}
			if ( 'weatherbit' === $provider->get_slug() ) {
				return 'not a credit';
			}
			return $credit;
		};
		add_filter( 'sloc_weather_credit', $filter, 10, 2 );
		$this->assertSame( '', Sloc_Weather_Data::get_attribution_markup( 'openweathermap' ) );
		$this->assertSame( '<span class="sloc-weather-attribution"><a href="https://www.here.com/">Weather from HERE</a></span>', Sloc_Weather_Data::get_attribution_markup( 'here' ) );
		$this->assertSame( array(), Loc_Config::weather_provider( 'weatherbit' )->get_attribution() );
		$this->assertStringContainsString( 'Open-Meteo.com', Sloc_Weather_Data::get_attribution_markup( 'openmeteo' ) );
		remove_filter( 'sloc_weather_credit', $filter, 10 );
	}

	/**
	 * Conditions name the provider they came from, and a fallback provider's name is kept.
	 */
	public function test_conditions_name_provider() {
		$provider = new Weather_Provider_Sloc_Test();
		$provider->set( 39.9524, -75.1636 );
		$this->assertSame( 'sloc_test', $provider->extra_data( array( 'temperature' => 20 ) )['provider'] );
		$this->assertSame(
			'openmeteo',
			$provider->extra_data(
				array(
					'temperature' => 20,
					'provider'    => 'openmeteo',
				)
			)['provider']
		);
	}

	/**
	 * The provider is stored with the weather, and its credit follows the weather on the page.
	 */
	public function test_credit_next_to_weather() {
		$post_id = self::factory()->post->create();
		set_post_weatherdata(
			$post_id,
			'',
			array(
				'temperature' => 21.5,
				'code'        => 800,
				'provider'    => 'openweathermap',
			)
		);
		$this->assertSame( 'openweathermap', get_post_meta( $post_id, 'weather_provider', true ) );

		$html = Sloc_Weather_Data::get_the_weather( 'post', $post_id );
		$this->assertStringContainsString( '<span class="sloc-weather-attribution"><a href="https://openweathermap.org/">Weather data provided by OpenWeather</a></span>', $html );
		$this->assertStringEndsWith( '</span></p>', $html );

		// Weather without a recorded provider, such as weather saved before 6.1.0, has no credit.
		delete_post_meta( $post_id, 'weather_provider' );
		$this->assertStringNotContainsString( 'sloc-weather-attribution', Sloc_Weather_Data::get_the_weather( 'post', $post_id ) );
	}

	/**
	 * In footer mode, credits are printed once in the footer, except those the provider requires next to the weather.
	 */
	public function test_credit_in_footer() {
		update_option( 'sloc_weather_attribution', 'footer' );
		$posts = array();
		foreach ( array( 'openweathermap', 'openweathermap', 'meteostat', 'openmeteo', 'here' ) as $provider ) {
			$post_id = self::factory()->post->create();
			set_post_weatherdata(
				$post_id,
				'',
				array(
					'temperature' => 21.5,
					'provider'    => $provider,
				)
			);
			$posts[ $post_id ] = $provider;
		}
		foreach ( $posts as $post_id => $provider ) {
			$html = Sloc_Weather_Data::get_the_weather( 'post', $post_id );
			if ( 'openmeteo' === $provider ) {
				$this->assertStringContainsString( 'Weather data by Open-Meteo.com', $html, 'Open-Meteo stays next to its weather.' );
			} else {
				$this->assertStringNotContainsString( 'sloc-weather-attribution', $html, $provider );
			}
		}
		$this->assertSame( 10, has_action( 'wp_footer', array( 'Sloc_Weather_Data', 'footer_credits' ) ) );

		ob_start();
		Sloc_Weather_Data::footer_credits();
		$footer = ob_get_clean();
		$this->assertSame( 1, substr_count( $footer, 'Weather data provided by OpenWeather' ), 'Each credit is printed once.' );
		$this->assertStringContainsString( 'Source: Meteostat and its data providers', $footer );
		$this->assertStringNotContainsString( 'Open-Meteo', $footer );
		$this->assertStringContainsString( '<p class="sloc-weather-attribution">Weather: <a href="https://openweathermap.org/">', $footer );

		// Nothing is printed again, or on a page without weather.
		ob_start();
		Sloc_Weather_Data::footer_credits();
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * The provider alone isn't weather, so it isn't shown and doesn't count.
	 */
	public function test_provider_alone_is_not_weather() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'weather_provider', 'openweathermap' );
		$this->assertSame( '', Sloc_Weather_Data::get_the_weather( 'post', $post_id ) );
		$this->assertFalse( Sloc_Weather_Data::has_weather( 'post', $post_id ) );

		update_post_meta( $post_id, 'weather_temperature', 20 );
		$this->assertTrue( Sloc_Weather_Data::has_weather( 'post', $post_id ) );
	}

	/**
	 * The editor saves the provider a lookup filled in, only with weather values and only for a known provider.
	 */
	public function test_save_provider_from_form() {
		$post_id = self::factory()->post->create();
		$_POST   = array(
			'temperature'      => '21.5',
			'weather_provider' => 'openmeteo',
		);
		Geo_Base::save_meta( 'post', $post_id );
		$this->assertSame( 'openmeteo', get_post_meta( $post_id, 'weather_provider', true ) );

		$_POST['weather_provider'] = 'not-a-provider';
		Geo_Base::save_meta( 'post', $post_id );
		$this->assertSame( '', get_post_meta( $post_id, 'weather_provider', true ) );

		$other = self::factory()->post->create();
		$_POST = array( 'weather_provider' => 'openmeteo' );
		Geo_Base::save_meta( 'post', $other );
		$this->assertSame( '', get_post_meta( $other, 'weather_provider', true ) );
	}

	/**
	 * The weather widgets end with the provider's credit, and don't list the provider as a reading.
	 */
	public function test_widget_credit() {
		$list = new ReflectionMethod( 'Sloc_Weather_Widget', 'weather_list' );
		if ( PHP_VERSION_ID < 80100 ) {
			$list->setAccessible( true );
		}
		$html = $list->invoke(
			null,
			array(
				'temperature' => 21.5,
				'provider'    => 'openmeteo',
			)
		);
		$this->assertStringEndsWith( '<span class="sloc-weather-attribution"><a href="https://open-meteo.com/">Weather data by Open-Meteo.com</a></span>', $html );
		$this->assertStringNotContainsString( '>openmeteo<', $html );
	}

	/**
	 * The Weather Credits setting is registered, sanitized and shown with its choices.
	 */
	public function test_setting() {
		global $wp_settings_fields;
		require_once ABSPATH . 'wp-admin/includes/template.php';

		$this->assertSame( 'next', get_option( 'sloc_weather_attribution' ) );
		$this->assertSame( 'footer', sanitize_option( 'sloc_weather_attribution', 'footer' ) );
		$this->assertSame( 'next', sanitize_option( 'sloc_weather_attribution', 'sidebar' ) );

		Loc_Config::admin_init();
		$field = $wp_settings_fields['simloc']['sloc_general']['sloc_weather_attribution'];
		$this->assertSame( 'Weather Credits', $field['title'] );
		$this->assertSame( array( 'next', 'footer' ), array_keys( $field['args']['list'] ) );
		$this->assertStringContainsString( 'Open-Meteo', $field['args']['description'] );
	}
}

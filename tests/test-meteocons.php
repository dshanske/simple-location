<?php
/**
 * Tests the Meteocons icon set for the weather conditions (#808).
 *
 * @package Simple_Location
 */

// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the plugin's own icon files.

/**
 * Meteocons tests.
 */
class MeteoconsTest extends WP_UnitTestCase {

	/**
	 * Clean up.
	 */
	public function tear_down() {
		delete_option( 'sloc_weather_icons' );
		parent::tear_down();
	}

	/**
	 * The plugin's directory.
	 *
	 * @return string
	 */
	protected function dir() {
		return dirname( __DIR__ ) . '/';
	}

	/**
	 * Each mapped Weather Icons icon and its Meteocons icon are bundled, with the license.
	 */
	public function test_map_and_files() {
		$map = Weather_Provider::meteocons_map();
		$this->assertNotEmpty( $map );
		foreach ( $map as $weather_icon => $meteocon ) {
			$this->assertFileExists( $this->dir() . 'svgs/' . $weather_icon . '.svg', $weather_icon );
			$this->assertFileExists( $this->dir() . 'svgs/meteocons/' . $meteocon . '.svg', $meteocon );
		}
		$bundled = array_map( fn( $file ) => basename( $file, '.svg' ), glob( $this->dir() . 'svgs/meteocons/*.svg' ) );
		$this->assertEqualSets( array_unique( array_values( $map ) ), $bundled, 'Only the mapped icons are bundled.' );
		$this->assertStringContainsString( 'MIT License', file_get_contents( $this->dir() . 'svgs/meteocons/LICENSE' ) );
	}

	/**
	 * Every condition icon the plugin picks for a condition code has a Meteocons icon.
	 */
	public function test_every_condition_is_mapped() {
		$map = Weather_Provider::meteocons_map();
		for ( $code = 200; $code < 1000; $code++ ) {
			foreach ( array( true, false ) as $day ) {
				$icon = Weather_Provider::weather_condition_icons( (string) $code, $day );
				if ( is_string( $icon ) && 0 === strpos( $icon, 'wi-' ) ) {
					$this->assertArrayHasKey( $icon, $map, $code . ( $day ? ' day' : ' night' ) );
				}
			}
		}
	}

	/**
	 * Weather Icons by default; with Meteocons, the condition icons are Meteocons and the others Weather Icons.
	 */
	public function test_icon_file() {
		$this->assertSame( 'weather-icons', Weather_Provider::icon_set() );
		$this->assertStringEndsWith( 'svgs/wi-day-cloudy.svg', Weather_Provider::icon_file( 'wi-day-cloudy' ) );
		$this->assertStringContainsString( trim( file_get_contents( $this->dir() . 'svgs/wi-day-cloudy.svg' ) ), Weather_Provider::get_icon( 'wi-day-cloudy' ) );

		$this->assertStringNotContainsString( 'sloc-icon-set-meteocons', Weather_Provider::get_icon( 'wi-day-cloudy' ) );

		update_option( 'sloc_weather_icons', 'meteocons' );
		$this->assertSame( 'meteocons', Weather_Provider::icon_set() );
		$this->assertStringEndsWith( 'svgs/meteocons/partly-cloudy-day.svg', Weather_Provider::icon_file( 'wi-day-cloudy' ) );
		$this->assertStringEndsWith( 'svgs/wi-moon-full.svg', Weather_Provider::icon_file( 'wi-moon-full' ), 'Not a condition icon.' );
		$this->assertStringEndsWith( 'svgs/fa-icy.svg', Weather_Provider::icon_file( 'fa-icy' ) );
		$this->assertSame( '', Weather_Provider::icon_file( 'no-such-icon' ) );
		$this->assertStringEndsWith( 'svgs/meteocons/clear-day.svg', Weather_Provider::icon_file( '../../wi-day-sunny' ), 'Only a file name.' );

		$html = Weather_Provider::get_icon( 'wi-day-cloudy', 'Partly Cloudy' );
		$this->assertStringContainsString( 'class="sloc-weather-icon sloc-icon-wi-day-cloudy sloc-icon-set-meteocons"', $html, 'The class keeps the stored name.' );
		$this->assertStringContainsString( 'class="sloc-weather-icon sloc-icon-wi-moon-full"', Weather_Provider::get_icon( 'wi-moon-full' ) );
		$this->assertStringContainsString( 'title="Partly Cloudy"', $html );
		$this->assertStringContainsString( 'viewBox="0 0 128 128"', $html );
		$this->assertSame( '', Weather_Provider::get_icon( 'none' ) );
	}

	/**
	 * The setting is registered, sanitized, and offered on the settings page.
	 */
	public function test_setting() {
		$this->assertSame( 'meteocons', Weather_Provider::sanitize_icon_set( 'meteocons' ) );
		foreach ( array( 'emoji', '', null, array( 'meteocons' ) ) as $invalid ) {
			$this->assertSame( 'weather-icons', Weather_Provider::sanitize_icon_set( $invalid ) );
		}
		$this->assertArrayHasKey( 'sloc_weather_icons', get_registered_settings() );
		update_option( 'sloc_weather_icons', 'emoji' );
		$this->assertSame( 'weather-icons', get_option( 'sloc_weather_icons' ), 'Sanitized when saved.' );
		$this->assertSame( array( 'weather-icons', 'meteocons' ), array_keys( Weather_Provider::icon_sets() ) );
	}

	/**
	 * Each icon survives the plugin's allowed HTML, which the weather output goes through, unchanged.
	 *
	 * Kses lowercases attribute names, such as viewBox; HTML parsers restore the case of SVG's, as for Weather Icons.
	 */
	public function test_icons_pass_kses() {
		foreach ( glob( $this->dir() . 'svgs/meteocons/*.svg' ) as $file ) {
			$svg  = trim( file_get_contents( $file ) );
			$norm = fn( $markup ) => strtolower( preg_replace( '/\s*\/>/', '/>', str_replace( "'", '"', $markup ) ) );
			$this->assertSame( $norm( $svg ), $norm( wp_kses( $svg, Simple_Location_Plugin::kses_clean() ) ), basename( $file ) );
		}
	}

	/**
	 * Each icon's IDs have its name as a prefix, so icons on one page don't share them, and are all used.
	 */
	public function test_icon_ids() {
		$all = array();
		foreach ( glob( $this->dir() . 'svgs/meteocons/*.svg' ) as $file ) {
			$name = basename( $file, '.svg' );
			$svg  = file_get_contents( $file );
			preg_match_all( '/\bid="([^"]+)"/', $svg, $ids );
			preg_match_all( '/url\(#([^)]+)\)/', $svg, $refs );
			foreach ( $ids[1] as $id ) {
				$this->assertStringStartsWith( 'mc-' . $name . '-', $id, $name );
				$this->assertContains( $id, $refs[1], 'Only IDs that are used are kept.' );
			}
			foreach ( $refs[1] as $ref ) {
				$this->assertContains( $ref, $ids[1], $name . ' refers to its own IDs.' );
			}
			$this->assertStringNotContainsString( 'style=', $svg );
			$all = array_merge( $all, $ids[1] );
		}
		$this->assertSame( count( $all ), count( array_unique( $all ) ) );
	}
}

<?php
/**
 * Tests for Astronomical_Calculator: sunrise and sunset, day and night, and the moon.
 *
 * Expected times are from published almanac tables, so the assertions allow a few minutes.
 *
 * @package Simple_Location
 */

/**
 * Astronomical calculator tests.
 */
class AstronomicalTest extends WP_UnitTestCase {

	/**
	 * A calculator for Philadelphia City Hall.
	 *
	 * @return Astronomical_Calculator
	 */
	protected function philadelphia() {
		return new Astronomical_Calculator( 39.9524, -75.1636 );
	}

	public function test_is_day() {
		$calc = new Astronomical_Calculator( '39.833', '-98.583' );
		$this->assertFalse( $calc->is_daytime( 1664674052 ) );
		$this->assertTrue( $calc->is_daytime( 1664728052 ) );
	}

	/**
	 * The calculator uses the timezone of the coordinates, or the site's when none is found.
	 */
	public function test_timezone() {
		$this->assertSame( '-04:00', $this->philadelphia()->get_datetime( strtotime( '2025-07-01T16:00:00Z' ) )->format( 'P' ) );

		update_option( 'timezone_string', 'Europe/Berlin' );
		$calc = new Astronomical_Calculator( 30.0, -40.0 );
		$this->assertSame( 'Europe/Berlin', $calc->get_datetime( strtotime( '2025-07-01T16:00:00Z' ) )->getTimezone()->getName() );
	}

	/**
	 * Sunrise and sunset in Philadelphia on 2025-07-01 are about 5:35 and 8:33 pm EDT.
	 */
	public function test_sunrise_and_sunset() {
		$calc = $this->philadelphia();
		$noon = strtotime( '2025-07-01T12:00:00-04:00' );

		$this->assertEqualsWithDelta( strtotime( '2025-07-01T05:35:00-04:00' ), $calc->get_timestamp( $noon ), 180 );
		$this->assertEqualsWithDelta( strtotime( '2025-07-01T20:33:00-04:00' ), $calc->get_timestamp( $noon, 'sunset' ), 180 );

		$this->assertStringStartsWith( '2025-07-01T05:3', $calc->get_iso8601( $noon ) );
		$this->assertStringEndsWith( '-04:00', $calc->get_iso8601( $noon ) );
		$this->assertStringStartsWith( '2025-07-01T20:3', $calc->get_iso8601( $noon, 'sunset' ) );
		$this->assertSame( 'America/New_York', $calc->get_datetime( $noon, 'sunset' )->getTimezone()->getName() );
		$this->assertMatchesRegularExpression( '/^5:3\d am$/', $calc->get_formatted( $noon, 'g:i a' ) );

		// Strings and DateTime objects work as well as timestamps.
		$this->assertSame( $calc->get_timestamp( $noon ), $calc->get_timestamp( '2025-07-01T12:00:00-04:00' ) );
		$this->assertSame( $calc->get_timestamp( $noon ), $calc->get_timestamp( new DateTime( '2025-07-01T12:00:00-04:00' ) ) );

		// No time means today.
		$this->assertSame( $calc->get_timestamp( time() ), $calc->get_timestamp( null ) );
	}

	/**
	 * In the evening west of UTC, sunrise and sunset are for the local date, not the next UTC day.
	 */
	public function test_sun_times_use_local_date_west() {
		$calc    = $this->philadelphia();
		$evening = strtotime( '2025-07-01T20:15:00-04:00' ); // 2025-07-02 00:15 UTC.

		$this->assertEqualsWithDelta( strtotime( '2025-07-01T05:35:00-04:00' ), $calc->get_timestamp( $evening ), 180 );
		$this->assertEqualsWithDelta( strtotime( '2025-07-01T20:33:00-04:00' ), $calc->get_timestamp( $evening, 'sunset' ), 180 );
		$this->assertTrue( $calc->is_daytime( $evening ) );
		$this->assertFalse( $calc->is_daytime( strtotime( '2025-07-01T21:00:00-04:00' ) ) );
	}

	/**
	 * In the morning east of UTC, sunrise and sunset are for the local date, not the previous UTC day.
	 */
	public function test_sun_times_use_local_date_east() {
		$calc    = new Astronomical_Calculator( 35.6812, 139.7671 ); // Tokyo Station.
		$morning = strtotime( '2025-07-01T07:00:00+09:00' ); // 2025-06-30 22:00 UTC.

		$this->assertSame( 'Asia/Tokyo', $calc->get_datetime( $morning )->getTimezone()->getName() );
		$this->assertEqualsWithDelta( strtotime( '2025-07-01T04:28:00+09:00' ), $calc->get_timestamp( $morning ), 180 );
		$this->assertEqualsWithDelta( strtotime( '2025-07-01T19:01:00+09:00' ), $calc->get_timestamp( $morning, 'sunset' ), 180 );
		$this->assertTrue( $calc->is_daytime( $morning ) );
	}

	/**
	 * Daytime is between sunrise and sunset.
	 */
	public function test_is_daytime() {
		$calc = $this->philadelphia();
		$this->assertTrue( $calc->is_daytime( strtotime( '2025-07-01T12:00:00-04:00' ) ) );
		$this->assertTrue( $calc->is_daytime( strtotime( '2025-07-01T06:00:00-04:00' ) ) );
		$this->assertFalse( $calc->is_daytime( strtotime( '2025-07-01T05:00:00-04:00' ) ) );
		$this->assertFalse( $calc->is_daytime( strtotime( '2025-12-21T18:00:00-05:00' ) ) );
		$this->assertIsBool( $calc->is_daytime() );

		$this->assertTrue( Astronomical_Calculator::between( 5, 1, 10 ) );
		$this->assertFalse( Astronomical_Calculator::between( 1, 1, 10 ) );
		$this->assertFalse( Astronomical_Calculator::between( 11, 1, 10 ) );
	}

	/**
	 * Julian dates count from noon on 2000-01-01 (J2000).
	 */
	public function test_julian_days() {
		$calc  = $this->philadelphia();
		$j2000 = strtotime( '2000-01-01T12:00:00Z' );
		$this->assertEqualsWithDelta( 2451545.0, $calc->to_julian( $j2000 ), 0.000001 );
		$this->assertEqualsWithDelta( 0.0, $calc->to_days( $j2000 ), 0.000001 );
		$this->assertEqualsWithDelta( 2440587.5, $calc->to_julian( 0 ), 0.000001 );
		$this->assertEquals( 1000 + 5400, $calc->hours_later( 1000, 1.5 ) );
	}

	/**
	 * The moon is fully lit at the full moon of 2025-07-10 and dark at the new moon of 2025-07-24.
	 */
	public function test_moon_illumination() {
		$calc = $this->philadelphia();

		$full = $calc->get_moon_illumination( strtotime( '2025-07-10T20:36:00Z' ) );
		$this->assertEqualsWithDelta( 1.0, $full['fraction'], 0.01 );
		$this->assertEqualsWithDelta( 0.5, $full['phase'], 0.02 );

		$new = $calc->get_moon_illumination( strtotime( '2025-07-24T19:11:00Z' ) );
		$this->assertEqualsWithDelta( 0.0, $new['fraction'], 0.01 );

		$first = $calc->get_moon_illumination( strtotime( '2025-07-02T19:30:00Z' ) );
		$this->assertEqualsWithDelta( 0.5, $first['fraction'], 0.02 );
		$this->assertEqualsWithDelta( 0.25, $first['phase'], 0.02 );

		$this->assertArrayHasKey( 'dist', $full );
		$this->assertGreaterThan( 356000, $full['dist'] );
		$this->assertLessThan( 407000, $full['dist'] );
	}

	/**
	 * Crescent and gibbous phases are named by whether the moon is waxing or waning.
	 */
	public function test_moon_names() {
		$calc = $this->philadelphia();
		$this->assertSame( 'waxing-crescent-moon', $calc->get_moon_data( strtotime( '2025-06-28T12:00:00Z' ) )['name'] );
		$this->assertSame( 'waning-gibbous-moon', $calc->get_moon_data( strtotime( '2025-07-14T12:00:00Z' ) )['name'] );
		$this->assertSame( 'waning-crescent-moon', $calc->get_moon_data( strtotime( '2025-07-21T12:00:00Z' ) )['name'] );

		$data = $calc->get_moon_data( strtotime( '2025-07-14T12:00:00Z' ) );
		$this->assertSame( 'Waning Gibbous', $data['text'] );
		$this->assertSame( 'wi-moon-alt-waning-gibbous-1', $data['icon'] );
		$this->assertArrayHasKey( 'fraction', $data );
	}

	/**
	 * Moonrise and moonset fall within the local day, at times when the moon is on the horizon.
	 */
	public function test_moon_times() {
		$calc  = $this->philadelphia();
		$day   = strtotime( '2025-07-10T00:00:00-04:00' );
		$times = $calc->get_moon_times( strtotime( '2025-07-10T12:00:00Z' ) );

		foreach ( array( 'moonrise', 'moonset' ) as $event ) {
			$this->assertGreaterThanOrEqual( $day, $times[ $event ], $event );
			$this->assertLessThan( $day + DAY_IN_SECONDS, $times[ $event ], $event );
			$altitude = $calc->get_moon_position( $times[ $event ] )['altitude'];
			$this->assertEqualsWithDelta( 0.0, $altitude, deg2rad( 1 ), $event );
		}
		$this->assertSame( $times['moonrise'], $calc->get_timestamp( strtotime( '2025-07-10T12:00:00Z' ), 'moonrise' ) );
		$this->assertSame( $times['moonset'], $calc->get_timestamp( strtotime( '2025-07-10T12:00:00Z' ), 'moonset' ) );

		// In the evening, after the UTC date has changed, the times are still for the local day.
		$this->assertSame( $times, $calc->get_moon_times( strtotime( '2025-07-10T23:30:00-04:00' ) ) );
	}

	/**
	 * Near the pole in midsummer the moon can stay up or down all day.
	 */
	public function test_moon_always_up_or_down() {
		$calc  = new Astronomical_Calculator( 89.9, 0 );
		$times = $calc->get_moon_times( strtotime( '2025-07-10T12:00:00Z' ) );
		$this->assertTrue( isset( $times['alwaysUp'] ) || isset( $times['alwaysDown'] ) );
		$this->assertSame( 0, $times['moonrise'] );
		$this->assertSame( 0, $times['moonset'] );
	}

	/**
	 * Clear-sky radiation increases with elevation.
	 */
	public function test_clear_sky_radiation() {
		$this->assertEqualsWithDelta( 0.75, $this->philadelphia()->clear_sky_radiation( 1 ), 0.000001 );
		$high = new Astronomical_Calculator( 39.7392, -104.9903, 1609 );
		$this->assertEqualsWithDelta( 0.75 + 0.00002 * 1609, $high->clear_sky_radiation( 1 ), 0.000001 );
	}
}

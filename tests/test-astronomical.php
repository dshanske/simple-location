<?php

class AstronomicalTest extends WP_UnitTestCase {
	public function test_is_day() {
		$calc = new Astronomical_Calculator( '39.833', '-98.583' );
		$this->assertFalse( $calc->is_daytime( 1664674052 ) );
		$this->assertTrue( $calc->is_daytime( 1664728052 ) );
	}
}


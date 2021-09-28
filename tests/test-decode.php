<?php

class DecodeTest extends WP_UnitTestCase {
	public function test_country_code_iso3() {
		$iso2 = Geo_Provider::country_code_iso3( 'USA' );
		$this->assertEquals( 'US', $iso2, wp_json_encode( $iso2 ) );
		$iso2 = Geo_Provider::country_code_iso3( 'GBR' );
		$this->assertEquals( 'GB', $iso2, wp_json_encode( $iso2 ) );
	}

	public function test_country_name() {
		$name = Geo_Provider::country_name( 'US' );
		$this->assertEquals( 'United States', $name, wp_json_encode( $name ) );
	}

	public function test_country_code() {
		$name = Geo_Provider::country_code( 'United States' );
		$this->assertEquals( 'US', $name, wp_json_encode( $name ) );
	}

	public function test_region_name() {
		$name = Geo_Provider::region_name( 'MA', 'US'  );
		$this->assertEquals( 'Massachusetts', $name, wp_json_encode( $name ) );
	}

	public function test_region_code() {
		$name = Geo_Provider::region_code( 'Massachusetts', 'US'  );
		$this->assertEquals( 'MA', $name, wp_json_encode( $name ) );
	}
}


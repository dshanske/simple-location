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

	/**
	 * A name containing a shorter region's name isn't given its code; names in other languages come before an
	 * inexact match; the inexact match takes whole words and the longest name (#872).
	 */
	public function test_region_code_inexact() {
		$this->assertSame( '22', Geo_Provider::region_code( 'Sofia City', 'BG' ), 'Sofia City, not Sofia Province.' );
		$this->assertSame( '23', Geo_Provider::region_code( 'Sofia', 'BG' ) );
		$this->assertSame( 'WV', Geo_Provider::region_code( 'West Virginia', 'US' ) );
		$this->assertSame( 'VA', Geo_Provider::region_code( 'Virginia', 'US' ) );
		$this->assertSame( 'BY', Geo_Provider::region_code( 'Freistaat Bayern', 'DE' ), 'An ISO name inside a longer one.' );
		$this->assertSame( 'BY', Geo_Provider::region_code( 'Bavaria', 'DE' ), 'An English name.' );
		$this->assertSame( 'IDF', Geo_Provider::region_code( 'Ile-de-France', 'FR' ), 'Without accents.' );
		$this->assertFalse( Geo_Provider::region_code( 'Nowhere', 'US' ) );
		$this->assertFalse( Geo_Provider::region_code( '', 'US' ) );
		$this->assertFalse( Geo_Provider::region_code( 'Texas', 'XX' ) );

		$this->assertFalse( Geo_Provider::region_data_name( 'Freistaat Bayern', 'DE', false ), 'Exact only.' );
		$this->assertSame( 'DE-BY', Geo_Provider::region_data_name( 'Freistaat Bayern', 'DE' )['code'] );
		$this->assertFalse( Geo_Provider::region_data_name( 'Ohioans', 'US' ), 'Not inside another word.' );
	}
}


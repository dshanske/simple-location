<?php

class LocationTaxonomyTest extends WP_UnitTestCase {
	public function test_set_and_get_location_data() {
		$addr = array(
				'country-name' => 'United States of America',
				'country-code' => 'US',
				'region' => 'Pennsylvania',
				'region-code' => 'PA',
				'locality' => 'Philadelphia'
			);
		$term = Location_Taxonomy::get_location( $addr, true );
		$return = Location_Taxonomy::location_data_to_hadr( Location_Taxonomy::get_location_data( $term ) );
		$this->assertEquals( $addr, $return, wp_json_encode( $return, JSON_PRETTY_PRINT ) );
		wp_delete_term( $term, 'location' );
	}

	public function test_set_and_get_location() {
		$addr = array(
				'country-name' => 'United States of America',
				'country-code' => 'US',
				'region' => 'Pennsylvania',
				'region-code' => 'PA',
				'locality' => 'Philadelphia'
			);
		$term = Location_Taxonomy::get_location( $addr, true );
		$termtoo = Location_Taxonomy::get_location( $addr, true );
		$this->assertEquals( $term, $termtoo );
		wp_delete_term( $term, 'location' );
	}

	public function test_set_and_get_locality() {
		$addr = array(
				'country-name' => 'United States of America',
				'country-code' => 'US',
				'region' => 'Pennsylvania',
				'region-code' => 'PA',
				'locality' => 'Philadelphia'
			);
		$term = Location_Taxonomy::get_location( $addr, true );
		$locality = Location_Taxonomy::get_locality( $addr );
		$this->assertEquals( $term, $locality );
		$ancestors = get_ancestors( $term, 'location', 'taxonomy' );
		$region = Location_Taxonomy::get_region( $addr );
		$this->assertEquals( $region, $ancestors[0] );
		$country = Location_Taxonomy::get_country( $addr );
		$this->assertEquals( $country, $ancestors[1] );
		wp_delete_term( $term, 'location' );
	}
}


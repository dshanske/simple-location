<?php

class LocationTest extends WP_UnitTestCase {
	public function test_set_and_get_location() {
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
}


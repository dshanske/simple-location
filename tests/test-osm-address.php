<?php
/**
 * Tests for the shared Nominatim-format address parser, Sloc_OSM_Address.
 *
 * The answers are shaped like ones sampled from Traccar Geocoder servers built from OpenStreetMap extracts.
 *
 * @package Simple_Location
 */

/**
 * Nominatim-format address parsing tests.
 */
class OsmAddressTest extends WP_UnitTestCase {

	/**
	 * Known settings for every test.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_country', '' );
		update_option( 'sloc_elevation_provider', 'sloc_test' );
	}

	/**
	 * Parses an answer at a location with the Traccar Geocoder provider.
	 *
	 * @param array $address   Address object.
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @param array $extra     Other top-level keys of the answer.
	 * @return array Address properties.
	 */
	protected function parse( $address, $latitude, $longitude, $extra = array() ) {
		$provider = new Geo_Provider_Traccar_Geocoder( array( 'servers' => array() ) );
		$provider->set( $latitude, $longitude );
		$call = function ( $json ) {
			return $this->address_to_mf( $json );
		};
		return Closure::bind( $call, $provider, Geo_Provider_Traccar_Geocoder::class )( array_merge( $extra, array( 'address' => $address ) ) );
	}

	/**
	 * Several values separated by semicolons keep the first.
	 */
	public function test_value() {
		$this->assertSame( '06105', Geo_Provider_Nominatim::osm_value( '06105;06106' ) );
		$this->assertSame( 'Main Street', Geo_Provider_Nominatim::osm_value( ' Main Street ' ) );
		$this->assertSame( '', Geo_Provider_Nominatim::osm_value( '' ) );
		$this->assertSame( '12', Geo_Provider_Nominatim::osm_value( 12 ) );
	}

	/**
	 * Ascending house numbers become a range; anything else keeps the first.
	 */
	public function test_house_number() {
		$this->assertSame( '46-48', Geo_Provider_Nominatim::osm_house_number( '46;48' ) );
		$this->assertSame( '394-400', Geo_Provider_Nominatim::osm_house_number( '394;396;398;400' ) );
		$this->assertSame( '130', Geo_Provider_Nominatim::osm_house_number( '130; 130 1/2' ) );
		$this->assertSame( '48', Geo_Provider_Nominatim::osm_house_number( '48;46' ) );
		$this->assertSame( '109-29', Geo_Provider_Nominatim::osm_house_number( '109-29' ), 'A Queens house number is kept.' );
		$this->assertSame( '10bis', Geo_Provider_Nominatim::osm_house_number( '10bis' ) );
		$this->assertSame( '7', Geo_Provider_Nominatim::osm_house_number( '7;' ) );
		$this->assertSame( '', Geo_Provider_Nominatim::osm_house_number( '' ) );
	}

	/**
	 * Postcodes keep the first value, and US ones get a lost leading zero back.
	 */
	public function test_postcode() {
		$this->assertSame( '02904-2118', Geo_Provider_Nominatim::osm_postcode( '2904-2118', 'US' ) );
		$this->assertSame( '02108', Geo_Provider_Nominatim::osm_postcode( '2108', 'US' ) );
		$this->assertSame( '06105', Geo_Provider_Nominatim::osm_postcode( '06105;06106', 'US' ) );
		$this->assertSame( '1010', Geo_Provider_Nominatim::osm_postcode( '1010', 'AT' ), 'Four-digit postcodes elsewhere are left alone.' );
		$this->assertSame( 'SW1A 2AA', Geo_Provider_Nominatim::osm_postcode( 'SW1A 2AA', 'GB' ) );
	}

	/**
	 * Numbered administrative districts aren't neighborhoods.
	 */
	public function test_is_district() {
		$this->assertTrue( Geo_Provider_Nominatim::osm_is_district( 'Manhattan Community Board 5' ) );
		$this->assertTrue( Geo_Provider_Nominatim::osm_is_district( 'Brooklyn Community District 17' ) );
		$this->assertTrue( Geo_Provider_Nominatim::osm_is_district( 'Ward 3' ) );
		$this->assertFalse( Geo_Provider_Nominatim::osm_is_district( 'Back Bay' ) );
		$this->assertFalse( Geo_Provider_Nominatim::osm_is_district( 'Ward Hill' ) );
		$this->assertFalse( Geo_Provider_Nominatim::osm_is_district( 'District of Columbia' ) );
	}

	/**
	 * "Village of" style prefixes come off US names, through the address format table's rules.
	 */
	public function test_strip_prefix() {
		$this->assertSame( 'Speculator', Sloc_Address_Format::clean( 'US', 'city', 'Village of Speculator' ) );
		$this->assertSame( 'New York', Sloc_Address_Format::clean( 'US', 'city', 'City of New York' ) );
		$this->assertSame( 'Townsend', Sloc_Address_Format::clean( 'US', 'city', 'Townsend' ) );
	}

	/**
	 * Region codes come from state_code, or an ISO 3166-2 key at the level of the region, in the right country.
	 */
	public function test_region_code() {
		$this->assertSame( 'PA', Geo_Provider_Nominatim::osm_region_code( array( 'ISO3166-2-lvl4' => 'US-PA' ), 'state', 'US' ) );
		$this->assertSame( 'NY', Geo_Provider_Nominatim::osm_region_code( array( 'state_code' => 'ny' ), 'state', 'US' ) );
		$this->assertSame( '75', Geo_Provider_Nominatim::osm_region_code( array( 'ISO3166-2-lvl4' => 'FR-IDF', 'ISO3166-2-lvl6' => 'FR-75' ), 'county', 'FR' ) );
		$this->assertSame( 'IDF', Geo_Provider_Nominatim::osm_region_code( array( 'ISO3166-2-lvl4' => 'FR-IDF', 'ISO3166-2-lvl6' => 'FR-75' ), 'state', 'FR' ) );
		$this->assertNull( Geo_Provider_Nominatim::osm_region_code( array( 'ISO3166-2-lvl4' => 'CA-QC' ), 'state', 'US' ), 'A code from another country is ignored.' );
		$this->assertNull( Geo_Provider_Nominatim::osm_region_code( array( 'ISO3166-2-lvl4' => 'US-PA' ), null, 'US' ) );
		$this->assertNull( Geo_Provider_Nominatim::osm_region_code( array( 'ISO3166-2-lvl4' => 'US-PA' ), 'state', '' ) );
	}

	/**
	 * A translated region name still gets its code from the ISO key.
	 */
	public function test_iso_region_code_parsed() {
		$addr = $this->parse(
			array(
				'city'           => 'Filadelfia',
				'state'          => 'Pensilvania',
				'ISO3166-2-lvl4' => 'US-PA',
				'country'        => 'Estados Unidos',
				'country_code'   => 'us',
			),
			39.9524,
			-75.1636
		);
		$this->assertSame( 'PA', $addr['region-code'] );
		$this->assertSame( 'Estados Unidos', $addr['country-name'] );
	}

	/**
	 * Multiple house numbers and postcodes are cleaned in a full address.
	 */
	public function test_multiple_values_parsed() {
		$addr = $this->parse(
			array(
				'house_number' => '46;48',
				'road'         => 'Beacon Street',
				'city'         => 'Somerville',
				'state'        => 'Massachusetts',
				'county'       => 'Middlesex County',
				'postcode'     => '02143;02144',
			),
			42.375421,
			-71.103624
		);
		$this->assertSame( '46-48 Beacon Street', $addr['street-address'] );
		$this->assertSame( '46-48', $addr['street-number'] );
		$this->assertSame( '02143', $addr['postal-code'] );
		$this->assertSame( 'US', $addr['country-code'] );
		$this->assertSame( 'MA', $addr['region-code'] );
	}

	/**
	 * In New York City's boroughs, which have no city in the data, the city comes from the place data.
	 */
	public function test_city_from_place() {
		$addr = $this->parse(
			array(
				'house_number' => '115-11',
				'road'         => '121st Street',
				'suburb'       => 'Queens Community Board 10',
				'state'        => 'New York',
				'county'       => 'Queens County',
				'postcode'     => '11420',
			),
			40.6731,
			-73.8174
		);
		$this->assertSame( 'Queens', $addr['locality'] );
		$this->assertSame( '115-11 121st Street', $addr['street-address'] );
		$this->assertArrayNotHasKey( 'extended-address', $addr, 'A community board isn\'t a neighborhood.' );
		$this->assertSame( '115-11 121st Street, Queens, NY, US', $addr['display-name'] );
	}

	/**
	 * A place in another region isn't used as the city, such as when an answer has the wrong state.
	 */
	public function test_city_not_from_other_region() {
		$addr = $this->parse(
			array(
				'road'   => 'Victory Boulevard',
				'state'  => 'New Jersey',
				'county' => 'Richmond County',
			),
			40.593603,
			-74.18769
		);
		$this->assertArrayNotHasKey( 'locality', $addr );
		$this->assertSame( 'New Jersey', $addr['region'] );
	}

	/**
	 * At the edge of an extract an answer can have only a road; the country, region and city come from the location.
	 */
	public function test_edge_of_extract() {
		$addr = $this->parse( array( 'road' => "Port de Cap d'Ail" ), 43.7242, 7.4167 );
		$this->assertSame( 'FR', $addr['country-code'] );
		$this->assertSame( "Cap-d'Ail", $addr['locality'] );
		$this->assertNotEmpty( $addr['region'] );
	}

	/**
	 * A country without regions, such as Monaco, gets none from the place data.
	 */
	public function test_no_region() {
		$addr = $this->parse(
			array(
				'road'         => 'Place du Casino',
				'suburb'       => 'Monte-Carlo',
				'city'         => 'Monaco',
				'country'      => 'Monaco',
				'country_code' => 'MC',
			),
			43.7396,
			7.4276
		);
		$this->assertArrayNotHasKey( 'region', $addr );
		$this->assertArrayNotHasKey( 'region-code', $addr );
		$this->assertSame( 'Monte-Carlo', $addr['extended-address'] );
		$this->assertSame( 'Place du Casino, Monaco, MC', $addr['display-name'] );
	}

	/**
	 * A suburb names part of the city, isn't repeated when it is the city, and stands in for a missing city.
	 */
	public function test_suburb() {
		$addr = $this->parse(
			array(
				'road'   => 'Eatonia Street',
				'suburb' => 'Allston',
				'city'   => 'Allston',
				'state'  => 'Massachusetts',
				'county' => 'Suffolk County',
			),
			42.355,
			-71.13
		);
		$this->assertArrayNotHasKey( 'extended-address', $addr );

		// Rural New York, with no place nearby in the place data.
		$addr = $this->parse(
			array(
				'road'   => 'Barrett Road',
				'suburb' => 'Hopkinton',
				'state'  => 'New York',
				'county' => 'Saint Lawrence County',
			),
			44.738728,
			-74.762763
		);
		$this->assertSame( 'Hopkinton', $addr['locality'] );
	}

	/**
	 * "Village of" comes off US place names only.
	 */
	public function test_prefix_us_only() {
		$addr = $this->parse(
			array(
				'city'   => 'Village of Speculator',
				'state'  => 'New York',
				'county' => 'Hamilton County',
			),
			43.5,
			-74.37
		);
		$this->assertSame( 'Speculator', $addr['locality'] );

		$addr = $this->parse(
			array(
				'city'         => 'City of London',
				'country'      => 'United Kingdom',
				'country_code' => 'gb',
			),
			51.5155,
			-0.0922
		);
		$this->assertSame( 'City of London', $addr['locality'] );
	}

	/**
	 * A small island outside the bundled borders still gets its country, and the state as its region.
	 */
	public function test_island_country() {
		$addr = $this->parse(
			array(
				'city'   => "Swan's Island",
				'state'  => 'Maine',
				'county' => 'Hancock County',
			),
			44.055454,
			-68.423863
		);
		$this->assertSame( 'US', $addr['country-code'] );
		$this->assertSame( 'Maine', $addr['region'] );
		$this->assertSame( 'ME', $addr['region-code'] );
	}

	/**
	 * The state is the region by default; the county in the United Kingdom and Italy; and the filter can change it.
	 */
	public function test_region_order() {
		$addr = $this->parse(
			array(
				'road'         => 'Braubachstraße',
				'city'         => 'Frankfurt am Main',
				'county'       => 'Frankfurt am Main',
				'state'        => 'Hessen',
				'country'      => 'Deutschland',
				'country_code' => 'de',
			),
			50.1109,
			8.6821
		);
		$this->assertSame( 'Hessen', $addr['region'] );
		$this->assertSame( 'HE', $addr['region-code'] );

		// Sofia: a city district comes back as the county.
		$addr = $this->parse(
			array(
				'city'         => 'София',
				'county'       => 'Район Оборище',
				'state'        => 'София-град',
				'country'      => 'България',
				'country_code' => 'bg',
			),
			42.6977,
			23.3219
		);
		$this->assertSame( 'София-град', $addr['region'] );

		$this->assertSame( array( 'county', 'state', 'region', 'state_district' ), Geo_Provider_Nominatim::osm_region_keys( 'GB' ) );
		$this->assertSame( array( 'county', 'state', 'region', 'state_district' ), Geo_Provider_Nominatim::osm_region_keys( 'IT' ) );
		$this->assertSame( array( 'state', 'region', 'state_district', 'county' ), Geo_Provider_Nominatim::osm_region_keys( '' ) );

		$filter = function ( $keys, $country ) {
			return 'DE' === $country ? array( 'county' ) : $keys;
		};
		add_filter( 'sloc_osm_region_keys', $filter, 10, 2 );
		$this->assertSame( array( 'county' ), Geo_Provider_Nominatim::osm_region_keys( 'DE' ) );
		add_filter( 'sloc_osm_region_keys', '__return_null', 20 );
		$this->assertSame( array(), Geo_Provider_Nominatim::osm_region_keys( 'DE' ), 'A broken filter gives no keys rather than an error.' );
		remove_filter( 'sloc_osm_region_keys', '__return_null', 20 );
		remove_filter( 'sloc_osm_region_keys', $filter, 10 );
	}

	/**
	 * A bilingual region name keeps the part with a known code.
	 */
	public function test_bilingual_region() {
		$this->assertSame( array( 'Northern Ireland', 'NIR' ), Geo_Provider_Nominatim::osm_region_name_code( 'Northern Ireland / Tuaisceart Éireann', 'GB' ) );
		$this->assertSame( array( 'Pennsylvania', 'PA' ), Geo_Provider_Nominatim::osm_region_name_code( 'Pennsylvania', 'US' ) );
		$this->assertSame( array( 'Nowhere | Nulle part', null ), Geo_Provider_Nominatim::osm_region_name_code( 'Nowhere | Nulle part', 'US' ) );

		// Belfast, from an extract without the United Kingdom's boundary.
		$addr = $this->parse(
			array(
				'house_number' => '9',
				'road'         => 'Donegall Square North',
				'suburb'       => 'Town Parks',
				'city'         => 'Belfast',
				'state'        => 'Northern Ireland / Tuaisceart Éireann',
				'postcode'     => 'BT1 5GJ',
			),
			54.5973,
			-5.9301
		);
		$this->assertSame( 'GB', $addr['country-code'] );
		$this->assertSame( 'Northern Ireland', $addr['region'] );
		$this->assertSame( 'NIR', $addr['region-code'] );
	}

	/**
	 * A council district given as the city is replaced by a town from the place data, or used, cleaned by the address
	 * format table's rules, when there is none.
	 */
	public function test_district_as_city() {
		$this->assertTrue( Geo_Provider_Nominatim::osm_is_district( 'Kenmare Municipal District' ) );
		$this->assertTrue( Geo_Provider_Nominatim::osm_is_district( 'The Metropolitan District of Limerick City' ) );
		$this->assertSame( 'Limerick', Sloc_Address_Format::clean( 'IE', 'city', 'The Metropolitan District of Limerick City' ) );
		$this->assertSame( 'Cobh', Sloc_Address_Format::clean( 'IE', 'city', 'Municipal District of Cobh' ), 'Not "of Cobh".' );
		$this->assertSame( 'Cahir — Cashel', Sloc_Address_Format::clean( 'IE', 'city', 'The Municipal District of Cahir — Cashel' ) );
		$this->assertTrue( Geo_Provider_Nominatim::osm_is_district( 'Inns Quay A Ward 1986' ) );
		$this->assertTrue( Geo_Provider_Nominatim::osm_is_district( 'מועצה אזורית תמר' ) );
		$this->assertTrue( Geo_Provider_Nominatim::osm_is_district( 'Tamar Regional Council' ) );
		$this->assertFalse( Geo_Provider_Nominatim::osm_is_district( 'Kenmare' ) );

		$addr = $this->parse(
			array(
				'city'         => 'Kenmare Municipal District',
				'county'       => 'County Kerry',
				'country'      => 'Éire / Ireland',
				'country_code' => 'ie',
			),
			51.8801,
			-9.5835
		);
		$this->assertSame( 'Kenmare', $addr['locality'] );
		$this->assertSame( 'County Kerry', $addr['region'] );
		$this->assertSame( 'KY', $addr['region-code'] );

		// Without a town nearby, the district would be used, cleaned like this.
		$this->assertSame( 'Kenmare', Sloc_Address_Format::clean( 'IE', 'city', 'Kenmare Municipal District' ) );

		// In the Negev, with no town nearby, the regional council is better than nothing.
		$addr = $this->parse(
			array(
				'city'         => 'מועצה אזורית תמר',
				'state'        => 'מחוז הדרום',
				'country'      => 'ישראל',
				'country_code' => 'il',
			),
			31.069013,
			35.286249
		);
		$this->assertSame( 'מועצה אזורית תמר', $addr['locality'] );
	}

	/**
	 * Without an address there is nothing to parse.
	 */
	public function test_no_address() {
		$provider = new Geo_Provider_Nominatim();
		$call     = function ( $json ) {
			return $this->osm_address_to_mf( $json );
		};
		$call = Closure::bind( $call, $provider, Geo_Provider_Nominatim::class );
		$this->assertSame( array(), $call( array() ) );
		$this->assertSame( array(), $call( array( 'address' => 'x' ) ) );
		$this->assertSame( array(), $call( 'x' ) );
	}

	/**
	 * Non-scalar values in the address are ignored.
	 */
	public function test_non_scalar_values() {
		$addr = $this->parse(
			array(
				'road'   => array( 'x' ),
				'city'   => 'Philadelphia',
				'state'  => 'Pennsylvania',
				'county' => null,
			),
			39.9524,
			-75.1636
		);
		$this->assertSame( 'Philadelphia', $addr['locality'] );
		$this->assertArrayNotHasKey( 'street', $addr );
	}
}

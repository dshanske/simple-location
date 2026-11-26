<?php
/**
 * Tests for the site's country choices and names in its language (#867, #868).
 *
 * @package Simple_Location
 */

/**
 * Address finisher tests.
 */
class AddressFinisherTest extends WP_UnitTestCase {

	/**
	 * Known settings for every test.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_country', '' );
		update_option( 'sloc_elevation_provider', 'sloc_test' );
		Sloc_Address_Format::reset();
	}

	/**
	 * Removes the filters the tests add.
	 */
	public function tear_down() {
		remove_all_filters( 'sloc_country_codes' );
		remove_all_filters( 'sloc_country_for_location' );
		remove_all_filters( 'sloc_address_language' );
		Sloc_Address_Format::reset();
		parent::tear_down();
	}

	/**
	 * Uses one country code instead of another.
	 *
	 * @param array $codes Map.
	 */
	protected function map( $codes ) {
		add_filter( 'sloc_country_codes', fn( $map ) => array_merge( $map, $codes ) );
	}

	/**
	 * The map comes from the constant, can be changed by the filter, and is cleaned.
	 */
	public function test_country_codes() {
		if ( ! defined( 'SLOC_COUNTRY_CODES' ) ) {
			// Codes no location has, so the constant doesn't change other tests.
			define( 'SLOC_COUNTRY_CODES', array( 'XA' => 'XB' ) );
		}
		$this->assertSame( array( 'XA' => 'XB' ), Sloc_Address_Finisher::country_codes() );
		$this->assertSame( 'XB', Sloc_Address_Finisher::country( 'xa' ) );

		add_filter(
			'sloc_country_codes',
			fn( $codes ) => array_merge(
				$codes,
				array(
					'ps'  => 'il',
					'BAD' => 'IL',
					'GB'  => 'United Kingdom',
					5     => 'US',
				)
			)
		);
		$this->assertSame(
			array(
				'XA' => 'XB',
				'PS' => 'IL',
			),
			Sloc_Address_Finisher::country_codes()
		);
		add_filter( 'sloc_country_codes', '__return_null', 20 );
		$this->assertSame( array(), Sloc_Address_Finisher::country_codes() );
	}

	/**
	 * The country for a location: the map, then the location filter; anything but a code becomes empty.
	 */
	public function test_country() {
		$this->map( array( 'PS' => 'IL' ) );
		$this->assertSame( 'IL', Sloc_Address_Finisher::country( 'ps', 31.9, 35.2 ) );
		$this->assertSame( 'DE', Sloc_Address_Finisher::country( 'de' ) );
		$this->assertSame( '', Sloc_Address_Finisher::country( '' ) );

		$filter = function ( $code, $latitude, $longitude ) {
			$this->assertSame( 31.77, $latitude );
			return ( 31.77 === $latitude && 35.23 === $longitude ) ? 'ps' : $code;
		};
		add_filter( 'sloc_country_for_location', $filter, 10, 3 );
		$this->assertSame( 'PS', Sloc_Address_Finisher::country( 'IL', 31.77, 35.23 ) );
		remove_filter( 'sloc_country_for_location', $filter, 10 );

		add_filter( 'sloc_country_for_location', fn() => array( 'IL' ) );
		$this->assertSame( '', Sloc_Address_Finisher::country( 'IL' ) );
	}

	/**
	 * The bundled lookup uses the site's choice too.
	 */
	public function test_country_for_location() {
		$this->assertSame( 'PS', Sloc_Provider::country_for_location( 31.9038, 35.2034 ), 'Ramallah, by the bundled borders.' );
		$this->map( array( 'PS' => 'IL' ) );
		$this->assertSame( 'IL', Sloc_Provider::country_for_location( 31.9038, 35.2034 ) );
	}

	/**
	 * Names in the site's language, with the provider's kept, and the display name rebuilt.
	 */
	public function test_site_language_names() {
		$addr = Sloc_Address_Finisher::finish(
			array(
				'street-address' => 'הצדף 12',
				'locality'       => 'אילת',
				'region'         => 'מחוז הדרום',
				'region-code'    => 'D',
				'country-name'   => 'ישראל',
				'country-code'   => 'IL',
				'display-name'   => 'הצדף 12, אילת, מחוז הדרום, IL',
			)
		);
		$this->assertSame( 'Israel', $addr['country-name'] );
		$this->assertSame( 'Southern District', $addr['region'] );
		$this->assertSame(
			array(
				'country-name' => 'ישראל',
				'region'       => 'מחוז הדרום',
			),
			$addr['local']
		);
		$this->assertSame( 'אילת', $addr['locality'], 'Towns stay as the provider gave them.' );
		$this->assertSame( 'הצדף 12, אילת, Southern District, IL', $addr['display-name'] );

		// Another language.
		add_filter( 'sloc_address_language', fn() => 'fr' );
		$addr = Sloc_Address_Finisher::finish(
			array(
				'country-name' => 'ישראל',
				'country-code' => 'IL',
			)
		);
		$this->assertSame( 'Israël', $addr['country-name'] );
		$this->assertSame( 'ישראל', $addr['local']['country-name'] );
		$this->assertArrayNotHasKey( 'display-name', $addr, 'No display name is added.' );
	}

	/**
	 * Region names start with a capital, though Wikidata labels some in lowercase (#875).
	 */
	public function test_region_name_capital() {
		$this->assertSame( 'Province of Perugia', Sloc_Address_Format::region_name( 'IT', 'PG', 'en' ) );
		$this->assertSame( 'Marrakesh-Safi', Sloc_Address_Format::region_name( 'MA', '07', 'en' ), 'The current region, not the one its code had before.' );
	}

	/**
	 * A name already in the site's language changes nothing, and names kept earlier aren't replaced.
	 */
	public function test_nothing_to_change() {
		$addr = array(
			'locality'     => 'Albany',
			'region'       => 'New York',
			'region-code'  => 'NY',
			'country-name' => 'United States',
			'country-code' => 'US',
			'display-name' => 'Custom name',
		);
		$this->assertSame( $addr, Sloc_Address_Finisher::finish( $addr ) );

		$addr = Sloc_Address_Finisher::finish(
			array(
				'country-name' => 'Deutschland',
				'country-code' => 'DE',
				'local'        => array( 'country-name' => 'Germany (as first given)' ),
			)
		);
		$this->assertSame( 'Germany', $addr['country-name'] );
		$this->assertSame( 'Germany (as first given)', $addr['local']['country-name'] );

		$this->assertSame( 'x', Sloc_Address_Finisher::finish( 'x' ) );
		$this->assertSame( array( 'locality' => 'Nowhere' ), Sloc_Address_Finisher::finish( array( 'locality' => 'Nowhere' ) ) );
	}

	/**
	 * A changed country brings its name, drops a region code it doesn't have, and rebuilds the display name.
	 */
	public function test_changed_country() {
		$this->map( array( 'PS' => 'IL' ) );
		$addr = Sloc_Address_Finisher::finish(
			array(
				'locality'     => 'Ramallah',
				'region'       => 'Ramallah and al-Bireh',
				'region-code'  => 'RBH',
				'country-name' => 'Palestine, State of',
				'country-code' => 'PS',
				'latitude'     => 31.9038,
				'longitude'    => 35.2034,
				'display-name' => 'Ramallah, Ramallah and al-Bireh, PS',
			)
		);
		$this->assertSame( 'IL', $addr['country-code'] );
		$this->assertSame( 'Israel', $addr['country-name'] );
		$this->assertArrayNotHasKey( 'region-code', $addr );
		$this->assertSame( 'Ramallah, Ramallah and al-Bireh, IL', $addr['display-name'] );
	}

	/**
	 * Lookups through the geocoding chain are finished.
	 */
	public function test_chain() {
		update_option( 'sloc_geo_provider', 'offline' );
		update_option( 'sloc_geo_provider_fallbacks', array() );
		$this->map( array( 'PS' => 'IL' ) );
		$addr = Sloc_Provider_Chain::reverse_lookup( 31.9038, 35.2034 );
		$this->assertIsArray( $addr );
		$this->assertSame( 'IL', $addr['country-code'] );
		$this->assertStringEndsWith( 'IL', $addr['display-name'] );
	}

	/**
	 * New location terms are named in the site's language and keep the provider's names; region names in other
	 * languages get their codes.
	 */
	public function test_terms() {
		$addr    = Sloc_Address_Finisher::finish(
			array(
				'locality'     => 'Бургас',
				'region'       => 'Бургас',
				'region-code'  => '02',
				'country-name' => 'България',
				'country-code' => 'BG',
			)
		);
		$term    = Location_Taxonomy::get_location( $addr, true );
		$region  = get_term( get_term( $term )->parent );
		$country = get_term( $region->parent );
		$this->assertSame( 'Burgas Province', $region->name );
		$this->assertSame( 'Бургас', get_term_meta( $region->term_id, 'local_name', true ) );
		$this->assertSame( 'Bulgaria', $country->name );
		$this->assertSame( 'България', get_term_meta( $country->term_id, 'local_name', true ) );

		$normal = Location_Taxonomy::normalize_address(
			array(
				'region'       => 'Bavaria',
				'country-code' => 'DE',
			)
		);
		$this->assertSame( 'BY', $normal['region-code'] );
	}
}

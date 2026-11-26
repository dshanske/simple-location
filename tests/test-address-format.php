<?php
/**
 * Tests for the address format table, built from OpenCage address-formatting (#865).
 *
 * @package Simple_Location
 */

require_once dirname( __DIR__ ) . '/includes/class-sloc-address-format-builder.php';

/**
 * Address format tests.
 */
class AddressFormatTest extends WP_UnitTestCase {

	/**
	 * Reads the bundled table again after each test.
	 */
	public function tear_down() {
		remove_all_filters( 'sloc_address_format_file' );
		Sloc_Address_Format::reset();
		parent::tear_down();
	}

	/**
	 * The bundled table records where it came from and covers the countries.
	 */
	public function test_bundled_table() {
		$json = json_decode( file_get_contents( Sloc_Address_Format::file() ), true );
		$this->assertSame( 'MIT', $json['source']['license'] );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{40}$/', $json['source']['commit'] );
		$this->assertGreaterThan( 200, count( $json['countries'] ) );
	}

	/**
	 * House number order, for countries the old list of 18 had and ones it missed.
	 */
	public function test_number_first() {
		foreach ( array( 'US', 'GB', 'FR', 'IE', 'CA', 'AU', 'JP' ) as $code ) {
			$this->assertTrue( Sloc_Address_Format::number_first( $code ), $code );
			$this->assertTrue( Geo_Provider_Nominatim::house_number( $code ), $code );
		}
		foreach ( array( 'DE', 'IT', 'ES', 'NL', 'SE', 'AT', 'PT', 'MX', 'RU', 'IL', 'GR', 'TR' ) as $code ) {
			$this->assertFalse( Sloc_Address_Format::number_first( $code ), $code );
			$this->assertFalse( Geo_Provider_Nominatim::house_number( $code ), $code );
		}
		$this->assertTrue( Sloc_Address_Format::number_first( '' ), 'Unknown countries put the number first.' );
		$this->assertTrue( Sloc_Address_Format::number_first( 'ZZ' ) );
		$this->assertTrue( Sloc_Address_Format::number_first( 'us' ) );
	}

	/**
	 * The division each country's addresses name.
	 */
	public function test_region_key() {
		$this->assertSame( 'state', Sloc_Address_Format::region_key( 'US' ) );
		$this->assertSame( 'state', Sloc_Address_Format::region_key( 'DE' ) );
		$this->assertSame( 'county', Sloc_Address_Format::region_key( 'GB' ) );
		$this->assertSame( 'county', Sloc_Address_Format::region_key( 'IT' ) );
		$this->assertSame( 'county', Sloc_Address_Format::region_key( 'IE' ) );
		$this->assertNull( Sloc_Address_Format::region_key( 'MC' ) );
		$this->assertSame( array( 'county', 'state', 'region', 'state_district' ), Geo_Provider_Nominatim::osm_region_keys( 'GB' ) );
		$this->assertSame( array( 'state', 'region', 'state_district', 'county' ), Geo_Provider_Nominatim::osm_region_keys( 'BG' ) );
	}

	/**
	 * Country rules clean names, only for their component when they name one.
	 */
	public function test_clean() {
		$this->assertSame( 'Camden', Sloc_Address_Format::clean( 'GB', 'city', 'London Borough of Camden' ) );
		$this->assertSame( 'London', Sloc_Address_Format::clean( 'GB', 'county', 'Greater London' ) );
		$this->assertSame( 'Roma', Sloc_Address_Format::clean( 'IT', 'county', 'Città Metropolitana di Roma' ) );
		$this->assertSame( 'Città Metropolitana di Roma', Sloc_Address_Format::clean( 'IT', 'city', 'Città Metropolitana di Roma' ), 'A county rule leaves cities alone.' );
		$this->assertSame( 'Main Street', Sloc_Address_Format::clean( 'ZZ', 'road', 'Main Street' ) );
		$this->assertSame( 'Village of', Sloc_Address_Format::clean( 'US', 'city', 'Village of' ), 'A name cleaning would empty is kept.' );
	}

	/**
	 * Region and county codes by name, in other languages too, and only ISO 3166-2 codes.
	 */
	public function test_code() {
		$this->assertSame( 'BY', Sloc_Address_Format::code( 'DE', 'Bavaria' ) );
		$this->assertSame( 'BY', Sloc_Address_Format::code( 'de', ' bayern ' ) );
		$this->assertSame( 'KY', Sloc_Address_Format::code( 'IE', 'County Kerry', 'county' ) );
		$this->assertNull( Sloc_Address_Format::code( 'IE', 'County Kerry' ), 'Counties aren\'t states.' );
		$this->assertNull( Sloc_Address_Format::code( 'DE', 'Atlantis' ) );
		$this->assertSame( array( 'Bavaria', 'BY' ), Geo_Provider_Nominatim::osm_region_name_code( 'Bavaria', 'DE' ) );
	}

	/**
	 * Without the table, the defaults apply and nothing fails.
	 */
	public function test_missing_table() {
		add_filter( 'sloc_address_format_file', fn() => __DIR__ . '/data/no-such-file.json' );
		Sloc_Address_Format::reset();
		$this->assertTrue( Sloc_Address_Format::number_first( 'DE' ) );
		$this->assertNull( Sloc_Address_Format::region_key( 'GB' ) );
		$this->assertSame( 'London Borough of Camden', Sloc_Address_Format::clean( 'GB', 'city', 'London Borough of Camden' ) );
		$this->assertNull( Sloc_Address_Format::code( 'DE', 'Bavaria' ) );
	}

	/**
	 * A broken rule in the table leaves the name as it is.
	 */
	public function test_bad_rule() {
		$file = get_temp_dir() . 'sloc-address-formats-test.json';
		file_put_contents( $file, wp_json_encode( array( 'countries' => array( 'XX' => array( 'rules' => array( array( '', '/(unclosed/u', '' ), array( '', 'not a rule' ), 'x' ) ) ) ) ) );
		add_filter( 'sloc_address_format_file', fn() => $file );
		Sloc_Address_Format::reset();
		$this->assertSame( 'Somewhere', Sloc_Address_Format::clean( 'XX', 'city', 'Somewhere' ) );
		unlink( $file );
	}

	/**
	 * The builder derives each country's entry from templates, rules and codes.
	 */
	public function test_builder() {
		$worldwide = array(
			'generic1' => "{{{house_number}}} {{{road}}}\n{{{city}}}, {{#first}} {{{state_code}}} || {{{state}}} {{/first}}\n",
			'AA'       => array(
				'address_template' => "{{{house_number}}} {{{road}}}\n{{#first}} {{{city}}} || {{{town}}} || {{{state}}} {{/first}}\n{{{country}}}\n",
				'fallback_template' => "{{{road}}}\n{{{county}}}\n{{{state}}}\n",
				'replace'          => array(
					array( 'county=^County of ', '' ),
					array( '^The ', '' ),
					array( '(broken', '' ),
				),
				'postformat_replace' => array(
					array( 'Town of ', '' ),
					array( '\nAA$', "\nCountry" ),
				),
			),
			'BB'       => array(
				'address_template' => "{{{road}}} {{{house_number}}}\n{{{postcode}}} {{{city}}}\n{{#first}} {{{country}}} || {{{state}}} {{/first}}\n",
			),
			'CC'       => array( 'use_country' => 'AA', 'replace' => array( array( '^Old ', '' ) ) ),
			'DD'       => array( 'address_template' => "{{{city}}}\n" ),
		);
		$states    = array(
			'AA' => array(
				'S1' => 'State One',
				'S2' => array(
					'default' => 'Zwei',
					'alt_en'  => 'Two',
				),
				'XX' => 'Not ISO',
			),
		);
		$counties  = array( 'AA' => array( 'C1' => 'County One' ) );
		$table     = Sloc_Address_Format_Builder::build( $worldwide, $states, $counties, array( 'commit' => 'abc' ), array( 'AA' => array( 'S1', 'S2', 'C1' ) ) );

		$this->assertSame( array( 'commit' => 'abc' ), $table['source'] );
		$this->assertArrayNotHasKey( 'generic1', $table['countries'] );
		$this->assertArrayNotHasKey( 'DD', $table['countries'], 'Nothing to say, so left out.' );
		$aa = $table['countries']['AA'];
		$this->assertTrue( $aa['number_first'] );
		$this->assertSame( 'county', $aa['region'], 'From the fallback template, since the main one names the state only as a city fallback.' );
		$this->assertSame(
			array(
				array( 'county', '/^County of /u', '' ),
				array( '', '/^The /u', '' ),
				array( '', '/Town of /u', '' ),
			),
			$aa['rules']
		);
		$this->assertSame(
			array(
				'state one' => 'S1',
				'two'       => 'S2',
				'zwei'      => 'S2',
			),
			$aa['states']
		);
		$this->assertSame( array( 'county one' => 'C1' ), $aa['counties'] );
		$this->assertFalse( $table['countries']['BB']['number_first'] );
		$this->assertArrayNotHasKey( 'region', $table['countries']['BB'], 'A state standing in for the country isn\'t a region.' );
		$this->assertSame( '/^Old /u', $table['countries']['CC']['rules'][0][1], 'A country sharing a format keeps its own rules first.' );
		$this->assertTrue( $table['countries']['CC']['number_first'] );

		$this->assertNull( Sloc_Address_Format_Builder::number_first( "{{{city}}}\n" ) );
		$this->assertSame( 'state', Sloc_Address_Format_Builder::region_component( "{{{city}}}, {{#first}} {{{state_code}}} || {{{state}}} {{/first}}" ) );
		$this->assertSame( 'state', Sloc_Address_Format_Builder::region_component( '{{{province}}}' ) );
		$this->assertSame( array(), Sloc_Address_Format_Builder::codes( 'x' ) );
		$this->assertSame( array(), Sloc_Address_Format_Builder::resolve( array( 'AA' => 'x' ), 'AA' ) );
		$this->assertSame( array( 'use_country' => 'AA' ), Sloc_Address_Format_Builder::resolve( array( 'AA' => array( 'use_country' => 'AA' ) ), 'AA' ), 'A loop stops.' );
	}
}

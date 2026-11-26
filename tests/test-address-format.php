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
		remove_all_filters( 'sloc_region_names_file' );
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
		$this->assertNull( Sloc_Address_Format::code( 'DE', 'Atlantis' ) );
		$this->assertNull( Sloc_Address_Format::code( 'DE', ' ' ) );
		$this->assertSame( array( 'Bavaria', 'BY' ), Geo_Provider_Nominatim::osm_region_name_code( 'Bavaria', 'DE' ) );
	}

	/**
	 * Region names from Wikidata, in the country's own languages, and English names by code.
	 */
	public function test_wikidata_names() {
		$this->assertSame( '02', Sloc_Address_Format::code( 'BG', 'Бургас' ) );
		$this->assertSame( '23', Sloc_Address_Format::code( 'BG', 'Софийска' ) );
		$this->assertSame( 'D', Sloc_Address_Format::code( 'IL', 'מחוז הדרום' ) );
		$this->assertSame( 'TN', Sloc_Address_Format::code( 'IN', 'தமிழ்நாடு' ) );
		$this->assertNull( Sloc_Address_Format::code( 'PS', 'قطاع غزة' ), 'The Gaza Strip has no ISO 3166-2 code; the governorates do.' );
		$this->assertSame( 'Southern District', Sloc_Address_Format::region_name( 'IL', 'D', 'en' ) );
		$this->assertSame( 'Burgas Province', Sloc_Address_Format::region_name( 'bg', '02', 'en' ) );
		$this->assertNull( Sloc_Address_Format::region_name( 'BG', 'ZZ', 'en' ) );

		$json = json_decode( file_get_contents( dirname( __DIR__ ) . '/data/region-names.json' ), true );
		$this->assertSame( 'CC0-1.0', $json['source']['license'] );
	}

	/**
	 * Region codes show in display names only where addresses use them.
	 */
	public function test_display_region() {
		$this->assertTrue( Sloc_Address_Format::shows_region_code( 'US' ) );
		$this->assertTrue( Sloc_Address_Format::shows_region_code( 'IT' ) );
		$this->assertFalse( Sloc_Address_Format::shows_region_code( 'DE' ) );
		$this->assertFalse( Sloc_Address_Format::shows_region_code( 'BG' ) );

		update_option( 'sloc_country', '' );
		$provider = new Geo_Provider_Nominatim();
		$address  = array(
			'locality'     => 'Tann (Rhön)',
			'region'       => 'Hessen',
			'region-code'  => 'HE',
			'country-code' => 'DE',
		);
		$this->assertSame( 'Tann (Rhön), Hessen, DE', $provider->display_name( $address ) );
		$address = array(
			'locality'     => 'Albany',
			'region'       => 'New York',
			'region-code'  => 'NY',
			'country-code' => 'US',
		);
		$this->assertSame( 'Albany, NY, US', $provider->display_name( $address ) );
		$this->assertSame(
			'Somewhere, XY, DE',
			$provider->display_name(
				array(
					'locality'     => 'Somewhere',
					'region-code'  => 'XY',
					'country-code' => 'DE',
				)
			),
			'A code without a name is still shown.'
		);
	}

	/**
	 * Without the table, the defaults apply and nothing fails.
	 */
	public function test_missing_table() {
		add_filter( 'sloc_address_format_file', fn() => __DIR__ . '/data/no-such-file.json' );
		add_filter( 'sloc_region_names_file', fn() => __DIR__ . '/data/no-such-file.json' );
		Sloc_Address_Format::reset();
		$this->assertTrue( Sloc_Address_Format::number_first( 'DE' ) );
		$this->assertNull( Sloc_Address_Format::region_key( 'GB' ) );
		$this->assertSame( 'London Borough of Camden', Sloc_Address_Format::clean( 'GB', 'city', 'London Borough of Camden' ) );
		$this->assertNull( Sloc_Address_Format::code( 'DE', 'Bavaria' ) );
		$this->assertSame( 'Southern District', Sloc_Address_Format::region_name( 'IL', 'D', 'en' ), 'Names by language are separate files.' );
		$this->assertTrue( Sloc_Address_Format::shows_region_code( 'DE' ), 'As before the table existed.' );
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
				'address_template'   => "{{{house_number}}} {{{road}}}\n{{#first}} {{{city}}} || {{{town}}} || {{{state}}} {{/first}}\n{{{country}}}\n",
				'fallback_template'  => "{{{road}}}\n{{{county}}}\n{{{state}}}\n",
				'replace'            => array(
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
			'CC'       => array(
				'use_country' => 'AA',
				'replace'     => array( array( '^Old ', '' ) ),
			),
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
		$this->assertArrayNotHasKey( 'region_code', $aa );
		$this->assertTrue( $table['countries']['CC']['number_first'] );

		$this->assertNull( Sloc_Address_Format_Builder::number_first( "{{{city}}}\n" ) );
		$this->assertSame( 'state', Sloc_Address_Format_Builder::region_component( '{{{city}}}, {{{state_code}}}', true ) );
		$this->assertNull( Sloc_Address_Format_Builder::region_component( '{{{city}}}, {{{state}}}', true ) );
		$this->assertSame( 'state', Sloc_Address_Format_Builder::region_component( '{{{city}}}, {{#first}} {{{state_code}}} || {{{state}}} {{/first}}' ) );
		$this->assertSame( 'state', Sloc_Address_Format_Builder::region_component( '{{{province}}}' ) );
		$this->assertSame( array(), Sloc_Address_Format_Builder::codes( 'x' ) );
		$this->assertSame( array(), Sloc_Address_Format_Builder::resolve( array( 'AA' => 'x' ), 'AA' ) );
		$this->assertSame( array( 'use_country' => 'AA' ), Sloc_Address_Format_Builder::resolve( array( 'AA' => array( 'use_country' => 'AA' ) ), 'AA' ), 'A loop stops.' );
	}

	/**
	 * Languages per country, corrected and with English, and region names from Wikidata entities.
	 */
	public function test_builder_wikidata() {
		$languages = Sloc_Address_Format_Builder::languages(
			array(
				'JP'      => 'jp',
				'BE'      => 'fr,nl, de',
				'SJ'      => '"no"',
				'MA'      => 'ar,fr',
				'comment' => 'x',
				'XX'      => array( 'bad' ),
			)
		);
		$this->assertSame( array( 'en', 'ja' ), $languages['JP'] );
		$this->assertSame( array( 'de', 'en', 'fr', 'nl' ), $languages['BE'] );
		$this->assertSame( array( 'en', 'no' ), $languages['SJ'] );
		$this->assertSame( array( 'ar', 'en', 'fr', 'zgh' ), $languages['MA'] );
		$this->assertSame( array( 'en' ), $languages['XX'] );
		$this->assertArrayNotHasKey( 'comment', $languages );

		$items    = array(
			'BG-02' => 'Q1',
			'BG-03' => 'Q2',
			'BG-99' => 'Q3',
			'bad'   => 'Q4',
			'BG-04' => 'Q5',
		);
		$entities = array(
			'Q1' => array(
				'labels'  => array(
					'bg' => array( 'value' => 'Бургас' ),
					'en' => array( 'value' => 'Burgas Province' ),
					'fr' => array( 'value' => 'Province de Bourgas' ),
				),
				'aliases' => array( 'bg' => array( array( 'value' => 'Бургаска област' ), array( 'value' => 'Общо' ) ) ),
			),
			'Q2' => array(
				'labels'  => array( 'bg' => array( 'value' => 'Варна' ) ),
				'aliases' => array( 'bg' => array( array( 'value' => 'общо' ), array( 'x' => 1 ) ) ),
			),
			'Q3' => array( 'labels' => array( 'bg' => array( 'value' => 'Not ISO' ) ) ),
		);
		$names    = Sloc_Address_Format_Builder::region_names( $items, $entities, array( 'BG' => array( 'bg', 'en' ) ), array( 'BG' => array( '02', '03', '04' ) ) );
		$this->assertSame(
			array(
				'burgas province' => '02',
				'бургас'          => '02',
				'бургаска област' => '02',
				'варна'           => '03',
			),
			$names['names']['BG'],
			'Names in other languages are left out, and a name for two codes is dropped.'
		);
		$this->assertSame(
			array( 'names' => array() ),
			Sloc_Address_Format_Builder::region_names( array( 'BG-02' => 'Q1' ), array( 'Q1' => array( 'aliases' => array( 'bg' => array( array( 'value' => 'а' ) ) ) ) ), array(), array( 'BG' => array() ) )
		);

		$localized = Sloc_Address_Format_Builder::localized_names(
			array(
				'BG'  => 'Q9',
				'bad' => 'Q8',
			),
			$items,
			array_merge(
				$entities,
				array(
					'Q9' => array(
						'labels' => array(
							'en' => array( 'value' => 'Bulgaria' ),
							'fr' => array( 'value' => 'Bulgarie' ),
						),
					),
				)
			),
			array( 'en', 'fr', 'de' ),
			array( 'BG' => array( '02', '03' ) )
		);
		$this->assertSame(
			array(
				'countries' => array( 'BG' => 'Bulgaria' ),
				'regions'   => array( 'BG-02' => 'Burgas Province' ),
			),
			$localized['en']
		);
		$this->assertSame(
			array(
				'countries' => array( 'BG' => 'Bulgarie' ),
				'regions'   => array( 'BG-02' => 'Province de Bourgas' ),
			),
			$localized['fr']
		);
		$this->assertArrayNotHasKey( 'de', $localized, 'A language without names is left out.' );
	}
}

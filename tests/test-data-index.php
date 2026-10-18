<?php
/**
 * Tests for Sloc_Data_Index, the sorted indexes of the bundled CSV files.
 *
 * @package Simple_Location
 */

/**
 * Data index tests.
 */
class DataIndexTest extends WP_UnitTestCase {

	/**
	 * Temporary directory for the test files.
	 *
	 * @var string
	 */
	protected $dir;

	/**
	 * Creates a directory with a small CSV file.
	 */
	public function set_up() {
		parent::set_up();
		$this->dir = get_temp_dir() . 'sloc-data-index-' . wp_generate_password( 8, false );
		wp_mkdir_p( $this->dir );
		$csv = "id,code,city\n"
			. "1,PHL,Philadelphia\n"
			. "2,abe,Allentown\n"
			. "3,,No Code\n"
			. "4,\"P\"\"Q\",\"Quoted, City\"\n"
			. "5,ACY,\"Atlantic\nCity\"\n"
			. "6,phl,Philadelphia\n"
			. "7,\\N,Unknown\n";
		file_put_contents( $this->dir . '/data.csv', $csv ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Removes the test files.
	 */
	public function tear_down() {
		foreach ( (array) glob( $this->dir . '/*' ) as $file ) {
			wp_delete_file( $file );
		}
		rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		parent::tear_down();
	}

	/**
	 * Builds an index of a column.
	 *
	 * @param int $column Column number.
	 * @return string Index file.
	 */
	protected function build( $column ) {
		$index = $this->dir . '/data-' . $column . '.idx';
		Sloc_Data_Index::build( $this->dir . '/data.csv', $index, $column, true );
		return $index;
	}

	/**
	 * Values are trimmed, with ASCII letters upper-cased and whitespace collapsed.
	 */
	public function test_normalize() {
		$this->assertSame( 'PHL', Sloc_Data_Index::normalize( ' phl ' ) );
		$this->assertSame( 'ATLANTIC CITY', Sloc_Data_Index::normalize( "Atlantic\n  City" ) );
		$this->assertSame( 'ZüRICH', Sloc_Data_Index::normalize( 'zürich' ), 'Only ASCII letters change case, as with strcasecmp().' );
	}

	/**
	 * The index holds the CSV file's size and one sorted line per row with a value; empty values and \N are left out.
	 */
	public function test_build() {
		$this->assertSame( 5, Sloc_Data_Index::build( $this->dir . '/data.csv', $this->dir . '/code.idx', 1, true ) );
		$lines = file( $this->dir . '/code.idx', FILE_IGNORE_NEW_LINES );
		$this->assertSame( '# size=' . filesize( $this->dir . '/data.csv' ), $lines[0] );
		$this->assertSame( array( 'ABE', 'ACY', 'P"Q', 'PHL', 'PHL' ), array_map( fn( $line ) => strstr( $line, "\t", true ), array_slice( $lines, 1 ) ) );
		$this->assertMatchesRegularExpression( '/^PHL\t\d{10}$/', $lines[4] );
		$this->assertFalse( Sloc_Data_Index::build( $this->dir . '/missing.csv', $this->dir . '/missing.idx', 1, true ) );
	}

	/**
	 * Every value is found, case-insensitively, with its full rows, including rows with quoted commas and line breaks.
	 */
	public function test_find() {
		$index = $this->build( 1 );
		$csv   = $this->dir . '/data.csv';

		$rows = Sloc_Data_Index::find( $index, $csv, 'phl' );
		$this->assertSame( array( array( '1', 'PHL', 'Philadelphia' ), array( '6', 'phl', 'Philadelphia' ) ), $rows, 'Rows with the same value are in file order.' );
		$this->assertSame( array( array( '2', 'abe', 'Allentown' ) ), Sloc_Data_Index::find( $index, $csv, 'ABE' ) );
		$this->assertSame( array( array( '4', 'P"Q', 'Quoted, City' ) ), Sloc_Data_Index::find( $index, $csv, 'p"q' ) );
		$this->assertSame( array( array( '5', 'ACY', "Atlantic\nCity" ) ), Sloc_Data_Index::find( $index, $csv, 'ACY' ) );

		// Values before, between and after the indexed ones aren't found.
		foreach ( array( 'AAA', 'ABF', 'PH', 'PHLX', 'ZZZ', '' ) as $missing ) {
			$this->assertSame( array(), Sloc_Data_Index::find( $index, $csv, $missing ), $missing );
		}

		$cities = $this->build( 2 );
		$this->assertCount( 2, Sloc_Data_Index::find( $cities, $csv, 'philadelphia' ) );
		$this->assertSame( '5', Sloc_Data_Index::find( $cities, $csv, 'ATLANTIC CITY' )[0][0] );
	}

	/**
	 * Without a usable index, find() returns null, so the caller reads the file instead.
	 */
	public function test_find_without_usable_index() {
		$index = $this->build( 1 );
		$csv   = $this->dir . '/data.csv';
		$this->assertNull( Sloc_Data_Index::find( $this->dir . '/missing.idx', $csv, 'PHL' ) );
		$this->assertNull( Sloc_Data_Index::find( $index, $this->dir . '/missing.csv', 'PHL' ) );

		// The CSV file changed after the index was built.
		file_put_contents( $csv, "9,PHL,Changed\n", FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->assertNull( Sloc_Data_Index::find( $index, $csv, 'PHL' ) );
	}

	/**
	 * The bundled indexes match the bundled files.
	 */
	public function test_bundled_indexes() {
		$data = dirname( __DIR__ ) . '/data/';
		$this->assertSame( 'KPHL', Sloc_Data_Index::find( $data . 'index/airports-iata_code.idx', $data . 'airports.csv', 'phl' )[0][1] );
		$this->assertSame( 'American Airlines', Sloc_Data_Index::find( $data . 'index/airlines-icao_code.idx', $data . 'airlines.csv', 'AAL' )[0][1] );
		foreach ( array( 'airports-iata_code', 'airports-ident', 'airports-gps_code', 'airports-municipality', 'airlines-iata_code', 'airlines-icao_code' ) as $name ) {
			$csv = $data . strtok( $name, '-' ) . '.csv';
			$this->assertNotNull( Sloc_Data_Index::find( $data . 'index/' . $name . '.idx', $csv, 'X' ), $name . ' is up to date.' );
		}
	}
}

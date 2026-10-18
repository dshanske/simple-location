<?php
/**
 * Sorted index files for the bundled CSV data.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds and searches sorted indexes of a column in a CSV file.
 *
 * An index file starts with a line giving the size of the CSV file it was built from, followed by one line per row
 * with a value in the column: the normalized value, a tab, and the row's byte offset in the CSV file, zero-padded.
 * Lines are sorted by value, then by offset, so rows with the same value stay in file order. A search finds the
 * value with a binary search on the file and reads only the matching lines, then the matching rows.
 *
 * Indexes are built by bin/update-data.php when the data files are updated.
 *
 * @since 6.0.0
 */
class Sloc_Data_Index {

	/**
	 * Normalizes a value for indexing and searching: trimmed, ASCII letters in upper case, and whitespace as spaces.
	 *
	 * Only ASCII letters change case, matching strcasecmp().
	 *
	 * @since 6.0.0
	 *
	 * @param string $value Value.
	 * @return string Normalized value.
	 */
	public static function normalize( $value ) {
		$value = preg_replace( '/\s+/', ' ', trim( (string) $value ) );
		return strtr( (string) $value, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' );
	}

	/**
	 * Builds an index of a column.
	 *
	 * @since 6.0.0
	 *
	 * @param string $data_file  CSV file.
	 * @param string $index_file Index file to write.
	 * @param int    $column     Column number, from 0.
	 * @param bool   $header     Whether the CSV file starts with a header row.
	 * @return int|false Number of indexed rows, or false if a file can't be opened.
	 */
	public static function build( $data_file, $index_file, $column, $header ) {
		$data = is_readable( $data_file ) ? fopen( $data_file, 'r' ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $data ) {
			return false;
		}
		if ( $header ) {
			fgetcsv( $data, 0, ',', '"', '' );
		}
		$lines  = array();
		$offset = ftell( $data );
		while ( false !== ( $row = fgetcsv( $data, 0, ',', '"', '' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$value = self::normalize( $row[ $column ] ?? '' );
			// OpenFlights writes \N for no value.
			if ( '' !== $value && '\N' !== $value ) {
				$lines[] = $value . "\t" . str_pad( (string) $offset, 10, '0', STR_PAD_LEFT );
			}
			$offset = ftell( $data );
		}
		fclose( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		sort( $lines, SORT_STRING );

		$index = fopen( $index_file, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $index ) {
			return false;
		}
		fwrite( $index, '# size=' . filesize( $data_file ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fwrite( $index, implode( "\n", $lines ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fclose( $index ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return count( $lines );
	}

	/**
	 * Finds the rows with a value.
	 *
	 * @since 6.0.0
	 *
	 * @param string $index_file Index file.
	 * @param string $data_file  CSV file the index was built from.
	 * @param string $value      Value to find.
	 * @return array[]|null Matching rows, in file order, or null if there is no usable index (missing, or built
	 *                      from a different version of the CSV file).
	 */
	public static function find( $index_file, $data_file, $value ) {
		if ( ! is_readable( $index_file ) || ! is_readable( $data_file ) ) {
			return null;
		}
		$index = fopen( $index_file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $index ) {
			return null;
		}
		// PHP caches file sizes, so a file updated since it was last checked would look unchanged.
		clearstatcache( true, $data_file );
		if ( ! preg_match( '/^# size=(\d+)$/', rtrim( (string) fgets( $index ) ), $match ) || filesize( $data_file ) !== (int) $match[1] ) {
			fclose( $index ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return null;
		}
		$offsets = self::search( $index, ftell( $index ), filesize( $index_file ), self::normalize( $value ) );
		fclose( $index ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$rows = array();
		if ( $offsets ) {
			$data = fopen( $data_file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			foreach ( $offsets as $offset ) {
				fseek( $data, $offset );
				$row = fgetcsv( $data, 0, ',', '"', '' );
				if ( is_array( $row ) ) {
					$rows[] = $row;
				}
			}
			fclose( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
		return $rows;
	}

	/**
	 * Binary-searches the sorted lines of an index for a value.
	 *
	 * Finds the first line whose value is not less than the one searched for, by searching byte positions: each
	 * step looks at the first line that starts at or after the middle position. Then it reads lines while they
	 * have the value.
	 *
	 * @since 6.0.0
	 *
	 * @param resource $index Open index file.
	 * @param int      $start Position of the first line after the header.
	 * @param int      $end   Size of the index file.
	 * @param string   $value Normalized value.
	 * @return int[] Byte offsets of the matching rows in the CSV file.
	 */
	protected static function search( $index, $start, $end, $value ) {
		$low  = $start;
		$high = $end;
		while ( $low < $high ) {
			$middle = intdiv( $low + $high, 2 );
			self::seek_line( $index, $middle, $start );
			$line = fgets( $index );
			if ( false === $line || strcmp( (string) strstr( $line, "\t", true ), $value ) >= 0 ) {
				$high = $middle;
			} else {
				$low = $middle + 1;
			}
		}

		$offsets = array();
		self::seek_line( $index, $low, $start );
		while ( false !== ( $line = fgets( $index ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$parts = explode( "\t", rtrim( $line, "\n" ) );
			if ( 2 !== count( $parts ) || $parts[0] !== $value ) {
				break;
			}
			$offsets[] = (int) $parts[1];
		}
		return $offsets;
	}

	/**
	 * Moves to the first line that starts at or after a position.
	 *
	 * @since 6.0.0
	 *
	 * @param resource $index    Open index file.
	 * @param int      $position Position.
	 * @param int      $start    Position of the first line after the header.
	 */
	protected static function seek_line( $index, $position, $start ) {
		if ( $position <= $start ) {
			fseek( $index, $start );
			return;
		}
		// The line before the position ends at or after it, so reading it leaves the file at the next line start.
		fseek( $index, $position - 1 );
		fgets( $index );
	}
}

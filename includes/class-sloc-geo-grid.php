<?php
/**
 * Offline lookup in a grid of areas, such as countries or timezones.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Finds the area, such as a country or timezone, that a location is in, from bundled files, without a request.
 *
 * The world is divided into half-degree cells, after aaronpk/Compass. Three files make a grid:
 *
 * - `.idx`: one unsigned 32-bit little-endian number per cell, 720 columns from 180° west, in 360 rows from 90°
 *   south. 0 means no area. Without the top bit, it is the number of the area that covers the whole cell. With the top
 *   bit, the rest is the offset of the cell's boundaries in the .dat file.
 * - `.dat`: for each border cell, an unsigned 16-bit ring count, then for each ring its area number and vertex count,
 *   and the vertices as pairs of unsigned 16-bit fractions of the cell (0 to 65535). All little-endian.
 * - `.json`: the areas, by number from 1: each an object with a 'code' and maybe a 'name'.
 *
 * A location in a border cell is in the area whose rings it is inside an odd number of times, so holes work.
 * Sloc_Geo_Data_Builder builds the files.
 *
 * @since 6.5.0
 */
class Sloc_Geo_Grid {

	/**
	 * Cells per degree.
	 *
	 * @since 6.5.0
	 * @var int
	 */
	const CELLS_PER_DEGREE = 2;

	/**
	 * Columns.
	 *
	 * @since 6.5.0
	 * @var int
	 */
	const WIDTH = 720;

	/**
	 * Rows.
	 *
	 * @since 6.5.0
	 * @var int
	 */
	const HEIGHT = 360;

	/**
	 * Largest vertex coordinate within a cell.
	 *
	 * @since 6.5.0
	 * @var int
	 */
	const SCALE = 65535;

	/**
	 * Flag of a border cell's index entry.
	 *
	 * @since 6.5.0
	 * @var int
	 */
	const BORDER = 0x80000000;

	/**
	 * Path and file name without extension.
	 *
	 * @since 6.5.0
	 * @var string
	 */
	protected string $prefix;

	/**
	 * Areas by number, from 1, once read.
	 *
	 * @since 6.5.0
	 * @var array|null
	 */
	protected ?array $areas = null;

	/**
	 * Constructor.
	 *
	 * @since 6.5.0
	 *
	 * @param string $prefix Path and file name without extension, such as data/geo/countries.
	 */
	public function __construct( $prefix ) {
		$this->prefix = (string) $prefix;
	}

	/**
	 * Returns whether the grid's files are there.
	 *
	 * @since 6.5.0
	 *
	 * @return bool Whether it can be used.
	 */
	public function exists() {
		return is_readable( $this->prefix . '.idx' ) && is_readable( $this->prefix . '.dat' ) && is_readable( $this->prefix . '.json' );
	}

	/**
	 * Returns the area a location is in.
	 *
	 * @since 6.5.0
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @return array|null The area, with 'code' and maybe 'name', or null if the location is in none, or the files
	 *                    are missing.
	 */
	public function lookup( $latitude, $longitude ) {
		if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) || ! $this->exists() ) {
			return null;
		}
		$x      = ( (float) $longitude + 180 ) * self::CELLS_PER_DEGREE;
		$y      = ( (float) $latitude + 90 ) * self::CELLS_PER_DEGREE;
		$column = min( self::WIDTH - 1, max( 0, (int) floor( $x ) ) );
		$row    = min( self::HEIGHT - 1, max( 0, (int) floor( $y ) ) );
		$entry  = self::read( $this->prefix . '.idx', ( $row * self::WIDTH + $column ) * 4, 4 );
		if ( 4 !== strlen( $entry ) ) {
			return null;
		}
		$value = unpack( 'V', $entry )[1];
		if ( 0 === $value ) {
			return null;
		}
		if ( ! ( $value & self::BORDER ) ) {
			return $this->area( $value );
		}
		$id = $this->border_lookup( $value & ~self::BORDER, ( $x - $column ) * self::SCALE, ( $y - $row ) * self::SCALE );
		return $id ? $this->area( $id ) : null;
	}

	/**
	 * Finds the area a point is in within a border cell.
	 *
	 * @since 6.5.0
	 *
	 * @param int   $offset Offset of the cell's rings in the .dat file.
	 * @param float $px     Point, as a fraction of the cell from 0 to SCALE, west to east.
	 * @param float $py     Point, south to north.
	 * @return int Area number, or 0 for none.
	 */
	protected function border_lookup( $offset, $px, $py ) {
		$handle = fopen( $this->prefix . '.dat', 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle || 0 !== fseek( $handle, $offset ) ) {
			return 0;
		}
		$header = (string) fread( $handle, 2 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		$rings  = 2 === strlen( $header ) ? unpack( 'v', $header )[1] : 0;
		$inside = array();
		for ( $r = 0; $r < $rings; $r++ ) {
			$head = (string) fread( $handle, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			if ( 4 !== strlen( $head ) ) {
				break;
			}
			$ring  = unpack( 'vid/vcount', $head );
			$bytes = (string) fread( $handle, 4 * $ring['count'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			if ( strlen( $bytes ) !== 4 * $ring['count'] || $ring['count'] < 3 ) {
				continue;
			}
			if ( self::in_ring( array_values( unpack( 'v*', $bytes ) ), $px, $py ) ) {
				$inside[ $ring['id'] ] = empty( $inside[ $ring['id'] ] );
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$found = array_keys( array_filter( $inside ) );
		return $found ? (int) $found[0] : 0;
	}

	/**
	 * Returns whether a point is inside a ring, by counting the edges a ray from it crosses.
	 *
	 * @since 6.5.0
	 *
	 * @param int[] $coordinates Ring, as x and y in turn.
	 * @param float $px          Point x.
	 * @param float $py          Point y.
	 * @return bool Whether it is inside.
	 */
	public static function in_ring( $coordinates, $px, $py ) {
		$inside = false;
		$count  = intdiv( count( $coordinates ), 2 );
		for ( $i = 0, $j = $count - 1; $i < $count; $j = $i++ ) {
			$xi = $coordinates[ 2 * $i ];
			$yi = $coordinates[ 2 * $i + 1 ];
			$xj = $coordinates[ 2 * $j ];
			$yj = $coordinates[ 2 * $j + 1 ];
			if ( ( $yi > $py ) !== ( $yj > $py ) && $px < ( $xj - $xi ) * ( $py - $yi ) / ( $yj - $yi ) + $xi ) {
				$inside = ! $inside;
			}
		}
		return $inside;
	}

	/**
	 * Returns an area by number.
	 *
	 * @since 6.5.0
	 *
	 * @param int $id Area number, from 1.
	 * @return array|null Area, or null if there is none, or it has no code.
	 */
	protected function area( $id ) {
		if ( null === $this->areas ) {
			$json        = json_decode( (string) file_get_contents( $this->prefix . '.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$this->areas = is_array( $json ) ? $json : array();
		}
		$area = $this->areas[ $id - 1 ] ?? null;
		return ( is_array( $area ) && ! empty( $area['code'] ) ) ? $area : null;
	}

	/**
	 * Reads bytes from a file.
	 *
	 * @since 6.5.0
	 *
	 * @param string $file   File.
	 * @param int    $offset Offset.
	 * @param int    $length Bytes to read.
	 * @return string Bytes, or an empty string if they can't be read.
	 */
	protected static function read( $file, $offset, $length ) {
		$handle = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return '';
		}
		$bytes = 0 === fseek( $handle, $offset ) ? (string) fread( $handle, $length ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $bytes;
	}
}

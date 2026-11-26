<?php
/**
 * Builds the offline geographic data in data/geo/.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Runs at development time from bin/update-data.php, outside WordPress, on local files.

/**
 * Builds the country and timezone grids and the places file that Sloc_Offline_Geo reads.
 *
 * This runs at development time, from bin/update-data.php and the tests; the plugin never loads it. See
 * Sloc_Geo_Grid for the grid format, after aaronpk/Compass: a half-degree grid where a cell inside one area is a
 * single number, and a border cell holds the area boundaries clipped to it.
 *
 * @since 6.5.0
 */
class Sloc_Geo_Data_Builder {

	/**
	 * Default simplification of boundaries, in degrees (about 550 m). Measured against GeoNames places, it loses no
	 * accuracy and makes the grids several times smaller.
	 *
	 * @since 6.5.0
	 * @var float
	 */
	const TOLERANCE = 0.005;

	/**
	 * GeoNames feature codes left out of the places: sections of places, and historical, abandoned or destroyed ones.
	 *
	 * @since 6.5.0
	 * @var string[]
	 */
	const SKIP_FEATURES = array( 'PPLX', 'PPLH', 'PPLQ', 'PPLW', 'PPLCH' );

	/**
	 * Builds a grid from GeoJSON polygons.
	 *
	 * @since 6.5.0
	 *
	 * @param array    $geojson   Decoded GeoJSON feature collection of polygons and multipolygons.
	 * @param callable $label     Receives a feature's properties and returns its entry in the names file, an array
	 *                            with at least 'code'; an empty code means no area.
	 * @param string   $prefix    Path and file name without extension; writes .idx, .dat and .json.
	 * @param float    $tolerance Simplification in degrees. 0 keeps every vertex.
	 * @return array Counts: names, uniform and border cells, and the sizes of the files in bytes.
	 */
	public static function build_grid( $geojson, $label, $prefix, $tolerance = self::TOLERANCE ) {
		$names     = array();
		$cells     = array();
		$tolerance = (float) $tolerance * Sloc_Geo_Grid::CELLS_PER_DEGREE;
		foreach ( $geojson['features'] ?? array() as $feature ) {
			$names[] = call_user_func( $label, $feature['properties'] ?? array() );
			$id      = count( $names );
			$type    = $feature['geometry']['type'] ?? '';
			if ( 'Polygon' === $type ) {
				$polygons = array( $feature['geometry']['coordinates'] );
			} elseif ( 'MultiPolygon' === $type ) {
				$polygons = $feature['geometry']['coordinates'];
			} else {
				continue;
			}
			foreach ( $polygons as $polygon ) {
				foreach ( $polygon as $index => $ring ) {
					self::add_ring( $ring, $id, 0 === $index ? 1 : -1, $tolerance, $cells );
				}
			}
		}
		return self::write_grid( $cells, $names, $prefix );
	}

	/**
	 * Clips a ring into the cells it covers.
	 *
	 * @since 6.5.0
	 *
	 * @param array[] $ring      Ring of [longitude, latitude] positions.
	 * @param int     $id        Area number, from 1.
	 * @param int     $sign      1 for an outer ring, -1 for a hole.
	 * @param float   $tolerance Simplification in cells.
	 * @param array   $cells     Rings by cell, added to.
	 */
	protected static function add_ring( $ring, $id, $sign, $tolerance, &$cells ) {
		$scale = Sloc_Geo_Grid::CELLS_PER_DEGREE;
		$pts   = array();
		foreach ( $ring as $position ) {
			$pts[] = array( ( $position[0] + 180 ) * $scale, ( $position[1] + 90 ) * $scale );
		}
		if ( $tolerance > 0 ) {
			$pts = self::simplify( $pts, $tolerance );
		}
		if ( count( $pts ) < 3 ) {
			return;
		}
		$xs = array_column( $pts, 0 );
		$ys = array_column( $pts, 1 );
		$x0 = max( 0, (int) floor( min( $xs ) ) );
		$x1 = min( Sloc_Geo_Grid::WIDTH, max( $x0 + 1, (int) ceil( max( $xs ) ) ) );
		$y0 = max( 0, (int) floor( min( $ys ) ) );
		$y1 = min( Sloc_Geo_Grid::HEIGHT, max( $y0 + 1, (int) ceil( max( $ys ) ) ) );
		self::split( $pts, $x0, $x1, $y0, $y1, $id, $sign, $cells );
	}

	/**
	 * Simplifies a ring with the Douglas-Peucker algorithm.
	 *
	 * @since 6.5.0
	 *
	 * @param array[] $pts       Points.
	 * @param float   $tolerance Largest distance a removed point may be from the result.
	 * @return array[] Points.
	 */
	public static function simplify( $pts, $tolerance ) {
		$count = count( $pts );
		if ( $count < 5 ) {
			return $pts;
		}
		$keep               = array_fill( 0, $count, false );
		$keep[0]            = true;
		$keep[ $count - 1 ] = true;
		$stack              = array( array( 0, $count - 1 ) );
		$limit              = $tolerance * $tolerance;
		while ( $stack ) {
			list( $first, $last ) = array_pop( $stack );
			$ax                   = $pts[ $first ][0];
			$ay                   = $pts[ $first ][1];
			$dx                   = $pts[ $last ][0] - $ax;
			$dy                   = $pts[ $last ][1] - $ay;
			$length               = $dx * $dx + $dy * $dy;
			$max                  = -1;
			$at                   = -1;
			for ( $i = $first + 1; $i < $last; $i++ ) {
				$px = $pts[ $i ][0] - $ax;
				$py = $pts[ $i ][1] - $ay;
				$t  = $length > 0 ? max( 0, min( 1, ( $px * $dx + $py * $dy ) / $length ) ) : 0;
				$ex = $px - $t * $dx;
				$ey = $py - $t * $dy;
				$d  = $ex * $ex + $ey * $ey;
				if ( $d > $max ) {
					$max = $d;
					$at  = $i;
				}
			}
			if ( $max > $limit ) {
				$keep[ $at ] = true;
				$stack[]     = array( $first, $at );
				$stack[]     = array( $at, $last );
			}
		}
		return array_values( array_intersect_key( $pts, array_filter( $keep ) ) );
	}

	/**
	 * Bisects a ring until each piece is in one cell.
	 *
	 * @since 6.5.0
	 *
	 * @param array[] $pts   Points, in cells.
	 * @param int     $x0    First column.
	 * @param int     $x1    Column after the last.
	 * @param int     $y0    First row.
	 * @param int     $y1    Row after the last.
	 * @param int     $id    Area number.
	 * @param int     $sign  1 for an outer ring, -1 for a hole.
	 * @param array   $cells Rings by cell, added to.
	 */
	protected static function split( $pts, $x0, $x1, $y0, $y1, $id, $sign, &$cells ) {
		if ( count( $pts ) < 3 ) {
			return;
		}
		if ( 1 === $x1 - $x0 && 1 === $y1 - $y0 ) {
			$cells[ $y0 * Sloc_Geo_Grid::WIDTH + $x0 ][] = array( $id, $sign, $pts );
			return;
		}
		if ( $x1 - $x0 >= $y1 - $y0 ) {
			$mid = intdiv( $x0 + $x1, 2 );
			self::split( self::clip( $pts, 0, $mid, true ), $x0, $mid, $y0, $y1, $id, $sign, $cells );
			self::split( self::clip( $pts, 0, $mid, false ), $mid, $x1, $y0, $y1, $id, $sign, $cells );
		} else {
			$mid = intdiv( $y0 + $y1, 2 );
			self::split( self::clip( $pts, 1, $mid, true ), $x0, $x1, $y0, $mid, $id, $sign, $cells );
			self::split( self::clip( $pts, 1, $mid, false ), $x0, $x1, $mid, $y1, $id, $sign, $cells );
		}
	}

	/**
	 * Clips a ring to one side of a line along a grid axis (Sutherland-Hodgman).
	 *
	 * @since 6.5.0
	 *
	 * @param array[] $pts   Points.
	 * @param int     $axis  0 for a vertical line at x = $value, 1 for a horizontal one at y = $value.
	 * @param float   $value Position of the line.
	 * @param bool    $below Whether to keep the side below the line, or the side above.
	 * @return array[] Points.
	 */
	public static function clip( $pts, $axis, $value, $below ) {
		$out   = array();
		$count = count( $pts );
		for ( $i = 0; $i < $count; $i++ ) {
			$a    = $pts[ $i ];
			$b    = $pts[ ( $i + 1 ) % $count ];
			$a_in = $below ? $a[ $axis ] <= $value : $a[ $axis ] >= $value;
			$b_in = $below ? $b[ $axis ] <= $value : $b[ $axis ] >= $value;
			if ( $a_in ) {
				$out[] = $a;
			}
			if ( $a_in !== $b_in ) {
				$t              = ( $value - $a[ $axis ] ) / ( $b[ $axis ] - $a[ $axis ] );
				$point          = array( $a[0] + $t * ( $b[0] - $a[0] ), $a[1] + $t * ( $b[1] - $a[1] ) );
				$point[ $axis ] = $value;
				$out[]          = $point;
			}
		}
		return $out;
	}

	/**
	 * Returns the area of a ring, ignoring its direction.
	 *
	 * @since 6.5.0
	 *
	 * @param array[] $pts Points.
	 * @return float Area.
	 */
	public static function area( $pts ) {
		$sum   = 0.0;
		$count = count( $pts );
		for ( $i = 0; $i < $count; $i++ ) {
			$a    = $pts[ $i ];
			$b    = $pts[ ( $i + 1 ) % $count ];
			$sum += $a[0] * $b[1] - $b[0] * $a[1];
		}
		return abs( $sum ) / 2;
	}

	/**
	 * Writes a grid's files.
	 *
	 * A cell that one area covers is stored as that area's number. A cell that no area touches is 0. Other cells
	 * keep their rings, as fractions of the cell from 0 to 65535.
	 *
	 * @since 6.5.0
	 *
	 * @param array  $cells  Rings by cell: each [area number, sign, points].
	 * @param array  $names  Names file entries, by area number from 1.
	 * @param string $prefix Path and file name without extension.
	 * @return array Counts. See build_grid().
	 */
	protected static function write_grid( $cells, $names, $prefix ) {
		$total   = Sloc_Geo_Grid::WIDTH * Sloc_Geo_Grid::HEIGHT;
		$index   = array_fill( 0, $total, 0 );
		$data    = '';
		$uniform = 0;
		$border  = 0;
		$scale   = Sloc_Geo_Grid::SCALE;
		ksort( $cells );
		foreach ( $cells as $cell => $rings ) {
			$column = $cell % Sloc_Geo_Grid::WIDTH;
			$row    = intdiv( $cell, Sloc_Geo_Grid::WIDTH );
			$cover  = array();
			foreach ( $rings as $ring ) {
				$cover[ $ring[0] ] = ( $cover[ $ring[0] ] ?? 0 ) + $ring[1] * self::area( $ring[2] );
			}
			arsort( $cover );
			$top = array_key_first( $cover );
			if ( $cover[ $top ] >= 1 - 1e-7 ) {
				$index[ $cell ] = $top;
				++$uniform;
				continue;
			}
			$packed = '';
			$count  = 0;
			foreach ( $rings as $ring ) {
				$points = array();
				$last   = null;
				foreach ( $ring[2] as $point ) {
					$point = array(
						max( 0, min( $scale, (int) round( ( $point[0] - $column ) * $scale ) ) ),
						max( 0, min( $scale, (int) round( ( $point[1] - $row ) * $scale ) ) ),
					);
					if ( $point !== $last ) {
						$points[] = $point;
						$last     = $point;
					}
				}
				if ( count( $points ) < 3 ) {
					continue;
				}
				$packed .= pack( 'vv', $ring[0], count( $points ) );
				foreach ( $points as $point ) {
					$packed .= pack( 'vv', $point[0], $point[1] );
				}
				++$count;
			}
			if ( 0 === $count ) {
				continue;
			}
			$index[ $cell ] = Sloc_Geo_Grid::BORDER | strlen( $data );
			$data          .= pack( 'v', $count ) . $packed;
			++$border;
		}
		$binary = pack( 'V*', ...$index );
		file_put_contents( $prefix . '.idx', $binary );
		file_put_contents( $prefix . '.dat', $data );
		file_put_contents( $prefix . '.json', self::json( $names ) );
		return array(
			'names'   => count( $names ),
			'uniform' => $uniform,
			'border'  => $border,
			'idx'     => strlen( $binary ),
			'dat'     => strlen( $data ),
		);
	}

	/**
	 * Returns the names file entry of a Natural Earth country: its ISO 3166-1 code and name.
	 *
	 * Natural Earth gives -99 for places without a code of their own, such as Northern Cyprus; those get an empty
	 * code, and a lookup there falls back to the nearest place's country.
	 *
	 * @since 6.5.0
	 *
	 * @param array $properties Feature properties.
	 * @return array Entry with 'code' and 'name'.
	 */
	public static function country_label( $properties ) {
		$code = '';
		foreach ( array( 'ISO_A2_EH', 'ISO_A2' ) as $key ) {
			$value = (string) ( $properties[ $key ] ?? '' );
			if ( 2 === strlen( $value ) && '-9' !== substr( $value, 0, 2 ) ) {
				$code = strtoupper( $value );
				break;
			}
		}
		return array(
			'code' => $code,
			'name' => (string) ( $properties['NAME'] ?? '' ),
		);
	}

	/**
	 * Returns the names file entry of a timezone-boundary-builder zone.
	 *
	 * @since 6.5.0
	 *
	 * @param array $properties Feature properties.
	 * @return array Entry with 'code', the timezone name.
	 */
	public static function timezone_label( $properties ) {
		return array( 'code' => (string) ( $properties['tzid'] ?? '' ) );
	}

	/**
	 * Builds the places file, its cell index and the regions file from GeoNames.
	 *
	 * The places file is CSV without a header: name, latitude, longitude, country code, region (admin1) code and
	 * population, sorted by one-degree cell, west to east within each row of cells from south to north. The cell
	 * index has the byte offset of each cell's first place, as unsigned 32-bit little-endian numbers, plus the file
	 * size at the end. The regions file is CSV: country code, region code, name.
	 *
	 * @since 6.5.0
	 *
	 * @param string $cities  GeoNames citiesN.txt.
	 * @param string $admin1  GeoNames admin1CodesASCII.txt.
	 * @param string $out_dir Directory to write places.csv, places.cells and regions.csv to.
	 * @return array Counts: places and regions.
	 */
	public static function build_places( $cities, $admin1, $out_dir ) {
		$rows   = array();
		$handle = fopen( $cities, 'r' );
		while ( false !== ( $line = fgets( $handle ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$f = explode( "\t", rtrim( $line, "\r\n" ) );
			if ( count( $f ) < 15 || in_array( $f[7], self::SKIP_FEATURES, true ) ) {
				continue;
			}
			$lat    = round( (float) $f[4], 4 );
			$lng    = round( (float) $f[5], 4 );
			$cell   = Sloc_Offline_Geo::place_cell( $lat, $lng );
			$rows[] = array( $cell, $f[1], $lat, $lng, $f[8], $f[10], (int) $f[14] );
		}
		fclose( $handle );
		// By cell, then by population, largest first, so a cell's places are read in a stable order.
		usort( $rows, fn( $a, $b ) => array( $a[0], -$a[6], $a[1] ) <=> array( $b[0], -$b[6], $b[1] ) );

		$cells  = array_fill( 0, Sloc_Offline_Geo::PLACE_COLUMNS * Sloc_Offline_Geo::PLACE_ROWS + 1, -1 );
		$output = fopen( $out_dir . '/places.csv', 'w' );
		foreach ( $rows as $row ) {
			if ( -1 === $cells[ $row[0] ] ) {
				$cells[ $row[0] ] = ftell( $output );
			}
			fputcsv( $output, array_slice( $row, 1 ), ',', '"', '' );
		}
		$size = ftell( $output );
		fclose( $output );
		// An empty cell starts where the next one does.
		$next = $size;
		for ( $i = count( $cells ) - 1; $i >= 0; $i-- ) {
			if ( -1 === $cells[ $i ] ) {
				$cells[ $i ] = $next;
			}
			$next = $cells[ $i ];
		}
		file_put_contents( $out_dir . '/places.cells', pack( 'V*', ...$cells ) );

		$regions = array();
		foreach ( file( $admin1, FILE_IGNORE_NEW_LINES ) as $line ) {
			$f = explode( "\t", $line );
			if ( count( $f ) < 2 || false === strpos( $f[0], '.' ) ) {
				continue;
			}
			list( $country, $code ) = explode( '.', $f[0], 2 );
			$regions[]              = array( $country, $code, $f[1] );
		}
		sort( $regions );
		$output = fopen( $out_dir . '/regions.csv', 'w' );
		foreach ( $regions as $region ) {
			fputcsv( $output, $region, ',', '"', '' );
		}
		fclose( $output );
		return array(
			'places'  => count( $rows ),
			'regions' => count( $regions ),
		);
	}

	/**
	 * Encodes JSON with unescaped slashes and Unicode, inside or outside WordPress.
	 *
	 * @since 6.5.0
	 *
	 * @param mixed $value Value.
	 * @return string JSON.
	 */
	protected static function json( $value ) {
		return (string) json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Also runs outside WordPress.
	}
}

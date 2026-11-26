<?php
/**
 * Offline country, place and timezone lookup.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Looks up the country, nearest place, region and timezone of a location from data bundled in data/geo/, without a
 * request.
 *
 * - Countries: a grid of Natural Earth 1:10m borders (public domain). A location in the sea within 15 km of a place
 *   takes that place's country.
 * - Timezones: a grid of timezone-boundary-builder zones with oceans (ODbL, from OpenStreetMap), so a location at sea
 *   gets its nautical zone, such as Etc/GMT+3.
 * - Places: GeoNames places of at least 1000 people (CC BY 4.0), chosen by distance weighted by population, so the
 *   city a location is in beats a smaller place just nearer.
 *
 * After aaronpk/Compass. Sloc_Geo_Data_Builder builds the files, from bin/update-data.php.
 *
 * @since 6.5.0
 */
class Sloc_Offline_Geo {

	/**
	 * Columns of one-degree cells in the places index.
	 *
	 * @since 6.5.0
	 * @var int
	 */
	const PLACE_COLUMNS = 360;

	/**
	 * Rows of one-degree cells in the places index.
	 *
	 * @since 6.5.0
	 * @var int
	 */
	const PLACE_ROWS = 180;

	/**
	 * Distance from a place within which a location in the sea takes its country, in meters.
	 *
	 * @since 6.5.0
	 * @var int
	 */
	const COAST_DISTANCE = 15000;

	/**
	 * Distance to look for places within, in meters.
	 *
	 * @since 6.5.0
	 * @var int
	 */
	const SEARCH_DISTANCE = 50000;

	/**
	 * Cache group.
	 *
	 * @since 6.5.0
	 * @var string
	 */
	const CACHE_GROUP = 'sloc_offline_geo';

	/**
	 * Grids, by file prefix.
	 *
	 * @since 6.5.0
	 * @var Sloc_Geo_Grid[]
	 */
	protected static array $grids = array();

	/**
	 * Regions, by country and region code, once read.
	 *
	 * @since 6.5.0
	 * @var array|null
	 */
	protected static ?array $regions = null;

	/**
	 * Returns the directory of the data files.
	 *
	 * @since 6.5.0
	 *
	 * @return string Path, with a trailing slash.
	 */
	public static function data_dir() {
		$dir = plugin_dir_path( __DIR__ ) . 'data/geo/';

		/**
		 * Filters the directory of the offline geographic data: the country and timezone grids, places and regions.
		 *
		 * @since 6.5.0
		 *
		 * @param string $dir Path, with a trailing slash.
		 */
		return trailingslashit( (string) apply_filters( 'sloc_offline_geo_dir', $dir ) );
	}

	/**
	 * Returns a grid.
	 *
	 * @since 6.5.0
	 *
	 * @param string $name Grid name: countries or timezones.
	 * @return Sloc_Geo_Grid Grid.
	 */
	protected static function grid( $name ) {
		$prefix = self::data_dir() . $name;
		if ( ! isset( self::$grids[ $prefix ] ) ) {
			self::$grids[ $prefix ] = new Sloc_Geo_Grid( $prefix );
		}
		return self::$grids[ $prefix ];
	}

	/**
	 * Forgets the regions read, such as after the data directory changes.
	 *
	 * @since 6.5.0
	 */
	public static function reset() {
		self::$grids   = array();
		self::$regions = null;
	}

	/**
	 * Returns whether a location is valid.
	 *
	 * @since 6.5.0
	 *
	 * @param mixed $latitude  Latitude.
	 * @param mixed $longitude Longitude.
	 * @return bool Whether both are numbers in range.
	 */
	protected static function valid( $latitude, $longitude ) {
		return is_numeric( $latitude ) && is_numeric( $longitude ) && abs( (float) $latitude ) <= 90 && abs( (float) $longitude ) <= 180;
	}

	/**
	 * Returns the country of a location.
	 *
	 * The country whose borders the location is within; or, at sea, or in a place Natural Earth gives no code, the
	 * country of a place within 15 km; or else the country of the timezone, whose boundaries include territorial
	 * waters and small islands the borders leave out.
	 *
	 * @since 6.5.0
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @return string Two-letter country code, or an empty string if there is none.
	 */
	public static function country( $latitude, $longitude ) {
		if ( ! self::valid( $latitude, $longitude ) ) {
			return '';
		}
		$key   = sprintf( 'country:%.4f,%.4f', $latitude, $longitude );
		$found = false;
		$code  = wp_cache_get( $key, self::CACHE_GROUP, false, $found );
		if ( $found ) {
			return (string) $code;
		}
		$area = self::grid( 'countries' )->lookup( $latitude, $longitude );
		$code = $area ? (string) $area['code'] : '';
		if ( '' === $code ) {
			$nearest = self::nearest_place( $latitude, $longitude, self::COAST_DISTANCE );
			$code    = $nearest ? $nearest['country'] : '';
		}
		if ( '' === $code ) {
			$code = self::timezone_country( self::timezone( $latitude, $longitude ) );
		}
		wp_cache_set( $key, $code, self::CACHE_GROUP );
		return $code;
	}

	/**
	 * Returns the country of a timezone.
	 *
	 * @since 6.5.0
	 *
	 * @param string $timezone Timezone name, such as America/New_York.
	 * @return string Two-letter country code, or an empty string for a nautical timezone such as Etc/GMT+5, or one
	 *                without a country.
	 */
	public static function timezone_country( $timezone ) {
		if ( '' === $timezone || 0 === strpos( $timezone, 'Etc/' ) || ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
			return '';
		}
		$location = ( new DateTimeZone( $timezone ) )->getLocation();
		$country  = is_array( $location ) ? (string) ( $location['country_code'] ?? '' ) : '';
		return ( 2 === strlen( $country ) && '??' !== $country ) ? $country : '';
	}

	/**
	 * Returns the timezone of a location.
	 *
	 * @since 6.5.0
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @return string Timezone name, such as America/New_York or, at sea, Etc/GMT+3; or an empty string if there is
	 *                none or the data is missing.
	 */
	public static function timezone( $latitude, $longitude ) {
		if ( ! self::valid( $latitude, $longitude ) ) {
			return '';
		}
		$area = self::grid( 'timezones' )->lookup( $latitude, $longitude );
		return $area ? (string) $area['code'] : '';
	}

	/**
	 * Returns whether the timezone data is there.
	 *
	 * @since 6.5.0
	 *
	 * @return bool Whether timezone() can answer.
	 */
	public static function has_timezones() {
		return self::grid( 'timezones' )->exists();
	}

	/**
	 * Returns whether the country data is there.
	 *
	 * @since 6.5.0
	 *
	 * @return bool Whether country() can answer from borders.
	 */
	public static function has_countries() {
		return self::grid( 'countries' )->exists();
	}

	/**
	 * Returns the place a location is in, or is nearest, weighted by population.
	 *
	 * Each place within 50 km is scored by its distance divided by the radius of a city of its population, after
	 * aaronpk/Compass, and places further than twice that radius, or 10 km if more, are left out. The best score wins.
	 *
	 * @since 6.5.0
	 *
	 * @param float  $latitude  Latitude.
	 * @param float  $longitude Longitude.
	 * @param string $country   Optional. Country code; only places in it count, such as the location's own country,
	 *                          so a big city across a border doesn't win.
	 * @return array|null Place: name, latitude, longitude, country, region_code, region, population and distance in
	 *                    meters. Or null if no place is close enough.
	 */
	public static function place( $latitude, $longitude, $country = '' ) {
		if ( ! self::valid( $latitude, $longitude ) ) {
			return null;
		}
		$country = strtoupper( (string) $country );
		$key     = sprintf( 'place:%.4f,%.4f,%s', $latitude, $longitude, $country );
		$found   = false;
		$place   = wp_cache_get( $key, self::CACHE_GROUP, false, $found );
		if ( $found ) {
			return $place ? $place : null;
		}
		$best  = null;
		$score = INF;
		foreach ( self::places_near( $latitude, $longitude, self::SEARCH_DISTANCE ) as $candidate ) {
			if ( '' !== $country && $candidate['country'] !== $country ) {
				continue;
			}
			$radius = max( 0.5, sqrt( $candidate['population'] / ( 2000 * M_PI ) ) ) * 1000;
			if ( $candidate['distance'] > max( 10000, 2 * $radius ) ) {
				continue;
			}
			$candidate_score = $candidate['distance'] / $radius;
			if ( $candidate_score < $score ) {
				$score = $candidate_score;
				$best  = $candidate;
			}
		}
		if ( $best ) {
			$best = self::with_region( $best );
		}
		wp_cache_set( $key, $best ? $best : false, self::CACHE_GROUP );
		return $best;
	}

	/**
	 * Returns the nearest place within a distance, whatever its size.
	 *
	 * @since 6.5.0
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @param int   $distance  Meters.
	 * @return array|null Place, without its region. See place(). Or null if there is none that close.
	 */
	public static function nearest_place( $latitude, $longitude, $distance ) {
		$nearest = null;
		foreach ( self::places_near( $latitude, $longitude, $distance ) as $candidate ) {
			if ( $candidate['distance'] <= $distance && ( ! $nearest || $candidate['distance'] < $nearest['distance'] ) ) {
				$nearest = $candidate;
			}
		}
		return $nearest;
	}

	/**
	 * Returns the one-degree cell of a location in the places index.
	 *
	 * @since 6.5.0
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @return int Cell, west to east within rows from south to north.
	 */
	public static function place_cell( $latitude, $longitude ) {
		$row    = min( self::PLACE_ROWS - 1, max( 0, (int) floor( (float) $latitude + 90 ) ) );
		$column = min( self::PLACE_COLUMNS - 1, max( 0, (int) floor( (float) $longitude + 180 ) ) );
		return $row * self::PLACE_COLUMNS + $column;
	}

	/**
	 * Returns the places in the cells around a location, with their distance.
	 *
	 * @since 6.5.0
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @param int   $distance  Meters; the cells covering at least this distance are read.
	 * @return array[] Places, each with name, latitude, longitude, country, region_code, population and distance.
	 */
	protected static function places_near( $latitude, $longitude, $distance ) {
		$file  = self::data_dir() . 'places.csv';
		$cells = self::data_dir() . 'places.cells';
		if ( ! is_readable( $file ) || ! is_readable( $cells ) ) {
			return array();
		}
		$latitude  = (float) $latitude;
		$longitude = (float) $longitude;
		$span      = $distance / 111320;
		$lng_span  = min( 180, $span / max( 0.01, cos( deg2rad( min( 89.9, abs( $latitude ) + $span ) ) ) ) );
		$rows      = range( max( 0, (int) floor( $latitude - $span + 90 ) ), min( self::PLACE_ROWS - 1, (int) floor( $latitude + $span + 90 ) ) );
		$first     = (int) floor( $longitude - $lng_span + 180 );
		$last      = (int) floor( $longitude + $lng_span + 180 );
		$columns   = array();
		// Each column once, wrapping around the antimeridian.
		$last = min( $last, $first + self::PLACE_COLUMNS - 1 );
		for ( $column = $first; $column <= $last; $column++ ) {
			$columns[] = ( ( $column % self::PLACE_COLUMNS ) + self::PLACE_COLUMNS ) % self::PLACE_COLUMNS;
		}

		$index = fopen( $cells, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$data  = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $index || ! $data ) {
			return array();
		}
		$places = array();
		foreach ( $rows as $row ) {
			foreach ( $columns as $column ) {
				fseek( $index, ( $row * self::PLACE_COLUMNS + $column ) * 4 );
				$bounds = (string) fread( $index, 8 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
				if ( 8 !== strlen( $bounds ) ) {
					continue;
				}
				list( , $start, $end ) = unpack( 'V2', $bounds );
				if ( $end <= $start ) {
					continue;
				}
				fseek( $data, $start );
				$chunk = (string) fread( $data, $end - $start ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
				foreach ( explode( "\n", rtrim( $chunk, "\n" ) ) as $line ) {
					$place = self::parse_place( $line );
					if ( $place ) {
						$place['distance'] = geo_distance( $latitude, $longitude, $place['latitude'], $place['longitude'] );
						$places[]          = $place;
					}
				}
			}
		}
		fclose( $index ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $places;
	}

	/**
	 * Parses a line of the places file.
	 *
	 * @since 6.5.0
	 *
	 * @param string $line Line.
	 * @return array|null Place, or null if the line isn't one.
	 */
	protected static function parse_place( $line ) {
		$row = str_getcsv( $line, ',', '"', '' );
		if ( count( $row ) < 6 ) {
			return null;
		}
		return array(
			'name'        => (string) $row[0],
			'latitude'    => (float) $row[1],
			'longitude'   => (float) $row[2],
			'country'     => (string) $row[3],
			'region_code' => (string) $row[4],
			'population'  => (int) $row[5],
		);
	}

	/**
	 * Adds the region's name to a place.
	 *
	 * @since 6.5.0
	 *
	 * @param array $place Place.
	 * @return array Place, with 'region': the region's name, or an empty string.
	 */
	protected static function with_region( $place ) {
		$place['region'] = self::region_name( $place['country'], $place['region_code'] );
		return $place;
	}

	/**
	 * Returns the name of a region from GeoNames' first-level divisions.
	 *
	 * @since 6.5.0
	 *
	 * @param string $country Country code.
	 * @param string $code    GeoNames region (admin1) code, which for some countries, such as the US, is the ISO
	 *                        3166-2 code, and for others isn't.
	 * @return string Name, or an empty string if it isn't known.
	 */
	public static function region_name( $country, $code ) {
		if ( null === self::$regions ) {
			self::$regions = array();
			$file          = self::data_dir() . 'regions.csv';
			$handle        = is_readable( $file ) ? fopen( $file, 'rb' ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			if ( $handle ) {
				while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
					if ( 3 === count( $row ) ) {
						self::$regions[ $row[0] . '.' . $row[1] ] = $row[2];
					}
				}
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}
		}
		return (string) ( self::$regions[ strtoupper( (string) $country ) . '.' . $code ] ?? '' );
	}

	/**
	 * Finds a place by name.
	 *
	 * Names match ignoring case and accents. When several places match, the one that matches the most hints wins,
	 * then the one with the most people. A hint matches a place's country code or name, or its region's name or
	 * code, so "PA" after Philadelphia finds Pennsylvania rather than Panama.
	 *
	 * @since 6.5.0
	 *
	 * @param string   $name  Place name, such as Philadelphia.
	 * @param string[] $hints Optional. Regions or countries the place is in, such as array( 'Pennsylvania', 'US' ).
	 * @return array|null Place, with its region. See place(). Its distance is 0. Or null if none matches.
	 */
	public static function find_place( $name, $hints = array() ) {
		$name = self::normalize( $name );
		$file = self::data_dir() . 'places.csv';
		if ( '' === $name || ! is_readable( $file ) ) {
			return null;
		}
		$hints  = array_filter( array_map( array( __CLASS__, 'normalize' ), (array) $hints ), 'strlen' );
		$handle = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return null;
		}
		$best = null;
		$rank = null;
		while ( false !== ( $line = fgets( $handle ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			// Most lines can be skipped before parsing: a name is at least as many bytes as its normalized form, and
			// at most about three times as many, for accented letters.
			$comma = strpos( $line, ',' );
			if ( false === $comma || ( '"' !== $line[0] && ( $comma < strlen( $name ) || $comma > 3 * strlen( $name ) + 2 ) ) ) {
				continue;
			}
			$place = self::parse_place( rtrim( $line, "\n" ) );
			if ( ! $place || self::normalize( $place['name'] ) !== $name ) {
				continue;
			}
			$place     = self::with_region( $place );
			$names     = array_map(
				array( __CLASS__, 'normalize' ),
				array( $place['country'], (string) Geo_Provider::country_name( $place['country'] ), $place['region'], $place['region_code'], (string) Geo_Provider::region_code( $place['region'], $place['country'] ) )
			);
			$candidate = array( count( array_intersect( $hints, $names ) ), $place['population'] );
			if ( null === $rank || $candidate > $rank ) {
				$rank = $candidate;
				$best = $place;
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( $best ) {
			$best['distance'] = 0;
		}
		return $best;
	}

	/**
	 * Normalizes a name for comparing: without accents, in lower case, with single spaces.
	 *
	 * @since 6.5.0
	 *
	 * @param string $name Name.
	 * @return string Normalized name.
	 */
	public static function normalize( $name ) {
		$name = remove_accents( trim( (string) $name ) );
		return strtolower( (string) preg_replace( '/\s+/', ' ', $name ) );
	}
}

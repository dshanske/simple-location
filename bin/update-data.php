<?php
/**
 * Downloads the bundled data sources and builds the files in data/.
 *
 * Run with `composer update-data`, or `php bin/update-data.php [meteostat] [airports] [airlines]` to update only
 * some sources. This runs outside WordPress, at development time; the plugin only reads the files it writes.
 *
 * Sources:
 * - meteostat: Meteostat weather station database (https://data.meteostat.net/stations.db, CC BY 4.0), reduced to
 *   data/meteostat-stations.csv. Needs the pdo_sqlite extension.
 * - airports: OurAirports (https://ourairports.com/data/, public domain), copied to data/airports.csv.
 * - airlines: OpenFlights (https://openflights.org/data, ODbL), copied to data/airlines.csv.
 * - countries: Natural Earth 1:10m countries (https://www.naturalearthdata.com/, public domain), built into the
 *   country grid in data/geo/countries.*.
 * - timezones: timezone-boundary-builder zones with oceans (https://github.com/evansiroky/timezone-boundary-builder,
 *   ODbL), built into the timezone grid in data/geo/timezones.*. Needs the zip extension, and about 3 GB of memory.
 * - places: GeoNames places of at least 1000 people and first-level divisions (https://www.geonames.org/, CC BY 4.0),
 *   built into data/geo/places.csv, places.cells and regions.csv. Needs the zip extension.
 * - addresses: OpenCage address-formatting (https://github.com/OpenCageData/address-formatting, MIT), its current
 *   commit, built into data/address-formats.json, which records the commit. Needs symfony/yaml (composer install).
 *
 * Updating airports or airlines also rebuilds their indexes in data/index/. `php bin/update-data.php indexes`
 * rebuilds the indexes from the current files without downloading anything.
 *
 * @package Simple_Location
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.DevelopmentFunctions, WordPress.Security.EscapeOutput, WordPress.DB.RestrictedClasses -- Command-line build script, run outside WordPress; it reads the Meteostat SQLite file.

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

// The index class is shared with the plugin, whose files only load when ABSPATH is defined.
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require dirname( __DIR__ ) . '/includes/class-sloc-data-index.php';
require dirname( __DIR__ ) . '/includes/class-sloc-geo-grid.php';
require dirname( __DIR__ ) . '/includes/class-sloc-offline-geo.php';
require dirname( __DIR__ ) . '/includes/class-sloc-geo-data-builder.php';
require dirname( __DIR__ ) . '/includes/class-sloc-address-format-builder.php';

$sloc_data_dir = dirname( __DIR__ ) . '/data';

$sloc_sources = array(
	'meteostat' => 'https://data.meteostat.net/stations.db',
	'airports'  => 'https://davidmegginson.github.io/ourairports-data/airports.csv',
	'airlines'  => 'https://raw.githubusercontent.com/jpatokal/openflights/master/data/airlines.dat',
	'countries' => 'https://raw.githubusercontent.com/nvkelso/natural-earth-vector/master/geojson/ne_10m_admin_0_countries.geojson',
	'timezones' => 'https://github.com/evansiroky/timezone-boundary-builder/releases/latest/download/timezones-with-oceans.geojson.zip',
	'places'    => 'https://download.geonames.org/export/dump/cities1000.zip',
	'addresses' => 'https://api.github.com/repos/OpenCageData/address-formatting/commits/master',
);

/**
 * OpenCage address-formatting files, at a commit, as a sprintf() pattern for the commit and path.
 */
const SLOC_ADDRESS_FORMATTING_FILE = 'https://raw.githubusercontent.com/OpenCageData/address-formatting/%s/%s';

/**
 * GeoNames first-level divisions, downloaded with the places.
 */
const SLOC_GEONAMES_ADMIN1 = 'https://download.geonames.org/export/dump/admin1CodesASCII.txt';

/**
 * Indexes of the airport and airline files: index name => CSV file, column, whether it has a header row.
 */
const SLOC_INDEXES = array(
	'airports-iata_code'    => array( 'airports.csv', 'iata_code', true ),
	'airports-ident'        => array( 'airports.csv', 'ident', true ),
	'airports-gps_code'     => array( 'airports.csv', 'gps_code', true ),
	'airports-municipality' => array( 'airports.csv', 'municipality', true ),
	'airlines-iata_code'    => array( 'airlines.csv', 3, false ),
	'airlines-icao_code'    => array( 'airlines.csv', 4, false ),
);

/**
 * Meteostat data sources that the hourly JSON API serves: hourly observations, and the model data it uses for
 * missing hours.
 */
const SLOC_METEOSTAT_HOURLY_PROVIDERS = array( 'isd_lite', 'metar', 'dwd_hourly', 'eccc_hourly', 'gsa_hourly', 'zamg_hourly', 'metno_forecast', 'dwd_mosmix' );

/**
 * Downloads a URL to a file.
 *
 * @param string $url  URL.
 * @param string $file Destination path.
 */
function sloc_download( $url, $file ) {
	fwrite( STDOUT, "Downloading $url\n" );
	$context = stream_context_create(
		array(
			'http' => array(
				'user_agent' => 'Simple Location data update (https://github.com/dshanske/simple-location)',
				'timeout'    => 300,
			),
		)
	);
	if ( ! copy( $url, $file, $context ) || 0 === filesize( $file ) ) {
		fwrite( STDERR, "Could not download $url\n" );
		exit( 1 );
	}
}

/**
 * Builds data/meteostat-stations.csv from the Meteostat station database.
 *
 * The file starts with a comment line holding the date the data was built, then a header row, then one row per
 * station: id, latitude, longitude, elevation, country, region, timezone, the first and last date of hourly data
 * (empty without any), and the English name.
 *
 * @param string $database Path of stations.db.
 * @param string $file     Destination path.
 */
function sloc_build_meteostat( $database, $file ) {
	if ( ! extension_loaded( 'pdo_sqlite' ) ) {
		fwrite( STDERR, "The pdo_sqlite extension is needed to read the Meteostat database.\n" );
		exit( 1 );
	}
	$db           = new PDO( 'sqlite:' . $database );
	$placeholders = implode( ',', array_fill( 0, count( SLOC_METEOSTAT_HOURLY_PROVIDERS ), '?' ) );
	$query        = $db->prepare(
		"SELECT s.id, s.latitude, s.longitude, s.elevation, s.country, s.region, s.timezone,
			( SELECT MIN( i.start ) FROM inventory i WHERE i.station = s.id AND i.provider IN ( $placeholders ) ) AS hourly_start,
			( SELECT MAX( i.end ) FROM inventory i WHERE i.station = s.id AND i.provider IN ( $placeholders ) ) AS hourly_end,
			( SELECT n.name FROM names n WHERE n.station = s.id AND n.language = 'en' ) AS name
		FROM stations s
		WHERE s.latitude IS NOT NULL AND s.longitude IS NOT NULL
		ORDER BY s.id"
	);
	$query->execute( array_merge( SLOC_METEOSTAT_HOURLY_PROVIDERS, SLOC_METEOSTAT_HOURLY_PROVIDERS ) );

	$handle = fopen( $file, 'w' );
	fwrite( $handle, '# Meteostat weather stations (https://meteostat.net, CC BY 4.0), built ' . gmdate( 'Y-m-d' ) . "\n" );
	fputcsv( $handle, array( 'id', 'latitude', 'longitude', 'elevation', 'country', 'region', 'timezone', 'hourly_start', 'hourly_end', 'name' ), ',', '"', '' );
	$count = 0;
	while ( $row = $query->fetch( PDO::FETCH_ASSOC ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		fputcsv(
			$handle,
			array(
				$row['id'],
				round( (float) $row['latitude'], 4 ),
				round( (float) $row['longitude'], 4 ),
				$row['elevation'],
				$row['country'],
				$row['region'],
				$row['timezone'],
				$row['hourly_start'],
				$row['hourly_end'],
				$row['name'],
			),
			',',
			'"',
			''
		);
		++$count;
	}
	fclose( $handle );
	fwrite( STDOUT, "Wrote $count stations to $file\n" );
}

/**
 * Returns the path part of a URL.
 *
 * @param string $url URL.
 * @return string Path.
 */
function sloc_url_path( $url ) {
	return (string) parse_url( $url, PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
}

/**
 * Extracts the one file in a zip archive with an extension.
 *
 * @param string $zip       Archive.
 * @param string $extension File extension, such as .txt.
 * @param string $dir       Directory to extract to.
 * @return string Extracted file.
 */
function sloc_unzip( $zip, $extension, $dir ) {
	$archive = new ZipArchive();
	if ( true !== $archive->open( $zip ) ) {
		fwrite( STDERR, "Could not open $zip\n" );
		exit( 1 );
	}
	for ( $i = 0; $i < $archive->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive property.
		$name = $archive->getNameIndex( $i );
		if ( substr( $name, -strlen( $extension ) ) === $extension && false === strpos( $name, '/' ) ) {
			$archive->extractTo( $dir, $name );
			$archive->close();
			return $dir . '/' . $name;
		}
	}
	fwrite( STDERR, "No $extension file in $zip\n" );
	exit( 1 );
}

/**
 * Builds a grid in data/geo/ from a GeoJSON file.
 *
 * @param string   $geojson GeoJSON file.
 * @param string   $prefix  Output path without extension.
 * @param callable $label   Names file entry for a feature's properties.
 */
function sloc_build_grid( $geojson, $prefix, $label ) {
	ini_set( 'memory_limit', '-1' ); // phpcs:ignore WordPress.PHP.IniSet.memory_limit_Disallowed -- The timezone boundaries are large.
	$start  = microtime( true );
	$counts = Sloc_Geo_Data_Builder::build_grid( json_decode( file_get_contents( $geojson ), true ), $label, $prefix );
	fwrite(
		STDOUT,
		sprintf(
			"Built %s: %d areas, %d uniform and %d border cells, %d KB, in %.0f s\n",
			$prefix,
			$counts['names'],
			$counts['uniform'],
			$counts['border'],
			( $counts['idx'] + $counts['dat'] ) / 1024,
			microtime( true ) - $start
		)
	);
}

/**
 * Builds the indexes of a CSV file in data/index/.
 *
 * @param string $data_dir Data directory.
 * @param string $csv      CSV file name, such as airports.csv.
 */
function sloc_build_indexes( $data_dir, $csv ) {
	if ( ! is_dir( $data_dir . '/index' ) ) {
		mkdir( $data_dir . '/index' );
	}
	foreach ( SLOC_INDEXES as $name => $index ) {
		if ( $csv !== $index[0] ) {
			continue;
		}
		$column = $index[1];
		if ( ! is_int( $column ) ) {
			$handle = fopen( $data_dir . '/' . $csv, 'r' );
			$column = array_search( $column, fgetcsv( $handle, 0, ',', '"', '' ), true );
			fclose( $handle );
		}
		$count = Sloc_Data_Index::build( $data_dir . '/' . $csv, $data_dir . '/index/' . $name . '.idx', $column, $index[2] );
		fwrite( STDOUT, "Indexed $count rows in $data_dir/index/$name.idx\n" );
	}
}

/**
 * Builds data/address-formats.json from OpenCage address-formatting at the commit described in a GitHub API answer.
 *
 * @param string $commit_file GitHub API answer for the branch's latest commit.
 * @param string $temp        Temporary directory.
 * @param string $file        Destination path.
 */
function sloc_build_addresses( $commit_file, $temp, $file ) {
	$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
	if ( ! is_readable( $autoload ) ) {
		fwrite( STDERR, "Run composer install first; the addresses source needs symfony/yaml.\n" );
		exit( 1 );
	}
	require_once $autoload;
	$commit = json_decode( (string) file_get_contents( $commit_file ), true );
	$sha    = is_array( $commit ) && is_string( $commit['sha'] ?? null ) && preg_match( '/^[0-9a-f]{40}$/', $commit['sha'] ) ? $commit['sha'] : '';
	if ( '' === $sha ) {
		fwrite( STDERR, "Could not read the address-formatting commit\n" );
		exit( 1 );
	}
	$parsed = array();
	foreach ( array(
		'worldwide' => 'conf/countries/worldwide.yaml',
		'states'    => 'conf/state_codes.yaml',
		'counties'  => 'conf/county_codes.yaml',
	) as $key => $path ) {
		$local = $temp . '/' . basename( $path );
		sloc_download( sprintf( SLOC_ADDRESS_FORMATTING_FILE, $sha, $path ), $local );
		$parsed[ $key ] = Symfony\Component\Yaml\Yaml::parseFile( $local );
		unlink( $local );
	}
	// Keep only region codes that are ISO 3166-2 codes in the plugin's own data.
	$iso = array();
	foreach ( glob( dirname( $file ) . '/iso_3166-2/*.json' ) as $iso_file ) {
		foreach ( (array) json_decode( (string) file_get_contents( $iso_file ), true ) as $subdivision ) {
			if ( is_array( $subdivision ) && isset( $subdivision['code'] ) && false !== strpos( $subdivision['code'], '-' ) ) {
				list( $country, $code ) = explode( '-', $subdivision['code'], 2 );
				$iso[ $country ][]      = $code;
			}
		}
	}
	$table = Sloc_Address_Format_Builder::build(
		$parsed['worldwide'],
		$parsed['states'],
		$parsed['counties'],
		array(
			'repository' => 'https://github.com/OpenCageData/address-formatting',
			'license'    => 'MIT',
			'commit'     => $sha,
			'date'       => (string) ( $commit['commit']['committer']['date'] ?? '' ),
		),
		$iso
	);
	file_put_contents( $file, json_encode( $table, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
	fwrite( STDOUT, sprintf( "Built %s with %d countries from address-formatting %s\n", $file, count( $table['countries'] ), substr( $sha, 0, 7 ) ) );
}

$sloc_selected = array_slice( $argv, 1 );
// "indexes" rebuilds the airport and airline indexes from the current files, without downloading.
if ( array( 'indexes' ) === $sloc_selected ) {
	sloc_build_indexes( $sloc_data_dir, 'airports.csv' );
	sloc_build_indexes( $sloc_data_dir, 'airlines.csv' );
	exit( 0 );
}
if ( empty( $sloc_selected ) ) {
	$sloc_selected = array_keys( $sloc_sources );
}
$sloc_unknown = array_diff( $sloc_selected, array_keys( $sloc_sources ) );
if ( $sloc_unknown ) {
	fwrite( STDERR, 'Unknown source: ' . implode( ', ', $sloc_unknown ) . '. Choose from: ' . implode( ', ', array_keys( $sloc_sources ) ) . "\n" );
	exit( 1 );
}

if ( ! is_dir( $sloc_data_dir . '/geo' ) ) {
	mkdir( $sloc_data_dir . '/geo' );
}

$sloc_temp = sys_get_temp_dir() . '/sloc-data-' . getmypid();
if ( ! is_dir( $sloc_temp ) && ! mkdir( $sloc_temp, 0700 ) ) {
	fwrite( STDERR, "Could not create $sloc_temp\n" );
	exit( 1 );
}

foreach ( $sloc_selected as $sloc_source ) {
	$sloc_download = $sloc_temp . '/' . basename( sloc_url_path( $sloc_sources[ $sloc_source ] ) );
	sloc_download( $sloc_sources[ $sloc_source ], $sloc_download );
	switch ( $sloc_source ) {
		case 'meteostat':
			sloc_build_meteostat( $sloc_download, $sloc_data_dir . '/meteostat-stations.csv' );
			break;
		case 'airports':
			copy( $sloc_download, $sloc_data_dir . '/airports.csv' );
			fwrite( STDOUT, "Updated $sloc_data_dir/airports.csv\n" );
			sloc_build_indexes( $sloc_data_dir, 'airports.csv' );
			break;
		case 'airlines':
			copy( $sloc_download, $sloc_data_dir . '/airlines.csv' );
			fwrite( STDOUT, "Updated $sloc_data_dir/airlines.csv\n" );
			sloc_build_indexes( $sloc_data_dir, 'airlines.csv' );
			break;
		case 'countries':
			sloc_build_grid( $sloc_download, $sloc_data_dir . '/geo/countries', array( 'Sloc_Geo_Data_Builder', 'country_label' ) );
			break;
		case 'timezones':
			$sloc_file = sloc_unzip( $sloc_download, '.json', $sloc_temp );
			sloc_build_grid( $sloc_file, $sloc_data_dir . '/geo/timezones', array( 'Sloc_Geo_Data_Builder', 'timezone_label' ) );
			unlink( $sloc_file );
			break;
		case 'addresses':
			sloc_build_addresses( $sloc_download, $sloc_temp, $sloc_data_dir . '/address-formats.json' );
			break;
		case 'places':
			$sloc_file   = sloc_unzip( $sloc_download, '.txt', $sloc_temp );
			$sloc_admin1 = $sloc_temp . '/admin1CodesASCII.txt';
			sloc_download( SLOC_GEONAMES_ADMIN1, $sloc_admin1 );
			$sloc_counts = Sloc_Geo_Data_Builder::build_places( $sloc_file, $sloc_admin1, $sloc_data_dir . '/geo' );
			fwrite( STDOUT, sprintf( "Built %s/geo/places.csv with %d places and regions.csv with %d regions\n", $sloc_data_dir, $sloc_counts['places'], $sloc_counts['regions'] ) );
			unlink( $sloc_file );
			unlink( $sloc_admin1 );
			break;
	}
	unlink( $sloc_download );
}
rmdir( $sloc_temp );

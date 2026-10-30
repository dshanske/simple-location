<?php
/**
 * Copies the Meteocons icons the plugin maps into svgs/meteocons/.
 *
 * Run with `composer update-meteocons`, or `php bin/update-meteocons.php [package-dir]`. Without a directory, the
 * pinned release of @meteocons/svg-static (https://github.com/basmilius/weather-icons, MIT) is downloaded from npm.
 * This runs outside WordPress, at development time; the plugin only reads the files it writes.
 *
 * Only the monochrome static icons named in data/meteocons.json are copied, so the plugin doesn't carry the whole set.
 * Each icon's IDs, which its masks and clip paths refer to, get the icon's name as a prefix, so icons shown together
 * on a page don't take each other's masks. The license is copied beside them.
 *
 * @package Simple_Location
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.DevelopmentFunctions, WordPress.Security.EscapeOutput -- Command-line build script, run outside WordPress.

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$sloc_version = '0.1.0';
$sloc_root    = dirname( __DIR__ );
$sloc_target  = $sloc_root . '/svgs/meteocons';
$sloc_map     = json_decode( (string) file_get_contents( $sloc_root . '/data/meteocons.json' ), true );
if ( ! is_array( $sloc_map ) ) {
	fwrite( STDERR, "data/meteocons.json is not a JSON object.\n" );
	exit( 1 );
}

$sloc_package = $argv[1] ?? '';
if ( '' === $sloc_package ) {
	$sloc_tmp     = sys_get_temp_dir() . '/sloc-meteocons-' . getmypid();
	$sloc_archive = $sloc_tmp . '.tgz';
	$sloc_url     = sprintf( 'https://registry.npmjs.org/@meteocons/svg-static/-/svg-static-%s.tgz', $sloc_version );
	echo "Downloading $sloc_url\n";
	if ( ! copy( $sloc_url, $sloc_archive ) ) {
		fwrite( STDERR, "Could not download $sloc_url\n" );
		exit( 1 );
	}
	( new PharData( $sloc_archive ) )->extractTo( $sloc_tmp, null, true );
	$sloc_package = $sloc_tmp . '/package';
}
$sloc_source = rtrim( $sloc_package, '/' ) . '/monochrome';
if ( ! is_dir( $sloc_source ) ) {
	fwrite( STDERR, "No monochrome icons in $sloc_package\n" );
	exit( 1 );
}

/**
 * Prepares an icon for inlining: without comments or titles, on one line, and with only the IDs its masks and clip
 * paths need, prefixed with its name.
 *
 * The mask type moves from a style to its attribute, which WordPress's allowed HTML keeps.
 *
 * @param string $svg  SVG.
 * @param string $name Icon name.
 * @return string SVG.
 */
function sloc_meteocons_prepare( $svg, $name ) {
	$prefix = 'mc-' . $name . '-';
	$svg    = preg_replace( '/<\?xml.*?\?>|<!--.*?-->|<title>.*?<\/title>/s', '', $svg );
	$svg    = preg_replace( '/\bstyle="mask-type:\s*(alpha|luminance);?"/', 'mask-type="$1"', $svg );
	preg_match_all( '/(?:url\(#|href="#)([^)"]+)/', $svg, $matches );
	$used = array_flip( $matches[1] );
	$svg  = preg_replace_callback(
		'/\sid="([^"]+)"/',
		fn( $match ) => isset( $used[ $match[1] ] ) ? $match[0] : '',
		$svg
	);
	$svg  = preg_replace( '/\bid="([^"]+)"/', 'id="' . $prefix . '$1"', $svg );
	$svg  = preg_replace( '/url\(#([^)]+)\)/', 'url(#' . $prefix . '$1)', $svg );
	$svg  = preg_replace( '/href="#([^"]+)"/', 'href="#' . $prefix . '$1"', $svg );
	return trim( preg_replace( '/>\s+</', '><', $svg ) ) . "\n";
}

if ( ! is_dir( $sloc_target ) ) {
	mkdir( $sloc_target, 0755, true );
}
foreach ( glob( $sloc_target . '/*.svg' ) as $sloc_old ) {
	unlink( $sloc_old );
}
$sloc_names = array_unique( array_values( $sloc_map ) );
sort( $sloc_names );
foreach ( $sloc_names as $sloc_name ) {
	$sloc_file = $sloc_source . '/' . basename( $sloc_name ) . '.svg';
	if ( ! is_file( $sloc_file ) ) {
		fwrite( STDERR, "Missing icon $sloc_name\n" );
		exit( 1 );
	}
	file_put_contents( $sloc_target . '/' . basename( $sloc_name ) . '.svg', sloc_meteocons_prepare( (string) file_get_contents( $sloc_file ), $sloc_name ) );
}
copy( rtrim( $sloc_package, '/' ) . '/LICENSE', $sloc_target . '/LICENSE' );
printf( "Copied %d icons to svgs/meteocons/.\n", count( $sloc_names ) );

<?php
/**
 * Builds JPEG files with chosen EXIF tags for tests.
 *
 * @package Simple_Location
 */

/**
 * Writes small JPEGs whose EXIF data (version, dates, offset, GPS) is set by the test, so
 * tests don't depend on binary fixtures.
 */
class Sloc_Test_Exif {

	/**
	 * TIFF field types used here.
	 */
	const BYTE      = 1;
	const ASCII     = 2;
	const LONG      = 4;
	const RATIONAL  = 5;
	const UNDEFINED = 7;

	/**
	 * Files created, removed by cleanup().
	 *
	 * @var string[]
	 */
	protected static $files = array();

	/**
	 * Whether the PHP extensions needed to build and read the files are loaded.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'exif_read_data' ) && function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagejpeg' );
	}

	/**
	 * Creates a JPEG with the given EXIF data.
	 *
	 * @param array $args {
	 *     EXIF values. All optional.
	 *
	 *     @type string $version   ExifVersion, e.g. '0232'.
	 *     @type string $original  DateTimeOriginal, 'Y:m:d H:i:s'.
	 *     @type string $digitized DateTimeDigitized, 'Y:m:d H:i:s'.
	 *     @type string $offset    OffsetTimeOriginal (tag 0x9011), e.g. '+02:00'.
	 *     @type float  $latitude  Signed decimal latitude.
	 *     @type float  $longitude Signed decimal longitude.
	 *     @type float  $altitude  Signed altitude in meters.
	 * }
	 * @return string Path to the file.
	 */
	public static function jpeg( $args = array() ) {
		$image = imagecreatetruecolor( 8, 8 );
		ob_start();
		imagejpeg( $image );
		$jpeg = ob_get_clean();

		$tiff = self::tiff( $args );
		$app1 = "Exif\0\0" . $tiff;
		// Insert the APP1 segment straight after the SOI marker.
		$jpeg = substr( $jpeg, 0, 2 ) . "\xFF\xE1" . pack( 'n', strlen( $app1 ) + 2 ) . $app1 . substr( $jpeg, 2 );

		$file = wp_tempnam( 'sloc-exif' ) . '.jpg';
		file_put_contents( $file, $jpeg ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		self::$files[] = $file;
		return $file;
	}

	/**
	 * Removes the files created.
	 */
	public static function cleanup() {
		foreach ( self::$files as $file ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}
		self::$files = array();
	}

	/**
	 * Builds a little-endian TIFF structure holding the EXIF and GPS IFDs.
	 *
	 * @param array $args See jpeg().
	 * @return string
	 */
	protected static function tiff( $args ) {
		$exif = array();
		if ( isset( $args['version'] ) ) {
			$exif[0x9000] = array( self::UNDEFINED, $args['version'] );
		}
		if ( isset( $args['original'] ) ) {
			$exif[0x9003] = array( self::ASCII, $args['original'] );
		}
		if ( isset( $args['digitized'] ) ) {
			$exif[0x9004] = array( self::ASCII, $args['digitized'] );
		}
		if ( isset( $args['offset'] ) ) {
			$exif[0x9011] = array( self::ASCII, $args['offset'] );
		}

		$gps = array();
		if ( isset( $args['latitude'] ) ) {
			$gps[0x0001] = array( self::ASCII, $args['latitude'] < 0 ? 'S' : 'N' );
			$gps[0x0002] = array( self::RATIONAL, self::dms( $args['latitude'] ) );
		}
		if ( isset( $args['longitude'] ) ) {
			$gps[0x0003] = array( self::ASCII, $args['longitude'] < 0 ? 'W' : 'E' );
			$gps[0x0004] = array( self::RATIONAL, self::dms( $args['longitude'] ) );
		}
		if ( isset( $args['altitude'] ) ) {
			$gps[0x0005] = array( self::BYTE, $args['altitude'] < 0 ? 1 : 0 );
			$gps[0x0006] = array( self::RATIONAL, array( array( (int) round( abs( $args['altitude'] ) * 100 ), 100 ) ) );
		}

		// IFD0 holds the pointers. Its size is known up front: two LONG entries at most, no data area.
		$ifd0_count = ( $exif ? 1 : 0 ) + ( $gps ? 1 : 0 );
		$offset     = 8 + 2 + 12 * $ifd0_count + 4;
		$ifd0       = array();
		$exif_ifd   = '';
		$gps_ifd    = '';
		if ( $exif ) {
			$ifd0[0x8769] = array( self::LONG, $offset );
			$exif_ifd     = self::ifd( $exif, $offset );
			$offset      += strlen( $exif_ifd );
		}
		if ( $gps ) {
			$ifd0[0x8825] = array( self::LONG, $offset );
			$gps_ifd      = self::ifd( $gps, $offset );
		}
		return "II\x2A\x00" . pack( 'V', 8 ) . self::ifd( $ifd0, 8 ) . $exif_ifd . $gps_ifd;
	}

	/**
	 * Encodes one IFD whose first byte will sit at $start in the TIFF structure.
	 *
	 * @param array $entries Tag => array( type, value ).
	 * @param int   $start   Offset of the IFD.
	 * @return string
	 */
	protected static function ifd( $entries, $start ) {
		ksort( $entries );
		$data_offset = $start + 2 + 12 * count( $entries ) + 4;
		$head        = pack( 'v', count( $entries ) );
		$data        = '';
		foreach ( $entries as $tag => $entry ) {
			list( $type, $value ) = $entry;
			switch ( $type ) {
				case self::ASCII:
					$bytes = $value . "\0";
					$count = strlen( $bytes );
					break;
				case self::UNDEFINED:
					$bytes = $value;
					$count = strlen( $bytes );
					break;
				case self::BYTE:
					$bytes = chr( $value );
					$count = 1;
					break;
				case self::LONG:
					$bytes = pack( 'V', $value );
					$count = 1;
					break;
				case self::RATIONAL:
					$bytes = '';
					foreach ( $value as $fraction ) {
						$bytes .= pack( 'VV', $fraction[0], $fraction[1] );
					}
					$count = count( $value );
					break;
			}
			$head .= pack( 'vvV', $tag, $type, $count );
			if ( strlen( $bytes ) <= 4 ) {
				$head .= str_pad( $bytes, 4, "\0" );
			} else {
				$head .= pack( 'V', $data_offset + strlen( $data ) );
				$data .= $bytes . ( strlen( $bytes ) % 2 ? "\0" : '' );
			}
		}
		return $head . pack( 'V', 0 ) . $data;
	}

	/**
	 * Converts a decimal coordinate to EXIF degree, minute, second rationals.
	 *
	 * @param float $coordinate Coordinate.
	 * @return array
	 */
	protected static function dms( $coordinate ) {
		$coordinate = abs( $coordinate );
		$degrees    = (int) floor( $coordinate );
		$minutes    = (int) floor( ( $coordinate - $degrees ) * 60 );
		$seconds    = ( $coordinate - $degrees - $minutes / 60 ) * 3600;
		return array( array( $degrees, 1 ), array( $minutes, 1 ), array( (int) round( $seconds * 10000 ), 10000 ) );
	}
}

<?php
/**
 * Metadata for Attachments
 *
 * Enhancements of metadata for Attachments
 *
 * @package Simple Location
 */

add_action( 'init', array( 'Sloc_Media_Metadata', 'init' ), 1 );

/**
 * Handles Media Metadata Enhancements
 *
 * @since 1.0.0
 */
class Sloc_Media_Metadata {

	/**
	 * Initialization Function.
	 *
	 * Meant to be attached to init hook. Sets up all the enhancements.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Grab geo data from EXIF, if it's available.
		$wp_version = get_bloginfo( 'version' );
		if ( version_compare( $wp_version, '5.0', '>=' ) ) {
			add_action( 'wp_read_image_metadata', array( __CLASS__, 'exif_data' ), 10, 5 );
		} else {
			add_action( 'wp_read_image_metadata', array( __CLASS__, 'exif_data' ), 10, 3 );
		}
	}

	/**
	 * Enhance EXIF data.
	 *
	 * The EXIF data extracted by WordPress By Default Does Not Include location data and the date information is incorrect.
	 *
	 * @param array  $meta Image Metadata.
	 * @param string $file Path to Image File.
	 * @param int    $image_type Type of Image.
	 * @param array  $iptc IPTC Data.
	 * @param array  $exif EXIF Data.
	 * @return array $meta Updated metadata.
	 *
	 * @since 1.0.0
	 */
	public static function exif_data( $meta, $file, $image_type, $iptc = null, $exif = null ) {
		if ( ! is_array( $exif ) && is_callable( 'exif_read_data' ) && in_array( $image_type, apply_filters( 'wp_read_image_metadata_types', array( IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM ) ), true ) ) {
			$exif = @exif_read_data( $file );
		}
		// If there is no Exif Version set return.
		if ( ! $exif['ExifVersion'] ) {
			return $meta;
		}
		$version = (int) $exif['ExifVersion'];
		// The changes between EXIF Versions mean different approaches are required.
		$meta['ExifVersion'] = sanitize_text_field( $exif['ExifVersion'] );
		if ( $version < 232 ) {
			// Prior to Version 232, GPS coordinates were stored in several fields.
			if ( ! empty( $exif['GPSLongitude'] ) && count( $exif['GPSLongitude'] ) === 3 && ! empty( $exif['GPSLongitudeRef'] ) ) {
				$meta['location']['longitude'] = round( ( 'W' === $exif['GPSLongitudeRef'] ? - 1 : 1 ) * wp_exif_gps_convert( $exif['GPSLongitude'] ), 7 );
			}
			if ( ! empty( $exif['GPSLatitude'] ) && count( $exif['GPSLatitude'] ) === 3 && ! empty( $exif['GPSLatitudeRef'] ) ) {
				$meta['location']['latitude'] = round( ( 'S' === $exif['GPSLatitudeRef'] ? - 1 : 1 ) * wp_exif_gps_convert( $exif['GPSLatitude'] ), 7 );
			}
			$datetime = null;
			if ( 231 === $version ) {
				// In Version 231 the timezone offset was stored in a separate field.
				foreach (
					array(
						'DateTimeOriginal'  => 'UndefinedTag:0x9011',
						'DateTimeDigitized' => 'UndefinedTag:0x9012',
					)
					as $time => $offset
				) {
					if ( ! empty( $exif[ $time ] ) && ! empty( $exif[ $offset ] ) ) {
						$datetime = wp_exif_datetime( $exif[ $time ], $exif[ $offset ] );
						break;
					}
				}
			} else {
				// Otherwise the timezone will be derived from the location.
				if ( ! empty( $meta['location'] ) ) {
					// Try to get the right timezone from the location.
					$timezone = Loc_Timezone::timezone_for_location( $meta['location']['latitude'], $meta['location']['longitude'] );
					if ( $timezone instanceof Timezone_Result ) {
						$timezone = $timezone->timezone;
					}
				} else {
					$timezone = wp_timezone();
				}
				if ( ! empty( $exif['DateTimeOriginal'] ) ) {
					$datetime = wp_exif_datetime( $exif['DateTimeOriginal'], $timezone );
				} elseif ( ! empty( $exif['DateTimeDigitized'] ) ) {
					$datetime = wp_exif_datetime( $exif['DateTimeDigitized'], $timezone );
				}
			}
			if ( $datetime ) {
				// By default WordPress sets a timestamp that is wrong because it does not factor in timezone. This issues a correct timestamp.
				$meta['created_timestamp'] = $datetime->getTimestamp();
				// Also stores an ISO8601 formatted string.
				$meta['created'] = $datetime->format( DATE_W3C );
			}
		} elseif ( 232 === $version ) {
			// As of Version 232, the timezone is stored along with the datetime.
			if ( ! empty( $exif['DateTimeOriginal'] ) ) {
				$datetime = new DateTime( $exif['DateTimeOriginal'] );
			} elseif ( ! empty( $exif['DateTimeDigitized'] ) ) {
				$datetime = new DateTime( $exif['DateTimeDigitized'] );
			}
			if ( $datetime ) {
				// By default WordPress sets a timestamp that is wrong because it does not factor in timezone. This issues a correct timestamp.
				$meta['created'] = $datetime->getTimestamp();
				// Also stores an ISO8601 formatted string.
				$meta['created_timestamp'] = $datetime->format( DATE_W3C );
			}
		}
		if ( ! empty( $exif['GPSAltitude'] ) ) {
			// Photos may also store an altitude.
			$meta['location']['altitude'] = wp_exif_frac2dec( $exif['GPSAltitude'] ) * ( 1 === $exif['GPSAltitudeRef'] ? -1 : 1 );
		}
		return $meta;
	}
}
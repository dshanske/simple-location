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
			add_filter( 'wp_read_image_metadata', array( __CLASS__, 'exif_data' ), 10, 5 );
		} else {
			add_filter( 'wp_read_image_metadata', array( __CLASS__, 'exif_data' ), 10, 3 );
		}

		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'attachment' ), 20, 2 );
		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'attachment_fields_to_edit' ), 10, 2 );
		add_action( 'attachment_submitbox_misc_actions', array( __CLASS__, 'attachment_submitbox_metadata' ), 12 );
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
		if ( ! array_key_exists( 'ExifVersion', $exif ) ) {
			return $meta;
		}
		$version = (int) $exif['ExifVersion'];
		// The changes between EXIF Versions mean different approaches are required.
		$meta['ExifVersion'] = sanitize_text_field( $exif['ExifVersion'] );
		if ( ! empty( $exif['GPSLongitude'] ) && count( $exif['GPSLongitude'] ) === 3 && ! empty( $exif['GPSLongitudeRef'] ) ) {
			$meta['location']['longitude'] = round( ( 'W' === $exif['GPSLongitudeRef'] ? - 1 : 1 ) * wp_exif_gps_convert( $exif['GPSLongitude'] ), 7 );
		}
		if ( ! empty( $exif['GPSLatitude'] ) && count( $exif['GPSLatitude'] ) === 3 && ! empty( $exif['GPSLatitudeRef'] ) ) {
			$meta['location']['latitude'] = round( ( 'S' === $exif['GPSLatitudeRef'] ? - 1 : 1 ) * wp_exif_gps_convert( $exif['GPSLatitude'] ), 7 );
		}
		$datetime = null;

		if ( $version <= 232 ) {
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

		if ( ! empty( $exif['GPSAltitude'] ) ) {
			// Photos may also store an altitude.
			$meta['location']['altitude'] = wp_exif_frac2dec( $exif['GPSAltitude'] ) * ( 1 === $exif['GPSAltitudeRef'] ? -1 : 1 );
		}
		return $meta;
	}

	/**
	 * Echos the created date to the attachment edit page.
	 *
	 * Adds the created date, if present to the attachment submit metabox.
	 *
	 * @param WP_Post $attachment The attachment.
	 *
	 * @since 1.0.0
	 */
	public static function attachment_submitbox_metadata( $attachment ) {
		$created = sloc_get_attachment_datetime( $attachment, 'created' );
		if ( $created ) {
			$created_on = sprintf(
			/* translators: Publish box date string. 1: Date, 2: Time. See https://secure.php.net/date */
				__( '%1$s at %2$s', 'simple-location' ),
				/* translators: Publish box date format, see https://secure.php.net/date */
				wp_date( _x( 'M j, Y', 'publish box date format', 'simple-location' ), $created->getTimestamp(), $created->getTimeZone() ),
				/* translators: Publish box time format, see https://secure.php.net/date */
				wp_date( _x( 'H:i T', 'publish box time format', 'simple-location' ), $created->getTimestamp(), $created->getTimeZone() )
			);
			echo '<div class="misc-pub-section curtime misc-pub-pubtime">';
			/* translators: Attachment information. %s: Date based on the timestamp in the attachment file. */
			echo wp_kses_post( sprintf( __( 'Created on: %s', 'simple-location' ), '<b>' . $created_on . '</b>' ) );
			echo '</div>';
		}
	}

	/**
	 * Displays the Latitude, Longitude, and Address Description on the Attachment Page.
	 *
	 * Adds the fields for location data from an attachment.
	 *
	 * @param array   $form_fields See attachment_fields_to_edit filter in WordPress.
	 * @param WP_Post $post Attachment post object.
	 * @return array $form_fields Updated with extra fields.
	 *
	 * @since 1.0.0
	 */
	public static function attachment_fields_to_edit( $form_fields, $post ) {
		$geodata                    = get_post_geodata( $post->ID );
		$form_fields['geo_address'] = array(
			'value'        => ifset( $geodata['address'] ),
			'label'        => __( 'Location', 'simple-location' ),
			'input'        => 'html',
			'show_in_edit' => false,
			'html'         => sprintf( '<span>%1$s</span>', ifset( $geodata['address'] ) ),
		);
		if ( isset( $geodata['latitude'] ) && isset( $geodata['longitude'] ) ) {
			$form_fields['location'] = array(
				'value'        => '',
				'label'        => __( 'Geo Coordinates', 'simple-location' ),
				'input'        => 'html',
				'show_in_edit' => false,
				'html'         => sprintf( '<span>%1$s, %2$s</span>', $geodata['latitude'], $geodata['longitude'] ),
			);
		}

		$time = get_post_meta( $post->ID, 'mf2_published', true );
		if ( $time ) {
			$form_fields['mf2_published'] = array(
				'value'        => $time,
				'label'        => __( 'Creation Time', 'simple-location' ),
				'input'        => 'html',
				'show_in_edit' => false,
				'html'         => sprintf( '<time dateime="%1$s" />%1$s</time><br />', $time ),
			);
		}
		return $form_fields;
	}


	/**
	 * Takes data from image meta and moves it to the appropriate keys in the attachments post meta.
	 *
	 * This includes moving the created date and location to their own keys and looking up the location and setting the address description.
	 *
	 * @param array $meta Image metadata.
	 * @param int   $post_id The attachment ID.
	 * @return array $meta The updated metadata.
	 *
	 * @since 1.0.0
	 */
	public static function attachment( $meta, $post_id ) {
		if ( ! isset( $meta['image_meta'] ) ) {
			return $meta;
		}

		$data   = $meta['image_meta'];
		$update = array();
		if ( isset( $data['created'] ) ) {
			$update['mf2_published'] = $data['created'];
		}

		if ( isset( $data['location'] ) ) {
			foreach ( array( 'latitude', 'longitude', 'altitude' ) as $prop ) {
				if ( array_key_exists( $prop, $data['location'] ) ) {
					$update[ 'geo_' . $prop ] = $data['location'][ $prop ];
				}
			}

			$venue = Post_Venue::at_venue( $update['geo_latitude'], $update['geo_longitude'] );
			if ( false !== $venue ) {
				update_post_meta( $args['ID'], 'venue_id', $venue );
				set_post_geodata( $args['ID'], 'visibility', 'protected' );
				$update['geo_address'] = get_the_title( $venue );
			} else {
				$reverse = Loc_Config::geo_provider();
				$reverse->set( $data['location']['latitude'], $data['location']['longitude'] );
				$reverse_adr = $reverse->reverse_lookup();
				if ( ! is_wp_error( $reverse_adr ) ) {
					$term   = Location_Taxonomy::get_location( $reverse_adr, true );
					Location_Taxonomy::set_location( $post_id, $term );
					if ( isset( $reverse_adr['display-name'] ) ) {
						$update['geo_address'] = $reverse_adr['display-name'];
					}
				}
				if ( ! array_key_exists( 'geo_altitude', $update ) ) {
					$update['geo_altitude'] = $reverse->elevation();
				}
				set_post_geodata( $post_id, 'visibility', 'public' );

			}

			$update = array_filter( $update );
			foreach ( $update as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}
		}

		return $meta;
	}
}

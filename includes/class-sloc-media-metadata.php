<?php
/**
 * Metadata for Attachments
 *
 * Enhancements of metadata for Attachments
 *
 * @package Simple Location
 */

defined( 'ABSPATH' ) || exit;

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
		add_filter( 'rest_prepare_attachment', array( __CLASS__, 'rest_prepare_attachment' ), 10, 3 );
	}

	/**
	 * Removes a photo's coordinates from its REST image metadata unless its location is public.
	 *
	 * Photos uploaded before 6.0.0 still have the GPS location in their stored image metadata,
	 * which core returns as media_details.image_meta to anyone who can view the attachment.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Response $response The response.
	 * @param WP_Post          $post     The attachment.
	 * @param WP_REST_Request  $request  The request.
	 * @return WP_REST_Response The response.
	 */
	public static function rest_prepare_attachment( $response, $post, $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Required by the filter.
		$data = $response->get_data();
		if ( ! isset( $data['media_details'] ) || ! is_array( $data['media_details'] ) ) {
			return $response;
		}
		if ( ! isset( $data['media_details']['image_meta'] ) || ! is_array( $data['media_details']['image_meta'] ) ) {
			return $response;
		}
		if ( ! array_key_exists( 'location', $data['media_details']['image_meta'] ) ) {
			return $response;
		}
		if ( 'public' !== get_post_geodata( $post->ID, 'visibility' ) ) {
			unset( $data['media_details']['image_meta']['location'] );
			$response->set_data( $data );
		}
		return $response;
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
	 * @since 6.0.0 Uses the offset stored in the photo whatever the EXIF version, and reads it under the tag
	 *               names current PHP gives it. Otherwise uses the timezone at the location, then the site's.
	 */
	public static function exif_data( $meta, $file, $image_type, $iptc = null, $exif = null ) {
		if ( ! is_array( $exif ) && is_callable( 'exif_read_data' ) && in_array( $image_type, apply_filters( 'wp_read_image_metadata_types', array( IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM ) ), true ) ) {
			// As in wp_read_image_metadata(), corrupt EXIF data raises warnings that aren't useful here.
			$exif = @exif_read_data( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		// If the EXIF data is missing or unreadable, or there is no Exif Version set, return.
		if ( ! is_array( $exif ) || ! array_key_exists( 'ExifVersion', $exif ) ) {
			return $meta;
		}
		$meta['ExifVersion'] = sanitize_text_field( $exif['ExifVersion'] );
		if ( ! empty( $exif['GPSLongitude'] ) && count( $exif['GPSLongitude'] ) === 3 && ! empty( $exif['GPSLongitudeRef'] ) ) {
			$meta['location']['longitude'] = round( ( 'W' === $exif['GPSLongitudeRef'] ? - 1 : 1 ) * wp_exif_gps_convert( $exif['GPSLongitude'] ), 7 );
		}
		if ( ! empty( $exif['GPSLatitude'] ) && count( $exif['GPSLatitude'] ) === 3 && ! empty( $exif['GPSLatitudeRef'] ) ) {
			$meta['location']['latitude'] = round( ( 'S' === $exif['GPSLatitudeRef'] ? - 1 : 1 ) * wp_exif_gps_convert( $exif['GPSLatitude'] ), 7 );
		}
		$datetime = null;

		// EXIF dates are local time. Since EXIF 2.31 the offset can be stored in tags 0x9011 and 0x9012, which
		// current PHP names OffsetTimeOriginal and OffsetTimeDigitized, and older PHP reports as unknown tags.
		foreach (
			array(
				'DateTimeOriginal'  => array( 'OffsetTimeOriginal', 'UndefinedTag:0x9011' ),
				'DateTimeDigitized' => array( 'OffsetTimeDigitized', 'UndefinedTag:0x9012' ),
			)
			as $time => $offsets
		) {
			if ( empty( $exif[ $time ] ) ) {
				continue;
			}
			foreach ( $offsets as $offset ) {
				if ( ! empty( $exif[ $offset ] ) && is_string( $exif[ $offset ] ) && preg_match( '/^[+-]\d{2}:\d{2}$/', trim( $exif[ $offset ] ) ) ) {
					$datetime = wp_exif_datetime( $exif[ $time ], trim( $exif[ $offset ] ) );
					break 2;
				}
			}
		}

		// Without an offset, use the timezone at the photo's location, or else the site's.
		if ( ! $datetime ) {
			$timezone = null;
			if ( isset( $meta['location']['latitude'], $meta['location']['longitude'] ) ) {
				$timezone = Loc_Timezone::timezone_for_location( $meta['location']['latitude'], $meta['location']['longitude'] );
				if ( $timezone instanceof Timezone_Result ) {
					$timezone = $timezone->timezone;
				}
			}
			if ( ! $timezone instanceof DateTimeZone ) {
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
	 * @since 6.0.0 Values are escaped.
	 */
	public static function attachment_fields_to_edit( $form_fields, $post ) {
		$geodata                    = get_post_geodata( $post->ID );
		$form_fields['geo_address'] = array(
			'value'        => ifset( $geodata['address'] ),
			'label'        => __( 'Location', 'simple-location' ),
			'input'        => 'html',
			'show_in_edit' => false,
			'html'         => sprintf( '<span>%1$s</span>', esc_html( ifset( $geodata['address'], '' ) ) ),
		);
		if ( isset( $geodata['latitude'] ) && isset( $geodata['longitude'] ) ) {
			$form_fields['location'] = array(
				'value'        => '',
				'label'        => __( 'Geo Coordinates', 'simple-location' ),
				'input'        => 'html',
				'show_in_edit' => false,
				'html'         => sprintf( '<span>%1$s, %2$s</span>', esc_html( $geodata['latitude'] ), esc_html( $geodata['longitude'] ) ),
			);
		}

		$time = get_post_meta( $post->ID, 'mf2_published', true );
		if ( $time ) {
			$form_fields['mf2_published'] = array(
				'value'        => $time,
				'label'        => __( 'Creation Time', 'simple-location' ),
				'input'        => 'html',
				'show_in_edit' => false,
				'html'         => sprintf( '<time dateime="%1$s" />%2$s</time><br />', esc_attr( $time ), esc_html( $time ) ),
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
	 * @since 6.0.0 The location is removed from the image metadata, and no visibility is stored. The creation date is
	 *               saved for photos without a location.
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

		if ( isset( $data['location']['latitude'], $data['location']['longitude'] ) ) {
			foreach ( array( 'latitude', 'longitude', 'altitude' ) as $prop ) {
				if ( array_key_exists( $prop, $data['location'] ) ) {
					$update[ 'geo_' . $prop ] = $data['location'][ $prop ];
				}
			}

			// No visibility is stored: the photo inherits it (see Geo_Data::get_attachment_visibility()).
			$venue = Post_Venue::at_venue( $update['geo_latitude'], $update['geo_longitude'] );
			if ( false !== $venue ) {
				update_post_meta( $post_id, 'venue_id', $venue );
				$update['geo_address'] = get_the_title( $venue );
			} else {
				$reverse     = Loc_Config::geo_provider();
				$reverse_adr = null;
				if ( is_object( $reverse ) ) {
					$reverse->set( $data['location']['latitude'], $data['location']['longitude'] );
					$reverse_adr = $reverse->reverse_lookup();
				}
				if ( is_array( $reverse_adr ) ) {
					$term = Location_Taxonomy::get_location( $reverse_adr, true );
					Location_Taxonomy::set_location( $post_id, $term );
					if ( isset( $reverse_adr['display-name'] ) ) {
						$update['geo_address'] = $reverse_adr['display-name'];
					}
				}
				if ( ! array_key_exists( 'geo_altitude', $update ) && is_object( $reverse ) ) {
					$elevation = $reverse->elevation();
					// Store only a number, never an error from the elevation lookup.
					if ( is_numeric( $elevation ) ) {
						$update['geo_altitude'] = $elevation;
					}
				}
			}

			// The location now lives in the geo fields, where its visibility applies; don't also keep it in the image metadata,
			// which core exposes to anyone, for example in the REST API.
			unset( $meta['image_meta']['location'] );
		}

		// The creation date is saved whether or not the photo has a location.
		$update = array_filter( $update );
		foreach ( $update as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return $meta;
	}
}

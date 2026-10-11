<?php
/**
 * Tests for photo metadata: EXIF location and dates, saving them to attachments, and the
 * attachment editor fields in includes/class-sloc-media-metadata.php.
 *
 * @package Simple_Location
 */

/**
 * Media metadata tests.
 */
class MediaMetadataTest extends WP_UnitTestCase {

	/**
	 * Use the fake geo provider and a known site timezone.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'sloc_geo_provider', 'sloc_test' );
		update_option( 'timezone_string', 'Europe/London' );
		Geo_Provider_Sloc_Test::reset();
	}

	/**
	 * Remove generated images.
	 */
	public function tear_down() {
		Sloc_Test_Exif::cleanup();
		parent::tear_down();
	}

	/**
	 * Skips tests that need the exif and gd extensions.
	 */
	protected function require_exif() {
		if ( ! Sloc_Test_Exif::available() ) {
			$this->markTestSkipped( 'Requires the exif and gd PHP extensions.' );
		}
	}

	/**
	 * Runs exif_data() the way core does, for a generated JPEG.
	 *
	 * @param array $args EXIF values, see Sloc_Test_Exif::jpeg().
	 * @return array Image metadata.
	 */
	protected function read( $args ) {
		$this->require_exif();
		$file = Sloc_Test_Exif::jpeg( $args );
		return Sloc_Media_Metadata::exif_data( array(), $file, IMAGETYPE_JPEG, array(), exif_read_data( $file ) );
	}

	/**
	 * Creates an attachment with the given image metadata.
	 *
	 * @param array $image_meta Image metadata.
	 * @return int Attachment ID.
	 */
	protected function attachment_with_meta( $image_meta ) {
		$attachment_id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		wp_update_attachment_metadata( $attachment_id, array( 'image_meta' => $image_meta ) );
		return $attachment_id;
	}

	/**
	 * A sample reverse lookup with a full address.
	 */
	protected function full_address() {
		Geo_Provider_Sloc_Test::$reverse = array(
			'display-name' => 'Lower Manhattan, New York',
			'country-name' => 'United States',
			'country-code' => 'US',
			'region'       => 'New York',
			'region-code'  => 'NY',
			'locality'     => 'New York',
		);
	}

	/**
	 * The filters are registered.
	 */
	public function test_hooks_registered() {
		$this->assertSame( 10, has_filter( 'rest_prepare_attachment', array( 'Sloc_Media_Metadata', 'rest_prepare_attachment' ) ) );
		$this->assertSame( 10, has_filter( 'wp_read_image_metadata', array( 'Sloc_Media_Metadata', 'exif_data' ) ) );
		$this->assertSame( 20, has_filter( 'wp_generate_attachment_metadata', array( 'Sloc_Media_Metadata', 'attachment' ) ) );
		$this->assertSame( 10, has_filter( 'attachment_fields_to_edit', array( 'Sloc_Media_Metadata', 'attachment_fields_to_edit' ) ) );
	}

	/**
	 * Metadata is unchanged when there is no EXIF version.
	 */
	public function test_exif_data_without_exif() {
		$meta = array( 'title' => 'Photo' );
		$this->assertSame( $meta, Sloc_Media_Metadata::exif_data( $meta, '', IMAGETYPE_JPEG, array(), array() ) );
	}

	/**
	 * Metadata is unchanged when the EXIF data is missing or unreadable, rather than failing.
	 */
	public function test_exif_data_not_an_array() {
		$meta = array( 'title' => 'Photo' );
		$this->assertSame( $meta, Sloc_Media_Metadata::exif_data( $meta, '', IMAGETYPE_PNG, array(), null ) );
		$this->assertSame( $meta, Sloc_Media_Metadata::exif_data( $meta, __FILE__, IMAGETYPE_JPEG ) );
	}

	/**
	 * GPS coordinates are converted to signed decimals.
	 *
	 * @dataProvider data_coordinates
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 */
	public function test_exif_data_coordinates( $latitude, $longitude ) {
		$meta = $this->read(
			array(
				'version'   => '0300',
				'latitude'  => $latitude,
				'longitude' => $longitude,
			)
		);
		$this->assertSame( '0300', $meta['ExifVersion'] );
		$this->assertEqualsWithDelta( $latitude, $meta['location']['latitude'], 0.000001 );
		$this->assertEqualsWithDelta( $longitude, $meta['location']['longitude'], 0.000001 );
	}

	/**
	 * Data provider of coordinates in each hemisphere.
	 *
	 * @return array
	 */
	public function data_coordinates() {
		return array(
			'north west' => array( 40.7128, -74.006 ),
			'south east' => array( -33.8688, 151.2093 ),
		);
	}

	/**
	 * GPS altitude above sea level is read.
	 */
	public function test_exif_data_altitude() {
		$meta = $this->read(
			array(
				'version'   => '0300',
				'latitude'  => 40.7128,
				'longitude' => -74.006,
				'altitude'  => 12.5,
			)
		);
		$this->assertEquals( 12.5, $meta['location']['altitude'] );
	}

	/**
	 * GPS altitude below sea level is negative.
	 */
	public function test_exif_data_altitude_below_sea_level() {
		$this->markTestSkipped( 'Known bug, see #308' );
		$meta = $this->read(
			array(
				'version'   => '0300',
				'latitude'  => 31.5,
				'longitude' => 35.5,
				'altitude'  => -430,
			)
		);
		$this->assertEquals( -430, $meta['location']['altitude'] );
	}

	/**
	 * Before EXIF 2.32 the offset is read from tag 0x9011 when PHP reports it as an unknown tag.
	 */
	public function test_exif_data_legacy_offset_tag() {
		$meta = Sloc_Media_Metadata::exif_data(
			array(),
			'',
			IMAGETYPE_JPEG,
			array(),
			array(
				'ExifVersion'         => '0231',
				'DateTimeOriginal'    => '2024:07:01 12:00:00',
				'UndefinedTag:0x9011' => '+02:00',
			)
		);
		$this->assertSame( '2024-07-01T12:00:00+02:00', $meta['created'] );
		$this->assertSame( 1719828000, $meta['created_timestamp'] );
	}

	/**
	 * The date uses the offset stored in the photo, under the tag name current PHP gives it.
	 */
	public function test_exif_data_offset() {
		$this->markTestSkipped( 'Known bug, see #323' );
		$meta = $this->read(
			array(
				'version'  => '0232',
				'original' => '2024:07:01 12:00:00',
				'offset'   => '+02:00',
			)
		);
		$this->assertSame( '2024-07-01T12:00:00+02:00', $meta['created'] );
	}

	/**
	 * Without an offset, the date uses the timezone at the photo's location.
	 */
	public function test_exif_data_timezone_from_location() {
		$this->markTestSkipped( 'Known bug, see #323' );
		$meta = $this->read(
			array(
				'version'   => '0232',
				'original'  => '2024:07:01 12:00:00',
				'latitude'  => 40.7128,
				'longitude' => -74.006,
			)
		);
		$this->assertSame( '2024-07-01T12:00:00-04:00', $meta['created'] );
	}

	/**
	 * After EXIF 2.32 the date uses the timezone at the photo's location.
	 */
	public function test_exif_data_newer_version_timezone_from_location() {
		$meta = $this->read(
			array(
				'version'   => '0300',
				'original'  => '2024:07:01 12:00:00',
				'latitude'  => 40.7128,
				'longitude' => -74.006,
			)
		);
		$this->assertSame( '2024-07-01T12:00:00-04:00', $meta['created'] );
		$this->assertSame( strtotime( '2024-07-01T16:00:00Z' ), $meta['created_timestamp'] );
	}

	/**
	 * Without a location, or far from any known place, the site timezone is used.
	 */
	public function test_exif_data_site_timezone() {
		$meta = $this->read(
			array(
				'version'  => '0300',
				'original' => '2024:07:01 12:00:00',
			)
		);
		$this->assertSame( '2024-07-01T12:00:00+01:00', $meta['created'] );

		$meta = $this->read(
			array(
				'version'   => '0300',
				'original'  => '2024:07:01 12:00:00',
				'latitude'  => 0.5,
				'longitude' => -140.5,
			)
		);
		$this->assertSame( '2024-07-01T12:00:00+01:00', $meta['created'] );
	}

	/**
	 * The digitized date is used when there is no original date.
	 */
	public function test_exif_data_digitized_date() {
		$meta = $this->read(
			array(
				'version'   => '0300',
				'digitized' => '2024:01:15 08:30:00',
			)
		);
		$this->assertSame( '2024-01-15T08:30:00+00:00', $meta['created'] );
	}

	/**
	 * Without image metadata nothing happens.
	 */
	public function test_attachment_without_image_meta() {
		$meta = array( 'width' => 10 );
		$this->assertSame( $meta, Sloc_Media_Metadata::attachment( $meta, 1 ) );
	}

	/**
	 * The created date is copied to mf2_published.
	 */
	public function test_attachment_created_date() {
		$this->markTestSkipped( 'Known bug, see #323' );
		$attachment_id = self::factory()->attachment->create();
		Sloc_Media_Metadata::attachment( array( 'image_meta' => array( 'created' => '2024-07-01T12:00:00-04:00' ) ), $attachment_id );
		$this->assertSame( '2024-07-01T12:00:00-04:00', get_post_meta( $attachment_id, 'mf2_published', true ) );
		$this->assertSame( '', get_post_meta( $attachment_id, 'geo_latitude', true ) );
	}

	/**
	 * A photo location is saved with the address, location term, and elevation from the geo provider.
	 */
	public function test_attachment_location() {
		$this->full_address();
		$attachment_id = self::factory()->attachment->create();
		$meta          = array(
			'image_meta' => array(
				'location' => array(
					'latitude'  => 40.7128,
					'longitude' => -74.006,
				),
			),
		);
		$this->assertSame( array( 'image_meta' => array() ), Sloc_Media_Metadata::attachment( $meta, $attachment_id ), 'The location is moved out of the image metadata.' );

		$geodata = get_post_geodata( $attachment_id );
		$this->assertEquals( 40.7128, $geodata['latitude'] );
		$this->assertEquals( -74.006, $geodata['longitude'] );
		$this->assertEquals( 50, $geodata['altitude'], 'Elevation comes from the geo provider when the photo has none.' );
		$this->assertSame( 'Lower Manhattan, New York', $geodata['address'] );
		$this->assertSame( array( 40.7128, -74.006 ), Geo_Provider_Sloc_Test::$last );

		$terms = wp_get_object_terms( $attachment_id, 'location' );
		$this->assertCount( 1, $terms );
		$this->assertSame( 'New York', $terms[0]->name );
	}

	/**
	 * An altitude from the photo is kept, and a failed reverse lookup leaves the address empty.
	 */
	public function test_attachment_location_lookup_error() {
		Geo_Provider_Sloc_Test::$reverse = new WP_Error( 'lookup_failed', 'Lookup failed' );
		$attachment_id                   = self::factory()->attachment->create();
		Sloc_Media_Metadata::attachment(
			array(
				'image_meta' => array(
					'location' => array(
						'latitude'  => 40.7128,
						'longitude' => -74.006,
						'altitude'  => 12.5,
					),
				),
			),
			$attachment_id
		);
		$this->assertEquals( 12.5, get_post_meta( $attachment_id, 'geo_altitude', true ) );
		$this->assertSame( '', get_post_meta( $attachment_id, 'geo_address', true ) );
		$this->assertSame( array(), wp_get_object_terms( $attachment_id, 'location' ) );
	}

	/**
	 * Saves the sample photo location for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	protected function save_photo_location( $attachment_id ) {
		Sloc_Media_Metadata::attachment(
			array(
				'image_meta' => array(
					'location' => array(
						'latitude'  => 40.7128,
						'longitude' => -74.006,
					),
				),
			),
			$attachment_id
		);
	}

	/**
	 * Creates a venue and makes at_venue() report it for any location.
	 *
	 * @param string $visibility Venue visibility.
	 * @return int Venue ID.
	 */
	protected function at_venue( $visibility ) {
		$venue_id = self::factory()->post->create(
			array(
				'post_type'  => 'venue',
				'post_title' => 'Corner Cafe',
			)
		);
		set_post_geodata( $venue_id, 'visibility', $visibility );
		add_filter(
			'pre_at_venue',
			function () use ( $venue_id ) {
				return $venue_id;
			}
		);
		return $venue_id;
	}

	/**
	 * A photo that isn't attached to a post, away from a private venue, uses the site's default visibility.
	 *
	 * @dataProvider data_default_visibility
	 *
	 * @param int    $option     Value of the geo_public option.
	 * @param string $visibility Expected visibility.
	 */
	public function test_attachment_location_default_visibility( $option, $visibility ) {
		$this->full_address();
		update_option( 'geo_public', $option );
		$attachment_id = self::factory()->attachment->create();
		$this->save_photo_location( $attachment_id );
		$this->assertSame( '', get_post_meta( $attachment_id, 'geo_public', true ), 'No visibility is stored on upload.' );
		$this->assertSame( $visibility, get_post_geodata( $attachment_id, 'visibility' ) );
	}

	/**
	 * Data provider of site defaults.
	 *
	 * @return array
	 */
	public function data_default_visibility() {
		return array(
			'public'    => array( 1, 'public' ),
			'protected' => array( 2, 'protected' ),
			'private'   => array( 0, 'private' ),
		);
	}

	/**
	 * A photo attached to a post inherits the post's visibility, and follows it when it changes.
	 */
	public function test_attachment_inherits_parent_visibility() {
		$this->full_address();
		update_option( 'geo_public', 1 );
		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->attachment->create( array( 'post_parent' => $post_id ) );
		$this->save_photo_location( $attachment_id );

		foreach ( array( 'private', 'protected', 'public' ) as $visibility ) {
			set_post_geodata( $post_id, 'visibility', $visibility );
			$this->assertSame( $visibility, get_post_geodata( $attachment_id, 'visibility' ), "Post is $visibility" );
		}

		// A post with no visibility of its own uses the site default, and so does the photo.
		set_post_geodata( $post_id, 'visibility', 'inherit' );
		update_option( 'geo_public', 0 );
		$this->assertSame( 'private', get_post_geodata( $attachment_id, 'visibility' ) );
	}

	/**
	 * A visibility set on the photo itself wins over the post's, and "inherit" removes it again.
	 */
	public function test_attachment_own_visibility() {
		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->attachment->create( array( 'post_parent' => $post_id ) );
		set_post_geodata( $post_id, 'visibility', 'public' );

		set_post_geodata( $attachment_id, 'visibility', 'private' );
		$this->assertSame( 'private', get_post_geodata( $attachment_id, 'visibility' ) );

		$this->assertTrue( set_post_geodata( $attachment_id, 'visibility', 'inherit' ) );
		$this->assertSame( '', get_post_meta( $attachment_id, 'geo_public', true ) );
		$this->assertSame( 'public', get_post_geodata( $attachment_id, 'visibility' ) );
	}

	/**
	 * At a private venue, an inherited public visibility becomes protected, but a stricter one is kept.
	 */
	public function test_attachment_private_venue_with_parent() {
		$this->at_venue( 'private' );
		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->attachment->create( array( 'post_parent' => $post_id ) );
		$this->save_photo_location( $attachment_id );

		$expected = array(
			'public'    => 'protected',
			'protected' => 'protected',
			'private'   => 'private',
		);
		foreach ( $expected as $parent => $visibility ) {
			set_post_geodata( $post_id, 'visibility', $parent );
			$this->assertSame( $visibility, get_post_geodata( $attachment_id, 'visibility' ), "Post is $parent" );
		}
	}

	/**
	 * A photo taken at a private venue is linked to the venue, named after it, and protected,
	 * so the venue name shows but the coordinates don't.
	 */
	public function test_attachment_at_private_venue() {
		update_option( 'geo_public', 1 );
		$venue_id      = $this->at_venue( 'private' );
		$attachment_id = self::factory()->attachment->create();
		$this->save_photo_location( $attachment_id );
		$this->assertEquals( $venue_id, get_post_meta( $attachment_id, 'venue_id', true ) );
		$this->assertSame( 'Corner Cafe', get_post_meta( $attachment_id, 'geo_address', true ) );
		$this->assertSame( 'protected', get_post_geodata( $attachment_id, 'visibility' ) );
	}

	/**
	 * A photo taken at a venue that isn't private is linked to it and uses the site's default visibility.
	 */
	public function test_attachment_at_public_venue() {
		update_option( 'geo_public', 1 );
		$venue_id      = $this->at_venue( 'public' );
		$attachment_id = self::factory()->attachment->create();
		$this->save_photo_location( $attachment_id );
		$this->assertEquals( $venue_id, get_post_meta( $attachment_id, 'venue_id', true ) );
		$this->assertSame( 'public', get_post_geodata( $attachment_id, 'visibility' ) );
	}

	/**
	 * Fetches an attachment from the REST API as a visitor.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Response data.
	 */
	protected function rest_media( $attachment_id ) {
		wp_set_current_user( 0 );
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id ) );
		$this->assertSame( 200, $response->get_status() );
		return $response->get_data();
	}

	/**
	 * Creates an attachment of a published post with GPS coordinates left in its image metadata,
	 * as photos uploaded before 5.0.26 have.
	 *
	 * @param string $visibility Visibility of the post.
	 * @return int Attachment ID.
	 */
	protected function photo_with_stored_location( $visibility ) {
		$post_id = self::factory()->post->create();
		set_post_geodata( $post_id, 'visibility', $visibility );
		$attachment_id = self::factory()->attachment->create(
			array(
				'post_parent'    => $post_id,
				'post_mime_type' => 'image/jpeg',
			)
		);
		update_post_meta( $attachment_id, 'geo_latitude', 40.7128 );
		update_post_meta( $attachment_id, 'geo_longitude', -74.006 );
		wp_update_attachment_metadata(
			$attachment_id,
			array(
				'width'      => 10,
				'height'     => 10,
				'file'       => 'photo.jpg',
				'sizes'      => array(),
				'image_meta' => array(
					'camera'   => 'Phone',
					'location' => array(
						'latitude'  => 40.7128,
						'longitude' => -74.006,
					),
				),
			)
		);
		return $attachment_id;
	}

	/**
	 * The REST API doesn't show visitors the coordinates in a photo's image metadata unless its location is public.
	 */
	public function test_rest_hides_image_meta_location() {
		foreach ( array( 'private', 'protected' ) as $visibility ) {
			$image_meta = $this->rest_media( $this->photo_with_stored_location( $visibility ) )['media_details']['image_meta'];
			$this->assertArrayNotHasKey( 'location', $image_meta, $visibility );
			$this->assertSame( 'Phone', $image_meta['camera'], 'The rest of the image metadata is kept.' );
		}

		$image_meta = $this->rest_media( $this->photo_with_stored_location( 'public' ) )['media_details']['image_meta'];
		$this->assertSame(
			array(
				'latitude'  => 40.7128,
				'longitude' => -74.006,
			),
			$image_meta['location']
		);
	}

	/**
	 * The REST filter leaves responses without image metadata alone.
	 */
	public function test_rest_prepare_attachment_without_image_meta() {
		$attachment_id = self::factory()->attachment->create( array( 'post_mime_type' => 'application/pdf' ) );
		$response      = new WP_REST_Response( array( 'media_details' => new stdClass() ) );
		$this->assertSame( $response, Sloc_Media_Metadata::rest_prepare_attachment( $response, get_post( $attachment_id ), new WP_REST_Request() ) );
		$response = new WP_REST_Response( array( 'media_details' => array( 'image_meta' => array( 'camera' => 'Phone' ) ) ) );
		$this->assertSame( array( 'camera' => 'Phone' ), Sloc_Media_Metadata::rest_prepare_attachment( $response, get_post( $attachment_id ), new WP_REST_Request() )->get_data()['media_details']['image_meta'] );
	}

	/**
	 * A photo location is saved even when no geo provider is set.
	 */
	public function test_attachment_without_geo_provider() {
		update_option( 'sloc_geo_provider', 'none' );
		$attachment_id = self::factory()->attachment->create();
		Sloc_Media_Metadata::attachment(
			array(
				'image_meta' => array(
					'location' => array(
						'latitude'  => 40.7128,
						'longitude' => -74.006,
					),
				),
			),
			$attachment_id
		);
		$this->assertEquals( 40.7128, get_post_meta( $attachment_id, 'geo_latitude', true ) );
	}

	/**
	 * Uploading a photo with GPS data stores its location and created date on the attachment.
	 */
	public function test_upload() {
		$this->require_exif();
		$this->full_address();
		$file          = Sloc_Test_Exif::jpeg(
			array(
				'version'   => '0300',
				'original'  => '2024:07:01 12:00:00',
				'latitude'  => 40.7128,
				'longitude' => -74.006,
				'altitude'  => 10,
			)
		);
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		$this->assertIsInt( $attachment_id );

		$geodata = get_post_geodata( $attachment_id );
		$this->assertEqualsWithDelta( 40.7128, $geodata['latitude'], 0.000001 );
		$this->assertEqualsWithDelta( -74.006, $geodata['longitude'], 0.000001 );
		$this->assertEquals( 10, $geodata['altitude'] );
		$this->assertSame( 'Lower Manhattan, New York', $geodata['address'] );
		$this->assertArrayNotHasKey( 'location', wp_get_attachment_metadata( $attachment_id )['image_meta'], 'Coordinates are not kept in the image metadata.' );
		$this->assertSame( '2024-07-01T12:00:00-04:00', get_post_meta( $attachment_id, 'mf2_published', true ) );

		$created = sloc_get_attachment_datetime( $attachment_id );
		$this->assertSame( '2024-07-01T12:00:00-04:00', $created->format( DATE_W3C ) );
	}

	/**
	 * The attachment editor shows the address, coordinates, and creation time.
	 */
	public function test_attachment_fields_to_edit() {
		$attachment = self::factory()->attachment->create_and_get();
		set_post_geodata(
			$attachment->ID,
			'',
			array(
				'latitude'  => 40.7128,
				'longitude' => -74.006,
				'address'   => 'Lower Manhattan',
			)
		);
		update_post_meta( $attachment->ID, 'mf2_published', '2024-07-01T12:00:00-04:00' );

		$fields = Sloc_Media_Metadata::attachment_fields_to_edit( array(), $attachment );
		$this->assertSame( 'Lower Manhattan', $fields['geo_address']['value'] );
		$this->assertStringContainsString( 'Lower Manhattan', $fields['geo_address']['html'] );
		$this->assertStringContainsString( '40.7128, -74.006', $fields['location']['html'] );
		$this->assertStringContainsString( '2024-07-01T12:00:00-04:00', $fields['mf2_published']['html'] );
	}

	/**
	 * Without a location only the empty address field is added.
	 */
	public function test_attachment_fields_to_edit_without_location() {
		$attachment = self::factory()->attachment->create_and_get();
		$fields     = Sloc_Media_Metadata::attachment_fields_to_edit( array(), $attachment );
		$this->assertSame( array( 'geo_address' ), array_keys( $fields ) );
	}

	/**
	 * The attachment editor fields escape the stored values.
	 */
	public function test_attachment_fields_to_edit_escaped() {
		$this->markTestSkipped( 'Known bug, see #322' );
		$attachment = self::factory()->attachment->create_and_get();
		update_post_meta( $attachment->ID, 'geo_address', '<script>alert(1)</script>' );
		update_post_meta( $attachment->ID, 'mf2_published', '"><script>alert(2)</script>' );
		$fields = Sloc_Media_Metadata::attachment_fields_to_edit( array(), $attachment );
		$this->assertStringNotContainsString( '<script>', $fields['geo_address']['html'] );
		$this->assertStringNotContainsString( '<script>', $fields['mf2_published']['html'] );
	}

	/**
	 * The creation time is valid markup with a datetime attribute.
	 */
	public function test_attachment_fields_to_edit_time_markup() {
		$this->markTestSkipped( 'Known bug, see #308' );
		$attachment = self::factory()->attachment->create_and_get();
		update_post_meta( $attachment->ID, 'mf2_published', '2024-07-01T12:00:00-04:00' );
		$fields = Sloc_Media_Metadata::attachment_fields_to_edit( array(), $attachment );
		$this->assertStringContainsString( '<time datetime="2024-07-01T12:00:00-04:00">', $fields['mf2_published']['html'] );
	}

	/**
	 * The coordinates field doesn't reuse the location taxonomy's field key.
	 */
	public function test_attachment_fields_to_edit_key() {
		$this->markTestSkipped( 'Known bug, see #295' );
		$attachment = self::factory()->attachment->create_and_get();
		set_post_geodata(
			$attachment->ID,
			'',
			array(
				'latitude'  => 40.7128,
				'longitude' => -74.006,
			)
		);
		$fields = Sloc_Media_Metadata::attachment_fields_to_edit( array( 'location' => array( 'label' => 'Locations' ) ), $attachment );
		$this->assertArrayNotHasKey( 'location', $fields );
	}

	/**
	 * The publish box shows the created date in the photo's own timezone.
	 */
	public function test_attachment_submitbox_metadata() {
		$attachment_id = $this->attachment_with_meta( array( 'created' => '2024-07-01T12:00:00-04:00' ) );
		ob_start();
		Sloc_Media_Metadata::attachment_submitbox_metadata( get_post( $attachment_id ) );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Created on:', $output );
		$this->assertStringContainsString( 'Jul 1, 2024', $output );
		$this->assertStringContainsString( '12:00', $output );
	}

	/**
	 * The publish box shows nothing without a created date.
	 */
	public function test_attachment_submitbox_metadata_without_date() {
		$attachment_id = $this->attachment_with_meta( array() );
		ob_start();
		Sloc_Media_Metadata::attachment_submitbox_metadata( get_post( $attachment_id ) );
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * Created dates are read from the ISO string, or else from the timestamp.
	 */
	public function test_sloc_get_attachment_datetime_created() {
		$attachment_id = $this->attachment_with_meta( array( 'created' => '2024-07-01T12:00:00-04:00' ) );
		$this->assertSame( '2024-07-01T12:00:00-04:00', sloc_get_attachment_datetime( $attachment_id )->format( DATE_W3C ) );

		$attachment_id = $this->attachment_with_meta( array( 'created_timestamp' => 1719849600 ) );
		$created       = sloc_get_attachment_datetime( $attachment_id, 'created' );
		$this->assertSame( 1719849600, $created->getTimestamp() );
		$this->assertSame( 'Europe/London', $created->getTimezone()->getName() );

		$this->assertFalse( sloc_get_attachment_datetime( $this->attachment_with_meta( array( 'created_timestamp' => 0 ) ) ) );
		$this->assertFalse( sloc_get_attachment_datetime( self::factory()->attachment->create() ) );
	}

	/**
	 * Upload and modified dates come from the post, in the site timezone.
	 */
	public function test_sloc_get_attachment_datetime_post_dates() {
		$attachment_id = self::factory()->attachment->create(
			array(
				'post_date'     => '2024-07-01 12:00:00',
				'post_modified' => '2024-07-02 13:00:00',
			)
		);
		$this->assertSame( '2024-07-01T12:00:00+01:00', sloc_get_attachment_datetime( $attachment_id, 'date' )->format( DATE_W3C ) );
		$this->assertInstanceOf( DateTimeImmutable::class, sloc_get_attachment_datetime( $attachment_id, 'modified' ) );
	}

	/**
	 * Non-attachments and missing posts return false.
	 */
	public function test_sloc_get_attachment_datetime_invalid() {
		$this->assertFalse( sloc_get_attachment_datetime( self::factory()->post->create() ) );
		$this->assertFalse( sloc_get_attachment_datetime( 999999 ) );
	}
}

<?php
/**
 * Tests for Plus Codes (#197).
 *
 * @package Simple_Location
 */

/**
 * Plus Code tests.
 */
class PlusCodeTest extends WP_UnitTestCase {

	/**
	 * Test provider responses, and an editor.
	 */
	public function set_up() {
		parent::set_up();
		Geo_Provider_Sloc_Test::reset();
		update_option( 'sloc_geo_provider', 'sloc_test' );
		delete_option( 'sloc_geo_fallbacks' );
		update_option( 'sloc_elevation_provider', 'none' );
		update_option( 'sloc_weather_provider', 'none' );
		update_option( 'sloc_map_provider', 'none' );
	}

	/**
	 * Clean up the request.
	 */
	public function tear_down() {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Codes from the Open Location Code test data: valid, short and full.
	 *
	 * @return array Code, valid, short, full.
	 */
	public function data_validity() {
		return array(
			array( '8FWC2345+G6', true, false, true ),
			array( '8FWC2345+G6G', true, false, true ),
			array( '8fwc2345+', true, false, true ),
			array( '8FWCX400+', true, false, true ),
			array( 'WC2345+G6g', true, true, false ),
			array( '2345+G6', true, true, false ),
			array( '45+G6', true, true, false ),
			array( '+G6', true, true, false ),
			array( 'G+', false, false, false ),
			array( '+', false, false, false ),
			array( '8FWC2345+G', false, false, false ),
			array( '8FWC2_45+G6', false, false, false ),
			array( '8FWC2η45+G6', false, false, false ),
			array( '8FWC2345+G6+', false, false, false ),
			array( '8FWC2345G6+', false, false, false ),
			array( '8FWC2300+G6', false, false, false ),
			array( 'WC2300+G6g', false, false, false ),
			array( 'WC2345+G', false, false, false ),
			array( 'WC2300+', false, false, false ),
			array( '0FWC2345+', false, false, false ),
			array( 'F2', false, false, false ),
			array( 'CFX30000+', true, false, true ),
			array( 'WFX30000+', true, false, false ),
			array( 'CXX30000+', true, false, false ),
			array( '', false, false, false ),
			array( array(), false, false, false ),
		);
	}

	/**
	 * Codes are checked as the specification says.
	 *
	 * @dataProvider data_validity
	 *
	 * @param mixed $code  Code.
	 * @param bool  $valid Valid.
	 * @param bool  $short Short.
	 * @param bool  $full  Full.
	 */
	public function test_validity( $code, $valid, $short, $full ) {
		$this->assertSame( $valid, Sloc_Plus_Code::is_valid( $code ), 'valid' );
		$this->assertSame( $short, Sloc_Plus_Code::is_short( $code ), 'short' );
		$this->assertSame( $full, Sloc_Plus_Code::is_full( $code ), 'full' );
	}

	/**
	 * Locations from the Open Location Code test data, with their codes.
	 *
	 * @return array Latitude, longitude, length, code.
	 */
	public function data_encoding() {
		return array(
			array( 20.375, 2.775, 6, '7FG49Q00+' ),
			array( 20.3700625, 2.7821875, 10, '7FG49QCJ+2V' ),
			array( 20.3701125, 2.782234375, 11, '7FG49QCJ+2VX' ),
			array( 20.3701135, 2.78223535156, 13, '7FG49QCJ+2VXGJ' ),
			array( 47.0000625, 8.0000625, 10, '8FVC2222+22' ),
			array( -41.2730625, 174.7859375, 10, '4VCPPQGP+Q9' ),
			array( 0.5, -179.5, 4, '62G20000+' ),
			array( -89.5, -179.5, 4, '22220000+' ),
			array( 20.5, 2.5, 4, '7FG40000+' ),
			array( -89.9999375, -179.9999375, 10, '22222222+22' ),
			array( 0.5, 179.5, 4, '6VGX0000+' ),
			array( 1, 1, 11, '6FH32222+222' ),
			array( 90, 1, 4, 'CFX30000+' ),
			array( 92, 1, 4, 'CFX30000+' ),
			array( 90, 1, 10, 'CFX3X2X2+X2' ),
			array( 1, 180, 4, '62H20000+' ),
			array( 1, 181, 4, '62H30000+' ),
			array( 37.4220625, -122.0840625, 10, '849VCWC8+R9' ),
		);
	}

	/**
	 * Locations are encoded as the reference implementations do.
	 *
	 * @dataProvider data_encoding
	 *
	 * @param float  $latitude  Latitude.
	 * @param float  $longitude Longitude.
	 * @param int    $length    Code length.
	 * @param string $code      Code.
	 */
	public function test_encode( $latitude, $longitude, $length, $code ) {
		$this->assertSame( $code, Sloc_Plus_Code::encode( $latitude, $longitude, $length ) );
	}

	/**
	 * Lengths that can't be used are errors, and the default is 10 digits.
	 */
	public function test_encode_lengths() {
		$this->assertWPError( Sloc_Plus_Code::encode( 1, 1, 1 ) );
		$this->assertWPError( Sloc_Plus_Code::encode( 1, 1, 7 ) );
		$this->assertSame( '7FG49QCJ+2V', Sloc_Plus_Code::encode( 20.3700625, 2.7821875 ) );
		$code = Sloc_Plus_Code::encode( 20.3701135, 2.78223535156, 20 );
		$this->assertStringStartsWith( '7FG49QCJ+2VXGJ', $code );
		$this->assertSame( 16, strlen( $code ), 'At most 15 digits.' );
	}

	/**
	 * Codes from the Open Location Code test data, with their areas.
	 *
	 * @return array Code, length, south, west, north, east.
	 */
	public function data_decoding() {
		return array(
			array( '7FG49Q00+', 6, 20.35, 2.75, 20.4, 2.8 ),
			array( '7FG49QCJ+2V', 10, 20.37, 2.782125, 20.370125, 2.78225 ),
			array( '7FG49QCJ+2VX', 11, 20.3701, 2.78221875, 20.370125, 2.78225 ),
			array( '7FG49QCJ+2VXGJ', 13, 20.370113, 2.782234375, 20.370114, 2.78223632813 ),
			array( '8FVC2222+22', 10, 47.0, 8.0, 47.000125, 8.000125 ),
			array( '4VCPPQGP+Q9', 10, -41.273125, 174.785875, -41.273, 174.786 ),
			array( '62G20000+', 4, 0.0, -180.0, 1, -179 ),
			array( '22220000+', 4, -90, -180, -89, -179 ),
			array( '7FG40000+', 4, 20, 2, 21, 3 ),
			array( '22222222+22', 10, -90, -180, -89.999875, -179.999875 ),
			array( '6VGX0000+', 4, 0, 179, 1, 180 ),
			array( '6fh32222+222', 11, 1, 1, 1.000025, 1.00003125 ),
			array( 'CFX30000+', 4, 89, 1, 90, 2 ),
		);
	}

	/**
	 * Codes are decoded into their areas and centers.
	 *
	 * @dataProvider data_decoding
	 *
	 * @param string $code   Code.
	 * @param int    $length Code length.
	 * @param float  $south  Southern edge.
	 * @param float  $west   Western edge.
	 * @param float  $north  Northern edge.
	 * @param float  $east   Eastern edge.
	 */
	public function test_decode( $code, $length, $south, $west, $north, $east ) {
		$area = Sloc_Plus_Code::decode( $code );
		$this->assertSame( $length, $area['code_length'] );
		$this->assertEqualsWithDelta( $south, $area['latitude_lo'], 1e-10 );
		$this->assertEqualsWithDelta( $west, $area['longitude_lo'], 1e-10 );
		$this->assertEqualsWithDelta( $north, $area['latitude_hi'], 1e-10 );
		$this->assertEqualsWithDelta( $east, $area['longitude_hi'], 1e-10 );
		$this->assertEqualsWithDelta( ( $south + $north ) / 2, $area['latitude'], 1e-10 );
		$this->assertEqualsWithDelta( ( $west + $east ) / 2, $area['longitude'], 1e-10 );
	}

	/**
	 * Short and invalid codes can't be decoded on their own.
	 */
	public function test_decode_needs_full_code() {
		$this->assertSame( 'invalid_plus_code', Sloc_Plus_Code::decode( 'CWC8+R9' )->get_error_code() );
		$this->assertSame( 'invalid_plus_code', Sloc_Plus_Code::decode( 'nonsense' )->get_error_code() );
	}

	/**
	 * Encoding the center of a decoded area gives the same code, anywhere in the world.
	 */
	public function test_round_trip() {
		foreach ( array( array( 39.9524, -75.1636 ), array( -33.8568, 151.2153 ), array( 64.1466, -21.9426 ), array( -0.0001, 0.0001 ), array( 51.4779, -0.0015 ) ) as $point ) {
			foreach ( array( 8, 10, 11, 15 ) as $length ) {
				$code = Sloc_Plus_Code::encode( $point[0], $point[1], $length );
				$area = Sloc_Plus_Code::decode( $code );
				// Within the area, allowing for floating point at its edges.
				$this->assertGreaterThanOrEqual( $area['latitude_lo'] - 1e-9, $point[0], $code );
				$this->assertLessThan( $area['latitude_hi'], $point[0], $code );
				$this->assertGreaterThanOrEqual( $area['longitude_lo'] - 1e-9, $point[1], $code );
				$this->assertLessThan( $area['longitude_hi'], $point[1], $code );
				$this->assertSame( $code, Sloc_Plus_Code::encode( $area['latitude'], $area['longitude'], $length ), $code );
			}
		}
	}

	/**
	 * A short code is recovered from a nearby location, including across the antimeridian and near a pole.
	 */
	public function test_recover_nearest() {
		$this->assertSame( '849VCWC8+R9', Sloc_Plus_Code::recover_nearest( 'CWC8+R9', 37.4, -122.1 ) );
		$this->assertSame( '849VCWC8+R9', Sloc_Plus_Code::recover_nearest( 'cwc8+r9', 37.4, -122.1 ) );
		$this->assertSame( '849VCWC8+R9', Sloc_Plus_Code::recover_nearest( '849VCWC8+R9', 0, 0 ), 'A full code as it is.' );
		$this->assertWPError( Sloc_Plus_Code::recover_nearest( 'nonsense', 0, 0 ) );

		// The nearest match can be in the next area over.
		$this->assertSame( '9C3W9QCJ+2VX', Sloc_Plus_Code::recover_nearest( '9QCJ+2VX', 51.3708675, -1.217765625 ) );
		$this->assertSame( '9C3W9QCJ+2VX', Sloc_Plus_Code::recover_nearest( 'CJ+2VX', 51.3708675, -1.217765625 ) );
		$this->assertSame( '8FJFW222+', Sloc_Plus_Code::recover_nearest( '22+', 42.899, 9.012 ) );
		$this->assertSame( '796RXG22+', Sloc_Plus_Code::recover_nearest( '22+', 14.95125, -23.5001 ) );
		$this->assertSame( '8FMF2222+', Sloc_Plus_Code::recover_nearest( '2222+', 43.0, 9.0 ) );
		$this->assertSame( '8FJF2222+', Sloc_Plus_Code::recover_nearest( '2222+', 42.4, 9.0 ) );

		// Across the antimeridian, and not past the North Pole.
		$code = Sloc_Plus_Code::encode( 10.5, 179.999 );
		$this->assertSame( $code, Sloc_Plus_Code::recover_nearest( substr( $code, 4 ), 10.5, -179.999 ) );
		$code = Sloc_Plus_Code::encode( 89.6, 1 );
		$this->assertSame( $code, Sloc_Plus_Code::recover_nearest( substr( $code, 4 ), 89.99, 1 ) );
	}

	/**
	 * A code is found in text, with the place after a short code.
	 */
	public function test_parse() {
		$this->assertSame(
			array(
				'code'  => '849VCWC8+R9',
				'place' => '',
			),
			Sloc_Plus_Code::parse( ' 849vcwc8+r9 ' )
		);
		$this->assertSame(
			array(
				'code'  => 'CWC8+R9',
				'place' => 'Mountain View, CA',
			),
			Sloc_Plus_Code::parse( 'CWC8+R9, Mountain View, CA' )
		);
		$this->assertFalse( Sloc_Plus_Code::parse( '1600 Amphitheatre Parkway' ) );
		$this->assertFalse( Sloc_Plus_Code::parse( 'Mountain View CWC8+R9' ) );
		$this->assertSame( 'CWC8+R9X', Sloc_Plus_Code::parse( 'CWC8+R9X' )['code'], 'Eleven digits.' );
		$this->assertFalse( Sloc_Plus_Code::parse( 'CWC8+R' ), 'Not a valid code.' );
		$this->assertFalse( Sloc_Plus_Code::parse( 'CWC8+R9abc' ) );
		$this->assertFalse( Sloc_Plus_Code::parse( null ) );
	}

	/**
	 * A full code is found on its own; a short code from its place, or from a location given; else an error.
	 */
	public function test_locate() {
		$area = Sloc_Plus_Code::locate( '849VCWC8+R9' );
		$this->assertSame( '849VCWC8+R9', $area['plus_code'] );
		$this->assertEqualsWithDelta( 37.4220625, $area['latitude'], 1e-9 );
		$this->assertEqualsWithDelta( -122.0840625, $area['longitude'], 1e-9 );

		Geo_Provider_Sloc_Test::$geocode = array(
			'latitude'  => 37.3861,
			'longitude' => -122.0839,
		);
		$this->assertSame( '849VCWC8+R9', Sloc_Plus_Code::locate( 'CWC8+R9 Mountain View, CA' )['plus_code'] );
		$this->assertSame( '849VCWC8+R9', Sloc_Plus_Code::locate( 'CWC8+R9', 37.4, -122.1 )['plus_code'] );

		$this->assertSame( 'plus_code_needs_place', Sloc_Plus_Code::locate( 'CWC8+R9' )->get_error_code() );
		$this->assertSame( 'invalid_plus_code', Sloc_Plus_Code::locate( 'Mountain View' )->get_error_code() );

		Geo_Provider_Sloc_Test::$geocode = new WP_Error( 'no_results', 'Nothing found' );
		$this->assertSame( 'no_results', Sloc_Plus_Code::locate( 'CWC8+R9 Nowhere' )->get_error_code() );
	}

	/**
	 * Geocoding a Plus Code gives its location and code, with the address found there.
	 */
	public function test_geocode_chain() {
		$location = Sloc_Provider_Chain::geocode( '849VCWC8+R9' );
		$this->assertSame( 'Test Address', $location['display-name'] );
		$this->assertSame( '849VCWC8+R9', $location['plus_code'] );
		$this->assertEqualsWithDelta( 37.4220625, $location['latitude'], 1e-9 );
		$this->assertEqualsWithDelta( 37.4220625, Geo_Provider_Sloc_Test::$last[0], 1e-9, 'The address is looked up at the code.' );

		Geo_Provider_Sloc_Test::$reverse = new WP_Error( 'no_results', 'Nothing found' );
		$location                        = Sloc_Provider_Chain::geocode( '849VCWC8+R9' );
		$this->assertSame( array( 'latitude', 'longitude', 'plus_code' ), array_keys( $location ), 'Without an address, the location.' );

		$this->assertSame( 45.01, Sloc_Provider_Chain::geocode( '1600 Amphitheatre Parkway' )['latitude'], 'Addresses as before.' );
	}

	/**
	 * The geocode route looks up a Plus Code in place of the coordinates, and says where it is.
	 */
	public function test_rest_geocode() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$request = new WP_REST_Request( 'GET', '/sloc_geo/1.0/geocode' );
		$request->set_param( 'plus_code', 'CWC8+R9' );
		$request->set_param( 'latitude', 37.4 );
		$request->set_param( 'longitude', -122.1 );
		$request->set_param( 'altitude', 1200 );
		$response = rest_do_request( $request )->get_data();
		$this->assertSame( '849VCWC8+R9', $response['plus_code'] );
		$this->assertEqualsWithDelta( 37.4220625, $response['latitude'], 1e-9 );
		$this->assertEqualsWithDelta( -122.0840625, $response['longitude'], 1e-9 );
		$this->assertSame( 'Test Address', $response['display-name'] );
		$this->assertEqualsWithDelta( 37.4220625, Geo_Provider_Sloc_Test::$last[0], 1e-9 );
		$this->assertArrayNotHasKey( 'altitude', $response, 'The altitude of the old location is dropped.' );

		$request = new WP_REST_Request( 'GET', '/sloc_geo/1.0/geocode' );
		$request->set_param( 'plus_code', 'CWC8+R9' );
		$response = rest_do_request( $request );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'plus_code_needs_place', $response->get_data()['code'] );
	}

	/**
	 * Saving the location box with a Plus Code and no coordinates saves the code's location.
	 */
	public function test_save_meta() {
		$post_id = self::factory()->post->create();
		$_POST   = array(
			'plus_code'  => '849VCWC8+R9',
			'latitude'   => '',
			'longitude'  => '',
			'geo_public' => 'public',
		);
		Geo_Base::save_meta( 'post', $post_id );
		$this->assertEqualsWithDelta( 37.4220625, get_post_meta( $post_id, 'geo_latitude', true ), 1e-7 );
		$this->assertEqualsWithDelta( -122.0840625, get_post_meta( $post_id, 'geo_longitude', true ), 1e-7 );

		// Coordinates in the form win, as the lookup already put the code's there.
		$_POST['latitude']  = '39.9524';
		$_POST['longitude'] = '-75.1636';
		Geo_Base::save_meta( 'post', $post_id );
		$this->assertEquals( 39.9524, get_post_meta( $post_id, 'geo_latitude', true ) );

		$_POST = array(
			'plus_code' => 'nonsense',
			'latitude'  => '',
			'longitude' => '',
		);
		Geo_Base::save_meta( 'post', $post_id );
		$this->assertSame( '', get_post_meta( $post_id, 'geo_latitude', true ) );
	}
}

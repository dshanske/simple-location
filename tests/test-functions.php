<?php
/**
 * Tests for the global helper functions in includes/functions.php.
 *
 * @package Simple_Location
 */

/**
 * Helper function tests.
 */
class FunctionsTest extends WP_UnitTestCase {

	/**
	 * Decimal coordinates are rounded to 7 places.
	 */
	public function test_clean_coordinate_decimal() {
		$this->assertSame( 45.0123457, clean_coordinate( '45.012345678' ) );
		$this->assertSame( -75.44, clean_coordinate( ' -75.44 ' ) );
		$this->assertSame( 45.01, clean_coordinate( 45.01 ) );
	}

	/**
	 * Non-numeric input is rejected.
	 */
	public function test_clean_coordinate_rejects_text() {
		$this->assertFalse( clean_coordinate( 'abc' ) );
		$this->assertFalse( clean_coordinate( '' ) );
	}

	/**
	 * Whole-number coordinates are valid and must not become 0.
	 */
	public function test_clean_coordinate_integer() {
		$this->assertSame( 45.0, clean_coordinate( '45' ) );
		$this->assertSame( -75.0, clean_coordinate( -75 ) );
	}

	/**
	 * Values outside the valid coordinate range are rejected.
	 */
	public function test_clean_coordinate_out_of_range() {
		$this->assertFalse( clean_coordinate( '191.5' ) );
		$this->assertFalse( clean_coordinate( '-181.2' ) );
	}

	/**
	 * Latitudes are limited to ±90 and longitudes to ±180, including impossible values like 300.
	 */
	public function test_clean_coordinate_range_by_type() {
		$this->assertSame( 90.0, clean_coordinate( '90', 'latitude' ) );
		$this->assertSame( -90.0, clean_coordinate( -90, 'lat' ) );
		$this->assertFalse( clean_coordinate( '90.5', 'latitude' ) );
		$this->assertFalse( clean_coordinate( -91, 'geo_latitude' ) );
		$this->assertSame( 120.5, clean_coordinate( '120.5', 'longitude' ) );
		$this->assertSame( 180.0, clean_coordinate( 180, 'geo_longitude' ) );
		$this->assertFalse( clean_coordinate( '300', 'longitude' ) );
		$this->assertFalse( clean_coordinate( 300 ) );
		$this->assertFalse( clean_coordinate( '-300.25' ) );
		$this->assertFalse( clean_coordinate( 300.0, 'latitude' ) );
	}

	/**
	 * Numbers with more than three whole digits are rejected rather than truncated.
	 */
	public function test_clean_coordinate_too_many_digits() {
		$this->assertFalse( clean_coordinate( '1234' ) );
		$this->assertFalse( clean_coordinate( '1234.5' ) );
		$this->assertFalse( clean_coordinate( '45.5.5' ) );
	}

	/**
	 * Leading numbers are kept when followed by text, and a plus sign is allowed.
	 */
	public function test_clean_coordinate_trailing_text() {
		$this->assertSame( 45.5, clean_coordinate( '45.5 N' ) );
		$this->assertSame( 45.5, clean_coordinate( '+45.5' ) );
		$this->assertSame( 0.0, clean_coordinate( '0' ) );
	}

	/**
	 * Values that are not strings or numbers are rejected without warnings.
	 */
	public function test_clean_coordinate_non_scalar() {
		$this->assertFalse( clean_coordinate( null ) );
		$this->assertFalse( clean_coordinate( array( 45 ) ) );
		$this->assertFalse( clean_coordinate( true ) );
		$this->assertFalse( clean_coordinate( NAN ) );
		$this->assertFalse( clean_coordinate( INF ) );
	}

	/**
	 * The meta sanitizer rejects an impossible latitude, using the meta key as the type.
	 */
	public function test_meta_sanitizer_rejects_out_of_range() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'geo_latitude', '95' );
		update_post_meta( $post_id, 'geo_longitude', '95' );
		$this->assertEmpty( get_post_meta( $post_id, 'geo_latitude', true ) );
		$this->assertEquals( 95, get_post_meta( $post_id, 'geo_longitude', true ) );
	}

	/**
	 * Distance between two known points (Philadelphia to New York, about 130 km).
	 */
	public function test_geo_distance_known_points() {
		$distance = geo_distance( 39.9526, -75.1652, 40.7128, -74.0060 );
		$this->assertEqualsWithDelta( 129600, $distance, 1500 );
	}

	/**
	 * The distance from a point to itself is effectively zero, never NAN.
	 *
	 * The spherical law of cosines loses precision at short distances, so allow up to a meter.
	 */
	public function test_geo_distance_same_point() {
		$distance = geo_distance( 39.9526, -75.1652, 39.9526, -75.1652 );
		$this->assertFalse( is_nan( $distance ), 'geo_distance returned NAN for identical points' );
		$this->assertEqualsWithDelta( 0, $distance, 1 );
	}

	/**
	 * A point is within its own radius, and a distant point is not.
	 */
	public function test_geo_in_radius() {
		$this->assertTrue( geo_in_radius( 40.0, -75.0, 40.0001, -75.0, 50 ) );
		$this->assertFalse( geo_in_radius( 40.0, -75.0, 40.01, -75.0, 50 ) );
		// Non-numeric radius falls back to 50 meters.
		$this->assertTrue( geo_in_radius( 40.0, -75.0, 40.0001, -75.0, 'abc' ) );
	}

	/**
	 * The radius box is centered on the point and every corner is the radius away along each axis.
	 */
	public function test_geo_radius_box_is_symmetric() {
		$box = geo_radius_box( 40.0, -75.0, 1000 );
		// Order: min lat, min lng, max lat, max lng.
		$this->assertEqualsWithDelta( 40.0 - $box[0], $box[2] - 40.0, 0.000001, 'Latitude span is not symmetric' );
		$this->assertEqualsWithDelta( -75.0 - $box[1], $box[3] + 75.0, 0.000001, 'Longitude span is not symmetric' );
		$this->assertEqualsWithDelta( 1000, geo_distance( 40.0, -75.0, $box[2], -75.0 ), 1 );
		$this->assertEqualsWithDelta( 1000, geo_distance( 40.0, -75.0, 40.0, $box[3] ), 1 );

		// The span depends on the latitude, not the longitude: near 90° longitude it used to approach infinity.
		$east = geo_radius_box( 40.0, 90.0, 1000 );
		$this->assertEqualsWithDelta( $box[3] - $box[1], $east[3] - $east[1], 0.000001 );
	}

	/**
	 * Bounding box of a set of points, in both orders.
	 */
	public function test_geo_bounding_box() {
		$points = array( array( 40, -75 ), array( 41, -74 ), array( 39, -76 ) );
		$this->assertSame( array( 39, -76, 41, -74 ), geo_bounding_box( $points ) );
		$this->assertSame( array( -76, 39, -74, 41 ), geo_bounding_box( $points, true ) );
	}

	/**
	 * Area of a right triangle with legs of 4 and 3.
	 */
	public function test_area_of_triangle() {
		$this->assertEquals( 6, area_of_triangle( array( 0, 0 ), array( 4, 0 ), array( 0, 3 ) ) );
	}

	/**
	 * Visvalingam-Whyatt simplification reduces to the target and keeps the end points.
	 */
	public function test_sloc_simplify_vw() {
		$points = array( array( 0, 0 ), array( 1, 0.01 ), array( 2, 0 ), array( 3, 5 ), array( 4, 0 ) );
		$result = sloc_simplify_vw( $points, 4 );
		$this->assertCount( 4, $result );
		$this->assertSame( array( 0, 0 ), $result[0] );
		$this->assertSame( array( 4, 0 ), end( $result ) );
		// Asking for more points than exist returns the input.
		$this->assertSame( $points, sloc_simplify_vw( $points, 10 ) );
	}

	/**
	 * Ramer-Douglas-Peucker simplification removes points within tolerance of a straight line.
	 */
	public function test_sloc_simplify_rdp() {
		$line = array( array( 0, 0 ), array( 1, 0.001 ), array( 2, 0 ) );
		$this->assertSame( array( array( 0, 0 ), array( 2, 0 ) ), sloc_simplify_rdp( $line, 0.1 ) );
		$this->assertCount( 2, sloc_simplify_rdp( array( array( 0, 0 ), array( 1, 1 ) ), 0.1 ) );
	}

	/**
	 * Degrees and decimal minutes formatting.
	 */
	public function test_dec_to_dms() {
		$this->assertSame( '40° 30′ N 75° 15′ E', dec_to_dms( 40.5, 75.25 ) );
		$this->assertSame( '40° 30′ N 75° 15′ E 100m', dec_to_dms( 40.5, 75.25, 100 ) );
	}

	/**
	 * Southern and western coordinates use a direction letter, not a minus sign as well.
	 */
	public function test_dec_to_dms_no_double_negative() {
		$this->assertSame( '33° 30′ S 70° 30′ W', dec_to_dms( -33.5, -70.5 ) );
	}

	/**
	 * Object type and ID helpers round-trip for every object type.
	 */
	public function test_object_helpers() {
		$post    = self::factory()->post->create_and_get();
		$comment = self::factory()->comment->create_and_get( array( 'comment_post_ID' => $post->ID ) );
		$user    = self::factory()->user->create_and_get();
		$term    = self::factory()->term->create_and_get();

		$objects = array(
			'post'    => array( $post, $post->ID ),
			'comment' => array( $comment, $comment->comment_ID ),
			'user'    => array( $user, $user->ID ),
			'term'    => array( $term, $term->term_id ),
		);
		foreach ( $objects as $type => $data ) {
			list( $object, $id ) = $data;
			$this->assertSame( $type, sloc_get_type_from_object( $object ), $type );
			$this->assertEquals( $id, sloc_get_id_from_object( $object ), $type );
			$this->assertEquals( $id, sloc_get_id_from_object( sloc_get_object_from_id( $type, $id ) ), $type );
		}
		$this->assertNull( sloc_get_object_from_id( 'widget', 1 ) );
		$this->assertNull( sloc_get_id_from_object( 'not an object' ) );
		$this->assertNull( sloc_get_type_from_object( new stdClass() ) );
	}

	/**
	 * Array and variable fallback helpers.
	 */
	public function test_fallback_helpers() {
		$array = array( 'a' => 1.567 );
		$this->assertSame( 1.567, ifset( $array['a'] ) );
		$this->assertSame( 'x', ifset( $array['b'], 'x' ) );
		$this->assertSame( 1.6, ifset_round( $array['a'], 1 ) );
		$other = array( 'a' => 1.567 );
		$this->assertSame( 1.567, array_key_return( 'a', $other ) );
		$this->assertFalse( array_key_return( 'b', $other ) );
		$not_array = 'string';
		$this->assertSame( 'd', array_key_return( 'a', $not_array, 'd' ) );
		$this->assertSame( '12.5', sanitize_float( '12.5abc' ) );
	}

	/**
	 * The rounding helper rounds numbers, including numeric strings, and returns anything else unchanged.
	 */
	public function test_sloc_round_value() {
		$this->assertSame( 1.57, sloc_round_value( 1.567, 2 ) );
		$this->assertSame( 2.0, sloc_round_value( 1.6 ) );
		$this->assertSame( 21.5, sloc_round_value( '21.54', 1 ) );
		$this->assertSame( 0.0, sloc_round_value( 0, 2 ) );
		$this->assertSame( '', sloc_round_value( '' ) );
		$this->assertNull( sloc_round_value( null ) );
		$this->assertSame( 'n/a', sloc_round_value( 'n/a' ) );
		$data = array();
		$this->assertSame( '', sloc_round_value( $data['speed'] ?? '', 2 ) );
		$this->assertArrayNotHasKey( 'speed', $data );
	}

	/**
	 * Reading a missing key with ifset() must not add that key to the array.
	 */
	public function test_ifset_does_not_modify_array() {
		$this->markTestSkipped( 'Known bug, see #311' );
		$array = array( 'a' => 1 );
		ifset( $array['b'] );
		$this->assertArrayNotHasKey( 'b', $array );
	}

	/**
	 * EXIF GPS degrees, minutes and seconds become decimal degrees, and missing seconds count as zero without a warning.
	 */
	public function test_wp_exif_gps_convert() {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$this->assertEqualsWithDelta( 39.9524, wp_exif_gps_convert( array( '39/1', '57/1', '864/100' ) ), 0.00001 );
		$this->assertEqualsWithDelta( 39.95, wp_exif_gps_convert( array( '39/1', '57/1' ) ), 0.0001 );
		$this->assertFalse( wp_exif_gps_convert( '39.9524' ) );
	}
}

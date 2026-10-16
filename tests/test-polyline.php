<?php
/**
 * Tests for the polyline encoder in lib/class-sloc-polyline.php.
 *
 * @package Simple_Location
 */

/**
 * Polyline tests.
 */
class PolylineTest extends WP_UnitTestCase {

	/**
	 * The worked example from Google's Encoded Polyline Algorithm Format documentation.
	 *
	 * @var array
	 */
	protected $points = array(
		array( 38.5, -120.2 ),
		array( 40.7, -120.95 ),
		array( 43.252, -126.453 ),
	);

	/**
	 * The encoded form of $points, from the same documentation.
	 */
	const ENCODED = '_p~iF~ps|U_ulLnnqC_mqNvxq`@';

	/**
	 * Points encode to Google's documented string.
	 */
	public function test_encode() {
		$this->assertSame( self::ENCODED, Sloc_Polyline::encode( $this->points ) );
	}

	/**
	 * A flat list of numbers encodes the same as a list of pairs.
	 */
	public function test_encode_flat_list() {
		$this->assertSame( self::ENCODED, Sloc_Polyline::encode( array( 38.5, -120.2, 40.7, -120.95, 43.252, -126.453 ) ) );
	}

	/**
	 * Numeric strings are accepted, and values are rounded to five decimal places.
	 */
	public function test_encode_strings_and_rounding() {
		$this->assertSame( self::ENCODED, Sloc_Polyline::encode( array( array( '38.5', '-120.2' ), array( '40.700001', '-120.95' ), array( '43.252', '-126.453' ) ) ) );
	}

	/**
	 * Nothing to encode gives an empty string.
	 */
	public function test_encode_empty() {
		$this->assertSame( '', Sloc_Polyline::encode( array() ) );
	}

	/**
	 * Decoding returns the flat list of coordinates.
	 */
	public function test_decode() {
		$decoded = Sloc_Polyline::decode( self::ENCODED );
		$this->assertCount( 6, $decoded );
		foreach ( Sloc_Polyline::flatten( $this->points ) as $i => $value ) {
			$this->assertEqualsWithDelta( $value, $decoded[ $i ], 0.000001 );
		}
		$this->assertSame( array(), Sloc_Polyline::decode( '' ) );
	}

	/**
	 * A string cut off in the middle of a value decodes the complete values and drops the last one.
	 */
	public function test_decode_truncated() {
		$decoded = Sloc_Polyline::decode( substr( self::ENCODED, 0, -1 ) );
		$this->assertCount( 5, $decoded );
		$this->assertEqualsWithDelta( 38.5, $decoded[0], 0.000001 );
		$this->assertEqualsWithDelta( 43.252, $decoded[4], 0.000001 );
	}

	/**
	 * Encoding then decoding returns the original points.
	 */
	public function test_round_trip() {
		$points = array(
			array( 39.9524, -75.1636 ),
			array( 39.9489, -75.15 ),
			array( -33.8568, 151.2153 ),
			array( 0, 0 ),
		);
		$pairs  = Sloc_Polyline::pair( Sloc_Polyline::decode( Sloc_Polyline::encode( $points ) ) );
		$this->assertCount( count( $points ), $pairs );
		foreach ( $points as $i => $point ) {
			$this->assertEqualsWithDelta( $point[0], $pairs[ $i ][0], 0.000001 );
			$this->assertEqualsWithDelta( $point[1], $pairs[ $i ][1], 0.000001 );
		}
	}

	/**
	 * Flatten and pair convert between lists of pairs and flat lists.
	 */
	public function test_flatten_and_pair() {
		$this->assertSame( array( 1, 2, 3, 4 ), Sloc_Polyline::flatten( array( array( 1, 2 ), array( 3, 4 ) ) ) );
		$this->assertSame( array( array( 1, 2 ), array( 3, 4 ) ), Sloc_Polyline::pair( array( 1, 2, 3, 4 ) ) );
		$this->assertSame( array( array( 1, 2 ), array( 3 ) ), Sloc_Polyline::pair( array( 1, 2, 3 ) ) );
		$this->assertSame( array(), Sloc_Polyline::pair( 'not a list' ) );
	}
}

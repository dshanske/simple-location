<?php
/**
 * Location Provider.
 *
 * @package Simple_Location
 */

/**
 * Location Provider using Airport Codes.
 *
 * @since 1.0.0
 */
class Location_Provider_Airport extends Location_Provider {

	/**
	 * Constructor for the Abstract Class.
	 *
	 * The default version of this just sets the parameters.
	 *
	 * @param array $args {
	 *  Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Set Location from Airport Code', 'simple-location' );
		$this->description = __( 'This location provider uses a local airport code database to set your location based on airport codes', 'simple-location' );
		$this->slug        = 'airport';
		$this->background  = false;
		parent::__construct( $args );
	}

	/**
	 * Get Coordinates in H-Geo MF2 Format.
	 *
	 * @param string|int|DateTime $time An ISO8601 time string, unix timestamp, or DateTime.
	 * @param array               $args Optional arguments to be passed.
	 * @return array|boolean Array with h-geo mf2 false if null
	 */
	public function retrieve( $time = null, $args = array() ) {
		if ( ! array_key_exists( 'address', $args ) ) {
			return new WP_Error( 'empty', __( 'No code passed through', 'simple-location' ) );
		}
		$code = trim( $args['address'] );
		if ( 3 === strlen( $code ) ) {
			$airport = Airport_Location::get( $code );
		} elseif ( 4 === strlen( $code ) ) {
			$airport = Airport_Location::get( $code, 'ident' );
		} else {
			$this->annotation = __( 'Something was passed but not an airport code', 'simple-location' );
			return new WP_Error( 'empty', __( 'Something was passed but not an airport code', 'simple-location' ) );
		}
		if ( is_wp_error( $airport ) ) {
			$this->annotation = __( 'No Match Found', 'simple-location' );
			return new WP_Error( 'no_match', __( 'Something was passed but not an airport code', 'simple-location' ) );
		}
		$this->latitude   = $airport['latitude'];
		$this->longitude  = $airport['longitude'];
		$this->altitude   = $airport['elevation'];
		$this->annotation = $airport['name'];
	}
}

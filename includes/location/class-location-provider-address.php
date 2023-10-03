<?php
/**
 * Location Provider.
 *
 * @package Simple_Location
 */

/**
 * Location Provider using Address.
 *
 * @since 1.0.0
 */
class Location_Provider_Address extends Location_Provider {

	/**
	 * Constructor for the Abstract Class.
	 *
	 * The default version of this just sets the parameters.
	 *
	 * @param array $args {
	 *  Arguments.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Set Location from Address Looked Up using Geo Provider', 'simple-location' );
		$this->slug        = 'address';
		$this->description = __( 'If this provider is used, it will look up the address provided in the address box to find the coordinates', 'simple-location' );
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
			$this->annotation = __( 'No Address Passed', 'simple-location' );
			return new WP_Error( 'empty', __( 'No address passed through', 'simple-location' ) );
		}
		if ( ! is_string( $args['address'] ) ) {
			return new WP_Error( 'no_string', __( 'Non String Passed Through', 'simple-location' ) );
		}
		$address = trim( $args['address'] );
		$geocode = Loc_Config::geo_provider();
		$address = $geocode->geocode( $address );
		if ( is_wp_error( $address ) ) {
			$this->annotation = __( 'No Item Found', 'simple-location' );
			return;
		}

		$this->latitude   = $address['latitude'];
		$this->longitude  = $address['longitude'];
		$this->altitude   = ifset( $address['altitude'] );
		$this->annotation = ifset( $address['display_name'] );
	}
}

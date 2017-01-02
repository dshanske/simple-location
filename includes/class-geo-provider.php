<?php

abstract class Geo_Provider {

	protected $reverse_zoom;
	protected $map_zoom;
	protected $height;
	protected $width;
	protected $api;
	protected $latitude;
	protected $longitude;
	protected $address;
	protected $static;
	protected $timezone;
	protected $offset;
	protected $offset_seconds;

	/**
	 * Constructor for the Abstract Class
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param string $key API Key if Needed
	 */
	public function __construct( $api = null ) {
		$this->height = get_option( 'sloc_height' );
		$this->width = get_option( 'sloc_width' );
		$this->map_zoom = get_option( 'sloc_zoom' );
		$this->api = $api;
	}

	/**
	 * Set and Validate Coordinates
	 *
	 * @param $lat Latitude
	 * @param $lng Longitude
	 * @param $zoom Reverse Zoom Precision
	 * @return boolean Return False if Validation Failed
	 */
	public function set( $lat, $lng, $zoom = 18 ) {
		// Validate inputs
		if ( ( ! is_numeric( $lat ) ) && ( ! is_numeric( $lng ) ) ) {
			return false;
		}
		$this->latitude = $lat;
		$this->longitude = $lng;
		$this->reverse_zoom = $zoom;
	}

	/**
	 * Get Coordinates
	 *
	 * @return array|boolean Array with Latitude and Longitude false if null
	 */
	public function get() {
		$return = array();
		$return['latitude'] = $this->latitude;
		$return['longitude'] = $this->longitude;
		$return = array_filter( $return );
		if ( ! empty( $return ) ) {
			return $return;
		}
		return false;
	}


	/**
	 * Return an address
	 *
	 * @return array microformats2 address elements in an array
	 */
	abstract public function reverse_lookup();


	/**
	 * Generate Display Name for a Reverse Address Lookup
	 *
	 * @param array $reverse Array of MF2 Address Properties
	 * @return string|boolean Return Display Name or False if Failed
	 */
	protected function display_name( $reverse ) {
		if ( ! is_array( $reverse ) ) {
			return false;
		}
		$text = array();
		$text[] = ifset( $reverse['name'] );
		if ( ! array_key_exists( 'address', $reverse ) ) {
			$text[] = ifset( $reverse['extended-address'] );
		}
		$text[] = ifset( $reverse['locality'] );
		$text[] = ifset( $reverse['region'] );
		$text[] = ifset( $reverse['country-name'] );
		$text = array_filter( $text );
		$return = join( ', ', $text );
		return apply_filters( 'location_display_name', $return, $reverse );
	}

		/**
	 * Return Timezone Data for a Set of Coordinates
	 *
	 * @return array|boolean Return Timezone Data or False if Failed
	 */

	protected function timezone() {
		$timezone = Loc_Timezone::timezone_for_location( $this->latitude, $this->longitude );
		if ( $timezone ) {
			$return = array();
			$return['timezone'] = $timezone->name;
			$return['offset']  = $timezone->offset;
			$return['seconds'] = $timezone->seconds;
			return $return;
		}
		return false;
	}

		/**
	 * Return a URL for a static map
	 *
	 * @return string URL of MAP
	 *
	 */
	abstract public function get_the_static_map();

		/**
	 * Return a URL for a link to a map
	 *
	 * @return string URL of link to a map
	 *
	 */
	abstract public function get_the_map_url();

		/**
	 * Return HTML code for a map
	 *
	 * @param boolean $static Return Static or Dynamic Map
	 * @return string HTML marked up map
	 */
	abstract public function get_the_map( $static = false );

		/**
	 * Given coordinates echo the output of get_the_map
	 *
	 * @param boolean $static Return Static or Dynamic Map
	 * @return echos the output
	 */
	public function the_map( $static = false ) {
		return $this->get_the_map( $static );
	}
}

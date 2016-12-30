<?php

abstract class Geo_Provider {

	public $reverse_zoom;
	public $map_zoom;
	public $height;
	private $width;
	private $api;
	private $latitude;
	private $longitude;
	private $address;
	private $static;
	private $timezone;
	private $offset;
	private $offset_seconds;

	/**
	 * Constructor for the Abstract Class
	 * 
	 * The default version of this just sets the parameters
	 *
	 * @param string $key API Key if Needed
	 */
	public function __construct( $api = null ) {
		$this->height = get_option( 'sloc_height' ) ;
		$this->width = get_option( 'sloc_width' );
		$this->map_zoom = get_option( 'sloc_zoom' ); 
		$this->reverse_zoom = 18;
		$this->api = $api; 
	}

	/**
	 * Return an address
	 *
	 * @param float $lat Latitude
	 * @param float $lng Longitude
	 * @param int $zoom Level of Lookup Detail 
	 * @return array microformats2 address elements in an array
	 */
	abstract private function reverse_lookup( $lat, $lng, $zoom);


	/**
	 * Generate Display Name for a Reverse Address Lookup
	 *
	 * @param array $reverse Array of MF2 Address Properties
	 * @return string|boolean Return Display Name or False if Failed
	 */
        private function display_name( $reverse ) {
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
	 * @param float $lat Latitude
	 * @param float $lng Longitude
	 * @return array|boolean Return Timezone Data or False if Failed
	 */

	private function timezone( $lat, $lng ) {
		if ( ! $lat || ! $lng ) {
			return false;
		}
		$timezone = Loc_Timezone::timezone_for_location( $lat, $lng );
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
		return self::get_the_map( $static );
	}
}

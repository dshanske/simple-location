<?php

abstract class Geo_Provider extends Sloc_Provider {

	protected $name;
	protected $reverse_zoom;
	protected $map_zoom;
	protected $height;
	protected $width;
	protected $api;
	protected $style;
	protected $user;
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
	public function __construct( $args = array() ) {
		$defaults           = array(
			'api'          => null,
			'latitude'     => null,
			'longitude'    => null,
			'reverse_zoom' => 18,
			'user'         => '',
		);
		$defaults           = apply_filters( 'sloc_geo_provider_defaults', $defaults );
		$r                  = wp_parse_args( $args, $defaults );
		$this->reverse_zoom = $r['reverse_zoom'];
		$this->user         = $r['user'];
		$this->api          = $r['api'];
		$this->set( $r['latitude'], $r['longitude'] );
	}

	/**
	 * Get Name
	 *
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Return elevation based on longitude and latitude
	 *
	 */
	abstract public function elevation();

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
		$text   = array();
		$text[] = ifset( $reverse['name'] );
		if ( ! array_key_exists( 'street-address', $reverse ) ) {
			$text[] = ifset( $reverse['extended-address'] );
		}
		$text[] = ifset( $reverse['locality'] );
		$text[] = ifset( $reverse['region'] );
		$text[] = ifset( $reverse['country-name'] );
		$text   = array_filter( $text );
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
			$return             = array();
			$return['timezone'] = $timezone->name;
			$return['offset']   = $timezone->offset;
			$return['seconds']  = $timezone->seconds;
			return $return;
		}
		return false;
	}
}

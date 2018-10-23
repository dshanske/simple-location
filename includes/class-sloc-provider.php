<?php

abstract class Sloc_Provider {

	protected $slug;
	protected $name;
	protected $api;
	protected $latitude;
	protected $longitude;

	/**
	 * Constructor for the Abstract Class
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param string $key API Key if Needed
	 */
	public function __construct( $args = array() ) {
		$defaults  = array(
			'api'       => null,
			'latitude'  => null,
			'longitude' => null,
		);
		$r         = wp_parse_args( $args, $defaults );
		$this->api = $r['api'];
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
	 * Get Slug
	 *
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Set and Validate Coordinates
	 *
	 * @param $lat Latitude or array
	 * @param $lng Longitude
	 * @return boolean Return False if Validation Failed
	 */
	public function set( $lat, $lng = null ) {
		if ( ! $lng && is_array( $lat ) ) {
			if ( isset( $lat['latitude'] ) && isset( $lat['longitude'] ) ) {
				$this->latitude  = $lat['latitude'];
				$this->longitude = $lat['longitude'];
				return true;
			} else {
				return false;
			}
		}
		// Validate inputs
		if ( ( ! is_numeric( $lat ) ) && ( ! is_numeric( $lng ) ) ) {
			return false;
		}
		$this->latitude  = $lat;
		$this->longitude = $lng;
		return true;
	}

	/**
	 * Get Coordinates
	 *
	 * @return array|boolean Array with Latitude and Longitude false if null
	 */
	public function get() {
		$return              = array();
		$return['latitude']  = $this->latitude;
		$return['longitude'] = $this->longitude;
		$return              = array_filter( $return );
		if ( ! empty( $return ) ) {
			return $return;
		}
		return false;
	}
}

<?php

abstract class Sloc_Provider {

	protected $slug;
	protected $name;
	protected $description;
	protected $api;
	protected $latitude;
	protected $longitude;
	protected $altitude;

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
			'altitude'  => null,
		);
		$r         = wp_parse_args( $args, $defaults );
		$this->api = $r['api'];
		$this->set( $r['latitude'], $r['longitude'] );
	}

	public function fetch_json( $url, $query ) {
		$fetch = add_query_arg( $query, $url );
		$args  = array(
			'headers'             => array(
				'Accept' => 'application/json',
			),
			'timeout'             => 10,
			'limit_response_size' => 1048576,
			'redirection'         => 1,
			// Use an explicit user-agent for Simple Location
			'user-agent'          => 'Simple Location for WordPress',
		);

		$response = wp_remote_get( $fetch, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( ( $code / 100 ) !== 2 ) {
			return new WP_Error( 'invalid_response', $body, array( 'status' => $code ) );
		}
		$json = json_decode( $body, true );
		if ( empty( $json ) ) {
			return new WP_Error( 'not_json_response', $body, array( 'type' => wp_remote_retrieve_header( $response, 'Content-Type' ) ) );
		}
		return $json;
	}

	/*
	 *
	 * @param $array Input Array
	 * @param $keys the keys in order returns the first one that exists
	*/
	public static function ifnot( $array, $keys ) {
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $array ) ) {
				return $array[ $key ];
			}
		}
		return null;
	}

	/**
	 * Get Name
	 *
	 */
	public function get_name() {
		return $this->name;
	}


	/**
	 * Get Description
	 *
	 */
	public function get_description() {
		return $this->description;
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
	public function set( $lat, $lng = null, $alt = null ) {
		if ( ! $lng && is_array( $lat ) ) {
			if ( isset( $lat['latitude'] ) && isset( $lat['longitude'] ) ) {
				$this->latitude  = $lat['latitude'];
				$this->longitude = $lat['longitude'];
				if ( isset( $lat['altitude'] ) ) {
					$this->altitude = $lat['altitude'];
				}
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

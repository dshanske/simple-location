<?php

abstract class Location_Provider extends Sloc_Provider {

	protected $api;
	protected $user;
	protected $latitude;
	protected $longitude;
	protected $accuracy;
	protected $altitude;
	protected $heading;
	protected $speed;
	protected $background = false; // Background determines if this source allows background updates

	/**
	 * Constructor for the Abstract Class
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param string $key API Key if Needed
	 */
	public function __construct( $args = array() ) {
		$defaults   = array(
			'api'  => null,
			'user' => '',
		);
		$defaults   = apply_filters( 'sloc_location_provider_defaults', $defaults );
		$r          = wp_parse_args( $args, $defaults );
		$this->user = $r['user'];
		$this->api  = $r['api'];
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
		$return['altitude']  = $this->altitude;
		$return['accuracy']  = $this->accuracy;
		$return['heading']   = $this->heading;
		$return['speed']     = $this->speed;
		$return              = array_filter( $return );
		if ( ! empty( $return ) ) {
			return $return;
		}
		return false;
	}

	/**
	 * Get Coordinates in Geo URI
	 *
	 * @return string|boolean GEOURI false if null
	 */
	public function get_geouri() {
		if ( empty( $this->latitude ) && empty( $this->longitude ) ) {
			return false;
		}
		$coords = array( $this->latitude, $this->longitude );
		if ( ! empty( $this->altitude ) ) {
			$coords[] = $this->altitude;
		}
		$return = 'geo:' . implode( ',', $coords );
		if ( ! empty( $this->accuracy ) ) {
			$return .= ';u=' . $this->accuracy;
		}
		return $return;
	}

	/**
	 * Get Coordinates in GeoJSON
	 *
	 * @return array|boolean Array in GeoJSON format false if null
	 */
	public function get_geojson() {
		if ( empty( $this->latitude ) && empty( $this->longitude ) ) {
			return false;
		}
		$coords = array( $this->longitude, $this->latitude );
		if ( ! empty( $this->altitude ) ) {
			$coords[] = $this->altitude;
		}
		$properties = array();
		foreach ( array() as $property ) {
			if ( ! empty( $this->$property ) ) {
				$properties[ $property ] = $this->$property;
			}
		}
		$properties = array_filter( $properties );
		return array(
			'type'       => 'Feature',
			'geometry'   => array(
				'type'        => 'Point',
				'coordinates' => $coords,
			),
			'properties' => $properties,
		);
	}


	/**
	 * Get Coordinates in H-Geo MF2 Format
	 *
	 * @return array|boolean Array with h-geo mf2 false if null
	 */
	public function get_mf2() {
		$properties              = array();
		$properties['latitude']  = $this->latitude;
		$properties['longitude'] = $this->longitude;
		$properties['altitude']  = $this->altitude;
		$properties['heading']   = $this->heading;
		$properties['speed']     = $this->speed;
		$properties              = array_filter( $properties );
		if ( empty( $properties ) ) {
			return false;
		}
		foreach ( $properties as $key => $value ) {
			$properties[ $key ] = array( $value );
		}
		return array(
			'type'       => array( 'h-geo' ),
			'properties' => $properties,
		);
	}


	public function set_user( $user ) {
		$this->user = $user;
	}

	public function background() {
		return $this->background;
	}

	abstract public function retrieve();
}

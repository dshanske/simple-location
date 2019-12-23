<?php

abstract class Location_Provider extends Sloc_Provider {

	protected $api;
	protected $user;
	protected $latitude;
	protected $longitude;
	protected $accuracy; // AKA Horizontal Accuracy
	protected $altitude_accuracy; // AKA Vertical Accuracy
	protected $altitude;
	protected $heading;
	protected $speed;
	protected $time       = null;
	protected $activity   = null;
	protected $annotation = ''; // Any annotation
	protected $other      = array(); // Extra data
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
		$return                      = array();
		$return['latitude']          = $this->latitude;
		$return['longitude']         = $this->longitude;
		$return['altitude']          = $this->altitude;
		$return['accuracy']          = $this->accuracy;
		$return['altitude_accuracy'] = $this->altitude_accuracy;
		$return['heading']           = $this->heading;
		$return['speed']             = $this->speed;
		$return['time']              = $this->time;
		$return['zoom']              = self::derive_zoom();
		$return['activity']          = $this->activity;
		$return['annotation']        = $this->annotation;
		$return['other']             = $this->other;
		$return                      = array_filter( $return );
		if ( ! empty( $return ) ) {
			return $return;
		}
		return false;
	}

	public function derive_zoom() {
		if ( $this->altitude > 1000 ) {
			return 9;
		}
		if ( 0 < $this->accuracy ) {
			$return = round( log( 591657550.5 / ( $this->accuracy * 45 ), 2 ) ) + 1;
			if ( $return > 20 ) {
				return 20;
			}
			return $return;
		}
		return get_option( 'sloc_zoom' );
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
		$properties['name']      = $this->annotation; // If there is an annotation set that as the name
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


	/**
	 * Get Coordinates in H-Geo MF2 Format
	 *
	 * @param int $time An ISO8601 time string
	 * @param array $args Optional arguments to be passed
	 * @return array|boolean Array with h-geo mf2 false if null
	 */
	abstract public function retrieve( $time = null, $args = array() );
}

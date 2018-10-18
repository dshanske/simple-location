<?php

abstract class Map_Provider {

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
	protected $static;

	/**
	 * Constructor for the Abstract Class
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param string $key API Key if Needed
	 */
	public function __construct( $args = array() ) {
		$defaults       = array(
			'height'    => get_option( 'sloc_height' ),
			'width'     => get_option( 'sloc_width' ),
			'map_zoom'  => get_option( 'sloc_zoom' ),
			'api'       => null,
			'latitude'  => null,
			'longitude' => null,
			'user'      => '',
			'style'     => '',
		);
		$defaults       = apply_filters( 'sloc_geo_provider_defaults', $defaults );
		$r              = wp_parse_args( $args, $defaults );
		$this->height   = $r['height'];
		$this->width    = $r['width'];
		$this->map_zoom = $r['map_zoom'];
		$this->user     = $r['user'];
		$this->style    = $r['style'];
		$this->api      = $r['api'];
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
	 * Set and Validate Coordinates
	 *
	 * @param $lat Latitude
	 * @param $lng Longitude
	 * @return boolean Return False if Validation Failed
	 */
	public function set( $lat, $lng ) {
		// Validate inputs
		if ( ( ! is_numeric( $lat ) ) && ( ! is_numeric( $lng ) ) ) {
			return false;
		}
		$this->latitude  = $lat;
		$this->longitude = $lng;
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

	/**
	 * Return an array of styles with key being id and value being display name
	 *
	 * @return array
	 */
	abstract public function get_styles();

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

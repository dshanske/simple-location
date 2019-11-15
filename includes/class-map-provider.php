<?php

abstract class Map_Provider extends Sloc_Provider {

	protected $map_zoom;
	protected $height;
	protected $width;
	protected $style;
	protected $user;
	protected $static;
	protected $location;

	/**
	 * Constructor for the Abstract Class
	 *
	 * The default version of this just sets the parameters
	 *
	 * @param string $key API Key if Needed
	 */
	public function __construct( $args = array() ) {
		global $content_width;
		$width = 1024;
		if ( $content_width ) {
			$width = $content_width;
		}
		if ( ! $width || $width > 1 ) {
			$width = get_option( 'sloc_width' );
			if ( ! is_numeric( $width ) ) {
				$width = 1024;
			}
		}
		$defaults = array(
			'width'     => $width,
			'height'    => round( $width / get_option( 'sloc_aspect', ( 16 / 9 ) ) ),
			'map_zoom'  => get_option( 'sloc_zoom' ),
			'api'       => null,
			'latitude'  => null,
			'longitude' => null,
			'altitude'  => null,
			'location'  => null,
			'user'      => '',
			'style'     => '',
		);
		$defaults = apply_filters( 'sloc_geo_provider_defaults', $defaults );
		$r        = wp_parse_args( $args, $defaults );

		$this->height   = $r['height'];
		$this->width    = $r['width'];
		$this->location = $r['location'];
		$this->map_zoom = $r['map_zoom'];
		$this->user     = $r['user'];
		$this->style    = $r['style'];
		$this->api      = $r['api'];
		$this->set( $r['latitude'], $r['longitude'], $r['altitude'] );
	}

	public function set( $args, $lng = null, $alt = null ) {
		if ( is_array( $args ) ) {
			if ( isset( $args['height'] ) ) {
				$this->height = $args['height'];
			}
			if ( isset( $args['width'] ) ) {
				$this->width = $args['width'];
			}
			if ( isset( $args['map_zoom'] ) ) {
				$this->map_zoom = $args['map_zoom'];
			}
			if ( isset( $args['location'] ) ) {
				$this->location = $args['location'];
			}
		}
		parent::set( $args, $lng, $alt );
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
	 * Return a URL for a static map with multiple locations
	 *
	 * @param $locations Array of latitude and longitudes
	 * @return string URL of MAP
	 *
	 */
	abstract public function get_archive_map( $locations );

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

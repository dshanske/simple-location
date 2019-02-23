<?php
// Wikimedia  Map Provider
class Map_Provider_Wikimedia extends Map_Provider {


	public function __construct( $args = array() ) {
		$this->name  = __( 'Wikimedia Maps', 'simple-location' );
		$this->slug  = 'wikimedia';
		$this->style = 'osm-intl';
		parent::__construct( $args );
	}

	public function get_styles() {
		return array(
			'osm-intl' => __( 'Map with Labels', 'simple-location' ),
			'osm'      => __( 'Map without Labels', 'simple-location' ),
		);
	}

	// Return code for map
	public function get_the_static_map() {
		$this->style = 'osm-intl';
		$map         = sprintf( 'https://maps.wikimedia.org/img/%1$s,%2$s,%3$s,%4$s,%5$sx%6$s@%7$sx.png', $this->style, $this->map_zoom, $this->latitude, $this->longitude, $this->width, $this->height, 3 );
		return $map;
	}

	public function get_the_map_url() {
		return sprintf( 'https://maps.wikimedia.org/#/%1$s/%2$s/%3$s', $this->map_zoom, $this->latitude, $this->longitude );
	}

	// Return code for map
	public function get_the_map( $static = true ) {
		$map  = $this->get_the_static_map();
		$link = $this->get_the_map_url();
		$c    = '<a target="_blank" href="' . $link . '"><img src="' . $map . '" /></a>';
		return $c;
	}

}

register_sloc_provider( new Map_Provider_Wikimedia() );


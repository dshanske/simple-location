<?php
// MapQuest Map Provider
class Map_Provider_Mapquest extends Map_Provider {


	public function __construct( $args = array() ) {
		$this->name = __( 'Mapquest Maps', 'simple-location' );
		$this->slug = 'mapquest';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_mapquest_api' );
		}
		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_mapquest_style' );
		}
		parent::__construct( $args );
	}

	public function get_styles() {
		return array(
			'map'   => __( 'Basic Map', 'simple-location' ),
			'hyb'   => __( 'Hybrid', 'simple-location' ),
			'sat'   => __( 'Satellite', 'simple-location' ),
			'light' => __( 'Light', 'simple-location' ),
			'dark'  => __( 'Dark', 'simple-location' ),
		);
	}

	// Return code for map
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return '';
		}
		$map = sprintf( 'https://open.mapquestapi.com/staticmap/v5/map?key=%1$s&center=%2$s,%3$s&size=%4$s,%5$s&type=%6$s', $this->api, $this->latitude, $this->longitude, $this->width, $this->height, $this->style );
		return $map;
	}

	public function get_the_map_url() {
		return sprintf( 'https://www.mapquest.com/?center=%1$s,%2$s&zoom=%3$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	// Return code for map
	public function get_the_map( $static = true ) {
		$map  = $this->get_the_static_map();
		$link = $this->get_the_map_url();
		$c    = '<a target="_blank" href="' . $link . '"><img src="' . $map . '" /></a>';
		return $c;
	}

}

register_sloc_provider( new Map_Provider_Mapquest() );


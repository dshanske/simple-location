<?php
// HERE Map Provider
class Map_Provider_Here extends Map_Provider {

	protected $appid;
	public function __construct( $args = array() ) {
		$this->name = __( 'HERE Maps', 'simple-location' );
		$this->slug = 'here';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_here_api' );
		}
		if ( ! isset( $args['appid'] ) ) {
			$args['appid'] = get_option( 'sloc_here_appid' );
		}
		$this->appid = $args['appid'];
		parent::__construct( $args );
	}

	public function get_styles() {
		return array();
	}

	// Return code for map
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return '';
		}
		$map = sprintf( 'https://image.maps.api.here.com/mia/1.6/?app_code=%1$s&app_id=%2$s&lat=%3$s&lon=%4$s&w=%5$s&h=%6$s', $this->api, $this->appid, $this->latitude, $this->longitude, $this->width, $this->height );
		return $map;
	}

	public function get_the_map_url() {
		return sprintf( 'https://wego.here.com/?map=%1$s,%2$s,%3$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	// Return code for map
	public function get_the_map( $static = true ) {
		$map  = $this->get_the_static_map();
		$link = $this->get_the_map_url();
		$c    = '<a target="_blank" href="' . $link . '"><img src="' . $map . '" /></a>';
		return $c;
	}

}

register_sloc_provider( new Map_Provider_Here() );


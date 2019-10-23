<?php
// LocationIQ Map Provider
class Map_Provider_LocationIQ extends Map_Provider {


	public function __construct( $args = array() ) {
		$this->name  = __( 'LocationIQ', 'simple-location' );
		$this->slug  = 'locationiq';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_locationiq_api' );
		}
		$this->style = 'roadmap';
		parent::__construct( $args );
	}

	public function get_styles() {
		return array();
	}

	// Return code for map
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Location IQ', 'simple-location' ) );
		}
		$map = add_query_arg(
			array(
				'key' => $this->api,
				'format' => 'png',
				'size' => sprintf( '%1$sx%2$s', $this->width, $this->height ),
				'markers' => sprintf( 'size:small|color:red|%1$s,%2$s', $this->latitude, $this->longitude )
			),
			'https://maps.locationiq.com/v2/staticmap'
		);
		return $map;
	}

	public function get_the_map_url() {
		return '';
	}

	// Return code for map
	public function get_the_map( $static = true ) {
		$map  = $this->get_the_static_map();
		$link = $this->get_the_map_url();
		$c    = '<a target="_blank" href="' . $link . '"><img src="' . $map . '" /></a>';
		return $c;
	}

}

register_sloc_provider( new Map_Provider_LocationIQ() );


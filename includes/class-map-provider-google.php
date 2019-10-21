<?php
// Google Map Provider
class Map_Provider_Google extends Map_Provider {


	public function __construct( $args = array() ) {
		$this->name = __( 'Google Maps', 'simple-location' );
		$this->slug = 'google';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_google_api' );
		}
		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_google_style' );
		}
		add_action( 'init', array( get_called_class(), 'init' ) );
		add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_google_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'Google Maps API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
		register_setting(
			'simloc',
			'sloc_google_style',
			array(
				'type'         => 'string',
				'description'  => 'Google Map Style',
				'show_in_rest' => false,
				'default'      => 'roadmap',
			)
		);
	}

	public static function admin_init() {
		add_settings_field(
			'googleapi', // id
			__( 'Google Maps API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_google_api',
			)
		);
		add_settings_field(
			'googlestyle', // id
			__( 'Google Style', 'simple-location' ),
			array( 'Loc_Config', 'style_callback' ),
			'simloc',
			'sloc_map',
			array(
				'label_for' => 'sloc_google_style',
				'provider'  => new Map_Provider_Google(),
			)
		);
	}

	public function get_styles() {
		return array(
			'roadmap'   => __( 'Roadmap', 'simple-location' ),
			'satellite' => __( 'Satellite', 'simple-location' ),
			'terrain'   => __( 'Terrain', 'simple-location' ),
			'hybrid'    => __( 'Satellite and Roadmap Hybrid', 'simple-location' ),
		);
	}

	// Return code for map
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return '';
		}
		$map = 'https://maps.googleapis.com/maps/api/staticmap?markers=color:red%7Clabel:P%7C' . $this->latitude . ',' . $this->longitude . '&size=' . $this->width . 'x' . $this->height . '&maptype=' . $this->style . '&language=' . get_bloginfo( 'language' ) . '&key=' . $this->api;
		return $map;
	}

	public function get_the_map_url() {
		return 'http://maps.google.com/maps?q=loc:' . $this->latitude . ',' . $this->longitude;
	}

	// Return code for map
	public function get_the_map( $static = true ) {
		$map  = $this->get_the_static_map();
		$link = $this->get_the_map_url();
		$c    = '<a target="_blank" href="' . $link . '"><img src="' . $map . '" /></a>';
		return $c;
	}

}

register_sloc_provider( new Map_Provider_Google() );


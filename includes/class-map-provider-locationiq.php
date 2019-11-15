<?php
// LocationIQ Map Provider
class Map_Provider_LocationIQ extends Map_Provider {


	public function __construct( $args = array() ) {
		$this->name = __( 'LocationIQ', 'simple-location' );
		$this->slug = 'locationiq';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_locationiq_api' );
		}
		$this->style = 'roadmap';

		$option = get_option( 'sloc_map_provider' );
		if ( 'locationiq' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_locationiq_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'Location IQ API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
	}

	public static function admin_init() {
		add_settings_field(
			'locationiq_api', // id
			__( 'LocationIQ API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_locationiq_api',
			)
		);
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
				'key'     => $this->api,
				'format'  => 'png',
				'size'    => sprintf( '%1$sx%2$s', $this->width, $this->height ),
				'markers' => sprintf( 'size:small|color:red|%1$s,%2$s', $this->latitude, $this->longitude ),
			),
			'https://maps.locationiq.com/v2/staticmap'
		);
		return $map;
	}

	public function get_archive_map( $locations ) {
		if ( empty( $this->api ) || empty( $locations ) ) {
			return '';
		}

		$markers = array();
		foreach ( $locations as $location ) {
			$markers[] = sprintf( '%1$s,%2$s', $location[0], $location[1] );
		}
		// $polyline = Polyline::encode( $locations );

		$map = add_query_arg(
			array(
				'key'     => $this->api,
				'format'  => 'png',
				'size'    => sprintf( '%1$sx%2$s', $this->width, $this->height ),
				'markers' => sprintf( 'size:small|color:red|%1$s', implode( '|', $markers ) ),
				'path'    => sprintf( 'weight:2|color:blue|fillcolor:%23add8e6|%1$s', implode( '|', $markers ) ),
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
		$c    = '<a target="_blank" href="' . $link . '"><img class="sloc-map" src="' . $map . '" /></a>';
		return $c;
	}

}

register_sloc_provider( new Map_Provider_LocationIQ() );


<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

/**
 * Map Provider using Google Map API.
 *
 * @since 1.0.0
 */
class Map_Provider_Google extends Map_Provider {


	public function __construct( $args = array() ) {
		$this->name       = __( 'Google Maps', 'simple-location' );
		$this->url = 'https://developers.google.com/maps/';
		$this->description = __( 'Google Maps Platform API key is required, however Google offers a $200 per month credit, which is the equivalent of 28,000 queries. Click Get Started. Make sure to enable the Static Map API. Follow the tutorial', 'simple-location' );
		$this->slug       = 'google';
		$this->max_width  = 640;
		$this->max_height = 640;
		$this->max_zoom   = 20;

		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_google_api' );
		}
		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_google_style' );
		}

		$option = get_option( 'sloc_map_provider' );
		if ( 'google' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
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
		$url = 'https://maps.googleapis.com/maps/api/staticmap';
		$map = add_query_arg(
			array(
				'markers'  => sprintf( 'color:red%7Clabel:P%7C|%1$s,%2$s', $this->latitude, $this->longitude ),
				'size'     => sprintf( '%1$sx%2$s', $this->width, $this->height ),
				'maptype'  => $this->style,
				'language' => get_bloginfo( 'language' ),
				'key'      => $this->api,
				'zoom'     => $this->map_zoom,
				'scale'    => 2,
			),
			$url
		);
		return $map;
	}


	public function get_archive_map( $locations ) {
		if ( empty( $this->api ) || empty( $this->style ) || empty( $locations ) ) {
			return '';
		}
		$url = 'https://maps.googleapis.com/maps/api/staticmap';

		$markers = array();
		foreach ( $locations as $location ) {
			$markers[] = sprintf( '%1$s,%2$s', $location[0], $location[1] );
		}
		$polyline = Sloc_Polyline::encode( $locations );

		$map = add_query_arg(
			array(
				'markers'  => 'size:tiny%7Ccolor:red%7Clabel:P%7C|' . implode( '|', $markers ),
				'size'     => sprintf( '%1$sx%2$s', $this->width, $this->height ),
				'maptype'  => $this->style,
				'language' => get_bloginfo( 'language' ),
				'key'      => $this->api,
				'path'     => 'color:0xff0000ff|weight:5|enc:' . $polyline,
			),
			$url
		);
		return $map;
	}

	public function get_the_map_url() {
		return 'http://maps.google.com/maps?q=loc:' . $this->latitude . ',' . $this->longitude;
	}

	// Return code for map
	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html();
	}

}

register_sloc_provider( new Map_Provider_Google() );


<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

/**
 * Map Provider using Geoapify  Map API.
 *
 * @since 1.0.0
 */
class Map_Provider_Geoapify extends Map_Provider {


	public function __construct( $args = array() ) {
		$this->name = __( 'GeoApify', 'simple-location' );
		$this->slug = 'geoapify';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_geoapify_api' );
		}

		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_geoapify_style' );
		}

		$option = get_option( 'sloc_map_provider' );
		if ( 'geoapify' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_geoapify_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'GeoApify API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
		register_setting(
			'simloc',
			'sloc_geoapify_style',
			array(
				'type'         => 'string',
				'description'  => 'Geoapify Map Style',
				'show_in_rest' => false,
				'default'      => 'osm-carto',
			)
		);
	}

	public static function admin_init() {
		add_settings_field(
			'geoapify_api', // id
			__( 'GeoApify API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_geoapify_api',
			)
		);
		add_settings_field(
			'geoapifystyle', // id
			__( 'Geoapify Style', 'simple-location' ),
			array( 'Loc_Config', 'style_callback' ),
			'simloc',
			'sloc_map',
			array(
				'label_for' => 'sloc_geoapify_style',
				'provider'  => new Map_Provider_Geoapify(),
			)
		);
	}


	public function get_styles() {
		return array(
			'osm-carto'                => __( 'OSM Carto', 'simple-location' ),
			'osm-bright'               => __( 'OSM Bright', 'simple-location' ),
			'osm-bright-grey'          => __( 'OSM Bright Grey', 'simple-location' ),
			'osm-bright-smooth'        => __( 'OSM Bright Smooth', 'simple-location' ),
			'klokantech-basic'         => __( 'Klokantech Basic', 'simple-location' ),
			'positron'                 => __( 'Positron', 'simple-location' ),
			'positron-blue'            => __( 'Positron Blue', 'simple-location' ),
			'positron-red'             => __( 'Positron Red', 'simple-location' ),
			'dark-matter'              => __( 'Dark Matter', 'simple-location' ),
			'dark-matter-brown'        => __( 'Dark Matter Brown', 'simple-location' ),
			'dark-matter-dark-grey'    => __( 'Dark Matter Dark Grey', 'simple-location' ),
			'dark-matter-dark-purple'  => __( 'Dark Matter Dark Purple', 'simple-location' ),
			'dark-matter-purple-roads' => __( 'Dark Matter Purple Roads', 'simple-location' ),
			'dark-matter-yellow-roads' => __( 'Dark Matter Yellow Roads', 'simple-location' ),
		);
	}

	// Return code for map
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for GeoApify', 'simple-location' ) );
		}
		$map = add_query_arg(
			array(
				'apiKey' => $this->api,
				'center' => sprintf( 'lonlat:%1$s,%2$s', $this->longitude, $this->latitude ),
				'format' => 'jpeg',
				'width'  => $this->width,
				'height' => $this->height,
				'zoom'   => $this->map_zoom,
				'style'  => $this->style,
				'marker' => sprintf( 'lonlat:%1$s,%2$s;size:small;color:red', $this->longitude, $this->latitude ),
			),
			'https://maps.geoapify.com/v1/staticmap'
		);
		return $map;
	}

	public function get_archive_map( $locations ) {
		if ( empty( $this->api ) || empty( $locations ) ) {
			return '';
		}

		$markers = array();
		foreach ( $locations as $location ) {
			$markers[] = sprintf( 'lonlat:%1$s,%2$s;size:small;color:red', $location[1], $location[0] );
		}

		$map = add_query_arg(
			array(
				'apiKey' => $this->api,
				'center' => sprintf( 'lonlat:%1$s,%2$s', $locations[0][1], $locations[0][0] ),
				'format' => 'jpeg',
				'width'  => $this->width,
				'height' => $this->height,
				'zoom'   => $this->map_zoom,
				'style'  => $this->style,
				'marker' => implode( '|', $markers )
			),
			'https://maps.geoapify.com/v1/staticmap'
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

register_sloc_provider( new Map_Provider_Geoapify() );


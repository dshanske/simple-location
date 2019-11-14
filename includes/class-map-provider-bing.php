<?php
// Bing Map Provider
class Map_Provider_Bing extends Map_Provider {


	public function __construct( $args = array() ) {
		$this->name = __( 'Bing Maps', 'simple-location' );
		$this->slug = 'bing';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_bing_api' );
		}
		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_bing_style' );
		}

		$option = get_option( 'sloc_map_provider' );
		if ( 'bing' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_bing_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'Bing Maps API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
		register_setting(
			'simloc',
			'sloc_bing_style',
			array(
				'type'         => 'string',
				'description'  => 'Bing Map Style',
				'show_in_rest' => false,
				'default'      => 'CanvasLight',
			)
		);
	}

	public static function admin_init() {
		add_settings_field(
			'bingapi', // id
			__( 'Bing API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_bing_api',
			)
		);

		add_settings_field(
			'bingstyle', // id
			__( 'Bing Style', 'simple-location' ),
			array( 'Loc_Config', 'style_callback' ),
			'simloc',
			'sloc_map',
			array(
				'label_for' => 'sloc_bing_style',
				'provider'  => new Map_Provider_Bing(),
			)
		);
	}

	public function get_styles() {
		return array(
			'Aerial'                   => __( 'Aerial Imagery', 'simple-location' ),
			'AerialWithLabels'         => __( 'Aerial Imagery with a Road Overlay', 'simple-location' ),
			'AerialWithLabelsOnDemand' => __( 'Aerial imagery with on-demand road overlay.', 'simple-location' ),
			'CanvasLight'              => __( 'A lighter version of the road maps which also has some of the details such as hill shading disabled.', 'simple-location' ),
			'CanvasDark'               => __( 'A dark version of the road maps.', 'simple-location' ),
			'CanvasGray'               => __( 'A grayscale version of the road maps.', 'simple-location' ),
			'Road'                     => __( 'Roads without additional imagery', 'simple-location' ),
			'Streetside'               => __( 'Street-level imagery', 'simple-location' ),
		);
	}

	// Return code for map
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return '';
		}
		$url = sprintf(
			'https://dev.virtualearth.net/REST/v1/Imagery/Map/%1$s/%2$s,%3$s/%4$s',
			$this->style,
			$this->latitude,
			$this->longitude,
			$this->map_zoom
		);
		$map = add_query_arg(
			array(
				'pushpin' => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
				'mapSize' => sprintf( '%1$s,%2$s', $this->width, $this->height ),
				'key'     => $this->api,
			),
			$url
		);
		return $map;
	}

	public function get_archive_map( $locations ) {
		return '';
	}

	public function get_the_map_url() {
		return sprintf( 'https://bing.com/maps/default.aspx?cp=%1$s,%2$s&lvl=%3$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	// Return code for map
	public function get_the_map( $static = true ) {
		$map  = $this->get_the_static_map();
		$link = $this->get_the_map_url();
		$c    = '<a target="_blank" href="' . $link . '"><img class="sloc-map" src="' . $map . '" /></a>';
		return $c;
	}
}

register_sloc_provider( new Map_Provider_Bing() );

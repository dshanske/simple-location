<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

/**
 * Map Provider using TomTom Map API.
 *
 * @since 1.0.0
 */
class Map_Provider_TomTom extends Map_Provider {


	public function __construct( $args = array() ) {
		$this->name         = __( 'TomTom Maps', 'simple-location' );
		$this->slug         = 'tomtom';
		$this->max_height   = 8192;
		$this->max_width    = 8192;
		$this->max_map_zoom = 22;
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_tomtom_api' );
		}
		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_tomtom_style' );
		}

		$option = get_option( 'sloc_map_provider' );
		if ( 'tomtom' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_tomtom_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'TomTom Maps API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
		register_setting(
			'simloc',
			'sloc_tomtom_style',
			array(
				'type'         => 'string',
				'description'  => 'TomTom Map Style',
				'show_in_rest' => false,
				'default'      => 'main',
			)
		);
	}

	public static function admin_init() {
		add_settings_field(
			'tomtomapi', // id
			__( 'TomTom API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_tomtom_api',
			)
		);

		add_settings_field(
			'tomtomstyle', // id
			__( 'TomTom Style', 'simple-location' ),
			array( 'Loc_Config', 'style_callback' ),
			'simloc',
			'sloc_map',
			array(
				'label_for' => 'sloc_tomtom_style',
				'provider'  => new Map_Provider_TomTom(),
			)
		);
	}

	public function get_styles() {
		return array(
			'main'  => __( 'Default', 'simple-location' ),
			'night' => __( 'Night', 'simple-location' ),
		);
	}

	// Return code for map
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return '';
		}
		$url = 'https://api.tomtom.com/map/1/staticimage';
		$map = add_query_arg(
			array(
				'key'      => $this->api,
				'center'   => sprintf( '%1$s,%2$s', $this->longitude, $this->latitude ),
				'zoom'     => $this->map_zoom,
				'width'    => $this->width,
				'height'   => $this->height,
				'language' => get_bloginfo( 'language' ),
				'layer'    => $this->style,
			),
			$url
		);
		return $map;
	}

	public function get_archive_map( $locations ) {
		return '';
	}

	public function get_the_map_url() {
		return sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=%3$s/%1$s/%2$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	// Return code for map
	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html();
	}
}

register_sloc_provider( new Map_Provider_TomTom() );

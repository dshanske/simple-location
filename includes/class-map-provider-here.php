<?php
// HERE Map Provider
class Map_Provider_Here extends Map_Provider {

	protected $appid;
	protected $type;
	public function __construct( $args = array() ) {
		$this->name = __( 'HERE Maps', 'simple-location' );
		$this->slug = 'here';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_here_api' );
		}
		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_here_style' );
		}
		if ( ! isset( $args['type'] ) ) {
			$this->type = get_option( 'sloc_here_type' );
		}

		$option = get_option( 'sloc_map_provider' );
		if ( 'here' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_here_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'HERE Maps API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
		register_setting(
			'simloc',
			'sloc_here_style',
			array(
				'type'         => 'string',
				'description'  => 'HERE Style',
				'show_in_rest' => false,
				'default'      => 'alps',
			)
		);

		register_setting(
			'simloc',
			'sloc_here_type',
			array(
				'type'         => 'string',
				'description'  => 'HERE Map Scheme Type',
				'show_in_rest' => false,
				'default'      => 0,
			)
		);
	}

	public static function admin_init() {
		add_settings_field(
			'hereapi', // id
			__( 'HERE API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_here_api',
			)
		);
		add_settings_field(
			'herestyle', // id
			__( 'HERE Style', 'simple-location' ),
			array( 'Loc_Config', 'style_callback' ),
			'simloc',
			'sloc_map',
			array(
				'label_for' => 'sloc_here_style',
				'provider'  => new Map_Provider_Here(),
			)
		);

		add_settings_field(
			'heretype', // id
			__( 'HERE Map Scheme Type', 'simple-location' ),
			array( get_called_class(), 'type_callback' ),
			'simloc',
			'sloc_map',
			array(
				'label_for' => 'sloc_here_type',
				'provider'  => new Map_Provider_Here(),
			)
		);
	}

	public static function type_callback( array $args ) {
		$name  = $args['label_for'];
		$types = self::get_types();
		if ( is_wp_error( $types ) ) {
			echo esc_html( $types->get_error_message() );
			return;
		}
		$text = get_option( $name );
		Loc_Config::select_callback( $name, $text, $types );
	}

	public function get_styles() {
		return array(
			'alps'       => __( 'Alps', 'simple-location' ),
			'daisy'      => __( 'Daisy', 'simple-location' ),
			'dreamworks' => __( 'Dreamworks', 'simple-location' ),
			'flame'      => __( 'Flame', 'simple-location' ),
			'fleet'      => __( 'Fleet', 'simple-location' ),
			'mini'       => __( 'Mini', 'simple-location' ),
		);
	}

	public function get_types() {
		return array(
			0 => __( 'Normal map view in day light mode', 'simple-location' ),
			1 => __( 'Satellite map view in day light mode', 'simple-location' ),
			2 => __( 'Terrain map view in day light mode', 'simple-location' ),
			3 => __( 'Satellite map view with streets in day light mode', 'simple-location' ),
			4 => __( 'Normal grey map view with public transit in day light mode', 'simple-location' ),
			5 => __( 'Normal grey map view in day light mode (used for background maps)', 'simple-location' ),
		);
	}

	// Return code for map
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return '';
		}
		$url = 'https://image.maps.ls.hereapi.com/mia/1.6/';
		$map = add_query_arg(
			array(
				'apiKey' => $this->api,
				'f'      => 0,
				'ppi'    => 320,
				'c'      => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
				//'lat'      => $this->latitude,
				//'lon'      => $this->longitude,
				'w'      => $this->width,
				'h'      => $this->height,
				'style'  => $this->style,
				't'      => $this->type,
				'z'      => $this->map_zoom,
			),
			$url
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
		$polyline = Polyline::encode( $locations );

		$url = 'https://image.maps.ls.hereapi.com/mia/1.6/route';
		$map = add_query_arg(
			array(
				'apiKey' => $this->api,
				'style'  => $this->style,
				't'      => $this->type,
				'w'      => $this->width,
				'h'      => $this->height,
				'r0'     => implode( ',', $markers ),
				'm0'     => implode( ',', $markers ),
			),
			$url
		);

		return $map;
	}

	public function get_the_map_url() {
		return sprintf( 'https://wego.here.com/?map=%1$s,%2$s,%3$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	// Return code for map
	public function get_the_map( $static = true ) {
		$map  = $this->get_the_static_map();
		$link = $this->get_the_map_url();
		$c    = '<a target="_blank" href="' . $link . '"><img class="sloc-map" src="' . $map . '" /></a>';
		return $c;
	}

}

register_sloc_provider( new Map_Provider_Here() );


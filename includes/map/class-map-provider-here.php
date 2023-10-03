<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

/**
 * Map Provider using HERE Map API.
 *
 * @since 1.0.0
 */
class Map_Provider_Here extends Map_Provider {
	use Sloc_API_Here;

	protected $type;
	public function __construct( $args = array() ) {
		$this->name         = __( 'HERE Maps', 'simple-location' );
		$this->slug         = 'here';
		$this->url          = 'https://developer.here.com/';
		$this->description  = __( 'HERE offers a free limited plan for up to 30,000 map transactions per month', 'simple-location' );
		$this->max_width    = 2048;
		$this->max_height   = 2048;
		$this->max_map_zoom = 20;

		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_here_api' );
		}
		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_here_style' );
		}
		if ( ! isset( $args['type'] ) ) {
			$this->type = get_option( 'sloc_here_type' );
		}

		parent::__construct( $args );
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

	public static function get_types() {
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
				'c'      => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
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
		$polyline = Sloc_Polyline::encode( $locations );

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
		return $this->get_the_static_map_html();
	}
}

register_sloc_provider( new Map_Provider_Here() );

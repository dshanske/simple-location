<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

/**
 * Map Provider using Bing Map API.
 *
 * @since 1.0.0
 */
class Map_Provider_Bing extends Map_Provider {
	use Sloc_API_Bing;

	public function __construct( $args = array() ) {
		$this->name         = __( 'Bing Maps', 'simple-location' );
		$this->slug         = 'bing';
		$this->url          = 'https://www.bingmapsportal.com/';
		$this->description  = __( 'Bing Static Map API Requires a Bings Maps key...which is available for 125k transactions.', 'simple-location' );
		$this->max_height   = 1500;
		$this->max_width    = 2000;
		$this->max_map_zoom = 22;
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_bing_api' );
		}
		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_bing_style' );
		}

		parent::__construct( $args );
	}

	public function get_styles() {
		return array(
			'Aerial'                   => __( 'Aerial Imagery', 'simple-location' ),
			'AerialWithLabels'         => __( 'Aerial Imagery with a Road Overlay', 'simple-location' ),
			'AerialWithLabelsOnDemand' => __( 'Aerial imagery with on-demand road overlay.', 'simple-location' ),
			'Streetside'               => __( 'Street-level imagery', 'simple-location' ),
			'BirdsEye'                 => __( 'Birds Eye (oblique-angle) imagery', 'simple-location' ),
			'BirdsEyeWithLabels'       => __( 'Birds Eye (oblique-angle) imagery with a road overlay', 'simple-location' ),
			'Road'                     => __( 'Roads without additional imagery', 'simple-location' ),
			'CanvasDark'               => __( 'A dark version of the road maps.', 'simple-location' ),
			'CanvasLight'              => __( 'A lighter version of the road maps which also has some of the details such as hill shading disabled.', 'simple-location' ),
			'CanvasGray'               => __( 'A grayscale version of the road maps.', 'simple-location' ),
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
				'pushpin' => sprintf( '%1$s,%2$s;45', $this->latitude, $this->longitude ),
				'mapSize' => sprintf( '%1$s,%2$s', $this->width, $this->height ),
				'key'     => $this->api,
			),
			$url
		);
		return $map;
	}

	public function get_archive_map( $locations ) {
		if ( empty( $this->api ) || empty( $locations ) ) {
			return '';
		}

		$markers  = array();
		$path     = array();
		$polyline = Sloc_Polyline::encode( $locations );
		$markers  = array();
		foreach ( $locations as $location ) {
			$markers[] = sprintf( '%1$s,%2$s;51', $location[0], $location[1] );
		}
		$map = add_query_arg(
			array(
				'dc'      => sprintf( 'l,,3;enc:%1$s', $polyline ),
				'mapArea' => implode( ',', geo_bounding_box( $locations ) ),
				'key'     => $this->api,
			),
			sprintf( 'https://dev.virtualearth.net/REST/v1/Imagery/Map/%1$s/', $this->style )
		);
		return $map . '&pp=' . implode( '&pp=', $markers );
	}

	public function get_the_map_url() {
		return sprintf( 'https://bing.com/maps/default.aspx?cp=%1$s,%2$s&lvl=%3$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	// Return code for map
	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html();
	}
}

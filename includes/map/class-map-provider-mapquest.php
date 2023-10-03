<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

/**
 * Map Provider using MapQuest API.
 *
 * @since 1.0.0
 */
class Map_Provider_Mapquest extends Map_Provider {
	use Sloc_API_Mapquest;

	public function __construct( $args = array() ) {
		$this->name         = __( 'Mapquest Maps', 'simple-location' );
		$this->slug         = 'mapquest';
		$this->url          = 'https://developer.mapquest.com/';
		$this->description  = __( 'Yes, MapQuest still exists. It offers Geocoding and a Static Map API. Offers a free tier with 15,000 transactions per month. Sign up for an API key', 'simple-location' );
		$this->max_map_zoom = 20;
		$this->max_height   = 1920;
		$this->max_width    = 1920;
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_mapquest_api' );
		}
		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_mapquest_style' );
		}

		parent::__construct( $args );
	}

	public function get_styles() {
		return array(
			'map'   => __( 'Basic Map', 'simple-location' ),
			'hyb'   => __( 'Hybrid', 'simple-location' ),
			'sat'   => __( 'Satellite', 'simple-location' ),
			'light' => __( 'Light', 'simple-location' ),
			'dark'  => __( 'Dark', 'simple-location' ),
		);
	}

	// Return code for map
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return '';
		}
		$url = 'https://www.mapquestapi.com/staticmap/v5/map';
		$map = add_query_arg(
			array(
				'key'       => $this->api,
				'center'    => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
				'size'      => sprintf( '%1$s,%2$s', $this->width, $this->height ),
				'type'      => $this->style,
				'locations' => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
			),
			$url
		);
		return $map;
	}


	public function get_archive_map( $locations ) {
		if ( empty( $this->api ) || empty( $this->style ) || empty( $locations ) ) {
			return '';
		}

		$markers = array();
		foreach ( $locations as $location ) {
			$markers[] = sprintf( '%1$s,%2$s', $location[0], $location[1] );
		}
		$polyline = Sloc_Polyline::encode( $locations );

		$url = 'https://open.mapquestapi.com/staticmap/v5/map';
		$map = add_query_arg(
			array(
				'key'       => $this->api,
				// 'center' => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
				'size'      => sprintf( '%1$s,%2$s', $this->width, $this->height ),
				'type'      => $this->style,
				'locations' => implode( '||', $markers ),
				'shape'     => 'cmp|enc:' . $polyline,
			),
			$url
		);
		return $map;
	}

	public function get_the_map_url() {
		return sprintf( 'https://www.mapquest.com/?center=%1$s,%2$s&zoom=%3$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	// Return code for map
	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html();
	}
}

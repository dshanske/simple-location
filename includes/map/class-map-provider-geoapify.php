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
	use Sloc_API_Geoapify;

	public function __construct( $args = array() ) {
		$this->name        = __( 'GeoApify', 'simple-location' );
		$this->slug        = 'geoapify';
		$this->url         = 'https://www.geoapify.com/';
		$this->description = __( 'GeoApify offers Maps and Geocoding APIs with a free tier of 3000 credits for requests per day. API Key required.', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_geoapify_api' );
		}

		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_geoapify_style' );
		}

		parent::__construct( $args );
	}

	public function get_styles() {
		return array(
			'osm-carto'                => __( 'OSM Carto', 'simple-location' ),
			'osm-bright'               => __( 'OSM Bright', 'simple-location' ),
			'osm-bright-grey'          => __( 'OSM Bright Grey', 'simple-location' ),
			'osm-bright-smooth'        => __( 'OSM Bright Smooth', 'simple-location' ),
			'klokantech-basic'         => __( 'Klokantech Basic', 'simple-location' ),
			'osm-liberty'              => __( 'OSM Liberty', 'simple-location' ),
			'maptiler-3d'              => __( 'Maptiler 3D', 'simple-location' ),
			'toner'                    => __( 'Toner', 'simple-location' ),
			'toner-grey'               => __( 'Toner Grey', 'simple-location' ),
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
				'area'   => 'rect:' . implode( ',', geo_bounding_box( $locations, true ) ),
				'format' => 'jpeg',
				'width'  => $this->width,
				'height' => $this->height,
				'zoom'   => $this->map_zoom,
				'style'  => $this->style,
				'marker' => implode( '|', $markers ),
			),
			'https://maps.geoapify.com/v1/staticmap'
		);
		return $map;
	}

	public function get_the_map_url() {
		return sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=%3$s/%1$s/%2$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	// Return code for map
	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html();
	}
}

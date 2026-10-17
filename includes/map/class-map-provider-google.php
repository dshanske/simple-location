<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map Provider using Google Map API.
 *
 * @since 1.0.0
 */
class Map_Provider_Google extends Map_Provider {
	use Sloc_API_Google;

	public function __construct( $args = array() ) {
		$this->name         = __( 'Google Maps', 'simple-location' );
		$this->url          = 'https://developers.google.com/maps/';
		$this->description  = __( 'Google Maps Platform API key is required, however Google offers a $200 per month credit, which is the equivalent of 28,000 queries. Click Get Started. Make sure to enable the Static Map API. Follow the tutorial', 'simple-location' );
		$this->slug         = 'google';
		$this->max_width    = 640;
		$this->max_height   = 640;
		$this->max_map_zoom = 20;

		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_google_api' );
		}
		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_google_style' );
		}
		// The style setting is only registered, with its default, while Google is the map provider.
		if ( empty( $args['style'] ) ) {
			$args['style'] = 'roadmap';
		}

		parent::__construct( $args );
	}

	public function get_styles() {
		return array(
			'roadmap'   => __( 'Roadmap', 'simple-location' ),
			'satellite' => __( 'Satellite', 'simple-location' ),
			'terrain'   => __( 'Terrain', 'simple-location' ),
			'hybrid'    => __( 'Satellite and Roadmap Hybrid', 'simple-location' ),
		);
	}

	/**
	 * Returns the pixel densities the provider can draw the static map at.
	 *
	 * @since 6.0.0
	 *
	 * @return int[] Pixel densities.
	 */
	public function get_scales() {
		return array( 1, 2 );
	}

	// Return code for map
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return '';
		}
		$url = 'https://maps.googleapis.com/maps/api/staticmap';
		$map = add_query_arg(
			array(
				'markers'  => sprintf( 'color:red|label:X|%1$s,%2$s', $this->latitude, $this->longitude ),
				'size'     => sprintf( '%1$sx%2$s', $this->width, $this->height ),
				'maptype'  => $this->style,
				'language' => get_bloginfo( 'language' ),
				'key'      => $this->api,
				'zoom'     => $this->map_zoom,
				'scale'    => $this->scale,
			),
			$url
		);
		return $map;
	}


	/**
	 * Returns the URL of a static map with a marker at each location and a path joining them.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Uses the roadmap style when none is set, and has no empty marker location.
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return string URL, or an empty string without an API key or locations.
	 */
	public function get_archive_map( $locations ) {
		if ( empty( $this->api ) || empty( $locations ) ) {
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
				'markers'  => 'size:tiny|color:red|label:P|' . implode( '|', $markers ),
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

	/**
	 * Returns a link to the location on Google Maps.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Uses the documented Maps URLs search link over HTTPS.
	 *
	 * @return string URL.
	 */
	public function get_the_map_url() {
		return sprintf( 'https://www.google.com/maps/search/?api=1&query=%1$s%%2C%2$s', $this->latitude, $this->longitude );
	}

	// Return code for map
	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html( true );
	}
}

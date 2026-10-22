<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map Provider using the HERE Map Image API v3.
 *
 * @since 1.0.0
 * @since 6.0.0 Uses Map Image API v3. Map scheme types were replaced by styles, so the type setting is gone.
 */
class Map_Provider_Here extends Map_Provider {
	use Sloc_API_Here;

	/**
	 * Base URL of the Map Image API v3.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const BASE_URL = 'https://image.maps.hereapi.com/mia/v3/base/mc/';

	/**
	 * Width, in pixels, of the whole world at zoom 0 on this provider's static maps.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	protected $tile_size = 256;

	/**
	 * Clickable area of an archive map marker, in pixels from the marker's location. Points are circles centred on the location.
	 *
	 * @since 6.1.0
	 * @var int[]
	 */
	protected $marker_box = array( -10, -10, 10, 10 );

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Uses the HERE map API key when one is set.
	 *
	 * @param array $args Arguments. See Map_Provider::__construct().
	 */
	public function __construct( $args = array() ) {
		$this->name         = __( 'HERE Maps', 'simple-location' );
		$this->slug         = 'here';
		$this->url          = 'https://developer.here.com/';
		$this->description  = __( 'HERE offers a free limited plan for up to 30,000 map transactions per month', 'simple-location' );
		$this->max_width    = 2048;
		$this->max_height   = 2048;
		$this->max_map_zoom = 20;

		if ( ! isset( $args['api'] ) ) {
			$args['api'] = self::get_map_api_key( 'here' );
		}
		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_here_style' );
		}
		// Styles from the retired API, such as alps, aren't valid in v3.
		if ( ! is_string( $args['style'] ) || ! array_key_exists( $args['style'], $this->get_styles() ) ) {
			$args['style'] = 'explore.day';
		}

		parent::__construct( $args );
	}

	/**
	 * Returns the Map Image API v3 styles.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Map Image API v3 styles.
	 *
	 * @return array Styles keyed by ID.
	 */
	public function get_styles() {
		return array(
			'explore.day'             => __( 'Explore (Day)', 'simple-location' ),
			'explore.night'           => __( 'Explore (Night)', 'simple-location' ),
			'explore.satellite.day'   => __( 'Explore Satellite (Day)', 'simple-location' ),
			'lite.day'                => __( 'Lite (Day)', 'simple-location' ),
			'lite.night'              => __( 'Lite (Night)', 'simple-location' ),
			'lite.satellite.day'      => __( 'Lite Satellite (Day)', 'simple-location' ),
			'logistics.day'           => __( 'Logistics (Day)', 'simple-location' ),
			'logistics.night'         => __( 'Logistics (Night)', 'simple-location' ),
			'logistics.satellite.day' => __( 'Logistics Satellite (Day)', 'simple-location' ),
			'satellite.day'           => __( 'Satellite (Day)', 'simple-location' ),
			'topo.day'                => __( 'Topographic (Day)', 'simple-location' ),
			'topo.night'              => __( 'Topographic (Night)', 'simple-location' ),
		);
	}

	/**
	 * Returns the static map image URL, centered on the location with a marker.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Uses Map Image API v3.
	 * @since 6.0.0 Draws a high-density map when the scale is 2.
	 *
	 * @return string URL, or an empty string without an API key.
	 */
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return '';
		}
		$url = sprintf(
			'%1$scenter:%2$s,%3$s;zoom=%4$s/%5$sx%6$s/png8',
			self::BASE_URL,
			$this->latitude,
			$this->longitude,
			$this->map_zoom,
			$this->width * $this->scale,
			$this->height * $this->scale
		);

		$args = array(
			'apiKey'  => $this->api,
			'style'   => $this->style,
			'overlay' => sprintf( 'point:%1$s,%2$s', $this->latitude, $this->longitude ),
		);
		// A high-density map is twice the size at scale 2, showing the same area.
		if ( 1 < $this->scale ) {
			$args['scale'] = $this->scale;
		}
		return add_query_arg( $args, $url );
	}

	/**
	 * Returns the pixel densities the provider can draw the static map at.
	 *
	 * HERE's size limit counts image pixels, so a high-density map is only offered when the doubled size fits.
	 *
	 * @since 6.0.0
	 *
	 * @return int[] Pixel densities.
	 */
	public function get_scales() {
		if ( $this->width * 2 > $this->max_width || $this->height * 2 > $this->max_height ) {
			return array( 1 );
		}
		return array( 1, 2 );
	}

	/**
	 * Returns the position part of an archive map's URL.
	 *
	 * @since 6.1.0
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return string `center:lat,lng;zoom=z` for the plugin's view, or `overlay:padding=32` for HERE to fit the locations.
	 */
	protected function get_archive_position( $locations ) {
		$view = $this->get_archive_view( $locations );
		if ( null === $view ) {
			return 'overlay:padding=32';
		}
		return sprintf( 'center:%1$s,%2$s;zoom=%3$s', $view['latitude'], $view['longitude'], $view['zoom'] );
	}

	/**
	 * Returns a map image URL showing the route through a list of locations, sized to fit them.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Uses Map Image API v3.
	 * @since 6.1.0 Centred and zoomed by the plugin, so its markers can be linked to their posts.
	 * @since 6.1.0 Drawn at the map's pixel density, for high-density images.
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return string URL, or an empty string without an API key or locations.
	 */
	public function get_archive_map( $locations ) {
		if ( empty( $this->api ) || empty( $locations ) ) {
			return '';
		}

		$points = array();
		foreach ( $locations as $location ) {
			$points[] = sprintf( '%1$s,%2$s', $location[0], $location[1] );
		}

		$args = array(
			'apiKey'  => $this->api,
			'style'   => $this->style,
			'overlay' => 'point:' . implode( '|', $points ),
		);
		// A high-density map is twice the size at scale 2, showing the same area.
		if ( 1 < $this->scale ) {
			$args['scale'] = $this->scale;
		}
		$url = add_query_arg(
			$args,
			sprintf( '%1$s%2$s/%3$sx%4$s/png8', self::BASE_URL, $this->get_archive_position( $locations ), $this->width * $this->scale, $this->height * $this->scale )
		);
		// Lines and points are separate overlays, and a line needs at least two points.
		if ( count( $points ) > 1 ) {
			$url .= '&overlay=line:' . implode( ',', $points ) . ';width=4';
		}
		return $url;
	}

	/**
	 * Returns a link to the location on HERE WeGo.
	 *
	 * @return string URL.
	 */
	public function get_the_map_url() {
		return sprintf( 'https://wego.here.com/?map=%1$s,%2$s,%3$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	/**
	 * Returns the map HTML.
	 *
	 * @param boolean $static Unused; the map is always static.
	 * @return string HTML.
	 */
	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html( true );
	}
}

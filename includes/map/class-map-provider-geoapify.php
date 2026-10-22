<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map Provider using Geoapify  Map API.
 *
 * @since 1.0.0
 */
class Map_Provider_Geoapify extends Map_Provider {
	use Sloc_API_GeoApify;

	/**
	 * Geoapify's terms allow storing and caching its static maps, with no time limit, so they are served from the site.
	 *
	 * @since 6.0.0
	 * @var bool
	 */
	protected $cacheable = true;


	/**
	 * Width, in pixels, of the whole world at zoom 0 on this provider's static maps.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	protected $tile_size = 256;

	/**
	 * Constructor.
	 *
	 * @since 4.1.5
	 *
	 * @param array $args Arguments. See Map_Provider::__construct().
	 */
	public function __construct( $args = array() ) {
		$this->name         = __( 'GeoApify', 'simple-location' );
		$this->slug         = 'geoapify';
		$this->url          = 'https://www.geoapify.com/';
		$this->description  = __( 'GeoApify offers Maps and Geocoding APIs with a free tier of 3000 credits for requests per day. API Key required.', 'simple-location' );
		$this->max_map_zoom = 20;
		$this->max_width    = 4096;
		$this->max_height   = 4096;
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_geoapify_api' );
		}

		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_geoapify_style' );
		}

		parent::__construct( $args );
	}

	/**
	 * Returns the map styles the provider offers.
	 *
	 * @since 4.1.5
	 *
	 * @return array Style names keyed by ID.
	 */
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

	/**
	 * Returns the URL of a static map of the location.
	 *
	 * @since 4.1.5
	 *
	 * @return string URL, or an empty string without the settings it needs.
	 */
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for GeoApify', 'simple-location' ) );
		}
		$args = array(
			'apiKey' => $this->api,
			'center' => sprintf( 'lonlat:%1$s,%2$s', $this->longitude, $this->latitude ),
			'format' => 'jpeg',
			'width'  => $this->width,
			'height' => $this->height,
			'zoom'   => $this->map_zoom,
			'style'  => $this->style,
			'marker' => sprintf( 'lonlat:%1$s,%2$s;size:small;color:red', $this->longitude, $this->latitude ),
		);
		if ( 1 < $this->scale ) {
			$args['scaleFactor'] = $this->scale;
		}
		$map = add_query_arg( $args, 'https://maps.geoapify.com/v1/staticmap' );
		return $map;
	}

	/**
	 * Returns the parameters that describe the current map, for a map image served from the site.
	 *
	 * @since 6.1.0
	 *
	 * @param array|null $locations Optional. List of array( latitude, longitude ) points for an archive map.
	 * @return string[] Map parameters. Archive maps have 'options', so images stored before the plugin chose their view
	 *                  aren't served under links placed for the new view.
	 */
	public function get_cache_args( $locations = null ) {
		$args = parent::get_cache_args( $locations );
		if ( null !== $locations ) {
			$args['options'] = 'view';
		}
		return $args;
	}

	/**
	 * Returns the URL of a static map with multiple locations.
	 *
	 * @since 4.1.5
	 * @since 6.1.0 Centred and zoomed by the plugin, so its markers can be linked to their posts.
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return string URL, or an empty string without the settings it needs or without locations.
	 */
	public function get_archive_map( $locations ) {
		if ( empty( $this->api ) || empty( $locations ) ) {
			return '';
		}

		$markers = array();
		foreach ( $locations as $location ) {
			$markers[] = sprintf( 'lonlat:%1$s,%2$s;size:small;color:red', $location[1], $location[0] );
		}

		$args = array(
			'apiKey' => $this->api,
			'format' => 'jpeg',
			'width'  => $this->width,
			'height' => $this->height,
			'zoom'   => $this->map_zoom,
			'style'  => $this->style,
			'marker' => implode( '|', $markers ),
		);
		$view = $this->get_archive_view( $locations );
		if ( null === $view ) {
			$args['area'] = 'rect:' . implode( ',', geo_bounding_box( $locations, true ) );
		} else {
			// Geoapify ignores the centre and zoom when an area is given, so there is none.
			$args['center'] = sprintf( 'lonlat:%1$s,%2$s', $view['longitude'], $view['latitude'] );
			$args['zoom']   = $view['zoom'];
		}
		return add_query_arg( $args, 'https://maps.geoapify.com/v1/staticmap' );
	}

	/**
	 * Returns the URL of a link to the location on a map.
	 *
	 * @since 4.1.5
	 *
	 * @return string URL.
	 */
	public function get_the_map_url() {
		return sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=%3$s/%1$s/%2$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	/**
	 * Returns the HTML for the map, which is always the static map.
	 *
	 * @since 4.1.5
	 *
	 * @param boolean $static Unused; the map is always static.
	 * @return string HTML marked up map.
	 */
	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html( true );
	}
}

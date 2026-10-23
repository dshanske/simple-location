<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map Provider using the Stadia Maps Static Maps API.
 *
 * Maps served from the site use Stadia's cacheable endpoint, whose images may be stored for as long as the account has
 * a paid subscription. Otherwise maps load from Stadia's standard endpoint, which may not be stored.
 *
 * @since 6.1.0
 */
class Map_Provider_Stadia extends Map_Provider {
	use Sloc_API_Stadia;

	/**
	 * Image formats the provider can be asked for.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const FORMATS = array( 'png', 'jpg' );

	/**
	 * Image format the plugin asks for when none is chosen.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const DEFAULT_FORMAT = 'png';

	/**
	 * Stadia's terms allow storing images from its cacheable endpoint, with no time limit, while the account has a paid
	 * subscription.
	 *
	 * @since 6.1.0
	 * @var bool
	 */
	protected $cacheable = true;

	/**
	 * Width, in pixels, of the whole world at zoom 0 on this provider's static maps. Stadia draws 512-pixel tiles.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	protected $tile_size = 512;

	/**
	 * Whether the provider's static maps accept fractional zoom levels.
	 *
	 * @since 6.1.0
	 * @var bool
	 */
	protected $fractional_zoom = true;

	/**
	 * Constructor.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args Arguments. See Map_Provider::__construct().
	 */
	public function __construct( $args = array() ) {
		$this->name         = __( 'Stadia Maps', 'simple-location' );
		$this->slug         = 'stadia';
		$this->url          = 'https://stadiamaps.com/';
		$this->description  = __( 'Stadia Maps\' static maps need a paid plan. With an API key, maps are stored on this site from Stadia\'s cacheable endpoint, which costs more credits per map but may be kept while you have a paid subscription. Otherwise maps load from Stadia, without a key if you add this site\'s domain in Stadia\'s client dashboard.', 'simple-location' );
		$this->max_map_zoom = 18;
		$this->max_width    = 4096;
		$this->max_height   = 4096;
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_stadia_api' );
		}

		if ( ! isset( $args['style'] ) ) {
			$args['style'] = $this->get_style_setting( 'sloc_stadia_style' );
		}

		parent::__construct( $args );
	}

	/**
	 * Returns the provider's style for each style family.
	 *
	 * @since 6.1.0
	 *
	 * @return string[] Style IDs keyed by family. See Map_Provider::get_family_styles().
	 */
	public function get_family_styles() {
		return array(
			'standard'  => 'alidade_smooth',
			'light'     => 'stamen_toner_lite',
			'dark'      => 'alidade_smooth_dark',
			'satellite' => 'alidade_satellite',
			'terrain'   => 'outdoors',
		);
	}

	/**
	 * Returns the map styles the provider offers.
	 *
	 * @since 6.1.0
	 *
	 * @return array Style names keyed by ID.
	 */
	public function get_styles() {
		return array(
			'alidade_smooth'      => __( 'Alidade Smooth', 'simple-location' ),
			'alidade_smooth_dark' => __( 'Alidade Smooth Dark', 'simple-location' ),
			'alidade_bright'      => __( 'Alidade Bright', 'simple-location' ),
			'alidade_satellite'   => __( 'Alidade Satellite', 'simple-location' ),
			'outdoors'            => __( 'Stadia Outdoors', 'simple-location' ),
			'osm_bright'          => __( 'OSM Bright', 'simple-location' ),
			'stamen_toner'        => __( 'Stamen Toner', 'simple-location' ),
			'stamen_toner_lite'   => __( 'Stamen Toner Lite', 'simple-location' ),
			'stamen_terrain'      => __( 'Stamen Terrain', 'simple-location' ),
			'stamen_watercolor'   => __( 'Stamen Watercolor', 'simple-location' ),
		);
	}

	/**
	 * Returns the pixel densities the provider can draw the static map at.
	 *
	 * @since 6.1.0
	 *
	 * @return int[] Pixel densities.
	 */
	public function get_scales() {
		return array( 1, 2 );
	}

	/**
	 * Whether the site may store the provider's map images and serve them itself.
	 *
	 * Only the cacheable endpoint's images may be stored, and the site can only fetch them with an API key, because
	 * Stadia's domain authentication needs a browser.
	 *
	 * @since 6.1.0
	 *
	 * @return bool True with an API key.
	 */
	public function is_cacheable() {
		return parent::is_cacheable() && is_string( $this->api ) && '' !== $this->api;
	}

	/**
	 * Returns the URL of a static map image.
	 *
	 * Maps served from the site come from the cacheable endpoint. Others come from the standard endpoint, with the
	 * API key when there is one, or with none for Stadia's domain authentication.
	 *
	 * @since 6.1.0
	 *
	 * @param array    $args    Query parameters other than the markers and the key.
	 * @param string[] $markers Markers, as Stadia's comma-separated marker strings.
	 * @return string URL.
	 */
	protected function build_url( $args, $markers ) {
		$cached = Sloc_Map_Cache::is_enabled( $this );
		$url    = sprintf(
			'https://tiles.stadiamaps.com/%1$s/%2$s.%3$s',
			$cached ? 'static_cacheable' : 'static',
			rawurlencode( (string) $this->style ),
			$this->get_format()
		);
		$args['size'] = sprintf( '%1$sx%2$s%3$s', $this->width, $this->height, 1 < $this->scale ? '@' . $this->scale . 'x' : '' );
		if ( 'below' === $this->attribution ) {
			$args['manual_attribution'] = 'true';
		}
		if ( ! empty( $this->api ) ) {
			$args['api_key'] = $this->api;
		}
		$query = array();
		foreach ( $args as $key => $value ) {
			$query[] = rawurlencode( $key ) . '=' . rawurlencode( (string) $value );
		}
		// Each marker is its own m parameter.
		foreach ( $markers as $marker ) {
			$query[] = 'm=' . rawurlencode( $marker );
		}
		return $url . '?' . implode( '&', $query );
	}

	/**
	 * Returns a marker in Stadia's marker syntax.
	 *
	 * Stadia draws its pin in the map style's design, or a smaller one; it has no dot.
	 *
	 * @since 6.1.0
	 *
	 * @param float  $latitude  Latitude.
	 * @param float  $longitude Longitude.
	 * @param string $size      Marker size, one of MARKER_SIZES.
	 * @return string Marker.
	 */
	protected function get_marker( $latitude, $longitude, $size ) {
		return sprintf(
			'%1$s,%2$s,%3$s,%4$s',
			$latitude,
			$longitude,
			'small' === $size ? $this->style . '_sm' : '',
			$this->get_marker_hex()
		);
	}

	/**
	 * Returns the URL of a static map of the location.
	 *
	 * @since 6.1.0
	 *
	 * @return string URL, or an empty string without a style.
	 */
	public function get_the_static_map() {
		if ( empty( $this->style ) ) {
			return '';
		}
		return $this->build_url(
			array(
				'center' => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
				'zoom'   => $this->map_zoom,
			),
			array( $this->get_marker( $this->latitude, $this->longitude, $this->marker_size ) )
		);
	}

	/**
	 * Returns the URL of a static map with multiple locations.
	 *
	 * The plugin centres and zooms the map, so its markers can be linked to their posts. If it can't, Stadia fits the
	 * map to the markers. A track has markers only at its ends, or none, and is drawn as a line.
	 *
	 * @since 6.1.0
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return string URL, or an empty string without a style or without locations.
	 */
	public function get_archive_map( $locations ) {
		if ( empty( $this->style ) || empty( $locations ) ) {
			return '';
		}
		$markers = array();
		foreach ( $this->get_marker_locations( $locations ) as $location ) {
			$markers[] = $this->get_marker( $location[0], $location[1], 'medium' );
		}
		$args = array();
		// A track is a line, as an encoded polyline with five decimal places.
		if ( $this->track && count( $locations ) > 1 ) {
			$args['l']              = sprintf( '%1$s,%2$s,3', Sloc_Polyline::encode( $locations ), $this->get_marker_hex() );
			$args['line_precision'] = 5;
		}
		$view = $this->get_archive_view( $locations );
		if ( null !== $view ) {
			$args['center'] = sprintf( '%1$s,%2$s', $view['latitude'], $view['longitude'] );
			$args['zoom']   = $view['zoom'];
		}
		return $this->build_url( $args, $markers );
	}

	/**
	 * Returns the map's credit to show under the map, when the attribution setting puts it there.
	 *
	 * These are the credits Stadia asks for with each style.
	 *
	 * @since 6.1.0
	 *
	 * @return string HTML of a paragraph with the credit, or an empty string if the credit is on the image.
	 */
	public function get_attribution_html() {
		if ( 'below' !== $this->attribution ) {
			return '';
		}
		$style   = (string) $this->style;
		$credits = array(
			sprintf( '<a href="https://stadiamaps.com/attribution/">%s</a>', esc_html__( '© Stadia Maps', 'simple-location' ) ),
		);
		if ( 0 === strpos( $style, 'stamen_' ) ) {
			$credits[] = sprintf( '<a href="https://stamen.com/">%s</a>', esc_html__( '© Stamen Design', 'simple-location' ) );
		}
		// Watercolor is drawn without OpenMapTiles' data.
		if ( 'stamen_watercolor' !== $style ) {
			$credits[] = sprintf( '<a href="https://openmaptiles.org/">%s</a>', esc_html__( '© OpenMapTiles', 'simple-location' ) );
		}
		$credits[] = sprintf( '<a href="https://www.openstreetmap.org/copyright">%s</a>', esc_html__( '© OpenStreetMap', 'simple-location' ) );
		if ( 'alidade_satellite' === $style ) {
			$credits[] = esc_html__( '© CNES, Distribution Airbus DS, © Airbus DS, © PlanetObserver (Contains Copernicus Data)', 'simple-location' );
		}
		return '<p class="sloc-map-attribution">' . implode( ' ', $credits ) . '</p>';
	}

	/**
	 * Returns the URL of a link to the location on a map.
	 *
	 * @since 6.1.0
	 *
	 * @return string URL.
	 */
	public function get_the_map_url() {
		return sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=%3$s/%1$s/%2$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	/**
	 * Returns the HTML for the map, which is always the static map, followed by the map's credit when the attribution
	 * setting puts it under the map.
	 *
	 * @since 6.1.0
	 *
	 * @param boolean $static Unused; the map is always static.
	 * @return string HTML marked up map.
	 */
	public function get_the_map( $static = true ) {
		$map = $this->get_the_static_map_html( true );
		return '' === $map ? '' : $map . $this->get_attribution_html();
	}
}

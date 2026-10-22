<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map Provider using MapQuest API.
 *
 * @since 1.0.0
 */
class Map_Provider_Mapquest extends Map_Provider {
	use Sloc_API_MapQuest;

	/**
	 * Image formats the provider can be asked for.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const FORMATS = array( 'png', 'jpg', 'gif' );

	/**
	 * Image format the plugin asks for when none is chosen.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const DEFAULT_FORMAT = 'jpg';

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
	 * @since 1.0.0
	 * @since 6.0.0 Uses the MapQuest map API key when one is set.
	 * @since 6.0.0 Uses the basic map style when none is set.
	 *
	 * @param array $args Arguments. See Map_Provider::__construct().
	 */
	public function __construct( $args = array() ) {
		$this->name         = __( 'Mapquest Maps', 'simple-location' );
		$this->slug         = 'mapquest';
		$this->url          = 'https://developer.mapquest.com/';
		$this->description  = __( 'Yes, MapQuest still exists. It offers Geocoding and a Static Map API. Offers a free tier with 15,000 transactions per month. Sign up for an API key', 'simple-location' );
		$this->max_map_zoom = 20;
		$this->max_height   = 1920;
		$this->max_width    = 1920;
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = self::get_map_api_key( 'mapquest' );
		}
		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_mapquest_style' );
		}
		// The style setting is only registered, with its default, while MapQuest is the map provider.
		if ( empty( $args['style'] ) ) {
			$args['style'] = 'map';
		}

		parent::__construct( $args );
	}

	/**
	 * Returns the map styles the provider offers.
	 *
	 * @since 3.4.0
	 *
	 * @return array Style names keyed by ID.
	 */
	public function get_styles() {
		return array(
			'map'   => __( 'Basic Map', 'simple-location' ),
			'hyb'   => __( 'Hybrid', 'simple-location' ),
			'sat'   => __( 'Satellite', 'simple-location' ),
			'light' => __( 'Light', 'simple-location' ),
			'dark'  => __( 'Dark', 'simple-location' ),
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
	 * @since 3.4.0
	 * @since 6.1.0 Drawn at the map's zoom level instead of MapQuest's default.
	 * @since 6.1.0 Uses the image format setting.
	 *
	 * @return string URL, or an empty string without the settings it needs.
	 */
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return '';
		}
		$url = 'https://www.mapquestapi.com/staticmap/v5/map';
		$map = add_query_arg(
			array(
				'key'       => $this->api,
				'center'    => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
				'size'      => sprintf( '%1$s,%2$s%3$s', $this->width, $this->height, ( 2 === $this->scale ) ? '@2x' : '' ),
				'zoom'      => $this->map_zoom,
				'type'      => $this->style,
				'locations' => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
			),
			$url
		);
		if ( '' !== $this->format ) {
			$map = add_query_arg( 'format', $this->get_vendor_format(), $map );
		}
		return $map;
	}


	/**
	 * Returns the URL of a static map with multiple locations.
	 *
	 * @since 4.0.0
	 * @since 6.1.0 Centred and zoomed by the plugin, so its markers can be linked to their posts.
	 * @since 6.1.0 Drawn at the map's pixel density, for high-density images.
	 * @since 6.1.0 Uses the image format setting.
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return string URL, or an empty string without the settings it needs or without locations.
	 */
	public function get_archive_map( $locations ) {
		if ( empty( $this->api ) || empty( $this->style ) || empty( $locations ) ) {
			return '';
		}

		$markers = array();
		foreach ( $locations as $location ) {
			$markers[] = sprintf( '%1$s,%2$s', $location[0], $location[1] );
		}
		$polyline = Sloc_Polyline::encode( $locations );

		$url  = 'https://www.mapquestapi.com/staticmap/v5/map';
		$args = array(
			'key'       => $this->api,
			'size'      => sprintf( '%1$s,%2$s%3$s', $this->width, $this->height, ( 2 === $this->scale ) ? '@2x' : '' ),
			'type'      => $this->style,
			'locations' => implode( '||', $markers ),
			'shape'     => 'cmp|enc:' . $polyline,
		);
		$view = $this->get_archive_view( $locations );
		if ( null !== $view ) {
			$args['center'] = sprintf( '%1$s,%2$s', $view['latitude'], $view['longitude'] );
			$args['zoom']   = $view['zoom'];
		}
		if ( '' !== $this->format ) {
			$args['format'] = $this->get_vendor_format();
		}
		$map = add_query_arg( $args, $url );
		return $map;
	}

	/**
	 * Returns the URL of a link to the location on a map.
	 *
	 * @since 3.4.0
	 *
	 * @return string URL.
	 */
	public function get_the_map_url() {
		return sprintf( 'https://www.mapquest.com/?center=%1$s,%2$s&zoom=%3$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	/**
	 * Returns the HTML for the map, which is always the static map.
	 *
	 * @since 3.4.0
	 *
	 * @param boolean $static Unused; the map is always static.
	 * @return string HTML marked up map.
	 */
	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html( true );
	}
}

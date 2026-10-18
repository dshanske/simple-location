<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map Provider using a fork of a Static  Map API found at https://github.com/dshanske/Static-Maps-API-PHP and must be self-hosted.
 *
 * @since 1.0.0
 */
class Map_Provider_StaticMap extends Map_Provider {
	/**
	 * Largest map width and height requested by default.
	 *
	 * The map server has no published limit. Its work grows with the image size, as it fetches every tile the map covers.
	 *
	 * @since 6.0.0
	 * @var int
	 */
	const DEFAULT_MAX_SIZE = 1280;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 The map size is limited to 1280 by 1280, or to SLOC_STATICMAP_MAX_WIDTH and SLOC_STATICMAP_MAX_HEIGHT when they are defined.
	 * @since 6.0.0 A map server URL passed as the `api` argument is used instead of the setting.
	 *
	 * @param array $args Arguments. See Map_Provider::__construct().
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Custom Static Map Provider', 'simple-location' );
		$this->slug        = 'staticmap';
		$this->url         = 'https://github.com/dshanske/Static-Maps-API-PHP';
		$this->description = __( 'A hosted instance of a Static Map generator that uses tiles', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_staticmap_url' );
		}

		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_staticmap_style' );
		}

		$max_size = array(
			'width'  => defined( 'SLOC_STATICMAP_MAX_WIDTH' ) ? (int) SLOC_STATICMAP_MAX_WIDTH : self::DEFAULT_MAX_SIZE,
			'height' => defined( 'SLOC_STATICMAP_MAX_HEIGHT' ) ? (int) SLOC_STATICMAP_MAX_HEIGHT : self::DEFAULT_MAX_SIZE,
		);

		/**
		 * Filters the largest map size requested from the custom static map server.
		 *
		 * Maps larger than this are shrunk to fit, keeping their shape. A value of 0 removes that limit.
		 *
		 * @since 6.0.0
		 *
		 * @param array $max_size {
		 *     Largest map size, in pixels.
		 *
		 *     @type int $width  Width. Default 1280, or SLOC_STATICMAP_MAX_WIDTH if defined.
		 *     @type int $height Height. Default 1280, or SLOC_STATICMAP_MAX_HEIGHT if defined.
		 * }
		 */
		$max_size = apply_filters( 'sloc_staticmap_max_size', $max_size );

		$this->max_width  = isset( $max_size['width'] ) ? absint( $max_size['width'] ) : self::DEFAULT_MAX_SIZE;
		$this->max_height = isset( $max_size['height'] ) ? absint( $max_size['height'] ) : self::DEFAULT_MAX_SIZE;

		parent::__construct( $args );
	}

	/**
	 * Registers the map server URL and style settings.
	 *
	 * @since 4.1.6
	 */
	public static function init() {
		self::register_settings_api( __( 'Custom Static Map URL', 'simple-location' ), 'sloc_staticmap_url' );

		register_setting(
			'simloc',
			'sloc_staticmap_style',
			array(
				'type'         => 'string',
				'description'  => 'Custom Static Map Style',
				'show_in_rest' => false,
				'default'      => 'osm',
			)
		);
	}

	/**
	 * Adds the map server URL and style fields to the settings page.
	 *
	 * @since 4.1.6
	 */
	public static function admin_init() {
		self::add_settings_url_parameter( __( 'Custom Static Map', 'simple-location' ), 'sloc_staticmap_url' );

		add_settings_field(
			'staticmapstyle', // id
			__( 'Static Map Style', 'simple-location' ),
			array( 'Loc_Config', 'style_callback' ),
			'simloc',
			'sloc_map',
			array(
				'label_for' => 'sloc_staticmap_style',
				'provider'  => new Map_Provider_StaticMap(),
			)
		);
	}


	/**
	 * Returns the map styles the provider offers.
	 *
	 * @since 4.1.6
	 *
	 * @return array Style names keyed by ID.
	 */
	public function get_styles() {
		return array(
			'osm'                       => __( 'OpenStreetMap', 'simple-location' ),
			'otm'                       => __( 'OpenTopoMap', 'simple-location' ),
			'stamen-toner'              => __( 'Stamen Toner', 'simple-location' ),
			'stamen-toner-background'   => __( 'Stamen Toner without Labels', 'simple-location' ),
			'stamen-toner-lite'         => __( 'Stamen Toner Light with Labels', 'simple-location' ),
			'stamen-terrain'            => __( 'Stamen Terrain with Labels', 'simple-location' ),
			'stamen-terrain-background' => __( 'Stamen Terrain without Labels', 'simple-location' ),
			'stamen-watercolor'         => __( 'Stamen Watercolor', 'simple-location' ),
			'carto-light'               => __( 'Carto Light', 'simple-location' ),
			'carto-dark'                => __( 'Carto Dark', 'simple-location' ),
			'carto-voyager'             => __( 'Carto Voyager', 'simple-location' ),
			'streets'                   => __( 'Default Esri street basemap', 'simple-location' ),
			'satellite'                 => __( 'Esris satellite basemap', 'simple-location' ),
			'hybrid'                    => __( 'Satellite basemap with labels', 'simple-location' ),
			'topo'                      => __( 'Esri topographic map', 'simple-location' ),
			'gray'                      => __( 'Esri gray canvas with labels', 'simple-location' ),
			'gray-background'           => __( 'Esri gray canvas without labels', 'simple-location' ),
			'oceans'                    => __( 'Esri ocean basemap', 'simple-location' ),
			'national-geographic'       => __( 'National Geographic basemap', 'simple-location' ),
		);
	}

	/**
	 * Returns which attribution the style needs.
	 *
	 * @since 4.1.6
	 *
	 * @return string|null 'esri' for Esri styles, 'osm' for OpenStreetMap, or null for the others.
	 */
	private function get_attribution() {
		if ( in_array(
			$this->style,
			array( 'streets', 'satellite', 'hybrid', 'topo', 'gray', 'gray-background', 'oceans', 'national-geographic' ),
			true
		) ) {
			return 'esri';
		} elseif ( 'osm' === $this->style ) {
			return 'osm';
		}
	}

	/**
	 * Returns the URL of a static map of the location.
	 *
	 * @since 4.1.6
	 *
	 * @return string URL, or an empty string without the settings it needs.
	 */
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_url', __( 'You have not set a URL for your custom map endpoint', 'simple-location' ) );
		}
		$map = add_query_arg(
			array(
				'width'       => $this->width,
				'height'      => $this->height,
				'zoom'        => $this->map_zoom,
				'basemap'     => $this->style,
				'latitude'    => $this->latitude,
				'longitude'   => $this->longitude,
				'marker'      => sprintf( 'lat:%1$s;lng:%2$s;icon:small-red-cutout', $this->latitude, $this->longitude ),
				'attribution' => $this->get_attribution(),
			),
			$this->api
		);
		return $map;
	}

	/**
	 * Returns the URL of a static map with multiple locations.
	 *
	 * @since 4.1.6
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return string URL, or an empty string without the settings it needs or without locations.
	 */
	public function get_archive_map( $locations ) {
		if ( empty( $this->api ) || empty( $locations ) ) {
			return '';
		}

		$markers = array();
		$path    = array();
		foreach ( $locations as $location ) {
			$markers[] = sprintf( 'lat:%1$s;lng:%2$s;icon:dot-small-red', $location[0], $location[1] );
			$path[]    = sprintf( '[%1$s,%2$s]', $location[1], $location[0] );
		}

		$map = add_query_arg(
			array(
				'width'       => $this->width,
				'height'      => $this->height,
				'basemap'     => $this->style,
				'attribution' => $this->get_attribution(),
				'path[]'      => implode( ',', $path ),
			),
			$this->api
		);
		$map = $map . '&marker[]=' . implode( '&marker[]=', $markers );
		return $map;
	}

	/**
	 * Returns the URL of a link to the location on a map.
	 *
	 * @since 4.1.6
	 *
	 * @return string URL.
	 */
	public function get_the_map_url() {
		return sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=%3$s/%1$s/%2$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	/**
	 * Returns the HTML for the map, which is always the static map.
	 *
	 * @since 4.1.6
	 *
	 * @param boolean $static Unused; the map is always static.
	 * @return string HTML marked up map.
	 */
	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html( true );
	}
}

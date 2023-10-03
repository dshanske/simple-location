<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

/**
 * Map Provider using a fork of a Static  Map API found at https://github.com/dshanske/Static-Maps-API-PHP and must be self-hosted.
 *
 * @since 1.0.0
 */
class Map_Provider_StaticMap extends Map_Provider {
	public function __construct( $args = array() ) {
		$this->name        = __( 'Custom Static Map Provider', 'simple-location' );
		$this->slug        = 'staticmap';
		$this->url         = 'https://github.com/dshanske/Static-Maps-API-PHP';
		$this->description = __( 'A hosted instance of a Static Map generator that uses tiles', 'simple-location' );
		if ( ! isset( $args['url'] ) ) {
			$args['api'] = get_option( 'sloc_staticmap_url' );
		}

		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_staticmap_style' );
		}

		parent::__construct( $args );
	}

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

	private function get_attribution() {
		if ( in_array(
			$this->style,
			array( 'streets', 'satellite', 'hybrid', 'topo', 'gray', 'gray-background', 'oceans', 'national-geographic' )
		) ) {
			return 'esri';
		} elseif ( 'osm' === $this->style ) {
			return 'osm';
		}
	}

	// Return code for map
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

	public function get_the_map_url() {
		return sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=%3$s/%1$s/%2$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	// Return code for map
	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html();
	}
}

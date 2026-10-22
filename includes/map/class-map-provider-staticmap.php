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
 * @since 6.1.0 Requires version 1.0.0 or later of the map server.
 */
class Map_Provider_StaticMap extends Map_Provider {
	/**
	 * Largest map width and height requested when the map server's own limits aren't known.
	 *
	 * Version 1.0.0 of the map server limits maps to 1280 by 1280 pixels unless its install changes that.
	 *
	 * @since 6.0.0
	 * @var int
	 */
	const DEFAULT_MAX_SIZE = 1280;

	/**
	 * How long, in seconds, answers from the map server's query API are kept.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const CACHE_TIME = 6 * HOUR_IN_SECONDS;

	/**
	 * How long, in seconds, a failed request to the map server's query API is remembered before it is tried again.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const ERROR_CACHE_TIME = 15 * MINUTE_IN_SECONDS;

	/**
	 * The provider and style each basemap ID of map servers before 1.0.0 is now called.
	 *
	 * These are the IDs the style setting stored before 6.1.0. The Stamen basemaps were removed from the map server, as
	 * their tiles no longer exist, so they map to the server's default map.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const BASEMAPS = array(
		'osm'                       => 'osm/streets',
		'otm'                       => 'opentopomap/topo',
		'streets'                   => 'esri/streets',
		'satellite'                 => 'esri/satellite',
		'hybrid'                    => 'esri/hybrid',
		'topo'                      => 'esri/topo',
		'gray'                      => 'esri/light',
		'gray-background'           => 'esri/light-nolabels',
		'oceans'                    => 'esri/oceans',
		'national-geographic'       => 'esri/natgeo',
		'carto-light'               => 'carto/light',
		'carto-dark'                => 'carto/dark',
		'carto-voyager'             => 'carto/streets',
		'stamen-toner'              => '',
		'stamen-toner-background'   => '',
		'stamen-toner-lite'         => '',
		'stamen-terrain'            => '',
		'stamen-terrain-background' => '',
		'stamen-watercolor'         => '',
	);

	/**
	 * Providers and styles offered when the map server's list can't be fetched: those version 1.0.0 ships without keys.
	 *
	 * @since 6.1.0
	 * @var array
	 */
	const FALLBACK_PROVIDERS = array(
		'osm'         => array(
			'name'   => 'OpenStreetMap',
			'styles' => array(
				'streets' => 'OpenStreetMap',
			),
		),
		'esri'        => array(
			'name'   => 'Esri',
			'styles' => array(
				'streets'        => 'World Street Map',
				'satellite'      => 'World Imagery',
				'hybrid'         => 'World Imagery with labels',
				'topo'           => 'World Topographic Map',
				'light'          => 'Light Gray Canvas',
				'light-nolabels' => 'Light Gray Canvas without labels',
				'oceans'         => 'Oceans',
				'natgeo'         => 'National Geographic World Map',
				'dark'           => 'Dark Gray Canvas',
				'dark-nolabels'  => 'Dark Gray Canvas without labels',
				'relief'         => 'World Shaded Relief',
				'physical'       => 'World Physical Map',
				'terrain'        => 'World Terrain with labels',
			),
		),
		'osm-fr'      => array(
			'name'   => 'OpenStreetMap France',
			'styles' => array(
				'streets'      => 'OpenStreetMap France',
				'humanitarian' => 'Humanitarian',
				'cycle'        => 'CyclOSM',
			),
		),
		'osm-de'      => array(
			'name'   => 'OpenStreetMap Germany',
			'styles' => array(
				'streets' => 'OpenStreetMap Germany',
			),
		),
		'opentopomap' => array(
			'name'   => 'OpenTopoMap',
			'styles' => array(
				'topo' => 'OpenTopoMap',
			),
		),
		'usgs'        => array(
			'name'   => 'USGS The National Map',
			'styles' => array(
				'topo'      => 'US Topo',
				'satellite' => 'USGS Imagery',
				'hybrid'    => 'USGS Imagery with topo',
			),
		),
		'nasa-gibs'   => array(
			'name'   => 'NASA GIBS',
			'styles' => array(
				'bluemarble' => 'Blue Marble, shaded relief and bathymetry',
			),
		),
		'swisstopo'   => array(
			'name'   => 'swisstopo',
			'styles' => array(
				'topo'      => 'National map (colour)',
				'topo-gray' => 'National map (grey)',
				'satellite' => 'SWISSIMAGE',
			),
		),
		'ign'         => array(
			'name'   => 'IGN (France)',
			'styles' => array(
				'streets'   => 'Plan IGN',
				'satellite' => 'Orthophotos',
			),
		),
		'basemap-at'  => array(
			'name'   => 'basemap.at',
			'styles' => array(
				'streets'   => 'basemap.at',
				'gray'      => 'basemap.at grau',
				'satellite' => 'basemap.at Orthofoto',
				'hybrid'    => 'basemap.at Orthofoto with labels',
			),
		),
	);

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 The map size is limited to 1280 by 1280, or to SLOC_STATICMAP_MAX_WIDTH and SLOC_STATICMAP_MAX_HEIGHT when they are defined.
	 * @since 6.0.0 A map server URL passed as the `api` argument is used instead of the setting.
	 * @since 6.1.0 The map size is limited when the map is drawn, to the map server's own limits when they are known.
	 * @since 6.1.0 The style is a provider and style ID, `provider/style`. Basemap IDs from before 6.1.0 are converted.
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
		$args['style'] = self::sanitize_style( $args['style'] );

		// Providers are created on every request, so the map server's limits are only looked up when a map is drawn.
		$this->max_width  = 0;
		$this->max_height = 0;

		parent::__construct( $args );
	}

	/**
	 * Sets the largest map size requested from the map server.
	 *
	 * @since 6.1.0
	 *
	 * @param array|WP_Error|null $info The map server's description from its query API, or an error or null if it isn't known.
	 */
	protected function set_max_size( $info ) {
		$limits = is_array( $info ) && isset( $info['limits'] ) && is_array( $info['limits'] ) ? $info['limits'] : array();

		$max_size = array(
			'width'  => isset( $limits['max_width'] ) && is_numeric( $limits['max_width'] ) ? (int) $limits['max_width'] : self::DEFAULT_MAX_SIZE,
			'height' => isset( $limits['max_height'] ) && is_numeric( $limits['max_height'] ) ? (int) $limits['max_height'] : self::DEFAULT_MAX_SIZE,
		);
		if ( defined( 'SLOC_STATICMAP_MAX_WIDTH' ) ) {
			$max_size['width'] = (int) SLOC_STATICMAP_MAX_WIDTH;
		}
		if ( defined( 'SLOC_STATICMAP_MAX_HEIGHT' ) ) {
			$max_size['height'] = (int) SLOC_STATICMAP_MAX_HEIGHT;
		}

		/**
		 * Filters the largest map size requested from the custom static map server.
		 *
		 * Maps larger than this are shrunk to fit, keeping their shape. A value of 0 removes that limit.
		 *
		 * @since 6.0.0
		 * @since 6.1.0 The default is the map server's own limit, when it is known.
		 *
		 * @param array $max_size {
		 *     Largest map size, in pixels.
		 *
		 *     @type int $width  Width. Default the map server's limit, or 1280 if it isn't known; SLOC_STATICMAP_MAX_WIDTH if defined.
		 *     @type int $height Height. Default the map server's limit, or 1280 if it isn't known; SLOC_STATICMAP_MAX_HEIGHT if defined.
		 * }
		 */
		$max_size = apply_filters( 'sloc_staticmap_max_size', $max_size );

		$this->max_width  = isset( $max_size['width'] ) ? absint( $max_size['width'] ) : self::DEFAULT_MAX_SIZE;
		$this->max_height = isset( $max_size['height'] ) ? absint( $max_size['height'] ) : self::DEFAULT_MAX_SIZE;
	}

	/**
	 * Registers the map server URL and style settings.
	 *
	 * @since 4.1.6
	 * @since 6.1.0 The style setting is sanitized to a provider and style ID, and defaults to the map server's default map.
	 */
	public static function init() {
		self::register_settings_api( __( 'Custom Static Map URL', 'simple-location' ), 'sloc_staticmap_url' );

		register_setting(
			'simloc',
			'sloc_staticmap_style',
			array(
				'type'              => 'string',
				'description'       => 'Custom Static Map Style',
				'show_in_rest'      => false,
				'default'           => '',
				'sanitize_callback' => array( __CLASS__, 'sanitize_style' ),
			)
		);
	}

	/**
	 * Adds the map server URL and style fields to the settings page.
	 *
	 * @since 4.1.6
	 * @since 6.1.0 The style field warns when the map server doesn't answer its query API.
	 */
	public static function admin_init() {
		self::add_settings_url_parameter( __( 'Custom Static Map', 'simple-location' ), 'sloc_staticmap_url' );

		add_settings_field(
			'staticmapstyle', // id
			__( 'Static Map Style', 'simple-location' ),
			array( __CLASS__, 'style_field' ),
			'simloc',
			'sloc_map',
			array(
				'label_for' => 'sloc_staticmap_style',
				'provider'  => new Map_Provider_StaticMap(),
			)
		);
	}

	/**
	 * Echoes the style field, with a warning if the map server's query API can't be reached.
	 *
	 * The settings page fetches the map server's list again, so changes to the server show up there right away.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args {
	 *     Arguments for the field.
	 *
	 *     @type string                  $label_for Option name.
	 *     @type Map_Provider_StaticMap  $provider  Provider.
	 * }
	 */
	public static function style_field( array $args ) {
		$provider = $args['provider'];
		$info     = $provider->get_api( '', true );
		$provider->get_api( 'providers', true );
		Loc_Config::style_callback( $args );
		if ( is_wp_error( $info ) && 'missing_url' !== $info->get_error_code() ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: Error message. */
						__( 'The custom map server did not answer its query API, so the styles listed are the ones every server offers. This plugin needs version 1.0.0 or later of the server. %s', 'simple-location' ),
						$info->get_error_message()
					)
				)
			);
		}
	}

	/**
	 * Converts a style setting to a provider and style ID.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $style `provider/style`, a basemap ID from before 6.1.0, or empty for the map server's default map.
	 * @return string `provider/style`, or an empty string for the map server's default map.
	 */
	public static function sanitize_style( $style ) {
		if ( ! is_string( $style ) ) {
			return '';
		}
		$style = strtolower( trim( $style ) );
		if ( array_key_exists( $style, self::BASEMAPS ) ) {
			return self::BASEMAPS[ $style ];
		}
		if ( ! preg_match( '#^([a-z0-9_-]+)/([a-z0-9_-]+)$#', $style ) ) {
			return '';
		}
		return $style;
	}

	/**
	 * Returns the map server's address, where its maps are drawn and its query API is found.
	 *
	 * Map servers before 1.0.0 drew maps at `img.php`, so a URL ending in it, or in `index.php`, is shortened to the folder.
	 *
	 * @since 6.1.0
	 *
	 * @return string URL ending in a slash, or an empty string without a URL.
	 */
	protected function get_server_url() {
		$api = $this->api;
		if ( ! is_string( $api ) || '' === trim( $api ) ) {
			return '';
		}
		$api = strtok( trim( $api ), '?#' );
		$api = preg_replace( '#/(img|index)\.php$#', '/', $api );
		return trailingslashit( $api );
	}

	/**
	 * Returns the name of the transient that keeps an answer from the map server's query API.
	 *
	 * @since 6.1.0
	 *
	 * @param string $endpoint Endpoint under `/api/v1/`, such as 'providers', or an empty string for the service description.
	 * @param string $server   Map server URL, from get_server_url().
	 * @return string Transient name.
	 */
	protected static function cache_key( $endpoint, $server ) {
		return 'sloc_staticmap_' . md5( $server . 'api/v1/' . $endpoint );
	}

	/**
	 * Returns a stored answer from the map server's query API, without contacting the server.
	 *
	 * @since 6.1.0
	 *
	 * @param string $endpoint Endpoint under `/api/v1/`, or an empty string for the service description.
	 * @return array|WP_Error|null The answer, the error a request got, or null if neither is stored.
	 */
	protected function get_cached_api( $endpoint ) {
		$server = $this->get_server_url();
		if ( '' === $server ) {
			return null;
		}
		$cached = get_transient( self::cache_key( $endpoint, $server ) );
		if ( ! is_array( $cached ) ) {
			return null;
		}
		if ( isset( $cached['error'] ) ) {
			return new WP_Error( 'sloc_staticmap_api', is_string( $cached['error'] ) ? $cached['error'] : '' );
		}
		return $cached;
	}

	/**
	 * Returns an answer from the map server's query API, fetching it if it isn't stored.
	 *
	 * Answers are stored for CACHE_TIME, and errors for ERROR_CACHE_TIME, so pages don't wait on a server that is down.
	 *
	 * @since 6.1.0
	 *
	 * @param string $endpoint Endpoint under `/api/v1/`, such as 'providers', or an empty string for the service description.
	 * @param bool   $refresh  Optional. Fetch the answer even if one is stored. Default false.
	 * @return array|WP_Error The answer, or an error.
	 */
	public function get_api( $endpoint, $refresh = false ) {
		$server = $this->get_server_url();
		if ( '' === $server ) {
			return new WP_Error( 'missing_url', __( 'You have not set a URL for your custom map endpoint', 'simple-location' ) );
		}
		if ( ! $refresh ) {
			$cached = $this->get_cached_api( $endpoint );
			if ( null !== $cached ) {
				return $cached;
			}
		}
		$json = $this->fetch_json( $server . 'api/v1/' . $endpoint, array() );
		if ( ! is_wp_error( $json ) && ( ! is_array( $json ) || ( '' === $endpoint && ! isset( $json['api_version'] ) ) ) ) {
			$json = new WP_Error( 'sloc_staticmap_api', __( 'The answer is not from a Static Maps API query API.', 'simple-location' ) );
		}
		$key = self::cache_key( $endpoint, $server );
		if ( is_wp_error( $json ) ) {
			set_transient( $key, array( 'error' => $json->get_error_message() ), self::ERROR_CACHE_TIME );
			return $json;
		}
		set_transient( $key, $json, self::CACHE_TIME );
		return $json;
	}

	/**
	 * Returns the providers and styles the map server offers.
	 *
	 * @since 6.1.0
	 *
	 * @return array Providers keyed by ID, each with 'name' and 'styles' (style names keyed by ID), in the server's order.
	 *               The providers version 1.0.0 ships without keys if the server's list can't be fetched.
	 */
	public function get_providers() {
		$json = $this->get_api( 'providers' );
		if ( is_wp_error( $json ) || ! isset( $json['providers'] ) || ! is_array( $json['providers'] ) ) {
			return self::FALLBACK_PROVIDERS;
		}
		$providers = array();
		foreach ( $json['providers'] as $provider ) {
			if ( ! is_array( $provider ) || ! isset( $provider['id'], $provider['styles'] ) || ! is_string( $provider['id'] ) || ! is_array( $provider['styles'] ) ) {
				continue;
			}
			$styles = array();
			foreach ( $provider['styles'] as $style ) {
				if ( is_array( $style ) && isset( $style['id'] ) && is_string( $style['id'] ) ) {
					$styles[ $style['id'] ] = isset( $style['name'] ) && is_scalar( $style['name'] ) ? (string) $style['name'] : $style['id'];
				}
			}
			if ( $styles ) {
				$providers[ $provider['id'] ] = array(
					'name'   => isset( $provider['name'] ) && is_scalar( $provider['name'] ) ? (string) $provider['name'] : $provider['id'],
					'styles' => $styles,
				);
			}
		}
		return $providers ? $providers : self::FALLBACK_PROVIDERS;
	}

	/**
	 * Returns the map styles the provider offers.
	 *
	 * @since 4.1.6
	 * @since 6.1.0 Styles come from the map server's query API, keyed by `provider/style`. An empty key is the server's default map.
	 *
	 * @return array Style names keyed by ID.
	 */
	public function get_styles() {
		$styles = array(
			'' => __( 'Default map of the map server', 'simple-location' ),
		);
		foreach ( $this->get_providers() as $id => $provider ) {
			foreach ( $provider['styles'] as $style => $name ) {
				$styles[ $id . '/' . $style ] = ( $name === $provider['name'] )
					? $name
					/* translators: 1: Map provider name 2: Map style name */
					: sprintf( __( '%1$s: %2$s', 'simple-location' ), $provider['name'], $name );
			}
		}
		return $styles;
	}

	/**
	 * Returns the request parameters that choose the map's provider and style.
	 *
	 * @since 6.1.0
	 *
	 * @return string[] With 'provider' and 'style', or empty for the map server's default map.
	 */
	protected function get_style_args() {
		$style = self::sanitize_style( $this->style );
		if ( '' === $style ) {
			return array();
		}
		list( $provider, $style ) = explode( '/', $style, 2 );
		return array(
			'provider' => $provider,
			'style'    => $style,
		);
	}

	/**
	 * Applies the map server's size limits, fetching them if they aren't stored.
	 *
	 * @since 6.1.0
	 */
	protected function load_server_limits() {
		$this->set_max_size( $this->get_api( '' ) );
		$this->fit_size();
	}

	/**
	 * Returns the URL of a static map of the location.
	 *
	 * @since 4.1.6
	 * @since 6.1.0 Chooses the map with `provider` and `style`, draws a generated pin, and leaves the attribution to the map server.
	 *
	 * @return string|WP_Error URL, or an error without a map server URL.
	 */
	public function get_the_static_map() {
		$server = $this->get_server_url();
		if ( '' === $server ) {
			return new WP_Error( 'missing_url', __( 'You have not set a URL for your custom map endpoint', 'simple-location' ) );
		}
		$this->load_server_limits();
		return add_query_arg(
			array_merge(
				array(
					'width'     => $this->width,
					'height'    => $this->height,
					'zoom'      => $this->map_zoom,
					'latitude'  => $this->latitude,
					'longitude' => $this->longitude,
					'marker'    => sprintf( 'lat:%1$s;lng:%2$s;icon:pin', $this->latitude, $this->longitude ),
				),
				$this->get_style_args()
			),
			$server
		);
	}

	/**
	 * Returns the URL of a static map with multiple locations.
	 *
	 * @since 4.1.6
	 * @since 6.1.0 Chooses the map with `provider` and `style`, draws generated dots, and leaves the attribution to the map server.
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return string URL, or an empty string without a map server URL or without locations.
	 */
	public function get_archive_map( $locations ) {
		$server = $this->get_server_url();
		if ( '' === $server || empty( $locations ) ) {
			return '';
		}
		$this->load_server_limits();

		$markers = array();
		$path    = array();
		foreach ( $locations as $location ) {
			$markers[] = sprintf( 'lat:%1$s;lng:%2$s;icon:dot;size:small', $location[0], $location[1] );
			$path[]    = sprintf( '[%1$s,%2$s]', $location[1], $location[0] );
		}

		$map = add_query_arg(
			array_merge(
				array(
					'width'  => $this->width,
					'height' => $this->height,
					'path[]' => implode( ',', $path ),
				),
				$this->get_style_args()
			),
			$server
		);
		return $map . '&marker[]=' . implode( '&marker[]=', $markers );
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

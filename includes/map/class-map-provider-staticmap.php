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
	 * Client ID the map server knows this site by, sent as `key` in signed URLs.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $client_id = '';

	/**
	 * Secret the map server gave the client for signing URLs.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $client_secret = '';

	/**
	 * Plain client key, sent as `key` when URLs aren't signed.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $client_key = '';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 The map size is limited to 1280 by 1280, or to SLOC_STATICMAP_MAX_WIDTH and SLOC_STATICMAP_MAX_HEIGHT when they are defined.
	 * @since 6.0.0 A map server URL passed as the `api` argument is used instead of the setting.
	 * @since 6.1.0 The map size is limited when the map is drawn, to the map server's own limits when they are known.
	 * @since 6.1.0 The style is a provider and style ID, `provider/style`. Basemap IDs from before 6.1.0 are converted.
	 * @since 6.1.0 Added the `client_id`, `client_secret` and `client_key` arguments.
	 *
	 * @param array $args {
	 *     Arguments. See Map_Provider::__construct(), and also the following.
	 *
	 *     @type string $client_id     Client ID for signed URLs. Default the sloc_staticmap_client_id setting.
	 *     @type string $client_secret Signing secret. Default the sloc_staticmap_client_secret setting.
	 *     @type string $client_key    Plain client key, used when URLs aren't signed. Default the sloc_staticmap_client_key setting.
	 * }
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

		foreach ( array( 'client_id', 'client_secret', 'client_key' ) as $credential ) {
			$value               = $args[ $credential ] ?? get_option( 'sloc_staticmap_' . $credential );
			$this->{$credential} = is_string( $value ) ? trim( $value ) : '';
		}

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
	 * @since 6.1.0 Registers the client ID, signing secret and client key settings.
	 */
	public static function init() {
		self::register_settings_api( __( 'Custom Static Map URL', 'simple-location' ), 'sloc_staticmap_url' );
		self::register_settings_api( __( 'Custom Static Map', 'simple-location' ), 'sloc_staticmap_client_id', __( 'Client ID', 'simple-location' ) );
		self::register_settings_api( __( 'Custom Static Map', 'simple-location' ), 'sloc_staticmap_client_secret', __( 'Signing Secret', 'simple-location' ) );
		self::register_settings_api( __( 'Custom Static Map', 'simple-location' ), 'sloc_staticmap_client_key', __( 'Client Key', 'simple-location' ) );

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
	 * @since 6.1.0 Adds the client ID, signing secret and client key fields.
	 */
	public static function admin_init() {
		self::add_settings_url_parameter( __( 'Custom Static Map', 'simple-location' ), 'sloc_staticmap_url' );

		$fields = array(
			'sloc_staticmap_client_id'     => array(
				__( 'Custom Static Map Client ID', 'simple-location' ),
				'text',
				__( 'Optional. The client name given to bin/clients.php on the map server. With the signing secret, map URLs are signed, so they carry no secret and can be published.', 'simple-location' ),
			),
			'sloc_staticmap_client_secret' => array(
				__( 'Custom Static Map Signing Secret', 'simple-location' ),
				'password',
				__( 'Optional. The signing secret bin/clients.php showed for the client. It never leaves this site.', 'simple-location' ),
			),
			'sloc_staticmap_client_key'    => array(
				__( 'Custom Static Map Client Key', 'simple-location' ),
				'password',
				__( 'Optional, used only without a client ID and signing secret. The plain key appears in every map image URL, so anyone who views a page can copy it. Use a signing secret instead where you can.', 'simple-location' ),
			),
		);
		foreach ( $fields as $option => $field ) {
			add_settings_field(
				$option,
				$field[0],
				array( 'Loc_Config', 'string_callback' ),
				'sloc_api',
				'sloc_api',
				array(
					'label_for'   => $option,
					'type'        => $field[1],
					'description' => $field[2],
				)
			);
		}

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
	 * Echoes the style field, with a warning if the map server's query API can't be reached or refuses the client.
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
		if ( ! is_wp_error( $info ) || 'missing_url' === $info->get_error_code() ) {
			return;
		}
		$data = $info->get_error_data();
		if ( is_array( $data ) && isset( $data['status'] ) && 403 === $data['status'] ) {
			/* translators: %s: Error message. */
			$message = __( 'The custom map server refused the client ID, signing secret or client key, so the styles listed are the ones every server offers. Check them on the API settings page. %s', 'simple-location' );
		} else {
			/* translators: %s: Error message. */
			$message = __( 'The custom map server did not answer its query API, so the styles listed are the ones every server offers. This plugin needs version 1.0.0 or later of the server. %s', 'simple-location' );
		}
		printf( '<p class="description">%s</p>', esc_html( sprintf( $message, $info->get_error_message() ) ) );
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
	 * @return string Transient name. The answer depends on the client, so its credentials are part of the name.
	 */
	protected function cache_key( $endpoint ) {
		return 'sloc_staticmap_' . md5( $this->get_server_url() . 'api/v1/' . $endpoint . '|' . implode( '|', $this->get_credentials() ) );
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
		$cached = get_transient( $this->cache_key( $endpoint ) );
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
		// Answers for a client also list the maps that need a client key.
		$json = $this->fetch_json( $this->build_url( array(), 'api/v1/' . $endpoint ), array() );
		if ( ! is_wp_error( $json ) && ( ! is_array( $json ) || ( '' === $endpoint && ! isset( $json['api_version'] ) ) ) ) {
			$json = new WP_Error( 'sloc_staticmap_api', __( 'The answer is not from a Static Maps API query API.', 'simple-location' ) );
		}
		$key = $this->cache_key( $endpoint );
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
	 * Returns the request parameter that identifies this site to the map server.
	 *
	 * @since 6.1.0
	 *
	 * @return string[] `key` with the client ID when URLs are signed, or with the plain client key; empty without either.
	 */
	protected function get_credentials() {
		if ( '' !== $this->client_id && '' !== $this->client_secret ) {
			return array( 'key' => $this->client_id );
		}
		if ( '' !== $this->client_key ) {
			return array( 'key' => $this->client_key );
		}
		return array();
	}

	/**
	 * Builds a URL on the map server, signed when there is a client ID and signing secret.
	 *
	 * Every name and value is percent-encoded, so browsers and HTML escaping leave the URL as it was signed. The signature
	 * is a base64url HMAC-SHA256 of the path and query, made with the signing secret, and comes last, as the map server
	 * requires. Signed URLs don't expire, since published posts keep their map URLs.
	 *
	 * @since 6.1.0
	 *
	 * @param array  $params List of array( name, value ) pairs, in order. A name can repeat, such as `marker[]`.
	 * @param string $path   Optional. Path under the map server's address, such as 'api/v1/providers'. Default ''.
	 * @return string URL.
	 */
	protected function build_url( $params, $path = '' ) {
		foreach ( $this->get_credentials() as $name => $value ) {
			$params[] = array( $name, $value );
		}
		$url   = $this->get_server_url() . $path;
		$query = array();
		foreach ( $params as $param ) {
			$query[] = rawurlencode( (string) $param[0] ) . '=' . rawurlencode( (string) $param[1] );
		}
		$query = implode( '&', $query );
		if ( '' === $this->client_id || '' === $this->client_secret ) {
			return '' === $query ? $url : $url . '?' . $query;
		}
		// The server signs the path and query exactly as requested, without the scheme and host.
		$signed    = (string) wp_parse_url( $url, PHP_URL_PATH ) . '?' . $query;
		$signature = rtrim( strtr( base64_encode( hash_hmac( 'sha256', $signed, $this->client_secret, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64url is the signature format the map server checks.
		return $url . '?' . $query . '&signature=' . $signature;
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
	 * @since 6.1.0 Sends the client key, signing the URL when there is a signing secret. Every parameter is percent-encoded.
	 *
	 * @return string|WP_Error URL, or an error without a map server URL.
	 */
	public function get_the_static_map() {
		$server = $this->get_server_url();
		if ( '' === $server ) {
			return new WP_Error( 'missing_url', __( 'You have not set a URL for your custom map endpoint', 'simple-location' ) );
		}
		$this->load_server_limits();
		$params = array(
			array( 'width', $this->width ),
			array( 'height', $this->height ),
			array( 'zoom', $this->map_zoom ),
			array( 'latitude', $this->latitude ),
			array( 'longitude', $this->longitude ),
			array( 'marker', sprintf( 'lat:%1$s;lng:%2$s;icon:pin', $this->latitude, $this->longitude ) ),
		);
		foreach ( $this->get_style_args() as $name => $value ) {
			$params[] = array( $name, $value );
		}
		return $this->build_url( $params );
	}

	/**
	 * Returns the URL of a static map with multiple locations.
	 *
	 * @since 4.1.6
	 * @since 6.1.0 Chooses the map with `provider` and `style`, draws generated dots, and leaves the attribution to the map server.
	 * @since 6.1.0 Sends the client key, signing the URL when there is a signing secret. Every parameter is percent-encoded.
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
			$markers[] = array( 'marker[]', sprintf( 'lat:%1$s;lng:%2$s;icon:dot;size:small', $location[0], $location[1] ) );
			$path[]    = sprintf( '[%1$s,%2$s]', $location[1], $location[0] );
		}

		$params = array(
			array( 'width', $this->width ),
			array( 'height', $this->height ),
			array( 'path[]', implode( ',', $path ) ),
		);
		foreach ( $this->get_style_args() as $name => $value ) {
			$params[] = array( $name, $value );
		}
		return $this->build_url( array_merge( $params, $markers ) );
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

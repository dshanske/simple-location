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
	 * Image formats the map server can send.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const FORMATS = array( 'png', 'png8', 'jpg', 'webp' );

	/**
	 * Longest map URL, in bytes, the site fetches with GET for its map cache. Longer maps are sent as a POST request.
	 *
	 * Web servers often refuse request lines over 8 KB, such as nginx with its default buffers.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const MAX_GET_LENGTH = 6000;

	/**
	 * How long, in seconds, visitors are sent to the map server for a map image it marks as private.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const PRIVATE_PASSTHROUGH_TIME = DAY_IN_SECONDS;

	/**
	 * How long, in seconds, visitors are sent to the map server when it drew its default map instead of the one asked for.
	 *
	 * The map server keeps such maps for 5 minutes, since the map asked for may work again soon.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const FALLBACK_PASSTHROUGH_TIME = 5 * MINUTE_IN_SECONDS;

	/**
	 * Whether the provider's terms allow the site to store its map images. The map server allows it, unless it marks
	 * an image private.
	 *
	 * @since 6.1.0
	 * @var bool
	 */
	protected $cacheable = true;

	/**
	 * Where the map's credit goes: drawn on the image with the provider's logo, drawn on the image as text only, or
	 * printed under the map.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const ATTRIBUTIONS = array( 'image', 'text', 'below' );

	/**
	 * Where the map's credit goes, one of ATTRIBUTIONS.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $attribution = 'image';

	/**
	 * Corner of the credit drawn on the image, one of POSITIONS.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $attribution_position = 'bottomright';

	/**
	 * IDs of the map server's overlays to draw on the map.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	protected $overlays = array();

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
	 * @since 6.1.0 Added the `format`, `marker`, `marker_color`, `marker_size`, `scalebar`, `scalebar_position` and `overlays` arguments.
	 * @since 6.1.0 Added the `attribution` and `attribution_position` arguments.
	 *
	 * @param array $args {
	 *     Arguments. See Map_Provider::__construct(), and also the following.
	 *
	 *     @type string $client_id     Client ID for signed URLs. Default the sloc_staticmap_client_id setting.
	 *     @type string $client_secret Signing secret. Default the sloc_staticmap_client_secret setting.
	 *     @type string $client_key    Plain client key, used when URLs aren't signed. Default the sloc_staticmap_client_key setting.
	 *     @type string   $format            Image format, one of FORMATS, or empty for the server's default. Default the sloc_staticmap_format setting.
	 *     @type string   $marker            Marker type, 'pin' or 'dot'. Default the sloc_marker setting.
	 *     @type string   $marker_color      Marker color, #rrggbb. Default the sloc_marker_color setting.
	 *     @type string   $marker_size       Marker size, one of MARKER_SIZES. Default the sloc_marker_size setting.
	 *     @type string   $scalebar          Scale bar, one of SCALEBARS, or empty for none. Default the sloc_scalebar setting.
	 *     @type string   $scalebar_position Scale bar corner, one of POSITIONS. Default the sloc_scalebar_position setting.
	 *     @type string[] $overlays          Overlay IDs. Default the sloc_staticmap_overlays setting.
	 *     @type string   $attribution          Where the credit goes, one of ATTRIBUTIONS. Default the sloc_staticmap_attribution setting.
	 *     @type string   $attribution_position Corner of the credit on the image, one of POSITIONS. Default the sloc_staticmap_attribution_position setting.
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

		$display = array(
			'overlays'             => 'sanitize_overlays',
			'attribution'          => 'sanitize_attribution',
			'attribution_position' => 'sanitize_attribution_position',
		);
		foreach ( $display as $property => $sanitize ) {
			$this->{$property} = self::$sanitize( $args[ $property ] ?? get_option( 'sloc_staticmap_' . $property, $this->{$property} ) );
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
	 * @since 6.1.0 Registers the image format and overlay settings.
	 * @since 6.1.0 Registers the attribution settings.
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

		$settings = array(
			'sloc_staticmap_format'               => array( 'Custom Static Map Image Format', '', 'sanitize_format' ),
			'sloc_staticmap_attribution'          => array( 'Custom Static Map Attribution', 'image', 'sanitize_attribution' ),
			'sloc_staticmap_attribution_position' => array( 'Custom Static Map Attribution Position', 'bottomright', 'sanitize_attribution_position' ),
		);
		foreach ( $settings as $option => $setting ) {
			register_setting(
				'simloc',
				$option,
				array(
					'type'              => 'string',
					'description'       => $setting[0],
					'show_in_rest'      => false,
					'default'           => $setting[1],
					'sanitize_callback' => array( __CLASS__, $setting[2] ),
				)
			);
		}
		register_setting(
			'simloc',
			'sloc_staticmap_overlays',
			array(
				'type'              => 'array',
				'description'       => 'Custom Static Map Overlays',
				'show_in_rest'      => false,
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_overlays' ),
			)
		);
	}

	/**
	 * Adds the map server URL and style fields to the settings page.
	 *
	 * @since 4.1.6
	 * @since 6.1.0 The style field warns when the map server doesn't answer its query API.
	 * @since 6.1.0 Adds the client ID, signing secret and client key fields.
	 * @since 6.1.0 Adds the image format and overlay fields.
	 * @since 6.1.0 Adds the attribution fields.
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

		$lists = array(
			'sloc_staticmap_format'               => array(
				__( 'Static Map Image Format', 'simple-location' ),
				array(
					''     => __( 'Default of the map server', 'simple-location' ),
					'png'  => __( 'PNG', 'simple-location' ),
					'png8' => __( 'PNG with 256 colors (smaller)', 'simple-location' ),
					'jpg'  => __( 'JPEG', 'simple-location' ),
					'webp' => __( 'WebP (smallest, if the server supports it)', 'simple-location' ),
				),
			),
			'sloc_staticmap_attribution'          => array(
				__( 'Static Map Attribution', 'simple-location' ),
				array(
					'image' => __( 'On the map, as the map server draws it', 'simple-location' ),
					'text'  => __( 'On the map, as text only, without logos', 'simple-location' ),
					'below' => __( 'Under the map, with links', 'simple-location' ),
				),
				__( 'The map providers\' terms require their credit. Under the map, the full credit is shown with links; if the map server can\'t supply it, the credit is drawn on the map instead. Check the terms of the providers you use: some, such as Esri, require their logo.', 'simple-location' ),
			),
			'sloc_staticmap_attribution_position' => array(
				__( 'Static Map Attribution Position', 'simple-location' ),
				array(
					'bottomright' => __( 'Bottom right', 'simple-location' ),
					'bottomleft'  => __( 'Bottom left', 'simple-location' ),
					'topright'    => __( 'Top right', 'simple-location' ),
					'topleft'     => __( 'Top left', 'simple-location' ),
				),
			),
		);
		foreach ( $lists as $option => $field ) {
			add_settings_field(
				$option,
				$field[0],
				array( 'Loc_Config', 'values_callback' ),
				'simloc',
				'sloc_map',
				array(
					'label_for'   => $option,
					'list'        => $field[1],
					'description' => $field[2] ?? '',
				)
			);
		}

		add_settings_field(
			'sloc_staticmap_overlays',
			__( 'Static Map Overlays', 'simple-location' ),
			array( __CLASS__, 'overlays_field' ),
			'simloc',
			'sloc_map',
			array(
				'label_for' => 'sloc_staticmap_overlays',
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
	 * Returns a value if it is one of a list, or a default.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed    $value   Value.
	 * @param string[] $allowed Allowed values.
	 * @param string   $default Value returned for anything else.
	 * @return string
	 */
	protected static function sanitize_choice( $value, $allowed, $default ) {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * Sanitizes the attribution setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Where the credit goes.
	 * @return string One of ATTRIBUTIONS. Default 'image'.
	 */
	public static function sanitize_attribution( $value ) {
		return self::sanitize_choice( $value, self::ATTRIBUTIONS, 'image' );
	}

	/**
	 * Sanitizes the attribution position setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Corner.
	 * @return string One of POSITIONS. Default 'bottomright'.
	 */
	public static function sanitize_attribution_position( $value ) {
		return self::sanitize_choice( $value, self::POSITIONS, 'bottomright' );
	}

	/**
	 * Sanitizes the overlays setting.
	 *
	 * The map server draws at most a few overlays (3 by default) and leaves out the rest, so no limit is applied here.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Overlay IDs, as an array or a comma-separated string.
	 * @return string[] Overlay IDs, without duplicates.
	 */
	public static function sanitize_overlays( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$overlays = array();
		foreach ( $value as $overlay ) {
			if ( is_string( $overlay ) ) {
				$overlay = strtolower( trim( $overlay ) );
				if ( preg_match( '/^[a-z0-9_-]+$/', $overlay ) ) {
					$overlays[] = $overlay;
				}
			}
		}
		return array_values( array_unique( $overlays ) );
	}

	/**
	 * Returns the overlays the map server offers.
	 *
	 * @since 6.1.0
	 *
	 * @return array|WP_Error Overlay names keyed by ID, with 'max' the most a map can have; or an error.
	 */
	public function get_overlays() {
		$json = $this->get_api( 'overlays' );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$overlays = array();
		if ( isset( $json['overlays'] ) && is_array( $json['overlays'] ) ) {
			foreach ( $json['overlays'] as $overlay ) {
				if ( is_array( $overlay ) && isset( $overlay['id'] ) && is_string( $overlay['id'] ) ) {
					$overlays[ $overlay['id'] ] = isset( $overlay['name'] ) && is_scalar( $overlay['name'] ) ? (string) $overlay['name'] : $overlay['id'];
				}
			}
		}
		return array(
			'overlays' => $overlays,
			'max'      => isset( $json['max_overlays'] ) && is_numeric( $json['max_overlays'] ) ? (int) $json['max_overlays'] : 0,
		);
	}

	/**
	 * Echoes the overlays field: a checkbox for each overlay the map server offers.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args {
	 *     Arguments for the field.
	 *
	 *     @type string                 $label_for Option name.
	 *     @type Map_Provider_StaticMap $provider  Provider.
	 * }
	 */
	public static function overlays_field( array $args ) {
		$name     = $args['label_for'];
		$selected = self::sanitize_overlays( get_option( $name ) );
		$overlays = $args['provider']->get_overlays();
		if ( is_wp_error( $overlays ) ) {
			// The style field above already says why the map server didn't answer.
			$overlays = array(
				'overlays' => array(),
				'max'      => 0,
			);
		}
		// Keep overlays that are chosen but not offered now, such as while the server is down.
		foreach ( array_diff( $selected, array_keys( $overlays['overlays'] ) ) as $id ) {
			$overlays['overlays'][ $id ] = $id;
		}
		if ( ! $overlays['overlays'] ) {
			printf( '<p class="description">%s</p>', esc_html__( 'The map server offers no overlays.', 'simple-location' ) );
			return;
		}
		// An empty value, so unchecking every box saves an empty list.
		printf( '<input type="hidden" name="%s[]" value="" />', esc_attr( $name ) );
		foreach ( $overlays['overlays'] as $id => $label ) {
			printf(
				'<label><input type="checkbox" name="%1$s[]" value="%2$s"%3$s /> %4$s</label><br />',
				esc_attr( $name ),
				esc_attr( $id ),
				checked( in_array( $id, $selected, true ), true, false ),
				esc_html( $label )
			);
		}
		$description = __( 'Transparent layers drawn on top of the map, with their credit added to the map\'s.', 'simple-location' );
		if ( $overlays['max'] ) {
			/* translators: %d: Number of overlays. */
			$description .= ' ' . sprintf( _n( 'The map server draws at most %d overlay.', 'The map server draws at most %d overlays.', $overlays['max'], 'simple-location' ), $overlays['max'] );
		}
		printf( '<p class="description">%s</p>', esc_html( $description ) );
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
	 * @param array  $params   Optional. List of array( name, value ) request parameters. Default empty.
	 * @return string Transient name. The answer depends on the client, so its credentials are part of the name.
	 */
	protected function cache_key( $endpoint, $params = array() ) {
		$query = $params ? '?' . wp_json_encode( $params ) : '';
		return 'sloc_staticmap_' . md5( $this->get_server_url() . 'api/v1/' . $endpoint . $query . '|' . implode( '|', $this->get_credentials() ) );
	}

	/**
	 * Returns a stored answer from the map server's query API, without contacting the server.
	 *
	 * @since 6.1.0
	 *
	 * @param string $endpoint Endpoint under `/api/v1/`, or an empty string for the service description.
	 * @param array  $params   Optional. List of array( name, value ) request parameters. Default empty.
	 * @return array|WP_Error|null The answer, the error a request got, or null if neither is stored.
	 */
	protected function get_cached_api( $endpoint, $params = array() ) {
		$server = $this->get_server_url();
		if ( '' === $server ) {
			return null;
		}
		$cached = get_transient( $this->cache_key( $endpoint, $params ) );
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
	 * @param array  $params   Optional. List of array( name, value ) request parameters, such as the map for 'attribution'. Default empty.
	 * @return array|WP_Error The answer, or an error.
	 */
	public function get_api( $endpoint, $refresh = false, $params = array() ) {
		$server = $this->get_server_url();
		if ( '' === $server ) {
			return new WP_Error( 'missing_url', __( 'You have not set a URL for your custom map endpoint', 'simple-location' ) );
		}
		if ( ! $refresh ) {
			$cached = $this->get_cached_api( $endpoint, $params );
			if ( null !== $cached ) {
				return $cached;
			}
		}
		// Answers for a client also list the maps that need a client key.
		$json = $this->fetch_json( $this->build_url( $params, 'api/v1/' . $endpoint ), array() );
		if ( ! is_wp_error( $json ) && ( ! is_array( $json ) || ( '' === $endpoint && ! isset( $json['api_version'] ) ) ) ) {
			$json = new WP_Error( 'sloc_staticmap_api', __( 'The answer is not from a Static Maps API query API.', 'simple-location' ) );
		}
		$key = $this->cache_key( $endpoint, $params );
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
	 * Returns the request parameters for how the map is drawn, other than its markers and paths.
	 *
	 * @since 6.1.0
	 *
	 * @return array List of array( name, value ) pairs: the provider and style, format, pixel density, overlays, attribution and scale bar.
	 */
	protected function get_display_params() {
		$params = array();
		foreach ( $this->get_style_args() as $name => $value ) {
			$params[] = array( $name, $value );
		}
		if ( '' !== $this->format ) {
			$params[] = array( 'format', $this->format );
		}
		if ( 1 !== $this->scale ) {
			$params[] = array( 'scale', $this->scale );
		}
		if ( $this->overlays ) {
			$params[] = array( 'overlay', implode( ',', $this->overlays ) );
		}
		if ( 'below' === $this->attribution && '' !== $this->get_attribution_html() ) {
			$params[] = array( 'attribution', 'none' );
		} else {
			if ( 'text' === $this->attribution ) {
				$params[] = array( 'attribution', 'text' );
			}
			if ( 'bottomright' !== $this->attribution_position ) {
				$params[] = array( 'attribution_position', $this->attribution_position );
			}
		}
		$scalebar = $this->get_scalebar_units();
		if ( '' !== $scalebar ) {
			$params[] = array( 'scalebar', $scalebar );
			$params[] = array( 'scalebar_position', $this->scalebar_position );
		}
		return $params;
	}

	/**
	 * Returns the map's credit to show under the map, when the attribution setting puts it there.
	 *
	 * The credit comes from the map server's `/api/v1/attribution`, for the map's provider, style and overlays, and is
	 * stored like the server's other answers. Only links are kept from its HTML.
	 *
	 * @since 6.1.0
	 *
	 * @return string HTML of a paragraph with the credit, or an empty string if it isn't shown under the map or can't be fetched.
	 */
	public function get_attribution_html() {
		if ( 'below' !== $this->attribution || '' === $this->get_server_url() ) {
			return '';
		}
		$params = array();
		foreach ( $this->get_style_args() as $name => $value ) {
			$params[] = array( $name, $value );
		}
		if ( $this->overlays ) {
			$params[] = array( 'overlay', implode( ',', $this->overlays ) );
		}
		$json = $this->get_api( 'attribution', false, $params );
		if ( is_wp_error( $json ) || ! isset( $json['html'] ) || ! is_string( $json['html'] ) ) {
			return '';
		}
		$html = trim(
			wp_kses(
				$json['html'],
				array(
					'a' => array(
						'href' => true,
						'rel'  => true,
					),
				)
			)
		);
		if ( '' === $html ) {
			return '';
		}
		return '<p class="sloc-map-attribution">' . $html . '</p>';
	}

	/**
	 * Returns a marker's options for the map server.
	 *
	 * @since 6.1.0
	 *
	 * @param string $type Marker type, 'pin' or 'dot'.
	 * @param string $size Marker size, one of MARKER_SIZES.
	 * @return string Options, as `key:value` pairs separated by semicolons.
	 */
	protected function get_marker_options( $type, $size ) {
		return sprintf( 'icon:%1$s;size:%2$s;color:%3$s', $type, $size, $this->get_marker_hex() );
	}

	/**
	 * Returns the pixel densities the map server can draw this map at.
	 *
	 * The map server's size limit applies to the image it sends, so a high-density map is only offered when twice its
	 * size fits.
	 *
	 * @since 6.1.0
	 *
	 * @return int[] Pixel densities.
	 */
	public function get_scales() {
		$info   = $this->get_cached_api( '' );
		$scales = is_array( $info ) && isset( $info['scales'] ) && is_array( $info['scales'] ) ? array_map( 'intval', $info['scales'] ) : array( 1, 2 );
		if ( ! in_array( 2, $scales, true ) ) {
			return array( 1 );
		}
		if ( ( $this->max_width && 2 * $this->width > $this->max_width ) || ( $this->max_height && 2 * $this->height > $this->max_height ) ) {
			return array( 1 );
		}
		return array( 1, 2 );
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
	 * @since 6.1.0 Uses the marker, image format, pixel density, overlay and scale bar settings.
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
			array( 'marker', sprintf( 'lat:%1$s;lng:%2$s;%3$s', $this->latitude, $this->longitude, $this->get_marker_options( $this->marker, $this->marker_size ) ) ),
		);
		return $this->build_url( array_merge( $params, $this->get_display_params() ) );
	}

	/**
	 * Returns the URL of a static map with multiple locations.
	 *
	 * @since 4.1.6
	 * @since 6.1.0 Chooses the map with `provider` and `style`, draws generated dots, and leaves the attribution to the map server.
	 * @since 6.1.0 Sends the client key, signing the URL when there is a signing secret. Every parameter is percent-encoded.
	 * @since 6.1.0 Draws the path as an encoded polyline, with dots in the marker color, and uses the image format, pixel density, overlay and scale bar settings.
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

		return $this->build_url( $this->get_archive_params( $locations ) );
	}

	/**
	 * Returns the request parameters of a static map with multiple locations, without the client's credentials.
	 *
	 * @since 6.1.0
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return array List of array( name, value ) pairs.
	 */
	protected function get_archive_params( $locations ) {
		$params = array(
			array( 'width', $this->width ),
			array( 'height', $this->height ),
			// An encoded polyline, in latitude, longitude order, keeps the URL short.
			array( 'polyline', 'enc:' . Sloc_Polyline::encode( $locations ) ),
		);
		foreach ( $locations as $location ) {
			$params[] = array( 'marker[]', sprintf( 'lat:%1$s;lng:%2$s;%3$s', $location[0], $location[1], $this->get_marker_options( 'dot', 'small' ) ) );
		}
		return array_merge( $params, $this->get_display_params() );
	}

	/**
	 * Returns where each location's marker is on the archive map, from the map server's `/api/v1/plan`.
	 *
	 * The plan is asked for with the same parameters as the image and stored like the server's other answers. The query
	 * API only answers GET requests, so a map whose request is too long for a URL has no marker positions.
	 *
	 * @since 6.1.0
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return array Boxes keyed by the location's index in $locations, each array( left, top, right, bottom ) in percent of
	 *               the image's width and height. Markers outside the image are left out, and so is everything if the
	 *               plan can't be fetched.
	 */
	public function get_archive_marker_boxes( $locations ) {
		if ( '' === $this->get_server_url() || empty( $locations ) || ! is_array( $locations ) ) {
			return array();
		}
		$this->load_server_limits();
		$params = $this->get_archive_params( $locations );
		if ( strlen( $this->build_url( $params, 'api/v1/plan' ) ) > self::MAX_GET_LENGTH ) {
			return array();
		}
		$plan = $this->get_api( 'plan', false, $params );
		if ( is_wp_error( $plan ) || empty( $plan['markers'] ) || ! is_array( $plan['markers'] ) ) {
			return array();
		}
		$width  = isset( $plan['width'] ) && is_numeric( $plan['width'] ) ? (float) $plan['width'] : 0;
		$height = isset( $plan['height'] ) && is_numeric( $plan['height'] ) ? (float) $plan['height'] : 0;
		if ( $width <= 0 || $height <= 0 ) {
			return array();
		}
		$count = count( $locations );
		$boxes = array();
		foreach ( $plan['markers'] as $marker ) {
			if ( ! is_array( $marker ) || ! isset( $marker['n'], $marker['box'] ) || ! is_array( $marker['box'] ) || 4 !== count( $marker['box'] ) ) {
				continue;
			}
			// Markers are numbered from 1, in the order they were sent.
			$index = (int) $marker['n'] - 1;
			if ( $index < 0 || $index >= $count ) {
				continue;
			}
			$box = self::marker_box_percent( array_map( 'floatval', array_values( $marker['box'] ) ), $width, $height );
			if ( null !== $box ) {
				$boxes[ $index ] = $box;
			}
		}
		return $boxes;
	}

	/**
	 * Whether the site may store this provider's map images and serve them itself.
	 *
	 * With only a plain client key, the map server marks every image private, so those maps aren't stored.
	 *
	 * @since 6.1.0
	 *
	 * @return bool
	 */
	public function is_cacheable() {
		return parent::is_cacheable() && ( '' === $this->client_key || ( '' !== $this->client_id && '' !== $this->client_secret ) );
	}

	/**
	 * Returns the settings, other than the map parameters, that change the map image.
	 *
	 * @since 6.1.0
	 *
	 * @param array|null $locations Optional. List of array( latitude, longitude ) points for an archive map.
	 * @return array Setting values: the image format, markers and scale bar, then the overlays, attribution and credentials.
	 */
	protected function get_cache_options( $locations = null ) {
		return array_merge(
			parent::get_cache_options( $locations ),
			array(
				$this->overlays,
				$this->attribution,
				$this->attribution_position,
				$this->get_credentials(),
			)
		);
	}

	/**
	 * Fetches the current map's image for the site to store.
	 *
	 * Archive maps whose URL would be too long for the map server are sent as a POST request with a JSON body. The map
	 * server only accepts signed URLs as GET requests, so a POST request carries the plain client key, if there is one.
	 *
	 * @since 6.1.0
	 *
	 * @param array|null $locations Optional. List of array( latitude, longitude ) points for an archive map.
	 * @param array      $args      Optional. Request arguments. Default empty.
	 * @return array|WP_Error Response, or an error.
	 */
	public function fetch_static_map( $locations = null, $args = array() ) {
		if ( null === $locations || '' === $this->get_server_url() || empty( $locations ) ) {
			return parent::fetch_static_map( $locations, $args );
		}
		$this->load_server_limits();
		$params = $this->get_archive_params( $locations );
		if ( strlen( $this->build_url( $params ) ) <= self::MAX_GET_LENGTH ) {
			return parent::fetch_static_map( $locations, $args );
		}
		if ( '' !== $this->client_key ) {
			$params[] = array( 'key', $this->client_key );
		}
		$body = array();
		foreach ( $params as $param ) {
			if ( '[]' === substr( $param[0], -2 ) ) {
				$body[ substr( $param[0], 0, -2 ) ][] = (string) $param[1];
			} else {
				$body[ $param[0] ] = (string) $param[1];
			}
		}
		$args['headers'] = array( 'Content-Type' => 'application/json' );
		$args['body']    = wp_json_encode( $body );
		return wp_safe_remote_post( $this->get_server_url(), $args );
	}

	/**
	 * Returns how long visitors are sent to the map server for a map image the site must not store.
	 *
	 * The map server marks images private when the provider's terms don't allow caching them (such as Stadia Maps) or
	 * when they were drawn for a plain client key, and sends X-Map-Fallback when it drew its default map instead.
	 *
	 * @since 6.1.0
	 *
	 * @param array $response Response with the image.
	 * @return int Seconds, or 0 to store the image.
	 */
	public function get_passthrough_time( $response ) {
		if ( '' !== (string) wp_remote_retrieve_header( $response, 'x-map-fallback' ) ) {
			return self::FALLBACK_PASSTHROUGH_TIME;
		}
		if ( preg_match( '/(^|[\s,])(private|no-store)([\s,=]|$)/i', (string) wp_remote_retrieve_header( $response, 'cache-control' ) ) ) {
			return self::PRIVATE_PASSTHROUGH_TIME;
		}
		return 0;
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
	 * @since 6.1.0 Followed by the map's credit when the attribution setting puts it under the map.
	 *
	 * @param boolean $static Unused; the map is always static.
	 * @return string HTML marked up map.
	 */
	public function get_the_map( $static = true ) {
		$map = $this->get_the_static_map_html( true );
		return '' === $map ? '' : $map . $this->get_attribution_html();
	}
}

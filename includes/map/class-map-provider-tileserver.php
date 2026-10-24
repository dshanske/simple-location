<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map Provider using your own TileServer GL server's static images, https://tileserver-gl.readthedocs.io/en/latest/endpoints.html.
 *
 * TileServer GL renders maps from vector tiles, such as OpenMapTiles from OpenStreetMap data, so there are no vendor
 * terms or keys, and the images can be stored on the site. Static images need the full tileserver-gl build, version
 * 4.6 or later, which styles each path on its own; tileserver-gl-light can't draw them.
 *
 * @since 6.1.0
 */
class Map_Provider_TileServer extends Map_Provider {

	/**
	 * Image formats the provider can be asked for.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const FORMATS = array( 'png', 'jpg', 'webp' );

	/**
	 * Image format the plugin asks for when none is chosen.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const DEFAULT_FORMAT = 'png';

	/**
	 * Largest side, in pixels, of an image TileServer GL draws by default, at its pixel density (its maxSize option).
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const MAX_SIZE = 2048;

	/**
	 * Radius, in pixels, of the dot drawn for each marker size.
	 *
	 * @since 6.1.0
	 * @var int[]
	 */
	const DOT_RADIUS = array(
		'small'  => 4,
		'medium' => 6,
		'large'  => 9,
	);

	/**
	 * Scale of the marker icon for each marker size.
	 *
	 * @since 6.1.0
	 * @var float[]
	 */
	const ICON_SCALE = array(
		'small'  => 0.75,
		'medium' => 1,
		'large'  => 1.25,
	);

	/**
	 * Seconds the server's styles and credits are kept, and seconds an error getting them is.
	 *
	 * @since 6.1.0
	 * @var int[]
	 */
	const CACHE_TIMES = array( 43200, 300 );

	/**
	 * The images are drawn by the site's own server, so the site may store them.
	 *
	 * @since 6.1.0
	 * @var bool
	 */
	protected $cacheable = true;

	/**
	 * TileServer GL draws static maps from 512-pixel tiles.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	protected $tile_size = 512;

	/**
	 * TileServer GL accepts fractional zoom levels.
	 *
	 * @since 6.1.0
	 * @var bool
	 */
	protected $fractional_zoom = true;

	/**
	 * Marker icon on the server, drawn instead of a dot, or an empty string for dots.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $icon = '';

	/**
	 * Constructor.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args {
	 *     Arguments. See Map_Provider::__construct() for the others.
	 *
	 *     @type string $api  Server URL. Default the sloc_tileserver_url setting.
	 *     @type string $icon Marker icon on the server. Default the sloc_tileserver_icon setting.
	 * }
	 */
	public function __construct( $args = array() ) {
		$this->name         = __( 'TileServer GL', 'simple-location' );
		$this->slug         = 'tileserver';
		$this->url          = 'https://github.com/maptiler/tileserver-gl';
		$this->description  = __( 'Your own TileServer GL server (version 4.6 or later, not the light build) draws the maps from its styles, with no vendor terms or keys, so they can be stored on this site. Credit the map data, usually OpenStreetMap, as your server\'s styles require.', 'simple-location' );
		$this->max_map_zoom = 22;
		$this->max_width    = self::MAX_SIZE;
		$this->max_height   = self::MAX_SIZE;
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_tileserver_url' );
		}
		if ( ! isset( $args['style'] ) ) {
			$args['style'] = $this->get_style_setting( 'sloc_tileserver_style' );
		}
		$this->icon = self::sanitize_icon( $args['icon'] ?? get_option( 'sloc_tileserver_icon', '' ) );
		parent::__construct( $args );
		// Markers are drawn centred on the location as dots, or with their foot on it as icons.
		if ( '' === $this->icon ) {
			$radius           = self::DOT_RADIUS['medium'] + 2;
			$this->marker_box = array( -$radius, -$radius, $radius, $radius );
		}
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 6.1.0
	 */
	public static function init() {
		self::register_settings_api( __( 'TileServer GL URL', 'simple-location' ), 'sloc_tileserver_url' );
		register_setting(
			'simloc',
			'sloc_tileserver_style',
			array(
				'type'              => 'string',
				'description'       => 'TileServer GL Style',
				'show_in_rest'      => false,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'simloc',
			'sloc_tileserver_icon',
			array(
				'type'              => 'string',
				'description'       => 'TileServer GL Marker Icon',
				'show_in_rest'      => false,
				'default'           => '',
				'sanitize_callback' => array( __CLASS__, 'sanitize_icon' ),
			)
		);
		self::register_format_setting( __( 'TileServer GL', 'simple-location' ), 'tileserver' );
	}

	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 6.1.0
	 */
	public static function admin_init() {
		self::add_settings_url_parameter( __( 'TileServer GL', 'simple-location' ), 'sloc_tileserver_url' );
		add_settings_field(
			'sloc_tileserver_style',
			__( 'TileServer GL Style', 'simple-location' ),
			array( 'Loc_Config', 'style_callback' ),
			'simloc',
			'sloc_map',
			array(
				'label_for' => 'sloc_tileserver_style',
				'provider'  => new Map_Provider_TileServer(),
			)
		);
		add_settings_field(
			'sloc_tileserver_icon',
			__( 'TileServer GL Marker Icon', 'simple-location' ),
			array( 'Loc_Config', 'string_callback' ),
			'simloc',
			'sloc_map',
			array(
				'label_for'   => 'sloc_tileserver_icon',
				'type'        => 'text',
				'description' => __( 'Optional. An icon in the server\'s icon folder, such as marker.png, drawn with its foot on each location. Without one, each location is a dot in the marker color.', 'simple-location' ),
			)
		);
		self::add_format_setting( __( 'TileServer GL', 'simple-location' ), 'tileserver' );
	}

	/**
	 * Sanitizes the marker icon setting: a file name or path in the server's icon folder.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Icon, such as marker.png or pins/red.svg.
	 * @return string Icon, or an empty string.
	 */
	public static function sanitize_icon( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		return preg_match( '#^[A-Za-z0-9_][A-Za-z0-9_./-]*\.(png|jpe?g|svg|webp)$#i', $value ) && false === strpos( $value, '..' ) ? $value : '';
	}

	/**
	 * Returns the server's address, ending in a slash.
	 *
	 * @since 6.1.0
	 *
	 * @return string URL, or an empty string without one.
	 */
	public function get_server_url() {
		$url = is_string( $this->api ) ? trim( $this->api ) : '';
		if ( ! preg_match( '#^https?://[^/\s]#i', $url ) ) {
			return '';
		}
		return trailingslashit( (string) strtok( esc_url_raw( $url, array( 'http', 'https' ) ), '?#' ) );
	}

	/**
	 * Returns a JSON document from the server, kept for a while, such as its list of styles.
	 *
	 * @since 6.1.0
	 *
	 * @param string $path    Path under the server's address, such as styles.json.
	 * @param bool   $refresh Optional. Fetch it even if it is kept. Default false.
	 * @return array|WP_Error Document, or an error.
	 */
	public function get_json( $path, $refresh = false ) {
		$server = $this->get_server_url();
		if ( '' === $server ) {
			return new WP_Error( 'missing_url', __( 'You have not set the address of your TileServer GL server', 'simple-location' ) );
		}
		$key    = 'sloc_tileserver_' . md5( $server . $path );
		$stored = $refresh ? false : get_transient( $key );
		if ( is_array( $stored ) ) {
			return isset( $stored['error'] ) ? new WP_Error( 'tileserver_error', (string) $stored['error'] ) : $stored['json'];
		}
		$json = $this->fetch_json( $server . $path, array() );
		if ( ! is_wp_error( $json ) && ! is_array( $json ) ) {
			$json = new WP_Error( 'tileserver_error', __( 'The server did not answer as TileServer GL does.', 'simple-location' ) );
		}
		if ( is_wp_error( $json ) ) {
			set_transient( $key, array( 'error' => $json->get_error_message() ), self::CACHE_TIMES[1] );
			return $json;
		}
		set_transient( $key, array( 'json' => $json ), self::CACHE_TIMES[0] );
		return $json;
	}

	/**
	 * Returns the map styles the server offers, from its styles.json.
	 *
	 * @since 6.1.0
	 *
	 * @return array Style names keyed by ID, or an empty array if the server can't be reached.
	 */
	public function get_styles() {
		$json   = $this->get_json( 'styles.json' );
		$styles = array();
		foreach ( is_array( $json ) ? $json : array() as $style ) {
			if ( is_array( $style ) && isset( $style['id'] ) && is_string( $style['id'] ) && '' !== $style['id'] ) {
				$styles[ $style['id'] ] = ( isset( $style['name'] ) && is_scalar( $style['name'] ) && '' !== (string) $style['name'] ) ? (string) $style['name'] : $style['id'];
			}
		}
		return $styles;
	}

	/**
	 * Returns the server's style for each style family, by the words in their IDs, such as dark or satellite.
	 *
	 * The standard family is the server's first style; a family without a matching style uses it too.
	 *
	 * @since 6.1.0
	 *
	 * @return string[] Style IDs keyed by family. See Map_Provider::get_family_styles().
	 */
	public function get_family_styles() {
		$ids      = array_keys( $this->get_styles() );
		$standard = $ids[0] ?? '';
		$words    = array(
			'light'     => array( 'light', 'positron', 'toner' ),
			'dark'      => array( 'dark', 'matter', 'night' ),
			'satellite' => array( 'satellite', 'hybrid', 'imagery', 'aerial' ),
			'terrain'   => array( 'terrain', 'outdoor', 'topo', 'hill' ),
		);
		$families = array( 'standard' => $standard );
		foreach ( $words as $family => $list ) {
			$families[ $family ] = $standard;
			foreach ( $ids as $id ) {
				if ( preg_match( '/' . implode( '|', $list ) . '/i', $id ) ) {
					$families[ $family ] = $id;
					break;
				}
			}
		}
		return $families;
	}

	/**
	 * Returns the current style, or the server's first if none is chosen.
	 *
	 * @since 6.1.0
	 *
	 * @return string Style ID, or an empty string if there is none.
	 */
	protected function get_style_id() {
		if ( is_string( $this->style ) && '' !== $this->style ) {
			return $this->style;
		}
		return $this->get_family_styles()['standard'];
	}

	/**
	 * Returns the pixel densities the server can draw the map at: twice as dense only if that is within its largest
	 * size.
	 *
	 * @since 6.1.0
	 *
	 * @return int[] Pixel densities.
	 */
	public function get_scales() {
		return ( max( (int) $this->width, (int) $this->height ) * 2 <= self::MAX_SIZE ) ? array( 1, 2 ) : array( 1 );
	}

	/**
	 * Returns a dot around a location, as a path the server fills: a closed circle of 16 sides.
	 *
	 * @since 6.1.0
	 *
	 * @param float  $latitude  Latitude.
	 * @param float  $longitude Longitude.
	 * @param float  $zoom      Zoom of the map, for the dot's size in pixels.
	 * @param string $size      Marker size, one of MARKER_SIZES.
	 * @return string Path.
	 */
	protected function get_dot( $latitude, $longitude, $zoom, $size ) {
		$radius = self::DOT_RADIUS[ $size ] ?? self::DOT_RADIUS['medium'];
		// Degrees of longitude per pixel, and of latitude, which shrink towards the poles on the map.
		$per_pixel = 360 / ( $this->tile_size * pow( 2, (float) $zoom ) );
		$points    = array();
		for ( $i = 0; $i <= 16; $i++ ) {
			$angle    = 2 * M_PI * ( $i % 16 ) / 16;
			$points[] = array(
				$latitude + $radius * $per_pixel * cos( deg2rad( $latitude ) ) * sin( $angle ),
				$longitude + $radius * $per_pixel * cos( $angle ),
			);
		}
		// An encoded polyline keeps five decimals, about a meter, too coarse for a dot when zoomed in close.
		if ( $radius * $per_pixel * cos( deg2rad( $latitude ) ) < 0.0002 ) {
			$geometry = implode( '|', array_map( fn( $point ) => sprintf( '%1$.7F,%2$.7F', $point[1], $point[0] ), $points ) );
		} else {
			$geometry = 'enc:' . Sloc_Polyline::encode( $points );
		}
		return sprintf( 'fill:#%1$s|stroke:#ffffff|width:2|%2$s', $this->get_marker_hex(), $geometry );
	}

	/**
	 * Returns a marker icon at a location, in TileServer GL's marker syntax.
	 *
	 * @since 6.1.0
	 *
	 * @param float  $latitude  Latitude.
	 * @param float  $longitude Longitude.
	 * @param string $size      Marker size, one of MARKER_SIZES.
	 * @return string Marker.
	 */
	protected function get_icon_marker( $latitude, $longitude, $size ) {
		return sprintf( '%1$s,%2$s|%3$s|scale:%4$s', $longitude, $latitude, $this->icon, self::ICON_SCALE[ $size ] ?? 1 );
	}

	/**
	 * Returns the markers of locations: icons, or dots.
	 *
	 * @since 6.1.0
	 *
	 * @param array  $locations List of array( latitude, longitude ) points.
	 * @param float  $zoom      Zoom of the map.
	 * @param string $size      Marker size, one of MARKER_SIZES.
	 * @return array Query parameters, as list of array( name, value ).
	 */
	protected function get_markers( $locations, $zoom, $size ) {
		$params = array();
		foreach ( $locations as $location ) {
			$params[] = '' === $this->icon
				? array( 'path', $this->get_dot( (float) $location[0], (float) $location[1], $zoom, $size ) )
				: array( 'marker', $this->get_icon_marker( (float) $location[0], (float) $location[1], $size ) );
		}
		return $params;
	}

	/**
	 * Returns the URL of a static map image.
	 *
	 * @since 6.1.0
	 *
	 * @param string $view   The view: `lon,lat,zoom` or `auto`.
	 * @param array  $params Query parameters, as list of array( name, value ); names can repeat.
	 * @return string URL, or an empty string without a server or style.
	 */
	protected function build_url( $view, $params ) {
		$server = $this->get_server_url();
		$style  = $this->get_style_id();
		if ( '' === $server || '' === $style ) {
			return '';
		}
		$url   = sprintf(
			'%1$sstyles/%2$s/static/%3$s/%4$sx%5$s%6$s.%7$s',
			$server,
			rawurlencode( $style ),
			$view,
			(int) $this->width,
			(int) $this->height,
			1 < $this->scale ? '@' . $this->scale . 'x' : '',
			$this->get_format()
		);
		$query = array();
		foreach ( $params as $param ) {
			$query[] = rawurlencode( $param[0] ) . '=' . rawurlencode( (string) $param[1] );
		}
		return empty( $query ) ? $url : $url . '?' . implode( '&', $query );
	}

	/**
	 * Returns the URL of a static map of the location.
	 *
	 * @since 6.1.0
	 *
	 * @return string URL, or an empty string without a server or style.
	 */
	public function get_the_static_map() {
		return $this->build_url(
			sprintf( '%1$s,%2$s,%3$s', $this->longitude, $this->latitude, $this->map_zoom ),
			$this->get_markers( array( array( $this->latitude, $this->longitude ) ), (float) $this->map_zoom, $this->marker_size )
		);
	}

	/**
	 * Returns the URL of a static map with multiple locations.
	 *
	 * The plugin centres and zooms the map, so its markers can be linked to their posts. If it can't, the server fits
	 * the map to the locations. A track is a line, with markers only at its ends, or none.
	 *
	 * @since 6.1.0
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return string URL, or an empty string without a server, a style or locations.
	 */
	public function get_archive_map( $locations ) {
		if ( empty( $locations ) ) {
			return '';
		}
		$view   = $this->get_archive_view( $locations );
		$params = array();
		if ( $this->track && count( $locations ) > 1 ) {
			$params[] = array( 'path', sprintf( 'stroke:#%1$s|width:3|enc:%2$s', $this->get_marker_hex(), Sloc_Polyline::encode( $locations ) ) );
		}
		// Without a view, the server chooses the zoom, so the dots are sized for a wide view.
		$params = array_merge( $params, $this->get_markers( $this->get_marker_locations( $locations ), null === $view ? 2 : $view['zoom'], 'medium' ) );
		if ( null === $view ) {
			$params[] = array( 'padding', 0.1 );
			$params[] = array( 'maxzoom', $this->max_map_zoom );
			return $this->build_url( 'auto', $params );
		}
		return $this->build_url( sprintf( '%1$s,%2$s,%3$s', $view['longitude'], $view['latitude'], $view['zoom'] ), $params );
	}

	/**
	 * Returns the map's credit to show under the map, when the attribution setting puts it there: the credit the
	 * server gives for the style's data.
	 *
	 * The credit on the image is the server's static attribution text, which its configuration sets.
	 *
	 * @since 6.1.0
	 *
	 * @return string HTML of a paragraph with the credit, or an empty string if the credit is on the image.
	 */
	public function get_attribution_html() {
		if ( 'below' !== $this->attribution ) {
			return '';
		}
		$style  = $this->get_style_id();
		$json   = '' === $style ? null : $this->get_json( 'styles/' . rawurlencode( $style ) . '.json' );
		$credit = ( is_array( $json ) && isset( $json['attribution'] ) && is_string( $json['attribution'] ) ) ? trim( $json['attribution'] ) : '';
		if ( '' === $credit ) {
			$credit = sprintf( '<a href="https://www.openstreetmap.org/copyright">%s</a>', esc_html__( '© OpenStreetMap contributors', 'simple-location' ) );
		}
		return '<p class="sloc-map-attribution">' . wp_kses(
			$credit,
			array(
				'a' => array(
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
				),
			)
		) . '</p>';
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

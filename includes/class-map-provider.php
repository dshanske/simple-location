<?php
/**
 * Base Map Provider Class.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retrieves Maps.
 *
 * @since 1.0.0
 */
abstract class Map_Provider extends Sloc_Provider {

	/**
	 * Map Zoom Level.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	protected $map_zoom;

	/**
	 * Space, in pixels, kept between an archive map's edges and its outermost locations when the plugin chooses its view.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const ARCHIVE_PADDING = 32;

	/**
	 * Width, in pixels, of the whole world at zoom 0 on the provider's static maps, or 0 if the plugin doesn't choose the
	 * view of the provider's archive maps.
	 *
	 * Most providers use 256-pixel tiles; Mapbox uses 512.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	protected $tile_size = 0;

	/**
	 * Whether the provider's static maps accept fractional zoom levels.
	 *
	 * @since 6.1.0
	 * @var bool
	 */
	protected $fractional_zoom = false;

	/**
	 * Clickable area of an archive map marker, as array( left, top, right, bottom ) in pixels from the marker's location.
	 *
	 * The default suits a pin whose tip is on the location.
	 *
	 * @since 6.1.0
	 * @var int[]
	 */
	protected $marker_box = array( -12, -28, 12, 2 );

	/**
	 * Map Zoom Level Maximum.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	protected $max_map_zoom;

	/**
	 * Whether the archive map is a track: a line along its points, with markers only as track_markers says.
	 *
	 * @since 6.1.0
	 * @var bool
	 */
	protected $track = false;

	/**
	 * Markers of a track: 'ends' for its start and end, or 'none'.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $track_markers = 'ends';

	/**
	 * Map Height.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	protected $height;

	/**
	 * Map Width.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	protected $width;

	/**
	 * Map Height Maximum Height.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	protected $max_height;

	/**
	 * Map Maximum Width.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	protected $max_width;

	/**
	 * Pixel density of the static map, 1 for normal screens and 2 for high-density screens.
	 *
	 * @since 6.0.0
	 * @var int
	 */
	protected $scale = 1;

	/**
	 * Whether the provider's terms allow the site to store its map images and serve them itself.
	 *
	 * Images of such providers are served from the site by Sloc_Map_Cache, so the API key never reaches the browser.
	 *
	 * @since 6.0.0
	 * @var bool
	 */
	protected $cacheable = false;

	/**
	 * Longest time, in days, the provider's terms allow storing a map image. 0 for no limit.
	 *
	 * Stored images are fetched again at least this often, whatever the Refresh Stored Maps After setting says.
	 *
	 * @since 6.0.0
	 * @var int
	 */
	protected $max_cache_age = 0;

	/**
	 * Default output options of a static map, for the arguments of the same names.
	 *
	 * - 'durable': for a provider whose images the site stores, use the signed map image route, which keeps working
	 *   after the stored file is replaced or the cache cleared, instead of the stored file's URL.
	 * - 'dark': add the dark style for visitors in dark mode, with a `<picture>`, when the Dark Mode Maps setting is on.
	 * - 'srcset': add the provider's high-density images.
	 * - 'loading': add `loading="lazy"` to the image.
	 *
	 * @since 6.2.0
	 * @var bool[]
	 */
	const OUTPUT_DEFAULTS = array(
		'durable' => false,
		'dark'    => true,
		'srcset'  => true,
		'loading' => true,
	);

	/**
	 * Output options of the current map, from OUTPUT_DEFAULTS and the arguments.
	 *
	 * @since 6.2.0
	 * @var bool[]
	 */
	protected $output = self::OUTPUT_DEFAULTS;

	/**
	 * Image formats the provider can be asked for: 'png' (full color), 'png8' (256 colors), 'jpg', 'webp' or 'gif'.
	 *
	 * Providers without a choice of format leave this empty.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const FORMATS = array();

	/**
	 * Map style families, which each provider draws in its closest style.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const STYLE_FAMILIES = array( 'standard', 'light', 'dark', 'satellite', 'terrain' );

	/**
	 * Image format the plugin asks for when none is chosen, one of FORMATS, or an empty string to leave it to the provider.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const DEFAULT_FORMAT = '';

	/**
	 * Marker shapes for a single location: a pin, or a dot.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const MARKERS = array( 'pin', 'dot' );

	/**
	 * Marker sizes for a single location.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const MARKER_SIZES = array( 'small', 'medium', 'large' );

	/**
	 * Default marker color.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const DEFAULT_MARKER_COLOR = '#d32f2f';

	/**
	 * Scale bar settings: the units, and 'site' for the units the site displays.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const SCALEBARS = array( 'site', 'metric', 'imperial', 'both' );

	/**
	 * Corners of the map, for the scale bar.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const POSITIONS = array( 'bottomleft', 'bottomright', 'topleft', 'topright' );

	/**
	 * Scale bar: one of SCALEBARS, or an empty string for none. Only some providers draw one.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $scalebar = '';

	/**
	 * Corner of the scale bar, one of POSITIONS, as near as the provider can place it.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $scalebar_position = 'bottomleft';

	/**
	 * Where the map's credit goes: drawn on the image with the provider's logo, drawn on the image as text only, or
	 * printed under the map. Providers that can't do one draw the credit on the image.
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
	 * Corner of the credit drawn on the image, one of POSITIONS, for providers that can move it.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $attribution_position = 'bottomright';

	/**
	 * Marker shape for a single location, one of MARKERS.
	 *
	 * Providers without a dot draw their smallest pin instead.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $marker = 'pin';

	/**
	 * Marker color, as #rrggbb. Archive map markers use it too.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $marker_color = self::DEFAULT_MARKER_COLOR;

	/**
	 * Marker size for a single location, one of MARKER_SIZES.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $marker_size = 'medium';

	/**
	 * Chosen image format, one of FORMATS, or an empty string for the default.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $format = '';

	/**
	 * Map Style.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $style;

	/**
	 * The style the provider draws without a map's own style family: its style setting, or the Map Style setting's.
	 *
	 * @since 6.2.0
	 * @var string
	 */
	protected $default_style = '';

	/**
	 * The current map's own style family, such as a post's, or an empty string for the default style. A family the
	 * provider has no style for is empty here, and the default style is drawn.
	 *
	 * @since 6.2.0
	 * @var string
	 */
	protected $style_family = '';

	/**
	 * Username if appropriate.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $user;

	/**
	 * Static Map URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $static;

	/**
	 * Location Information: the address of the location, used to describe the map.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Used as the alternative text of the map image.
	 * @var string
	 */
	protected $location;

	/**
	 * Constructor for the Abstract Class.
	 *
	 * The default version of this just sets the parameters.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Without a usable width setting, the default width is the theme's content width, as for the setting itself.
	 * @since 6.1.0 Added the `format` argument.
	 * @since 6.1.0 Added the `marker`, `marker_color` and `marker_size` arguments.
	 * @since 6.1.0 Added the `scalebar` and `scalebar_position` arguments.
	 * @since 6.1.0 Added the `attribution` and `attribution_position` arguments.
	 * @since 6.2.0 Added the `durable`, `dark`, `srcset` and `loading` arguments.
	 *
	 * @param array $args {
	 *  Arguments.
	 *  @type string $api API Key.
	 *  @type float $latitude Latitude.
	 *  @type float $longitude Longitude.
	 *  @type float $altitude Altitude.
	 *  @type int $width Width.
	 *  @type int $height Height.
	 *  @type string $user Username.
	 *  @type string $style Style of Map.
	 *  @type int $map_zoom Map Zoom Level.
	 *  @type string $format Image format, one of FORMATS, or empty for the default. Default the sloc_{slug}_format setting.
	 *  @type string $marker Marker shape, one of MARKERS. Default the sloc_marker setting.
	 *  @type string $marker_color Marker color, #rrggbb. Default the sloc_marker_color setting.
	 *  @type string $marker_size Marker size, one of MARKER_SIZES. Default the sloc_marker_size setting.
	 *  @type string $scalebar Scale bar, one of SCALEBARS, or empty for none. Default the sloc_scalebar setting.
	 *  @type string $scalebar_position Scale bar corner, one of POSITIONS. Default the sloc_scalebar_position setting.
	 *  @type string $attribution Where the credit goes, one of ATTRIBUTIONS. Default the sloc_attribution setting.
	 *  @type string $attribution_position Corner of the credit on the image, one of POSITIONS. Default the
	 *                                     sloc_attribution_position setting.
	 *  @type bool   $durable Use an image URL that keeps working, for copies read outside the site. See
	 *                        OUTPUT_DEFAULTS. Default false.
	 *  @type bool   $dark    Add the dark mode image. Default true.
	 *  @type bool   $srcset  Add the high-density images. Default true.
	 *  @type bool   $loading Add loading="lazy". Default true.
	 */
	public function __construct( $args = array() ) {
		// The setting's registered default is the content width, but it isn't registered before init.
		$width = get_option( 'sloc_width' );
		if ( ! is_numeric( $width ) || $width < 1 ) {
			$width = Loc_Config::get_default_map_width();
		}
		$defaults = array(
			'width'                => $width,
			'height'               => round( $width / get_option( 'sloc_aspect', ( 16 / 9 ) ) ),
			'map_zoom'             => get_option( 'sloc_zoom' ),
			'api'                  => '',
			'latitude'             => null,
			'longitude'            => null,
			'altitude'             => null,
			'location'             => '',
			'user'                 => '',
			'style'                => '',
			'format'               => get_option( 'sloc_' . $this->slug . '_format', '' ),
			'marker'               => get_option( 'sloc_marker', 'pin' ),
			'marker_color'         => get_option( 'sloc_marker_color', self::DEFAULT_MARKER_COLOR ),
			'marker_size'          => get_option( 'sloc_marker_size', 'medium' ),
			'scalebar'             => get_option( 'sloc_scalebar', '' ),
			'scalebar_position'    => get_option( 'sloc_scalebar_position', 'bottomleft' ),
			'attribution'          => get_option( 'sloc_attribution', 'image' ),
			'attribution_position' => get_option( 'sloc_attribution_position', 'bottomright' ),
		);
		/** This filter is documented in includes/class-geo-provider.php */
		$defaults = apply_filters( 'sloc_geo_provider_defaults', $defaults );
		$r        = wp_parse_args( $args, $defaults );

		$this->height   = $r['height'];
		$this->width    = $r['width'];
		$this->location = $r['location'];
		$this->map_zoom = $r['map_zoom'];
		$this->user     = $r['user'];
		$this->style    = $r['style'];
		// Kept, so each map can choose its own style family and the next map gets this one back.
		$this->default_style = (string) $this->style;
		$this->api      = $r['api'];
		$this->format   = static::sanitize_format( $r['format'] );

		$this->marker       = self::sanitize_marker( $r['marker'] );
		$this->marker_color = self::sanitize_marker_color( $r['marker_color'] );
		$this->marker_size  = self::sanitize_marker_size( $r['marker_size'] );

		$this->scalebar          = self::sanitize_scalebar( $r['scalebar'] );
		$this->scalebar_position = self::sanitize_scalebar_position( $r['scalebar_position'] );

		$this->attribution          = self::sanitize_attribution( $r['attribution'] );
		$this->attribution_position = self::sanitize_attribution_position( $r['attribution_position'] );
		$this->output               = self::output_options( $r );
		$this->set( $r['latitude'], $r['longitude'], $r['altitude'] );
		$this->fit_size();

		if ( $this->is_active() && method_exists( $this, 'admin_init' ) ) {
			add_action( 'init', array( $this, 'init' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
		}
	}

	/**
	 * Makes the archive map a track: a line along its points, with markers at its ends or none.
	 *
	 * @since 6.1.0
	 *
	 * @param string $markers Optional. 'ends' (default) or 'none'.
	 */
	public function set_track( $markers = 'ends' ) {
		$this->track         = true;
		$this->track_markers = ( 'none' === $markers ) ? 'none' : 'ends';
	}

	/**
	 * Whether the archive map is a track.
	 *
	 * @since 6.1.0
	 *
	 * @return bool
	 */
	public function is_track() {
		return $this->track;
	}

	/**
	 * Returns the locations of an archive map that get a marker.
	 *
	 * @since 6.1.0
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return array Every location, or for a track its start and end, or none.
	 */
	protected function get_marker_locations( $locations ) {
		$locations = array_values( $locations );
		if ( ! $this->track ) {
			return $locations;
		}
		if ( 'none' === $this->track_markers || empty( $locations ) ) {
			return array();
		}
		$first = reset( $locations );
		$last  = end( $locations );
		return ( $first === $last ) ? array( $first ) : array( $first, $last );
	}

	/**
	 * Sanitizes the map style family setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Style family.
	 * @return string One of STYLE_FAMILIES, 'standard' by default.
	 */
	public static function sanitize_style_family( $value ) {
		return in_array( $value, self::STYLE_FAMILIES, true ) ? $value : 'standard';
	}

	/**
	 * Sanitizes a map's own style family, such as a post's.
	 *
	 * @since 6.2.0
	 *
	 * @param mixed $value Style family.
	 * @return string One of STYLE_FAMILIES, or an empty string for the default style.
	 */
	public static function sanitize_map_style( $value ) {
		return in_array( $value, self::STYLE_FAMILIES, true ) ? $value : '';
	}

	/**
	 * Returns the names of the style families.
	 *
	 * @since 6.2.0
	 *
	 * @return string[] Translated names, keyed by family.
	 */
	public static function get_style_family_names() {
		return array(
			'standard'  => __( 'Standard', 'simple-location' ),
			'light'     => __( 'Light', 'simple-location' ),
			'dark'      => __( 'Dark', 'simple-location' ),
			'satellite' => __( 'Satellite', 'simple-location' ),
			'terrain'   => __( 'Terrain', 'simple-location' ),
		);
	}

	/**
	 * Sanitizes a provider's map style setting.
	 *
	 * Styles are IDs, such as mapbox/streets-v12, or empty to use the Map Style setting. They aren't checked
	 * against the provider's list, which for some providers is fetched from the provider.
	 *
	 * @since 6.2.0
	 *
	 * @param mixed $value Style ID.
	 * @return string The style ID without markup, or an empty string.
	 */
	public static function sanitize_style( $value ) {
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	/**
	 * Returns the provider's style for each style family.
	 *
	 * @since 6.1.0
	 *
	 * @return string[] Style IDs keyed by family. A family the provider has no style for is left out, and drawn in the
	 *                  standard style.
	 */
	public function get_family_styles() {
		return array();
	}

	/**
	 * Returns the provider's style for a style family.
	 *
	 * @since 6.1.0
	 *
	 * @param string $family One of STYLE_FAMILIES.
	 * @return string Style ID, the standard style for a family the provider doesn't have, or an empty string if the
	 *                provider has no styles.
	 */
	public function get_family_style( $family ) {
		$styles = $this->get_family_styles();
		return $styles[ $family ] ?? ( $styles['standard'] ?? '' );
	}

	/**
	 * Returns the style the provider's style setting chooses, or the Map Style family's style when it isn't set.
	 *
	 * @since 6.1.0
	 *
	 * @param string $option Name of the provider's style setting, such as sloc_google_style.
	 * @return string Style ID.
	 */
	protected function get_style_setting( $option ) {
		$style = get_option( $option, '' );
		if ( is_string( $style ) && '' !== $style ) {
			return $style;
		}
		return $this->get_family_style( self::sanitize_style_family( get_option( 'sloc_map_style', 'standard' ) ) );
	}

	/**
	 * Returns a copy of the provider that draws the dark style, for visitors whose system is set to dark mode.
	 *
	 * @since 6.1.0
	 *
	 * @since 6.2.0 Null when the map's `dark` argument is false.
	 * @since 6.2.0 Null when the map has its own style family other than standard, such as a post set to satellite.
	 *
	 * @return Map_Provider|null The copy, or null if the Dark Mode Maps setting is off, the map's `dark` argument is
	 *                           false, the map has its own style family other than standard, the provider has no dark
	 *                           style, or the map already uses it.
	 */
	public function get_dark_variant() {
		if ( ! $this->output['dark'] || ! get_option( 'sloc_map_dark_mode' ) ) {
			return null;
		}
		// A map with its own look, such as satellite, keeps it in dark mode.
		if ( '' !== $this->style_family && 'standard' !== $this->style_family ) {
			return null;
		}
		$styles = $this->get_family_styles();
		if ( ! isset( $styles['dark'] ) || $styles['dark'] === $this->style ) {
			return null;
		}
		$dark        = clone $this;
		$dark->style = $styles['dark'];
		return $dark;
	}

	/**
	 * Returns a `<source>` for the dark mode variant of a map image, to put in a `<picture>` before the image.
	 *
	 * @since 6.1.0
	 *
	 * @param string $srcset Image candidates of the dark variant, not escaped.
	 * @return string HTML.
	 */
	protected static function dark_source( $srcset ) {
		return sprintf( '<source media="(prefers-color-scheme: dark)" srcset="%s" />', esc_attr( $srcset ) );
	}

	/**
	 * Returns a vendor's API key for maps.
	 *
	 * Static map image URLs show the key to visitors, so a vendor's maps can use their own key, which can be
	 * restricted to maps and to this site without breaking the vendor's server-side lookups. Without one,
	 * the vendor's main key is used.
	 *
	 * @since 6.0.0
	 *
	 * @param string $vendor Vendor slug, as in the option name sloc_{vendor}_api.
	 * @return string API key, or an empty string.
	 */
	protected static function get_map_api_key( $vendor ) {
		$key = get_option( 'sloc_' . $vendor . '_map_api' );
		if ( empty( $key ) || ! is_string( $key ) ) {
			$key = get_option( 'sloc_' . $vendor . '_api' );
		}
		return is_string( $key ) ? $key : '';
	}

	/**
	 * Registers the setting for a vendor's map API key.
	 *
	 * @since 6.0.0
	 *
	 * @param string $name   Vendor name.
	 * @param string $vendor Vendor slug, as in the option name sloc_{vendor}_map_api.
	 */
	protected static function register_map_api_setting( $name, $vendor ) {
		self::register_settings_api( $name, 'sloc_' . $vendor . '_map_api', __( 'Map API Key', 'simple-location' ) );
	}

	/**
	 * Adds the field for a vendor's map API key to the API settings.
	 *
	 * @since 6.0.0
	 * @since 6.1.0 Adds the `$description` parameter.
	 *
	 * @param string      $name        Vendor name.
	 * @param string      $vendor      Vendor slug, as in the option name sloc_{vendor}_map_api.
	 * @param string|null $description Field description. Default a note that visitors see the key, and that the vendor's
	 *                                 main key is used without one.
	 */
	protected static function add_map_api_setting( $name, $vendor, $description = null ) {
		if ( null === $description ) {
			$description = sprintf(
				// translators: Name of the map vendor.
				__( 'Optional. Map images show this key to every visitor, so use a key that only allows static maps and is restricted to this site. Leave empty to use the %s API key.', 'simple-location' ),
				$name
			);
		}
		add_settings_field(
			'sloc_' . $vendor . '_map_api',
			// translators: Name of the map vendor.
			sprintf( __( '%s Map API Key', 'simple-location' ), $name ),
			array( 'Loc_Config', 'string_callback' ),
			'sloc_api',
			'sloc_api',
			array(
				'label_for'   => 'sloc_' . $vendor . '_map_api',
				'type'        => 'password',
				'description' => $description,
			)
		);
	}

	/**
	 * Sanitizes an image format setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Image format.
	 * @return string One of the provider's FORMATS, or an empty string for the default, which the default format also
	 *                gives. 'jpeg' becomes 'jpg'.
	 */
	public static function sanitize_format( $value ) {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		if ( 'jpeg' === $value ) {
			$value = 'jpg';
		}
		return ( static::DEFAULT_FORMAT !== $value && in_array( $value, static::FORMATS, true ) ) ? $value : '';
	}

	/**
	 * Sanitizes the marker shape setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Marker shape.
	 * @return string One of MARKERS. Default 'pin'.
	 */
	public static function sanitize_marker( $value ) {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		return in_array( $value, self::MARKERS, true ) ? $value : 'pin';
	}

	/**
	 * Sanitizes the marker color setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Color, #rrggbb or rrggbb.
	 * @return string Color as #rrggbb, in lower case. Default DEFAULT_MARKER_COLOR.
	 */
	public static function sanitize_marker_color( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^#?([0-9a-f]{6})$/i', trim( $value ), $match ) ) {
			return self::DEFAULT_MARKER_COLOR;
		}
		return '#' . strtolower( $match[1] );
	}

	/**
	 * Sanitizes the marker size setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Marker size.
	 * @return string One of MARKER_SIZES. Default 'medium'.
	 */
	public static function sanitize_marker_size( $value ) {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		return in_array( $value, self::MARKER_SIZES, true ) ? $value : 'medium';
	}

	/**
	 * Sanitizes the scale bar setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Scale bar.
	 * @return string One of SCALEBARS, or an empty string for none.
	 */
	public static function sanitize_scalebar( $value ) {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		return in_array( $value, self::SCALEBARS, true ) ? $value : '';
	}

	/**
	 * Sanitizes the scale bar position setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Corner.
	 * @return string One of POSITIONS. Default 'bottomleft'.
	 */
	public static function sanitize_scalebar_position( $value ) {
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		return in_array( $value, self::POSITIONS, true ) ? $value : 'bottomleft';
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
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		return in_array( $value, self::ATTRIBUTIONS, true ) ? $value : 'image';
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
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		return in_array( $value, self::POSITIONS, true ) ? $value : 'bottomright';
	}

	/**
	 * Returns the units of the scale bar, with the site's units in place of 'site'.
	 *
	 * @since 6.1.0
	 *
	 * @return string 'metric', 'imperial', 'both', or an empty string for no scale bar.
	 */
	public function get_scalebar_units() {
		if ( 'site' !== $this->scalebar ) {
			return $this->scalebar;
		}
		return $this->site_uses_imperial() ? 'imperial' : 'metric';
	}

	/**
	 * Whether the site displays imperial units.
	 *
	 * @since 6.1.0
	 *
	 * @return bool
	 */
	protected function site_uses_imperial() {
		return 'imperial' === get_option( 'sloc_measurements', Loc_Config::measurement_default() );
	}

	/**
	 * Returns the language of the map's labels, for providers that can translate them.
	 *
	 * @since 6.1.0
	 *
	 * @return string Language tag, such as `en-US`, or an empty string to leave the labels in their local languages.
	 */
	public function get_language() {
		/**
		 * Filters the language of map labels, for map providers that can translate them.
		 *
		 * Google and TomTom take the whole tag; HERE and Geoapify take its two-letter language code.
		 *
		 * @since 6.1.0
		 *
		 * @param string       $language Language tag, such as `en-US`. Default the site's language. An empty string
		 *                               leaves the labels in each place's local language.
		 * @param Map_Provider $map      Map provider.
		 */
		$language = apply_filters( 'sloc_map_language', get_bloginfo( 'language' ), $this );
		return is_string( $language ) ? trim( $language ) : '';
	}

	/**
	 * Returns the two-letter code of the language of the map's labels.
	 *
	 * @since 6.1.0
	 *
	 * @return string ISO 639-1 code, such as `en`, or an empty string if the language has none.
	 */
	protected function get_language_code() {
		$code = strtolower( strtok( $this->get_language(), '-_' ) );
		return preg_match( '/^[a-z]{2}$/', $code ) ? $code : '';
	}

	/**
	 * Adds the two-letter code of the labels' language to a query, as `lang`, when there is one.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args Query parameters.
	 * @return array Query parameters.
	 */
	protected function add_language_code( $args ) {
		$code = $this->get_language_code();
		if ( '' !== $code ) {
			$args['lang'] = $code;
		}
		return $args;
	}

	/**
	 * Returns the marker color without its #, as most providers take it.
	 *
	 * @since 6.1.0
	 *
	 * @return string Color as rrggbb.
	 */
	protected function get_marker_hex() {
		return ltrim( $this->marker_color, '#' );
	}

	/**
	 * Returns the image format the map is drawn in.
	 *
	 * @since 6.1.0
	 *
	 * @return string One of FORMATS, or an empty string when the provider decides.
	 */
	public function get_format() {
		return '' === $this->format ? static::DEFAULT_FORMAT : $this->format;
	}

	/**
	 * Returns the provider's own name for the image format the map is drawn in.
	 *
	 * @since 6.1.0
	 *
	 * @param string[] $names Optional. The provider's names for formats, keyed by format, where they differ. Default empty.
	 * @return string Format name, or an empty string when the provider decides.
	 */
	protected function get_vendor_format( $names = array() ) {
		$format = $this->get_format();
		return $names[ $format ] ?? $format;
	}

	/**
	 * Returns the names of the provider's image formats, for the format setting.
	 *
	 * @since 6.1.0
	 *
	 * @return string[] Names keyed by format, starting with the default, keyed by an empty string.
	 */
	public static function get_format_names() {
		$names = array(
			'png'  => __( 'PNG', 'simple-location' ),
			'png8' => __( 'PNG with 256 colors', 'simple-location' ),
			'jpg'  => __( 'JPEG', 'simple-location' ),
			'webp' => __( 'WebP', 'simple-location' ),
			'gif'  => __( 'GIF', 'simple-location' ),
		);
		$list  = array(
			'' => isset( $names[ static::DEFAULT_FORMAT ] )
				// translators: Name of the image format used by default.
				? sprintf( __( 'Default (%s)', 'simple-location' ), $names[ static::DEFAULT_FORMAT ] )
				: __( 'Default of the provider', 'simple-location' ),
		);
		foreach ( static::FORMATS as $format ) {
			if ( static::DEFAULT_FORMAT !== $format && isset( $names[ $format ] ) ) {
				$list[ $format ] = $names[ $format ];
			}
		}
		return $list;
	}

	/**
	 * Registers the setting for the provider's image format.
	 *
	 * @since 6.1.0
	 *
	 * @param string $name   Provider name.
	 * @param string $vendor Provider slug, as in the option name sloc_{vendor}_format.
	 */
	protected static function register_format_setting( $name, $vendor ) {
		register_setting(
			'simloc',
			'sloc_' . $vendor . '_format',
			array(
				'type'              => 'string',
				'description'       => $name . ' Image Format',
				'show_in_rest'      => false,
				'default'           => '',
				'sanitize_callback' => array( static::class, 'sanitize_format' ),
			)
		);
	}

	/**
	 * Adds the field for the provider's image format to the map settings.
	 *
	 * @since 6.1.0
	 *
	 * @param string $name   Provider name.
	 * @param string $vendor Provider slug, as in the option name sloc_{vendor}_format.
	 */
	protected static function add_format_setting( $name, $vendor ) {
		add_settings_field(
			'sloc_' . $vendor . '_format',
			// translators: Name of the map provider.
			sprintf( __( '%s Image Format', 'simple-location' ), $name ),
			array( 'Loc_Config', 'values_callback' ),
			'simloc',
			'sloc_map',
			array(
				'label_for'   => 'sloc_' . $vendor . '_format',
				'list'        => static::get_format_names(),
				'description' => __( 'JPEG suits satellite maps. PNG keeps lines and labels sharp.', 'simple-location' ),
			)
		);
	}

	/**
	 * Is Provider Active
	 */
	public function is_active() {
		$option = get_option( 'sloc_map_provider' );
		return ( $this->slug === $option );
	}

	/**
	 * Set and Validate Coordinates.
	 *
	 * @since 6.0.0 An array sets the map's description from 'location' or 'address', and clears it otherwise.
	 *
	 * @since 6.2.0 An array sets the output options too, from the `durable`, `dark`, `srcset` and `loading` keys;
	 *              missing keys use OUTPUT_DEFAULTS.
	 *
	 * @since 6.2.0 An array's `style_family` (or a post's saved `map_style`) chooses the map's style family; see
	 *              set_style_family().
	 *
	 * @param array|float $args Latitude or array of all three properties.
	 * @param float       $lng Longitude. Optional if first property is an array.
	 * @param float       $alt Altitude. Optional.
	 * @return boolean Return False if Validation Failed
	 */
	public function set( $args, $lng = null, $alt = null ) {
		if ( is_array( $args ) ) {
			if ( isset( $args['height'] ) ) {
				$this->height = $args['height'];
			}
			if ( isset( $args['width'] ) ) {
				$this->width = $args['width'];
			}
			if ( isset( $args['map_zoom'] ) ) {
				$this->map_zoom = $args['map_zoom'];
			}
			// One provider draws many maps, so each map's description and output options replace the last ones.
			$location       = $args['location'] ?? $args['address'] ?? '';
			$this->location = is_scalar( $location ) ? (string) $location : '';
			$this->output   = self::output_options( $args );
			$this->set_style_family( $args['style_family'] ?? $args['map_style'] ?? '' );
			$this->fit_size();
		}
		return parent::set( $args, $lng, $alt );
	}

	/**
	 * Draws the current map in the provider's style for a style family, or in the default style.
	 *
	 * A family the provider has no style for, such as satellite for a provider without satellite maps, draws the
	 * default style instead. The family isn't changed wherever it was chosen, so it applies again with a provider that
	 * has that style.
	 *
	 * @since 6.2.0
	 *
	 * @param string $family One of STYLE_FAMILIES, or an empty string for the default style.
	 * @return bool Whether the provider has a style for the family; true for the default style, false for an unknown family.
	 */
	public function set_style_family( $family ) {
		$asked  = is_string( $family ) ? $family : '';
		$family = self::sanitize_map_style( $family );
		$styles = $this->get_family_styles();
		if ( '' !== $family && isset( $styles[ $family ] ) && '' !== $styles[ $family ] ) {
			$this->style        = $styles[ $family ];
			$this->style_family = $family;
			return true;
		}
		$this->style        = $this->default_style;
		$this->style_family = '';
		// The default is always there; an unknown family isn't.
		return '' === $asked;
	}

	/**
	 * Returns the current map's own style family, if the provider has a style for it.
	 *
	 * @since 6.2.0
	 *
	 * @return string One of STYLE_FAMILIES, or an empty string for the default style.
	 */
	public function get_style_family() {
		return $this->style_family;
	}

	/**
	 * Returns the output options from map arguments.
	 *
	 * @since 6.2.0
	 *
	 * @param array $args Map arguments, with any of the keys of OUTPUT_DEFAULTS.
	 * @return bool[] Output options, with every key of OUTPUT_DEFAULTS.
	 */
	protected static function output_options( $args ) {
		$output = self::OUTPUT_DEFAULTS;
		foreach ( $output as $key => $default ) {
			if ( isset( $args[ $key ] ) ) {
				$output[ $key ] = rest_sanitize_boolean( $args[ $key ] );
			}
		}
		return $output;
	}

	/**
	 * Shrinks the map size to fit the provider's maximum, keeping its shape.
	 *
	 * @since 6.0.0
	 */
	protected function fit_size() {
		if ( ! is_numeric( $this->width ) || ! is_numeric( $this->height ) ) {
			return;
		}
		$ratio = 1;
		if ( $this->max_width && $this->width > $this->max_width ) {
			$ratio = $this->max_width / $this->width;
		}
		if ( $this->max_height && $this->height * $ratio > $this->max_height ) {
			$ratio = $this->max_height / $this->height;
		}
		if ( $ratio < 1 ) {
			$this->width  = (int) round( $this->width * $ratio );
			$this->height = (int) round( $this->height * $ratio );
		}
	}

	/**
	 * Returns the pixel densities the provider can draw the static map at.
	 *
	 * Every density shows the same area as the normal map. Providers without a high-density option return only 1.
	 *
	 * @since 6.0.0
	 *
	 * @return int[] Pixel densities.
	 */
	public function get_scales() {
		return array( 1 );
	}

	/**
	 * Sets the pixel density of the static map.
	 *
	 * @since 6.0.0
	 *
	 * @param int $scale Pixel density. Ignored if the provider can't draw it.
	 */
	public function set_scale( $scale ) {
		if ( in_array( (int) $scale, $this->get_scales(), true ) ) {
			$this->scale = (int) $scale;
		}
	}

	/**
	 * Whether the provider's terms allow the site to store its map images and serve them itself.
	 *
	 * @since 6.0.0
	 *
	 * @return bool
	 */
	public function is_cacheable() {
		return $this->cacheable;
	}

	/**
	 * Returns the longest time, in days, the provider's terms allow storing a map image.
	 *
	 * @since 6.0.0
	 *
	 * @return int Days, or 0 for no limit.
	 */
	public function get_max_cache_age() {
		return (int) $this->max_cache_age;
	}

	/**
	 * Returns the parameters that describe the current map, for a map image served from the site.
	 *
	 * @since 6.0.0
	 * @since 6.1.0 Has 'options', a hash of the settings that change the image, from get_cache_options().
	 * @since 6.1.0 A track has 'track', its markers.
	 *
	 * @param array|null $locations Optional. List of array( latitude, longitude ) points for an archive map.
	 * @return string[] Map parameters.
	 */
	public function get_cache_args( $locations = null ) {
		$args = array(
			'provider' => (string) $this->slug,
			'width'    => (string) $this->width,
			'height'   => (string) $this->height,
			'zoom'     => (string) $this->map_zoom,
			'style'    => (string) $this->style,
			'scale'    => (string) $this->scale,
		);
		if ( null === $locations ) {
			$args['lat'] = (string) $this->latitude;
			$args['lng'] = (string) $this->longitude;
		} else {
			$args['points'] = Sloc_Polyline::encode( $locations );
		}
		// The settings are signed as a hash, so a changed setting gives new images.
		$args['options'] = substr( md5( (string) wp_json_encode( $this->get_cache_options( $locations ) ) ), 0, 12 );
		if ( $this->track && null !== $locations ) {
			$args['track'] = $this->track_markers;
		}
		return $args;
	}

	/**
	 * Returns the settings, other than the map parameters, that change the map image.
	 *
	 * @since 6.1.0
	 *
	 * @param array|null $locations Optional. List of array( latitude, longitude ) points for an archive map.
	 * @return array Setting values.
	 */
	protected function get_cache_options( $locations = null ) {
		return array(
			$this->format,
			$this->marker,
			$this->marker_color,
			$this->marker_size,
			$this->get_scalebar_units(),
			$this->scalebar_position,
			$this->attribution,
			$this->attribution_position,
		);
	}

	/**
	 * Fetches the current map's image from the provider, for the site to store it.
	 *
	 * @since 6.1.0
	 *
	 * @param array|null $locations Optional. List of array( latitude, longitude ) points for an archive map.
	 * @param array      $args      Optional. Request arguments for wp_safe_remote_get(). Default empty.
	 * @return array|WP_Error Response, or an error if the map can't be built or fetched.
	 */
	public function fetch_static_map( $locations = null, $args = array() ) {
		$url = $this->get_static_map_url( $locations );
		if ( '' === $url ) {
			return new WP_Error( 'sloc_map_not_found', __( 'This map is not available.', 'simple-location' ), array( 'status' => 404 ) );
		}
		return wp_safe_remote_get( $url, $args );
	}

	/**
	 * Returns the provider's URL of the current map's image.
	 *
	 * @since 6.1.0
	 *
	 * @param array|null $locations Optional. List of array( latitude, longitude ) points for an archive map.
	 * @return string URL, or an empty string if the map can't be built.
	 */
	public function get_static_map_url( $locations = null ) {
		$url = null === $locations ? $this->get_the_static_map() : $this->get_archive_map( $locations );
		return ( empty( $url ) || ! is_string( $url ) ) ? '' : $url;
	}

	/**
	 * Returns how long visitors are sent to the provider for a map image the site must not store.
	 *
	 * The site's map cache stores every image a cacheable provider sends, unless this says otherwise, for example
	 * because the response is marked private.
	 *
	 * @since 6.1.0
	 *
	 * @param array $response Response with the image, from fetch_static_map().
	 * @return int Seconds to send visitors to the provider's URL instead of storing the image, or 0 to store it.
	 */
	public function get_passthrough_time( $response ) {
		return 0;
	}

	/**
	 * Returns the image URL for an archive map with a marker at each location.
	 *
	 * Providers whose terms allow it are served from the site, unless that is turned off, so the API key isn't in the page.
	 *
	 * @since 6.0.0
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return string URL, or an empty string if there is no map.
	 */
	public function get_archive_map_src( $locations ) {
		$map = $this->get_archive_map( $locations );
		if ( empty( $map ) || is_wp_error( $map ) ) {
			return '';
		}
		return Sloc_Map_Cache::is_enabled( $this ) ? Sloc_Map_Cache::get_url( $this, $locations ) : $map;
	}

	/**
	 * Returns the view of an archive map that fits all its locations, for providers whose archive maps the plugin centres.
	 *
	 * The locations' bounding box is fitted, in Web Mercator, into the image less ARCHIVE_PADDING on each side. The zoom is
	 * no closer than the site's zoom setting or the provider's maximum, and is rounded down when the provider only takes
	 * whole zoom levels.
	 *
	 * @since 6.1.0
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return array|null Array with 'latitude', 'longitude' and 'zoom', or null if the provider fits its own archive maps,
	 *                    there are no usable locations, or they cross the antimeridian.
	 */
	public function get_archive_view( $locations ) {
		if ( ! $this->tile_size || empty( $locations ) || ! is_array( $locations ) ) {
			return null;
		}
		$width  = (float) $this->width;
		$height = (float) $this->height;
		if ( $width <= 0 || $height <= 0 ) {
			return null;
		}
		$points = array();
		foreach ( $locations as $location ) {
			if ( ! is_array( $location ) || ! isset( $location[0], $location[1] ) || ! is_numeric( $location[0] ) || ! is_numeric( $location[1] ) ) {
				return null;
			}
			$points[] = self::mercator( (float) $location[0], (float) $location[1] );
		}
		$xs = array_column( $points, 0 );
		$ys = array_column( $points, 1 );
		$dx = max( $xs ) - min( $xs );
		$dy = max( $ys ) - min( $ys );
		// Locations more than half the world apart are probably either side of the antimeridian.
		if ( $dx > 0.5 ) {
			return null;
		}

		$zoom = is_numeric( $this->map_zoom ) ? (float) $this->map_zoom : 14;
		if ( $this->max_map_zoom ) {
			$zoom = min( $zoom, (float) $this->max_map_zoom );
		}
		$inner_width  = max( 1, $width - 2 * self::ARCHIVE_PADDING );
		$inner_height = max( 1, $height - 2 * self::ARCHIVE_PADDING );
		if ( $dx > 0 ) {
			$zoom = min( $zoom, log( $inner_width / ( $dx * $this->tile_size ), 2 ) );
		}
		if ( $dy > 0 ) {
			$zoom = min( $zoom, log( $inner_height / ( $dy * $this->tile_size ), 2 ) );
		}
		$zoom = $this->fractional_zoom ? floor( $zoom * 100 ) / 100 : floor( $zoom );
		$zoom = max( 0, $zoom );

		$x = ( max( $xs ) + min( $xs ) ) / 2;
		$y = ( max( $ys ) + min( $ys ) ) / 2;
		return array(
			'latitude'  => round( rad2deg( atan( sinh( M_PI * ( 1 - 2 * $y ) ) ) ), 6 ),
			'longitude' => round( $x * 360 - 180, 6 ),
			'zoom'      => $this->fractional_zoom ? $zoom : (int) $zoom,
		);
	}

	/**
	 * Projects a location to Web Mercator, as a fraction of the world's width and height from its top-left corner.
	 *
	 * @since 6.1.0
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @return float[] Array( x, y ), each from 0 to 1.
	 */
	protected static function mercator( $latitude, $longitude ) {
		// Web Mercator ends at about 85.05 degrees north and south.
		$sin = max( -0.9999, min( 0.9999, sin( deg2rad( $latitude ) ) ) );
		return array(
			( $longitude + 180 ) / 360,
			0.5 - log( ( 1 + $sin ) / ( 1 - $sin ) ) / ( 4 * M_PI ),
		);
	}

	/**
	 * Converts a marker's box on the image to percent of the image's size, keeping only the part on the image.
	 *
	 * @since 6.1.0
	 *
	 * @param float[] $box    Array( left, top, right, bottom ) in pixels from the image's top-left corner.
	 * @param float   $width  Image width in pixels.
	 * @param float   $height Image height in pixels.
	 * @return float[]|null Array( left, top, right, bottom ) in percent, or null if the marker is outside the image.
	 */
	protected static function marker_box_percent( $box, $width, $height ) {
		$left   = max( 0, min( $width, $box[0] ) );
		$top    = max( 0, min( $height, $box[1] ) );
		$right  = max( 0, min( $width, $box[2] ) );
		$bottom = max( 0, min( $height, $box[3] ) );
		if ( $right <= $left || $bottom <= $top ) {
			return null;
		}
		return array( 100 * $left / $width, 100 * $top / $height, 100 * $right / $width, 100 * $bottom / $height );
	}

	/**
	 * Returns where each location's marker is on the archive map.
	 *
	 * When the plugin chooses the archive map's view (see get_archive_view()), each location is projected onto the image
	 * and given the provider's marker box. Providers that fit their own archive maps have no clickable markers, unless
	 * they override this.
	 *
	 * @since 6.1.0
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return array Boxes keyed by the location's index in $locations, each array( left, top, right, bottom ) in percent of
	 *               the image's width and height. Markers outside the image are left out.
	 */
	public function get_archive_marker_boxes( $locations ) {
		$view = $this->get_archive_view( $locations );
		if ( null === $view ) {
			return array();
		}
		$width  = (float) $this->width;
		$height = (float) $this->height;
		$world  = $this->tile_size * pow( 2, $view['zoom'] );
		$center = self::mercator( $view['latitude'], $view['longitude'] );
		$boxes  = array();
		foreach ( array_values( $locations ) as $index => $location ) {
			$point = self::mercator( (float) $location[0], (float) $location[1] );
			$x     = ( $point[0] - $center[0] ) * $world + $width / 2;
			$y     = ( $point[1] - $center[1] ) * $world + $height / 2;
			$box   = self::marker_box_percent(
				array( $x + $this->marker_box[0], $y + $this->marker_box[1], $x + $this->marker_box[2], $y + $this->marker_box[3] ),
				$width,
				$height
			);
			if ( null !== $box ) {
				$boxes[ $index ] = $box;
			}
		}
		return $boxes;
	}

	/**
	 * Returns the srcset of an archive map, with an image for each pixel density the provider can draw.
	 *
	 * Every density shows the same area, so the marker links fit them all.
	 *
	 * @since 6.1.0
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return string Comma-separated image candidates, not escaped, or an empty string if there is only one density.
	 */
	public function get_archive_srcset( $locations ) {
		$scales = $this->get_scales();
		if ( count( $scales ) < 2 ) {
			return '';
		}
		$scale  = $this->scale;
		$srcset = array();
		foreach ( $scales as $density ) {
			$this->scale = $density;
			$image       = $this->get_archive_map_src( $locations );
			if ( '' === $image ) {
				$this->scale = $scale;
				return '';
			}
			$srcset[] = sprintf( '%1$s %2$sx', esc_url_raw( $image ), $density );
		}
		$this->scale = $scale;
		return implode( ', ', $srcset );
	}

	/**
	 * Returns the HTML for an archive map, with a link to each post over its marker when the provider says where they are.
	 *
	 * The links are positioned in percent, so they stay over their markers when the image is scaled, and fit every
	 * pixel density in its srcset.
	 *
	 * @since 6.1.0
	 * @since 6.2.0 Enqueues the front-end stylesheet; see Simple_Location_Plugin::enqueue_styles().
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @param int[] $post_ids  Optional. The post of each location, in the same order. Default empty, for no links.
	 * @return string HTML.
	 */
	public function get_archive_map_html( $locations, $post_ids = array() ) {
		Simple_Location_Plugin::enqueue_styles();
		$src    = $this->get_archive_map_src( $locations );
		$srcset = '' === $src ? '' : $this->get_archive_srcset( $locations );
		$html   = sprintf(
			'<img class="archive-map sloc-map" src="%1$s"%2$s alt="%3$s" />',
			esc_url( $src ),
			'' === $srcset ? '' : sprintf( ' srcset="%s"', esc_attr( $srcset ) ),
			esc_attr__( 'Map of the locations listed on this page', 'simple-location' )
		);
		// The dark style shows the same view, so the marker links fit it too.
		$dark = '' === $src ? null : $this->get_dark_variant();
		if ( $dark ) {
			$dark_src = $dark->get_archive_map_src( $locations );
			if ( '' !== $dark_src ) {
				$dark_srcset = $dark->get_archive_srcset( $locations );
				$html        = '<picture>' . self::dark_source( '' === $dark_srcset ? esc_url_raw( $dark_src ) : $dark_srcset ) . $html . '</picture>';
			}
		}
		if ( '' === $src || empty( $post_ids ) || ! is_array( $locations ) || count( $post_ids ) !== count( $locations ) ) {
			return $html;
		}
		$post_ids = array_values( $post_ids );
		$links    = array();
		foreach ( $this->get_archive_marker_boxes( $locations ) as $index => $box ) {
			if ( ! isset( $post_ids[ $index ] ) ) {
				continue;
			}
			$title   = self::get_marker_title( $post_ids[ $index ], $locations[ $index ] );
			$links[] = sprintf(
				'<a class="sloc-archive-map-marker" href="%1$s" style="%2$s" title="%3$s" aria-label="%3$s"></a>',
				esc_url( get_permalink( $post_ids[ $index ] ) ),
				esc_attr( sprintf( 'left:%1$s%%;top:%2$s%%;width:%3$s%%;height:%4$s%%', round( $box[0], 3 ), round( $box[1], 3 ), round( $box[2] - $box[0], 3 ), round( $box[3] - $box[1], 3 ) ) ),
				esc_attr( $title )
			);
		}
		if ( empty( $links ) ) {
			return $html;
		}
		return '<div class="sloc-archive-map">' . $html . implode( '', $links ) . '</div>';
	}

	/**
	 * Returns the label of an archive map marker: where the post is.
	 *
	 * That is the post's venue, when the venue is publicly viewable; otherwise its location, address or coordinates,
	 * as the location is displayed on the post.
	 *
	 * @since 6.1.1
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $location Array( latitude, longitude ) of the marker.
	 * @return string Plain text label.
	 */
	public static function get_marker_title( $post_id, $location ) {
		$title = '';
		$venue = (int) Post_Venue::get_post_venue( $post_id );
		if ( $venue > 0 && is_post_publicly_viewable( $venue ) ) {
			$title = get_the_title( $venue );
		}
		if ( '' === $title ) {
			$term = Location_Taxonomy::get_post_location( $post_id );
			if ( $term ) {
				$title = Location_Taxonomy::display_name(
					$term,
					array(
						'links' => false,
						'flag'  => false,
					)
				);
			}
		}
		if ( '' === $title ) {
			$address = get_post_geodata( $post_id, 'address' );
			$title   = is_string( $address ) ? $address : '';
		}
		if ( '' === $title && isset( $location[0], $location[1] ) ) {
			$title = dec_to_dms( $location[0], $location[1] );
		}
		$title = trim( wp_specialchars_decode( wp_strip_all_tags( $title ), ENT_QUOTES ) );

		/**
		 * Filters the label of an archive map marker, shown when hovering over it and read by screen readers.
		 *
		 * @since 6.1.1
		 *
		 * @param string $title    Plain text label: the post's venue, or else its location, address or coordinates.
		 * @param int    $post_id  Post ID.
		 * @param array  $location Array( latitude, longitude ) of the marker.
		 */
		return (string) apply_filters( 'sloc_archive_marker_title', $title, $post_id, $location );
	}


	/**
	 * Return an array of styles with key being id and value being display name.
	 *
	 * @return array
	 */
	abstract public function get_styles();

	/**
	 * Return a URL for a static map.
	 *
	 * @return string $url URL of MAP.
	 */
	abstract public function get_the_static_map();


	/**
	 * Return a URL for a static map with multiple locations.
	 *
	 * @param array $locations Array of latitude and longitudes.
	 * @return string $url URL of MAP.
	 */
	abstract public function get_archive_map( $locations );

	/**
	 * Return a URL for a link to a map.
	 *
	 * @return string $url URL of link to a map.
	 */
	abstract public function get_the_map_url();

	/**
	 * Return HTML code for a map.
	 *
	 * @param boolean $static Return Static or Dynamic Map.
	 * @return string HTML marked up map.
	 */
	abstract public function get_the_map( $static = false );


	/**
	 * Returns the alternative text of the static map image.
	 *
	 * The map is the only content of its link, so this text is also the link's accessible name.
	 *
	 * @since 6.0.0
	 *
	 * @return string Alternative text, not escaped.
	 */
	public function get_alt_text() {
		$location = trim( wp_strip_all_tags( (string) $this->location ) );
		$alt      = '' === $location
			? __( 'Map showing this location', 'simple-location' )
			/* translators: %s: Address of the location. */
			: sprintf( __( 'Map showing %s', 'simple-location' ), $location );

		/**
		 * Filters the alternative text of a static map image.
		 *
		 * @since 6.0.0
		 *
		 * @param string       $alt Alternative text.
		 * @param Map_Provider $map Map provider, set to the map's location.
		 */
		return (string) apply_filters( 'sloc_map_alt', $alt, $this );
	}

	/**
	 * Returns the map's credit to show next to the map, for providers that don't draw it on the image.
	 *
	 * @since 6.1.0
	 *
	 * @return string HTML, or an empty string. Providers draw their credit on the map image by default.
	 */
	public function get_attribution_html() {
		return '';
	}

	/**
	 * Returns the HTML for a static map image linked to the full map.
	 *
	 * @since 4.1.10
	 * @since 6.0.0 The URLs and sizes are escaped.
	 * @since 6.0.0 The srcset uses the provider's high-density maps of the same area, and is left out when the provider has none.
	 * @since 6.0.0 Providers whose terms allow it are served from the site, so the API key isn't in the page.
	 * @since 6.0.0 The image has alternative text.
	 * @since 6.1.0 With the Dark Mode Maps setting on, a `<picture>` adds the provider's dark style for visitors in dark mode.
	 * @since 6.2.0 The map's `dark`, `srcset`, `loading` and `durable` arguments can turn the `<picture>`, srcset and
	 *              lazy loading off, and use image URLs that keep working; with the first three off it is a plain
	 *              `<a><img></a>`.
	 * @since 6.2.0 Enqueues the front-end stylesheet; see Simple_Location_Plugin::enqueue_styles().
	 *
	 * @param bool $srcset Optional. Add a srcset with the provider's high-density maps. Default false.
	 * @return string HTML.
	 */
	public function get_the_static_map_html( $srcset = false ) {
		Simple_Location_Plugin::enqueue_styles();
		$images = $this->get_static_images( $srcset );
		if ( is_wp_error( $images ) ) {
			return $this->static_map_error( $images );
		}
		$img = sprintf(
			'<img class="sloc-map" src="%1$s"%2$s width="%3$s" height="%4$s" alt="%5$s"%6$s />',
			esc_url( $images['src'] ),
			empty( $images['candidates'] ) ? '' : sprintf( ' srcset="%s"', esc_attr( self::srcset( $images['candidates'], true ) ) ),
			esc_attr( $this->width ),
			esc_attr( $this->height ),
			esc_attr( $this->get_alt_text() ),
			$this->output['loading'] ? ' loading="lazy"' : ''
		);
		$dark = $this->get_dark_variant();
		if ( $dark ) {
			$dark_images = $dark->get_static_images( $srcset );
			if ( ! is_wp_error( $dark_images ) ) {
				$img = '<picture>' . self::dark_source( empty( $dark_images['candidates'] ) ? esc_url( $dark_images['src'] ) : self::srcset( $dark_images['candidates'], true ) ) . $img . '</picture>';
			}
		}
		return sprintf( '<a target="_blank" href="%1$s">%2$s</a>', esc_url( $this->get_the_map_url() ), $img );
	}

	/**
	 * Returns the static map as data, for callers that build their own markup, such as emails and feeds.
	 *
	 * Uses the same images, and the same output options from the map's arguments, as get_the_static_map_html().
	 * The URLs aren't escaped.
	 *
	 * @since 6.2.0
	 *
	 * @return array|WP_Error {
	 *     Static map, or an error if the map can't be built, such as without an API key.
	 *
	 *     @type string $src         Image URL.
	 *     @type string $srcset      High-density image candidates, or an empty string.
	 *     @type string $dark_src    Image URL of the dark style, or an empty string without a dark mode image.
	 *     @type string $dark_srcset High-density candidates of the dark style, or an empty string.
	 *     @type string $link        URL of the full map.
	 *     @type string $alt         Alternative text.
	 *     @type int    $width       Width in pixels.
	 *     @type int    $height      Height in pixels.
	 *     @type string $attribution The provider's credit as HTML, when the Map Attribution setting puts it under the
	 *                               map, or an empty string when it is drawn on the image.
	 * }
	 */
	public function get_static_map_data() {
		$images = $this->get_static_images( true );
		if ( is_wp_error( $images ) ) {
			return $images;
		}
		$data = array(
			'src'         => (string) $images['src'],
			'srcset'      => self::srcset( $images['candidates'], false ),
			'dark_src'    => '',
			'dark_srcset' => '',
			'link'        => (string) $this->get_the_map_url(),
			'alt'         => $this->get_alt_text(),
			'width'       => (int) $this->width,
			'height'      => (int) $this->height,
			'attribution' => (string) $this->get_attribution_html(),
		);
		$dark = $this->get_dark_variant();
		if ( $dark ) {
			$dark_images = $dark->get_static_images( true );
			if ( ! is_wp_error( $dark_images ) ) {
				$data['dark_src']    = (string) $dark_images['src'];
				$data['dark_srcset'] = self::srcset( $dark_images['candidates'], false );
			}
		}
		return $data;
	}

	/**
	 * Returns image candidates as a srcset value.
	 *
	 * @since 6.2.0
	 *
	 * @param array $candidates Image URLs keyed by pixel density.
	 * @param bool  $escape     Whether to escape the URLs with esc_url(), for HTML.
	 * @return string Image candidates, such as "https://… 1x,https://… 2x", or an empty string.
	 */
	protected static function srcset( $candidates, $escape ) {
		$list = array();
		foreach ( $candidates as $density => $url ) {
			$list[] = sprintf( '%1$s %2$sx', $escape ? esc_url( $url ) : $url, $density );
		}
		return implode( ',', $list );
	}

	/**
	 * Returns the image URL of the static map, and its high-density candidates.
	 *
	 * Providers whose terms allow it are served from the site.
	 *
	 * @since 6.1.0
	 * @since 6.2.0 Returns 'candidates', not escaped, in place of 'srcset'. Follows the map's `srcset` and `durable`
	 *              arguments.
	 *
	 * @param bool $srcset Whether to include the provider's high-density maps.
	 * @return array|WP_Error Array with 'src' and 'candidates' (image URLs keyed by pixel density, or empty), or an
	 *                        error if the map can't be built.
	 */
	protected function get_static_images( $srcset ) {
		$src = $this->get_the_static_map();
		if ( is_wp_error( $src ) ) {
			return $src;
		}
		$cached = Sloc_Map_Cache::is_enabled( $this );
		if ( $cached ) {
			$src = Sloc_Map_Cache::get_url( $this, null, $this->output['durable'] );
		}
		$scales = $this->get_scales();
		if ( ! $srcset || ! $this->output['srcset'] || count( $scales ) < 2 ) {
			return array(
				'src'        => $src,
				'candidates' => array(),
			);
		}
		$candidates = array();
		$scale      = $this->scale;
		foreach ( $scales as $density ) {
			$this->scale = $density;
			$image       = $this->get_the_static_map();
			if ( is_wp_error( $image ) ) {
				$this->scale = $scale;
				return $image;
			}
			if ( $cached ) {
				$image = Sloc_Map_Cache::get_url( $this, null, $this->output['durable'] );
			}
			$candidates[ $density ] = $image;
		}
		$this->scale = $scale;
		return array(
			'src'        => $src,
			'candidates' => $candidates,
		);
	}

	/**
	 * Returns what to show in place of a static map that couldn't be built.
	 *
	 * Site administrators see the reason, such as a missing API key; everyone else sees nothing.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_Error $error Error from get_the_static_map().
	 * @return string Escaped error message, or an empty string.
	 */
	protected function static_map_error( $error ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		return esc_html( $error->get_error_message() );
	}

	/**
	 * Given coordinates echo the output of get_the_map.
	 *
	 * @param boolean $static Return Static or Dynamic Map.
	 * @return echos the output.
	 */
	public function the_map( $static = false ) {
		return $this->get_the_map( $static );
	}
}

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
	 * Image formats the provider can be asked for: 'png' (full color), 'png8' (256 colors), 'jpg', 'webp' or 'gif'.
	 *
	 * Providers without a choice of format leave this empty.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const FORMATS = array();

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
		$this->api      = $r['api'];
		$this->format   = static::sanitize_format( $r['format'] );

		$this->marker       = self::sanitize_marker( $r['marker'] );
		$this->marker_color = self::sanitize_marker_color( $r['marker_color'] );
		$this->marker_size  = self::sanitize_marker_size( $r['marker_size'] );

		$this->scalebar          = self::sanitize_scalebar( $r['scalebar'] );
		$this->scalebar_position = self::sanitize_scalebar_position( $r['scalebar_position'] );

		$this->attribution          = self::sanitize_attribution( $r['attribution'] );
		$this->attribution_position = self::sanitize_attribution_position( $r['attribution_position'] );
		$this->set( $r['latitude'], $r['longitude'], $r['altitude'] );
		$this->fit_size();

		if ( $this->is_active() && method_exists( $this, 'admin_init' ) ) {
			add_action( 'init', array( $this, 'init' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
		}
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
	 *
	 * @param string $name   Vendor name.
	 * @param string $vendor Vendor slug, as in the option name sloc_{vendor}_map_api.
	 */
	protected static function add_map_api_setting( $name, $vendor ) {
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
				'description' => sprintf(
					// translators: Name of the map vendor.
					__( 'Optional. Map images show this key to every visitor, so use a key that only allows static maps and is restricted to this site. Leave empty to use the %s API key.', 'simple-location' ),
					$name
				),
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
			// One provider draws many maps, so each map's description replaces the last one.
			$location       = $args['location'] ?? $args['address'] ?? '';
			$this->location = is_scalar( $location ) ? (string) $location : '';
			$this->fit_size();
		}
		return parent::set( $args, $lng, $alt );
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
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @param int[] $post_ids  Optional. The post of each location, in the same order. Default empty, for no links.
	 * @return string HTML.
	 */
	public function get_archive_map_html( $locations, $post_ids = array() ) {
		$src    = $this->get_archive_map_src( $locations );
		$srcset = '' === $src ? '' : $this->get_archive_srcset( $locations );
		$html   = sprintf(
			'<img class="archive-map sloc-map" src="%1$s"%2$s alt="%3$s" />',
			esc_url( $src ),
			'' === $srcset ? '' : sprintf( ' srcset="%s"', esc_attr( $srcset ) ),
			esc_attr__( 'Map of the locations listed on this page', 'simple-location' )
		);
		if ( '' === $src || empty( $post_ids ) || ! is_array( $locations ) || count( $post_ids ) !== count( $locations ) ) {
			return $html;
		}
		$post_ids = array_values( $post_ids );
		$links    = array();
		foreach ( $this->get_archive_marker_boxes( $locations ) as $index => $box ) {
			if ( ! isset( $post_ids[ $index ] ) ) {
				continue;
			}
			$title   = wp_strip_all_tags( get_the_title( $post_ids[ $index ] ) );
			$links[] = sprintf(
				'<a class="sloc-archive-map-marker" href="%1$s" style="%2$s" title="%3$s" aria-label="%3$s"></a>',
				esc_url( get_permalink( $post_ids[ $index ] ) ),
				esc_attr( sprintf( 'left:%1$s%%;top:%2$s%%;width:%3$s%%;height:%4$s%%', round( $box[0], 3 ), round( $box[1], 3 ), round( $box[2] - $box[0], 3 ), round( $box[3] - $box[1], 3 ) ) ),
				esc_attr( '' === $title ? __( 'Untitled', 'simple-location' ) : $title )
			);
		}
		if ( empty( $links ) ) {
			return $html;
		}
		return '<div class="sloc-archive-map">' . $html . implode( '', $links ) . '</div>';
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
	 *
	 * @param bool $srcset Optional. Add a srcset with the provider's high-density maps. Default false.
	 * @return string HTML.
	 */
	public function get_the_static_map_html( $srcset = false ) {
		$orig = $this->get_the_static_map();
		if ( is_wp_error( $orig ) ) {
			return $this->static_map_error( $orig );
		}
		if ( Sloc_Map_Cache::is_enabled( $this ) ) {
			$orig = Sloc_Map_Cache::get_url( $this );
		}
		$link   = $this->get_the_map_url();
		$height = $this->height;
		$width  = $this->width;

		$scales = $this->get_scales();
		if ( $srcset && count( $scales ) > 1 ) {
			$srcset = array();
			$scale  = $this->scale;
			foreach ( $scales as $density ) {
				$this->scale = $density;
				$image       = $this->get_the_static_map();
				if ( is_wp_error( $image ) ) {
					$this->scale = $scale;
					return $this->static_map_error( $image );
				}
				if ( Sloc_Map_Cache::is_enabled( $this ) ) {
					$image = Sloc_Map_Cache::get_url( $this );
				}
				$srcset[] = sprintf( '%1$s %2$sx', esc_url( $image ), $density );
			}
			$this->scale = $scale;

			$srcset = implode( ',', $srcset );
			return sprintf( '<a target="_blank" href="%1$s"><img class="sloc-map" src="%2$s" srcset="%3$s" width="%4$s" height="%5$s" alt="%6$s" loading="lazy" /></a>', esc_url( $link ), esc_url( $orig ), esc_attr( $srcset ), esc_attr( $width ), esc_attr( $height ), esc_attr( $this->get_alt_text() ) );
		}
		return sprintf( '<a target="_blank" href="%1$s"><img class="sloc-map" src="%2$s" width="%3$s" height="%4$s" alt="%5$s" loading="lazy" /></a>', esc_url( $link ), esc_url( $orig ), esc_attr( $width ), esc_attr( $height ), esc_attr( $this->get_alt_text() ) );
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

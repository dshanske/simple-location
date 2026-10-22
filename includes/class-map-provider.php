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
	 */
	public function __construct( $args = array() ) {
		// The setting's registered default is the content width, but it isn't registered before init.
		$width = get_option( 'sloc_width' );
		if ( ! is_numeric( $width ) || $width < 1 ) {
			$width = Loc_Config::get_default_map_width();
		}
		$defaults = array(
			'width'     => $width,
			'height'    => round( $width / get_option( 'sloc_aspect', ( 16 / 9 ) ) ),
			'map_zoom'  => get_option( 'sloc_zoom' ),
			'api'       => '',
			'latitude'  => null,
			'longitude' => null,
			'altitude'  => null,
			'location'  => '',
			'user'      => '',
			'style'     => '',
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
		return $args;
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

<?php
/**
 * Base Map Provider Class.
 *
 * @package Simple_Location
 */

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
	 * Location Information.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $location;

	/**
	 * Constructor for the Abstract Class.
	 *
	 * The default version of this just sets the parameters.
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
		global $content_width;
		$width = 1024;
		if ( $content_width ) {
			$width = $content_width;
		}
		if ( ! $width || $width > 1 ) {
			$width = get_option( 'sloc_width' );
			if ( ! is_numeric( $width ) ) {
				$width = 1024;
			}
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

		if ( $this->is_active() && method_exists( $this, 'admin_init' ) ) {
			add_action( 'init', array( $this, 'init' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
		}
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
			if ( isset( $args['location'] ) ) {
				$this->location = $args['location'];
			}
		}
		return parent::set( $args, $lng, $alt );
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


	// Return HTML Code for Static Map.
	public function get_the_static_map_html( $srcset = true ) {
		$orig   = $this->get_the_static_map();
		$link   = $this->get_the_map_url();
		$height = $this->height;
		$width  = $this->width;

		if ( $srcset ) {
			$srcset = array();
			foreach ( array( '0.5', '1', '2', '3' ) as $mult ) {
				$this->height = round( $height * floatval( $mult ) );
				$this->width  = round( $width * floatval( $mult ) );
				$image        = $this->get_the_static_map();
				if ( is_wp_error( $image ) ) {
					return $image->get_error_message();
				}
				$srcset[] = sprintf( '%1$s %2$sx', $image, $mult );

			}
			$srcset = implode( ',', $srcset );
			return sprintf( '<a target="_blank" href="%1$s"><img class="sloc-map" src="%2$s" srcset="%3$s" width="%4$s" height="%5$s" loading="lazy" /></a>', $link, $orig, $srcset, $width, $height );
		}
		return sprintf( '<a target="_blank" href="%1$s"><img class="sloc-map" src="%2$s" width="%3$s" height="%4$s" loading="lazy" /></a>', $link, $orig, $width, $height );
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

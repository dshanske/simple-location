<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map Provider using TomTom Map API.
 *
 * @since 1.0.0
 */
class Map_Provider_TomTom extends Map_Provider {
	use Sloc_API_TomTom;

	/**
	 * Constructor.
	 *
	 * @since 4.4.9
	 *
	 * @param array $args Arguments. See Map_Provider::__construct().
	 */
	public function __construct( $args = array() ) {
		$this->name         = __( 'TomTom Maps', 'simple-location' );
		$this->slug         = 'tomtom';
		$this->url          = 'https://developer.tomtom.com/';
		$this->description  = __( 'Offers a freemium option or pay as you go. Sign up for an API Key', 'simple-location' );
		$this->max_height   = 8192;
		$this->max_width    = 8192;
		$this->max_map_zoom = 22;
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_tomtom_api' );
		}
		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_tomtom_style' );
		}
		parent::__construct( $args );
	}

	/**
	 * Returns the map styles the provider offers.
	 *
	 * @since 4.4.9
	 *
	 * @return array Style names keyed by ID.
	 */
	public function get_styles() {
		return array(
			'main'  => __( 'Default', 'simple-location' ),
			'night' => __( 'Night', 'simple-location' ),
		);
	}

	/**
	 * Returns the URL of a static map of the location.
	 *
	 * @since 4.4.9
	 *
	 * @return string URL, or an empty string without the settings it needs.
	 */
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return '';
		}
		$url = 'https://api.tomtom.com/map/1/staticimage';
		$map = add_query_arg(
			array(
				'key'      => $this->api,
				'center'   => sprintf( '%1$s,%2$s', $this->longitude, $this->latitude ),
				'zoom'     => $this->map_zoom,
				'width'    => $this->width,
				'height'   => $this->height,
				'language' => get_bloginfo( 'language' ),
				'layer'    => $this->style,
			),
			$url
		);
		return $map;
	}

	/**
	 * Returns the URL of a static map with multiple locations.
	 *
	 * @since 4.4.9
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return string Always an empty string; TomTom has no map of multiple locations.
	 */
	public function get_archive_map( $locations ) {
		return '';
	}

	/**
	 * Returns the URL of a link to the location on a map.
	 *
	 * @since 4.4.9
	 *
	 * @return string URL.
	 */
	public function get_the_map_url() {
		return sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=%3$s/%1$s/%2$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	/**
	 * Returns the HTML for the map, which is always the static map.
	 *
	 * @since 4.4.9
	 *
	 * @param boolean $static Unused; the map is always static.
	 * @return string HTML marked up map.
	 */
	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html( true );
	}
}

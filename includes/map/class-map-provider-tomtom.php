<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

/**
 * Map Provider using TomTom Map API.
 *
 * @since 1.0.0
 */
class Map_Provider_TomTom extends Map_Provider {
	use Sloc_API_TomTom;

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

	public function get_styles() {
		return array(
			'main'  => __( 'Default', 'simple-location' ),
			'night' => __( 'Night', 'simple-location' ),
		);
	}

	// Return code for map
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

	public function get_archive_map( $locations ) {
		return '';
	}

	public function get_the_map_url() {
		return sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=%3$s/%1$s/%2$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	// Return code for map
	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html();
	}
}

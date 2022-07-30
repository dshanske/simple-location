<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

/**
 * Map Provider using LocationIQ Map API.
 *
 * @since 1.0.0
 */
class Map_Provider_LocationIQ extends Map_Provider {


	public function __construct( $args = array() ) {
		$this->name         = __( 'LocationIQ', 'simple-location' );
		$this->slug         = 'locationiq';
		$this->url = 'https://locationiq.com/';
		$this->description = __( 'LocationIQ offers Geocoding and Static maps, with a free tier of 5000 requests/day. Sign up for an API key', 'simple-location' );
		$this->max_map_zoom = 18;

		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_locationiq_api' );
		}
		$this->style = 'roadmap';

		$option = get_option( 'sloc_map_provider' );
		if ( 'locationiq' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_locationiq_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'Location IQ API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
	}

	public static function admin_init() {
		self::add_settings_parameter( __( 'Location IQ', 'simple-location' ), 'sloc_locationiq_api' );
	}


	public function get_styles() {
		return array();
	}

	// Return code for map
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Location IQ', 'simple-location' ) );
		}
		$map = add_query_arg(
			array(
				'key'     => $this->api,
				'size'    => sprintf( '%1$sx%2$s', $this->width, $this->height ),
				'markers' => sprintf( 'size:small|color:red|%1$s,%2$s', $this->latitude, $this->longitude ),
			),
			'https://maps.locationiq.com/v3/staticmap'
		);
		return $map;
	}

	public function get_archive_map( $locations ) {
		if ( empty( $this->api ) || empty( $locations ) ) {
			return '';
		}

		$markers = array();
		foreach ( $locations as $location ) {
			$markers[] = sprintf( '%1$s,%2$s', $location[0], $location[1] );
		}
		// $polyline = Sloc_Polyline::encode( $locations );

		$map = add_query_arg(
			array(
				'key'     => $this->api,
				'format'  => 'png',
				'size'    => sprintf( '%1$sx%2$s', $this->width, $this->height ),
				'markers' => sprintf( 'icon:small-red-blank|size:small|color:red|%1$s', implode( '|', $markers ) ),
				'path'    => sprintf( 'weight:2|color:blue%1$s', implode( '|', $markers ) ),
			),
			'https://maps.locationiq.com/v2/staticmap'
		);
		return $map;
	}

	public function get_the_map_url() {
		return '';
	}

	// Return code for map
	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html();
	}

}

register_sloc_provider( new Map_Provider_LocationIQ() );


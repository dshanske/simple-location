<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

/**
 * Reverse Geolocation using OpenRoute Service API.
 *
 * @since 1.0.0
 */
class Geo_Provider_OpenRoute extends Geo_Provider_Pelias {

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
	 *  @type string $address Formatted Address String
	 *  @type int $reverse_zoom Reverse Zoom. Default 18.
	 *  @type string $user User name.
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'OpenRoute', 'simple-location' );
		$this->slug = 'openroute';
		$this->url = 'https://openrouteservice.org/plans/';
		$this->description = __( 'OpenRouteService is free for everyone, but you still need to sign up for an API key.', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_openroute_api' );
		}

		$option = get_option( 'sloc_geo_provider' );
		if ( 'openroute' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		Geo_Provider::__construct( $args );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function init() {
		register_setting(
			'sloc_providers', // Option group.
			'sloc_openroute_api', // Option name.
			array(
				'type'         => 'string',
				'description'  => 'OpenRoute Service API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
	}

	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'OpenRoute', 'simple-location' ), 'sloc_openroute_api' );
	}

	/**
	 * Returns elevation.
	 *
	 * @return float $elevation Elevation.
	 *
	 * @since 1.0.0
	 */
	public function elevation() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for OpenRoute', 'simple-location' ) );
		}
		$args = array(
			'api_key'    => $this->api,
			'format_out' => 'point',
			'geometry'   => $this->longitude . ',' . $this->latitude,
		);

		$json = $this->fetch_json( 'https://api.openrouteservice.org/elevation/point', $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( array_key_exists( 'geometry', $json ) ) {
			return 0;
		}
		return $json['geometry'][2];
	}

	/**
	 * Return an address.
	 *
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for OpenRoute', 'simple-location' ) );
		}
		$args = array(
			'api_key'   => $this->api,
			'point.lat' => $this->latitude,
			'point.lon' => $this->longitude,
		// 'size'      => 1,
		);

		$json = $this->fetch_json( 'https://api.openrouteservice.org/geocode/reverse', $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		return $this->address_to_mf( $json );
	}

	/**
	 * Geocode address.
	 *
	 * @param  string $address String representation of location.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function geocode( $address ) {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for OpenRoute', 'simple-location' ) );
		}
		$args = array(
			'api_key' => $this->api,
			'text'    => $address,
		);

		$json = $this->fetch_json( 'https://api.openrouteservice.org/geocode/search', $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		if ( wp_is_numeric_array( $json ) ) {
			$json = $json[0];
		}
		$address             = $json['features'][0];
		$return              = $this->address_to_mf( $json );
		$return['latitude']  = $address['geometry']['coordinates'][1];
		$return['longitude'] = $address['geometry']['coordinates'][0];
		return array_filter( $return );
	}
}

register_sloc_provider( new Geo_Provider_OpenRoute() );

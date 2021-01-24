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
class Geo_Provider_OpenRoute extends Geo_Provider {

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
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_openroute_api' );
		}

		$option = get_option( 'sloc_geo_provider' );
		if ( 'openroute' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		parent::__construct( $args );
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
		add_settings_field(
			'openroute_api', // ID.
			__( 'OpenRoute API Key', 'simple-location' ), // Setting title.
			array( 'Loc_Config', 'string_callback' ), // Display callback.
			'sloc_providers', // Settings page.
			'sloc_api', // Settings section.
			array(
				'label_for' => 'sloc_openroute_api',
			)
		);
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
			'format'    => 'json',
			'point.lat' => $this->latitude,
			'point.lon' => $this->longitude,
			'size'      => 1,
		);

		$json = $this->fetch_json( 'https://api.openrouteservice.org/geocode/reverse', $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$address = $json['features'][0];
		return $this->address_to_mf( $address );
	}

	/**
	 * Convert address properties to mf2
	 *
	 * @param  array $address Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	private function address_to_mf( $address ) {
		$address = $address['properties'];
		$addr    = array(
			'name'             => self::ifnot(
				$address,
				array(
					'label',
				)
			),
			'street-address'   => $street,
			'extended-address' => self::ifnot(
				$address,
				array(
					'borough',
					'neighbourhood',
					'suburb',
				)
			),
			'locality'         => self::ifnot(
				$address,
				array(
					'locality',
				)
			),
			'region'           => self::ifnot(
				$address,
				array(
					'region',
				)
			),
			'country-name'     => self::ifnot(
				$address,
				array(
					'country',
				)
			),
			'postal-code'      => self::ifnot(
				$address,
				array(
					'postalcode',
				)
			),

			'latitude'         => $this->latitude,
			'longitude'        => $this->longitude,
			'raw'              => $address,
		);

		$addr                 = array_filter( $addr );
		$addr['display-name'] = $this->label;
		$tz                   = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		return $addr;
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
		$return              = $this->address_to_mf( $address );
		$return['latitude']  = $address['geometry']['coordinates'][1];
		$return['longitude'] = $address['geometry']['coordinates'][0];
		return array_filter( $return );
	}
}

register_sloc_provider( new Geo_Provider_OpenRoute() );

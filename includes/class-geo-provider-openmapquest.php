<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

/**
 * Reverse Geolocation using MapQuest Open APIs.
 *
 * @since 1.0.0
 */
class Geo_Provider_OpenMapquest extends Geo_Provider_Nominatim {


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
		$this->name = __( 'Open Search(Nominatim) via Mapquest', 'simple-location' );
		$this->slug = 'openmapquest';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_mapquest_api' );
		}

		$option = get_option( 'sloc_geo_provider' );
		if ( 'mapquest' === $option ) {
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
			'sloc_mapquest_api', // Option name.
			array(
				'type'         => 'string',
				'description'  => 'Mapquest API Key',
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
			'mapquestapi', // ID.
			__( 'MapQuest API Key', 'simple-location' ), // Setting title.
			array( 'Loc_Config', 'string_callback' ), // Display callback.
			'sloc_providers', // Settings page.
			'sloc_api', // Settings section.
			array(
				'label_for' => 'sloc_mapquest_api',
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
			return null;
		}
		$args = array(
			'latLngCollection' => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
			'key'              => $this->api,
		);
		$url  = 'https://open.mapquestapi.com/elevation/v1/profile';

		$json = $this->fetch_json( $url, $args );

		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( isset( $json['error_message'] ) ) {
				return new WP_Error( $json['status'], $json['error_message'] );
		}
		if ( ! isset( $json['elevationProfile'] ) ) {
			return null;
		}
		return round( $json['elevationProfile'][0]['height'], 2 );
	}

	/**
	 * Return an address.
	 *
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API Key for Mapquest', 'simple-location' ) );
		}
		$args = array(
			'format'          => 'json',
			'extratags'       => '1',
			'addressdetails'  => '1',
			'lat'             => $this->latitude,
			'lon'             => $this->longitude,
			'zoom'            => $this->reverse_zoom,
			'accept-language' => get_bloginfo( 'language' ),
			'key'             => $this->api,
		);
		$url  = 'https://open.mapquestapi.com/nominatim/v1/reverse.php';

		$json = $this->fetch_json( $url, $args );
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
			return new WP_Error( 'missing_api_key', __( 'You have not set an API Key for Mapquest', 'simple-location' ) );
		}
		$args = array(
			'q'               => $address,
			'format'          => 'json',
			'extratags'       => '1',
			'addressdetails'  => '1',
			'accept-language' => get_bloginfo( 'language' ),
			'key'             => $this->api,
		);
		$url  = 'https://open.mapquestapi.com/nominatim/v1/search.php';

		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( wp_is_numeric_array( $json ) ) {
			$json = $json[0];
		}
		$address             = $json['address'];
		$return              = $this->address_to_mf( $address );
		$return['latitude']  = ifset( $json['lat'] );
		$return['longitude'] = ifset( $json['lon'] );
		if ( isset( $json['extratags'] ) ) {
			$return['url']   = ifset( $json['extratags']['website'] );
			$return['photo'] = ifset( $json['extratags']['image'] );
		}

		return array_filter( $return );
	}


}

register_sloc_provider( new Geo_Provider_OpenMapquest() );

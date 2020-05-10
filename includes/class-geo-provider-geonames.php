<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

/**
 * Reverse Geolocation using Bing API.
 *
 * @since 1.0.0
 */
class Geo_Provider_Geonames extends Geo_Provider {

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
		$this->name = __( 'Geonames', 'simple-location' );
		$this->slug = 'geonames';
		if ( ! isset( $args['user'] ) ) {
			$args['user'] = get_option( 'sloc_geonames_user' );
		}

		$option = get_option( 'sloc_geo_provider' );
		if ( 'geonames' === $option ) {
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
			'sloc_providers',
			'sloc_geonames_user',
			array(
				'type'         => 'string',
				'description'  => 'Geonames User',
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
			'geonamesuser', // ID.
			__( 'Geonames User', 'simple-location' ),
			array( 'Loc_Config', 'string_callback' ),
			'sloc_providers',
			'sloc_api',
			array(
				'label_for' => 'sloc_geonames_user',
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
		if ( ! $this->user ) {
			return null;
		}
		$args = array(
			'username' => $this->user,
			'lat'      => $this->latitude,
			'lng'      => $this->longitude,
		);
		$url  = 'http://api.geonames.org/srtm1';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( array_key_exists( 'srtm1', $json ) ) {
			return round( $json['srtm1'], 2 );
		}
		return null;
	}

	/**
	 * Return an address.
	 *
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function reverse_lookup() {
		if ( ! $this->user ) {
			return null;
		}
		$args = array(
			'username' => $this->user,
			'lat'      => $this->latitude,
			'lng'      => $this->longitude,
		);
		$url  = 'https://secure.geonames.org/findNearbyPlaceNameJSON';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$json              = $json['geonames'][0];
		$addr              = $this->address_to_mf2( $json );
		$addr['latitude']  = $this->latitude;
		$addr['longitude'] = $this->longitude;
		return $addr;
	}

	/**
	 * Convert address properties to mf2
	 *
	 * @param  array $json Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	private function address_to_mf2( $json ) {
		$addr                   = array();
		$addr['street-address'] = ifset( $json['toponymName'] );
		$addr['locality']       = ifset( $json['adminName1'] );
		// $addr['region']         = ifset( $json[] );
		$addr['country-name'] = ifset( $json['countryName'] );
		$display              = array();
		foreach ( array( 'street-address', 'locality', 'country-name' ) as $prop ) {
			$display[] = ifset( $addr[ $prop ] );
		}
		$addr['display-name'] = implode( ', ', $display );
		$tz                   = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		if ( WP_DEBUG ) {
			$addr['raw'] = $json;
		}
		return array_filter( $addr );
	}

	/**
	 * Geocode address.
	 *
	 * @param  string $address String representation of location.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function geocode( $address ) {
		if ( ! $this->user ) {
			return null;
		}
		$args = array(
			'username' => $this->user,
			'q'        => $address,
			'type'     => 'json',
			'lang'     => get_bloginfo( 'language' ),
			'style'    => 'FULL',
		);
		$url  = 'https://secure.geonames.org/search';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$json                = $json['geonames'][0];
		$return              = $this->address_to_mf2( $json );
		$return['latitude']  = ifset( $json['lat'] );
		$return['longitude'] = ifset( $json['lng'] );
		$return['altitude']  = ifset( $json['elevation'] );
		return array_filter( $return );
	}
}

register_sloc_provider( new Geo_Provider_Geonames() );

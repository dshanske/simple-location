<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

/**
 * Reverse Geolocation using GeoNames API.
 *
 * @since 1.0.0
 */
class Geo_Provider_Geonames extends Geo_Provider {
	use Sloc_API_Geonames;

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
		$this->url  = 'The Geonames database is available under a creative commons license. A free user account is required.';
		if ( ! isset( $args['user'] ) ) {
			$args['user'] = get_option( 'sloc_geonames_user' );
		}

		parent::__construct( $args );
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
		$url  = 'http://api.geonames.org/extendedFindNearbyJSON';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

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
		$addr = array();
		if ( WP_DEBUG ) {
			$addr['raw'] = $json;
		}
		if ( array_key_exists( 'address', $json ) ) {
			$json = $json['address'];
		} elseif ( array_key_exists( 'geonames', $json ) ) {
			$json = $json['geonames'];
			$json = end( $json );
		}
		$addr['country-code'] = ifset( $json['countryCode'] );
		$addr['country-name'] = isset( $json['countryName'] ) ? $json['countryName'] : self::country_name( $addr['country-code'] );

		$addr['locality'] = self::ifnot(
			$json,
			array(
				'adminName2',
				'toponymName',
			)
		);
		$addr['region']   = ifset( $json['adminName1'] );
		if ( array_key_exists( 'adminCodes1', $json ) ) {
			$addr['region-code'] = $json['adminCodes1']['ISO3166_2'];
		} elseif ( array_key_exists( 'adminCode1', $json ) ) {
			$addr['region-code'] = $json['adminCode1'];
		} else {
			$addr['region-code'] = self::region_code( $addr['region'], $addr['country-code'] );
		}
		$addr['postal-code'] = ifset( $json['postalcode'] );

		$number = ifset( $json['streetNumber'] );
		$street = ifset( $json['street'] );

		// Adjust position of house number/name based on country practice.
		if ( self::house_number( $country_code ) ) {
			$street_address = $number . ' ' . $street;
		} else {
			$street_address = $street . ' ' . $number;
		}

		$addr['street-address'] = trim( $street_address );
		$addr['street']         = $street;
		$addr['street-number']  = $number;

		$addr                 = array_filter( $addr );
		$addr['display-name'] = self::display_name( $addr );
		$tz                   = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
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
			return new WP_Error( 'no_username_set', __( 'No GeoNames Username Set', 'simple-location' ) );
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

		if ( 1 === count( $json ) && array_key_exists( 'status', $json ) ) {
			return new WP_Error( 'unknown_error', $json['status']['message'] );
		}

		$json                = $json['geonames'][0];
		$return              = $this->address_to_mf2( $json );
		$return['latitude']  = ifset( $json['lat'] );
		$return['longitude'] = ifset( $json['lng'] );
		$return['altitude']  = ifset( $json['elevation'] );
		return array_filter( $return );
	}
}

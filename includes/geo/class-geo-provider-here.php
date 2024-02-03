<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

/**
 * Reverse Geolocation using HERE API.
 *
 * @since 1.0.0
 */
class Geo_Provider_Here extends Geo_Provider {

	use Sloc_API_Here;

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
		$this->name        = __( 'HERE', 'simple-location' );
		$this->slug        = 'here';
		$this->url         = 'https://platform.here.com/';
		$this->description = __( 'HERE offers a limited plan for up to 1,000 free transactions per month', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_here_api' );
		}

		parent::__construct( $args );
	}

	/**
	 * Return an address.
	 *
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Bing', 'simple-location' ) );
		}
		$args = array(
			'apiKey' => $this->api,
			'at'     => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
			'lang'   => get_bloginfo( 'language' ),
		);
		$url  = 'https://revgeocode.search.hereapi.com/v1/revgeocode';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		if ( ! isset( $json['items'] ) || empty( $json['items'] ) ) {
			return new WP_Error( 'invalid_response', __( 'No results', 'simple-location' ) );
		}
		$json              = $json['items'][0];
		$addr              = $this->address_to_mf2( $json );
		$addr['latitude']  = $this->latitude;
		$addr['longitude'] = $this->longitude;
		return array_filter( $addr );
	}

	/**
	 * Convert address properties to mf2
	 *
	 * @param  array $json Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	private function address_to_mf2( $json ) {
		$addr = array();
		if ( array_key_exists( 'address', $json ) ) {
			$location              = $json['address'];
			$number                = self::ifnot(
				$location,
				array(
					'houseNumber',
				)
			);
			$street               = self::ifnot(
				$location,
				array(
					'street',
				)
			);
			$addr['street']        = $street;
			$addr['street_number'] = $number;

			$addr['name']             = ifset( $json['title'] );
			$addr['street-address']   = $street;
			$addr['extended-address'] = self::ifnot(
				$location,
				array(
					'district',
				)
			);
			$addr['country-name']     = ifset( $location['countryName'] );
			$addr['country-code']     = self::country_code_iso3( ifset( $location['countryCode'] ) );
			$addr['locality']         = ifset( $location['city'] );
			$addr['region']           = self::ifnot(
				$location,
				array(
					'state',
					'county',
				)
			);
			$addr['region-code']      = self::ifnot(
				$location,
				array(
					'stateCode',
					'countyCode',
				)
			);
			if ( empty( $addr['region-code'] ) ) {
				$addr['region-code'] = self::region_code( $addr['region'], $addr['country-code'] );
			}
			$addr['postal-code'] = ifset( $location['postalCode'] );

			// Adjust position of house number/name based on country practice.
			if ( self::house_number( $addr['country_code'] ) ) {
				$addr['street-address'] = $number . ' ' . $street;
			} else {
				$addr['street-address'] = $street . ' ' . $number;
			}
		}

		if ( ! array_key_exists( 'display-name', $addr ) ) {
			$addr['display-name'] = $this->display_name( $addr );
		}

		$tz = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		if ( WP_DEBUG ) {
			$addr['raw'] = $json;
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
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Bing', 'simple-location' ) );
		}
		$args = array(
			'apiKey' => $this->api,
			'q'      => $address,
			'lang'   => get_bloginfo( 'language' ),

		);
		$url  = 'https://revgeocode.search.hereapi.com/v1/geocode';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( ! isset( $json['items'] ) || empty( $json['items'] ) ) {
			return new WP_Error( 'invalid_response', __( 'No results', 'simple-location' ) );
		}
		$json   = $json['items'][0];
		$return = $this->address_to_mf2( $json );
		if ( isset( $json['position'] ) ) {
			$return['latitude']  = $json['position']['lat'];
			$return['longitude'] = $json['position']['lng'];
		}
		return $return;
	}
}

<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reverse Geolocation using GeoApify Service API.
 *
 * @since 1.0.0
 */
class Geo_Provider_GeoApify extends Geo_Provider_Pelias {
	use Sloc_API_GeoApify;

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
		$this->name        = __( 'GeoApify', 'simple-location' );
		$this->slug        = 'geoapify';
		$this->url         = 'https://www.geoapify.com/';
		$this->description = __( 'GeoApify offers Maps and Geocoding APIs with a free tier of 3000 credits for requests per day. API Key required.', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_geoapify_api' );
		}

		Geo_Provider::__construct( $args );
	}

	/**
	 * Return an address.
	 *
	 * @return array|WP_Error $reverse microformats2 address elements in an array.
	 */
	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for GeoApify', 'simple-location' ) );
		}

		$args = array(
			'apiKey' => $this->api,
			'lat'    => $this->latitude,
			'lon'    => $this->longitude,
			'type'   => 'building',
		);
		$url  = 'https://api.geoapify.com/v1/geocode/reverse/';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( empty( $json['features'][0]['properties'] ) ) {
			return new WP_Error( 'invalid_response', __( 'No results', 'simple-location' ) );
		}
		return $this->address_to_mf( $json );
	}

	/**
	 * Convert a Geoapify result to mf2.
	 *
	 * Geoapify returns GeoJSON like Pelias, but with its own property names, and sorts results by relevance.
	 *
	 * @since 6.0.0
	 *
	 * @param  array $json Raw JSON, a GeoJSON feature collection.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	protected function address_to_mf( $json ) {
		$address = $json['features'][0]['properties'];

		$number       = self::ifnot( $address, array( 'housenumber' ) );
		$street       = self::ifnot( $address, array( 'street' ) );
		$country_code = strtoupper( (string) ( $address['country_code'] ?? '' ) );

		// Adjust position of house number/name based on country practice.
		if ( self::house_number( $country_code ) ) {
			$street_address = $number . ' ' . $street;
		} else {
			$street_address = $street . ' ' . $number;
		}

		$addr = array(
			'name'             => $address['name'] ?? null,
			'street-address'   => trim( $street_address ),
			'street'           => $street,
			'street-number'    => $number,
			'extended-address' => self::ifnot(
				$address,
				array(
					'suburb',
					'district',
					'quarter',
				)
			),
			'locality'         => self::ifnot(
				$address,
				array(
					'city',
					'town',
					'village',
					'hamlet',
				)
			),
			'region'           => self::ifnot(
				$address,
				array(
					'state',
					'county',
				)
			),
			'region-code'      => self::ifnot(
				$address,
				array(
					'state_code',
					'county_code',
				)
			),
			'country-name'     => $address['country'] ?? null,
			'country-code'     => $country_code,
			'postal-code'      => $address['postcode'] ?? null,
			'latitude'         => $this->latitude,
			'longitude'        => $this->longitude,
		);

		if ( WP_DEBUG ) {
			$addr['raw'] = $json;
		}

		$addr = array_filter( $addr );
		if ( ! array_key_exists( 'country-code', $addr ) && array_key_exists( 'country-name', $addr ) ) {
			$addr['country-code'] = self::country_code( $addr['country-name'] );
		}
		if ( ! array_key_exists( 'region-code', $addr ) && array_key_exists( 'region', $addr ) && array_key_exists( 'country-code', $addr ) ) {
			$addr['region-code'] = self::region_code( $addr['region'], $addr['country-code'] );
		}
		$addr['display-name'] = $this->display_name( $addr );
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
	 * @return array|WP_Error $reverse microformats2 address elements in an array.
	 */
	public function geocode( $address ) {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for GeoApify', 'simple-location' ) );
		}

		$args = array(
			'text'   => $address,
			'apiKey' => $this->api,
		);

		$url = 'https://api.geoapify.com/v1/geocode/search';

		$json = $this->fetch_json( $url, $args );

		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( empty( $json['features'][0]['geometry']['coordinates'] ) ) {
			return new WP_Error( 'invalid_response', __( 'No results', 'simple-location' ) );
		}

		$coordinates         = $json['features'][0]['geometry']['coordinates'];
		$return              = $this->address_to_mf( $json );
		$return['latitude']  = $coordinates[1];
		$return['longitude'] = $coordinates[0];
		return array_filter( $return );
	}
}

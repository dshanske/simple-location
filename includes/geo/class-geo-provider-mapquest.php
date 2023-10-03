<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

/**
 * Reverse Geolocation using MapQuest API.
 *
 * @since 1.0.0
 */
class Geo_Provider_Mapquest extends Geo_Provider {
	use Sloc_API_Mapquest;

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
		$this->name        = __( 'Mapquest', 'simple-location' );
		$this->slug        = 'mapquest';
		$this->url         = 'https://developer.mapquest.com/';
		$this->description = __( 'Yes, MapQuest still exists. It offers Geocoding and a Static Map API. Offers a free tier with 15,000 transactions per month. Sign up for an API key', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_mapquest_api' );
		}

		Geo_Provider::__construct( $args );
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
			'includeRoadMetadata' => 'true',
			'location'            => $this->latitude . ',' . $this->longitude,
			'zoom'                => $this->reverse_zoom,
			'key'                 => $this->api,
		);
		$url  = 'http://www.mapquestapi.com/geocoding/v1/reverse';

		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		if ( ! array_key_exists( 'results', $json ) ) {
			return new WP_Error( 'no_results', __( 'No Results Found', 'simple-location' ) );
		}

		return self::address_to_mf( $json );
	}

	/**
	 * Convert address properties to mf2
	 *
	 * @param  array $json Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	protected function address_to_mf( $json ) {
		$json = $json['results'][0];

		if ( ! array_key_exists( 'locations', $json ) ) {
			return new WP_Error( 'no_locations', __( 'No Locations Found', 'simple-location' ) );
		}

		$location = $json['locations'];

		if ( ! empty( $location ) ) {
			$location = $location[0];
		}

		$return = array(
			'street-address'   => ifset( $location['street'] ),
			'extended-address' => ifset( $location['adminArea6'] ),
			'locality'         => ifset( $location['adminArea5'] ),
			'postal-code'      => ifset( $location['postalCode'] ),
			'country-name'     => self::country_name( ifset( $location['adminArea1'] ) ),
			'country-code'     => ifset( $location['adminArea1'] ),
		);

		if ( 'US' === $return['country-code'] || 'CA' === $return['country-code'] ) {
			$return['region-code'] = self::ifnot(
				$location,
				array(
					'adminArea3',
					'adminArea4',
				)
			);
			$return['region']      = self::region_name( $return['region-code'], $return['country-code'] );
		} else {
			$return['region']      = self::ifnot(
				$location,
				array(
					'adminArea4',
					'adminArea3',
				)
			);
			$return['region-code'] = self::region_code( $return['region'], $return['country-code'] );
		}

		if ( array_key_exists( 'latLng', $location ) && ! array_key_exists( 'latitude', $return ) ) {
			$return['latitude']  = ifset( $location['latLng']['lat'] );
			$return['longitude'] = ifset( $location['latLng']['lng'] );
		}

		if ( WP_DEBUG ) {
			$return['raw'] = $json;
		}

		if ( ! array_key_exists( 'display-name', $return ) ) {
			$return['display-name'] = $this->display_name( $return );
		}

		return array_filter( $return );
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
			'location' => $address,
			'key'      => $this->api,
		);
		$url  = 'http://www.mapquestapi.com/geocoding/v1/address';

		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$return = $this->address_to_mf( $json );
		if ( array_key_exists( 'latLng', $json ) ) {
			$return['latitude']  = ifset( $json['latLng']['lat'] );
			$return['longitude'] = ifset( $json['latLng']['lng'] );
		}

		return array_filter( $return );
	}
}

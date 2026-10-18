<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

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
		$url  = 'https://www.mapquestapi.com/geocoding/v1/reverse';

		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		return self::address_to_mf( $json );
	}

	/**
	 * Convert address properties to mf2
	 *
	 * @since 6.0.0 MapQuest's error status, and a response without locations, return an error instead of warnings.
	 *
	 * @param  array $json Raw JSON.
	 * @return array|WP_Error $reverse microformats2 address elements in an array, or an error.
	 */
	protected function address_to_mf( $json ) {
		// MapQuest reports errors, such as a bad key, with HTTP 200 and a status code other than 0.
		$status = $json['info']['statuscode'] ?? 0;
		if ( 0 !== (int) $status ) {
			$messages = $json['info']['messages'] ?? array();
			$message  = ( is_array( $messages ) && isset( $messages[0] ) && is_string( $messages[0] ) ) ? $messages[0] : __( 'MapQuest returned an error.', 'simple-location' );
			return new WP_Error( 'mapquest_error', $message, array( 'status' => (int) $status ) );
		}

		$location = $json['results'][0]['locations'][0] ?? null;
		if ( ! is_array( $location ) ) {
			return new WP_Error( 'no_results', __( 'No Results Found', 'simple-location' ), array( 'status' => 404 ) );
		}

		$return = array(
			'street-address'   => $location['street'] ?? null,
			'extended-address' => $location['adminArea6'] ?? null,
			'locality'         => $location['adminArea5'] ?? null,
			'postal-code'      => $location['postalCode'] ?? null,
			'country-name'     => self::country_name( $location['adminArea1'] ?? '' ),
			'country-code'     => $location['adminArea1'] ?? null,
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
			$return['latitude']  = $location['latLng']['lat'] ?? null;
			$return['longitude'] = $location['latLng']['lng'] ?? null;
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
		$url  = 'https://www.mapquestapi.com/geocoding/v1/address';

		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$return = $this->address_to_mf( $json );
		if ( is_wp_error( $return ) ) {
			return $return;
		}
		if ( array_key_exists( 'latLng', $json ) ) {
			$return['latitude']  = $json['latLng']['lat'] ?? null;
			$return['longitude'] = $json['latLng']['lng'] ?? null;
		}

		return array_filter( $return );
	}
}

<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

/**
 * Reverse Geolocation using Google API.
 *
 * @since 1.0.0
 */
class Geo_Provider_Google extends Geo_Provider {
	use Sloc_API_Google;

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
		$this->name        = __( 'Google', 'simple-location' );
		$this->url         = 'https://developers.google.com/maps/';
		$this->description = __( 'Google Maps Platform API key is required, however Google offers a $200 per month credit, which is the equivalent of 28,000 queries. Click Get Started. Make sure to enable the Geocoding API. Follow the tutorial', 'simple-location' );
		$this->slug        = 'google';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_google_api' );
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
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Google', 'simple-location' ) );
		}
		$args = array(
			'latlng'        => $this->latitude . ',' . $this->longitude,
			'language'      => get_bloginfo( 'language' ),
			'location_type' => 'ROOFTOP|RANGE_INTERPOLATED',
			'key'           => $this->api,
		);
		$url  = 'https://maps.googleapis.com/maps/api/geocode/json?';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		if ( array_key_exists( 'status', $json ) && 'OK' !== $json['status'] ) {
			return new WP_Error( $json['status'], ifset( $json['errormessage'], __( 'Error Returning Results from Google', 'simple-location' ) ) );
		}

		$addr              = $this->address_to_mf2( $json );
		$addr['latitude']  = $this->latitude;
		$addr['longitude'] = $this->longitude;
		return $addr;
	}

	/**
	 * Convert address properties to mf2
	 *
	 * @param  array $data Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	private function address_to_mf2( $data ) {
		$addr = array();
		if ( WP_DEBUG ) {
			$addr['raw'] = $data;
		}

		$addr['display-name'] = ifset( $data['formatted_address'] );
		$addr['plus-code']    = ifset( $data['plus_code']['global_code'] );
		$result               = ifset( $data['results'][0] );
		if ( isset( $result['address_components'] ) ) {
			foreach ( $result['address_components'] as $component ) {
				if ( in_array( 'administrative_area_level_1', $component['types'], true ) ) {
					$addr['region'] = $component['long_name'];
					if ( $component['short_name'] !== $component['long_name'] ) {
						$addr['region-code'] = $component['short_name'];
					}
				}
				if ( in_array( 'country', $component['types'], true ) ) {
					$addr['country-name'] = $component['long_name'];
					$addr['country-code'] = $component['short_name'];
				}
				if ( in_array( 'neighborhood', $component['types'], true ) ) {
					$addr['extended-address'] = $component['long_name'];
				}
				if ( in_array( 'locality', $component['types'], true ) ) {
					$addr['locality'] = $component['long_name'];
				}
				if ( in_array( 'street_number', $component['types'], true ) ) {
					$number = $component['short_name'];
				}
				if ( in_array( 'route', $component['types'], true ) ) {
					$street = $component['short_name'];
				}
				if ( in_array( 'postal_code', $component['types'], true ) ) {
					$addr['postal-code'] = $component['long_name'];
				}
			}
		}

		// Adjust position of house number/name based on country practice.
		if ( self::house_number( $addr['country-code'] ) ) {
			$street_address = $number . ' ' . $street;
		} else {
			$street_address = $street . ' ' . $number;
		}
		$addr['street-address'] = trim( $street_address );
		$addr['street']         = $street;
		$addr['street_number']  = $number;

		$tz = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		$addr = array_filter( $addr );
		if ( ! array_key_exists( 'region-code', $addr ) && array_key_exists( 'region', $addr ) ) {
			$addr['region-code'] = self::region_code( $addr['region'], $addr['country-code'] );
		}
		if ( ! array_key_exists( 'display-name', $addr ) ) {
			$addr['display-name'] = $this->display_name( $addr );
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
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Google', 'simple-location' ) );
		}
		$args = array(
			'key'     => $this->api,
			'address' => $address,

		);
		$url  = 'https://maps.googleapis.com/maps/api/geocode/json';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( wp_is_numeric_array( $json['results'] ) ) {
			$json = $json['results'][0];
		}
		$return = $this->address_to_mf2( $json );
		if ( isset( $json['geometry'] ) ) {
			$return['latitude']  = $json['geometry']['location']['lat'];
			$return['longitude'] = $json['geometry']['location']['lng'];
		}
		return $return;
	}
}

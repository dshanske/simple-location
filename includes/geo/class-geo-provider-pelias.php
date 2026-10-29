<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reverse Geolocation using Pelias API. https://github.com/pelias/documentation/blob/master/reverse.md#reverse-geocoding
 *
 * @since 1.0.0
 */
class Geo_Provider_Pelias extends Geo_Provider {

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
		$this->name        = __( 'Pelias', 'simple-location' );
		$this->slug        = 'pelias';
		$this->url         = 'https://pelias.io/';
		$this->description = __( 'Pelias is an open source geocoder. To use this, a Pelias server is required', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_pelias_api' );
		}

		parent::__construct( $args );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 * @since 6.2.0 The key and URL are registered once, on the API settings page.
	 */
	public static function init() {
		self::register_settings_api( __( 'Pelias Service API Key', 'simple-location' ), 'sloc_pelias_api' );
		self::register_settings_api( __( 'Pelias Service URL', 'simple-location' ), 'sloc_pelias_url' );
	}

	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 4.0.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'Pelias', 'simple-location' ), 'sloc_pelias_api' );
		self::add_settings_url_parameter( __( 'Pelias', 'simple-location' ), 'sloc_pelias_url' );
	}

	/**
	 * Return an address.
	 *
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for this Pelias instance', 'simple-location' ) );
		}
		if ( ! wp_http_validate_url( get_option( 'sloc_pelias_url' ) ) ) {
			return new WP_Error( 'missing_pelias_url', __( 'You did not provide a Pelias URL', 'simple-location' ) );
		}

		$args = array(
			'api_key'   => $this->api,
			'point.lat' => $this->latitude,
			'point.lon' => $this->longitude,
			'size'      => 1,
		);
		$url  = trailingslashit( get_option( 'sloc_pelias_url' ) ) . 'reverse/';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		return $this->address_to_mf( $json );
	}

	/**
	 * Returns the error in a Pelias response, if it has no results.
	 *
	 * Pelias lists problems, such as a bad request, in `geocoding.errors`, and a search that finds nothing has no
	 * features.
	 *
	 * @since 6.0.0
	 *
	 * @param array $json Response.
	 * @return WP_Error|null Error, or null if the response has results.
	 */
	protected static function response_error( $json ) {
		$errors = $json['geocoding']['errors'] ?? array();
		if ( is_array( $errors ) && ! empty( $errors ) ) {
			return new WP_Error( 'pelias_error', implode( ' ', array_filter( $errors, 'is_string' ) ), array( 'status' => 400 ) );
		}
		if ( ! isset( $json['features'][0]['properties'] ) || ! is_array( $json['features'][0]['properties'] ) ) {
			return new WP_Error( 'no_results', __( 'No Results Found', 'simple-location' ), array( 'status' => 404 ) );
		}
		return null;
	}

	/**
	 * Convert address properties to mf2
	 *
	 * @since 6.0.0 Street-address results have no name instead of an undefined one, and the closer of two equal results is used.
	 * @since 6.0.0 A response without results returns an error instead of warnings.
	 *
	 * @param  array $json Raw JSON.
	 * @return array|WP_Error $reverse microformats2 address elements in an array, or an error.
	 */
	protected function address_to_mf( $json ) {
		$error = self::response_error( $json );
		if ( $error ) {
			return $error;
		}
		$json    = $json['features'];
		$address = $json[0]['properties'];

		// Give the result with the highest confidence level. At equal confidence levels prioritize venues over addresses.
		foreach ( $json as $feature ) {
			if ( $feature['properties']['confidence'] < $address['confidence'] ) {
				break;
			} elseif ( $feature['properties']['confidence'] > $address['confidence'] ) {
				$address = $feature['properties'];
				continue;
			}
			// Prioritize venue over address.
			if ( 'venue' === $feature['properties']['layer'] && 'venue' !== $address['layer'] ) {
				$address = $feature['properties'];
				continue;
			}
			// At equal confidence and priority, prefer the closer result.
			if ( ( 'venue' === $feature['properties']['layer'] ) === ( 'venue' === $address['layer'] ) && ( $feature['properties']['distance'] ?? INF ) < ( $address['distance'] ?? INF ) ) {
				$address = $feature['properties'];
			}
		}

		$number = self::ifnot(
			$address,
			array(
				'housenumber',
			)
		);

		$street = self::ifnot(
			$address,
			array(
				'street',
			)
		);

		$country_code = $address['country_a'] ?? '';
		$country_code = self::country_code_iso3( $country_code );

		// Adjust position of house number/name based on country practice.
		if ( self::house_number( $country_code ) ) {
			$street_address = $number . ' ' . $street;
		} else {
			$street_address = $street . ' ' . $number;
		}
		$street_address = trim( $street_address );

		// A street address's name is the address itself.
		$name = null;
		if ( 'address' !== $address['layer'] ) {
			$name = $address['name'] ?? null;
		}

		$addr = array(
			'name'             => $name,
			'street-address'   => $street_address,
			'street'           => $street,
			'street-number'    => $number,
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
					'macroregion',
					'region',
					'macrocounty',
					'county',
				)
			),
			'region-code'      => self::ifnot(
				$address,
				array(
					'macroregion_a',
					'region_a',
					'macrocounty_a',
					'county_a',
				)
			),
			'country-name'     => self::ifnot(
				$address,
				array(
					'country',
				)
			),
			'country-code'     => $country_code,
			'postal-code'      => self::ifnot(
				$address,
				array(
					'postalcode',
				)
			),

			'latitude'         => $this->latitude,
			'longitude'        => $this->longitude,
		);

		if ( WP_DEBUG ) {
			$addr['raw']     = $json;
			$addr['feature'] = $address;
		}

		$addr = array_filter( $addr );
		if ( ! array_key_exists( 'country-code', $addr ) && array_key_exists( 'country-name', $addr ) ) {
			$addr['country-code'] = self::country_code( $addr['country-name'] );
		}
		if ( ! array_key_exists( 'region-code', $addr ) && array_key_exists( 'region', $addr ) ) {
			$addr['region-code'] = self::region_code( $addr['region'], $addr['country-code'] );
		}
		if ( ! array_key_exists( 'display-name', $addr ) ) {
			$addr['display-name'] = $this->display_name( $addr );
		}
		$tz = $this->timezone();
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
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for this Pelias Instance', 'simple-location' ) );
		}
		$args = array(
			'api_key' => $this->api,
			'text'    => $address,
		);

		$url  = trailingslashit( get_option( 'sloc_pelias_url' ) ) . 'search/';
		$json = $this->fetch_json( $url, $args );

		if ( is_wp_error( $json ) ) {
			return $json;
		}

		if ( wp_is_numeric_array( $json ) ) {
			$json = $json[0];
		}
		$return = $this->address_to_mf( $json );
		if ( is_wp_error( $return ) ) {
			return $return;
		}
		$address             = $json['features'][0];
		$return['latitude']  = $address['geometry']['coordinates'][1];
		$return['longitude'] = $address['geometry']['coordinates'][0];
		return array_filter( $return );
	}
}

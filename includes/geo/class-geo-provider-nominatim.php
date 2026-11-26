<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reverse Geolocation using Nominatim API.
 *
 * @since 1.0.0
 */
class Geo_Provider_Nominatim extends Geo_Provider {
	use Sloc_API_Nominatim;
	use Sloc_OSM_Geo;

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
		$this->name        = __( 'OpenStreetMap Nominatim', 'simple-location' );
		$this->slug        = 'nominatim';
		$this->url         = 'https://nominatim.org/';
		$this->description = __( 'Nominatim uses OpenStreetMap Data for geocoding/reverse geocoding. OSM offers a free service for infrequent use.', 'simple-location' );
		parent::__construct( $args );
	}

	/**
	 * Return an address.
	 *
	 * @since 1.0.0
	 * @since 6.1.0 Asks the Nominatim server setting's server (#233).
	 *
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function reverse_lookup() {
		$args = array(
			'format'          => 'jsonv2',
			'extratags'       => '1',
			'addressdetails'  => '1',
			'namedetails'     => '1',
			'lat'             => $this->latitude,
			'lon'             => $this->longitude,
			'zoom'            => $this->reverse_zoom,
			'accept-language' => get_bloginfo( 'language' ),
		);
		$json = $this->nominatim_json( 'reverse', $args );

		if ( is_wp_error( $json ) ) {
			return $json;
		}
		// A place that can't be found comes back as an error message with HTTP 200.
		if ( isset( $json['error'] ) ) {
			$message = is_string( $json['error'] ) ? $json['error'] : ( $json['error']['message'] ?? '' );
			return new WP_Error( 'no_results', '' !== $message ? $message : __( 'No Results Found', 'simple-location' ), array( 'status' => 404 ) );
		}
		return $this->address_to_mf( $json );
	}

	/**
	 * Convert address properties to mf2
	 *
	 * @since 1.0.0
	 * @since 6.5.0 Uses the parser shared with other Nominatim-format providers, Sloc_OSM_Address.
	 *
	 * @param  array $json Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	protected function address_to_mf( $json ) {
		return $this->osm_address_to_mf( $json );
	}


	/**
	 * Geocode address.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 The address is parsed, and a search with no results returns an error.
	 * @since 6.1.0 Asks the Nominatim server setting's server (#233).
	 *
	 * @param  string $address String representation of location.
	 * @return array|WP_Error $reverse microformats2 address elements in an array.
	 */
	public function geocode( $address ) {
		$args = array(
			'q'               => $address,
			'format'          => 'jsonv2',
			'extratags'       => '1',
			'addressdetails'  => '1',
			'namedetails'     => '1',
			'accept-language' => get_bloginfo( 'language' ),
		);
		$json = $this->nominatim_json( 'search', $args );

		if ( is_wp_error( $json ) ) {
			return $json;
		}
		return $this->search_result_to_mf( $json );
	}

	/**
	 * Parses the first result of a search.
	 *
	 * @since 6.0.0
	 *
	 * @param array $json Search response.
	 * @return array|WP_Error Microformats2 address elements, or an error if nothing was found.
	 */
	protected function search_result_to_mf( $json ) {
		if ( ! is_array( $json ) || empty( $json ) || isset( $json['error'] ) ) {
			return new WP_Error( 'no_results', __( 'No results found', 'simple-location' ), array( 'status' => 404 ) );
		}
		if ( wp_is_numeric_array( $json ) ) {
			$json = $json[0];
		}

		// Parse on a copy set to the result's location, so the address gets its coordinates and timezone.
		$result = clone $this;
		$result->set( $json['lat'] ?? '', $json['lon'] ?? '' );
		$return              = $result->address_to_mf( $json );
		$return['latitude']  = $json['lat'] ?? null;
		$return['longitude'] = $json['lon'] ?? null;
		if ( isset( $json['extratags'] ) ) {
			$return['url']   = $json['extratags']['website'] ?? null;
			$return['photo'] = $json['extratags']['image'] ?? null;
		}

		return array_filter( $return );
	}
}

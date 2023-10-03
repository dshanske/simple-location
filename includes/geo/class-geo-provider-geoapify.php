<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

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
	 * @return array $reverse microformats2 address elements in an array.
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
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for OpenRoute', 'simple-location' ) );
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

		if ( wp_is_numeric_array( $json ) ) {
			$json = $json[0];
		}
		$address             = $json['features'][0];
		$return              = $this->address_to_mf( $json );
		$return['latitude']  = $address['geometry']['coordinates'][1];
		$return['longitude'] = $address['geometry']['coordinates'][0];
		return array_filter( $return );
	}
}

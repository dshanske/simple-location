<?php
/**
 * Elevation Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reverse Geolocation using Google API.
 *
 * @since 1.0.0
 */
class Elevation_Provider_Google extends Elevation_Provider {
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
		$this->description = __( 'Google Maps Platform needs an API key and a billing account, and gives each API a monthly free allowance. Enable the Elevation API.', 'simple-location' );
		$this->slug        = 'google';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_google_api' );
		}

		parent::__construct( $args );
	}

	/**
	 * Returns elevation.
	 *
	 * @return float|WP_Error $elevation Elevation, or an error.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Returns an error without an API key or results, and shows Google's error message.
	 */
	public function elevation() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Google', 'simple-location' ) );
		}
		$args = array(
			'locations' => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
			'key'       => $this->api,
		);
		$url  = 'https://maps.googleapis.com/maps/api/elevation/json';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		if ( isset( $json['status'] ) && is_string( $json['status'] ) && 'OK' !== $json['status'] ) {
			return new WP_Error( $json['status'], $json['error_message'] ?? __( 'Error Returning Results from Google', 'simple-location' ) );
		}

		if ( ! isset( $json['results'][0]['elevation'] ) || ! is_numeric( $json['results'][0]['elevation'] ) ) {
			return new WP_Error( 'no_results', __( 'No elevation found', 'simple-location' ), array( 'status' => 404 ) );
		}
		return round( $json['results'][0]['elevation'], 2 );
	}
}

<?php
/**
 * Elevation Provider.
 *
 * @package Simple_Location
 */

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
		$this->description = __( 'Google Maps Platform API key is required, however Google offers a $200 per month credit, which is the equivalent of 28,000 queries. Click Get Started. Make sure to enable the Geocoding API. Follow the tutorial', 'simple-location' );
		$this->slug        = 'google';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_google_api' );
		}

		parent::__construct( $args );
	}

	/**
	 * Returns elevation.
	 *
	 * @return float $elevation Elevation.
	 *
	 * @since 1.0.0
	 */
	public function elevation() {
		if ( empty( $this->api ) ) {
			return null;
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

		if ( array_key_exists( 'status', $json ) && 'OK' !== $json['status'] ) {
			return new WP_Error( $json['status'], ifset( $json['errormessage'], __( 'Error Returning Results from Google', 'simple-location' ) ) );
		}

		if ( ! isset( $json['results'] ) ) {
			return null;
		}
		return round( $json['results'][0]['elevation'], 2 );
	}
}

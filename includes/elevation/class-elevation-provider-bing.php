<?php
/**
 * Elevation Geolocation Provider.
 *
 * @package Simple_Location
 */

/**
 * Elevation using Bing API.
 *
 * @since 4.6.0
 */
class Elevation_Provider_Bing extends Elevation_Provider {
	use Sloc_API_Bing;

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
		$this->name        = __( 'Bing', 'simple-location' );
		$this->slug        = 'bing';
		$this->url         = 'https://www.bingmapsportal.com/';
		$this->description = __( 'Bing Geocoding API Requires a Bings Maps key...which is available for 125k transactions.', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_bing_api' );
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
			return new WP_Error( 'no_key', __( 'Missing API Key', 'simple-location' ) );
		}
		$args = array(
			'points' => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
			'key'    => $this->api,
		);
		$url  = 'http://dev.virtualearth.net/REST/v1/Elevation/List';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( ! isset( $json['authenticationResultCode'] ) || ( isset( $json['authenticationResultCode'] ) && 'ValidCredentials' !== $json['authenticationResultCode'] ) ) {
			return new WP_Error( 'invalid_api_key', __( 'Unable to Authenticate to Bing', 'simple-location' ) );
		}
		if ( isset( $json['error_message'] ) ) {
				return new WP_Error( $json['status'], $json['error_message'] );
		}
		if ( ! isset( $json['resourceSets'] ) ) {
			return new WP_Error( 'no_resource_sets', __( 'Missing Resource Sets', 'simple-location' ), $json );
		}
			$json = $json['resourceSets'][0]['resources'][0];
		if ( ! isset( $json['elevations'] ) ) {
			return null;
		}
			return round( $json['elevations'][0], 2 );
	}
}

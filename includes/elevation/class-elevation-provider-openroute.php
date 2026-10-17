<?php
/**
 * Elevation Provider.
 *
 * @package Simple_Location
 */

/**
 * Elevation using OpenRoute Service API.
 *
 * @since 4.5.0
 */
class Elevation_Provider_OpenRoute extends Elevation_Provider {
	use Sloc_API_OpenRoute;

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
		$this->name        = __( 'OpenRoute', 'simple-location' );
		$this->slug        = 'openroute';
		$this->url         = 'https://openrouteservice.org/plans/';
		$this->description = __( 'OpenRouteService is free for everyone, but you still need to sign up for an API key.', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_openroute_api' );
		}

		parent::__construct( $args );
	}

	/**
	 * Returns elevation.
	 *
	 * @return float|WP_Error $elevation Elevation, or an error.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Returns an error instead of 0 (sea level) without a result, and rounds to two places.
	 */
	public function elevation() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for OpenRoute', 'simple-location' ) );
		}
		$args = array(
			'api_key'    => $this->api,
			'format_out' => 'point',
			'geometry'   => $this->longitude . ',' . $this->latitude,
		);

		$json = $this->fetch_json( 'https://api.openrouteservice.org/elevation/point', $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( ! isset( $json['geometry'][2] ) || ! is_numeric( $json['geometry'][2] ) ) {
			return new WP_Error( 'no_results', __( 'No elevation found', 'simple-location' ), array( 'status' => 404 ) );
		}
		return round( $json['geometry'][2], 2 );
	}
}

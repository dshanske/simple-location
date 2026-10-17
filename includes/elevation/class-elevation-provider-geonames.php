<?php
/**
 * Elevation Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Elevation using GeoNames API.
 *
 * @since 1.0.0
 */
class Elevation_Provider_Geonames extends Elevation_Provider {
	use Sloc_API_Geonames;

	/**
	 * Value GeoNames returns for points without elevation data.
	 *
	 * @since 6.0.0
	 * @var int
	 */
	const NO_DATA = -32768;

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
		$this->name        = __( 'Geonames', 'simple-location' );
		$this->slug        = 'geonames';
		$this->url         = 'https://www.geonames.org/';
		$this->description = __( 'The Geonames database is available under a creative commons license. A free user account is required.', 'simple-location' );
		if ( ! isset( $args['user'] ) ) {
			$args['user'] = get_option( 'sloc_geonames_user' );
		}

		parent::__construct( $args );
	}

	/**
	 * Returns elevation.
	 *
	 * @return float|WP_Error $elevation Elevation, or an error.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Returns an error for points without elevation data instead of -32768.
	 */
	public function elevation() {
		if ( ! $this->user ) {
			return new WP_Error( 'missing_username', __( 'Missing GeoNames User', 'simple-location' ) );
		}
		$args = array(
			'username' => $this->user,
			'lat'      => $this->latitude,
			'lng'      => $this->longitude,
		);
		$url  = 'https://secure.geonames.org/srtm1';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( 1 === count( $json ) && array_key_exists( 'status', $json ) ) {
			return new WP_Error( 'unknown_error', $json['status']['message'] );
		}

		if ( array_key_exists( 'srtm1', $json ) && is_numeric( $json['srtm1'] ) ) {
			// GeoNames marks points without data, such as the ocean, with -32768.
			if ( self::NO_DATA === (int) $json['srtm1'] ) {
				return new WP_Error( 'no_elevation_data', __( 'GeoNames has no elevation data for this location', 'simple-location' ), array( 'status' => 404 ) );
			}
			return round( $json['srtm1'], 2 );
		}
		return new WP_Error( 'unknown_error', __( 'Unknown Geonames Error', 'simple-location' ) );
	}
}

<?php
/**
 * Elevation Provider.
 *
 * @package Simple_Location
 */

/**
 * Elevation using MapQuest Open APIs.
 *
 * @since 4.5.0
 */
class Elevation_Provider_OpenMapquest extends Elevation_Provider {
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
		$this->name = __( 'Open Search(Nominatim) via Mapquest', 'simple-location' );
		$this->slug = 'openmapquest';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_mapquest_api' );
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
			'latLngCollection' => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
			'key'              => $this->api,
		);
		$url  = 'https://open.mapquestapi.com/elevation/v1/profile';

		$json = $this->fetch_json( $url, $args );

		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( isset( $json['error_message'] ) ) {
				return new WP_Error( $json['status'], $json['error_message'] );
		}
		if ( ! isset( $json['elevationProfile'] ) ) {
			return null;
		}
		return round( $json['elevationProfile'][0]['height'], 2 );
	}
}

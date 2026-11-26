<?php
/**
 * Venue Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Venue Search using Nominatim API.
 *
 * @since 1.0.0
 */
class Venue_Provider_Nominatim extends Venue_Provider {
	use Sloc_API_Nominatim;
	use Sloc_OSM_Venue;

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
	 * Returns what a site may keep of this provider's places: the details, as OpenStreetMap's license allows with credit.
	 *
	 * @since 6.1.0
	 *
	 * @return string Venue_Provider::STORAGE_FULL.
	 */
	public function storage() {
		return self::STORAGE_FULL;
	}

	/**
	 * Return an address.
	 *
	 * @since 6.0.0 An error response is an error, or an empty list when there is nothing at the point.
	 * @since 6.1.0 Asks the Nominatim server setting's server (#233).
	 *
	 * @return array|WP_Error $reverse microformats2 address elements in an array.
	 */
	public function reverse_lookup() {
		$args = array(
			'format'          => 'jsonv2',
			'extratags'       => '1',
			'addressdetails'  => '1',
			'namedetails'     => '1',
			'lat'             => $this->latitude,
			'lon'             => $this->longitude,
			'zoom'            => 18,
			'accept-language' => get_bloginfo( 'language' ),
		);
		$json = $this->nominatim_json( 'reverse', $args );

		if ( is_wp_error( $json ) ) {
			return $json;
		}

		// Errors come back as an object with a 200 status; "Unable to geocode" means there is nothing at the point.
		if ( isset( $json['error'] ) ) {
			$message = is_string( $json['error'] ) ? $json['error'] : ( $json['error']['message'] ?? '' );
			if ( 'Unable to geocode' === $message ) {
				return array( 'items' => array() );
			}
			return new WP_Error( 'unable_to_retrieve', $message ? $message : __( 'Unable to Retrieve', 'simple-location' ) );
		}

		$return = array();
		if ( wp_is_numeric_array( $json ) ) {
			foreach ( $json as $item ) {
				$return[] = $this->address_to_hcard( $item );
			}
			return array( 'items' => $return );
		} else {
			return array(
				'items' => array( $this->address_to_hcard( $json ) ),
			);
		}
	}

	/**
	 * Convert address properties to hcard
	 *
	 * @since 6.0.0 Handles Wikipedia tags without a language prefix.
	 * @since 6.1.0 Uses the place's own coordinates (#720), and adds the shared venue category and a location icon (#707).
	 * @since 6.5.0 Uses the parser shared with other Nominatim-format providers, Sloc_OSM_Venue.
	 *
	 * @param  array $json Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	protected function address_to_hcard( $json ) {
		return $this->osm_venue_to_hcard( $json );
	}
}

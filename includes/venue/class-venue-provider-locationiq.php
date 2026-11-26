<?php
/**
 * Venue Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Venue Search using LocationIQ API.
 *
 * @since 5.0.0
 */
class Venue_Provider_LocationIQ extends Venue_Provider {
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
		$this->name        = __( 'LocationIQ', 'simple-location' );
		$this->slug        = 'locationiq';
		$this->url         = 'https://locationiq.com/';
		$this->description = __( 'LocationIQ offers Geocoding and Static maps, with a free tier of 5000 requests/day. Sign up for an API key', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_locationiq_api' );
		}

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
	 * @since 6.0.0 Returns an error without an API key or for an error response.
	 * @since 6.1.0 Searches the venue search radius, for up to the most venues set.
	 *
	 * @return array|WP_Error $reverse microformats2 address elements in an array.
	 */
	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Location IQ', 'simple-location' ) );
		}
		$args = array(
			'key'    => $this->api,
			'lat'    => $this->latitude,
			'lon'    => $this->longitude,
			'radius' => $this->radius,
			'limit'  => $this->limit,
		);
		$url  = 'https://us1.locationiq.com/v1/nearby';

		$json = $this->fetch_json( $url, $args );

		if ( is_wp_error( $json ) ) {
			return $json;
		}

		// Errors can come back as an object with a 200 status.
		if ( ! wp_is_numeric_array( $json ) ) {
			$message = isset( $json['error'] ) && is_string( $json['error'] ) ? $json['error'] : __( 'Unable to Retrieve', 'simple-location' );
			return new WP_Error( 'unable_to_retrieve', $message );
		}

		$return = array();

		foreach ( $json as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$card = $this->address_to_hcard( $item );
			if ( ! empty( $card ) ) {
				$return[] = $card;
			}
		}

		return array( 'items' => $return );
	}

	/**
	 * Convert address properties to hcard
	 *
	 * @since 6.0.0 Uses the venue's own coordinates and name; no longer sets the search point's altitude.
	 * @since 6.0.0 Handles Wikipedia tags without a language prefix.
	 * @since 6.1.0 Adds the shared venue category and a location icon (#707).
	 * @since 6.5.0 Uses the parser shared with other Nominatim-format providers, Sloc_OSM_Venue.
	 *
	 * @param  array $json Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	protected function address_to_hcard( $json ) {
		return $this->osm_venue_to_hcard( $json );
	}
}

<?php
/**
 * Venue Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Venue using Google API.
 *
 * @since 1.0.0
 */
class Venue_Provider_Google extends Venue_Provider {
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
		$this->description = __( 'Google Maps Platform needs an API key and a billing account, and gives each API a monthly free allowance. Enable the Places API.', 'simple-location' );
		$this->slug        = 'google';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_google_api' );
		}

		parent::__construct( $args );
	}

	/**
	 * Returns the establishments near the location.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Returns the venues under 'items', like the other venue providers, and an empty list when there are none.
	 *
	 * @return array|WP_Error h-cards under 'items', or an error.
	 */
	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Google', 'simple-location' ) );
		}
		$args = array(
			'location' => $this->latitude . ',' . $this->longitude,
			'radius'   => 200,
			'type'     => 'establishment',
			'language' => get_bloginfo( 'language' ),
			'key'      => $this->api,
		);
		$url  = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json?';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		// ZERO_RESULTS means there are no establishments within the radius.
		if ( isset( $json['status'] ) && is_string( $json['status'] ) && ! in_array( $json['status'], array( 'OK', 'ZERO_RESULTS' ), true ) ) {
			return new WP_Error( $json['status'], $json['error_message'] ?? __( 'Error Returning Results from Google', 'simple-location' ), array( $json ) );
		}

		$items = array();
		foreach ( $json['results'] ?? array() as $result ) {
			$items[] = $this->address_to_hcard( $result );
		}

		return array( 'items' => $items );
	}

	/**
	 * Convert address properties to mf2
	 *
	 * @param  array $data Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	private function address_to_hcard( $data ) {
		$card = array(
			'type' => 'card',
		);
		if ( WP_DEBUG ) {
			$card['raw'] = $data;
		}

		$card['name']      = $data['name'] ?? null;
		$card['plus-code'] = $data['plus_code']['global_code'] ?? null;
		if ( isset( $data['geometry'] ) ) {
			$card['latitude']    = $data['geometry']['location']['lat'] ?? null;
			$card['longitude']   = $data['geometry']['location']['lng'] ?? null;
			$card['boundingbox'] = $data['geometry']['viewport'] ?? null;
		}
		$card['category'] = $data['types'] ?? null;

		$tz = $this->timezone();
		if ( $tz ) {
			$card = array_merge( $card, $tz );
		}
		$card = array_filter( $card );

		return $card;
	}
}

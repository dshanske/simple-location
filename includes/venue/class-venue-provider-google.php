<?php
/**
 * Venue Provider.
 *
 * @package Simple_Location
 */

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
		$this->description = __( 'Google Maps Platform API key is required, however Google offers a $200 per month credit, which is the equivalent of 28,000 queries. Click Get Started. Make sure to enable the Geocoding API. Follow the tutorial', 'simple-location' );
		$this->slug        = 'google';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_google_api' );
		}

		parent::__construct( $args );
	}

	/**
	 * Return an address.
	 *
	 * @return array $reverse microformats2 address elements in an array.
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

		if ( array_key_exists( 'status', $json ) && 'OK' !== $json['status'] ) {
			return new WP_Error( $json['status'], ifset( $json['errormessage'], __( 'Error Returning Results from Google', 'simple-location' ) ), array( $json ) );
		}

		$json  = $json['results'];
		$items = array();

		foreach ( $json as $result ) {
			$items[] = $this->address_to_hcard( $result );
		}

		return $items;
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

		$card['name']      = ifset( $data['name'] );
		$card['plus-code'] = ifset( $data['plus_code']['global_code'] );
		if ( isset( $data['geometry'] ) ) {
			$card['latitude']    = ifset( $data['geometry']['location']['lat'] );
			$card['longitude']   = ifset( $data['geometry']['location']['lng'] );
			$card['boundingbox'] = ifset( $data['geometry']['viewport'] );
		}
		$card['category'] = $data['types'];

		$tz = $this->timezone();
		if ( $tz ) {
			$card = array_merge( $card, $tz );
		}
		$card = array_filter( $card );

		return $card;
	}
}

<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

/**
 * Reverse Geolocation using LocationIQ API which uses the Nominatim Mapping
 *
 * @since 1.0.0
 */
class Geo_Provider_LocationIQ extends Geo_Provider_Nominatim {
	use Sloc_API_LocationIQ;

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

		Geo_Provider::__construct( $args );
	}

	/**
	 * Return an address.
	 *
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Bing', 'simple-location' ) );
		}
		$args = array(
			'key'             => $this->api,
			'format'          => 'json',
			'lat'             => $this->latitude,
			'lon'             => $this->longitude,
			'statecode'       => 1,
			'accept-language' => get_bloginfo( 'language' ),
			'extratags'       => 1,
			'addressdetails'  => 1,
		);

		$json = $this->fetch_json( 'https://us1.locationiq.com/v1/reverse.php', $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		return $this->address_to_mf( $json );
	}

	/**
	 * Geocode address.
	 *
	 * @param  string $address String representation of location.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function geocode( $address ) {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Bing', 'simple-location' ) );
		}
		$args = array(
			'key'             => $this->api,
			'format'          => 'json',
			'addressdetails'  => 1,
			'extratags'       => 1,
			'dedupe'          => 1,
			'limit'           => 1,
			'statecode'       => 1,
			'q'               => $address,
			'accept-language' => get_bloginfo( 'language' ),
		);

		$json = $this->fetch_json( 'https://us1.locationiq.com/v1/search.php', $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		if ( wp_is_numeric_array( $json ) ) {
			$json = $json[0];
		}

		$address             = $json['address'];
		$return              = $this->address_to_mf( $address );
		$return['latitude']  = ifset( $json['lat'] );
		$return['longitude'] = ifset( $json['lon'] );
		if ( isset( $json['extratags'] ) ) {
			$return['url']   = ifset( $json['extratags']['website'] );
			$return['photo'] = ifset( $json['extratags']['image'] );
		}
		return array_filter( $return );
	}
}

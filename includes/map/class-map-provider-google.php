<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map Provider using Google Map API.
 *
 * @since 1.0.0
 */
class Map_Provider_Google extends Map_Provider {
	use Sloc_API_Google;

	/**
	 * Base URL of the Maps Static API.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const BASE_URL = 'https://maps.googleapis.com/maps/api/staticmap';

	/**
	 * URL signing secret from the Google Cloud console, in URL-safe base64.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $secret = '';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Uses the Google map API key when one is set, and the URL signing secret.
	 *
	 * @param array $args {
	 *  Arguments. See Map_Provider::__construct(), and also the following.
	 *  @type string $secret URL signing secret. Defaults to the sloc_google_map_secret setting.
	 * }
	 */
	public function __construct( $args = array() ) {
		$this->name         = __( 'Google Maps', 'simple-location' );
		$this->url          = 'https://developers.google.com/maps/';
		$this->description  = __( 'Google Maps Platform API key is required, however Google offers a $200 per month credit, which is the equivalent of 28,000 queries. Click Get Started. Make sure to enable the Static Map API. Follow the tutorial', 'simple-location' );
		$this->slug         = 'google';
		$this->max_width    = 640;
		$this->max_height   = 640;
		$this->max_map_zoom = 20;

		if ( ! isset( $args['api'] ) ) {
			$args['api'] = self::get_map_api_key( 'google' );
		}
		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_google_style' );
		}
		// The style setting is only registered, with its default, while Google is the map provider.
		if ( empty( $args['style'] ) ) {
			$args['style'] = 'roadmap';
		}
		if ( ! isset( $args['secret'] ) ) {
			$args['secret'] = get_option( 'sloc_google_map_secret' );
		}
		$this->secret = is_string( $args['secret'] ) ? trim( $args['secret'] ) : '';

		parent::__construct( $args );
	}

	/**
	 * Returns the map styles the provider offers.
	 *
	 * @since 3.2.1
	 *
	 * @return array Style names keyed by ID.
	 */
	public function get_styles() {
		return array(
			'roadmap'   => __( 'Roadmap', 'simple-location' ),
			'satellite' => __( 'Satellite', 'simple-location' ),
			'terrain'   => __( 'Terrain', 'simple-location' ),
			'hybrid'    => __( 'Satellite and Roadmap Hybrid', 'simple-location' ),
		);
	}

	/**
	 * Returns the pixel densities the provider can draw the static map at.
	 *
	 * @since 6.0.0
	 *
	 * @return int[] Pixel densities.
	 */
	public function get_scales() {
		return array( 1, 2 );
	}

	/**
	 * Returns the URL of a static map centered on the location, with a marker.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 The query is URL-encoded, and the URL is signed when a signing secret is set.
	 *
	 * @return string|WP_Error URL, an empty string without an API key, or an error if the signing secret isn't valid.
	 */
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return '';
		}
		return $this->build_url(
			array(
				'markers'  => sprintf( 'color:red|label:X|%1$s,%2$s', $this->latitude, $this->longitude ),
				'size'     => sprintf( '%1$sx%2$s', $this->width, $this->height ),
				'maptype'  => $this->style,
				'language' => get_bloginfo( 'language' ),
				'key'      => $this->api,
				'zoom'     => $this->map_zoom,
				'scale'    => $this->scale,
			)
		);
	}

	/**
	 * Builds a Maps Static API URL, signed when a signing secret is set.
	 *
	 * The query is encoded before signing, because Google checks the signature against the URL exactly as sent.
	 *
	 * @since 6.0.0
	 *
	 * @param array $query Query parameters.
	 * @return string|WP_Error URL, or an error if the signing secret isn't valid.
	 */
	protected function build_url( $query ) {
		$url = self::BASE_URL . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		if ( '' === $this->secret ) {
			return $url;
		}
		$signed = self::sign_url( $url, $this->secret );
		if ( false === $signed ) {
			return new WP_Error( 'invalid_signing_secret', __( 'The Google URL signing secret is not valid. Copy it again from the Google Cloud console.', 'simple-location' ) );
		}
		return $signed;
	}

	/**
	 * Signs a Google Maps Platform URL.
	 *
	 * The signature is an HMAC-SHA1 of the URL's path and query, keyed with the decoded secret, in URL-safe base64.
	 * It is added as the last parameter. See https://developers.google.com/maps/documentation/maps-static/digital-signature
	 *
	 * @since 6.0.0
	 *
	 * @param string $url    URL with an encoded query.
	 * @param string $secret URL signing secret, in URL-safe base64.
	 * @return string|false Signed URL, or false if the secret isn't valid base64.
	 */
	public static function sign_url( $url, $secret ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Google issues the secret in base64.
		$key = base64_decode( strtr( $secret, '-_', '+/' ), true );
		if ( empty( $key ) ) {
			return false;
		}
		$query    = wp_parse_url( $url, PHP_URL_QUERY );
		$resource = wp_parse_url( $url, PHP_URL_PATH ) . ( $query ? '?' . $query : '' );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Google expects the signature in base64.
		$signature = strtr( base64_encode( hash_hmac( 'sha1', $resource, $key, true ) ), '+/', '-_' );
		return $url . ( $query ? '&' : '?' ) . 'signature=' . $signature;
	}

	/**
	 * Returns the URL of a static map with a marker at each location and a path joining them.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Uses the roadmap style when none is set, and has no empty marker location.
	 * @since 6.0.0 The query is URL-encoded, and the URL is signed when a signing secret is set.
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return string URL, or an empty string without an API key or locations, or if the signing secret isn't valid.
	 */
	public function get_archive_map( $locations ) {
		if ( empty( $this->api ) || empty( $locations ) ) {
			return '';
		}

		$markers = array();
		foreach ( $locations as $location ) {
			$markers[] = sprintf( '%1$s,%2$s', $location[0], $location[1] );
		}
		$polyline = Sloc_Polyline::encode( $locations );

		$map = $this->build_url(
			array(
				'markers'  => 'size:tiny|color:red|label:P|' . implode( '|', $markers ),
				'size'     => sprintf( '%1$sx%2$s', $this->width, $this->height ),
				'maptype'  => $this->style,
				'language' => get_bloginfo( 'language' ),
				'key'      => $this->api,
				'path'     => 'color:0xff0000ff|weight:5|enc:' . $polyline,
			)
		);
		return is_wp_error( $map ) ? '' : $map;
	}

	/**
	 * Returns a link to the location on Google Maps.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Uses the documented Maps URLs search link over HTTPS.
	 *
	 * @return string URL.
	 */
	public function get_the_map_url() {
		return sprintf( 'https://www.google.com/maps/search/?api=1&query=%1$s%%2C%2$s', $this->latitude, $this->longitude );
	}

	/**
	 * Returns the HTML for the map, which is always the static map.
	 *
	 * @since 3.0.1
	 *
	 * @param boolean $static Unused; the map is always static.
	 * @return string HTML marked up map.
	 */
	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html( true );
	}
}

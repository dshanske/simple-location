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
	 * Image formats the provider can be asked for. Google draws 256-color PNGs unless asked otherwise.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const FORMATS = array( 'png', 'png8', 'jpg', 'gif' );

	/**
	 * Image format the plugin asks for when none is chosen.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const DEFAULT_FORMAT = 'png8';

	/**
	 * Base URL of the Maps Static API.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const BASE_URL = 'https://maps.googleapis.com/maps/api/staticmap';

	/**
	 * Width, in pixels, of the whole world at zoom 0 on this provider's static maps.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	protected $tile_size = 256;

	/**
	 * Clickable area of an archive map marker, in pixels from the marker's location. Tiny markers are small pins.
	 *
	 * @since 6.1.0
	 * @var int[]
	 */
	protected $marker_box = array( -8, -22, 8, 1 );

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
		$this->description  = __( 'Google Maps Platform needs an API key and a billing account, and gives each API a monthly free allowance. Enable the Maps Static API.', 'simple-location' );
		$this->slug         = 'google';
		$this->max_width    = 640;
		$this->max_height   = 640;
		$this->max_map_zoom = 20;

		if ( ! isset( $args['api'] ) ) {
			$args['api'] = self::get_map_api_key( 'google' );
		}
		if ( ! isset( $args['style'] ) ) {
			$args['style'] = $this->get_style_setting( 'sloc_google_style' );
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
	 * Returns the provider's style for each style family.
	 *
	 * Light and dark are Google's roadmap with its silver and night styling.
	 *
	 * @since 6.1.0
	 *
	 * @return string[] Style IDs keyed by family. See Map_Provider::get_family_styles().
	 */
	public function get_family_styles() {
		return array(
			'standard'  => 'roadmap',
			'light'     => 'light',
			'dark'      => 'dark',
			'satellite' => 'hybrid',
			'terrain'   => 'terrain',
		);
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
			'light'     => __( 'Light (styled roadmap)', 'simple-location' ),
			'dark'      => __( 'Dark (styled roadmap)', 'simple-location' ),
		);
	}

	/**
	 * Returns the map styling rules of the light and dark styles, which are Google's roadmap restyled.
	 *
	 * These are Google's silver and night examples for styled maps.
	 *
	 * @since 6.1.0
	 *
	 * @return string[][] Rules for the `style` parameter, keyed by style.
	 */
	public static function get_style_rules() {
		return array(
			'light' => array(
				'element:geometry|color:0xf5f5f5',
				'element:labels.icon|visibility:off',
				'element:labels.text.fill|color:0x616161',
				'element:labels.text.stroke|color:0xf5f5f5',
				'feature:administrative.land_parcel|element:labels.text.fill|color:0xbdbdbd',
				'feature:poi|element:geometry|color:0xeeeeee',
				'feature:poi|element:labels.text.fill|color:0x757575',
				'feature:poi.park|element:geometry|color:0xe5e5e5',
				'feature:poi.park|element:labels.text.fill|color:0x9e9e9e',
				'feature:road|element:geometry|color:0xffffff',
				'feature:road.arterial|element:labels.text.fill|color:0x757575',
				'feature:road.highway|element:geometry|color:0xdadada',
				'feature:road.highway|element:labels.text.fill|color:0x616161',
				'feature:road.local|element:labels.text.fill|color:0x9e9e9e',
				'feature:transit.line|element:geometry|color:0xe5e5e5',
				'feature:transit.station|element:geometry|color:0xeeeeee',
				'feature:water|element:geometry|color:0xc9c9c9',
				'feature:water|element:labels.text.fill|color:0x9e9e9e',
			),
			'dark'  => array(
				'element:geometry|color:0x242f3e',
				'element:labels.text.stroke|color:0x242f3e',
				'element:labels.text.fill|color:0x746855',
				'feature:administrative.locality|element:labels.text.fill|color:0xd59563',
				'feature:poi|element:labels.text.fill|color:0xd59563',
				'feature:poi.park|element:geometry|color:0x263c3f',
				'feature:poi.park|element:labels.text.fill|color:0x6b9a76',
				'feature:road|element:geometry|color:0x38414e',
				'feature:road|element:geometry.stroke|color:0x212a37',
				'feature:road|element:labels.text.fill|color:0x9ca5b3',
				'feature:road.highway|element:geometry|color:0x746855',
				'feature:road.highway|element:geometry.stroke|color:0x1f2835',
				'feature:road.highway|element:labels.text.fill|color:0xf3d19c',
				'feature:transit|element:geometry|color:0x2f3948',
				'feature:transit.station|element:labels.text.fill|color:0xd59563',
				'feature:water|element:geometry|color:0x17263c',
				'feature:water|element:labels.text.fill|color:0x515c6d',
				'feature:water|element:labels.text.stroke|color:0x17263c',
			),
		);
	}

	/**
	 * Returns Google's map type for the style: the light and dark styles are the roadmap restyled.
	 *
	 * @since 6.1.0
	 *
	 * @return string Map type.
	 */
	protected function get_maptype() {
		return array_key_exists( $this->style, self::get_style_rules() ) ? 'roadmap' : (string) $this->style;
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
	 * @since 6.1.0 Uses the image format setting.
	 * @since 6.1.0 Uses the marker settings.
	 * @since 6.1.0 Uses the language from get_language().
	 *
	 * @return string|WP_Error URL, an empty string without an API key, or an error if the signing secret isn't valid.
	 */
	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return '';
		}
		return $this->build_url(
			$this->add_format(
				array(
					'markers'  => sprintf( '%1$s|%2$s,%3$s', $this->get_marker_style(), $this->latitude, $this->longitude ),
					'size'     => sprintf( '%1$sx%2$s', $this->width, $this->height ),
					'maptype'  => $this->get_maptype(),
					'language' => $this->get_language(),
					'key'      => $this->api,
					'zoom'     => $this->map_zoom,
					'scale'    => $this->scale,
				)
			)
		);
	}

	/**
	 * Returns the style of a single location's marker.
	 *
	 * Google has no dot, so a dot is its tiny marker. Its normal marker is the large size.
	 *
	 * @since 6.1.0
	 *
	 * @return string Marker style, such as `size:mid|color:0xd32f2f|label:X`.
	 */
	protected function get_marker_style() {
		$sizes = array(
			'small'  => 'size:small|',
			'medium' => 'size:mid|',
			'large'  => '',
		);
		$size  = 'dot' === $this->marker ? 'size:tiny|' : $sizes[ $this->marker_size ];
		return $size . 'color:0x' . $this->get_marker_hex() . '|label:X';
	}

	/**
	 * Adds the chosen image format to a Maps Static API query.
	 *
	 * Without a chosen format, none is sent and Google draws a 256-color PNG.
	 *
	 * @since 6.1.0
	 *
	 * @param array $query Query parameters.
	 * @return array Query parameters.
	 */
	protected function add_format( $query ) {
		if ( '' !== $this->format ) {
			// Google's png is the 256-color png8; full color is png32.
			$query['format'] = $this->get_vendor_format( array( 'png' => 'png32' ) );
		}
		return $query;
	}

	/**
	 * Builds a Maps Static API URL, signed when a signing secret is set.
	 *
	 * The query is encoded before signing, because Google checks the signature against the URL exactly as sent.
	 *
	 * @since 6.0.0
	 * @since 6.1.0 Adds the styling rules of the light and dark styles.
	 *
	 * @param array $query Query parameters.
	 * @return string|WP_Error URL, or an error if the signing secret isn't valid.
	 */
	protected function build_url( $query ) {
		// Without a language, Google shows each place's local names.
		if ( isset( $query['language'] ) && '' === $query['language'] ) {
			unset( $query['language'] );
		}
		$url = self::BASE_URL . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		// Each styling rule is its own style parameter, which http_build_query() can't repeat.
		foreach ( self::get_style_rules()[ $this->style ] ?? array() as $rule ) {
			$url .= '&style=' . rawurlencode( $rule );
		}
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
	 * @since 6.1.0 Centred and zoomed by the plugin, so its markers can be linked to their posts.
	 * @since 6.1.0 Drawn at the map's pixel density, for high-density images.
	 * @since 6.1.0 Uses the image format setting.
	 * @since 6.1.0 Markers are in the marker color.
	 * @since 6.1.0 Uses the language from get_language().
	 * @since 6.1.0 A track has markers only at its ends, or none, and a line through its points.
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return string URL, or an empty string without an API key or locations, or if the signing secret isn't valid.
	 */
	public function get_archive_map( $locations ) {
		if ( empty( $this->api ) || empty( $locations ) ) {
			return '';
		}

		$markers = array();
		foreach ( $this->get_marker_locations( $locations ) as $location ) {
			$markers[] = sprintf( '%1$s,%2$s', $location[0], $location[1] );
		}
		$polyline = Sloc_Polyline::encode( $locations );

		$args = array(
			'markers'  => $markers ? 'size:tiny|color:0x' . $this->get_marker_hex() . '|label:P|' . implode( '|', $markers ) : '',
			'size'     => sprintf( '%1$sx%2$s', $this->width, $this->height ),
			'maptype'  => $this->get_maptype(),
			'language' => $this->get_language(),
			'key'      => $this->api,
			'path'     => 'color:0xff0000ff|weight:5|enc:' . $polyline,
		);
		$view = $this->get_archive_view( $locations );
		if ( null !== $view ) {
			$args['center'] = sprintf( '%1$s,%2$s', $view['latitude'], $view['longitude'] );
			$args['zoom']   = $view['zoom'];
		}
		if ( 1 < $this->scale ) {
			$args['scale'] = $this->scale;
		}
		$map = $this->build_url( $this->add_format( array_filter( $args, 'strlen' ) ) );
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

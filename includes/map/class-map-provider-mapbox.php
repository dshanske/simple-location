<?php
/**
 * Map Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map Provider using MapBox Map API.
 *
 * @since 1.0.0
 */
class Map_Provider_Mapbox extends Map_Provider {
	use Sloc_API_Mapbox;

	/**
	 * Image formats the provider can be asked for. Without one, Mapbox draws PNGs of styles with vector layers and JPEGs of raster-only styles.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const FORMATS = array( 'png', 'jpg', 'webp' );

	/**
	 * Image format the plugin asks for when none is chosen.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const DEFAULT_FORMAT = '';

	/**
	 * Width, in pixels, of the whole world at zoom 0 on this provider's static maps. Mapbox draws 512-pixel tiles.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	protected $tile_size = 512;

	/**
	 * Whether the provider's static maps accept fractional zoom levels.
	 *
	 * @since 6.1.0
	 * @var bool
	 */
	protected $fractional_zoom = true;

	/**
	 * Token for listing the user's styles, which needs the secret styles:list scope. It is only sent from the server.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $styles_token = '';

	/**
	 * Whether map images have no token because the only one set is a secret token.
	 *
	 * @since 6.1.0
	 * @var bool
	 */
	protected $secret_token = false;

	/**
	 * Constructor.
	 *
	 * @since 3.4.0
	 * @since 6.1.0 Map images use the Mapbox map API key when one is set, and never a secret token. The style list uses
	 *              the Mapbox API key.
	 *
	 * @param array $args {
	 *     Arguments. See Map_Provider::__construct() for the others.
	 *
	 *     @type string $styles_token Token for the style list. Default the sloc_mapbox_api setting.
	 * }
	 */
	public function __construct( $args = array() ) {
		$this->name         = __( 'Mapbox', 'simple-location' );
		$this->slug         = 'mapbox';
		$this->url          = 'https://www.mapbox.com/';
		$this->description  = __( 'Mapbox offers 50,000 Static Map Requests per month with a free API key.', 'simple-location' );
		$this->max_width    = 1280;
		$this->max_height   = 1280;
		$this->max_map_zoom = 22;
		if ( ! isset( $args['styles_token'] ) ) {
			$args['styles_token'] = get_option( 'sloc_mapbox_api' );
		}
		$this->styles_token = is_string( $args['styles_token'] ) ? $args['styles_token'] : '';

		if ( ! isset( $args['api'] ) ) {
			$args['api'] = self::get_map_api_key( 'mapbox' );
		}
		// Every visitor sees the token in map images, so a secret token is never used for them.
		if ( self::is_secret_token( $args['api'] ) ) {
			$args['api']        = '';
			$this->secret_token = true;
		}

		if ( ! isset( $args['user'] ) ) {
			$args['user'] = get_option( 'sloc_mapbox_user' );
		}

		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_mapbox_style' );
		}

		parent::__construct( $args );
	}

	/**
	 * Whether a token is a Mapbox secret token, which must not be shown to visitors.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $token Token.
	 * @return bool True for a secret (sk.) token.
	 */
	public static function is_secret_token( $token ) {
		return is_string( $token ) && 0 === strpos( trim( $token ), 'sk.' );
	}

	/**
	 * Returns Mapbox's own styles, which are requested from the mapbox account instead of the user's.
	 *
	 * @since 3.4.0
	 *
	 * @return array Style names keyed by ID.
	 */
	public function default_styles() {
		return array(
			'streets-v11'           => 'Mapbox Streets',
			'outdoors-v11'          => 'Mapbox Outdoor',
			'light-v10'             => 'Mapbox Light',
			'dark-v10'              => 'Mapbox Dark',
			'satellite-v9'          => 'Mapbox Satellite',
			'satellite-streets-v11' => 'Mapbox Satellite Streets',
			'navigation-day-v1'     => 'Mapbox Navigation Day',
			'navigation-night-v1'   => 'Mapbox Navigation Night',
		);
	}

	/**
	 * Returns the built-in styles, plus the user's own styles for a user other than mapbox.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Errors carry the HTTP status, and Mapbox's message when there is one.
	 * @since 6.1.0 Uses the style list token, not the map image token.
	 *
	 * @return array|WP_Error Style names keyed by ID, or an error if the user's styles can't be fetched.
	 */
	public function get_styles() {
		if ( empty( $this->user ) ) {
			return array();
		}
		$return = $this->default_styles();
		if ( 'mapbox' === $this->user ) {
			return $return;
		}
		$json = $this->fetch_json(
			'https://api.mapbox.com/styles/v1/' . rawurlencode( $this->user ),
			array(
				'access_token' => $this->styles_token,
				'limit'        => 50,
			)
		);
		if ( is_wp_error( $json ) ) {
			// A user without styles of their own gets an empty list.
			$data = $json->get_error_data();
			if ( 'not_json_response' === $json->get_error_code() && '[]' === trim( $data['body'] ?? '' ) ) {
				return $return;
			}
			if ( 'invalid_response' !== $json->get_error_code() ) {
				return $json;
			}
			$status  = isset( $data['status'] ) ? (int) $data['status'] : 0;
			$body    = json_decode( $data['body'] ?? '', true );
			$message = ( is_array( $body ) && ! empty( $body['message'] ) && is_string( $body['message'] ) )
				? $body['message']
				/* translators: %d: HTTP status code. */
				: sprintf( __( 'Mapbox styles could not be loaded (HTTP status %d).', 'simple-location' ), $status );
			return new WP_Error( 'mapbox_styles', $message, array( 'status' => $status ) );
		}
		foreach ( $json as $style ) {
			if ( is_array( $style ) && isset( $style['id'], $style['name'] ) ) {
				$return[ $style['id'] ] = $style['name'];
			}
		}
		return $return;
	}


	/**
	 * Returns the URL of a link to the location on a map.
	 *
	 * @since 3.4.0
	 *
	 * @return string URL.
	 */
	public function get_the_map_url() {
		return sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=%3$s/%1$s/%2$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	/**
	 * Returns the HTML for the map, which is always the static map.
	 *
	 * @since 3.4.0
	 * @since 6.1.0 Followed by the map's credit when the attribution setting puts it under the map.
	 *
	 * @param boolean $static Unused; the map is always static.
	 * @return string HTML marked up map.
	 */
	public function get_the_map( $static = true ) {
		$map = $this->get_the_static_map_html( true );
		// Without an image, $map is empty or the reason shown to administrators.
		return false === strpos( $map, '<img' ) ? $map : $map . $this->get_attribution_html();
	}

	/**
	 * Returns the query that removes the credit from the image when the attribution setting puts it under the map.
	 *
	 * Mapbox's logo stays on the image.
	 *
	 * @since 6.1.0
	 *
	 * @return string `&attribution=false`, or an empty string.
	 */
	protected function get_attribution_query() {
		return 'below' === $this->attribution ? '&attribution=false' : '';
	}

	/**
	 * Returns the map's credit to show under the map, when the attribution setting puts it there.
	 *
	 * This is Mapbox's attribution for static maps on a web page, with Maxar's credit for its satellite styles.
	 *
	 * @since 6.1.0
	 *
	 * @return string HTML of a paragraph with the credit, or an empty string if the credit is on the image.
	 */
	public function get_attribution_html() {
		if ( 'below' !== $this->attribution ) {
			return '';
		}
		$credits = array(
			sprintf( '<a href="https://www.mapbox.com/about/maps/">%s</a>', esc_html__( '© Mapbox', 'simple-location' ) ),
			sprintf( '<a href="https://www.openstreetmap.org/copyright">%s</a>', esc_html__( '© OpenStreetMap', 'simple-location' ) ),
		);
		if ( 0 === strpos( (string) $this->style, 'satellite' ) ) {
			$credits[] = sprintf( '<a href="https://www.maxar.com/">%s</a>', esc_html__( '© Maxar', 'simple-location' ) );
		}
		$credits[] = sprintf( '<a href="https://apps.mapbox.com/feedback/">%s</a>', esc_html__( 'Improve this map', 'simple-location' ) );
		return '<p class="sloc-map-attribution">' . implode( ' ', $credits ) . '</p>';
	}

	/**
	 * Returns the URL of a static map with multiple locations.
	 *
	 * @since 4.0.0
	 * @since 6.1.0 Centred and zoomed by the plugin, so its markers can be linked to their posts.
	 * @since 6.1.0 Drawn at the map's pixel density, for high-density images.
	 * @since 6.1.0 Uses the image format setting.
	 * @since 6.1.0 Markers are in the marker color.
	 * @since 6.1.0 Leaves the credit off the image when the attribution setting puts it under the map.
	 *
	 * @param array $locations List of array( latitude, longitude ) points.
	 * @return string URL, or an empty string without the settings it needs or without locations.
	 */
	public function get_archive_map( $locations ) {
		if ( empty( $this->api ) || empty( $this->style ) || empty( $locations ) ) {
			return '';
		}
		$user   = $this->user;
		$styles = $this->default_styles();
		if ( array_key_exists( $this->style, $styles ) ) {
			$user = 'mapbox';
		}
		$markers = array();
		foreach ( $locations as $location ) {
			$markers[] = sprintf( 'pin-s+%1$s(%2$s,%3$s)', $this->get_marker_hex(), $location[1], $location[0] );
		}
		$polyline = Sloc_Polyline::encode( $locations );
		$view     = $this->get_archive_view( $locations );
		$map      = sprintf(
			'https://api.mapbox.com/styles/v1/%1$s/%2$s/static/%3$s,path-5+f44-0.5(%4$s)/%8$s/%5$sx%6$s%9$s%10$s?access_token=%7$s%11$s',
			$user,
			$this->style,
			implode( ',', $markers ),
			rawurlencode( $polyline ),
			$this->width,
			$this->height,
			$this->api,
			null === $view ? 'auto' : sprintf( '%1$s,%2$s,%3$s', $view['longitude'], $view['latitude'], $view['zoom'] ),
			( 2 === $this->scale ) ? '@2x' : '',
			$this->get_format_extension(),
			$this->get_attribution_query()
		);
		return $map;
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
	 * Returns the URL of a static map of the location.
	 *
	 * @since 3.4.0
	 * @since 6.1.0 Uses the image format setting.
	 * @since 6.1.0 Uses the marker settings.
	 * @since 6.1.0 Leaves the credit off the image when the attribution setting puts it under the map.
	 * @since 6.1.0 Returns an error without a usable token.
	 *
	 * @return string|WP_Error URL, an empty string without a style, or an error without a token that may be shown.
	 */
	public function get_the_static_map() {
		if ( $this->secret_token ) {
			return new WP_Error( 'secret_api_key', __( 'The Mapbox API key is a secret token, which map images would show to every visitor. Enter a public token as the Mapbox Map API Key.', 'simple-location' ) );
		}
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Mapbox', 'simple-location' ) );
		}
		if ( empty( $this->style ) ) {
			return '';
		}
		$user   = $this->user;
		$styles = $this->default_styles();
		if ( array_key_exists( $this->style, $styles ) ) {
			$user = 'mapbox';
		}
		$map = sprintf(
			'https://api.mapbox.com/styles/v1/%1$s/%2$s/static/%11$s(%3$s,%4$s)/%3$s,%4$s,%5$s,0,0/%6$sx%7$s%9$s%10$s?access_token=%8$s%12$s',
			$user,
			$this->style,
			$this->longitude,
			$this->latitude,
			$this->map_zoom,
			$this->width,
			$this->height,
			$this->api,
			( 2 === $this->scale ) ? '@2x' : '',
			$this->get_format_extension(),
			// Mapbox has small and large pins, and no dot.
			sprintf( 'pin-%1$s+%2$s', ( 'large' === $this->marker_size && 'dot' !== $this->marker ) ? 'l' : 's', $this->get_marker_hex() ),
			$this->get_attribution_query()
		);
		return $map;
	}

	/**
	 * Returns the file extension that asks Mapbox for the chosen image format.
	 *
	 * @since 6.1.0
	 *
	 * @return string Extension, such as `.jpeg`, or an empty string to let Mapbox choose.
	 */
	protected function get_format_extension() {
		$format = $this->get_vendor_format( array( 'jpg' => 'jpeg' ) );
		return '' === $format ? '' : '.' . $format;
	}
}

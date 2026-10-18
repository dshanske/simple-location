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

	public function __construct( $args = array() ) {
		$this->name         = __( 'Mapbox', 'simple-location' );
		$this->slug         = 'mapbox';
		$this->url          = 'https://www.mapbox.com/';
		$this->description  = __( 'Mapbox offers 50,000 Static Map Requests per month with a free API key.', 'simple-location' );
		$this->max_width    = 1280;
		$this->max_height   = 1280;
		$this->max_map_zoom = 22;
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_mapbox_api' );
		}

		if ( ! isset( $args['user'] ) ) {
			$args['user'] = get_option( 'sloc_mapbox_user' );
		}

		if ( ! isset( $args['style'] ) ) {
			$args['style'] = get_option( 'sloc_mapbox_style' );
		}

		parent::__construct( $args );
	}

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
				'access_token' => $this->api,
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


	public function get_the_map_url() {
		return sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=%3$s/%1$s/%2$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	public function get_the_map( $static = true ) {
		return $this->get_the_static_map_html( true );
	}

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
			$markers[] = sprintf( 'pin-s(%1$s,%2$s)', $location[1], $location[0] );
		}
		$polyline = Sloc_Polyline::encode( $locations );
		$map      = sprintf(
			'https://api.mapbox.com/styles/v1/%1$s/%2$s/static/%3$s,path-5+f44-0.5(%4$s)/auto/%5$sx%6$s?access_token=%7$s',
			$user,
			$this->style,
			implode( ',', $markers ),
			rawurlencode( $polyline ),
			$this->width,
			$this->height,
			$this->api
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

	public function get_the_static_map() {
		if ( empty( $this->api ) || empty( $this->style ) ) {
			return '';
		}
		$user   = $this->user;
		$styles = $this->default_styles();
		if ( array_key_exists( $this->style, $styles ) ) {
			$user = 'mapbox';
		}
		$map = sprintf(
			'https://api.mapbox.com/styles/v1/%1$s/%2$s/static/pin-s(%3$s,%4$s)/%3$s,%4$s,%5$s,0,0/%6$sx%7$s%9$s?access_token=%8$s',
			$user,
			$this->style,
			$this->longitude,
			$this->latitude,
			$this->map_zoom,
			$this->width,
			$this->height,
			$this->api,
			( 2 === $this->scale ) ? '@2x' : ''
		);
		return $map;
	}
}

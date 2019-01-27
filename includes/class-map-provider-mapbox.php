<?php
// Mapbox Map Provider
class Map_Provider_Mapbox extends Map_Provider {

	public function __construct( $args = array() ) {
		$this->name = __( 'Mapbox', 'simple-location' );
		$this->slug = 'mapbox';
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
			'streets-v10'           => 'Mapbox Streets',
			'outdoors-v10'          => 'Mapbox Outdoor',
			'light-v9'              => 'Mapbox Light',
			'dark-v9'               => 'Mapbox Dark',
			'satellite-v9'          => 'Mapbox Satellite',
			'satellite-streets-v10' => 'Mapbox Satellite Streets',
			'traffic-day-v2'        => 'Mapbox Traffic Day',
			'traffic-night-v2'      => 'Mapbox Traffic Night',
		);
	}

	public function get_styles() {
		if ( empty( $this->user ) ) {
			return array();
		}
		$return = $this->default_styles();
		if ( 'mapbox' === $this->user ) {
			return $return;
		}
		$url          = 'https://api.mapbox.com/styles/v1/' . $this->user . '?access_token=' . $this->api;
				$args = array(
					'headers'             => array(
						'Accept' => 'application/json',
					),
					'timeout'             => 10,
					'limit_response_size' => 1048576,
					'redirection'         => 1,
					// Use an explicit user-agent for Simple Location
					'user-agent'          => 'Simple Location for WordPress',
				);
		$request = wp_remote_get( $url, $args );
		if ( is_wp_error( $request ) ) {
			return $request; // Bail early.
		}
		$body = wp_remote_retrieve_body( $request );
		$data = json_decode( $body );
		if ( 200 !== wp_remote_retrieve_response_code( $request ) ) {
			return new WP_Error( '403', $data->message );
		}
		foreach ( $data as $style ) {
			if ( is_object( $style ) ) {
				$return[ $style->id ] = $style->name;
			}
		}
		return $return;
	}


	public function get_the_map_url() {
		return sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=%3$s/%1$s/%2$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	public function get_the_map( $static = true ) {
		if ( $static ) {
			$map  = sprintf( '<img src="%s">', $this->get_the_static_map() );
			$link = $this->get_the_map_url();
			return '<a target="_blank" href="' . $link . '">' . $map . '</a>';
		}
	}

	public function get_the_static_map() {
		if ( empty( $this->api ) ) {
			return '';
		}
		$user   = $this->user;
		$styles = $this->default_styles();
		if ( empty( $styles ) || empty( $this->styles ) ) {
			return '';
		}
		if ( array_key_exists( $this->style, $styles ) ) {
			$user = 'mapbox';
		}
		$map = sprintf( 'https://api.mapbox.com/styles/v1/%1$s/%2$s/static/pin-s(%3$s,%4$s)/%3$s,%4$s, %5$s,0,0/%6$sx%7$s?access_token=%8$s', $user, $this->style, $this->longitude, $this->latitude, $this->map_zoom, $this->width, $this->height, $this->api );
		return $map;

	}

}

register_sloc_provider( new Map_Provider_Mapbox() );

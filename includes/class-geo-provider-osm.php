<?php
// OSM Static Map Provider
class Geo_Provider_OSM extends Geo_Provider {

	public function __construct( $args = array() ) {
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

	public function reverse_lookup() {
		$query = sprintf( 'http://nominatim.openstreetmap.org/reverse?format=json&extratags=1&addressdetails=1&lat=%1$s&lon=%2$s&zoom=%3$s&accept-language=%4$s', $this->latitude, $this->longitude, $this->reverse_zoom, get_bloginfo( 'language' ) );
		error_log( $query );
		$response = wp_remote_get( $query );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$json    = json_decode( $response['body'], true );
		$address = $json['address'];
		if ( 'us' === $address['country_code'] ) {
			$region = ifset( $address['state'] ) ?: ifset( $address['county'] );
		} else {
			$region = ifset( $address['county'] ) ?: ifset( $address['state'] );
		}
		$street  = ifset( $address['house_number'], '' ) . ' ';
		$street .= ifset( $address['road'] ) ?: ifset( $address['highway'] ) ?: ifset( $address['footway'] ) ?: '';
		$addr    = array(
			'name'             => ifset( $address['attraction'] ) ?: ifset( $address['building'] ) ?: ifset( $address['hotel'] ) ?: ifset( $address['address29'] ) ?: ifset( $address['address26'] ) ?: null,
			'street-address'   => $street,
			'extended-address' => ifset( $address['boro'] ) ?: ifset( $address['neighbourhood'] ) ?: ifset( $address['suburb'] ) ?: null,
			'locality'         => ifset( $address['hamlet'] ) ?: ifset( $address['village'] ) ?: ifset( $address['town'] ) ?: ifset( $address['city'] ) ?: null,
			'region'           => $region,
			'country-name'     => ifset( $address['country'] ) ?: null,
			'postal-code'      => ifset( $address['postcode'] ) ?: null,
			'country-code'     => strtoupper( $address['country_code'] ) ?: null,
			'latitude'         => $this->latitude,
			'longitude'        => $this->longitude,
			'raw'              => $address,
		);
		if ( is_null( $addr['country-name'] ) ) {
			$codes                = json_decode( wp_remote_retrieve_body( wp_remote_get( 'http://country.io/names.json' ) ), true );
			$addr['country-name'] = $codes[ $addr['country-code'] ];
		}
				$addr         = array_filter( $addr );
		$addr['display-name'] = $this->display_name( $addr );
		$tz                   = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		return $addr;
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
		$url     = 'https://api.mapbox.com/styles/v1/' . $this->user . '?access_token=' . $this->api;
		$request = wp_remote_get( $url );
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
		return sprintf( 'http://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=%3$s/%1$s/%2$s', $this->latitude, $this->longitude, $this->map_zoom );
	}

	public function get_the_map( $static = true ) {
		if ( $static ) {
			$map  = sprintf( '<img src="%s">', $this->get_the_static_map() );
			$link = $this->get_the_map_url();
			return '<a href="' . $link . '">' . $map . '</a>';
		}
	}

	public function get_the_static_map() {
		$user = $this->user;
		if ( array_key_exists( $this->style, $this->default_styles() ) ) {
			$user = 'mapbox';
		}
		$map = sprintf( 'https://api.mapbox.com/styles/v1/%1$s/%2$s/static/pin-s(%3$s,%4$s)/%3$s,%4$s, %5$s,0,0/%6$sx%7$s?access_token=%8$s', $user, $this->style, $this->longitude, $this->latitude, $this->map_zoom, $this->width, $this->height, $this->api );
		return $map;

	}

}

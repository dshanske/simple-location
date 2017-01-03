<?php
// OSM Static Map Provider
class Geo_Provider_OSM extends Geo_Provider {

	public function __construct() {
		parent::__construct();
		$this->api = get_option( 'sloc_mapbox_api' );
		$this->map_zoom = 18;
	}

	public function reverse_lookup() {
		$response = wp_remote_get( 'http://nominatim.openstreetmap.org/reverse?format=json&extratags=1&addressdetails=1&lat=' . $this->latitude . '&lon=' . $this->longitude . '&zoom=' . $this->reverse_zoom . '&accept-language=' . get_bloginfo( 'language' ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$json = json_decode( $response['body'], true );
		$address = $json['address'];
		if ( 'us' === $address['country_code'] ) {
			$region = ifset( $address['state'] ) ?: ifset( $address['county'] );
		} else {
			$region = ifset( $address['county'] ) ?: ifset( $address['state'] );
		}
		$street = ifset( $address['house_number'], '' ) . ' ';
		$street .= ifset( $address['road'] ) ?: ifset( $address['highway'] ) ?: ifset( $address['footway'] ) ?: '';
		$addr = array(
			'name' => ifset( $address['attraction'] ) ?: ifset( $address['building'] ) ?: ifset( $address['hotel'] ) ifset( $address['address29'] ) ?: ifset( $address['address26'] ) ?: null,
			'street-address' => $street,
			'extended-address' => ifset( $address['boro'] ) ?: ifset( $address['neighbourhood'] ) ?: ifset( $address['suburb'] ) ?: null,
			'locality' => ifset( $address['hamlet'] ) ?: ifset( $address['village'] ) ?: ifset( $address['town'] ) ?: ifset( $address['city'] ) ?: null,
			'region' => $region,
			'country-name' => ifset( $address['country'] ) ?: null,
			'postal-code' => ifset( $address['postcode'] ) ?: null,
			'country-code' => strtoupper( $address['country_code'] ) ?: null,
			'latitude' => $this->latitude,
			'longitude' => $this->longitude,
			'raw' => $address,
		);
		if ( is_null( $addr['country-name'] ) ) {
			$codes = json_decode( wp_remote_retrieve_body( wp_remote_get( 'http://country.io/names.json' ) ), true );
			$addr['country-name'] = $codes[ $addr['country-code'] ];
		}
				$addr = array_filter( $addr );
		$addr['display-name'] = $this->display_name( $addr );
		$tz = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		return $addr;
	}

	public function get_the_map_url() {
		return 'http://www.openstreetmap.org/#map=14/' . $this->latitude . '/' . $this->longitude;
	}

	public function get_the_map( $static = true ) {
		$map = $this->get_the_static_map( );
		$link = $this->get_the_map_url( );
		$c = '<a href="' . $link . '"><img src="' . $map . '" /></a>';
		return $c;

	}

	public function get_the_static_map( ) {
		$map = 'https://api.mapbox.com/styles/v1/mapbox/streets-v8/static/' . $this->longitude . ',' . $this->latitude. ',' . $this->map_zoom . ',0,0/'     . $this->height . 'x' . $this->width . '?access_token=' . $this->api;
		return $map;

	}

}

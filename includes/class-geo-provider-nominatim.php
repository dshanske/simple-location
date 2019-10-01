<?php
// Nominatim API Provider
class Geo_Provider_Nominatim extends Geo_Provider {

	public function __construct( $args = array() ) {
		$this->name = __( 'OpenStreetMap Nominatim', 'simple-location' );
		$this->slug = 'nominatim';
		parent::__construct( $args );
	}

	public function elevation() {
		return null;
	}


	public function reverse_lookup() {
		$query = add_query_arg(
			array(
				'format'          => 'json',
				'extratags'       => '1',
				'addressdetails'  => '1',
				'lat'             => $this->latitude,
				'lon'             => $this->longitude,
				'zoom'            => $this->reverse_zoom,
				'accept-language' => get_bloginfo( 'language' ),
			),
			'https://nominatim.openstreetmap.org/reverse'
		);
		$args  = array(
			'headers'             => array(
				'Accept' => 'application/json',
			),
			'timeout'             => 10,
			'limit_response_size' => 1048576,
			'redirection'         => 1,
			// Use an explicit user-agent for Simple Location
			'user-agent'          => sprintf( 'Simple Location for WordPress(%1$s)', home_url() );
		);

		$response = wp_remote_get( $query, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( ( $code / 100 ) !== 2 ) {
			return new WP_Error( 'invalid_response', wp_remote_retrieve_body( $response ), array( 'status' => $code ) );
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

}

register_sloc_provider( new Geo_Provider_Nominatim() );

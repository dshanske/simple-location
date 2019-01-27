<?php
// Google Geocode API Provider
class Geo_Provider_Google extends Geo_Provider {

	public function __construct( $args = array() ) {
		$this->name = __( 'Google', 'simple-location' );
		$this->slug = 'google';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_google_api' );
		}

		parent::__construct( $args );
	}

	public function elevation() {
		if ( empty( $this->api ) ) {
			return null;
		}
		$query = add_query_arg(
			array(
				'locations' => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
				'key'       => $this->api,
			),
			'https://maps.googleapis.com/maps/api/elevation/json'
		);
		$args  = array(
			'headers'             => array(
				'Accept' => 'application/json',
			),
			'timeout'             => 10,
			'limit_response_size' => 1048576,
			'redirection'         => 1,
			// Use an explicit user-agent for Simple Location
			'user-agent'          => 'Simple Location for WordPress',
		);

		$response = wp_remote_get( $query, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( ( $code / 100 ) !== 2 ) {
			return new WP_Error( 'invalid_response', wp_remote_retrieve_body( $response ), array( 'status' => $code ) );
		}
		$json = json_decode( $response['body'], true );
		if ( isset( $json['error_message'] ) ) {
			return new WP_Error( $json['status'], $json['error_message'] );
		}
		if ( ! isset( $json['results'] ) ) {
			return null;
		}
		return round( $json['results'][0]['elevation'] );
	}

	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Google', 'simple-location' ) );
		}
		$query = add_query_arg(
			array(
				'latlng' => $this->latitude . ',' . $this->longitude,
				// 'language'      => get_bloginfo( 'language' ),
				//'location_type' => 'ROOFTOP|RANGE_INTERPOLATED',
				// 'result_type'   => 'street_address',
				'key'    => $this->api,
			),
			'https://maps.googleapis.com/maps/api/geocode/json?'
		);
		$args  = array(
			'headers'             => array(
				'Accept' => 'application/json',
			),
			'timeout'             => 10,
			'limit_response_size' => 1048576,
			'redirection'         => 1,
			// Use an explicit user-agent for Simple Location
			'user-agent'          => 'Simple Location for WordPress',
		);

		$response = wp_remote_get( $query, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( ( $code / 100 ) !== 2 ) {
			return new WP_Error( 'invalid_response', wp_remote_retrieve_body( $response ), array( 'status' => $code ) );
		}
		$json = json_decode( $response['body'], true );
		$raw  = $json;
		if ( isset( $json['results'] ) ) {
			$data = wp_is_numeric_array( $json['results'] ) ? array_shift( $json['results'] ) : $json['results'];
		} else {
			return array();
		}
		$addr                 = array(
			'latitude'  => $this->latitude,
			'longitude' => $this->longitude,
		);
		$addr['display-name'] = ifset( $data['formatted_address'] );
		$addr['plus-code']    = ifset( $data['plus_code']['global_code'] );
		if ( isset( $data['address_components'] ) ) {
			foreach ( $data['address_components'] as $component ) {
				if ( in_array( 'administrative_area_level_1', $component['types'], true ) ) {
					$addr['region'] = $component['long_name'];
				}
				if ( in_array( 'country', $component['types'], true ) ) {
					$addr['country-name'] = $component['long_name'];
					$addr['country-code'] = $component['short_name'];
				}
				if ( in_array( 'neighborhood', $component['types'], true ) ) {
					$addr['extended-address'] = $component['long_name'];
				}
				if ( in_array( 'locality', $component['types'], true ) ) {
					$addr['locality'] = $component['long_name'];
				}
				if ( in_array( 'street_number', $component['types'], true ) ) {
					$addr['street-address'] = $component['long_name'];
				}
				if ( in_array( 'route', $component['types'], true ) ) {
					if ( isset( $addr['street-address'] ) ) {
						$addr['street-address'] .= ' ' . $component['long_name'];
					} else {
						$addr['street-address'] = $component['long_name'];
					}
				}
				if ( in_array( 'postal_code', $component['types'], true ) ) {
					$addr['postal-code'] = $component['long_name'];
				}
			}
		}

		$tz = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		if ( WP_DEBUG ) {
			$addr['raw'] = $raw;
		}
		return $addr;
	}
}

register_sloc_provider( new Geo_Provider_Google() );

<?php
// Google Geocode API Provider
class Geo_Provider_Google extends Geo_Provider {

	public function __construct( $args = array() ) {
		$this->name = __( 'Google', 'simple-location' );
		$this->slug = 'google';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_google_api' );
		}

		$option = get_option( 'sloc_geo_provider' );
		if ( 'google' === $option ) {
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
			add_action( 'init', array( get_called_class(), 'init' ) );
		}
		parent::__construct( $args );
	}

	public static function admin_init() {
		add_settings_field(
			'googleapi', // id
			__( 'Google Maps API Key', 'simple-location' ), // setting title
			array( 'Loc_Config', 'string_callback' ), // display callback
			'sloc_providers', // settings page
			'sloc_api', // settings section
			array(
				'label_for' => 'sloc_google_api',
			)
		);
	}

	public static function init() {
		register_setting(
			'sloc_providers', // option group
			'sloc_google_api', // option name
			array(
				'type'         => 'string',
				'description'  => 'Google Maps API Key',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
	}

	public function elevation() {
		if ( empty( $this->api ) ) {
			return null;
		}
		$args = array(
			'locations' => sprintf( '%1$s,%2$s', $this->latitude, $this->longitude ),
			'key'       => $this->api,
		);
		$url  = 'https://maps.googleapis.com/maps/api/elevation/json';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( isset( $json['error_message'] ) ) {
			return new WP_Error( $json['status'], $json['error_message'] );
		}
		if ( ! isset( $json['results'] ) ) {
			return null;
		}
		return round( $json['results'][0]['elevation'], 2 );
	}

	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Google', 'simple-location' ) );
		}
		$args = array(
			'latlng' => $this->latitude . ',' . $this->longitude,
			// 'language'      => get_bloginfo( 'language' ),
			//'location_type' => 'ROOFTOP|RANGE_INTERPOLATED',
			// 'result_type'   => 'street_address',
			'key'    => $this->api,
		);
		$url  = 'https://maps.googleapis.com/maps/api/geocode/json?';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$raw = $json;
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

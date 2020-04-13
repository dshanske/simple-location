<?php
// Geonames Geocode API Provider
class Geo_Provider_Geonames extends Geo_Provider {

	public function __construct( $args = array() ) {
		$this->name = __( 'Geonames', 'simple-location' );
		$this->slug = 'geonames';
		if ( ! isset( $args['user'] ) ) {
			$args['user'] = get_option( 'sloc_geonames_user' );
		}

		$option = get_option( 'sloc_geo_provider' );
		if ( 'geonames' === $option ) {
			add_action( 'init', array( get_called_class(), 'init' ) );
			add_action( 'admin_init', array( get_called_class(), 'admin_init' ) );
		}
		parent::__construct( $args );
	}

	public static function init() {
		register_setting(
			'sloc_providers',
			'sloc_geonames_user',
			array(
				'type'         => 'string',
				'description'  => 'Geonames User',
				'show_in_rest' => false,
				'default'      => '',
			)
		);
	}

	public static function admin_init() {
		add_settings_field(
			'geonamesuser', // id
			__( 'Geonames User', 'simple-location' ),
			array( 'Loc_Config', 'string_callback' ),
			'sloc_providers',
			'sloc_api',
			array(
				'label_for' => 'sloc_geonames_user',
			)
		);
	}

	public function elevation() {
		if ( ! $this->user ) {
			return null;
		}
		$args = array(
			'username' => $this->user,
			'lat'      => $this->latitude,
			'lng'      => $this->longitude,
		);
		$url  = 'http://api.geonames.org/srtm1';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( array_key_exists( 'srtm1', $json ) ) {
			return round( $json['srtm1'], 2 );
		}
		return null;
	}



	public function reverse_lookup() {
		if ( ! $this->user ) {
			return null;
		}
		$args = array(
			'username' => $this->user,
			'lat'      => $this->latitude,
			'lng'      => $this->longitude,
		);
		$url  = 'https://secure.geonames.org/findNearbyPlaceNameJSON';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$json = $json['geonames'][0];
		$addr = array(
			'latitude'  => $this->latitude,
			'longitude' => $this->longitude,
		);

		$addr['street-address'] = ifset( $json['toponymName'] );
		$addr['locality']       = ifset( $json['adminName1'] );
		// $addr['region']         = ifset( $json[] );
		$addr['country-name'] = ifset( $json['countryName'] );
		$display              = array();
		foreach ( array( 'street-address', 'locality', 'country-name' ) as $prop ) {
			$display[] = ifset( $addr[ $prop ] );
		}
		$addr['display-name'] = implode( ', ', $display );
		$tz                   = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		if ( WP_DEBUG ) {
			$addr['raw'] = $json;
		}
		return $addr;
	}
}

register_sloc_provider( new Geo_Provider_Geonames() );

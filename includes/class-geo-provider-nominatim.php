<?php
// Nominatim API Provider
class Geo_Provider_Nominatim extends Geo_Provider {

	public function __construct( $args = array() ) {
		$this->name = __( 'OpenStreetMap Nominatim', 'simple-location' );
		$this->slug = 'nominatim';
		parent::__construct( $args );
	}

	public function elevation() {
		return 0;
	}


	public function reverse_lookup() {
		$args = array(
			'format'          => 'json',
			'extratags'       => '1',
			'addressdetails'  => '1',
			'lat'             => $this->latitude,
			'lon'             => $this->longitude,
			'zoom'            => $this->reverse_zoom,
			'accept-language' => get_bloginfo( 'language' ),
		);
		$url  = 'https://nominatim.openstreetmap.org/reverse';

		$json = $this->fetch_json( $url, $args );

		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$address = $json['address'];
		if ( 'us' === $address['country_code'] ) {
			$region = self::ifnot(
				$address,
				array(
					'state',
					'county',
				)
			);
		} else {
			$region = self::ifnot(
				$address,
				array(
					'county',
					'state',
				)
			);
		}
		$street  = ifset( $address['house_number'], '' ) . ' ';
		$street .= self::ifnot(
			$address,
			array(
				'road',
				'highway',
				'footway',
			)
		);
		$addr    = array(
			'name'             => self::ifnot(
				$address,
				array(
					'attraction',
					'building',
					'hotel',
					'address29',
					'address26',
				)
			),
			'street-address'   => $street,
			'extended-address' => self::ifnot(
				$address,
				array(
					'boro',
					'neighbourhood',
					'suburb',
				)
			),
			'locality'         => self::ifnot(
				$address,
				array(
					'hamlet',
					'village',
					'town',
					'city',
				)
			),
			'region'           => $region,
			'country-name'     => self::ifnot(
				$address,
				array(
					'country',
				)
			),
			'postal-code'      => self::ifnot(
				$address,
				array(
					'postcode',
				)
			),
			'country-code'     => strtoupper( $address['country_code'] ),
			'latitude'         => $this->latitude,
			'longitude'        => $this->longitude,
			'raw'              => $address,
		);
		if ( is_null( $addr['country-name'] ) ) {
			$codes                = json_decode(
				wp_remote_retrieve_body(
					wp_remote_get( 'http://country.io/names.json' )
				),
				true
			);
			$addr['country-name'] = $codes[ $addr['country-code'] ];
		}
		$addr                 = array_filter( $addr );
		$addr['display-name'] = $this->display_name( $addr );
		$tz                   = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		return $addr;
	}

}

register_sloc_provider( new Geo_Provider_Nominatim() );

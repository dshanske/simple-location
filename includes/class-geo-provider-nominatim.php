<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

/**
 * Reverse Geolocation using Nominatim API.
 *
 * @since 1.0.0
 */
class Geo_Provider_Nominatim extends Geo_Provider {

	/**
	 * Constructor for the Abstract Class.
	 *
	 * The default version of this just sets the parameters.
	 *
	 * @param array $args {
	 *  Arguments.
	 *  @type string $api API Key.
	 *  @type float $latitude Latitude.
	 *  @type float $longitude Longitude.
	 *  @type float $altitude Altitude.
	 *  @type string $address Formatted Address String
	 *  @type int $reverse_zoom Reverse Zoom. Default 18.
	 *  @type string $user User name.
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'OpenStreetMap Nominatim', 'simple-location' );
		$this->slug = 'nominatim';
		parent::__construct( $args );
	}

	/**
	 * Returns elevation but there is no Nominatim Elevation API.
	 *
	 * @return float $elevation Elevation.
	 *
	 * @since 1.0.0
	 */
	public function elevation() {
		return 0;
	}

	/**
	 * Return an address.
	 *
	 * @return array $reverse microformats2 address elements in an array.
	 */
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
		return $this->address_to_mf( $json );
	}

	/**
	 * Convert address properties to mf2
	 *
	 * @param  array $json Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	protected function address_to_mf( $json ) {
		if ( ! array_key_exists( 'address', $json ) ) {
			return array();
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
					'region', 
					'county',
					'state',
					'state_district',
				)
			);
		}
		$street  = self::ifnot( 
			$address,
			array(
				'house_number',
				'house_name'
			)
		);
		if ( ! empty( $street ) ) {
			$street .= ' ';
		}

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
					'tourism',
					'place_of_worship',
					'building',
					'hotel',
					'address29',
					'address26',
					'emergency', 
					'historic', 
					'military', 
					'natural', 
					'landuse', 
					'place', 
					'railway', 
					'man_made', 
					'aerialway', 
					'boundary', 
					'amenity', 
					'aeroway', 
					'club', 
					'craft', 
					'leisure', 
					'office', 
					'mountain_pass', 
					'shop', 
					'bridge', 
					'tunnel', 
					'waterway'
				)
			),
			'street-address'   => $street,
			'extended-address' => self::ifnot(
				$address,
				array(
					'boro',
					'borough',
					'neighbourhood',
					'city_district',
					'district',
					'suburb',
					'subdivision',
					'allotments',
					'quarter'
				)
			),
			'locality'         => self::ifnot(
				$address,
				array(
					'hamlet',
					'village',
					'town',
					'city',
					'municipality', 
					'croft',
					'isolated_dwlling'
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
			'raw'              => $json,
		);
		if ( is_null( $addr['country-name'] ) ) {
			$addr['country-name'] = self::country_name( $addr['country-code'] );
		}
		if ( isset( $json['extratags'] ) ) {
			$addr['url']   = ifset( $json['extratags']['website'] );
			$addr['photo'] = ifset( $json['extratags']['image'] );
		}
		$addr                 = array_filter( $addr );
		if ( ! array_key_exists( 'display-name', $addr ) ) {
			$addr['display-name'] = $this->display_name( $addr );
		}
		$tz                   = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		return $addr;
	}


	/**
	 * Geocode address.
	 *
	 * @param  string $address String representation of location.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function geocode( $address ) {
		$args = array(
			'q'               => $address,
			'format'          => 'jsonv2',
			'extratags'       => '1',
			'addressdetails'  => '1',
			'namedetails'     => '1',
			'accept-language' => get_bloginfo( 'language' ),
		);
		$url  = 'https://nominatim.openstreetmap.org/search';

		$json = $this->fetch_json( $url, $args );

		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( wp_is_numeric_array( $json ) ) {
			$json = $json[0];
		}

		$address             = $json['address'];
		$return              = $this->address_to_mf( $address );
		$return['latitude']  = ifset( $json['lat'] );
		$return['longitude'] = ifset( $json['lon'] );
		if ( isset( $json['extratags'] ) ) {
			$return['url']   = ifset( $json['extratags']['website'] );
			$return['photo'] = ifset( $json['extratags']['image'] );
		}

		return array_filter( $return );
	}

}

register_sloc_provider( new Geo_Provider_Nominatim() );

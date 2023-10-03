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
		$this->name        = __( 'OpenStreetMap Nominatim', 'simple-location' );
		$this->slug        = 'nominatim';
		$this->url         = 'https://nominatim.org/';
		$this->description = __( 'Nominatim uses OpenStreetMap Data for geocoding/reverse geocoding. OSM offers a free service for infrequent use.', 'simple-location' );
		parent::__construct( $args );
	}

	/**
	 * Return an address.
	 *
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function reverse_lookup() {
		$args = array(
			'format'          => 'jsonv2',
			'extratags'       => '1',
			'addressdetails'  => '1',
			'namedetails'     => '1',
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

		$address      = $json['address'];
		$country_code = strtoupper( ifset( $address['country_code'] ) );

		if ( in_array( $country_code, array( 'US', 'FR' ) ) ) {
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

		if ( ! empty( $region ) ) {
			$region_code = self::region_code( $region, $country_code );
		}

		$number = self::ifnot(
			$address,
			array(
				'house_number',
				'house_name',
			)
		);

		$street = self::ifnot(
			$address,
			array(
				'road',
				'highway',
				'footway',
				'pedestrian',
			)
		);
		// Adjust position of house number/name based on country practice.
		if ( self::house_number( $country_code ) ) {
			$street_address = $number . ' ' . $street;
		} else {
			$street_address = $street . ' ' . $number;
		}
		$street_address = trim( $street_address );

		$addr = array(
			'name'             => self::ifnot(
				$address,
				array(
					'attraction',
					'library',
					'parking',
					'tourism',
					'place_of_worship',
					'building',
					'hotel',
					'historic',
					'military',
					'office',
					'club',
					'craft',
					'leisure',
					'shop',
					'address29',
					'address26',
					'emergency',
					'natural',
					'landuse',
					'place',
					'railway',
					'man_made',
					'aerialway',
					'boundary',
					'amenity',
					'aeroway',
					'mountain_pass',
					'bridge',
					'tunnel',
					'waterway',
				)
			),
			'street-number'    => $number,
			'street'           => $street,
			'street-address'   => $street_address,
			'extended-address' => self::ifnot(
				$address,
				array(
					'boro',
					'borough',
					'neighbourhood',
					'neighborhood',
					'city_district',
					'district',
					'subdivision',
					'allotments',
					'quarter',
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
					'isolated_dwelling',
					'suburb',
				)
			),
			'region'           => $region,
			'region-code'      => array_key_exists( 'state_code', $address ) ? strtoupper( $address['state_code'] ) : $region_code,
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
			'country-code'     => $country_code,

			'latitude'         => $this->latitude,
			'longitude'        => $this->longitude,
			'altitude'         => $this->altitude,
			'raw'              => $json,
		);
		if ( is_null( $addr['country-name'] ) ) {
			$addr['country-name'] = self::country_name( $addr['country-code'] );
		}
		if ( isset( $json['extratags'] ) ) {
			$addr['url']   = ifset( $json['extratags']['website'] );
			$addr['photo'] = ifset( $json['extratags']['image'] );
		}
		$addr = array_filter( $addr );
		if ( ! array_key_exists( 'display-name', $addr ) ) {
			$addr['display-name'] = $this->display_name( $addr );
		}
		$tz = $this->timezone();
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

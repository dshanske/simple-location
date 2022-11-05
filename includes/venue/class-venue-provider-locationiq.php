<?php
/**
 * Venue Provider.
 *
 * @package Simple_Location
 */

/**
 * Venue Search using LocationIQ API.
 *
 * @since 5.0.0
 */
class Venue_Provider_LocationIQ extends Venue_Provider {

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
		$this->name        = __( 'LocationIQ', 'simple-location' );
		$this->slug        = 'locationiq';
		$this->url         = 'https://locationiq.com/';
		$this->description = __( 'LocationIQ offers Geocoding and Static maps, with a free tier of 5000 requests/day. Sign up for an API key', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_locationiq_api' );
		}

		parent::__construct( $args );
	}

	/**
	 * Return an address.
	 *
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function reverse_lookup() {
		$args = array(
			'key' => $this->api,
			'lat' => $this->latitude,
			'lon' => $this->longitude,
		);
		$url  = 'https://us1.locationiq.com/v1/nearby';

		$json = $this->fetch_json( $url, $args );

		if ( is_wp_error( $json ) ) {
			return $json;
		}

		$return = array();

		foreach ( $json as $item ) {
			$return[] = $this->address_to_hcard( $item );
		}

		return array( 'items' => $return );
	}

	/**
	 * Convert address properties to hcard
	 *
	 * @param  array $json Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	protected function address_to_hcard( $json ) {
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
			'type'             => 'card',
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
			'category'         => self::ifnot(
				$json,
				array(
					'type',
					'category',
				)
			),

			'latitude'         => $this->latitude,
			'longitude'        => $this->longitude,
			'altitude'         => $this->altitude,
			'raw'              => $json,
		);
		if ( is_null( $addr['country-name'] ) ) {
			$addr['country-name'] = self::country_name( $addr['country-code'] );
		}
		if ( isset( $json['extratags'] ) ) {
			$addr['url'] = ifset( $json['extratags']['website'] );
			if ( empty( $addr['url'] ) && isset( $json['extratags']['wikipedia'] ) ) {
				$wiki        = explode( ':', $json['extratags']['wikipedia'] );
				$addr['url'] = 'https://' . $wiki[0] . '.wikipedia.org/wiki/' . str_replace( ' ', '_', $wiki[1] );
			}
			$addr['photo'] = ifset( $json['extratags']['image'] );
			$addr['tel']   = ifset( $json['extratags']['phone'] );
		}
		if ( isset( $json['boundingbox'] ) ) {
			$addr['boundingbox'] = $json['boundingbox'];
		}
		$addr = array_filter( $addr );
		$tz   = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		return $addr;
	}
}

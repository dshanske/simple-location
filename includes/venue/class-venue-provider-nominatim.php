<?php
/**
 * Venue Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Venue Search using Nominatim API.
 *
 * @since 1.0.0
 */
class Venue_Provider_Nominatim extends Venue_Provider {
	use Sloc_API_Nominatim;

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
	 * @since 6.0.0 An error response is an error, or an empty list when there is nothing at the point.
	 * @since 6.1.0 Asks the Nominatim server setting's server (#233).
	 *
	 * @return array|WP_Error $reverse microformats2 address elements in an array.
	 */
	public function reverse_lookup() {
		$args = array(
			'format'          => 'jsonv2',
			'extratags'       => '1',
			'addressdetails'  => '1',
			'namedetails'     => '1',
			'lat'             => $this->latitude,
			'lon'             => $this->longitude,
			'zoom'            => 18,
			'accept-language' => get_bloginfo( 'language' ),
		);
		$json = $this->nominatim_json( 'reverse', $args );

		if ( is_wp_error( $json ) ) {
			return $json;
		}

		// Errors come back as an object with a 200 status; "Unable to geocode" means there is nothing at the point.
		if ( isset( $json['error'] ) ) {
			$message = is_string( $json['error'] ) ? $json['error'] : ( $json['error']['message'] ?? '' );
			if ( 'Unable to geocode' === $message ) {
				return array( 'items' => array() );
			}
			return new WP_Error( 'unable_to_retrieve', $message ? $message : __( 'Unable to Retrieve', 'simple-location' ) );
		}

		$return = array();
		if ( wp_is_numeric_array( $json ) ) {
			foreach ( $json as $item ) {
				$return[] = $this->address_to_hcard( $item );
			}
			return array( 'items' => $return );
		} else {
			return array(
				'items' => array( $this->address_to_hcard( $json ) ),
			);
		}
	}

	/**
	 * Convert address properties to hcard
	 *
	 * @since 6.0.0 Handles Wikipedia tags without a language prefix.
	 * @since 6.1.0 Uses the place's own coordinates (#720), and adds the shared venue category and a location icon (#707).
	 *
	 * @param  array $json Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	protected function address_to_hcard( $json ) {
		if ( ! array_key_exists( 'address', $json ) ) {
			return array();
		}

		$address      = $json['address'];
		$country_code = strtoupper( $address['country_code'] ?? '' );

		if ( in_array( $country_code, array( 'US', 'FR' ), true ) ) {
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

		// Some places, such as Singapore, have no region.
		$region_code = null;
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

			// The place's own coordinates, not the search point's.
			'latitude'         => isset( $json['lat'] ) && is_numeric( $json['lat'] ) ? (float) $json['lat'] : null,
			'longitude'        => isset( $json['lon'] ) && is_numeric( $json['lon'] ) ? (float) $json['lon'] : null,
			'uid'              => self::osm_uid( $json['osm_type'] ?? '', $json['osm_id'] ?? '' ),
			'raw'              => $json,
		);
		if ( is_null( $addr['country-name'] ) ) {
			$addr['country-name'] = self::country_name( $addr['country-code'] );
		}
		if ( isset( $json['extratags'] ) ) {
			$addr['url'] = $json['extratags']['website'] ?? null;
			if ( empty( $addr['url'] ) && isset( $json['extratags']['wikipedia'] ) ) {
				$addr['url'] = self::wikipedia_url( $json['extratags']['wikipedia'] );
			}
			$addr['photo'] = $json['extratags']['image'] ?? null;
			$addr['tel']   = $json['extratags']['phone'] ?? null;
		}
		if ( isset( $json['boundingbox'] ) ) {
			$addr['boundingbox'] = $json['boundingbox'];
		}
		$addr = array_filter( $addr );
		$addr = self::add_category( $addr, self::osm_category( $json['category'] ?? $json['class'] ?? '', $json['type'] ?? '' ) );
		$tz   = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		return $addr;
	}
}

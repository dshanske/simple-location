<?php
/**
 * Venue Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Venues from TomTom's Search API nearby search, https://docs.tomtom.com/search-api/documentation/search-service/nearby-search.
 *
 * @since 6.1.0
 */
class Venue_Provider_TomTom extends Venue_Provider {
	use Sloc_API_TomTom;

	/**
	 * TomTom classification codes and their shared categories and icons.
	 *
	 * @since 6.1.0
	 * @var array[]
	 */
	const CATEGORY_MAP = array(
		'RESTAURANT'                     => array( 'food', '' ),
		'CAFE_PUB'                       => array( 'food', '' ),
		'NIGHTLIFE'                      => array( 'drink', '' ),
		'HOTEL_MOTEL'                    => array( 'lodging', 'fa-suitcase' ),
		'HOLIDAY_RENTAL'                 => array( 'lodging', 'fa-suitcase' ),
		'CAMPING_GROUND'                 => array( 'outdoors', '' ),
		'SHOP'                           => array( 'shop', '' ),
		'SHOPPING_CENTER'                => array( 'shop', '' ),
		'MARKET'                         => array( 'shop', '' ),
		'AIRPORT'                        => array( 'transport', 'fa-plane' ),
		'RAILWAY_STATION'                => array( 'transport', 'fa-train' ),
		'PUBLIC_TRANSPORT_STOP'          => array( 'transport', 'fa-bus' ),
		'FERRY_TERMINAL'                 => array( 'transport', 'fa-ship' ),
		'PETROL_STATION'                 => array( 'transport', 'fa-car' ),
		'RENT_A_CAR_FACILITY'            => array( 'transport', 'fa-car' ),
		'PARKING_GARAGE'                 => array( 'transport', 'fa-car' ),
		'OPEN_PARKING_AREA'              => array( 'transport', 'fa-car' ),
		'MUSEUM'                         => array( 'culture', '' ),
		'IMPORTANT_TOURIST_ATTRACTION'   => array( 'culture', '' ),
		'TOURIST_ATTRACTION'             => array( 'culture', '' ),
		'LIBRARY'                        => array( 'culture', '' ),
		'THEATER'                        => array( 'entertainment', '' ),
		'CINEMA'                         => array( 'entertainment', '' ),
		'CASINO'                         => array( 'entertainment', '' ),
		'AMUSEMENT_PARK'                 => array( 'entertainment', '' ),
		'ZOOS_ARBORETA_BOTANICAL_GARDEN' => array( 'entertainment', '' ),
		'PARK_RECREATION_AREA'           => array( 'outdoors', '' ),
		'BEACH'                          => array( 'outdoors', '' ),
		'SPORTS_CENTER'                  => array( 'sport', '' ),
		'STADIUM'                        => array( 'sport', '' ),
		'GOLF_COURSE'                    => array( 'sport', '' ),
		'HOSPITAL_POLYCLINIC'            => array( 'health', '' ),
		'DOCTOR'                         => array( 'health', '' ),
		'DENTIST'                        => array( 'health', '' ),
		'PHARMACY'                       => array( 'health', '' ),
		'SCHOOL'                         => array( 'education', '' ),
		'COLLEGE_UNIVERSITY'             => array( 'education', '' ),
		'PLACE_OF_WORSHIP'               => array( 'worship', '' ),
		'GOVERNMENT_OFFICE'              => array( 'government', '' ),
		'POLICE_STATION'                 => array( 'government', '' ),
		'POST_OFFICE'                    => array( 'government', '' ),
		'EMBASSY'                        => array( 'government', '' ),
		'COMPANY'                        => array( 'office', '' ),
		'BANK'                           => array( 'office', '' ),
	);

	/**
	 * Constructor.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args Arguments. See Venue_Provider::__construct(). The key defaults to the sloc_tomtom_api setting.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'TomTom', 'simple-location' );
		$this->slug        = 'tomtom';
		$this->url         = 'https://developer.tomtom.com/';
		$this->description = __( 'Finds places near you with TomTom\'s Search API, using your TomTom API key.', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_tomtom_api' );
		}
		parent::__construct( $args );
	}

	/**
	 * Returns the places near the location.
	 *
	 * @since 6.1.0
	 *
	 * @return array|WP_Error h-cards under 'items', or an error.
	 */
	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for TomTom', 'simple-location' ) );
		}
		$json = $this->fetch_json(
			'https://api.tomtom.com/search/2/nearbySearch/.json',
			array(
				'key'      => $this->api,
				'lat'      => $this->latitude,
				'lon'      => $this->longitude,
				'radius'   => $this->radius,
				'limit'    => min( 100, $this->limit ),
				'language' => get_bloginfo( 'language' ),
			)
		);
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$items = array();
		foreach ( is_array( $json['results'] ?? null ) ? $json['results'] : array() as $result ) {
			if ( is_array( $result ) ) {
				$items[] = $this->result_to_hcard( $result );
			}
		}
		return array( 'items' => array_values( array_filter( $items ) ) );
	}

	/**
	 * Returns the shared category and location icon of a place's TomTom classifications.
	 *
	 * @since 6.1.0
	 *
	 * @param array $classifications Classifications, each with a 'code', such as RESTAURANT.
	 * @return array Array with 'category', a key of Venue_Provider::categories(), and 'icon'.
	 */
	public static function tomtom_category( $classifications ) {
		foreach ( (array) $classifications as $classification ) {
			$code = is_array( $classification ) ? ( $classification['code'] ?? '' ) : '';
			if ( is_string( $code ) && isset( self::CATEGORY_MAP[ $code ] ) ) {
				return array(
					'category' => self::CATEGORY_MAP[ $code ][0],
					'icon'     => self::CATEGORY_MAP[ $code ][1],
				);
			}
		}
		return array(
			'category' => 'other',
			'icon'     => '',
		);
	}

	/**
	 * Converts a result to an h-card.
	 *
	 * @since 6.1.0
	 *
	 * @param array $result Result.
	 * @return array Venue as an h-card, or an empty array for a result that isn't a named place with a location.
	 */
	protected function result_to_hcard( $result ) {
		$poi = is_array( $result['poi'] ?? null ) ? $result['poi'] : array();
		if ( empty( $poi['name'] ) || ! is_numeric( $result['position']['lat'] ?? null ) || ! is_numeric( $result['position']['lon'] ?? null ) ) {
			return array();
		}
		$address = is_array( $result['address'] ?? null ) ? $result['address'] : array();
		$country = strtoupper( (string) ( $address['countryCode'] ?? '' ) );
		$number  = $address['streetNumber'] ?? '';
		$street  = $address['streetName'] ?? '';
		$region  = $address['countrySubdivisionName'] ?? $address['countrySubdivision'] ?? null;
		// The subdivision is a code, such as PA, when TomTom also gives its name.
		$code  = isset( $address['countrySubdivisionName'] ) ? ( $address['countrySubdivision'] ?? null ) : null;
		$names = array();
		foreach ( (array) ( $poi['classifications'] ?? array() ) as $classification ) {
			foreach ( (array) ( $classification['names'] ?? array() ) as $name ) {
				if ( isset( $name['name'] ) && is_string( $name['name'] ) ) {
					$names[] = $name['name'];
				}
			}
		}
		$card = array(
			'type'             => 'card',
			'name'             => $poi['name'],
			'label'            => $address['freeformAddress'] ?? null,
			'street-number'    => $number,
			'street'           => $street,
			'street-address'   => trim( self::house_number( $country ) ? $number . ' ' . $street : $street . ' ' . $number ),
			'extended-address' => $address['municipalitySubdivision'] ?? $address['neighbourhood'] ?? null,
			'locality'         => $address['municipality'] ?? null,
			'region'           => $region,
			'region-code'      => $code,
			'postal-code'      => $address['postalCode'] ?? null,
			'country-name'     => $address['country'] ?? null,
			'country-code'     => '' === $country ? null : $country,
			'url'              => isset( $poi['url'] ) ? self::with_scheme( $poi['url'] ) : null,
			'tel'              => $poi['phone'] ?? null,
			'category'         => $names,
			'latitude'         => (float) $result['position']['lat'],
			'longitude'        => (float) $result['position']['lon'],
		);
		if ( WP_DEBUG ) {
			$card['raw'] = $result;
		}
		$card = self::add_category( array_filter( $card ), self::tomtom_category( $poi['classifications'] ?? array() ) );
		$tz   = $this->timezone();
		if ( $tz ) {
			$card = array_merge( $card, $tz );
		}
		return $card;
	}

	/**
	 * Adds https:// to a website TomTom gives without a scheme, such as www.example.com.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $url Website.
	 * @return string|null URL.
	 */
	protected static function with_scheme( $url ) {
		if ( ! is_string( $url ) || '' === trim( $url ) ) {
			return null;
		}
		$url = trim( $url );
		return preg_match( '#^https?://#i', $url ) ? $url : 'https://' . $url;
	}
}

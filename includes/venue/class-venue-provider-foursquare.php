<?php
/**
 * Venue Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Venues from the Foursquare Places API's place search, https://docs.foursquare.com/fsq-developers-places/reference/place-search.
 *
 * Foursquare's terms allow keeping a place's ID, but not its other details, so only references are kept.
 *
 * @since 6.1.0
 */
class Venue_Provider_Foursquare extends Venue_Provider {

	/**
	 * Places API version sent with each request.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const API_VERSION = '2025-06-17';

	/**
	 * Fields asked for: only Pro fields, which cost less than Premium ones.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const FIELDS = array( 'fsq_place_id', 'name', 'latitude', 'longitude', 'location', 'categories', 'website', 'tel', 'distance' );

	/**
	 * Words in Foursquare category names and their shared categories and icons, checked in order.
	 *
	 * Foursquare's Pro fields name each category, such as Coffee Shop, without its place in the hierarchy.
	 *
	 * @since 6.1.0
	 * @var array[]
	 */
	const CATEGORY_MAP = array(
		'airport'     => array( 'transport', 'fa-plane' ),
		'bus'         => array( 'transport', 'fa-bus' ),
		'ferry'       => array( 'transport', 'fa-ship' ),
		'metro'       => array( 'transport', 'fa-subway' ),
		'subway'      => array( 'transport', 'fa-subway' ),
		'train'       => array( 'transport', 'fa-train' ),
		'rail'        => array( 'transport', 'fa-train' ),
		'parking'     => array( 'transport', 'fa-car' ),
		'gas station' => array( 'transport', 'fa-car' ),
		'hotel'       => array( 'lodging', 'fa-suitcase' ),
		'hostel'      => array( 'lodging', 'fa-suitcase' ),
		'motel'       => array( 'lodging', 'fa-suitcase' ),
		'inn'         => array( 'lodging', 'fa-suitcase' ),
		'bed and'     => array( 'lodging', 'fa-suitcase' ),
		'coffee'      => array( 'food', '' ),
		'café'        => array( 'food', '' ),
		'cafe'        => array( 'food', '' ),
		'restaurant'  => array( 'food', '' ),
		'bakery'      => array( 'food', '' ),
		'diner'       => array( 'food', '' ),
		'pizza'       => array( 'food', '' ),
		'food'        => array( 'food', '' ),
		'bar'         => array( 'drink', '' ),
		'pub'         => array( 'drink', '' ),
		'brewery'     => array( 'drink', '' ),
		'winery'      => array( 'drink', '' ),
		'nightclub'   => array( 'drink', '' ),
		'museum'      => array( 'culture', '' ),
		'gallery'     => array( 'culture', '' ),
		'library'     => array( 'culture', '' ),
		'monument'    => array( 'culture', '' ),
		'historic'    => array( 'culture', '' ),
		'theater'     => array( 'entertainment', '' ),
		'cinema'      => array( 'entertainment', '' ),
		'movie'       => array( 'entertainment', '' ),
		'music venue' => array( 'entertainment', '' ),
		'zoo'         => array( 'entertainment', '' ),
		'casino'      => array( 'entertainment', '' ),
		'park'        => array( 'outdoors', '' ),
		'beach'       => array( 'outdoors', '' ),
		'trail'       => array( 'outdoors', '' ),
		'garden'      => array( 'outdoors', '' ),
		'stadium'     => array( 'sport', '' ),
		'gym'         => array( 'sport', '' ),
		'sport'       => array( 'sport', '' ),
		'hospital'    => array( 'health', '' ),
		'doctor'      => array( 'health', '' ),
		'dentist'     => array( 'health', '' ),
		'pharmacy'    => array( 'health', '' ),
		'clinic'      => array( 'health', '' ),
		'school'      => array( 'education', '' ),
		'college'     => array( 'education', '' ),
		'university'  => array( 'education', '' ),
		'church'      => array( 'worship', '' ),
		'mosque'      => array( 'worship', '' ),
		'synagogue'   => array( 'worship', '' ),
		'temple'      => array( 'worship', '' ),
		'city hall'   => array( 'government', '' ),
		'post office' => array( 'government', '' ),
		'police'      => array( 'government', '' ),
		'courthouse'  => array( 'government', '' ),
		'government'  => array( 'government', '' ),
		'bank'        => array( 'office', '' ),
		'office'      => array( 'office', '' ),
		'coworking'   => array( 'office', '' ),
		'store'       => array( 'shop', '' ),
		'shop'        => array( 'shop', '' ),
		'market'      => array( 'shop', '' ),
		'mall'        => array( 'shop', '' ),
		'boutique'    => array( 'shop', '' ),
	);

	/**
	 * Constructor.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args Arguments. See Venue_Provider::__construct(). The key defaults to the sloc_foursquare_api setting.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Foursquare', 'simple-location' );
		$this->slug        = 'foursquare';
		$this->url         = 'https://foursquare.com/developers/';
		$this->description = __( 'Finds places near you with the Foursquare Places API, using your Foursquare service API key.', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_foursquare_api' );
		}
		parent::__construct( $args );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 6.1.0
	 */
	public static function init() {
		self::register_settings_api( __( 'Foursquare', 'simple-location' ), 'sloc_foursquare_api' );
	}

	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 6.1.0
	 */
	public static function admin_init() {
		self::add_settings_parameter( __( 'Foursquare', 'simple-location' ), 'sloc_foursquare_api' );
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
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Foursquare', 'simple-location' ) );
		}
		$json = $this->fetch_json(
			'https://places-api.foursquare.com/places/search',
			array(
				'll'     => $this->latitude . ',' . $this->longitude,
				'radius' => $this->radius,
				'limit'  => $this->limit,
				'sort'   => 'DISTANCE',
				'fields' => implode( ',', self::FIELDS ),
			),
			array(
				'Authorization'        => 'Bearer ' . $this->api,
				'X-Places-Api-Version' => self::API_VERSION,
			)
		);
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$items = array();
		foreach ( is_array( $json['results'] ?? null ) ? $json['results'] : array() as $place ) {
			if ( is_array( $place ) ) {
				$items[] = $this->place_to_hcard( $place );
			}
		}
		return array( 'items' => array_values( array_filter( $items ) ) );
	}

	/**
	 * Returns the shared category and location icon of a place's Foursquare categories, from the first one that fits.
	 *
	 * @since 6.1.0
	 *
	 * @param string[] $names Category names, such as Coffee Shop.
	 * @return array Array with 'category', a key of Venue_Provider::categories(), and 'icon'.
	 */
	public static function foursquare_category( $names ) {
		foreach ( (array) $names as $name ) {
			if ( ! is_string( $name ) ) {
				continue;
			}
			$name = ' ' . strtolower( $name ) . ' ';
			foreach ( self::CATEGORY_MAP as $word => $match ) {
				// Whole words, so Bar isn't found in Barbershop.
				if ( preg_match( '/[^\p{L}]' . preg_quote( $word, '/' ) . 's?[^\p{L}]/u', $name ) ) {
					return array(
						'category' => $match[0],
						'icon'     => $match[1],
					);
				}
			}
		}
		return array(
			'category' => 'other',
			'icon'     => '',
		);
	}

	/**
	 * Converts a place to an h-card.
	 *
	 * @since 6.1.0
	 *
	 * @param array $place Place.
	 * @return array Venue as an h-card, or an empty array for a place without a name or location.
	 */
	protected function place_to_hcard( $place ) {
		if ( empty( $place['name'] ) || ! is_numeric( $place['latitude'] ?? null ) || ! is_numeric( $place['longitude'] ?? null ) ) {
			return array();
		}
		$location = is_array( $place['location'] ?? null ) ? $place['location'] : array();
		$country  = strtoupper( (string) ( $location['country'] ?? '' ) );
		$names    = array();
		foreach ( (array) ( $place['categories'] ?? array() ) as $category ) {
			if ( is_array( $category ) && isset( $category['name'] ) && is_string( $category['name'] ) ) {
				$names[] = $category['name'];
			}
		}
		// A region such as PA is a code; its name comes from the country's subdivisions.
		$region = $location['region'] ?? null;
		$code   = null;
		if ( is_string( $region ) && '' !== $country && preg_match( '/^[A-Z0-9]{1,3}$/', $region ) ) {
			$code = $region;
			$name = self::region_name( $code, $country );
			if ( is_string( $name ) && '' !== $name ) {
				$region = $name;
			}
		}
		$id   = $place['fsq_place_id'] ?? null;
		$card = array(
			'type'           => 'card',
			'name'           => $place['name'],
			'street-address' => $location['address'] ?? null,
			'locality'       => $location['locality'] ?? null,
			'region'         => $region,
			'region-code'    => $code,
			'postal-code'    => $location['postcode'] ?? null,
			'country-code'   => '' === $country ? null : $country,
			'country-name'   => '' === $country ? null : self::country_name( $country ),
			'url'            => $place['website'] ?? null,
			'tel'            => $place['tel'] ?? null,
			'category'       => $names,
			'latitude'       => (float) $place['latitude'],
			'longitude'      => (float) $place['longitude'],
			// The place's Foursquare page, the URL check-ins from Swarm name it by.
			'uid'            => ( is_string( $id ) && preg_match( '/^[0-9a-z]+$/i', $id ) ) ? 'https://foursquare.com/v/' . $id : null,
		);
		if ( WP_DEBUG ) {
			$card['raw'] = $place;
		}
		$card = self::add_category( array_filter( $card ), self::foursquare_category( $names ) );
		$tz   = $this->timezone();
		if ( $tz ) {
			$card = array_merge( $card, $tz );
		}
		return $card;
	}
}

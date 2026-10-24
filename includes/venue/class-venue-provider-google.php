<?php
/**
 * Venue Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Venue using Google API.
 *
 * @since 1.0.0
 */
class Venue_Provider_Google extends Venue_Provider {
	use Sloc_API_Google;

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
		$this->name        = __( 'Google', 'simple-location' );
		$this->url         = 'https://developers.google.com/maps/';
		$this->description = __( 'Google Maps Platform API key is required, however Google offers a $200 per month credit, which is the equivalent of 28,000 queries. Click Get Started. Make sure to enable the Geocoding API. Follow the tutorial', 'simple-location' );
		$this->slug        = 'google';
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_google_api' );
		}

		parent::__construct( $args );
	}

	/**
	 * Fields asked for: only those billed as Nearby Search Pro, not the Enterprise fields such as the website.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const FIELDS = array( 'id', 'displayName', 'formattedAddress', 'addressComponents', 'location', 'types', 'primaryType', 'plusCode', 'viewport', 'googleMapsUri' );

	/**
	 * Returns the establishments near the location.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Returns the venues under 'items', like the other venue providers, and an empty list when there are none.
	 * @since 6.1.0 Uses Places API (New) Nearby Search, nearest first, within the venue search radius (#703).
	 *
	 * @return array|WP_Error h-cards under 'items', or an error.
	 */
	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Google', 'simple-location' ) );
		}
		/**
		 * Filters the place fields asked of Google. Fields beyond Nearby Search Pro, such as websiteUri, cost more.
		 *
		 * @since 6.1.0
		 *
		 * @param string[] $fields Place fields, such as 'displayName'.
		 */
		$fields = (array) apply_filters( 'sloc_google_venue_fields', self::FIELDS );
		$json   = $this->post_json(
			'https://places.googleapis.com/v1/places:searchNearby',
			array(
				'locationRestriction' => array(
					'circle' => array(
						'center' => array(
							'latitude'  => (float) $this->latitude,
							'longitude' => (float) $this->longitude,
						),
						'radius' => (float) $this->radius,
					),
				),
				'maxResultCount'      => min( 20, $this->limit ),
				'rankPreference'      => 'DISTANCE',
				'languageCode'        => get_bloginfo( 'language' ),
			),
			array(
				'X-Goog-Api-Key'   => $this->api,
				'X-Goog-FieldMask' => implode( ',', array_map( fn( $field ) => 'places.' . $field, $fields ) ),
			)
		);
		// No places nearby is an empty object.
		if ( is_wp_error( $json ) && 'not_json_response' === $json->get_error_code() && in_array( trim( (string) ( $json->get_error_data()['body'] ?? '' ) ), array( '{}', '[]' ), true ) ) {
			return array( 'items' => array() );
		}
		if ( is_wp_error( $json ) ) {
			return self::api_error( $json );
		}

		$items = array();
		foreach ( is_array( $json['places'] ?? null ) ? $json['places'] : array() as $place ) {
			if ( is_array( $place ) ) {
				$items[] = $this->address_to_hcard( $place );
			}
		}
		return array( 'items' => array_values( array_filter( $items ) ) );
	}

	/**
	 * Returns Google's explanation of a refused request, when the response has one.
	 *
	 * Google answers with an error object, such as PERMISSION_DENIED when the Places API (New) isn't enabled.
	 *
	 * @since 6.1.0
	 *
	 * @param WP_Error $error Error from post_json().
	 * @return WP_Error Error with Google's message, and its status, such as PERMISSION_DENIED, as 'reason' in the data;
	 *                  or the error unchanged.
	 */
	protected static function api_error( $error ) {
		$data = $error->get_error_data();
		$body = is_array( $data ) ? json_decode( $data['body'] ?? '', true ) : null;
		if ( is_array( $body ) && isset( $body['error']['status'], $body['error']['message'] ) && is_string( $body['error']['message'] ) ) {
			return new WP_Error(
				$error->get_error_code(),
				$body['error']['message'],
				array(
					'status' => $data['status'] ?? 0,
					'reason' => (string) $body['error']['status'],
				)
			);
		}
		return $error;
	}

	/**
	 * Returns the shared category and location icon of a place's Google types, from the most specific type.
	 *
	 * @since 6.1.0
	 *
	 * @param string[] $types Google place types, most specific first.
	 * @return array Array with 'category', a key of Venue_Provider::categories(), and 'icon'.
	 */
	public static function google_category( $types ) {
		$icons      = array(
			'airport'            => 'fa-plane',
			'train_station'      => 'fa-train',
			'subway_station'     => 'fa-subway',
			'light_rail_station' => 'fa-tram',
			'bus_station'        => 'fa-bus',
			'taxi_stand'         => 'fa-taxi',
			'car_rental'         => 'fa-car',
			'gas_station'        => 'fa-car',
			'parking'            => 'fa-car',
			'ferry_terminal'     => 'fa-ship',
		);
		$categories = array(
			'food'          => array( 'restaurant', 'meal_takeaway', 'meal_delivery', 'bakery', 'cafe', 'coffee_shop', 'fast_food_restaurant' ),
			'drink'         => array( 'bar', 'pub', 'wine_bar' ),
			'lodging'       => array( 'lodging', 'hotel', 'motel', 'hostel', 'bed_and_breakfast', 'campground' ),
			'transport'     => array_merge( array_keys( $icons ), array( 'transit_station' ) ),
			'entertainment' => array( 'night_club', 'movie_theater', 'amusement_park', 'bowling_alley', 'casino', 'zoo', 'aquarium' ),
			'culture'       => array( 'museum', 'art_gallery', 'library', 'tourist_attraction', 'performing_arts_theater', 'historical_landmark' ),
			'outdoors'      => array( 'park', 'natural_feature', 'hiking_area', 'beach' ),
			'sport'         => array( 'gym', 'stadium', 'sports_complex', 'golf_course', 'swimming_pool' ),
			'health'        => array( 'hospital', 'doctor', 'dentist', 'pharmacy', 'physiotherapist', 'drugstore' ),
			'education'     => array( 'school', 'primary_school', 'secondary_school', 'university' ),
			'worship'       => array( 'place_of_worship', 'church', 'mosque', 'synagogue', 'hindu_temple' ),
			'government'    => array( 'city_hall', 'courthouse', 'police', 'fire_station', 'post_office', 'local_government_office', 'embassy' ),
			'office'        => array( 'accounting', 'lawyer', 'insurance_agency', 'real_estate_agency', 'bank', 'corporate_office' ),
			'shop'          => array( 'shopping_mall', 'supermarket', 'grocery_store', 'convenience_store', 'liquor_store', 'florist' ),
		);
		foreach ( (array) $types as $type ) {
			foreach ( $categories as $category => $list ) {
				if ( in_array( $type, $list, true ) ) {
					return array(
						'category' => $category,
						'icon'     => $icons[ $type ] ?? ( 'lodging' === $category ? 'fa-suitcase' : '' ),
					);
				}
			}
			// Google names every kind of shop with _store.
			if ( is_string( $type ) && '_store' === substr( $type, -6 ) ) {
				return array(
					'category' => 'shop',
					'icon'     => '',
				);
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
	 * @since 1.0.0
	 * @since 6.1.0 Reads Places API (New) places, with their address (#703).
	 *
	 * @param array $place Place.
	 * @return array Venue as an h-card, or an empty array for a place without a name.
	 */
	private function address_to_hcard( $place ) {
		$name = $place['displayName']['text'] ?? null;
		if ( ! is_string( $name ) || '' === $name ) {
			return array();
		}
		$card = array(
			'type'      => 'card',
			'name'      => $name,
			'label'     => $place['formattedAddress'] ?? null,
			'plus-code' => $place['plusCode']['globalCode'] ?? null,
			'latitude'  => is_numeric( $place['location']['latitude'] ?? null ) ? (float) $place['location']['latitude'] : null,
			'longitude' => is_numeric( $place['location']['longitude'] ?? null ) ? (float) $place['location']['longitude'] : null,
			'category'  => is_array( $place['types'] ?? null ) ? $place['types'] : null,
			'url'       => $place['websiteUri'] ?? null,
			'tel'       => $place['internationalPhoneNumber'] ?? null,
		);
		if ( isset( $place['viewport'] ) ) {
			$card['boundingbox'] = $place['viewport'];
		}
		$card = array_merge( $card, self::address_components( $place['addressComponents'] ?? array() ) );
		if ( WP_DEBUG ) {
			$card['raw'] = $place;
		}
		$types = $card['category'] ?? array();
		// The primary type is the most specific.
		if ( ! empty( $place['primaryType'] ) && is_string( $place['primaryType'] ) ) {
			array_unshift( $types, $place['primaryType'] );
		}
		$card = self::add_category( array_filter( $card ), self::google_category( $types ) );

		$tz = $this->timezone();
		if ( $tz ) {
			$card = array_merge( $card, $tz );
		}
		return $card;
	}

	/**
	 * Converts a place's address components to h-card address properties.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $components Address components, each with longText, shortText and types.
	 * @return array Address properties.
	 */
	protected static function address_components( $components ) {
		$parts = array();
		foreach ( is_array( $components ) ? $components : array() as $component ) {
			foreach ( (array) ( $component['types'] ?? array() ) as $type ) {
				if ( ! isset( $parts[ $type ] ) ) {
					$parts[ $type ] = $component;
				}
			}
		}
		$long    = fn( $type ) => $parts[ $type ]['longText'] ?? null;
		$short   = fn( $type ) => $parts[ $type ]['shortText'] ?? null;
		$number  = $long( 'street_number' );
		$street  = $long( 'route' );
		$country = strtoupper( (string) $short( 'country' ) );
		return array(
			'street-number'    => $number,
			'street'           => $street,
			'street-address'   => trim( self::house_number( $country ) ? $number . ' ' . $street : $street . ' ' . $number ),
			'extended-address' => $long( 'neighborhood' ) ?? $long( 'sublocality' ),
			'locality'         => $long( 'locality' ) ?? $long( 'postal_town' ),
			'region'           => $long( 'administrative_area_level_1' ),
			'region-code'      => $short( 'administrative_area_level_1' ),
			'postal-code'      => $long( 'postal_code' ),
			'country-name'     => $long( 'country' ),
			'country-code'     => '' === $country ? null : $country,
		);
	}
}

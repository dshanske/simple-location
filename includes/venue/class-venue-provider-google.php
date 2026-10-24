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
	 * Returns the establishments near the location.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Returns the venues under 'items', like the other venue providers, and an empty list when there are none.
	 * @since 6.1.0 Searches the venue search radius, not 200 meters.
	 *
	 * @return array|WP_Error h-cards under 'items', or an error.
	 */
	public function reverse_lookup() {
		if ( empty( $this->api ) ) {
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Google', 'simple-location' ) );
		}
		$args = array(
			'location' => $this->latitude . ',' . $this->longitude,
			'radius'   => $this->radius,
			'type'     => 'establishment',
			'language' => get_bloginfo( 'language' ),
			'key'      => $this->api,
		);
		$url  = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json?';
		$json = $this->fetch_json( $url, $args );
		if ( is_wp_error( $json ) ) {
			return $json;
		}

		// ZERO_RESULTS means there are no establishments within the radius.
		if ( isset( $json['status'] ) && is_string( $json['status'] ) && ! in_array( $json['status'], array( 'OK', 'ZERO_RESULTS' ), true ) ) {
			return new WP_Error( $json['status'], $json['error_message'] ?? __( 'Error Returning Results from Google', 'simple-location' ), array( $json ) );
		}

		$items = array();
		foreach ( $json['results'] ?? array() as $result ) {
			$items[] = $this->address_to_hcard( $result );
		}

		return array( 'items' => $items );
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
	 * Convert address properties to mf2
	 *
	 * @param  array $data Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	private function address_to_hcard( $data ) {
		$card = array(
			'type' => 'card',
		);
		if ( WP_DEBUG ) {
			$card['raw'] = $data;
		}

		$card['name']      = $data['name'] ?? null;
		$card['plus-code'] = $data['plus_code']['global_code'] ?? null;
		if ( isset( $data['geometry'] ) ) {
			$card['latitude']    = $data['geometry']['location']['lat'] ?? null;
			$card['longitude']   = $data['geometry']['location']['lng'] ?? null;
			$card['boundingbox'] = $data['geometry']['viewport'] ?? null;
		}
		$card['category'] = $data['types'] ?? null;
		$card             = self::add_category( $card, self::google_category( is_array( $card['category'] ) ? $card['category'] : array() ) );

		$tz = $this->timezone();
		if ( $tz ) {
			$card = array_merge( $card, $tz );
		}
		$card = array_filter( $card );

		return $card;
	}
}

<?php
/**
 * Venue Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Venues from the Geoapify Places API, https://apidocs.geoapify.com/docs/places/.
 *
 * @since 6.1.0
 */
class Venue_Provider_Geoapify extends Venue_Provider {
	use Sloc_API_GeoApify;

	/**
	 * Geoapify categories searched: places people go, not roads, buildings or boundaries.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const CATEGORIES = array( 'accommodation', 'activity', 'airport', 'beach', 'camping', 'catering', 'commercial', 'education', 'entertainment', 'healthcare', 'heritage', 'leisure', 'national_park', 'office', 'public_transport', 'religion', 'rental', 'service', 'sport', 'tourism' );

	/**
	 * Geoapify category prefixes and their shared categories and icons, most specific first.
	 *
	 * @since 6.1.0
	 * @var array[]
	 */
	const CATEGORY_MAP = array(
		'catering.bar'            => array( 'drink', '' ),
		'catering.pub'            => array( 'drink', '' ),
		'catering.biergarten'     => array( 'drink', '' ),
		'catering.taproom'        => array( 'drink', '' ),
		'catering'                => array( 'food', '' ),
		'accommodation'           => array( 'lodging', 'fa-suitcase' ),
		'commercial'              => array( 'shop', '' ),
		'office.government'       => array( 'government', '' ),
		'office'                  => array( 'office', '' ),
		'service.police'          => array( 'government', '' ),
		'service.post'            => array( 'government', '' ),
		'service.financial'       => array( 'office', '' ),
		'education'               => array( 'education', '' ),
		'healthcare'              => array( 'health', '' ),
		'religion'                => array( 'worship', '' ),
		'entertainment.museum'    => array( 'culture', '' ),
		'entertainment.culture'   => array( 'culture', '' ),
		'entertainment'           => array( 'entertainment', '' ),
		'heritage'                => array( 'culture', '' ),
		'tourism'                 => array( 'culture', '' ),
		'leisure.park'            => array( 'outdoors', '' ),
		'leisure.picnic'          => array( 'outdoors', '' ),
		'leisure.playground'      => array( 'outdoors', '' ),
		'leisure'                 => array( 'entertainment', '' ),
		'national_park'           => array( 'outdoors', '' ),
		'camping'                 => array( 'outdoors', '' ),
		'beach'                   => array( 'outdoors', '' ),
		'sport'                   => array( 'sport', '' ),
		'activity'                => array( 'sport', '' ),
		'airport'                 => array( 'transport', 'fa-plane' ),
		'public_transport.train'  => array( 'transport', 'fa-train' ),
		'public_transport.subway' => array( 'transport', 'fa-subway' ),
		'public_transport.tram'   => array( 'transport', 'fa-tram' ),
		'public_transport.bus'    => array( 'transport', 'fa-bus' ),
		'public_transport.ferry'  => array( 'transport', 'fa-ship' ),
		'public_transport'        => array( 'transport', '' ),
		'rental.car'              => array( 'transport', 'fa-car' ),
		'rental.bicycle'          => array( 'transport', 'fa-biking' ),
	);

	/**
	 * Constructor.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args Arguments. See Venue_Provider::__construct(). The key defaults to the sloc_geoapify_api setting.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'GeoApify', 'simple-location' );
		$this->slug        = 'geoapify';
		$this->url         = 'https://www.geoapify.com/';
		$this->description = __( 'Finds places near you with the Geoapify Places API, using your Geoapify API key.', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_geoapify_api' );
		}
		parent::__construct( $args );
	}

	/**
	 * Returns what a site may keep of this provider's places: the details, which Geoapify's terms allow storing, with credit to OpenStreetMap.
	 *
	 * @since 6.1.0
	 *
	 * @return string Venue_Provider::STORAGE_FULL.
	 */
	public function storage() {
		return self::STORAGE_FULL;
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
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for Geoapify', 'simple-location' ) );
		}
		$point    = $this->longitude . ',' . $this->latitude;
		$query    = array(
			'categories' => implode( ',', self::CATEGORIES ),
			'filter'     => 'circle:' . $point . ',' . $this->radius,
			'bias'       => 'proximity:' . $point,
			'limit'      => $this->limit,
			'apiKey'     => $this->api,
		);
		$language = strtolower( substr( (string) get_bloginfo( 'language' ), 0, 2 ) );
		if ( '' !== $language ) {
			$query['lang'] = $language;
		}
		$json = $this->fetch_json( 'https://api.geoapify.com/v2/places', $query );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$items = array();
		foreach ( is_array( $json['features'] ?? null ) ? $json['features'] : array() as $feature ) {
			if ( is_array( $feature['properties'] ?? null ) ) {
				$items[] = $this->place_to_hcard( $feature['properties'] );
			}
		}
		return array( 'items' => array_values( array_filter( $items ) ) );
	}

	/**
	 * Returns the shared category and location icon of a place's Geoapify categories.
	 *
	 * @since 6.1.0
	 *
	 * @param string[] $categories Geoapify categories, such as 'catering.cafe'.
	 * @return array Array with 'category', a key of Venue_Provider::categories(), and 'icon'.
	 */
	public static function geoapify_category( $categories ) {
		foreach ( self::CATEGORY_MAP as $prefix => $match ) {
			foreach ( (array) $categories as $category ) {
				if ( is_string( $category ) && ( $category === $prefix || 0 === strpos( $category, $prefix . '.' ) ) ) {
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
	 * Returns a place's identifier: its OpenStreetMap page for a place from OpenStreetMap, or its Geoapify ID.
	 *
	 * @since 6.1.0
	 *
	 * @param array $place Place properties.
	 * @return string|null Identifier.
	 */
	protected static function uid( $place ) {
		$raw = is_array( $place['datasource']['raw'] ?? null ) ? $place['datasource']['raw'] : array();
		$uid = self::osm_uid( $raw['osm_type'] ?? '', $raw['osm_id'] ?? '' );
		if ( '' !== $uid ) {
			return $uid;
		}
		return ( isset( $place['place_id'] ) && is_string( $place['place_id'] ) ) ? 'geoapify:' . $place['place_id'] : null;
	}

	/**
	 * Converts a place's properties to an h-card.
	 *
	 * @since 6.1.0
	 *
	 * @param array $place Place properties.
	 * @return array Venue as an h-card, or an empty array for a place without a name or location.
	 */
	protected function place_to_hcard( $place ) {
		if ( empty( $place['name'] ) || ! is_numeric( $place['lat'] ?? null ) || ! is_numeric( $place['lon'] ?? null ) ) {
			return array();
		}
		$country    = strtoupper( (string) ( $place['country_code'] ?? '' ) );
		$number     = $place['housenumber'] ?? '';
		$street     = $place['street'] ?? '';
		$categories = is_array( $place['categories'] ?? null ) ? array_values( array_filter( $place['categories'], 'is_string' ) ) : array();
		$card       = array(
			'type'             => 'card',
			'name'             => $place['name'],
			'label'            => $place['formatted'] ?? null,
			'street-number'    => $number,
			'street'           => $street,
			'street-address'   => trim( self::house_number( $country ) ? $number . ' ' . $street : $street . ' ' . $number ),
			'extended-address' => $place['district'] ?? $place['suburb'] ?? null,
			'locality'         => $place['city'] ?? null,
			'region'           => $place['state'] ?? null,
			'region-code'      => isset( $place['state_code'] ) ? strtoupper( $place['state_code'] ) : null,
			'postal-code'      => $place['postcode'] ?? null,
			'country-name'     => $place['country'] ?? null,
			'country-code'     => '' === $country ? null : $country,
			'url'              => $place['website'] ?? $place['datasource']['raw']['website'] ?? null,
			'tel'              => $place['contact']['phone'] ?? $place['datasource']['raw']['phone'] ?? null,
			'category'         => $categories,
			'latitude'         => (float) $place['lat'],
			'longitude'        => (float) $place['lon'],
			'uid'              => self::uid( $place ),
		);
		if ( WP_DEBUG ) {
			$card['raw'] = $place;
		}
		$card = self::add_category( array_filter( $card ), self::geoapify_category( $categories ) );
		$tz   = $this->timezone();
		if ( $tz ) {
			$card = array_merge( $card, $tz );
		}
		return $card;
	}
}

<?php
/**
 * Venue Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Venues from a Pelias server's reverse search of its venue layer, https://github.com/pelias/documentation/blob/master/reverse.md.
 *
 * A Pelias server's places come from open data, such as OpenStreetMap, Who's On First and Foursquare's open places,
 * so they may be kept.
 *
 * @since 6.1.0
 */
class Venue_Provider_Pelias extends Venue_Provider {

	/**
	 * Most places a Pelias reverse search returns.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const MAX_SIZE = 40;

	/**
	 * Pelias category prefixes and their shared categories and icons, most specific first.
	 *
	 * @since 6.1.0
	 * @var array[]
	 */
	const CATEGORY_MAP = array(
		'transport:air'  => array( 'transport', 'fa-plane' ),
		'transport:rail' => array( 'transport', 'fa-train' ),
		'transport:bus'  => array( 'transport', 'fa-bus' ),
		'transport:sea'  => array( 'transport', 'fa-ship' ),
		'transport'      => array( 'transport', '' ),
		'food'           => array( 'food', '' ),
		'nightlife'      => array( 'drink', '' ),
		'accommodation'  => array( 'lodging', 'fa-suitcase' ),
		'retail'         => array( 'shop', '' ),
		'education'      => array( 'education', '' ),
		'health'         => array( 'health', '' ),
		'religion'       => array( 'worship', '' ),
		'government'     => array( 'government', '' ),
		'professional'   => array( 'office', '' ),
		'finance'        => array( 'office', '' ),
		'entertainment'  => array( 'entertainment', '' ),
		'recreation'     => array( 'outdoors', '' ),
	);

	/**
	 * Constructor.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args Arguments. See Venue_Provider::__construct(). The key defaults to the sloc_pelias_api setting.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Pelias', 'simple-location' );
		$this->slug        = 'pelias';
		$this->url         = 'https://pelias.io/';
		$this->description = __( 'Finds places near you with your own Pelias server, or another you may use, from the Pelias Service URL setting. A key is sent only if one is set.', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_pelias_api' );
		}
		parent::__construct( $args );
	}

	/**
	 * Init Function To Register Settings: the Pelias server and key, shared with the Pelias geocoder.
	 *
	 * @since 6.1.0
	 */
	public static function init() {
		Geo_Provider_Pelias::init();
	}

	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 6.1.0
	 */
	public static function admin_init() {
		Geo_Provider_Pelias::admin_init();
	}

	/**
	 * Returns what a site may keep of this provider's places: the details, as they come from open data.
	 *
	 * @since 6.1.0
	 *
	 * @return string Venue_Provider::STORAGE_FULL.
	 */
	public function storage() {
		return self::STORAGE_FULL;
	}

	/**
	 * Returns the reverse search URL.
	 *
	 * @since 6.1.0
	 *
	 * @return string|WP_Error URL, or an error without a server.
	 */
	protected function endpoint() {
		$server = get_option( 'sloc_pelias_url' );
		// Only the form: requests are made with wp_safe_remote_get(), which checks where they go.
		$server = is_string( $server ) ? trim( $server ) : '';
		$server = preg_match( '#^https?://[^/\s]#i', $server ) ? esc_url_raw( $server, array( 'http', 'https' ) ) : '';
		if ( '' === $server ) {
			return new WP_Error( 'missing_pelias_url', __( 'You did not provide a Pelias URL', 'simple-location' ) );
		}
		return trailingslashit( $server ) . 'reverse';
	}

	/**
	 * Returns the places near the location.
	 *
	 * @since 6.1.0
	 *
	 * @return array|WP_Error h-cards under 'items', or an error.
	 */
	public function reverse_lookup() {
		$url = $this->endpoint();
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		$query = array(
			'point.lat'              => $this->latitude,
			'point.lon'              => $this->longitude,
			'layers'                 => 'venue',
			'size'                   => min( self::MAX_SIZE, $this->limit ),
			// Pelias measures the radius in kilometers.
			'boundary.circle.radius' => $this->radius / 1000,
		);
		$language = strtolower( substr( (string) get_bloginfo( 'language' ), 0, 2 ) );
		if ( '' !== $language ) {
			$query['lang'] = $language;
		}
		if ( ! empty( $this->api ) ) {
			$query['api_key'] = $this->api;
		}
		$json = $this->fetch_json( $url, $query );
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$errors = $json['geocoding']['errors'] ?? array();
		if ( is_array( $errors ) && ! empty( $errors ) ) {
			return new WP_Error( 'pelias_error', implode( ' ', array_filter( $errors, 'is_string' ) ), array( 'status' => 400 ) );
		}
		$items = array();
		foreach ( is_array( $json['features'] ?? null ) ? $json['features'] : array() as $feature ) {
			if ( is_array( $feature['properties'] ?? null ) ) {
				$items[] = $this->feature_to_hcard( $feature );
			}
		}
		return array( 'items' => array_values( array_filter( $items ) ) );
	}

	/**
	 * Returns the shared category and location icon of a place's Pelias categories.
	 *
	 * @since 6.1.0
	 *
	 * @param string[] $categories Pelias categories, such as 'food' or 'transport:air'.
	 * @return array Array with 'category', a key of Venue_Provider::categories(), and 'icon'.
	 */
	public static function pelias_category( $categories ) {
		foreach ( self::CATEGORY_MAP as $prefix => $match ) {
			foreach ( (array) $categories as $category ) {
				if ( is_string( $category ) && ( $category === $prefix || 0 === strpos( $category, $prefix . ':' ) ) ) {
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
	 * Returns a place's identifier: its OpenStreetMap page or Foursquare page, or its Pelias ID.
	 *
	 * @since 6.1.0
	 *
	 * @param array $properties Feature properties, with 'source', 'source_id' and 'gid'.
	 * @return string|null Identifier.
	 */
	protected static function uid( $properties ) {
		$source = $properties['source'] ?? '';
		$id     = $properties['source_id'] ?? '';
		if ( 'openstreetmap' === $source && is_string( $id ) && false !== strpos( $id, '/' ) ) {
			$parts = explode( '/', $id, 2 );
			$uid   = self::osm_uid( $parts[0], $parts[1] );
			if ( '' !== $uid ) {
				return $uid;
			}
		}
		if ( 'foursquare' === $source && is_string( $id ) && preg_match( '/^[0-9a-f]{20,32}$/i', $id ) ) {
			return 'https://foursquare.com/v/' . $id;
		}
		return ( isset( $properties['gid'] ) && is_string( $properties['gid'] ) ) ? $properties['gid'] : null;
	}

	/**
	 * Converts a feature to an h-card.
	 *
	 * @since 6.1.0
	 *
	 * @param array $feature GeoJSON feature, with properties and a point geometry.
	 * @return array Venue as an h-card, or an empty array for a place without a name or location.
	 */
	protected function feature_to_hcard( $feature ) {
		$place       = $feature['properties'];
		$coordinates = $feature['geometry']['coordinates'] ?? array();
		if ( empty( $place['name'] ) || ! is_numeric( $coordinates[0] ?? null ) || ! is_numeric( $coordinates[1] ?? null ) ) {
			return array();
		}
		$country    = self::country_code_iso3( $place['country_a'] ?? '' );
		$number     = $place['housenumber'] ?? '';
		$street     = $place['street'] ?? '';
		$osm        = is_array( $place['addendum']['osm'] ?? null ) ? $place['addendum']['osm'] : array();
		$categories = is_array( $place['category'] ?? null ) ? array_values( array_filter( $place['category'], 'is_string' ) ) : array();
		$url        = $osm['website'] ?? null;
		if ( empty( $url ) && ! empty( $osm['wikipedia'] ) ) {
			$url = self::wikipedia_url( $osm['wikipedia'] );
		}
		$card = array(
			'type'             => 'card',
			'name'             => $place['name'],
			'label'            => $place['label'] ?? null,
			'street-number'    => $number,
			'street'           => $street,
			'street-address'   => trim( self::house_number( $country ) ? $number . ' ' . $street : $street . ' ' . $number ),
			'extended-address' => $place['neighbourhood'] ?? $place['borough'] ?? null,
			'locality'         => $place['locality'] ?? null,
			'region'           => $place['region'] ?? null,
			'region-code'      => $place['region_a'] ?? null,
			'postal-code'      => $place['postalcode'] ?? null,
			'country-name'     => $place['country'] ?? null,
			'country-code'     => $country ? $country : null,
			'url'              => $url,
			'tel'              => $osm['phone'] ?? null,
			'category'         => $categories,
			'latitude'         => (float) $coordinates[1],
			'longitude'        => (float) $coordinates[0],
			'uid'              => self::uid( $place ),
		);
		if ( WP_DEBUG ) {
			$card['raw'] = $feature;
		}
		$card = self::add_category( array_filter( $card ), self::pelias_category( $categories ) );
		$tz   = $this->timezone();
		if ( $tz ) {
			$card = array_merge( $card, $tz );
		}
		return $card;
	}
}

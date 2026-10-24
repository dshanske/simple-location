<?php
/**
 * Base Venue Provider Class.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retrieves Location Information.
 *
 * @since 1.0.0
 */
abstract class Venue_Provider extends Sloc_Provider {
	use GeoLocation_Trait;

	/**
	 * Search radius in meters, without a setting.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const DEFAULT_RADIUS = 250;

	/**
	 * Smallest search radius in meters.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const MIN_RADIUS = 10;

	/**
	 * Largest search radius in meters.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const MAX_RADIUS = 5000;

	/**
	 * Most venues found in a search, without a setting.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const DEFAULT_LIMIT = 10;

	/**
	 * Most venues a search can be set to find.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const MAX_LIMIT = 50;

	/**
	 * Storage level of a provider whose places may be stored, such as OpenStreetMap's, with credit.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const STORAGE_FULL = 'full';

	/**
	 * Storage level of a provider whose terms allow storing only a place's identifier and links.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const STORAGE_REFERENCE = 'reference';

	/**
	 * Seconds a provider's search results are cached, when its terms allow.
	 *
	 * Check-in apps ask for the venues at the same place again and again.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const CACHE_TIME = 600;

	/**
	 * Search radius in meters.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	protected $radius = self::DEFAULT_RADIUS;

	/**
	 * Most venues found in a search.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	protected $limit = self::DEFAULT_LIMIT;

	/**
	 * Timezone.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $timezone;

	/**
	 * Offset.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $offset;

	/**
	 * Offset in Seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	protected $offset_seconds;

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
	 *  @type int $radius Search radius in meters. Default the sloc_venue_radius setting.
	 *  @type int $limit Most venues found. Default the sloc_venue_limit setting.
	 * }
	 *
	 * @since 1.0.0
	 * @since 6.1.0 Takes the search radius and limit.
	 */
	public function __construct( $args = array() ) {
		$defaults = array(
			'api'       => null,
			'latitude'  => null,
			'longitude' => null,
			'altitude'  => null,
			'user'      => '',
			'radius'    => get_option( 'sloc_venue_radius', self::DEFAULT_RADIUS ),
			'limit'     => get_option( 'sloc_venue_limit', self::DEFAULT_LIMIT ),
		);
		/**
		 * Filters the default arguments of venue providers.
		 *
		 * @since 5.0.0
		 *
		 * @param array $defaults Default arguments: api, latitude, longitude, altitude and user.
		 */
		$defaults     = apply_filters( 'sloc_venue_provider_defaults', $defaults );
		$r            = wp_parse_args( $args, $defaults );
		$this->user   = $r['user'];
		$this->api    = $r['api'];
		$this->radius = self::sanitize_radius( $r['radius'] );
		$this->limit  = self::sanitize_limit( $r['limit'] );
		$this->set( $r['latitude'], $r['longitude'] );

		if ( $this->is_active() && method_exists( $this, 'admin_init' ) ) {
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'init', array( $this, 'init' ) );
		}
	}

	/**
	 * Is Provider Active
	 *
	 * @since 1.0.0
	 * @since 6.1.0 Active anywhere in the venue chain.
	 */
	public function is_active() {
		return in_array( $this->slug, Sloc_Provider_Chain::configured( 'venue' ), true );
	}

	/**
	 * Returns elevation from provider.
	 *
	 * @return float|WP_Error $elevation Elevation, or an error.
	 *
	 * @since 1.0.0
	 * @since 6.0.0 Returns an error instead of 0 (sea level) when no elevation provider is set.
	 * @since 6.1.0 Tries the elevation providers in order. See Sloc_Provider_Chain.
	 */
	public function elevation() {
		return Sloc_Provider_Chain::elevation( $this->latitude, $this->longitude );
	}

	/**
	 * Returns the Wikipedia URL for an OpenStreetMap wikipedia tag.
	 *
	 * The tag is usually "language:Title". A tag without a language prefix is taken as an English title.
	 *
	 * @since 6.0.0
	 *
	 * @param string $tag Value of the wikipedia tag.
	 * @return string URL, or an empty string for an empty tag.
	 */
	public static function wikipedia_url( $tag ) {
		if ( ! is_string( $tag ) || '' === trim( $tag ) ) {
			return '';
		}
		$language = 'en';
		$title    = trim( $tag );
		$parts    = explode( ':', $title, 2 );
		// Titles can contain colons, so only a language code counts as a prefix.
		if ( 2 === count( $parts ) && preg_match( '/^[a-z]{2,3}(-[a-z0-9]+)*$/i', trim( $parts[0] ) ) ) {
			$language = strtolower( trim( $parts[0] ) );
			$title    = trim( $parts[1] );
		}
		return 'https://' . $language . '.wikipedia.org/wiki/' . rawurlencode( str_replace( ' ', '_', $title ) );
	}

	/**
	 * Returns what a site may keep of this provider's places, as the maintainers read the provider's terms.
	 *
	 * With STORAGE_REFERENCE, a venue keeps only the place's name as the user chose it, its coordinates, its
	 * identifier and links; its address comes from the site's geocoder. With STORAGE_FULL, it keeps the details.
	 *
	 * @since 6.1.0
	 *
	 * @return string STORAGE_FULL or STORAGE_REFERENCE. Default STORAGE_REFERENCE.
	 */
	public function storage() {
		return self::STORAGE_REFERENCE;
	}

	/**
	 * Returns how long this provider's search results may be cached, in seconds.
	 *
	 * A provider whose places may be kept caches them for CACHE_TIME; one that allows keeping only references
	 * doesn't cache, unless it overrides this because its terms allow keeping results for a time.
	 *
	 * @since 6.1.0
	 *
	 * @return int Seconds, or 0 not to cache.
	 */
	public function cache_time() {
		$seconds = self::STORAGE_FULL === $this->storage() ? self::CACHE_TIME : 0;
		/**
		 * Filters how long a venue provider's search results are cached, in seconds.
		 *
		 * Check a provider's terms before caching its results longer, or caching those of a provider that doesn't.
		 *
		 * @since 6.1.0
		 *
		 * @param int            $seconds  Seconds, or 0 not to cache.
		 * @param Venue_Provider $provider Venue provider.
		 */
		return max( 0, (int) apply_filters( 'sloc_venue_cache_time', $seconds, $this ) );
	}

	/**
	 * Returns the transient key of this provider's search at its location.
	 *
	 * Searches within about 10 meters of each other share a key.
	 *
	 * @since 6.1.0
	 *
	 * @return string|false Key, or false when the search isn't cached or has no location.
	 */
	protected function cache_key() {
		if ( ! is_numeric( $this->latitude ) || ! is_numeric( $this->longitude ) || 0 === $this->cache_time() ) {
			return false;
		}
		$search = array( $this->slug, round( (float) $this->latitude, 4 ), round( (float) $this->longitude, 4 ), $this->radius, $this->limit, get_bloginfo( 'language' ) );
		return 'sloc_venues_' . md5( wp_json_encode( $search ) );
	}

	/**
	 * Returns the identifier of an OpenStreetMap place: its page on openstreetmap.org.
	 *
	 * Providers whose places come from OpenStreetMap use it as a venue's 'uid', so the same place matches
	 * whichever of them found it.
	 *
	 * @since 6.1.0
	 *
	 * @param string     $type Element type: node, way or relation, or N, W or R.
	 * @param string|int $id   Element ID.
	 * @return string URL, such as https://www.openstreetmap.org/way/28331796, or an empty string.
	 */
	public static function osm_uid( $type, $id ) {
		$types = array(
			'n'        => 'node',
			'w'        => 'way',
			'r'        => 'relation',
			'node'     => 'node',
			'way'      => 'way',
			'relation' => 'relation',
		);
		$type  = $types[ strtolower( is_string( $type ) ? $type : '' ) ] ?? '';
		$id    = is_int( $id ) ? (string) $id : $id;
		if ( '' === $type || ! is_string( $id ) || ! preg_match( '/^[1-9][0-9]*$/', $id ) ) {
			return '';
		}
		return 'https://www.openstreetmap.org/' . $type . '/' . $id;
	}

	/**
	 * Sanitizes the search radius setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Radius in meters.
	 * @return int Radius from MIN_RADIUS to MAX_RADIUS, or DEFAULT_RADIUS for anything that isn't a number.
	 */
	public static function sanitize_radius( $value ) {
		if ( ! is_numeric( $value ) ) {
			return self::DEFAULT_RADIUS;
		}
		return max( self::MIN_RADIUS, min( self::MAX_RADIUS, (int) round( (float) $value ) ) );
	}

	/**
	 * Sanitizes the most venues setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Most venues.
	 * @return int From 1 to MAX_LIMIT, or DEFAULT_LIMIT for anything that isn't a number.
	 */
	public static function sanitize_limit( $value ) {
		if ( ! is_numeric( $value ) ) {
			return self::DEFAULT_LIMIT;
		}
		return max( 1, min( self::MAX_LIMIT, (int) $value ) );
	}

	/**
	 * Returns the search radius in meters.
	 *
	 * @since 6.1.0
	 *
	 * @return int Radius.
	 */
	public function get_radius() {
		return $this->radius;
	}

	/**
	 * Returns the most venues a search finds.
	 *
	 * @since 6.1.0
	 *
	 * @return int Limit.
	 */
	public function get_limit() {
		return $this->limit;
	}

	/**
	 * Returns the venues near the location: the provider's, within the radius, nearest first, up to the limit.
	 *
	 * Each venue with coordinates gets its distance from the location, in meters, as 'distance'. Venues without
	 * coordinates come last. Each venue names the provider that found it as 'provider'.
	 *
	 * The provider's venues are cached for cache_time() seconds, and sorted and limited for each request.
	 *
	 * @since 6.1.0
	 *
	 * @return array|WP_Error Array with the venues as h-cards under 'items', or an error.
	 */
	public function nearby() {
		$key   = $this->cache_key();
		$items = $key ? get_transient( $key ) : false;
		if ( ! is_array( $items ) ) {
			$result = $this->reverse_lookup();
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$items = ( is_array( $result ) && isset( $result['items'] ) && is_array( $result['items'] ) ) ? $result['items'] : array();
			if ( $key ) {
				set_transient( $key, $items, $this->cache_time() );
			}
		}
		foreach ( $items as $key => $item ) {
			if ( is_array( $item ) && ! empty( $item ) && empty( $item['provider'] ) ) {
				$items[ $key ]['provider'] = $this->slug;
			}
		}
		return array( 'items' => self::sort_items( $items, $this->latitude, $this->longitude, $this->radius, $this->limit ) );
	}

	/**
	 * Sorts venues by distance from a location, leaving out those beyond the radius.
	 *
	 * @since 6.1.0
	 *
	 * @param array $items     Venues as h-cards.
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @param int   $radius    Radius in meters, or 0 for any distance.
	 * @param int   $limit     Most venues, or 0 for all.
	 * @return array Venues, nearest first, each with 'distance' in meters if it has coordinates.
	 */
	public static function sort_items( $items, $latitude, $longitude, $radius = 0, $limit = 0 ) {
		$near = array();
		$rest = array();
		foreach ( (array) $items as $item ) {
			if ( ! is_array( $item ) || empty( $item ) ) {
				continue;
			}
			if ( ! is_numeric( $item['latitude'] ?? null ) || ! is_numeric( $item['longitude'] ?? null ) || ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
				$rest[] = $item;
				continue;
			}
			$item['distance'] = (int) round( geo_distance( $latitude, $longitude, $item['latitude'], $item['longitude'] ) );
			if ( $radius > 0 && $item['distance'] > $radius ) {
				continue;
			}
			$near[] = $item;
		}
		usort( $near, fn( $a, $b ) => $a['distance'] <=> $b['distance'] );
		$items = array_merge( $near, $rest );
		return $limit > 0 ? array_slice( $items, 0, $limit ) : $items;
	}

	/**
	 * Returns the venue categories every provider's categories are mapped to, with their names.
	 *
	 * Each venue keeps its provider's own categories as 'category'; its shared category is 'venue-category'.
	 *
	 * @since 6.1.0
	 *
	 * @return string[] Names, keyed by category.
	 */
	public static function categories() {
		return array(
			'food'          => __( 'Food', 'simple-location' ),
			'drink'         => __( 'Drink', 'simple-location' ),
			'lodging'       => __( 'Lodging', 'simple-location' ),
			'shop'          => __( 'Shop', 'simple-location' ),
			'transport'     => __( 'Transport', 'simple-location' ),
			'culture'       => __( 'Culture', 'simple-location' ),
			'entertainment' => __( 'Entertainment', 'simple-location' ),
			'outdoors'      => __( 'Outdoors', 'simple-location' ),
			'sport'         => __( 'Sport', 'simple-location' ),
			'health'        => __( 'Health', 'simple-location' ),
			'education'     => __( 'Education', 'simple-location' ),
			'worship'       => __( 'Worship', 'simple-location' ),
			'government'    => __( 'Government', 'simple-location' ),
			'office'        => __( 'Office', 'simple-location' ),
			'other'         => __( 'Other', 'simple-location' ),
		);
	}

	/**
	 * Returns the shared category and location icon of an OpenStreetMap tag, such as amenity=cafe.
	 *
	 * Used by the providers whose places come from OpenStreetMap.
	 *
	 * @since 6.1.0
	 *
	 * @param string $key   Tag key, such as 'amenity'.
	 * @param string $value Tag value, such as 'cafe'.
	 * @return array Array with 'category', a key of categories(), and 'icon', a location icon or an empty string.
	 */
	public static function osm_category( $key, $value ) {
		$key    = strtolower( (string) $key );
		$value  = strtolower( (string) $value );
		$icons  = array(
			'aeroway=aerodrome'              => 'fa-plane',
			'aeroway=terminal'               => 'fa-plane',
			'railway=station'                => 'fa-train',
			'railway=halt'                   => 'fa-train',
			'railway=subway_entrance'        => 'fa-subway',
			'railway=tram_stop'              => 'fa-tram',
			'amenity=bus_station'            => 'fa-bus',
			'highway=bus_stop'               => 'fa-bus',
			'amenity=ferry_terminal'         => 'fa-ship',
			'amenity=taxi'                   => 'fa-taxi',
			'amenity=bicycle_rental'         => 'fa-biking',
			'amenity=car_rental'             => 'fa-car',
			'amenity=fuel'                   => 'fa-car',
			'amenity=parking'                => 'fa-car',
			'public_transport=station'       => 'fa-train',
			'public_transport=stop_position' => 'fa-bus',
		);
		$values = array(
			'amenity' => array(
				'food'          => array( 'restaurant', 'fast_food', 'food_court', 'cafe', 'ice_cream' ),
				'drink'         => array( 'bar', 'pub', 'biergarten' ),
				'entertainment' => array( 'cinema', 'theatre', 'nightclub', 'casino' ),
				'culture'       => array( 'arts_centre', 'library' ),
				'education'     => array( 'school', 'college', 'university', 'kindergarten' ),
				'health'        => array( 'hospital', 'clinic', 'doctors', 'dentist', 'pharmacy' ),
				'worship'       => array( 'place_of_worship' ),
				'transport'     => array( 'bus_station', 'ferry_terminal', 'taxi', 'bicycle_rental', 'car_rental', 'fuel', 'parking' ),
				'government'    => array( 'townhall', 'courthouse', 'police', 'fire_station', 'post_office', 'embassy' ),
				'office'        => array( 'bank' ),
			),
			'tourism' => array(
				'lodging'       => array( 'hotel', 'motel', 'hostel', 'guest_house', 'apartment', 'chalet', 'alpine_hut' ),
				'outdoors'      => array( 'camp_site', 'caravan_site', 'viewpoint', 'picnic_site' ),
				'entertainment' => array( 'zoo', 'theme_park', 'aquarium' ),
				'culture'       => array( 'museum', 'gallery', 'artwork', 'attraction' ),
			),
			'leisure' => array(
				'outdoors'      => array( 'park', 'garden', 'nature_reserve', 'beach_resort', 'playground', 'dog_park' ),
				'sport'         => array( 'sports_centre', 'stadium', 'pitch', 'fitness_centre', 'swimming_pool', 'golf_course', 'track', 'ice_rink' ),
				'entertainment' => array( 'water_park', 'bowling_alley', 'amusement_arcade' ),
			),
		);
		// Whole keys, whatever their value.
		$keys     = array(
			'shop'             => 'shop',
			'craft'            => 'shop',
			'office'           => 'office',
			'historic'         => 'culture',
			'natural'          => 'outdoors',
			'aeroway'          => 'transport',
			'railway'          => 'transport',
			'public_transport' => 'transport',
		);
		$category = 'other';
		foreach ( $values[ $key ] ?? array() as $shared => $list ) {
			if ( in_array( $value, $list, true ) ) {
				$category = $shared;
				break;
			}
		}
		if ( 'other' === $category && isset( $keys[ $key ] ) ) {
			$category = $keys[ $key ];
		}
		return array(
			'category' => $category,
			'icon'     => $icons[ $key . '=' . $value ] ?? ( 'lodging' === $category ? 'fa-suitcase' : '' ),
		);
	}

	/**
	 * Adds a shared category, and a location icon if one fits, to a venue.
	 *
	 * @since 6.1.0
	 *
	 * @param array $card  Venue as an h-card.
	 * @param array $match Array with 'category' and 'icon', as osm_category() returns.
	 * @return array Venue with 'venue-category', and 'icon' if there is one.
	 */
	protected static function add_category( $card, $match ) {
		/**
		 * Filters the shared category and location icon a provider's venue is given.
		 *
		 * @since 6.1.0
		 *
		 * @param array $match Array with 'category', a key of Venue_Provider::categories(), and 'icon'.
		 * @param array $card  Venue as an h-card, with the provider's own categories as 'category'.
		 */
		$match = apply_filters( 'sloc_venue_category', $match, $card );
		if ( ! empty( $match['category'] ) && isset( self::categories()[ $match['category'] ] ) ) {
			$card['venue-category'] = $match['category'];
		}
		if ( ! empty( $match['icon'] ) ) {
			$card['icon'] = $match['icon'];
		}
		return $card;
	}

	/**
	 * Returns a list of one or more possible venues in h-card jf2 format.
	 *
	 * Callers use nearby(), which sorts and limits the venues. Each venue should have a 'uid' that identifies the
	 * place at the provider: a page about it, such as osm_uid() returns, or an identifier such as
	 * here:pds:place:840dr4ux-1234.
	 *
	 * @since 1.0.0
	 * @since 6.1.0 Venues have a 'uid' (#729).
	 *
	 * @return array Array of jf2 h-card elements under property items.
	 */
	abstract public function reverse_lookup();
}

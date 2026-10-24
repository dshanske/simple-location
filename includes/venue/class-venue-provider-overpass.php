<?php
/**
 * Venue Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Venues from OpenStreetMap, found with the Overpass API, https://wiki.openstreetmap.org/wiki/Overpass_API.
 *
 * Finds the named places near a location: amenities, shops, tourism, leisure, crafts, offices and historic places.
 * The public servers need no key and are for light use; heavy or commercial use should use another server.
 *
 * @since 6.1.0
 */
class Venue_Provider_Overpass extends Venue_Provider {

	/**
	 * The main public Overpass server, used without a server URL setting.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const PUBLIC_URL = 'https://overpass-api.de/api/interpreter';

	/**
	 * OpenStreetMap keys of the places found, in the order the first one names the place's category.
	 *
	 * @since 6.1.0
	 * @var string[]
	 */
	const KEYS = array( 'amenity', 'shop', 'tourism', 'leisure', 'craft', 'office', 'historic' );

	/**
	 * Most places asked of the server, before they are sorted by distance and limited.
	 *
	 * @since 6.1.0
	 * @var int
	 */
	const MAX_ELEMENTS = 100;

	/**
	 * Server URL.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $server = self::PUBLIC_URL;

	/**
	 * Constructor.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args {
	 *     Arguments. See Venue_Provider::__construct() for the others.
	 *
	 *     @type string $server Server URL. Default the sloc_overpass_url setting, or the public server.
	 * }
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'OpenStreetMap Overpass', 'simple-location' );
		$this->slug        = 'overpass';
		$this->url         = 'https://wiki.openstreetmap.org/wiki/Overpass_API';
		$this->description = __( 'Finds the named places near you in OpenStreetMap, with no key. The public servers are for light use; heavy or commercial use should use another server, or your own.', 'simple-location' );

		$server = self::sanitize_url( $args['server'] ?? get_option( 'sloc_overpass_url' ) );
		if ( '' !== $server ) {
			$this->server = $server;
		}
		parent::__construct( $args );
	}

	/**
	 * Sanitizes the server URL setting.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Server URL, such as https://overpass.example.com/api/interpreter.
	 * @return string HTTP or HTTPS URL, or an empty string for the public server.
	 */
	public static function sanitize_url( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return (string) esc_url_raw( trim( $value ), array( 'http', 'https' ) );
	}

	/**
	 * Init Function To Register Settings.
	 *
	 * @since 6.1.0
	 */
	public static function init() {
		register_setting(
			'sloc_api',
			'sloc_overpass_url',
			array(
				'type'              => 'string',
				'description'       => 'Overpass Server URL',
				'show_in_rest'      => false,
				'default'           => '',
				'sanitize_callback' => array( __CLASS__, 'sanitize_url' ),
			)
		);
	}

	/**
	 * Admin Init Function To Register Settings.
	 *
	 * @since 6.1.0
	 */
	public static function admin_init() {
		add_settings_field(
			'sloc_overpass_url',
			__( 'Overpass Server URL', 'simple-location' ),
			array( 'Loc_Config', 'string_callback' ),
			'sloc_api',
			'sloc_api',
			array(
				'label_for'   => 'sloc_overpass_url',
				'type'        => 'url',
				'description' => __( 'Optional. The interpreter address of another Overpass server, or your own, such as https://overpass.example.com/api/interpreter. Leave empty to use the public server.', 'simple-location' ),
			)
		);
	}

	/**
	 * Returns the Overpass QL query for the named places within the radius.
	 *
	 * @since 6.1.0
	 *
	 * @return string Query.
	 */
	public function query() {
		$around = sprintf( 'around:%1$d,%2$s,%3$s', $this->radius, self::coordinate( $this->latitude ), self::coordinate( $this->longitude ) );
		return sprintf(
			'[out:json][timeout:15];nwr(%1$s)[name][~"^(%2$s)$"~"."];out center tags %3$d;',
			$around,
			implode( '|', self::KEYS ),
			self::MAX_ELEMENTS
		);
	}

	/**
	 * Formats a coordinate for a query, without exponents or locale separators.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed $value Coordinate.
	 * @return string Coordinate.
	 */
	protected static function coordinate( $value ) {
		return rtrim( rtrim( sprintf( '%.7F', (float) $value ), '0' ), '.' );
	}

	/**
	 * Returns the named places near the location.
	 *
	 * @since 6.1.0
	 *
	 * @return array|WP_Error h-cards under 'items', or an error, such as HTTP 429 when the server is busy.
	 */
	public function reverse_lookup() {
		if ( ! is_numeric( $this->latitude ) || ! is_numeric( $this->longitude ) ) {
			return array( 'items' => array() );
		}
		$json = $this->fetch_json(
			$this->server,
			array( 'data' => $this->query() ),
			// The usage policy asks each application to identify itself.
			array( 'User-Agent' => sprintf( 'Simple Location for WordPress (%1$s)', home_url( '/' ) ) )
		);
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$items = array();
		foreach ( is_array( $json['elements'] ?? null ) ? $json['elements'] : array() as $element ) {
			if ( is_array( $element ) ) {
				$items[] = $this->element_to_hcard( $element );
			}
		}
		return array( 'items' => array_values( array_filter( $items ) ) );
	}

	/**
	 * Converts an OpenStreetMap node, way or relation to an h-card.
	 *
	 * @since 6.1.0
	 *
	 * @param array $element Element, with tags, and lat and lon, or a center for ways and relations.
	 * @return array Venue as an h-card, or an empty array without a name or a location.
	 */
	protected function element_to_hcard( $element ) {
		$tags      = is_array( $element['tags'] ?? null ) ? $element['tags'] : array();
		$latitude  = $element['lat'] ?? $element['center']['lat'] ?? null;
		$longitude = $element['lon'] ?? $element['center']['lon'] ?? null;
		if ( empty( $tags['name'] ) || ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
			return array();
		}
		$key = '';
		foreach ( self::KEYS as $candidate ) {
			if ( isset( $tags[ $candidate ] ) ) {
				$key = $candidate;
				break;
			}
		}
		$country = strtoupper( (string) ( $tags['addr:country'] ?? '' ) );
		$number  = $tags['addr:housenumber'] ?? '';
		$street  = $tags['addr:street'] ?? '';
		$state   = $tags['addr:state'] ?? null;
		$url     = $tags['website'] ?? $tags['contact:website'] ?? $tags['url'] ?? null;
		if ( empty( $url ) && ! empty( $tags['wikipedia'] ) ) {
			$url = self::wikipedia_url( $tags['wikipedia'] );
		}
		$card = array(
			'type'           => 'card',
			'name'           => $tags['name'],
			'street-number'  => $number,
			'street'         => $street,
			'street-address' => trim( self::house_number( $country ) ? $number . ' ' . $street : $street . ' ' . $number ),
			'locality'       => $tags['addr:city'] ?? null,
			'region'         => $state,
			// A two-letter state, as US addresses have, is the region code.
			'region-code'    => ( is_string( $state ) && preg_match( '/^[A-Z]{2}$/', $state ) ) ? $state : null,
			'postal-code'    => $tags['addr:postcode'] ?? null,
			'country-code'   => '' === $country ? null : $country,
			'country-name'   => '' === $country ? null : self::country_name( $country ),
			'url'            => $url,
			'tel'            => $tags['phone'] ?? $tags['contact:phone'] ?? null,
			'photo'          => $tags['image'] ?? null,
			'category'       => '' === $key ? null : $tags[ $key ],
			'latitude'       => (float) $latitude,
			'longitude'      => (float) $longitude,
			'uid'            => self::osm_uid( $element['type'] ?? '', $element['id'] ?? '' ),
		);
		if ( WP_DEBUG ) {
			$card['raw'] = $element;
		}
		$card = self::add_category( array_filter( $card ), self::osm_category( $key, $tags[ $key ] ?? '' ) );
		$tz   = $this->timezone();
		if ( $tz ) {
			$card = array_merge( $card, $tz );
		}
		return $card;
	}
}

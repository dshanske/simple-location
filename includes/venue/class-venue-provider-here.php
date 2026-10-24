<?php
/**
 * Venue Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Venues from HERE Geocoding and Search's browse endpoint, which finds places near a point, nearest first.
 *
 * @since 6.1.0
 */
class Venue_Provider_HERE extends Venue_Provider {
	use Sloc_API_Here;

	/**
	 * HERE category ID prefixes and their shared categories and icons, most specific first.
	 *
	 * HERE's categories are levels of a hierarchy, such as 100 (eat and drink), 100-1000 (restaurant) and
	 * 100-1000-0000.
	 *
	 * @since 6.1.0
	 * @var array[]
	 */
	const CATEGORY_MAP = array(
		'200-2000' => array( 'drink', '' ),
		'100'      => array( 'food', '' ),
		'200'      => array( 'entertainment', '' ),
		'300-3200' => array( 'worship', '' ),
		'300'      => array( 'culture', '' ),
		'350'      => array( 'outdoors', '' ),
		'400-4000' => array( 'transport', 'fa-plane' ),
		'400'      => array( 'transport', '' ),
		'500'      => array( 'lodging', 'fa-suitcase' ),
		'550-5510' => array( 'outdoors', '' ),
		'550'      => array( 'entertainment', '' ),
		'600'      => array( 'shop', '' ),
		'700-7600' => array( 'transport', 'fa-car' ),
		'700-7850' => array( 'transport', 'fa-car' ),
		'700'      => array( 'office', '' ),
		'800-8000' => array( 'health', '' ),
		'800-8100' => array( 'government', '' ),
		'800-8200' => array( 'education', '' ),
		'800-8300' => array( 'culture', '' ),
		'800-8500' => array( 'transport', 'fa-car' ),
		'800-8600' => array( 'sport', '' ),
	);

	/**
	 * Constructor.
	 *
	 * @since 6.1.0
	 *
	 * @param array $args Arguments. See Venue_Provider::__construct(). The key defaults to the sloc_here_api setting.
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'HERE', 'simple-location' );
		$this->slug        = 'here';
		$this->url         = 'https://platform.here.com/';
		$this->description = __( 'Finds places near you with HERE Geocoding and Search, using your HERE API key.', 'simple-location' );
		if ( ! isset( $args['api'] ) ) {
			$args['api'] = get_option( 'sloc_here_api' );
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
			return new WP_Error( 'missing_api_key', __( 'You have not set an API key for HERE', 'simple-location' ) );
		}
		$point = $this->latitude . ',' . $this->longitude;
		$json  = $this->fetch_json(
			'https://browse.search.hereapi.com/v1/browse',
			array(
				'at'     => $point,
				'in'     => 'circle:' . $point . ';r=' . $this->radius,
				'limit'  => min( 100, $this->limit ),
				'lang'   => get_bloginfo( 'language' ),
				'apiKey' => $this->api,
			)
		);
		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$items = array();
		foreach ( is_array( $json['items'] ?? null ) ? $json['items'] : array() as $item ) {
			// Only places, not the streets or towns browse can also return.
			if ( is_array( $item ) && 'place' === ( $item['resultType'] ?? 'place' ) ) {
				$items[] = $this->item_to_hcard( $item );
			}
		}
		return array( 'items' => array_values( array_filter( $items ) ) );
	}

	/**
	 * Returns the shared category and location icon of a place's HERE categories, from its primary category.
	 *
	 * @since 6.1.0
	 *
	 * @param array $categories HERE categories, each with 'id', and 'primary' for the primary one.
	 * @return array Array with 'category', a key of Venue_Provider::categories(), and 'icon'.
	 */
	public static function here_category( $categories ) {
		$ids = array();
		foreach ( (array) $categories as $category ) {
			if ( is_array( $category ) && isset( $category['id'] ) && is_string( $category['id'] ) ) {
				if ( ! empty( $category['primary'] ) ) {
					array_unshift( $ids, $category['id'] );
				} else {
					$ids[] = $category['id'];
				}
			}
		}
		foreach ( $ids as $id ) {
			foreach ( self::CATEGORY_MAP as $prefix => $match ) {
				if ( $id === $prefix || 0 === strpos( $id, $prefix . '-' ) ) {
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
	 * Returns the first value of a kind of contact, such as a website.
	 *
	 * HERE lists contacts as an array of groups, each with arrays of values by kind.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed  $contacts Contacts.
	 * @param string $kind     Kind, such as 'www' or 'phone'.
	 * @return string|null Value.
	 */
	protected static function contact( $contacts, $kind ) {
		$groups = ( is_array( $contacts ) && isset( $contacts[ $kind ] ) ) ? array( $contacts ) : (array) $contacts;
		foreach ( $groups as $group ) {
			foreach ( (array) ( $group[ $kind ] ?? array() ) as $entry ) {
				if ( is_array( $entry ) && ! empty( $entry['value'] ) && is_string( $entry['value'] ) ) {
					return $entry['value'];
				}
			}
		}
		return null;
	}

	/**
	 * Converts a place to an h-card.
	 *
	 * @since 6.1.0
	 *
	 * @param array $item Place.
	 * @return array Venue as an h-card, or an empty array for a place without a name or location.
	 */
	protected function item_to_hcard( $item ) {
		if ( empty( $item['title'] ) || ! is_numeric( $item['position']['lat'] ?? null ) || ! is_numeric( $item['position']['lng'] ?? null ) ) {
			return array();
		}
		$address = is_array( $item['address'] ?? null ) ? $item['address'] : array();
		$country = self::country_code_iso3( $address['countryCode'] ?? '' );
		$number  = $address['houseNumber'] ?? '';
		$street  = $address['street'] ?? '';
		$card    = array(
			'type'             => 'card',
			'name'             => $item['title'],
			'label'            => $address['label'] ?? null,
			'street-number'    => $number,
			'street'           => $street,
			'street-address'   => trim( self::house_number( $country ) ? $number . ' ' . $street : $street . ' ' . $number ),
			'extended-address' => $address['district'] ?? null,
			'locality'         => $address['city'] ?? null,
			'region'           => $address['state'] ?? null,
			'region-code'      => $address['stateCode'] ?? null,
			'postal-code'      => $address['postalCode'] ?? null,
			'country-name'     => $address['countryName'] ?? null,
			'country-code'     => $country ? $country : null,
			'url'              => self::contact( $item['contacts'] ?? array(), 'www' ),
			'tel'              => self::contact( $item['contacts'] ?? array(), 'phone' ),
			'category'         => is_array( $item['categories'] ?? null ) ? array_values( array_filter( array_column( array_filter( $item['categories'], 'is_array' ), 'name' ), 'is_string' ) ) : null,
			'latitude'         => (float) $item['position']['lat'],
			'longitude'        => (float) $item['position']['lng'],
		);
		if ( WP_DEBUG ) {
			$card['raw'] = $item;
		}
		$card = self::add_category( array_filter( $card ), self::here_category( $item['categories'] ?? array() ) );
		$tz   = $this->timezone();
		if ( $tz ) {
			$card = array_merge( $card, $tz );
		}
		return $card;
	}
}

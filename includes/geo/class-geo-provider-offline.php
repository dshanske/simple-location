<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Offline geocoding from the data bundled with the plugin: no key, no requests.
 *
 * Gives the place a location is in, its region and country, from GeoNames places of at least 1000 people and Natural
 * Earth borders. It doesn't know streets. As the last provider in the geocoding chain, it gives a post at least its
 * town or country when the others can't; on its own, no location leaves the site. See Sloc_Offline_Geo.
 *
 * @since 6.5.0
 */
class Geo_Provider_Offline extends Geo_Provider {

	/**
	 * Constructor.
	 *
	 * @since 6.5.0
	 *
	 * @param array $args Arguments. See Geo_Provider::__construct().
	 */
	public function __construct( $args = array() ) {
		$this->name        = __( 'Offline (built in)', 'simple-location' );
		$this->slug        = 'offline';
		$this->url         = 'https://www.geonames.org/';
		$this->description = __( 'Looks up the town, region and country from data that comes with the plugin, without a key or sending the location anywhere. It doesn\'t know streets. Use it on its own, or last, so posts get at least a town or country when other providers can\'t answer. Places from GeoNames, borders from Natural Earth.', 'simple-location' );
		parent::__construct( $args );
	}

	/**
	 * Return an address.
	 *
	 * The place the location is in, or is nearest, weighted by population; without one nearby, only the country.
	 *
	 * @since 6.5.0
	 *
	 * @return array|WP_Error Microformats2 address elements, or `no_results` when the location is in no country and
	 *                        near no place, such as at sea.
	 */
	public function reverse_lookup() {
		$country = Sloc_Offline_Geo::country( $this->latitude, $this->longitude );
		// Only places in the location's country, so a big city across a border doesn't win.
		$place = Sloc_Offline_Geo::place( $this->latitude, $this->longitude, $country );
		if ( ! $place && '' === $country ) {
			return new WP_Error( 'no_results', __( 'No Results Found', 'simple-location' ), array( 'status' => 404 ) );
		}
		return $this->to_mf( $place, '' !== $country ? $country : $place['country'] );
	}

	/**
	 * Geocode address.
	 *
	 * Finds a place by name. The address is read as a place name, optionally followed by its region and country,
	 * separated by commas, such as "Springfield, Illinois, US". Street addresses aren't known, so only the last parts
	 * that name a place are used.
	 *
	 * @since 6.5.0
	 *
	 * @param string $address Address.
	 * @return array|WP_Error Microformats2 address elements with the place's coordinates, or `no_results`.
	 */
	public function geocode( $address ) {
		$parts = array_values( array_filter( array_map( 'trim', explode( ',', (string) $address ) ), 'strlen' ) );
		$found = null;
		// Each part in turn is tried as the place, with the parts after it as hints of its region and country, so a
		// street address before the place is skipped.
		$count = count( $parts );
		for ( $i = 0; $i < $count && ! $found; $i++ ) {
			$found = Sloc_Offline_Geo::find_place( $parts[ $i ], array_slice( $parts, $i + 1 ) );
		}
		if ( ! $found ) {
			return new WP_Error( 'no_results', __( 'No results found', 'simple-location' ), array( 'status' => 404 ) );
		}
		$result = clone $this;
		$result->set( $found['latitude'], $found['longitude'] );
		return $result->to_mf( $found, $found['country'] );
	}

	/**
	 * Converts a place to microformats2 address properties.
	 *
	 * @since 6.5.0
	 *
	 * @param array|null $place   Place, or null for only the country.
	 * @param string     $country Country code.
	 * @return array Microformats2 address elements.
	 */
	protected function to_mf( $place, $country ) {
		$region = $place['region'] ?? '';
		$code   = '' === $region ? false : self::region_code( $region, $country );
		// GeoNames region codes are ISO 3166-2 codes in some countries, such as the US, and not in others, such as
		// Portugal; one is only used when ISO gives it the same name.
		if ( ! $code && $place && '' !== $place['region_code'] && Sloc_Offline_Geo::normalize( (string) self::region_name( $place['region_code'], $country ) ) === Sloc_Offline_Geo::normalize( $region ) ) {
			$code = $place['region_code'];
		}
		$addr                 = array_filter(
			array(
				'locality'     => $place['name'] ?? null,
				'region'       => $region,
				'region-code'  => $code,
				'country-name' => self::country_name( $country ),
				'country-code' => $country,
				'latitude'     => $this->latitude,
				'longitude'    => $this->longitude,
				'altitude'     => $this->altitude,
			)
		);
		$addr['display-name'] = $this->display_name( $addr );
		$tz                   = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		return $addr;
	}
}

<?php
/**
 * Parsing of Nominatim-style OpenStreetMap addresses.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads a Nominatim-style `address` object into microformats2 address properties.
 *
 * Nominatim, LocationIQ and Traccar Geocoder all answer with OpenStreetMap data in this format, for addresses and for
 * venues, so they share one parser. It cleans what OpenStreetMap data holds as entered: several values separated by
 * semicolons, US postcodes that lost a leading zero, administrative districts given as a suburb or city, and
 * "Village of" style names. What an answer leaves out, such as the country from a server built from US state extracts,
 * or the city in New York City's boroughs, is filled in from the location and the bundled place data.
 *
 * Used through Sloc_OSM_Geo by geo providers and Sloc_OSM_Venue by venue providers.
 *
 * @since 6.5.0
 */
trait Sloc_OSM_Address {

	/**
	 * Address keys naming the place itself, in order of preference.
	 *
	 * @since 6.5.0
	 *
	 * @return string[] Keys.
	 */
	protected static function osm_name_keys() {
		return array( 'attraction', 'library', 'parking', 'tourism', 'place_of_worship', 'building', 'hotel', 'historic', 'military', 'office', 'club', 'craft', 'leisure', 'shop', 'address29', 'address26', 'emergency', 'natural', 'landuse', 'place', 'railway', 'man_made', 'aerialway', 'boundary', 'amenity', 'aeroway', 'mountain_pass', 'bridge', 'tunnel', 'waterway' );
	}

	/**
	 * Reads the address of an answer.
	 *
	 * @since 6.5.0
	 *
	 * @param array      $json      Answer, with 'address'.
	 * @param float|null $latitude  Optional. Latitude of the place, to fill in what the answer leaves out. Default the
	 *                              provider's.
	 * @param float|null $longitude Optional. Longitude of the place. Default the provider's.
	 * @return array Address properties: name, street-number, street, street-address, extended-address, locality, region,
	 *               region-code, country-name, postal-code and country-code, each only when known. Empty without an
	 *               address.
	 */
	protected function osm_address( $json, $latitude = null, $longitude = null ) {
		if ( ! is_array( $json ) || ! isset( $json['address'] ) || ! is_array( $json['address'] ) ) {
			return array();
		}
		$latitude  = $latitude ?? $this->latitude;
		$longitude = $longitude ?? $this->longitude;
		$address   = array_map( array( __CLASS__, 'osm_value' ), array_filter( $json['address'], 'is_scalar' ) );

		$country_code = strtoupper( $address['country_code'] ?? '' );
		if ( '' === $country_code ) {
			// A server built from extracts smaller than a country, such as US states, has no country boundary.
			$country_code = self::country_for_location( $latitude, $longitude );
		}
		if ( '' === $country_code && class_exists( 'Sloc_Offline_Geo' ) ) {
			// Small islands and coastal waters can fall outside the bundled country borders.
			$place        = Sloc_Offline_Geo::place( $latitude, $longitude );
			$country_code = $place ? (string) $place['country'] : '';
		}

		// Clean names with the country's rules, such as "London Borough of Camden". Districts are recognized by their
		// names as given, since cleaning "Kenmare Municipal District" leaves what looks like a town.
		$given = $address;
		foreach ( $address as $key => $value ) {
			if ( ! in_array( $key, array( 'country_code', 'postcode', 'house_number' ), true ) ) {
				$address[ $key ] = Sloc_Address_Format::clean( $country_code, $key, $value );
			}
		}

		$region_key = null;
		foreach ( self::osm_region_keys( $country_code ) as $key ) {
			if ( is_string( $key ) && ! empty( $address[ $key ] ) ) {
				$region_key = $key;
				break;
			}
		}
		$region      = $region_key ? $address[ $region_key ] : null;
		$region_code = self::osm_region_code( $json['address'], $region_key, $country_code );
		if ( empty( $region_code ) && ! empty( $region ) ) {
			list( $region, $region_code ) = self::osm_region_name_code( $region, $country_code, 'county' === $region_key ? 'county' : 'state' );
		}

		$number = self::ifnot( $address, array( 'house_number', 'house_name' ) );
		if ( isset( $json['address']['house_number'] ) && $number === $address['house_number'] ) {
			$number = self::osm_house_number( (string) $json['address']['house_number'] );
		}
		$street = self::ifnot( $address, array( 'road', 'highway', 'footway', 'pedestrian' ) );
		if ( self::house_number( $country_code ) ) {
			$street_address = $number . ' ' . $street;
		} else {
			$street_address = $street . ' ' . $number;
		}

		$suburb = $address['suburb'] ?? null;
		if ( $suburb && self::osm_is_district( $given['suburb'] ) ) {
			$suburb = null;
		}

		$locality_keys = array( 'hamlet', 'village', 'town', 'city', 'municipality', 'croft', 'isolated_dwelling' );
		$locality      = self::ifnot( $address, $locality_keys );
		$district      = null;
		if ( $locality && self::osm_is_district( (string) self::ifnot( $given, $locality_keys ) ) ) {
			// A council district, such as Ireland's "Kenmare Municipal District", isn't a town; it's used, cleaned, only
			// when nothing else is.
			$district = $locality;
			$locality = null;
		}

		$addr = array(
			'name'             => self::ifnot( $address, self::osm_name_keys() ),
			'street-number'    => $number,
			'street'           => $street,
			'street-address'   => trim( $street_address ),
			'extended-address' => self::ifnot( $address, array( 'boro', 'borough', 'neighbourhood', 'neighborhood', 'city_district', 'district', 'subdivision', 'allotments', 'quarter' ) ),
			'locality'         => $locality,
			'region'           => $region,
			'region-code'      => $region_code,
			'country-name'     => $address['country'] ?? null,
			'postal-code'      => self::osm_postcode( $address['postcode'] ?? '', $country_code ),
			'country-code'     => $country_code,
		);
		$addr = $this->osm_fill_place( $addr, $address['state'] ?? '', $latitude, $longitude );

		// A suburb names the part of the city, or stands in for a city the answer and the place data don't have.
		if ( $suburb ) {
			if ( empty( $addr['locality'] ) ) {
				$addr['locality'] = $suburb;
			} elseif ( empty( $addr['extended-address'] ) && ! Geo_Provider::same_name( $suburb, $addr['locality'] ) ) {
				$addr['extended-address'] = $suburb;
			}
		}
		if ( empty( $addr['locality'] ) && $district ) {
			$addr['locality'] = $district;
		}
		if ( empty( $addr['country-name'] ) ) {
			$addr['country-name'] = self::country_name( $country_code );
		}
		return array_filter( $addr );
	}

	/**
	 * Fills a missing city and region from the bundled place data.
	 *
	 * The place is the nearest one, weighted by population, in the same country. It isn't used as the city when it is
	 * in another state than the answer's, so a town across a state line isn't named. The place data's regions are
	 * first-level ones, such as US states or Irish provinces, so an answer without a state can't contradict it.
	 *
	 * @since 6.5.0
	 *
	 * @param array      $addr      Address properties.
	 * @param string     $state     Optional. The answer's state, or other first-level division.
	 * @param float|null $latitude  Optional. Latitude. Default the provider's.
	 * @param float|null $longitude Optional. Longitude. Default the provider's.
	 * @return array Address properties.
	 */
	protected function osm_fill_place( $addr, $state = '', $latitude = null, $longitude = null ) {
		if ( ( ! empty( $addr['locality'] ) && ! empty( $addr['region'] ) ) || empty( $addr['country-code'] ) || ! class_exists( 'Sloc_Offline_Geo' ) ) {
			return $addr;
		}
		$place = Sloc_Offline_Geo::place( $latitude ?? $this->latitude, $longitude ?? $this->longitude, $addr['country-code'] );
		if ( ! $place ) {
			return $addr;
		}
		// GeoNames region codes are ISO 3166-2 codes only for some countries, and 00 means no region.
		$region = ( '00' !== ( $place['region_code'] ?? '' ) ) ? (string) ( $place['region'] ?? '' ) : '';
		if ( empty( $addr['region'] ) && '' !== $region ) {
			$addr['region']      = $region;
			$addr['region-code'] = self::region_code( $region, $addr['country-code'] );
			$state               = '';
		}
		if ( empty( $addr['locality'] ) && ! self::osm_other_region( $region, (string) $state, $addr['country-code'] ) ) {
			$addr['locality'] = $place['name'];
		}
		return $addr;
	}

	/**
	 * Returns whether two region names are known to be different regions: both have an ISO 3166-2 code, and the codes
	 * differ. Names in different languages, such as "Southern District" and "מחוז הדרום", aren't a contradiction.
	 *
	 * @since 6.5.0
	 *
	 * @param string $region       Region name.
	 * @param string $other        Other region name, which may be bilingual, such as "Northern Ireland / Tuaisceart Éireann".
	 * @param string $country_code Country code.
	 * @return bool Whether they are different regions.
	 */
	protected static function osm_other_region( $region, $other, $country_code ) {
		if ( '' === $region || '' === $other || Geo_Provider::same_name( $region, $other ) ) {
			return false;
		}
		$code       = self::region_code( $region, $country_code );
		$other_code = self::osm_region_name_code( $other, $country_code )[1];
		return ! empty( $code ) && ! empty( $other_code ) && strtoupper( $code ) !== strtoupper( $other_code );
	}

	/**
	 * Returns the first of several values separated by semicolons, the OpenStreetMap convention.
	 *
	 * @since 6.5.0
	 *
	 * @param mixed $value Value.
	 * @return string First value, trimmed.
	 */
	public static function osm_value( $value ) {
		$parts = explode( ';', (string) $value );
		return trim( $parts[0] );
	}

	/**
	 * Formats a house number that may list several numbers, such as "46;48" for a two-family house.
	 *
	 * Ascending whole numbers become a range, "46-48"; anything else, such as "130; 130 1/2", keeps the first.
	 *
	 * @since 6.5.0
	 *
	 * @param string $value House number.
	 * @return string House number.
	 */
	public static function osm_house_number( $value ) {
		$parts = array_values( array_filter( array_map( 'trim', explode( ';', $value ) ), 'strlen' ) );
		if ( count( $parts ) < 2 ) {
			return $parts[0] ?? '';
		}
		foreach ( $parts as $index => $part ) {
			if ( ! ctype_digit( $part ) || ( $index > 0 && (int) $part <= (int) $parts[ $index - 1 ] ) ) {
				return $parts[0];
			}
		}
		return $parts[0] . '-' . end( $parts );
	}

	/**
	 * Cleans a postcode: the first of several, with a lost leading zero restored for the US.
	 *
	 * @since 6.5.0
	 *
	 * @param string $value        Postcode.
	 * @param string $country_code Country code.
	 * @return string Postcode.
	 */
	public static function osm_postcode( $value, $country_code ) {
		$value = self::osm_value( $value );
		if ( 'US' === $country_code && preg_match( '/^\d{4}(-\d{4})?$/', $value ) ) {
			$value = '0' . $value;
		}
		return $value;
	}

	/**
	 * Returns the address keys to take the region from, in order, for a country.
	 *
	 * The division the country's addresses name, from the address format table, such as the county in the United
	 * Kingdom and Italy, comes first; then the state, or its equivalent, region, state_district and county.
	 *
	 * @since 6.5.0
	 *
	 * @param string $country_code Country code.
	 * @return string[] Address keys.
	 */
	public static function osm_region_keys( $country_code ) {
		$keys = array( 'state', 'region', 'state_district', 'county' );
		$own  = Sloc_Address_Format::region_key( $country_code );
		if ( $own ) {
			$keys = array_values( array_unique( array_merge( array( $own ), $keys ) ) );
		}

		/**
		 * Filters the address keys a Nominatim-format answer's region is taken from, in order.
		 *
		 * @since 6.5.0
		 *
		 * @param string[] $keys         Address keys, such as state, region, state_district and county.
		 * @param string   $country_code Two-letter country code, or an empty string if it isn't known.
		 */
		$keys = apply_filters( 'sloc_osm_region_keys', $keys, $country_code );
		return is_array( $keys ) ? array_values( $keys ) : array();
	}

	/**
	 * Returns a region name and its code, choosing the name in a bilingual one, such as
	 * "Northern Ireland / Tuaisceart Éireann", whose code is known.
	 *
	 * The code comes from the ISO 3166-2 data, or else from the address format table's names in other languages, such
	 * as Bavaria for Bayern.
	 *
	 * @since 6.5.0
	 *
	 * @param string $region       Region name.
	 * @param string $country_code Country code.
	 * @param string $kind         Optional. 'state' or 'county', which list of the address format table to use.
	 *                             Default 'state'.
	 * @return array Region name and code, which is null if it isn't known.
	 */
	public static function osm_region_name_code( $region, $country_code, $kind = 'state' ) {
		$lookup = function ( $name ) use ( $country_code, $kind ) {
			$code = self::region_code( $name, $country_code );
			return empty( $code ) ? Sloc_Address_Format::code( $country_code, $name, $kind ) : $code;
		};
		$parts  = preg_split( '/\s+[\/|]\s+/u', $region );
		if ( count( $parts ) > 1 ) {
			foreach ( $parts as $part ) {
				$code = $lookup( $part );
				if ( ! empty( $code ) ) {
					return array( $part, $code );
				}
			}
		}
		$code = $lookup( $region );
		return array( $region, empty( $code ) ? null : $code );
	}

	/**
	 * Returns whether a suburb or city is an administrative district rather than a neighborhood or town, such as
	 * "Manhattan Community Board 5", "Ward 3", Ireland's "Kenmare Municipal District" or an Israeli regional council.
	 *
	 * @since 6.5.0
	 *
	 * @param string $name Suburb or city.
	 * @return bool Whether it is a district.
	 */
	public static function osm_is_district( $name ) {
		return (bool) preg_match( '/(^|\s)(Community (Board|District)|Ward|District|Council District)\s+\d+[A-Z]?$|\b(Municipal|Metropolitan) District\b|\bRegional Council\b|^מועצה אזורית\s/iu', $name );
	}


	/**
	 * Returns the region code from Nominatim's ISO 3166-2 keys, such as "ISO3166-2-lvl4": "US-PA".
	 *
	 * Only a code at an administrative level that matches the kind of region chosen, and in the address's country, is
	 * used.
	 *
	 * @since 6.5.0
	 *
	 * @param array       $address      Address from the answer.
	 * @param string|null $region_key   Address key the region came from.
	 * @param string      $country_code Country code.
	 * @return string|null Region code without the country, such as "PA", or null.
	 */
	public static function osm_region_code( $address, $region_key, $country_code ) {
		if ( ! empty( $address['state_code'] ) && is_string( $address['state_code'] ) ) {
			return strtoupper( $address['state_code'] );
		}
		$levels = array(
			'state'          => array( 4, 3, 5 ),
			'region'         => array( 3, 4 ),
			'state_district' => array( 5, 6 ),
			'county'         => array( 6, 5, 7 ),
		);
		if ( '' === $country_code || null === $region_key || ! isset( $levels[ $region_key ] ) ) {
			return null;
		}
		foreach ( $levels[ $region_key ] as $level ) {
			$code = $address[ 'ISO3166-2-lvl' . $level ] ?? null;
			if ( is_string( $code ) && 0 === stripos( $code, $country_code . '-' ) ) {
				return strtoupper( substr( $code, strlen( $country_code ) + 1 ) );
			}
		}
		return null;
	}
}

<?php
/**
 * Parsing of Nominatim-style OpenStreetMap addresses.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Converts a Nominatim-style `address` object to microformats2 address properties.
 *
 * Nominatim, LocationIQ and Traccar Geocoder all answer with OpenStreetMap data in this format, so they share one
 * parser. It cleans what OpenStreetMap data holds as entered: several values separated by semicolons, US postcodes
 * that lost a leading zero, administrative districts given as a suburb, and "Village of" style names. What an answer
 * leaves out, such as the country from a server built from US state extracts, or the city in New York City's
 * boroughs, is filled in from the location and the bundled place data.
 *
 * Used by classes extending Geo_Provider.
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
	 * Converts an answer to microformats2 address properties.
	 *
	 * @since 6.5.0 Moved from Geo_Provider_Nominatim::address_to_mf() and shared with Traccar Geocoder.
	 *
	 * @param array $json Answer, with 'address'.
	 * @return array Microformats2 address elements, or an empty array without an address.
	 */
	protected function osm_address_to_mf( $json ) {
		if ( ! is_array( $json ) || ! isset( $json['address'] ) || ! is_array( $json['address'] ) ) {
			return array();
		}
		$address = array_map( array( __CLASS__, 'osm_value' ), array_filter( $json['address'], 'is_scalar' ) );

		$country_code = strtoupper( $address['country_code'] ?? '' );
		if ( '' === $country_code ) {
			// A server built from extracts smaller than a country, such as US states, has no country boundary.
			$country_code = self::country_for_location( $this->latitude, $this->longitude );
		}
		if ( '' === $country_code && class_exists( 'Sloc_Offline_Geo' ) ) {
			// Small islands and coastal waters can fall outside the bundled country borders.
			$place        = Sloc_Offline_Geo::place( $this->latitude, $this->longitude );
			$country_code = $place ? (string) $place['country'] : '';
		}

		$region_keys = in_array( $country_code, array( 'US', 'FR' ), true ) ? array( 'state', 'county' ) : array( 'region', 'county', 'state', 'state_district' );
		$region_key  = null;
		foreach ( $region_keys as $key ) {
			if ( ! empty( $address[ $key ] ) ) {
				$region_key = $key;
				break;
			}
		}
		$region = $region_key ? $address[ $region_key ] : null;

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
		if ( $suburb && self::osm_is_district( $suburb ) ) {
			$suburb = null;
		}

		$locality = self::ifnot( $address, array( 'hamlet', 'village', 'town', 'city', 'municipality', 'croft', 'isolated_dwelling' ) );
		if ( $locality && 'US' === $country_code ) {
			$locality = self::osm_strip_prefix( $locality );
		}

		$addr = array(
			'name'             => self::ifnot( $address, self::osm_name_keys() ),
			'street-number'    => $number,
			'street'           => $street,
			'street-address'   => trim( $street_address ),
			'extended-address' => self::ifnot( $address, array( 'boro', 'borough', 'neighbourhood', 'neighborhood', 'city_district', 'district', 'subdivision', 'allotments', 'quarter' ) ),
			'locality'         => $locality,
			'region'           => $region,
			'region-code'      => self::osm_region_code( $json['address'], $region_key, $country_code ),
			'country-name'     => $address['country'] ?? null,
			'postal-code'      => self::osm_postcode( $address['postcode'] ?? '', $country_code ),
			'country-code'     => $country_code,
			'latitude'         => $this->latitude,
			'longitude'        => $this->longitude,
			'altitude'         => $this->altitude,
			'raw'              => $json,
		);
		if ( empty( $addr['region-code'] ) && ! empty( $region ) ) {
			$addr['region-code'] = self::region_code( $region, $country_code );
		}
		$addr = $this->osm_fill_place( $addr );

		// A suburb names the part of the city, or stands in for a city the answer and the place data don't have.
		if ( $suburb ) {
			if ( empty( $addr['locality'] ) ) {
				$addr['locality'] = $suburb;
			} elseif ( empty( $addr['extended-address'] ) && ! self::same_name( $suburb, $addr['locality'] ) ) {
				$addr['extended-address'] = $suburb;
			}
		}
		if ( empty( $addr['country-name'] ) ) {
			$addr['country-name'] = self::country_name( $country_code );
		}
		if ( isset( $json['extratags'] ) && is_array( $json['extratags'] ) ) {
			$addr['url']   = $json['extratags']['website'] ?? null;
			$addr['photo'] = $json['extratags']['image'] ?? null;
		}
		$addr = array_filter( $addr );
		if ( ! array_key_exists( 'display-name', $addr ) ) {
			$addr['display-name'] = $this->display_name( $addr );
		}
		$tz = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		return $addr;
	}

	/**
	 * Fills a missing city and region from the bundled place data.
	 *
	 * The place is the nearest one, weighted by population, in the same country. It is used as the city only when it is
	 * in the answer's region, so a town across a state line isn't named.
	 *
	 * @since 6.5.0
	 *
	 * @param array $addr Address properties.
	 * @return array Address properties.
	 */
	protected function osm_fill_place( $addr ) {
		if ( ( ! empty( $addr['locality'] ) && ! empty( $addr['region'] ) ) || empty( $addr['country-code'] ) || ! class_exists( 'Sloc_Offline_Geo' ) ) {
			return $addr;
		}
		$place = Sloc_Offline_Geo::place( $this->latitude, $this->longitude, $addr['country-code'] );
		if ( ! $place ) {
			return $addr;
		}
		// GeoNames region codes are ISO 3166-2 codes only for some countries, and 00 means no region.
		$region = ( '00' !== ( $place['region_code'] ?? '' ) ) ? (string) ( $place['region'] ?? '' ) : '';
		if ( empty( $addr['region'] ) && '' !== $region ) {
			$addr['region']      = $region;
			$addr['region-code'] = self::region_code( $region, $addr['country-code'] );
		}
		if ( empty( $addr['locality'] ) && ( empty( $addr['region'] ) || '' === $region || self::osm_same_region( $region, $addr ) ) ) {
			$addr['locality'] = $place['name'];
		}
		return $addr;
	}

	/**
	 * Returns whether a region name is the address's region, by name or by its ISO 3166-2 code.
	 *
	 * @since 6.5.0
	 *
	 * @param string $region Region name.
	 * @param array  $addr   Address properties, with 'region' and maybe 'region-code' and 'country-code'.
	 * @return bool Whether they are the same.
	 */
	protected static function osm_same_region( $region, $addr ) {
		if ( self::same_name( $region, $addr['region'] ) ) {
			return true;
		}
		$code = self::region_code( $region, $addr['country-code'] ?? '' );
		return ! empty( $code ) && ! empty( $addr['region-code'] ) && strtoupper( $code ) === strtoupper( $addr['region-code'] );
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
	 * Returns whether a suburb is a numbered administrative district rather than a neighborhood, such as
	 * "Manhattan Community Board 5" or "Ward 3".
	 *
	 * @since 6.5.0
	 *
	 * @param string $name Suburb.
	 * @return bool Whether it is a district.
	 */
	public static function osm_is_district( $name ) {
		return (bool) preg_match( '/(^|\s)(Community (Board|District)|Ward|District|Council District)\s+\d+[A-Z]?$/i', $name );
	}

	/**
	 * Removes a "Village of", "Town of", "City of" or "Borough of" prefix from a US place name.
	 *
	 * @since 6.5.0
	 *
	 * @param string $name Place name.
	 * @return string Name.
	 */
	public static function osm_strip_prefix( $name ) {
		$stripped = preg_replace( '/^(Village|Town|City|Borough) of\s+/i', '', $name );
		return '' === $stripped ? $name : $stripped;
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

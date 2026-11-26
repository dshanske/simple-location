<?php
/**
 * Final touches to every looked-up address.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Applies the site's choices to an address from any provider: the country a site chooses for a location, and country
 * and region names in the site's language, keeping the names the provider gave.
 *
 * Used for the geocoding chain, lookups with a named provider, and venue searches.
 *
 * @since 6.5.0
 */
class Sloc_Address_Finisher {

	/**
	 * Returns the country codes the site uses instead of others, such as array( 'PS' => 'IL' ).
	 *
	 * The default comes from the SLOC_COUNTRY_CODES constant, which can be set in wp-config.php.
	 *
	 * @since 6.5.0
	 *
	 * @return array Country code => the code to use instead, both uppercase.
	 */
	public static function country_codes() {
		$codes = defined( 'SLOC_COUNTRY_CODES' ) && is_array( constant( 'SLOC_COUNTRY_CODES' ) ) ? constant( 'SLOC_COUNTRY_CODES' ) : array();

		/**
		 * Filters the country codes used instead of others.
		 *
		 * The bundled data and the geocoders decide a location's country by who controls it, or by ISO 3166, without
		 * taking a position on disputed areas. A site can choose otherwise, for every location with a code.
		 *
		 * @since 6.5.0
		 *
		 * @param array $codes Country code => the code to use instead, such as array( 'PS' => 'IL' ). Default the
		 *                     SLOC_COUNTRY_CODES constant, or none.
		 */
		$codes = apply_filters( 'sloc_country_codes', $codes );
		$clean = array();
		foreach ( is_array( $codes ) ? $codes : array() as $from => $to ) {
			if ( is_string( $from ) && is_string( $to ) && preg_match( '/^[A-Za-z]{2}$/', $from ) && preg_match( '/^[A-Za-z]{2}$/', $to ) ) {
				$clean[ strtoupper( $from ) ] = strtoupper( $to );
			}
		}
		return $clean;
	}

	/**
	 * Returns the country the site uses for a location.
	 *
	 * @since 6.5.0
	 *
	 * @param string     $code      Country code from the data or a provider; may be empty.
	 * @param float|null $latitude  Latitude, if known.
	 * @param float|null $longitude Longitude, if known.
	 * @return string Country code, uppercase, or an empty string.
	 */
	public static function country( $code, $latitude = null, $longitude = null ) {
		$code  = strtoupper( (string) $code );
		$codes = self::country_codes();
		$code  = $codes[ $code ] ?? $code;

		/**
		 * Filters the country of a location.
		 *
		 * For choices that depend on where the location is, such as a disputed area within a country.
		 *
		 * @since 6.5.0
		 *
		 * @param string     $code      Country code, after the sloc_country_codes map; may be empty.
		 * @param float|null $latitude  Latitude, if known.
		 * @param float|null $longitude Longitude, if known.
		 */
		$code = apply_filters( 'sloc_country_for_location', $code, $latitude, $longitude );
		return is_string( $code ) && preg_match( '/^[A-Za-z]{2}$/', $code ) ? strtoupper( $code ) : '';
	}

	/**
	 * Finishes an address: the site's country, and names in the site's language with the provider's kept.
	 *
	 * When the country changes, its name follows, and a region code the new country doesn't have is dropped. Country
	 * and region names the site's language has are used, and the ones the provider gave are kept under 'local'. The
	 * display name is rebuilt when anything changed.
	 *
	 * @since 6.5.0
	 *
	 * @param array|mixed $addr Microformats2 address properties.
	 * @return array|mixed The address, or what was given if it isn't one.
	 */
	public static function finish( $addr ) {
		if ( ! is_array( $addr ) ) {
			return $addr;
		}
		$changed   = false;
		$latitude  = isset( $addr['latitude'] ) && is_numeric( $addr['latitude'] ) ? (float) $addr['latitude'] : null;
		$longitude = isset( $addr['longitude'] ) && is_numeric( $addr['longitude'] ) ? (float) $addr['longitude'] : null;
		$given     = strtoupper( (string) ( $addr['country-code'] ?? '' ) );
		$code      = self::country( $given, $latitude, $longitude );
		if ( $code !== $given && '' !== $code ) {
			$addr['country-code'] = $code;
			$addr['country-name'] = Geo_Provider::country_name( $code );
			if ( ! empty( $addr['region-code'] ) && ! Geo_Provider::region_name( (string) $addr['region-code'], $code ) ) {
				unset( $addr['region-code'] );
			}
			$changed = true;
		}
		if ( '' === $code ) {
			return $addr;
		}

		$local   = is_array( $addr['local'] ?? null ) ? $addr['local'] : array();
		$country = Sloc_Address_Format::country_name( $code );
		if ( $country && ( $addr['country-name'] ?? '' ) !== $country ) {
			if ( ! empty( $addr['country-name'] ) && ! isset( $local['country-name'] ) ) {
				$local['country-name'] = $addr['country-name'];
			}
			$addr['country-name'] = $country;
			$changed              = true;
		}
		$region = empty( $addr['region-code'] ) ? null : Sloc_Address_Format::region_name( $code, (string) $addr['region-code'] );
		if ( $region && ( $addr['region'] ?? '' ) !== $region ) {
			if ( ! empty( $addr['region'] ) && ! isset( $local['region'] ) ) {
				$local['region'] = $addr['region'];
			}
			$addr['region'] = $region;
			$changed        = true;
		}
		if ( $local ) {
			$addr['local'] = $local;
		}
		if ( $changed && array_key_exists( 'display-name', $addr ) ) {
			$display = Geo_Provider::format_display_name( array_diff_key( $addr, array( 'display-name' => true ) ) );
			if ( $display ) {
				$addr['display-name'] = $display;
			}
		}
		return $addr;
	}
}

<?php
/**
 * Per-country address conventions.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads the address format table in data/address-formats.json, built from OpenCage address-formatting
 * (https://github.com/OpenCageData/address-formatting, MIT) by bin/update-data.php.
 *
 * It says, per country, whether the house number comes before the street, which division the address names as its
 * region, how to clean names such as "London Borough of Camden", and the codes of regions and counties by name.
 *
 * @since 6.5.0
 */
class Sloc_Address_Format {

	/**
	 * The table, once read.
	 *
	 * @since 6.5.0
	 * @var array|null
	 */
	protected static $table = null;

	/**
	 * The region names, once read.
	 *
	 * @since 6.5.0
	 * @var array|null
	 */
	protected static $names = null;

	/**
	 * Returns the path of the table.
	 *
	 * @since 6.5.0
	 *
	 * @return string Path.
	 */
	public static function file() {
		/**
		 * Filters the path of the address format table.
		 *
		 * @since 6.5.0
		 *
		 * @param string $file Path of data/address-formats.json.
		 */
		return (string) apply_filters( 'sloc_address_format_file', plugin_dir_path( __DIR__ ) . 'data/address-formats.json' );
	}

	/**
	 * Forgets the table, so it is read again.
	 *
	 * @since 6.5.0
	 */
	public static function reset() {
		self::$table = null;
		self::$names = null;
	}

	/**
	 * Returns the region names built from Wikidata in data/region-names.json, read only when needed.
	 *
	 * @since 6.5.0
	 *
	 * @return array 'names' (country => lowercased name => code) and 'english' (country => code => English name).
	 */
	protected static function names() {
		if ( null === self::$names ) {
			/**
			 * Filters the path of the region names built from Wikidata.
			 *
			 * @since 6.5.0
			 *
			 * @param string $file Path of data/region-names.json.
			 */
			$file        = (string) apply_filters( 'sloc_region_names_file', plugin_dir_path( __DIR__ ) . 'data/region-names.json' );
			$json        = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file.
			self::$names = array(
				'names'   => is_array( $json['names'] ?? null ) ? $json['names'] : array(),
				'english' => is_array( $json['english'] ?? null ) ? $json['english'] : array(),
			);
		}
		return self::$names;
	}

	/**
	 * Returns a country's entry.
	 *
	 * @since 6.5.0
	 *
	 * @param string $country_code Country code.
	 * @return array Entry, empty if the country isn't in the table or the table is missing.
	 */
	public static function country( $country_code ) {
		if ( null === self::$table ) {
			$file        = self::file();
			$json        = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file.
			self::$table = is_array( $json['countries'] ?? null ) ? $json['countries'] : array();
		}
		$entry = self::$table[ strtoupper( (string) $country_code ) ] ?? array();
		return is_array( $entry ) ? $entry : array();
	}

	/**
	 * Returns whether the house number comes before the street.
	 *
	 * @since 6.5.0
	 *
	 * @param string $country_code Country code.
	 * @return bool Whether it does; true when the table doesn't say.
	 */
	public static function number_first( $country_code ) {
		$entry = self::country( $country_code );
		return isset( $entry['number_first'] ) ? (bool) $entry['number_first'] : true;
	}

	/**
	 * Returns whether addresses show the region's code, such as NY in "Albany, NY", rather than its name.
	 *
	 * True for the United States, Canada, Australia, Brazil, Mexico, Italy (province codes such as RM) and a few
	 * others. Without the table, true, as before it existed.
	 *
	 * @since 6.5.0
	 *
	 * @param string $country_code Country code.
	 * @return bool Whether they do.
	 */
	public static function shows_region_code( $country_code ) {
		$entry = self::country( $country_code );
		if ( empty( self::$table ) ) {
			return true;
		}
		return ! empty( $entry['region_code'] );
	}

	/**
	 * Returns the address key an address names as its region, such as state, or county in the United Kingdom.
	 *
	 * @since 6.5.0
	 *
	 * @param string $country_code Country code.
	 * @return string|null Address key, or null if the table doesn't say.
	 */
	public static function region_key( $country_code ) {
		$entry = self::country( $country_code );
		return isset( $entry['region'] ) && is_string( $entry['region'] ) ? $entry['region'] : null;
	}

	/**
	 * Cleans a name with the country's rules, such as removing "London Borough of" in the United Kingdom.
	 *
	 * @since 6.5.0
	 *
	 * @param string $country_code Country code.
	 * @param string $component    Address key, such as county.
	 * @param string $value        Name.
	 * @return string Cleaned name, or the name if cleaning would leave nothing.
	 */
	public static function clean( $country_code, $component, $value ) {
		$entry = self::country( $country_code );
		$clean = (string) $value;
		foreach ( is_array( $entry['rules'] ?? null ) ? $entry['rules'] : array() as $rule ) {
			if ( ! is_array( $rule ) || 3 !== count( $rule ) || ( '' !== $rule[0] && $rule[0] !== $component ) ) {
				continue;
			}
			$result = @preg_replace( (string) $rule[1], (string) $rule[2], $clean ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A bad rule leaves the name as it is.
			if ( is_string( $result ) ) {
				$clean = $result;
			}
		}
		$clean = trim( $clean );
		return '' === $clean ? (string) $value : $clean;
	}

	/**
	 * Returns the ISO 3166-2 code of a region or county by name, in the country's languages or English.
	 *
	 * OpenCage's names come first, then Wikidata's, which cover the regions of about 200 countries in their own
	 * languages, such as Бургас for BG-02 or מחוז הדרום for IL-D.
	 *
	 * @since 6.5.0
	 *
	 * @param string $country_code Country code.
	 * @param string $name         Name, such as Bavaria or Bayern.
	 * @param string $kind         Optional. 'state' or 'county', which of OpenCage's lists to look in first. Default
	 *                             'state'.
	 * @return string|null Code without the country, such as BY, or null if it isn't known.
	 */
	public static function code( $country_code, $name, $kind = 'state' ) {
		$country = strtoupper( (string) $country_code );
		$entry   = self::country( $country );
		$list    = 'county' === $kind ? ( $entry['counties'] ?? array() ) : ( $entry['states'] ?? array() );
		$key     = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $name ), 'UTF-8' ) : strtolower( trim( (string) $name ) );
		if ( '' === $key ) {
			return null;
		}
		if ( is_array( $list ) && isset( $list[ $key ] ) ) {
			return (string) $list[ $key ];
		}
		$names = self::names()['names'][ $country ] ?? array();
		return is_array( $names ) && isset( $names[ $key ] ) ? (string) $names[ $key ] : null;
	}

	/**
	 * Returns the English name of a region, from Wikidata, such as Southern District for IL-D.
	 *
	 * @since 6.5.0
	 *
	 * @param string $country_code Country code.
	 * @param string $code         Region code without the country, such as D.
	 * @return string|null English name, or null if it isn't known.
	 */
	public static function english_region( $country_code, $code ) {
		$names = self::names()['english'][ strtoupper( (string) $country_code ) ] ?? array();
		$code  = strtoupper( (string) $code );
		return is_array( $names ) && isset( $names[ $code ] ) && is_string( $names[ $code ] ) ? $names[ $code ] : null;
	}
}

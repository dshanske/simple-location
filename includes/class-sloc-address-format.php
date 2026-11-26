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
	 * Returns the code of a region or county by name, in any of the languages the table has.
	 *
	 * @since 6.5.0
	 *
	 * @param string $country_code Country code.
	 * @param string $name         Name, such as Bavaria or Bayern.
	 * @param string $kind         Optional. 'state' or 'county'. Default 'state'.
	 * @return string|null Code, such as BY, or null if it isn't known.
	 */
	public static function code( $country_code, $name, $kind = 'state' ) {
		$entry = self::country( $country_code );
		$list  = 'county' === $kind ? ( $entry['counties'] ?? array() ) : ( $entry['states'] ?? array() );
		$key   = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $name ), 'UTF-8' ) : strtolower( trim( (string) $name ) );
		return is_array( $list ) && isset( $list[ $key ] ) ? (string) $list[ $key ] : null;
	}
}

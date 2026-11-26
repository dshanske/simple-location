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
	 * Country and region names by language, once read.
	 *
	 * @since 6.5.0
	 * @var array
	 */
	protected static $localized = array();

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
		self::$table     = null;
		self::$names     = null;
		self::$localized = array();
	}

	/**
	 * Returns the region names built from Wikidata in data/region-names.json, read only when needed.
	 *
	 * @since 6.5.0
	 *
	 * @return array 'names': country => lowercased name => code.
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
			self::$names = array( 'names' => is_array( $json['names'] ?? null ) ? $json['names'] : array() );
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
	 * Returns the site's language, as the names data has it: the language part of the locale, such as fr for fr_CA.
	 *
	 * @since 6.5.0
	 *
	 * @return string Language code, such as en.
	 */
	public static function site_language() {
		$locale   = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$language = strtolower( (string) strtok( (string) $locale, '_-' ) );

		/**
		 * Filters the language country and region names are shown in.
		 *
		 * @since 6.5.0
		 *
		 * @param string $language Language code, such as en, from the site's locale.
		 * @param string $locale   The site's locale, such as en_US.
		 */
		return (string) apply_filters( 'sloc_address_language', '' === $language ? 'en' : $language, (string) $locale );
	}

	/**
	 * Returns the country and region names in a language, built from Wikidata in data/names/{language}.json.
	 *
	 * @since 6.5.0
	 *
	 * @param string $language Language code.
	 * @return array 'countries' (code => name) and 'regions' (ISO 3166-2 code => name), empty without data.
	 */
	protected static function localized( $language ) {
		$language = strtolower( (string) $language );
		if ( ! isset( self::$localized[ $language ] ) ) {
			$json = null;
			if ( preg_match( '/^[a-z]{2,3}$/', $language ) ) {
				/**
				 * Filters the folder of the country and region names by language.
				 *
				 * @since 6.5.0
				 *
				 * @param string $dir Path of data/names/, with a trailing slash.
				 */
				$file = trailingslashit( (string) apply_filters( 'sloc_address_names_dir', plugin_dir_path( __DIR__ ) . 'data/names/' ) ) . $language . '.json';
				$json = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file.
			}
			self::$localized[ $language ] = array(
				'countries' => is_array( $json['countries'] ?? null ) ? $json['countries'] : array(),
				'regions'   => is_array( $json['regions'] ?? null ) ? $json['regions'] : array(),
			);
		}
		return self::$localized[ $language ];
	}

	/**
	 * Returns a country's name in a language, such as Israël for IL in French.
	 *
	 * @since 6.5.0
	 *
	 * @param string $country_code Country code.
	 * @param string $language     Optional. Language code. Default the site's language.
	 * @return string|null Name, or null if it isn't known.
	 */
	public static function country_name( $country_code, $language = '' ) {
		$names = self::localized( '' === $language ? self::site_language() : $language )['countries'];
		$name  = $names[ strtoupper( (string) $country_code ) ] ?? null;
		return is_string( $name ) && '' !== $name ? $name : null;
	}

	/**
	 * Returns a region's name in a language, such as Southern District for IL-D in English.
	 *
	 * @since 6.5.0
	 *
	 * @param string $country_code Country code.
	 * @param string $code         Region code without the country, such as D.
	 * @param string $language     Optional. Language code. Default the site's language.
	 * @return string|null Name, or null if it isn't known.
	 */
	public static function region_name( $country_code, $code, $language = '' ) {
		$names = self::localized( '' === $language ? self::site_language() : $language )['regions'];
		$name  = $names[ strtoupper( $country_code . '-' . $code ) ] ?? null;
		return is_string( $name ) && '' !== $name ? $name : null;
	}
}

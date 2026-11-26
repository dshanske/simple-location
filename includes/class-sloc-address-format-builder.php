<?php
/**
 * Builds the address format table from OpenCage address-formatting.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns OpenCage's address-formatting configuration into the compact table in data/address-formats.json.
 *
 * Used at development time by bin/update-data.php, which parses the YAML; the plugin only reads the table, through
 * Sloc_Address_Format. Source: https://github.com/OpenCageData/address-formatting (MIT).
 *
 * @since 6.5.0
 */
class Sloc_Address_Format_Builder {

	/**
	 * Address components that name the city, whose fallback chains don't say where the region goes.
	 *
	 * @since 6.5.0
	 * @var string[]
	 */
	const LOCALITY_COMPONENTS = array( 'city', 'town', 'village', 'hamlet', 'municipality', 'postal_city', 'suburb', 'city_district', 'neighbourhood', 'island' );

	/**
	 * Region components, as they appear in templates, and the address key each stands for.
	 *
	 * @since 6.5.0
	 * @var array
	 */
	const REGION_COMPONENTS = array(
		'state_code'     => 'state',
		'state'          => 'state',
		'county_code'    => 'county',
		'county'         => 'county',
		'state_district' => 'state_district',
		'region'         => 'region',
		'province'       => 'state',
	);

	/**
	 * Cleanup rules of our own, run before OpenCage's: component, PHP regular expression and replacement, by country.
	 *
	 * Ireland's comes first so "Municipal District of Cobh" becomes Cobh, not "of Cobh" after OpenCage's rule that
	 * removes "Municipal District".
	 *
	 * @since 6.5.0
	 * @var array
	 */
	const FIRST_RULES = array(
		'IE' => array(
			array( '', '/^(The )?(Municipal|Metropolitan) District of\s+/iu', '' ),
		),
	);

	/**
	 * Cleanup rules of our own, added to OpenCage's: component, PHP regular expression and replacement, by country.
	 *
	 * @since 6.5.0
	 * @var array
	 */
	const EXTRA_RULES = array(
		'US' => array(
			array( '', '/^(Village|Town|City|Borough|Township) of\s+/iu', '' ),
		),
	);

	/**
	 * Builds the table.
	 *
	 * @since 6.5.0
	 *
	 * @param array $worldwide    Parsed conf/countries/worldwide.yaml.
	 * @param array $state_codes  Parsed conf/state_codes.yaml.
	 * @param array $county_codes Parsed conf/county_codes.yaml.
	 * @param array $source       Optional. Where the data came from, such as the repository and commit.
	 * @param array $iso          Optional. ISO 3166-2 codes without the country prefix, by country; region and county
	 *                            codes not among them are left out, so codes are always ISO codes. Default keeps all.
	 * @return array Table: 'source', and 'countries', keyed by country code (left out when there's nothing to say), each with 'number_first' (bool, or absent
	 *               when the template doesn't say), 'region' (address key, or absent), 'region_code' (true when addresses
	 *               show the region's code, such as NY, rather than its name), 'rules' (each component, pattern
	 *               and replacement), 'states' and 'counties' (lowercased name => code).
	 */
	public static function build( $worldwide, $state_codes, $county_codes, $source = array(), $iso = null ) {
		$countries = array();
		foreach ( array_keys( $worldwide ) as $code ) {
			if ( ! is_string( $code ) || ! preg_match( '/^[A-Z]{2}$/', $code ) ) {
				continue;
			}
			$config = self::resolve( $worldwide, $code );
			$main   = is_string( $config['address_template'] ?? null ) ? $config['address_template'] : '';
			$number = self::number_first( $main );
			$region = self::region_component( $main );
			$entry  = array_filter(
				array(
					'number_first' => $number,
					'region'       => $region ?? self::region_component( is_string( $config['fallback_template'] ?? null ) ? $config['fallback_template'] : '' ),
					'region_code'  => $region && self::region_component( $main, true ) ? true : null,
					'rules'        => self::rules( $config, $code ),
					'states'       => self::codes( $state_codes[ $code ] ?? array(), is_array( $iso ) ? ( $iso[ $code ] ?? array() ) : null ),
					'counties'     => self::codes( $county_codes[ $code ] ?? array(), is_array( $iso ) ? ( $iso[ $code ] ?? array() ) : null ),
				),
				fn( $value ) => null !== $value && array() !== $value
			);
			if ( $entry ) {
				$countries[ $code ] = $entry;
			}
		}
		ksort( $countries );
		return array(
			'source'    => $source,
			'countries' => $countries,
		);
	}

	/**
	 * Returns a country's configuration, following use_country to the country whose format it shares.
	 *
	 * @since 6.5.0
	 *
	 * @param array  $worldwide Parsed worldwide.yaml.
	 * @param string $code      Country code.
	 * @return array Configuration.
	 */
	public static function resolve( $worldwide, $code ) {
		$seen   = array();
		$config = $worldwide[ $code ] ?? array();
		while ( is_array( $config ) && isset( $config['use_country'] ) && is_string( $config['use_country'] ) && ! isset( $seen[ $config['use_country'] ] ) ) {
			$seen[ $config['use_country'] ] = true;
			$own                            = $config;
			$config                         = $worldwide[ strtoupper( $config['use_country'] ) ] ?? array();
			if ( is_array( $config ) && isset( $own['replace'] ) ) {
				$config['replace'] = array_merge( $own['replace'], $config['replace'] ?? array() );
			}
		}
		return is_array( $config ) ? $config : array();
	}

	/**
	 * Returns whether a template puts the house number before the street.
	 *
	 * @since 6.5.0
	 *
	 * @param string $template Template.
	 * @return bool|null Whether it does, or null if no line has both.
	 */
	public static function number_first( $template ) {
		foreach ( explode( "\n", $template ) as $line ) {
			$number = strpos( $line, '{{{house_number}}}' );
			$road   = strpos( $line, '{{{road}}}' );
			if ( false !== $number && false !== $road ) {
				return $number < $road;
			}
		}
		return null;
	}

	/**
	 * Returns the address key a template names as the region.
	 *
	 * Fallback chains that stand in for the city or the country are left out, such as "city || county || state" in
	 * Germany's template; the first region component left is the one the address shows.
	 *
	 * @since 6.5.0
	 *
	 * @param string $template Template.
	 * @param bool   $code     Optional. Whether to return the key only when the template shows the region's code, such
	 *                         as state_code for "Albany, NY". Default false.
	 * @return string|null Address key, such as state or county, or null if the template names none.
	 */
	public static function region_component( $template, $code = false ) {
		$template = preg_replace_callback(
			'/\{\{#first\}\}(.*?)\{\{\/first\}\}/s',
			function ( $match ) {
				preg_match_all( '/\{\{\{([a-z_]+)\}\}\}/', $match[1], $names );
				return array_intersect( $names[1], array_merge( self::LOCALITY_COMPONENTS, array( 'country' ) ) ) ? '' : $match[0];
			},
			$template
		);
		preg_match_all( '/\{\{\{([a-z_]+)\}\}\}/', (string) $template, $names );
		foreach ( $names[1] as $name ) {
			if ( isset( self::REGION_COMPONENTS[ $name ] ) ) {
				return ! $code || '_code' === substr( $name, -5 ) ? self::REGION_COMPONENTS[ $name ] : null;
			}
		}
		return null;
	}

	/**
	 * Returns a country's cleanup rules as PHP regular expressions.
	 *
	 * OpenCage's replace rules apply to components, those starting with "component=" to that one only. Its
	 * postformat_replace rules apply to the formatted address; those within one line are used on components too. Rules
	 * that don't compile are left out.
	 *
	 * @since 6.5.0
	 *
	 * @param array  $config Country configuration.
	 * @param string $code   Country code.
	 * @return array[] Rules: component (empty for all), pattern and replacement.
	 */
	public static function rules( $config, $code ) {
		$rules = array();
		$lists = array(
			is_array( $config['replace'] ?? null ) ? $config['replace'] : array(),
			is_array( $config['postformat_replace'] ?? null ) ? $config['postformat_replace'] : array(),
		);
		foreach ( $lists as $index => $list ) {
			foreach ( $list as $rule ) {
				if ( ! is_array( $rule ) || 2 !== count( $rule ) || ! is_string( $rule[0] ) || ! is_scalar( $rule[1] ) ) {
					continue;
				}
				$component = '';
				$pattern   = $rule[0];
				if ( 0 === $index && preg_match( '/^([a-z_]+)=(.*)$/s', $pattern, $match ) ) {
					$component = $match[1];
					$pattern   = $match[2];
				}
				$replacement = (string) $rule[1];
				if ( 1 === $index && ( preg_match( '/\\n|\n/', $pattern ) || preg_match( '/\\n|\n/', $replacement ) ) ) {
					continue;
				}
				$regex = '/' . str_replace( '/', '\/', $pattern ) . '/u';
				if ( false === @preg_match( $regex, '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Checks whether the expression compiles.
					continue;
				}
				$rules[] = array( $component, $regex, $replacement );
			}
		}
		return array_merge( self::FIRST_RULES[ $code ] ?? array(), $rules, self::EXTRA_RULES[ $code ] ?? array() );
	}

	/**
	 * Returns a name to code map from OpenCage's state or county codes for one country.
	 *
	 * @since 6.5.0
	 *
	 * @param array      $list  Code => name, or code => names (default, alt, alt_en and other languages).
	 * @param array|null $valid Optional. Codes to keep. Default all.
	 * @return array Lowercased name => code.
	 */
	public static function codes( $list, $valid = null ) {
		$map = array();
		foreach ( is_array( $list ) ? $list : array() as $code => $names ) {
			if ( is_array( $valid ) && ! in_array( (string) $code, $valid, true ) ) {
				continue;
			}
			foreach ( is_array( $names ) ? $names : array( $names ) as $name ) {
				if ( is_string( $name ) && '' !== trim( $name ) ) {
					$map[ self::normalize( $name ) ] = (string) $code;
				}
			}
		}
		ksort( $map );
		return $map;
	}

	/**
	 * Normalizes a name for lookup.
	 *
	 * @since 6.5.0
	 *
	 * @param string $name Name.
	 * @return string Lowercased, trimmed name.
	 */
	public static function normalize( $name ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $name ), 'UTF-8' ) : strtolower( trim( $name ) );
	}

	/**
	 * Language codes to add to, or correct in, OpenCage's country languages, for region names.
	 *
	 * OpenCage lists Japan as jp and Belarus as by, and gives India and Morocco fewer languages than their place names
	 * use in OpenStreetMap.
	 *
	 * @since 6.5.0
	 * @var array
	 */
	const EXTRA_LANGUAGES = array(
		'IN' => array( 'ta', 'te', 'kn', 'ml', 'bn', 'mr', 'gu' ),
		'MA' => array( 'zgh' ),
		'DZ' => array( 'zgh' ),
	);

	/**
	 * Returns the languages to read region names in, by country: OpenCage's country languages, corrected and added to,
	 * and English.
	 *
	 * @since 6.5.0
	 *
	 * @param array $country2lang Parsed conf/country2lang.yaml: country code => comma-separated language codes.
	 * @return array Country code => language codes.
	 */
	public static function languages( $country2lang ) {
		$fix       = array(
			'jp' => 'ja',
			'by' => 'be',
		);
		$languages = array();
		foreach ( is_array( $country2lang ) ? $country2lang : array() as $code => $list ) {
			if ( ! is_string( $code ) || ! preg_match( '/^[A-Z]{2}$/', $code ) ) {
				continue;
			}
			$codes = array();
			foreach ( explode( ',', is_scalar( $list ) ? (string) $list : '' ) as $language ) {
				$language = strtolower( trim( $language, " \t\"'" ) );
				if ( preg_match( '/^[a-z]{2,3}$/', $language ) ) {
					$codes[] = $fix[ $language ] ?? $language;
				}
			}
			$codes = array_merge( $codes, self::EXTRA_LANGUAGES[ $code ] ?? array(), array( 'en' ) );
			$codes = array_values( array_unique( $codes ) );
			sort( $codes );
			$languages[ $code ] = $codes;
		}
		return $languages;
	}

	/**
	 * Builds region names from Wikidata: every label and alias, in each country's languages, of the items with an
	 * ISO 3166-2 code.
	 *
	 * A name that stands for more than one code in a country is left out.
	 *
	 * @since 6.5.0
	 *
	 * @param array      $items     ISO 3166-2 code, such as BG-02, => Wikidata item ID.
	 * @param array      $entities  Item ID => entity, with 'labels' (language => array with 'value') and 'aliases'
	 *                              (language => list of arrays with 'value').
	 * @param array      $languages Country code => language codes. See languages().
	 * @param array|null $iso       Optional. ISO 3166-2 codes without the country prefix, by country; others are left
	 *                              out. Default keeps all.
	 * @return array 'names': country => lowercased name => code without the country prefix.
	 */
	public static function region_names( $items, $entities, $languages, $iso = null ) {
		$found = array();
		foreach ( $items as $iso_code => $id ) {
			if ( ! is_string( $iso_code ) || ! preg_match( '/^([A-Z]{2})-([A-Z0-9]{1,3})$/', $iso_code, $match ) ) {
				continue;
			}
			list( , $country, $code ) = $match;
			if ( is_array( $iso ) && ! in_array( $code, $iso[ $country ] ?? array(), true ) ) {
				continue;
			}
			$entity = $entities[ $id ] ?? null;
			if ( ! is_array( $entity ) ) {
				continue;
			}
			foreach ( $languages[ $country ] ?? array( 'en' ) as $language ) {
				$values = array();
				if ( isset( $entity['labels'][ $language ]['value'] ) ) {
					$values[] = $entity['labels'][ $language ]['value'];
				}
				foreach ( $entity['aliases'][ $language ] ?? array() as $alias ) {
					if ( isset( $alias['value'] ) ) {
						$values[] = $alias['value'];
					}
				}
				foreach ( $values as $value ) {
					if ( is_string( $value ) && '' !== trim( $value ) ) {
						$found[ $country ][ self::normalize( $value ) ][ $code ] = true;
					}
				}
			}
		}
		$names = array();
		foreach ( $found as $country => $list ) {
			foreach ( $list as $name => $codes ) {
				if ( 1 === count( $codes ) ) {
					$names[ $country ][ $name ] = (string) key( $codes );
				}
			}
			if ( isset( $names[ $country ] ) ) {
				ksort( $names[ $country ] );
			}
		}
		ksort( $names );
		return array( 'names' => $names );
	}

	/**
	 * Builds country and region names by language from Wikidata labels, for showing them in a site's language.
	 *
	 * @since 6.5.0
	 *
	 * @param array      $countries ISO 3166-1 alpha-2 code => Wikidata item ID.
	 * @param array      $regions   ISO 3166-2 code, such as BG-02, => Wikidata item ID.
	 * @param array      $entities  Item ID => entity, with 'labels' (language => array with 'value').
	 * @param string[]   $languages Language codes to build.
	 * @param array|null $iso       Optional. ISO 3166-2 codes without the country prefix, by country; regions not among
	 *                              them, and countries without an entry, are left out. Default keeps all.
	 * @return array Language => 'countries' (code => name) and 'regions' (ISO 3166-2 code => name); languages without
	 *               any name are left out.
	 */
	public static function localized_names( $countries, $regions, $entities, $languages, $iso = null ) {
		$names = array();
		foreach ( $languages as $language ) {
			$list = array(
				'countries' => array(),
				'regions'   => array(),
			);
			foreach ( $countries as $code => $id ) {
				$label = $entities[ $id ]['labels'][ $language ]['value'] ?? null;
				if ( is_string( $label ) && '' !== $label && preg_match( '/^[A-Z]{2}$/', (string) $code ) ) {
					$list['countries'][ $code ] = $label;
				}
			}
			foreach ( $regions as $code => $id ) {
				$label = $entities[ $id ]['labels'][ $language ]['value'] ?? null;
				if ( ! is_string( $label ) || '' === $label || ! preg_match( '/^([A-Z]{2})-([A-Z0-9]{1,3})$/', (string) $code, $match ) ) {
					continue;
				}
				if ( is_array( $iso ) && ! in_array( $match[2], $iso[ $match[1] ] ?? array(), true ) ) {
					continue;
				}
				$list['regions'][ $code ] = $label;
			}
			if ( $list['countries'] || $list['regions'] ) {
				ksort( $list['countries'] );
				ksort( $list['regions'] );
				$names[ $language ] = $list;
			}
		}
		return $names;
	}
}

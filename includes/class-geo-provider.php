<?php
/**
 * Base Reverse Geolocation Provider Class.
 *
 * @package Simple_Location
 */

/**
 * Retrieves Location Information.
 *
 * @since 1.0.0
 */
abstract class Geo_Provider extends Sloc_Provider {

	 /**
	  * Reverse Zoom Level.
	  *
	  * @since 1.0.0
	  * @var int
	  */
	protected $reverse_zoom;

	 /**
	  * Username if Applicable.
	  *
	  * @since 1.0.0
	  * @var int
	  */
	protected $user;

	 /**
	  * Timezone.
	  *
	  * @since 1.0.0
	  * @var string
	  */
	protected $timezone;

	 /**
	  * Offset.
	  *
	  * @since 1.0.0
	  * @var string
	  */
	protected $offset;

	 /**
	  * Offset in Seconds.
	  *
	  * @since 1.0.0
	  * @var int
	  */
	protected $offset_seconds;

	/**
	 * Constructor for the Abstract Class.
	 *
	 * The default version of this just sets the parameters.
	 *
	 * @param array $args {
	 *  Arguments.
	 *  @type string $api API Key.
	 *  @type float $latitude Latitude.
	 *  @type float $longitude Longitude.
	 *  @type float $altitude Altitude.
	 *  @type string $address Formatted Address String
	 *  @type int $reverse_zoom Reverse Zoom. Default 18.
	 *  @type string $user User name.
	 */
	public function __construct( $args = array() ) {
		$defaults           = array(
			'api'          => null,
			'latitude'     => null,
			'longitude'    => null,
			'altitude'     => null,
			'reverse_zoom' => 18,
			'user'         => '',
		);
		$defaults           = apply_filters( 'sloc_geo_provider_defaults', $defaults );
		$r                  = wp_parse_args( $args, $defaults );
		$this->reverse_zoom = $r['reverse_zoom'];
		$this->user         = $r['user'];
		$this->api          = $r['api'];
		$this->set( $r['latitude'], $r['longitude'] );
	}

	/**
	 * Returns elevation.
	 *
	 * @return float $elevation Elevation.
	 *
	 * @since 1.0.0
	 */
	abstract public function elevation();

	/**
	 * Return an address.
	 *
	 * @return array $reverse microformats2 address elements in an array.
	 */
	abstract public function reverse_lookup();

	/**
	 * Geocode address.
	 *
	 * @param  string $address String representation of location.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	abstract public function geocode( $address );

	/**
	 * Generate Display Name for a Reverse Address Lookup.
	 *
	 * @param array $reverse Array of MF2 Address Properties.
	 * @return string|boolean Return Display Name or False if Failed.
	 */
	public function display_name( $reverse ) {
		if ( ! is_array( $reverse ) ) {
			return false;
		}
		$reverse = array_filter( $reverse );
		if ( isset( $reverse['display_name'] ) ) {
			return apply_filters( 'location_display_name', $reverse['display_name'], $reverse );
		}
		$text = array();
		if ( array_key_exists( 'name', $reverse ) ) {
			$text[] = $reverse['name'];
		} elseif ( ! array_key_exists( 'street-address', $reverse ) ) {
			$text[] = ifset( $reverse['extended-address'] );
		} else {
			$text[] = ifset( $reverse['street-address'] );
		}

		$text[] = ifset( $reverse['locality'] );
		$text   = array_filter( $text );
		if ( empty( $text ) ) {
			$text[] = ifset( $reverse['region'] );
		} else {
			if ( array_key_exists( 'region-code', $reverse ) ) {
				$text[] = $reverse['region-code'];
			} else {
				$text[] = ifset( $reverse['region'] );
			}
		}
		if ( array_key_exists( 'country-code', $reverse ) ) {
			if ( get_option( 'sloc_country' ) !== $reverse['country-code'] ) {
				$text[] = $reverse['country-code'];
			}
		} else {
			$text[] = ifset( $reverse['country-name'] );
		}
		$text   = array_filter( $text );
		$return = join( ', ', $text );
		return apply_filters( 'location_display_name', $return, $reverse );
	}

	/**
	 * Is Country One Where the Street Number Comes First.
	 *
	 * @param string $code Country Code.
	 * @return boolean True if yes.
	 */
	public function house_number( $code ) {
		return ! in_array(
			$code,
			array(
				'BE',
				'BG',
				'BR',
				'CH',
				'CL',
				'CN',
				'CZ',
				'DE',
				'DK',
				'ES',
				'FI',
				'HR',
				'IT',
				'NL',
				'NO',
				'PL',
				'SE',
				'SK',
			)
		);
	}

	/**
	 * Turn Country Code into Country Name.
	 *
	 * @param string $code Country Code.
	 * @return string|boolean Country Name or false is failed.
	 */
	public function country_name( $code ) {
		$code = strtoupper( trim( $code ) );
		if ( 2 !== strlen( $code ) ) {
			return false;
		}
		$file  = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/countries.json';
		$codes = json_decode( file_get_contents( $file ), true );
		if ( array_key_exists( $code, $codes ) ) {
			return $codes[ $code ];
		}
		return false;
	}

	/**
	 * Turn Country Name into ISO2 Country Code.
	 *
	 * @param string $name Country Name.
	 * @return string|boolean Country Code or false is failed.
	 */
	public function country_code( $name ) {
		$file  = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/countries.json';
		$codes = json_decode( file_get_contents( $file ), true );
		$name  = trim( $name );
		foreach ( $codes as $key => $value ) {
			if ( $name === $value ) {
				return $key;
			}
		}
		return false;
	}

	/**
	 * Turn ISO3 into ISO2 Country Code.
	 *
	 * @param string $iso3 ISO3 Country Code.
	 * @return string|boolean ISO2 Country Code or false is failed.
	 */
	public function country_code_iso3( $iso3 ) {
		$iso3 = trim( $iso3 );
		if ( 3 !== strlen( $iso3 ) ) {
			return false;
		}
		$file  = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/countries-iso3.json';
		$codes = json_decode( file_get_contents( $file ), true );
		foreach ( $codes as $key => $value ) {
			if ( $iso3 === $value ) {
				return $key;
			}
		}
		return false;
	}

	/**
	 * Turn a Region Name into a Region Code.
	 *
	 * @param string $name Region Name.
	 * @param string $country Country Code.
	 * @return string|boolean Region Code or false is failed.
	 */
	public function region_code( $name, $country ) {
		$name    = trim( $name );
		$country = strtoupper( trim( $country ) );
		$codes   = array();

		$file = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/iso_3166-2/' . $country . '.json';
		if ( ! file_exists( $file ) ) {
			return false;
		}

		$codes = json_decode( file_get_contents( $file ), true );
		foreach ( $codes as $code ) {
			if ( $name === $code['name'] ) {
				return str_replace( $country . '-', '', $code['code'] );
			}
		}

		// If it cannot find a match, try to find an inexact match.
		foreach ( $codes as $code ) {
			if ( str_contains( $name, $code['name'] ) ) {
				return str_replace( $country . '-', '', $code['code'] );
			}
			// To cover non-latin characters do a string comparison.
			if ( str_contains( $name, iconv( 'UTF-8', 'ASCII//TRANSLIT', $code['name'] ) ) ) {
				return str_replace( $country . '-', '', $code['code'] );
			}
		}
		return false;
	}

	/**
	 * Turn a Region Code into Region Name.
	 *
	 * @param string $code Region Code.
	 * @param string $country Country Code.
	 * @return string|boolean Region Name or false is failed.
	 */
	public function region_name( $code, $country ) {
		$code  = strtoupper( trim( $code ) );
		$codes = array();

		if ( 'US' === $country ) {
			$file  = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/states-us.json';
			$codes = json_decode( file_get_contents( $file ), true );
		}

		if ( 'CA' === $country ) {
			$file  = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/provinces-ca.json';
			$codes = json_decode( file_get_contents( $file ), true );
		}

		if ( array_key_exists( $code, $codes ) ) {
			return $codes[ $code ];
		}
		return false;
	}

	/**
	 * Return Timezone Data for a Set of Coordinates.
	 *
	 * @return array|boolean Return Timezone Data or False if Failed
	 */
	public function timezone() {
		$timezone = Loc_Timezone::timezone_for_location( $this->latitude, $this->longitude );
		if ( $timezone ) {
			$return             = array();
			$return['timezone'] = $timezone->name;
			$return['offset']   = $timezone->offset;
			$return['seconds']  = $timezone->seconds;
			return $return;
		}
		return false;
	}
}

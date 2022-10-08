<?php
/**
 * Geolocation Helper Trait.
 *
 * @package Simple_Location
 */

/**
 *
 *
 * @since 5.0.0
 */
trait GeoLocation_Trait {
	/**
	 * Is Country One Where the Street Number Comes First.
	 *
	 * @param string $code Country Code.
	 * @return boolean True if yes.
	 */
	public static function house_number( $code ) {
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
	 * Returns Country Data
	 *
	 * @param string $iso ISO2 or ISO3 Country Code.
	 * @return array|boolean Country Data or false is failed.
	 */
	public static function country_data( $iso ) {
		$iso = trim( $iso );
		$iso = strtoupper( trim( $iso ) );

		$file  = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/iso_3166-1.json';
		$codes = json_decode( file_get_contents( $file ), true );
		$codes = $codes['3166-1'];
		$match = wp_filter_object_list( $codes, array( 'alpha_' . strlen( $iso ) => $iso ) );
		if ( is_array( $match ) && 1 === count( $match ) ) {
			return array_shift( $match );
		}
		return false;
	}

	/**
	 * Returns Country Data from Name
	 *
	 * @param string $name Country Name.
	 * @return array|boolean Country Data or false is failed.
	 */
	public static function country_data_name( $name ) {
		$name  = trim( $name );
		$file  = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/iso_3166-1.json';
		$codes = json_decode( file_get_contents( $file ), true );
		$codes = $codes['3166-1'];
		$match = wp_filter_object_list( $codes, array( 'name' => $name ) );
		if ( is_array( $match ) && 1 === count( $match ) ) {
			return array_shift( $match );
		}
		return false;
	}

	/**
	 * Turn Country Code into Country Name.
	 *
	 * @param string $code Country Code.
	 * @return string|boolean Country Name or false is failed.
	 */
	public static function country_name( $code ) {
		$match = self::country_data( $code );
		if ( is_array( $match ) ) {
			return $match['name'];
		}
		return false;
	}

	/**
	 * Turn Country Code into Country Flag.
	 *
	 * @param string $code Country Code.
	 * @return string|boolean Country Name or false is failed.
	 */
	public static function country_flag( $code ) {
		$match = self::country_data( $code );
		if ( is_array( $match ) ) {
			return $match['flag'];
		}
		return false;
	}

	/**
	 * Turn Country Name into ISO2 Country Code.
	 *
	 * @param string $name Country Name.
	 * @return string|boolean Country Code or false is failed.
	 */
	public static function country_code( $name ) {
		$match = self::country_data_name( $name );
		if ( is_array( $match ) ) {
			return $match['alpha_2'];
		}
		return false;
	}

	/**
	 * Turn ISO3 into ISO2 Country Code.
	 *
	 * @param string $iso3 ISO3 Country Code.
	 * @return string|boolean ISO2 Country Code or false is failed.
	 */
	public static function country_code_iso3( $iso3 ) {
		$match = self::country_data( $iso3 );
		if ( is_array( $match ) ) {
			return $match['alpha_2'];
		}

		return false;
	}

	/**
	 * Return Data on a Region by Code
	 *
	 * @param string $code Region Code.
	 * @param string $country ISO2 Country Code.
	 * @return array|boolean Region Data or false if failed.
	 */
	public static function region_data( $code, $country ) {
		$code    = trim( $code );
		$country = strtoupper( trim( $country ) );
		$codes   = array();

		$file = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/iso_3166-2/' . $country . '.json';
		if ( ! file_exists( $file ) ) {
			return false;
		}

		$codes = json_decode( file_get_contents( $file ), true );

		$match = wp_filter_object_list( $codes, array( 'code' => $country . '-' . $code ) );

		if ( is_array( $match ) && 1 === count( $match ) ) {
			return array_shift( $match );
		}
		return false;
	}

	/**
	 * Return Data on a Region by Name
	 *
	 * @param string $name Region Name.
	 * @param string $country ISO2 Country Code.
	 * @return array|boolean Region Data or false if failed.
	 */
	public static function region_data_name( $name, $country ) {
		$name    = trim( $name );
		$country = strtoupper( trim( $country ) );
		$codes   = array();

		$file = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/iso_3166-2/' . $country . '.json';
		if ( ! file_exists( $file ) ) {
			return false;
		}

		$codes = json_decode( file_get_contents( $file ), true );

		$match = wp_filter_object_list( $codes, array( 'name' => $name ) );

		if ( is_array( $match ) && 1 === count( $match ) ) {
			return array_shift( $match );
		}

		// If it cannot find a match, try to find an inexact match.
		foreach ( $codes as $code ) {
			if ( str_contains( $name, $code['name'] ) ) {
				return $code;
			}
			// To cover non-latin characters do a string comparison.
			if ( str_contains( $name, iconv( 'UTF-8', 'ASCII//TRANSLIT', $code['name'] ) ) ) {
				return $code;
			}
		}

		return false;
	}

	/**
	 * Turn a Region Name into a Region Code.
	 *
	 * @param string $name Region Name.
	 * @param string $country Country Code.
	 * @return string|boolean Region Code or false if failed.
	 */
	public static function region_code( $name, $country ) {
		$name    = trim( $name );
		$country = strtoupper( trim( $country ) );

		$match = self::region_data_name( $name, $country );

		if ( is_array( $match ) ) {
			return str_replace( $country . '-', '', $match['code'] );
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
	public static function region_name( $code, $country ) {
		$code  = strtoupper( trim( $code ) );
		$match = self::region_data( $code, $country );
		if ( is_array( $match ) ) {
			return $match['name'];
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

	public static function country_select( $country ) {
		$file  = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/iso_3166-1.json';
		$codes = json_decode( file_get_contents( $file ), true );
		$codes = $codes['3166-1'];

		echo '<select name="country" id="country">';
		foreach ( $codes as $code ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $code['alpha_2'] ), selected( $country, $code['alpha_2'], false ), esc_html( $code['flag'] . ' ' . $code['name'] ) );
		}
		echo '</select>';
	}

	public static function region_select( $region, $country ) {
		$country = strtoupper( trim( $country ) );
		if ( 2 !== strlen( $country ) ) {
			return false;
		}
		$file = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/iso_3166-2/' . $country . '.json';
		if ( ! file_exists( $file ) ) {
			return false;
		}

		$codes = json_decode( file_get_contents( $file ), true );
		$codes = wp_list_pluck( $codes, 'name', 'code' );
		if ( ! array_key_exists( $country . '-' . $region, $codes ) && ! empty( $region ) ) {
			printf( '<input class="widefat" type=text" name="region" value="%s" required />', esc_attr( $region ) );
			return;
		}
		echo '<select name="region" id="region">';
		foreach ( $codes as $code => $name ) {
			$code = str_replace( $country . '-', '', $code );
			printf( '<option value="%1$s" %2$s>%3$s(%1$s)</option>', esc_attr( $code ), selected( $region, $code, false ), esc_html( $name ) );
		}
		echo '</select>';
	}
}

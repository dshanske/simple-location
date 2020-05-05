<?php
/**
 * Provides Information about an Airport.
 *
 * Functionality borrowed from https://github.com/aaronpk/Atlas
 * airports.csv is from
 * http://ourairports.com/data/
 * http://ourairports.com/data/airports.csv
 *
 * @package Simple_Location
 */

/**
 * Retrieves Airport Location Data.
 *
 * Extracts information from a rather large csv file to return information about an airport.
 *
 * @since 1.0.0
 */
class Airport_Location {

	/**
	 * Return an array of information based on passed data.
	 *
	 * @param string $search Value to Search For.
	 * @param string $field Field to Search. Defaults to IATA Code.
	 * @return null|array $airport {
	 *  An array of details about the airport. Or for certain fields, an array of matching airports.
	 *  See https://ourairports.com/help/data-dictionary.html#airports.
	 *
	 *  @type string $code IATA Airport Code.
	 *  @type float $latitude Latitude.
	 *  @type float $longitude Longitude.
	 *  @type float $elevation Elevation in meters.
	 *  @type string $name Official Name of Airport.
	 *  @type string $type Type of Airport.
	 *   Allowed values are "closed_airport", "heliport", "large_airport", "medium_airport", "seaplane_base", and "small_airport".
	 *  @type string $home_link URL of Airport Homepage.
	 *  @type string $wikipedia_link URL of the Airport Homepage
	 *  @type int $id Internal OurAirports integer identifier for the airport. This will stay persistent, even if the airport code changes.
	 *  @type string $ident This will be the ICAO code if available. Otherwise, it will be a local airport code (if no conflict), or if nothing else is available, an internally-generated code starting with the ISO2 country code, followed by a dash and a four-digit number.
	 *  @type string $continent The code for the continent where the airport is (primarily) located. Allowed values are "AF" (Africa), "AN" (Antarctica), "AS" (Asia), "EU" (Europe), "NA" (North America), "OC" (Oceania), or "SA" (South America).
	 *  @type string $iso_country The two-character ISO 3166:1-alpha2 code for the country where the airport is (primarily) located.
	 *  @type string $iso_region An alphanumeric code for the high-level administrative subdivision of a country where the airport is primarily located (e.g. province, governorate), prefixed by the ISO2 country code and a hyphen. Uses ISO 3166:2 codes whenever possible, preferring higher administrative levels, but also includes some custom codes.
	 *  @type string $municipality  The primary municipality that the airport serves (when available). Note that this is not necessarily the municipality where the airport is physically located.
	 *  @type boolean $scheduled_service  True if the airport currently has scheduled airline service, not present otherwise.
	 *  @type string $gps_code The code that an aviation GPS database (such as Jeppesen's or Garmin's) would normally use for the airport. This will always be the ICAO code if one exists. Note that, unlike ident, this is not guaranteed to be globally unique.
	 *   @type string $local_code The local country code for the airport, if different from the gps_code and iata_code fields (used mainly for US airports).
	 *   @type array $keywords Extra keywords/phrases to assist with search, comma-separated. May include former names for the airport, alternate codes, names in other languages, nearby tourist destinations, etc.
	 * }
	 * @since 4.0.0
	 */
	public static function get( $search, $field = 'iata_code' ) {
		$airport = wp_cache_get( $search . '_' . $field, 'airports' );
		if ( $airport ) {
			return $airport;
		}
		$file = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/airports.csv';
		if ( ! file_exists( $file ) ) {
			return new WP_Error( 'filesystem_error', "File doesn't exist" );
		}
		$fp   = fopen( $file, 'r' ); // phpcs:ignore
		rewind( $fp );
		$keys   = array_flip( fgetcsv( $fp ) );
		$return = array();
		$line   = fgetcsv( $fp );
		while ( $line ) {
			$line = fgetcsv( $fp );
			if ( 0 === strcasecmp( $line[ $keys[ $field ] ], $search ) ) {
				$airport = array();
				foreach ( $keys as $key => $value ) {
					if ( 'keywords' === $key ) {
						$airport[ $key ] = explode( ',', $line[ $value ] );
						$airport[ $key ] = array_map( 'trim', $airport[ $key ] );
						$airport[ $key ] = array_filter( $airport[ $key ] );
					} elseif ( 'iata_code' === $key ) {
						$airport['code'] = $line[ $value ];
					} elseif ( 'latitude_deg' === $key ) {
						$airport['latitude'] = WP_Geo_Data::clean_coordinate( $line[ $value ] );
					} elseif ( 'longitude_deg' === $key ) {
						$airport['longitude'] = WP_Geo_Data::clean_coordinate( $line[ $value ] );
					} elseif ( 'elevation_ft' === $key ) {
						$airport['elevation']    = round( (int) $line[ $value ] * 3.28 );
						$airport['elevation_ft'] = (int) $line[ $value ];
					} elseif ( 'scheduled_service' === $key ) {
						$airport['scheduled_service'] = ( 'yes' === $line[ $value ] );
					} else {
						$airport[ $key ] = $line[ $value ];
					}
				}
				$airport = array_filter( $airport );
				if ( in_array( $field, array( 'iata_code', 'ident', 'gps_code' ), true ) ) {
					$return = $airport;

				} else {
					$return[] = $airport;
				}
			}
		}
		wp_cache_set( $search . '_' . $field, $airport, 'airports', 86400 );
		return $return;
	}
} // End Class

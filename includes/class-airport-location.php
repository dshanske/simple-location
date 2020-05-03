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
	 * Given an airport code return an array of information.
	 *
	 * Retusn.

	 * @param string $code A 3 letter IATA airport code.
	 * @return null|array $airport {
	 *  An array of details about the airport.
	 *
	 *  @type string $code Provided Airport Code.
	 *  @type float $latitude Latitude.
	 *  @type float $longitude Longitude.
	 *  @type float $elevation Elevation in meters.
	 *  @type string $name Name of Airport.
	 *  @type string $type Type of Airport.
				small_airport, medium_airport, large_airport, heliport
	 *  @type string $home_link URL of Airport Homepage.
	 *  @type string $wikipedia_link URL of the Airport Homepage
	 * }
	 * @since 4.0.0
	 */
	public static function get( $code ) {
		$airport = wp_cache_get( $code, 'airports' );
		if ( $airport ) {
			return $airport;
		}
		$file = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/airports.csv';
		if ( ! file_exists( $file ) ) {
			return new WP_Error( 'filesystem_error', "File doesn't exist" );
		}
		if ( ! is_string( $code ) ) {
			return null;
		}
		$code = strtoupper( $code );
		$fp   = fopen( $file, 'r' ); // phpcs:ignore
		rewind( $fp );
		while ( ! $airport ) {
			$line = fgetcsv( $fp );
			if ( ! $line ) {
				break;
			}
			if ( $line[13] === $code && 'closed' !== $line[2] ) {
				$airport = array(
					'code'           => $code,
					'latitude'       => $line[4],
					'longitude'      => $line[5],
					'elevation'      => round( $line[6] * 3.28, 2 ),
					'name'           => $line[3],
					'type'           => $line[2],
					'home_link'      => $line[15],
					'wikipedia_link' => $line[16],
				);
			}
		}
		wp_cache_set( $code, $airport, 'airports', 86400 );
		return $airport;
	}
} // End Class

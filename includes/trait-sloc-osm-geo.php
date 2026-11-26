<?php
/**
 * Nominatim-style OpenStreetMap addresses for geo providers.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Converts a Nominatim-style answer to the address of a reverse lookup or search.
 *
 * Used by classes extending Geo_Provider: Nominatim, LocationIQ and Traccar Geocoder.
 *
 * @since 6.5.0
 */
trait Sloc_OSM_Geo {
	use Sloc_OSM_Address;

	/**
	 * Converts an answer to microformats2 address properties.
	 *
	 * @since 6.5.0 Moved from Geo_Provider_Nominatim::address_to_mf() and shared with Traccar Geocoder.
	 *
	 * @param array $json Answer, with 'address'.
	 * @return array Microformats2 address elements, or an empty array without an address.
	 */
	protected function osm_address_to_mf( $json ) {
		$addr = $this->osm_address( $json );
		if ( empty( $addr ) ) {
			return array();
		}
		$addr['latitude']  = $this->latitude;
		$addr['longitude'] = $this->longitude;
		$addr['altitude']  = $this->altitude;
		$addr['raw']       = $json;
		if ( isset( $json['extratags'] ) && is_array( $json['extratags'] ) ) {
			$addr['url']   = $json['extratags']['website'] ?? null;
			$addr['photo'] = $json['extratags']['image'] ?? null;
		}
		$addr                 = array_filter( $addr );
		$addr['display-name'] = $this->display_name( $addr );
		$tz                   = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		return $addr;
	}
}

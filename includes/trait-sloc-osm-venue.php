<?php
/**
 * Nominatim-style OpenStreetMap places for venue providers.
 *
 * @package Simple_Location
 */

defined( 'ABSPATH' ) || exit;

/**
 * Converts a Nominatim-style place to a venue h-card.
 *
 * Used by classes extending Venue_Provider: Nominatim and LocationIQ.
 *
 * @since 6.5.0
 */
trait Sloc_OSM_Venue {
	use Sloc_OSM_Address;

	/**
	 * Converts a place to an h-card.
	 *
	 * The address uses the place's own coordinates, not the search point's.
	 *
	 * @since 6.5.0 Replaces the copies in Venue_Provider_Nominatim and Venue_Provider_LocationIQ.
	 *
	 * @param array $json Place, with 'address'.
	 * @return array Microformats2 h-card properties, or an empty array without an address.
	 */
	protected function osm_venue_to_hcard( $json ) {
		if ( ! is_array( $json ) ) {
			return array();
		}
		$latitude  = isset( $json['lat'] ) && is_numeric( $json['lat'] ) ? (float) $json['lat'] : null;
		$longitude = isset( $json['lon'] ) && is_numeric( $json['lon'] ) ? (float) $json['lon'] : null;
		$address   = $this->osm_address( $json, $latitude, $longitude );
		if ( empty( $address ) ) {
			return array();
		}
		$addr = array_merge(
			array( 'type' => 'card' ),
			$address,
			array(
				'category'  => self::ifnot( $json, array( 'type', 'category' ) ),
				'latitude'  => $latitude,
				'longitude' => $longitude,
				'uid'       => self::osm_uid( $json['osm_type'] ?? '', $json['osm_id'] ?? '' ),
				'raw'       => $json,
			)
		);
		if ( isset( $json['name'] ) && is_string( $json['name'] ) && '' !== $json['name'] ) {
			$addr['name'] = $json['name'];
		} elseif ( isset( $json['address']['name'] ) && is_string( $json['address']['name'] ) && '' !== $json['address']['name'] ) {
			$addr['name'] = $json['address']['name'];
		}
		if ( isset( $json['extratags'] ) && is_array( $json['extratags'] ) ) {
			$addr['url'] = $json['extratags']['website'] ?? null;
			if ( empty( $addr['url'] ) && isset( $json['extratags']['wikipedia'] ) ) {
				$addr['url'] = self::wikipedia_url( $json['extratags']['wikipedia'] );
			}
			$addr['photo'] = $json['extratags']['image'] ?? null;
			$addr['tel']   = $json['extratags']['phone'] ?? null;
		}
		if ( isset( $json['boundingbox'] ) ) {
			$addr['boundingbox'] = $json['boundingbox'];
		}
		$addr = array_filter( $addr );
		$addr = self::add_category( $addr, self::osm_category( $json['category'] ?? $json['class'] ?? '', $json['type'] ?? '' ) );
		$tz   = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		return $addr;
	}
}

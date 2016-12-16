<?php
/**
 * Reverse Geolocation Backend
 *
 *
 *
 * @package Simple Location
 */

add_action( 'init', array( 'Ajax_Geo', 'init' ), 1 );

class Ajax_Geo {
	public static function init() {
		add_action( 'wp_ajax_get_sloc_address_data', array( 'Ajax_Geo', 'get_address_data' ) );
	}

	public static function get_address_data() {
		global $wpdb;
		if ( empty( $_POST['longitude'] ) || empty( $_POST['latitude'] ) ) {
				wp_send_json_error( new WP_Error( 'nogeo', __( 'You must specify coordinates', 'simple-location' ) ) );
		}
		$reverse = new osm_static();
		$reverse_adr = $reverse->reverse_lookup( $_POST['latitude'], $_POST['longitude'] );
		$reverse_adr = self::display_name( $reverse_adr );
		$reverse_adr = self::timezone( $_POST['latitude'], $_POST['longitude'], $reverse_adr );
		if ( is_wp_error( $reverse_adr ) ) {
			wp_send_json_error( $response );
		}
		wp_send_json_success( $reverse_adr );
	}
	public static function timezone( $lat, $lng, $reverse ) {
		if ( ! is_array( $reverse ) ) {
			return $reverse;
		}
		$timezone = Loc_Timezone::timezone_for_location( $lat, $lng );
		if ( $timezone ) {
			$reverse['timezone'] = $timezone->name;
			$reverse['offset'] = $timezone->offset;
			$reverse['seconds'] = $timezone->seconds;
		}
		return $reverse;
	}

	public static function display_name( $reverse ) {
		if ( ! is_array( $reverse ) ) {
			return $reverse;
		}
		$text = array();
		$text[] = ifset( $reverse['name'] );
		$text[] = ifset( $reverse['locality'] );
		$text[] = ifset( $reverse['region'] );
		$text[] = ifset( $reverse['country-name'] );
		$text = array_filter( $text );
		$reverse['display-name'] = join( ', ', $text );
		return $reverse;
	}
}

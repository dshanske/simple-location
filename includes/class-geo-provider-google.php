<?php
// Google Map Provider
class Geo_Provider_Google extends Geo_Provider {

	public function reverse_lookup( ) {
		$response = wp_remote_get( 'https://maps.googleapis.com/maps/api/geocode/json?latlng=' . $this->latitude . ',' . $this->longitude );
		$json = json_decode( $response['body'], true );
		$address = $json['results'][0]['address_components'];
		$addr = array(
			'name' => $json['results'][0]['formatted_address'],
			'latitude' => $this->latitude,
			'longitude' => $this->longitude,
			'raw' => $address,
		);
		$addr = array_filter( $addr );
		$addr['display-name'] = $this->display_name( $addr );
		$tz = $this->timezone( $this->latitude, $this->longitude );
		$addr = array_merge( $addr, $tz );
		return $addr;
	}

	// Return code for map
	public function get_the_static_map( ) {
		$map = 'https://maps.googleapis.com/maps/api/staticmap?markers=color:red%7Clabel:P%7C' . $this->latitude . ',' . $this->longitude . '&size=' . $this->height . 'x' . $this->width;
		return $map;
	}

	public function get_the_map_url() {
		return 'http://maps.google.com/maps?q=loc:' . $this->latitude . ',' . $this->longitude;
	}

	// Return code for map
	public function get_the_map( $static = true) {
		$link = $this->get_the_static_map( );
		$map = $this->get_the_map_url( );
		$c = '<a href="' . $link . '"><img src="' . $map . '" /></a>';
		return $c;
	}

}

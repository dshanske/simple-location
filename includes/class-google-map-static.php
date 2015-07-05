<?php
// Google Map Provider
class google_map_static implements map_provider {
	public function reverse_lookup($lat, $lon, $zoom=18, $alt = NULL) {
	  $response = wp_remote_get('https://maps.googleapis.com/maps/api/geocode/json?latlng=' . $lat . ',' . $lon);
	  $json = json_decode($response['body'], true);
	  $address = $json['results'][0]['address_components'];
		$addr = array(
			'name' => $json['results'][0]['formatted_address'],
			'latitude' => $lat,
			'longitude' => $lon,
			'altitude' => $alt,
			'raw' => $address
		);
		return array_filter($addr);
	}

  // Return code for map
  public function get_the_map_url($lat, $lon, $height=300, $width=300, $zoom=14) {
    $map = 'https://maps.googleapis.com/maps/api/staticmap?markers=color:red%7Clabel:P%7C' . $lat . ',' . $lon . '&size=' . $height . 'x' . $width;
    return $map;
  }


	// Return code for map
	public function get_the_map($lat, $lon, $height=300, $width=300, $zoom=14) {
		$link = 'http://maps.google.com/maps?q=loc:' . $lat . ',' . $lon;
		$map = self::get_the_map_url($lat, $lon, $height, $width, $zoom);
		$c = '<a href="' . $link . '"><img src="' . $map . '" /></a>';
		return $c;
	}
	
  public function the_map($lat, $lon, $height=300, $width=300, $zoom=14) {
		echo self::get_the_map($lat, $lon, $height, $width, $zoom);
	}
}

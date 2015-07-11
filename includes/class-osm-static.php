<?php
// OSM Static Map Provider
class osm_static implements map_provider {
	public static function reverse_lookup($lat, $lon, $zoom=18, $alt = NULL) {
	  $response = wp_remote_get('http://nominatim.openstreetmap.org/reverse?format=json&lat=' . $lat . '&lon=' . $lon . '&zoom=' . $zoom . '&accept-language=' . get_bloginfo('language') );
	  $json = json_decode($response['body'], true);
	  $address = $json['address'];
	  if ($address['country_code'] == 'us') {
			$region = $address['state'] ?: $address['county'];
		}
		else {
			$region = $address['county'] ?: $address['state'];
		}
		$street = $address['house_number'] . ' ' . $address['road'];
		$addr = array(
			'name' => $address['attraction'] ?: $address['building'] ?: $address['hotel'] ?: $address['highway'] ?: null,
			'street-address' => $street,
			'extended-address' => $address['boro'] ?: $address['neighbourhood'] ?: $address['suburb'] ?: null,
			'locality' => $address['hamlet'] ?: $address['village'] ?: $address['town'] ?: $address['city'] ?: null,
			'region' => $region,
			'country-name' => $address['country'] ?: null,
			'postal-code' => $address['postcode'] ?: null,
			'country-code' => strtoupper($address['country_code']) ?: null,
			'latitude' => $lat,
			'longitude' => $lon,
			'altitude' => $alt,
			'raw' => $address
		);
		if (is_null($addr['country-name']) ) {
			$codes = json_decode(wp_remote_retrieve_body(wp_remote_get('http://country.io/names.json')), true);
			$addr['country-name'] = $codes[$addr['country-code']];
		}
		return array_filter($addr);
	}

  public static function get_the_map_url($lat, $lon, $height=300, $width=300, $zoom=14) {
    $map = plugin_dir_url( __FILE__ ) . 'staticmap.php?center=' . $lat . ',' . $lon . '&zoom=' . $zoom . '&size=' . $width . 'x' . $height . '&markers=' . $lat . ',' . $lon . '&maptype=mapnik';
		return $map;
	}

 public static function get_the_map_link($lat, $lon) {
    return 'http://www.openstreetmap.org/#map=14/' . $lat . '/' . $lon;
  }

	// Return code for map linked to OSM
	public static function get_the_map($lat, $lon, $height=300, $width=300, $zoom=14) {
		$c = '<img src="' . self::get_the_map_url($lat, $long, $height, $width, $zoom) . '" />';
		return $c;
	}
	
  public static function the_map($lat, $lon, $height=300, $width=300, $zoom=14) {
		echo get_the_map($lat, $lon, $height, $width, $zoom);
	}
}

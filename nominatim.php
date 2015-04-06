<?php

function sloc_reverse_lookup($lat, $lon, $zoom=18, $alt = NULL) {
  $response = wp_remote_get('http://nominatim.openstreetmap.org/reverse?format=json&lat=' . clean_coordinate($lat) . '&lon=' . clean_coordinate($lon) . '&zoom=' . $zoom);
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
        'country-code' => $address['country_code'] ?: null,
        'latitude' => clean_coordinate($lat),
        'longitude' => clean_coordinate($lon),
        'altitude' => $alt,
        'raw' => $address
  );
  return array_filter($addr);
}

// Return code for map linked to OSM
function sloc_get_the_map($lat, $lon, $height=300, $width=300, $zoom=14) {
  $link = 'http://www.openstreetmap.org/#map=15/' . $lat . '/' . $lon;
  $map = plugin_dir_url( __FILE__ ) . 'staticmap.php?center=' . $lat . ',' . $lon . '&zoom=' . $zoom . '&size=' . $width . 'x' . $height . '&markers=' . $lat . ',' . $lon . '&maptype=mapnik';
  $c = '<a href="' . $link . '"><img src="' . $map . '" /></a>';
  return $c;
}

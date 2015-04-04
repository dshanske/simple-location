<?php

function reverse_lookup($lat, $lon, $zoom=18, $alt = NULL) {
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

function nameForLocation(array $location, $fallback='Unknown Location') {
  if (isset($location['name'])) return $location['name'];
  if (isset($location['street-address']) and isset($location['region']) and isset($location['region']))
    return $location['street-address'] . " , " . $location['locality'] . " , " . $location['region'];
  if (isset($location['latitude']) and isset($location['longitude']))
    return round($location['latitude'], 2) . ', ' . round($location['longitude'], 2);
  return $fallback;
}

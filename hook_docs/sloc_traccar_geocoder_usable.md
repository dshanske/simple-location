
Filters whether a Traccar Geocoder server's answer is used, or the next server asked.

By default an answer is used when it has a street or a city. An answer with only a country or region means
the location is outside the regions the server has.


postcode, country and country_code, each only when known.

## Auto-generated Example

```php
add_filter(
   'sloc_traccar_geocoder_usable',
    function(
        bool $usable,
        array $address,
        array $json,
        string $url,
        float $latitude,
        float $longitude
    ) {
        // Your code here.
        return $usable;
    },
    10,
    6
);
```

## Parameters

- *`bool`* `$usable` Whether to use it.
- *`array`* `$address` Address from the answer: house_number, road, suburb, city, county, state,
- *`array`* `$json` Answer.
- *`string`* `$url` Server URL.
- *`float`* `$latitude` Latitude looked up.
- *`float`* `$longitude` Longitude looked up.

## Files

- [includes/geo/class-geo-provider-traccar-geocoder.php:322](https://github.com/dshanske/simple-location/blob/trunk/includes/geo/class-geo-provider-traccar-geocoder.php#L322)
```php
apply_filters( 'sloc_traccar_geocoder_usable', $usable, $address, $json, $url, $this->latitude, $this->longitude )
```



[← All Hooks](Hooks)

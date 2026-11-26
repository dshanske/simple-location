
Filters the country of a location.

For choices that depend on where the location is, such as a disputed area within a country.

## Auto-generated Example

```php
add_filter(
   'sloc_country_for_location',
    function(
        string $code,
        float $latitude = null,
        float $longitude = null
    ) {
        // Your code here.
        return $code;
    },
    10,
    3
);
```

## Parameters

- *`string`* `$code` Country code, after the sloc_country_codes map; may be empty.
- *`float|null`* `$latitude` Latitude, if known.
- *`float|null`* `$longitude` Longitude, if known.

## Files

- [includes/class-sloc-address-finisher.php:79](https://github.com/dshanske/simple-location/blob/trunk/includes/class-sloc-address-finisher.php#L79)
```php
apply_filters( 'sloc_country_for_location', $code, $latitude, $longitude )
```



[← All Hooks](Hooks)

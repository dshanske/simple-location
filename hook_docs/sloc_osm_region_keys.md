
Filters the address keys a Nominatim-format answer's region is taken from, in order.

## Auto-generated Example

```php
add_filter(
   'sloc_osm_region_keys',
    function(
        string[] $keys,
        string
    ) {
        // Your code here.
        return $keys;
    },
    10,
    2
);
```

## Parameters

- *`string[]`* `$keys` Address keys, such as state, region, state_district and county.
- `string` $country_code Two-letter country code, or an empty string if it isn't known. Other variable names: `$country_code`

## Files

- [includes/trait-sloc-osm-address.php:283](https://github.com/dshanske/simple-location/blob/trunk/includes/trait-sloc-osm-address.php#L283)
```php
apply_filters( 'sloc_osm_region_keys', $keys, $country_code )
```



[← All Hooks](Hooks)

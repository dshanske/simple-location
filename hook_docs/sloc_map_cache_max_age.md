
Filters how many days a stored map image is used before it is fetched again from the provider.

The provider's own limit, from its terms, still applies to the filtered value.

## Auto-generated Example

```php
add_filter(
   'sloc_map_cache_max_age',
    function(
        int $days,
        Simple_Location\Map_Provider $map
    ) {
        // Your code here.
        return $days;
    },
    10,
    2
);
```

## Parameters

- *`int`* `$days` Days, from the Refresh Stored Maps After setting. 0 never fetches the image again.
- *`Simple_Location\Map_Provider`* `$map` Map provider.

## Files

- [includes/class-sloc-map-cache.php:332](https://github.com/dshanske/simple-location/blob/main/includes/class-sloc-map-cache.php#L332)
```php
apply_filters( 'sloc_map_cache_max_age', $days, $map )
```



[← All Hooks](Hooks)

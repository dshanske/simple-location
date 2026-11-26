
Filters the directory of the offline geographic data: the country and timezone grids, places and regions.

## Auto-generated Example

```php
add_filter(
   'sloc_offline_geo_dir',
    function( string $dir ) {
        // Your code here.
        return $dir;
    }
);
```

## Parameters

- *`string`* `$dir` Path, with a trailing slash.

## Files

- [includes/class-sloc-offline-geo.php:100](https://github.com/dshanske/simple-location/blob/trunk/includes/class-sloc-offline-geo.php#L100)
```php
apply_filters( 'sloc_offline_geo_dir', $dir )
```



[← All Hooks](Hooks)

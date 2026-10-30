
Filters what may be kept of a check-in's venue.


to keep only the name, coordinates, identifier and links, with the address from the
site's geocoder.

## Auto-generated Example

```php
add_filter(
   'sloc_venue_storage',
    function(
        string $storage,
        array $venue
    ) {
        // Your code here.
        return $storage;
    },
    10,
    2
);
```

## Parameters

- *`string`* `$storage` Venue_Provider::STORAGE_FULL to keep the details, or Venue_Provider::STORAGE_REFERENCE
- *`array`* `$venue` Check-in or venue properties.

## Files

- [includes/class-post-venue.php:1263](https://github.com/dshanske/simple-location/blob/trunk/includes/class-post-venue.php#L1263)
```php
apply_filters( 'sloc_venue_storage', $storage, $venue )
```



[← All Hooks](Hooks)

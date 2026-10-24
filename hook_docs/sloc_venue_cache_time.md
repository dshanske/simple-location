
Filters how long a venue provider's search results are cached, in seconds.

Check a provider's terms before caching its results longer, or caching those of a provider that doesn't.

## Auto-generated Example

```php
add_filter(
   'sloc_venue_cache_time',
    function(
        int $seconds,
        Venue_Provider $provider
    ) {
        // Your code here.
        return $seconds;
    },
    10,
    2
);
```

## Parameters

- *`int`* `$seconds` Seconds, or 0 not to cache.
- *`Venue_Provider`* `$provider` Venue provider.

## Files

- [includes/venue/class-venue-provider-here.php:78](https://github.com/dshanske/simple-location/blob/trunk/includes/venue/class-venue-provider-here.php#L78)
```php
apply_filters( 'sloc_venue_cache_time', self::CACHE_TIME, $this )
```

- [includes/class-venue-provider.php:260](https://github.com/dshanske/simple-location/blob/trunk/includes/class-venue-provider.php#L260)
```php
apply_filters( 'sloc_venue_cache_time', $seconds, $this )
```



[← All Hooks](Hooks)

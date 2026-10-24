
Filters the default arguments of venue providers.

## Auto-generated Example

```php
add_filter(
   'sloc_venue_provider_defaults',
    function( array $defaults ) {
        // Your code here.
        return $defaults;
    }
);
```

## Parameters

- *`array`* `$defaults` Default arguments: api, latitude, longitude, altitude and user.

## Files

- [includes/class-venue-provider.php:152](https://github.com/dshanske/simple-location/blob/trunk/includes/class-venue-provider.php#L152)
```php
apply_filters( 'sloc_venue_provider_defaults', $defaults )
```



[← All Hooks](Hooks)

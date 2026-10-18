
Filters the default arguments of elevation providers.

## Auto-generated Example

```php
add_filter(
   'sloc_elevation_provider_defaults',
    function( array $defaults ) {
        // Your code here.
        return $defaults;
    }
);
```

## Parameters

- *`array`* `$defaults` Default arguments: api, latitude, longitude, altitude and user.

## Files

- [includes/class-elevation-provider.php:46](https://github.com/dshanske/simple-location/blob/trunk/includes/class-elevation-provider.php#L46)
```php
apply_filters( 'sloc_elevation_provider_defaults', $defaults )
```



[← All Hooks](Hooks)

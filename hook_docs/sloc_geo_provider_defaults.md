
This filter is documented in includes/class-geo-provider.php

## Auto-generated Example

```php
add_filter(
   'sloc_geo_provider_defaults',
    function( array $defaults ) {
        // Your code here.
        return $defaults;
    }
);
```

## Parameters

- *`array`* `$defaults` Default arguments: api, latitude, longitude, altitude, reverse_zoom and user, plus the map size, zoom, location and style for map providers.

## Files

- [includes/class-geo-provider.php:81](https://github.com/dshanske/simple-location/blob/trunk/includes/class-geo-provider.php#L81)
```php
apply_filters( 'sloc_geo_provider_defaults', $defaults )
```

- [includes/class-map-provider.php:288](https://github.com/dshanske/simple-location/blob/trunk/includes/class-map-provider.php#L288)
```php
apply_filters( 'sloc_geo_provider_defaults', $defaults )
```



[← All Hooks](Hooks)


Filters the default arguments of weather providers.

## Auto-generated Example

```php
add_filter(
   'sloc_weather_provider_defaults',
    function( array $defaults ) {
        // Your code here.
        return $defaults;
    }
);
```

## Parameters

- *`array`* `$defaults` Default arguments: api, latitude, longitude, station_id, cache_time and units.

## Files

- [includes/class-weather-provider.php:85](https://github.com/dshanske/simple-location/blob/trunk/includes/class-weather-provider.php#L85)
```php
apply_filters( 'sloc_weather_provider_defaults', $defaults )
```



[← All Hooks](Hooks)

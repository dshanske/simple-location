
Filters the default arguments for displaying a location.

Arguments passed to the display function override these defaults.

## Auto-generated Example

```php
add_filter(
   'simple_location_display_defaults',
    function( array $defaults ) {
        // Your code here.
        return $defaults;
    }
);
```

## Parameters

- *`array`* `$defaults` Default display arguments, such as weather, altitude, taxonomy, link, icon, markup and wrapper-type.

## Files

- [includes/class-geo-data.php:1200](https://github.com/dshanske/simple-location/blob/trunk/includes/class-geo-data.php#L1200)
```php
apply_filters( 'simple_location_display_defaults', $defaults )
```



[← All Hooks](Hooks)

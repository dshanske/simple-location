
This filter is documented in includes/class-geo-provider.php

## Auto-generated Example

```php
add_filter(
   'location_display_name',
    function(
        string $display_name,
        array $reverse
    ) {
        // Your code here.
        return $display_name;
    },
    10,
    2
);
```

## Parameters

- *`string`* `$display_name` Display name.
- *`array`* `$reverse` Address properties from the lookup.

## Files

- [includes/class-geo-provider.php:170](https://github.com/dshanske/simple-location/blob/trunk/includes/class-geo-provider.php#L170)
```php
apply_filters( 'location_display_name', $reverse['display_name'], $reverse )
```

- [includes/class-geo-provider.php:210](https://github.com/dshanske/simple-location/blob/trunk/includes/class-geo-provider.php#L210)
```php
apply_filters( 'location_display_name', $return, $reverse )
```



[← All Hooks](Hooks)

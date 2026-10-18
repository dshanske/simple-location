## Auto-generated Example

```php
add_filter(
   'location_display_name',
    function(
        $display_name,
        $reverse
    ) {
        // Your code here.
        return $display_name;
    },
    10,
    2
);
```

## Parameters

- `$display_name` Other variable names: `$return`
- `$reverse`

## Files

- [includes/class-geo-provider.php:140](https://github.com/dshanske/simple-location/blob/trunk/includes/class-geo-provider.php#L140)
```php
apply_filters( 'location_display_name', $reverse['display_name'], $reverse )
```

- [includes/class-geo-provider.php:169](https://github.com/dshanske/simple-location/blob/trunk/includes/class-geo-provider.php#L169)
```php
apply_filters( 'location_display_name', $return, $reverse )
```



[← All Hooks](Hooks)

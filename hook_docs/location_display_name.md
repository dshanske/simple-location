## Auto-generated Example

```php
add_filter(
    'location_display_name',
    function (
        $display_name,
        $reverse
    ) {
        // Your code here
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

- [includes/class-geo-provider.php:137](https://github.com/dshanske/simple-location/blob/main/includes/class-geo-provider.php#L137)
```php
apply_filters( 'location_display_name', $reverse['display_name'], $reverse )
```

- [includes/class-geo-provider.php:166](https://github.com/dshanske/simple-location/blob/main/includes/class-geo-provider.php#L166)
```php
apply_filters( 'location_display_name', $return, $reverse )
```



[Hooks](Hooks)

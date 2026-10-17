
Filters the largest map size requested from the custom static map server.

Maps larger than this are shrunk to fit, keeping their shape. A value of 0 removes that limit.

## Auto-generated Example

```php
add_filter(
   'sloc_staticmap_max_size',
    function( array $max_size ) {
        // Your code here.
        return $max_size;
    }
);
```

## Parameters

- *`array`* `$max_size`
  - *`int`* `$width` Width. Default 1280, or SLOC_STATICMAP_MAX_WIDTH if defined.
  - *`int`* `$height` Height. Default 1280, or SLOC_STATICMAP_MAX_HEIGHT if defined.

## Files

- [includes/map/class-map-provider-staticmap.php:64](https://github.com/dshanske/simple-location/blob/main/includes/map/class-map-provider-staticmap.php#L64)
```php
apply_filters( 'sloc_staticmap_max_size', $max_size )
```



[← All Hooks](Hooks)


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
  - *`int`* `$width` Width. Default the map server's limit, or 1280 if it isn't known; SLOC_STATICMAP_MAX_WIDTH if defined.
  - *`int`* `$height` Height. Default the map server's limit, or 1280 if it isn't known; SLOC_STATICMAP_MAX_HEIGHT if defined.

## Files

- [includes/map/class-map-provider-staticmap.php:444](https://github.com/dshanske/simple-location/blob/trunk/includes/map/class-map-provider-staticmap.php#L444)
```php
apply_filters( 'sloc_staticmap_max_size', $max_size )
```



[← All Hooks](Hooks)

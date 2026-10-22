
Filters the alternative text of a static map image.

## Auto-generated Example

```php
add_filter(
   'sloc_map_alt',
    function(
        string $alt,
        Map_Provider
    ) {
        // Your code here.
        return $alt;
    },
    10,
    2
);
```

## Parameters

- *`string`* `$alt` Alternative text.
- `Map_Provider` $map Map provider, set to the map's location. Other variable names: `$this`

## Files

- [includes/class-map-provider.php:1029](https://github.com/dshanske/simple-location/blob/trunk/includes/class-map-provider.php#L1029)
```php
apply_filters( 'sloc_map_alt', $alt, $this )
```



[← All Hooks](Hooks)


Filters a post's GeoJSON feature. Return null to leave the post out.

## Auto-generated Example

```php
add_filter(
   'sloc_geojson_feature',
    function(
        array|null,
        WP_Post $post
    ) {
        // Your code here.
        return array|null;
    },
    10,
    2
);
```

## Parameters

- `array|null` $feature Point feature, with the post's name, url, published time and address as properties. Other variable names: `$feature`
- *`WP_Post`* `$post` Post.

## Files

- [includes/class-sloc-geojson.php:154](https://github.com/dshanske/simple-location/blob/trunk/includes/class-sloc-geojson.php#L154)
```php
apply_filters( 'sloc_geojson_feature', $feature, $post )
```



[← All Hooks](Hooks)

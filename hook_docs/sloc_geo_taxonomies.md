Filters the taxonomies whose terms get geo meta registered.

## Auto-generated Example

```php
add_filter(
   'sloc_geo_taxonomies',
    function( array $taxonomies ) {
        // Your code here.
        return $taxonomies;
    }
);
```

## Parameters

- *`string[]`* `$taxonomies` Taxonomy names. Default empty.

## Files

- [includes/class-geo-data.php:](https://github.com/dshanske/simple-location/blob/main/includes/class-geo-data.php#L)
```php
apply_filters( 'sloc_geo_taxonomies', array() )
```



[← All Hooks](Hooks)


Filters the taxonomies whose terms get geo meta registered.

## Auto-generated Example

```php
add_filter(
   'sloc_geo_taxonomies',
    function( string[] $taxonomies ) {
        // Your code here.
        return $taxonomies;
    }
);
```

## Parameters

- *`string[]`* `$taxonomies` Taxonomy names. Default empty.

## Files

- [includes/class-geo-data.php:751](https://github.com/dshanske/simple-location/blob/trunk/includes/class-geo-data.php#L751)
```php
apply_filters( 'sloc_geo_taxonomies', array() )
```



[← All Hooks](Hooks)

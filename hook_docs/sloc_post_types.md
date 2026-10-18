
Filters the post types that get the location metabox and admin column.

## Auto-generated Example

```php
add_filter(
   'sloc_post_types',
    function( string[] $post_types ) {
        // Your code here.
        return $post_types;
    }
);
```

## Parameters

- *`string[]`* `$post_types` Post types that support geo-location.

## Files

- [includes/class-geo-base.php:112](https://github.com/dshanske/simple-location/blob/trunk/includes/class-geo-base.php#L112)
```php
apply_filters( 'sloc_post_types', get_post_types_by_support( 'geo-location' ) )
```



[← All Hooks](Hooks)

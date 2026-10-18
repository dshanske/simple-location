## Auto-generated Example

```php
add_filter(
   'sloc_post_types',
    function( $post_types_by_support ) {
        // Your code here.
        return $post_types_by_support;
    }
);
```

## Parameters

- `$post_types_by_support`

## Files

- [includes/class-geo-base.php:91](https://github.com/dshanske/simple-location/blob/trunk/includes/class-geo-base.php#L91)
```php
apply_filters( 'sloc_post_types', get_post_types_by_support( 'geo-location' ) )
```



[← All Hooks](Hooks)

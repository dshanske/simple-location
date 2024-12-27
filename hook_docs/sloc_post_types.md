## Auto-generated Example

```php
add_filter(
    'sloc_post_types',
    function ( $post_types_by_support ) {
        // Your code here
        return $post_types_by_support;
    }
);
```

## Parameters

- `$post_types_by_support`

## Files

- [includes/class-geo-base.php:89](https://github.com/dshanske/simple-location/blob/main/includes/class-geo-base.php#L89)
```php
apply_filters( 'sloc_post_types', get_post_types_by_support( 'geo-location' ) )
```



[Hooks](Hooks)

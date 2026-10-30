
Filters whether to add the location and map to the current post's content.

Return false where they are shown another way, such as in an email that renders them itself, instead of
removing the plugin's `the_content` callbacks.

## Auto-generated Example

```php
add_filter(
   'sloc_content_location',
    function(
        bool $add,
        WP_Post $post = null
    ) {
        // Your code here.
        return $add;
    },
    10,
    2
);
```

## Parameters

- *`bool`* `$add` Whether to add them. Default true.
- *`WP_Post|null`* `$post` The current post.

## Files

- [includes/class-geo-data.php:1082](https://github.com/dshanske/simple-location/blob/trunk/includes/class-geo-data.php#L1082)
```php
apply_filters( 'sloc_content_location', true, get_post() )
```



[← All Hooks](Hooks)

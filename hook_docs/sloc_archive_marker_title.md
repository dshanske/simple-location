
Filters the label of an archive map marker, shown when hovering over it and read by screen readers.

## Auto-generated Example

```php
add_filter(
   'sloc_archive_marker_title',
    function(
        string,
        int $post_id,
        array $location
    ) {
        // Your code here.
        return string;
    },
    10,
    3
);
```

## Parameters

- `string` $title    Plain text label: the post's venue, or else its location, address or coordinates. Other variable names: `$title`
- *`int`* `$post_id` Post ID.
- *`array`* `$location` Array( latitude, longitude ) of the marker.

## Files

- [includes/class-map-provider.php:1471](https://github.com/dshanske/simple-location/blob/trunk/includes/class-map-provider.php#L1471)
```php
apply_filters( 'sloc_archive_marker_title', $title, $post_id, $location )
```



[← All Hooks](Hooks)

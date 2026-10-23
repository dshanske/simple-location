
Filters whether a hidden location is shown to the current user.

Users who can read an object's location see a private or protected location, marked as hidden. Return false
when the output is generated for others to see, such as content sent to a federated service.

## Auto-generated Example

```php
add_filter(
   'sloc_show_hidden_location',
    function(
        bool $show,
        string $type,
        int $id
    ) {
        // Your code here.
        return $show;
    },
    10,
    3
);
```

## Parameters

- *`bool`* `$show` Whether to show a hidden location. Defaults to whether the current user can read it.
- *`string`* `$type` Object type: post, comment, term or user.
- *`int`* `$id` Object ID.

## Files

- [includes/class-geo-data.php:1162](https://github.com/dshanske/simple-location/blob/trunk/includes/class-geo-data.php#L1162)
```php
apply_filters( 'sloc_show_hidden_location', Geo_Base::current_user_can_read( sloc_get_object_from_id( $type, $id ) ), $type, $id )
```



[← All Hooks](Hooks)

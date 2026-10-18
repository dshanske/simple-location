
Fires after the location metabox is shown, so other plugins can add to it.

## Auto-generated Example

```php
add_action(
   'simple_location_sidebox',
    function(
        WP_Screen $screen = null,
        WP_Post|WP_Comment $object,
        array $args
    ) {
        // Your code here.
    },
    10,
    3
);
```

## Parameters

- *`WP_Screen|null`* `$screen` Current admin screen.
- *`WP_Post|WP_Comment`* `$object` Post or comment being edited.
- *`array`* `$args` Metabox arguments.

## Files

- [includes/class-geo-base.php:137](https://github.com/dshanske/simple-location/blob/trunk/includes/class-geo-base.php#L137)
```php
do_action( 'simple_location_sidebox', get_current_screen(), $object, $args )
```



[← All Hooks](Hooks)

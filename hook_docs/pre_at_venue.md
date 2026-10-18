
Short-circuits the checking for a venue if it is not stored as normal.

Default null.

## Auto-generated Example

```php
add_filter(
   'pre_at_venue',
    function(
        mixed $value,
        float $lat,
        float $lnt
    ) {
        // Your code here.
        return $value;
    },
    10,
    3
);
```

## Parameters

- *`mixed`* `$value` The boolean value as to whether someone is at a venue.
- *`float`* `$lat` Latitude.
- *`float`* `$lnt` Longitude.

## Files

- [includes/class-post-venue.php:479](https://github.com/dshanske/simple-location/blob/trunk/includes/class-post-venue.php#L479)
```php
apply_filters( 'pre_at_venue', null, $lat, $lng )
```



[← All Hooks](Hooks)

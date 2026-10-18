
Short-circuits the checking for a venue if it is not stored as normal.

Default null.

## Auto-generated Example

```php
add_filter(
   'pre_nearby_venue',
    function(
        mixed $value,
        float $lat,
        float $lnt,
        int $radius
    ) {
        // Your code here.
        return $value;
    },
    10,
    4
);
```

## Parameters

- *`mixed`* `$value` The boolean value as to whether someone is at a venue.
- *`float`* `$lat` Latitude.
- *`float`* `$lnt` Longitude.
- *`int`* `$radius` Radius.

## Files

- [includes/class-post-venue.php:408](https://github.com/dshanske/simple-location/blob/trunk/includes/class-post-venue.php#L408)
```php
apply_filters( 'pre_nearby_venue', null, $lat, $lng, $radius )
```



[← All Hooks](Hooks)


Short-circuits the checking for a venue if it is not stored as normal.

Default null.

## Auto-generated Example

```php
add_filter(
    'pre_at_venue',
    function (
        Simple_Location\mixed $value,
        Simple_Location\float $lat,
        Simple_Location\float $lnt
    ) {
        // Your code here
        return $value;
    },
    10,
    3
);
```

## Parameters

- *`Simple_Location\mixed`* `$value` The boolean value as to whether someone is at a venue.
- *`Simple_Location\float`* `$lat` Latitude.
- *`Simple_Location\float`* `$lnt` Longitude.

## Files

- [includes/class-post-venue.php:446](https://github.com/dshanske/simple-location/blob/main/includes/class-post-venue.php#L446)
```php
apply_filters( 'pre_at_venue', null, $lat, $lng )
```



[Hooks](Hooks)

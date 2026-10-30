
Filters the places near a location.

## Auto-generated Example

```php
add_filter(
   'sloc_places',
    function(
        array[],
        float $lat,
        float $lng,
        array
    ) {
        // Your code here.
        return array[];
    },
    10,
    4
);
```

## Parameters

- `array[]` $places Places as mf2 h-cards, the site's venues first. Other variable names: `$array_map_cards`
- *`float`* `$lat` Latitude.
- *`float`* `$lng` Longitude.
- `array` $args   Arguments: 'limit', 'local' and 'provider'. Other variable names: `$args`

## Files

- [includes/class-post-venue.php:712](https://github.com/dshanske/simple-location/blob/trunk/includes/class-post-venue.php#L712)
```php
apply_filters( 'sloc_places', array_map( array( __CLASS__, 'jf2_to_mf2' ), $cards ), $lat, $lng, $args )
```



[← All Hooks](Hooks)

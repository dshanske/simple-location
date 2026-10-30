
Filters the links shown for a venue.

## Auto-generated Example

```php
add_filter(
   'sloc_venue_links',
    function(
        array[],
        int $venue_id
    ) {
        // Your code here.
        return array[];
    },
    10,
    2
);
```

## Parameters

- `array[]` $links    Links, each with 'url', 'name' and 'type', 'website' or 'alternate'. Other variable names: `$links`
- *`int`* `$venue_id` Venue ID.

## Files

- [includes/class-post-venue.php:1452](https://github.com/dshanske/simple-location/blob/trunk/includes/class-post-venue.php#L1452)
```php
apply_filters( 'sloc_venue_links', $links, $venue_id )
```



[← All Hooks](Hooks)

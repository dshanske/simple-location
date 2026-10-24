
Filters the venue a check-in is at.

## Auto-generated Example

```php
add_filter(
   'sloc_find_venue',
    function(
        int|false $venue_id,
        array $venue
    ) {
        // Your code here.
        return $venue_id;
    },
    10,
    2
);
```

## Parameters

- *`int|false`* `$venue_id` Venue ID, or false to create a venue.
- *`array`* `$venue` Check-in or venue properties.

## Files

- [includes/class-post-venue.php:899](https://github.com/dshanske/simple-location/blob/trunk/includes/class-post-venue.php#L899)
```php
apply_filters( 'sloc_find_venue', $venue_id, $venue )
```



[← All Hooks](Hooks)

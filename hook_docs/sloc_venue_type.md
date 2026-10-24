
Filters the venue type given to a new venue.

## Auto-generated Example

```php
add_filter(
   'sloc_venue_type',
    function(
        int $term_id,
        array $venue
    ) {
        // Your code here.
        return $term_id;
    },
    10,
    2
);
```

## Parameters

- *`int`* `$term_id` Venue type term ID, or 0 for none.
- *`array`* `$venue` Check-in or venue properties.

## Files

- [includes/class-post-venue.php:997](https://github.com/dshanske/simple-location/blob/trunk/includes/class-post-venue.php#L997)
```php
apply_filters( 'sloc_venue_type', $term_id, $venue )
```



[← All Hooks](Hooks)


Filters the hosts of sites with pages about places.

A check-in URL on one of these hosts is kept as an alternate URL of the venue, not its website.

## Auto-generated Example

```php
add_filter(
   'sloc_venue_alternate_hosts',
    function( string[] ) {
        // Your code here.
        return string[];
    }
);
```

## Parameters

- `string[]` $hosts Hosts, such as 'openstreetmap.org'. Subdomains match too. Other variable names: `$hosts`

## Files

- [includes/class-post-venue.php:1675](https://github.com/dshanske/simple-location/blob/trunk/includes/class-post-venue.php#L1675)
```php
apply_filters( 'sloc_venue_alternate_hosts', $hosts )
```



[← All Hooks](Hooks)

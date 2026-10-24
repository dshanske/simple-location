
Filters the place fields asked of Google. Fields beyond Nearby Search Pro, such as websiteUri, cost more.

## Auto-generated Example

```php
add_filter(
   'sloc_google_venue_fields',
    function( string[] ) {
        // Your code here.
        return string[];
    }
);
```

## Parameters

- `string[]` $fields Place fields, such as 'displayName'. Other variable names: `self::FIELDS`

## Files

- [includes/venue/class-venue-provider-google.php:73](https://github.com/dshanske/simple-location/blob/trunk/includes/venue/class-venue-provider-google.php#L73)
```php
apply_filters( 'sloc_google_venue_fields', self::FIELDS )
```



[← All Hooks](Hooks)


Filters the shared category and location icon a provider's venue is given.

## Auto-generated Example

```php
add_filter(
   'sloc_venue_category',
    function(
        $match_array_with_category_a_key_of_venue_provider_categories_and_icon,
        array
    ) {
        // Your code here.
        return $match_array_with_category_a_key_of_venue_provider_categories_and_icon;
    },
    10,
    2
);
```

## Parameters

- `$match_array_with_category_a_key_of_venue_provider_categories_and_icon` Other variable names: `$match`
- `array` $card  Venue as an h-card, with the provider's own categories as 'category'. Other variable names: `$card`

## Files

- [includes/class-venue-provider.php:559](https://github.com/dshanske/simple-location/blob/trunk/includes/class-venue-provider.php#L559)
```php
apply_filters( 'sloc_venue_category', $match, $card )
```



[← All Hooks](Hooks)

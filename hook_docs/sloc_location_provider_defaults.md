
Filters the default arguments of location providers.

## Auto-generated Example

```php
add_filter(
   'sloc_location_provider_defaults',
    function( array $defaults ) {
        // Your code here.
        return $defaults;
    }
);
```

## Parameters

- *`array`* `$defaults` Default arguments: api and user.

## Files

- [includes/class-location-provider.php:142](https://github.com/dshanske/simple-location/blob/trunk/includes/class-location-provider.php#L142)
```php
apply_filters( 'sloc_location_provider_defaults', $defaults )
```



[← All Hooks](Hooks)

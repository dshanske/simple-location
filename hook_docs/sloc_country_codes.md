
Filters the country codes used instead of others.

The bundled data and the geocoders decide a location's country by who controls it, or by ISO 3166, without
taking a position on disputed areas. A site can choose otherwise, for every location with a code.


SLOC_COUNTRY_CODES constant, or none.

## Auto-generated Example

```php
add_filter(
   'sloc_country_codes',
    function( $codes_country_code_>_the_code_to_use_instead_such_as_default_the ) {
        // Your code here.
        return $codes_country_code_>_the_code_to_use_instead_such_as_default_the;
    }
);
```

## Parameters

- `$codes_country_code_>_the_code_to_use_instead_such_as_default_the` Other variable names: `$codes`

## Files

- [includes/class-sloc-address-finisher.php:43](https://github.com/dshanske/simple-location/blob/trunk/includes/class-sloc-address-finisher.php#L43)
```php
apply_filters( 'sloc_country_codes', $codes )
```



[← All Hooks](Hooks)

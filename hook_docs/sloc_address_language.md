
Filters the language country and region names are shown in.

## Auto-generated Example

```php
add_filter(
   'sloc_address_language',
    function(
        string string,
        string
    ) {
        // Your code here.
        return string;
    },
    10,
    2
);
```

## Parameters

- *`string`* `string` $language Language code, such as en, from the site's locale. Other variable names: `$____language__en___language`
- `string` $locale   The site's locale, such as en_US. Other variable names: `$locale`

## Files

- [includes/class-sloc-address-format.php:234](https://github.com/dshanske/simple-location/blob/trunk/includes/class-sloc-address-format.php#L234)
```php
apply_filters( 'sloc_address_language', '' === $language ? 'en' : $language, (string) $locale )
```



[← All Hooks](Hooks)

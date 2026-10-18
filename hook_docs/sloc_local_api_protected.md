> **DEPRECATED**
> This hook was deprecated in version 6.0.0.
> Use `sloc_local_api_public` instead.

## Auto-generated Example

```php
add_filter(
   'sloc_local_api_protected',
    function(
        array $array,
        string $6_0_0,
        string $sloc_local_api_public
    ) {
        // Your code here.
        return $array;
    },
    10,
    3
);
```

## Parameters

- *`array`* `$array`
- *`string`* `$6_0_0`
- *`string`* `$sloc_local_api_public`

## Files

- [includes/class-rest-geo.php:126](https://github.com/dshanske/simple-location/blob/trunk/includes/class-rest-geo.php#L126)
```php
apply_filters_deprecated( 'sloc_local_api_protected', array( false ), '6.0.0', 'sloc_local_api_public' )
```



[← All Hooks](Hooks)

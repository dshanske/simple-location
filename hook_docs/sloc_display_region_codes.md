
Filters the regions whose code is shown after a locality of the same name.

## Auto-generated Example

```php
add_filter(
   'sloc_display_region_codes',
    function( array string[] ) {
        // Your code here.
        return string[];
    }
);
```

## Parameters

- *`array`* `string[]` $regions Country and region codes, such as 'US-NY' for New York, NY. Other variable names: `$string_list`

## Files

- [includes/class-geo-provider.php:168](https://github.com/dshanske/simple-location/blob/trunk/includes/class-geo-provider.php#L168)
```php
apply_filters( 'sloc_display_region_codes', array( 'US-NY' ) )
```



[← All Hooks](Hooks)

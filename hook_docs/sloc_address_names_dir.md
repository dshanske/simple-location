
Filters the folder of the country and region names by language.

## Auto-generated Example

```php
add_filter(
   'sloc_address_names_dir',
    function( string $dir ) {
        // Your code here.
        return $dir;
    }
);
```

## Parameters

- *`string`* `$dir` Path of data/names/, with a trailing slash.

## Files

- [includes/class-sloc-address-format.php:257](https://github.com/dshanske/simple-location/blob/trunk/includes/class-sloc-address-format.php#L257)
```php
apply_filters( 'sloc_address_names_dir', plugin_dir_path( __DIR__ ) . 'data/names/' )
```



[← All Hooks](Hooks)


Filters the path of the address format table.

## Auto-generated Example

```php
add_filter(
   'sloc_address_format_file',
    function( string $file ) {
        // Your code here.
        return $file;
    }
);
```

## Parameters

- *`string`* `$file` Path of data/address-formats.json.

## Files

- [includes/class-sloc-address-format.php:44](https://github.com/dshanske/simple-location/blob/trunk/includes/class-sloc-address-format.php#L44)
```php
apply_filters( 'sloc_address_format_file', plugin_dir_path( __DIR__ ) . 'data/address-formats.json' )
```



[← All Hooks](Hooks)


Filters the path of the region names built from Wikidata.

## Auto-generated Example

```php
add_filter(
   'sloc_region_names_file',
    function( string $file ) {
        // Your code here.
        return $file;
    }
);
```

## Parameters

- *`string`* `$file` Path of data/region-names.json.

## Files

- [includes/class-sloc-address-format.php:81](https://github.com/dshanske/simple-location/blob/trunk/includes/class-sloc-address-format.php#L81)
```php
apply_filters( 'sloc_region_names_file', plugin_dir_path( __DIR__ ) . 'data/region-names.json' )
```



[← All Hooks](Hooks)


Filters where stored map images are kept.

## Auto-generated Example

```php
add_filter(
   'sloc_map_cache_directory',
    function( array $directory ) {
        // Your code here.
        return $directory;
    }
);
```

## Parameters

- *`array`* `$directory`
  - *`string`* `$path` Absolute path of the directory.
  - *`string`* `$url` URL of the directory.

## Files

- [includes/class-sloc-map-cache.php:640](https://github.com/dshanske/simple-location/blob/trunk/includes/class-sloc-map-cache.php#L640)
```php
apply_filters( 'sloc_map_cache_directory', $directory )
```



[← All Hooks](Hooks)

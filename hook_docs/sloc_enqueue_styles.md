
Filters whether the front-end stylesheet is enqueued in the head of the current page.

Return true to load it on every page, as before 6.2.0, such as for a theme that shows locations in ways the
plugin can't detect. Output from the plugin's functions enqueues it anyway, printed in the footer.

## Auto-generated Example

```php
add_filter(
   'sloc_enqueue_styles',
    function( bool $needs ) {
        // Your code here.
        return $needs;
    }
);
```

## Parameters

- *`bool`* `$needs` Whether the page shows location, weather or maps.

## Files

- [simple-location.php:510](https://github.com/dshanske/simple-location/blob/trunk/simple-location.php#L510)
```php
apply_filters( 'sloc_enqueue_styles', $needs )
```



[← All Hooks](Hooks)

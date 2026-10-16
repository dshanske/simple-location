
Filters whether the local airport and timezone lookup endpoints are open to everyone.

By default they require a logged-in user who can read.

## Auto-generated Example

```php
add_filter(
   'sloc_local_api_public',
    function( bool $public ) {
        // Your code here.
        return $public;
    }
);
```

## Parameters

- *`bool`* `$public` Whether anyone, including logged-out visitors, can use the endpoints.

## Files

- [includes/class-rest-geo.php:135](https://github.com/dshanske/simple-location/blob/main/includes/class-rest-geo.php#L135)
```php
apply_filters( 'sloc_local_api_public', $granted )
```



[← All Hooks](Hooks)

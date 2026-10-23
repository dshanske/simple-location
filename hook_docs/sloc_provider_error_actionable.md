
Filters whether a provider error is recorded for administrators to fix.

## Auto-generated Example

```php
add_filter(
   'sloc_provider_error_actionable',
    function(
        bool $actionable,
        WP_Error $error,
        Sloc_Provider $provider
    ) {
        // Your code here.
        return $actionable;
    },
    10,
    3
);
```

## Parameters

- *`bool`* `$actionable` Whether to record it.
- *`WP_Error`* `$error` Error.
- *`Sloc_Provider`* `$provider` Provider that returned it.

## Files

- [includes/class-sloc-provider-errors.php:103](https://github.com/dshanske/simple-location/blob/trunk/includes/class-sloc-provider-errors.php#L103)
```php
apply_filters( 'sloc_provider_error_actionable', $actionable, $error, $provider )
```



[← All Hooks](Hooks)


Filters the one-line message about a provider error, or the provider's recovery.

## Auto-generated Example

```php
add_filter(
   'sloc_provider_error_message',
    function(
        string $message,
        array $record,
        bool $resolved
    ) {
        // Your code here.
        return $message;
    },
    10,
    3
);
```

## Parameters

- *`string`* `$message` Plain text, on one line.
- *`array`* `$record` Recorded error. See `sloc_provider_error`.
- *`bool`* `$resolved` Whether the provider works again.

## Files

- [includes/class-sloc-provider-errors.php:236](https://github.com/dshanske/simple-location/blob/trunk/includes/class-sloc-provider-errors.php#L236)
```php
apply_filters( 'sloc_provider_error_message', $message, $record, $resolved )
```



[← All Hooks](Hooks)

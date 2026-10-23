
Fires when a provider with a recorded error answers again.

## Auto-generated Example

```php
add_action(
   'sloc_provider_error_resolved',
    function(
        string $message,
        array $record
    ) {
        // Your code here.
    },
    10,
    2
);
```

## Parameters

- *`string`* `$message` One line saying the provider works again, for a notification.
- *`array`* `$record` The error that was recorded. See `sloc_provider_error`.

## Files

- [includes/class-sloc-provider-errors.php:199](https://github.com/dshanske/simple-location/blob/trunk/includes/class-sloc-provider-errors.php#L199)
```php
do_action( 'sloc_provider_error_resolved', self::message( $record, true ), $record )
```



[← All Hooks](Hooks)

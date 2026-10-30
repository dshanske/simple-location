
Fires when a provider starts failing with an error an administrator can fix.

## Auto-generated Example

```php
add_action(
   'sloc_provider_error',
    function(
        string $message,
        array $record,
        WP_Error $error
    ) {
        // Your code here.
    },
    10,
    3
);
```

## Parameters

- *`string`* `$message` One line describing the error, for a notification.
- *`array`* `$record` The recorded error: type, provider slug, provider name, code, message, first and last seen as timestamps, count of times seen at most once a minute, and dismissed.
- *`WP_Error`* `$error` Error.

## Files

- [includes/class-sloc-provider-errors.php:168](https://github.com/dshanske/simple-location/blob/trunk/includes/class-sloc-provider-errors.php#L168)
```php
do_action( 'sloc_provider_error', self::message( $errors[ $key ] ), $errors[ $key ], $error )
```



[← All Hooks](Hooks)

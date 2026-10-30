
Filters the weather of an object as plain text.

## Auto-generated Example

```php
add_filter(
   'sloc_weather_text',
    function(
        string $text,
        array $weather,
        string $type,
        int $id
    ) {
        // Your code here.
        return $text;
    },
    10,
    4
);
```

## Parameters

- *`string`* `$text` Plain text, such as "72°F Clear", or an empty string.
- *`array`* `$weather` Weather data.
- *`string`* `$type` Object type.
- *`int`* `$id` Object ID.

## Files

- [includes/class-weather-data.php:965](https://github.com/dshanske/simple-location/blob/trunk/includes/class-weather-data.php#L965)
```php
apply_filters( 'sloc_weather_text', $text, $weather, $type, $id )
```



[← All Hooks](Hooks)

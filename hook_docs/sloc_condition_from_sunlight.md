
Filters the condition estimated from the sunlight measured.

## Auto-generated Example

```php
add_filter(
   'sloc_condition_from_sunlight',
    function(
        int $code,
        float $index,
        float $clear
    ) {
        // Your code here.
        return $code;
    },
    10,
    3
);
```

## Parameters

- *`int`* `$code` Condition code, 800 (clear sky) to 804 (overcast).
- *`float`* `$index` Clearness index: the radiation measured over that of a clear sky.
- *`float`* `$clear` Clear-sky radiation in W/m².

## Files

- [includes/class-weather-provider.php:324](https://github.com/dshanske/simple-location/blob/trunk/includes/class-weather-provider.php#L324)
```php
apply_filters( 'sloc_condition_from_sunlight', $code, $index, $clear )
```



[← All Hooks](Hooks)

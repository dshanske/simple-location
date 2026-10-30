
Filters the category of an air quality index, such as for another scale or other colors.

## Auto-generated Example

```php
add_filter(
   'sloc_aqi_category',
    function(
        array|null,
        int $aqi
    ) {
        // Your code here.
        return array|null;
    },
    10,
    2
);
```

## Parameters

- `array|null` $category Category, with 'slug', 'name', 'color' and 'text_color', or null to show no category. Other variable names: `$category`
- *`int`* `$aqi` Air quality index, rounded.

## Files

- [includes/trait-weather-info.php:700](https://github.com/dshanske/simple-location/blob/trunk/includes/trait-weather-info.php#L700)
```php
apply_filters( 'sloc_aqi_category', $category, $aqi )
```



[← All Hooks](Hooks)

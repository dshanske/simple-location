
Filters the credit shown with a weather provider's weather.

Some providers only require their credit on free plans, such as OpenWeather and Weatherbit; a site on a paid
plan can remove it by returning an empty array. A site can also add a credit for a provider that doesn't
require one.

## Auto-generated Example

```php
add_filter(
   'sloc_weather_credit',
    function(
        array $credit,
        Weather_Provider $provider
    ) {
        // Your code here.
        return $credit;
    },
    10,
    2
);
```

## Parameters

- *`array`* `$credit`
- *`Weather_Provider`* `$provider` Weather provider.
  - *`string`* `$text` Credit text.
  - *`string`* `$url` Link for the credit.
  - *`bool`* `$adjacent` Whether the credit must be next to the weather, rather than in the footer.

## Files

- [includes/class-weather-provider.php:144](https://github.com/dshanske/simple-location/blob/trunk/includes/class-weather-provider.php#L144)
```php
apply_filters( 'sloc_weather_credit', $this->attribution(), $this )
```



[← All Hooks](Hooks)


Filters the language of map labels, for map providers that can translate them.

Google and TomTom take the whole tag; HERE and Geoapify take its two-letter language code.


leaves the labels in each place's local language.

## Auto-generated Example

```php
add_filter(
   'sloc_map_language',
    function(
        string,
        Map_Provider $map
    ) {
        // Your code here.
        return string;
    },
    10,
    2
);
```

## Parameters

- `string` $language Language tag, such as `en-US`. Default the site's language. An empty string Other variable names: `$bloginfo`
- *`Map_Provider`* `$map` Map provider.

## Files

- [includes/class-map-provider.php:812](https://github.com/dshanske/simple-location/blob/trunk/includes/class-map-provider.php#L812)
```php
apply_filters( 'sloc_map_language', get_bloginfo( 'language' ), $this )
```



[← All Hooks](Hooks)

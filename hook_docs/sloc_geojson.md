
Filters the GeoJSON FeatureCollection of a feed.

## Auto-generated Example

```php
add_filter(
   'sloc_geojson',
    function(
        array array,
        WP_Post[] $posts
    ) {
        // Your code here.
        return array;
    },
    10,
    2
);
```

## Parameters

- *`array`* `array` $collection FeatureCollection, with 'type' and 'features'. Other variable names: `$string_list`
- *`WP_Post[]`* `$posts` Posts in the feed, including those without a public location.

## Files

- [includes/class-sloc-geojson.php:99](https://github.com/dshanske/simple-location/blob/trunk/includes/class-sloc-geojson.php#L99)
```php
apply_filters(
			'sloc_geojson',
			array(
				'type'     => 'FeatureCollection',
				'features' => $features,
			),
			$posts
		)
```



[← All Hooks](Hooks)

## Auto-generated Example

```php
add_filter(
   'default_sloc_aspect_ratios',
    function( array $string_list ) {
        // Your code here.
        return $string_list;
    }
);
```

## Parameters

- *`array`* `$string_list`

## Files

- [includes/class-loc-config.php:854](https://github.com/dshanske/simple-location/blob/trunk/includes/class-loc-config.php#L854)
```php
apply_filters(
			'default_sloc_aspect_ratios',
			array(
				'1.77777777778' => __( 'Widescreen', 'simple-location' ),
				'1'             => __( 'Square', 'simple-location' ),
				'1.333333333'   => __( 'Medium Format', 'simple-location' ),
				'3'             => __( 'Panorama', 'simple-location' ),
				'12'            => __( 'Circle-Vision 360', 'simple-location' ),
			)
		)
```



[← All Hooks](Hooks)

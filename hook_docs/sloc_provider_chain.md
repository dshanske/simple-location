
Filters the providers a lookup tries, in order.

## Auto-generated Example

```php
add_filter(
   'sloc_provider_chain',
    function(
        string[] $slugs,
        string
    ) {
        // Your code here.
        return $slugs;
    },
    10,
    2
);
```

## Parameters

- *`string[]`* `$slugs` Provider slugs, at most three.
- `string` $type  Provider type, such as 'weather'. Other variable names: `$type`

## Files

- [includes/class-sloc-provider-chain.php:78](https://github.com/dshanske/simple-location/blob/trunk/includes/class-sloc-provider-chain.php#L78)
```php
apply_filters( 'sloc_provider_chain', $slugs, $type )
```



[← All Hooks](Hooks)

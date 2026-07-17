# Tolerate Varied ES Response Shapes

Parse responses defensively: `data_get` with defaults, and handle both `hits.total` forms.

ES returns `hits.total` as an object (`{value, relation}`) by default, but as a bare int under `rest_total_hits_as_int`. The parser must accept either so pagination works under both — and never crash on a missing key.

```php
$total = data_get($response, 'hits.total', 0);

return new ElasticPaginator(
    items: data_get($response, 'hits.hits', []),
    total: is_array($total) ? ($total['value'] ?? 0) : (int) $total,
    perPage: $size ?: config('stretch.query.default_size'),
    currentPage: $size ? (int) floor($request->getFrom() / $size) + 1 : 1,
);
```

- Reach into response nesting with `data_get($response, 'a.b.c', $default)`, never raw `$response['a']['b']` — missing keys must degrade, not fatal.
- Normalize both total shapes: `is_array($total) ? $total['value'] ?? 0 : (int) $total`.
- Derive the current page from the builder's `from`/`size` (`floor(from/size)+1`), guarding division when size is 0/null.

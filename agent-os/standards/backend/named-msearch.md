# Named msearch by Key

`MultiQueryBuilder` pairs named queries to positional msearch responses by sorting keys identically at build AND remap.

Elasticsearch `_msearch` returns a positional `responses[]` array. Sorting the queries the same way in both places guarantees each name lines up with its own result, and makes the cache key order-independent.

```php
// build(): sortKeys() before emitting header/body pairs
$queries = collect($this->queries)->sortKeys()->toArray();

// execute(): remap positional responses back onto the sorted names
$key = -1;
$results['responses'] = collect($this->queries)->sortKeys()->map(function () use (&$results, &$key) {
    $key++;
    return Arr::get($results, "responses.{$key}");
})->toArray();
```

- `add($name, ...)` uses `$name` as the index fallback when the sub-query sets none (`getIndex() ?? $name`).
- Callers read results by name: `$results['responses']['posts']`.
- Empty builder short-circuits: `execute()` returns `['responses' => []]` without hitting ES.
- Any change to build ordering MUST be mirrored in the remap, or names and results desync silently.

See [[structural-cache-keys]] — sorted build() is what makes the multi cache key order-stable.

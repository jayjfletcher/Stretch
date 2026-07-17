# Structural Cache Keys

Cache key = prefix + connection + indexes + `sha1(serialize(Arr::sortRecursive(build())))`.

The key is derived from the built query STRUCTURE, not a manual string. `sortRecursive` normalizes clause/key order so the same logical query always yields the same key regardless of build order — order variations don't fragment the cache.

```php
public function getCacheKey(): string
{
    $sorted = Arr::sortRecursive($this->build());
    $hash = sha1(serialize($sorted));
    $indexes = $this->getIndexes()->implode(':');

    return $this->getCachePrefix().$this->getConnectionName().':'.$indexes.$hash;
}
```

- Scoped by connection AND index: identical queries on different connections/indexes get distinct keys — no cross-connection collisions.
- `build()` must stay pure (no side effects) so key generation is safe to call independently of `execute()`.
- Any new state that changes query semantics MUST flow through `build()`, or it won't affect the key and will serve stale cache.

See [[fluent-builder]] (build() purity) and [[immutable-connection-switch]] (connection scoping).

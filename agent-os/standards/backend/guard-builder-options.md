# Guard Builder Options by Type

When a builder option only applies to certain shapes, `build()` throws `LogicException` on misuse — never silently drops it.

Silently ignoring an unsupported option hides a coding mistake. A `LogicException` (programmer error) surfaces it at build time with an actionable message, beating a cryptic downstream ES parse error.

```php
if (isset($agg['terms'])) {
    $agg['terms']['size'] = min($this->size ?? config('stretch.aggregations.default_size'),
                                config('stretch.aggregations.max_buckets'));
} elseif ($this->size !== null) {
    throw new LogicException(
        sprintf('size() is only supported on terms aggregations, [%s] given.', $this->aggregationType())
    );
}
```

- Validate at `build()`, not in the setter — the setter doesn't yet know the final aggregation type.
- Message names the offending option AND the actual type (`$this->aggregationType()`).
- `orderBy()` follows the same guard (allowed on terms/histogram/date_histogram only).
- Terms aggregations always get a config-clamped `size` (`min(size ?? default, max_buckets)`) — bucket explosion is capped by default.

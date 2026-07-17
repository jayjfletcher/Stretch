# Callback Sub-Builders

Composite clauses take a callback receiving a FRESH builder; the parent extracts the built result.

Keeps sub-query state isolated from the parent — nothing leaks across clause boundaries.

```php
public function nested(string $path, callable $callback): static
{
    $sub = new ElasticsearchQueryBuilder($this->client, $this->manager);
    $callback($sub);
    $query = $sub->build()['query'] ?? null;
    // ...attach (see empty-clause-skip)
}
```

- Query-context clauses (`nested`, `filter`, `postFilter`) pass `$this->client, $this->manager` so connection context carries into the sub-builder.
- Pure structural sub-builders build without executing, so they need no client: `BoolQueryBuilder` (`new ElasticsearchQueryBuilder`), `AggregationBuilder`, `RetrieverBuilder`.
- Parent reads `$sub->build()['query']` (or `->build()` for aggs/retrievers) — never reaches into sub-builder internals.
- New composite clause → new dedicated builder class implementing its contract; don't inline clause assembly in the main builder.

See [[empty-clause-skip]] for the null-skip rule on the extracted query.

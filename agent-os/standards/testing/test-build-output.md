# Test build() Output, Not execute()

Query-shape tests construct a bare builder (no client) and assert on `build()` — never mock ES.

`build()` is pure, so DSL-structure assertions need no Elasticsearch or mock: fast, deterministic, and they test the array mapping directly. Keeps structure tests isolated from dispatch tests.

```php
it('can create a match query', function () {
    $builder = new ElasticsearchQueryBuilder;   // no client
    $builder->match('title', 'Laravel');
    $query = $builder->build();
    expect($query['query']['match']['title']['query'])->toBe('Laravel');
});
```

- Mock the client ONLY when the thing under test is dispatch/wrapping/facade behavior (see [[mock-client-contract]]).
- Assert the exact DSL path, not just presence — `$query['query']['range']['created_at']['gte']`.
- Assert absence too where it matters: `expect($query)->not->toHaveKey('track_total_hits')`.

See [[fluent-builder]] for why build() is pure.

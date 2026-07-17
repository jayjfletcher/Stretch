# Fluent Builder: build() / execute()

Builder methods mutate internal state and return `static`. `build()` assembles the body; `execute()` sends it.

- Every clause/option method returns `$this` (typed `static`) for chaining. Sub-builder entry points (`range()`, `bool()` no-callback) return the sub-builder instead.
- State lives in typed protected arrays/scalars (`$query`, `$aggregations`, `$sort`, `$size`, ...). Methods append; nothing hits ES until `execute()`.
- `build()` is pure — assembles and returns the array body, no side effects. `toArray()` is its alias for inspection.
- `execute()` calls `build()`, wraps in `['index' => ..., 'body' => ...]`, stores `$lastQuery`, dispatches via the client.

## Single-clause unwrap

`build()` emits a lone clause bare; only multiple clauses auto-wrap in `bool.must`:

```php
count($this->query) === 1
    ? $body['query'] = $this->query[0]              // { "match": {...} }
    : $body['query'] = ['bool' => ['must' => $this->query]];
```

Keeps output idiomatic — a single `match` matches hand-written ES DSL, not `bool.must`.

- Precedence in `build()`: `retriever` replaces query+knn entirely (early return); else top-level `knn`; else query (with `filters` → `bool.filter` wrap).

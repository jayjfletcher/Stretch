# Empty-Clause Skip

Composite clauses that receive a callback MUST skip attachment when the callback adds no query.

An empty `bool`/`nested`/`filter` clause makes Elasticsearch reject the whole request. Skipping keeps requests valid when callbacks conditionally add nothing.

- Build the sub-query, read `build()['query'] ?? null`, skip on null:
  ```php
  $sub = new ElasticsearchQueryBuilder;
  $callback($sub);
  $query = $sub->build()['query'] ?? null;
  if ($query === null) {
      return $this; // never attach an empty clause
  }
  ```
- Applies to: `nested()`, `filter()`, `postFilter()`, and `BoolQueryBuilder` must/should/filter/mustNot (via `buildSubQuery()`).
- Any new composite/callback clause MUST follow this — never emit `{"bool": {}}`, `{"nested": {...}}` with no query, etc.

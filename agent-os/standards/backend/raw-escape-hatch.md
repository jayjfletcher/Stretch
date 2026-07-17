# raw() Escape Hatches

Every builder area pairs typed helpers with a raw escape hatch. No ES feature is ever unreachable.

- Typed helpers cover common DSL (`terms()`, `dateHistogram()`, `match()`, ...).
- `raw()` / `rawAggregation()` is the permanent fallback for long-tail or uncovered structures — pass the ES array through verbatim:
  ```php
  $agg->raw('my_agg', ['filter' => [...], 'aggs' => [...]]);
  $builder->rawAggregation('price_stats', ['stats' => ['field' => 'price']]);
  ```
- Add a typed helper when a pattern is used often; leave rare/one-off structures on raw() to keep the API surface small.
- Never block a feature behind "not implemented" — raw() must always accept it.

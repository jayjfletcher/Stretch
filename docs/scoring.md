# Scoring

Stretch exposes Elasticsearch's relevance-tuning queries for blending custom
signals — popularity, recency, proximity — into the score.

## rank_feature

Boost by a numeric `rank_feature` / `rank_features` mapped field using one of
four score functions: `saturation`, `log`, `sigmoid`, or `linear` (default).
Typically used inside a bool query's `should` clauses.

```php
use JayI\Stretch\Facades\Stretch;

Stretch::index('pages')
    ->bool(function ($bool) {
        $bool->must(fn ($q) => $q->match('content', 'laravel'))
            ->should(fn ($q) => $q->rankFeature('pagerank', ['saturation' => ['pivot' => 8]]))
            ->should(fn ($q) => $q->rankFeature('url_length', ['log' => ['scaling_factor' => 4]]));
    })
    ->execute();
```

Score-function options:

| Option | Example |
|--------|---------|
| `saturation` | `['saturation' => ['pivot' => 8]]` |
| `log` | `['log' => ['scaling_factor' => 4]]` |
| `sigmoid` | `['sigmoid' => ['pivot' => 7, 'exponent' => 0.6]]` |
| `linear` | default — omit, or `['linear' => new \stdClass]` |
| `boost` | `['boost' => 2.5]` — stackable with any of the above |

## distance_feature

Boost documents whose `date` / `date_nanos` / `geo_point` field is close to an
origin. Favour recent or nearby documents.

```php
// Favour recent documents
Stretch::index('posts')
    ->bool(function ($bool) {
        $bool->must(fn ($q) => $q->match('title', 'release'))
            ->should(fn ($q) => $q->distanceFeature('created_at', 'now', '7d'));
    })
    ->execute();

// Favour documents near a location
Stretch::index('stores')
    ->distanceFeature('location', [-71.3, 41.15], '1000m')
    ->execute();
```

## script_score

Re-score every matching document with a script.

```php
Stretch::index('posts')
    ->scriptScore(
        fn ($q) => $q->match('title', 'laravel'),
        ['source' => "_score * doc['popularity'].value"],
    )
    ->execute();
```

## function_score

Fine-grained scoring via one or more functions combined with `score_mode` /
`boost_mode`. The callback receives a `FunctionScoreBuilder`.

```php
Stretch::index('posts')
    ->functionScore(function ($fs) {
        $fs->query(fn ($q) => $q->match('title', 'laravel'))
            ->fieldValueFactor('popularity', modifier: 'log1p', factor: 1.2)
            ->gauss('created_at', origin: 'now', scale: '10d')
            ->scoreMode('sum')
            ->boostMode('multiply')
            ->minScore(0.5);
    })
    ->execute();
```

### FunctionScoreBuilder methods

| Method | Purpose |
|--------|---------|
| `query($callback)` | The inner query the functions apply to |
| `fieldValueFactor($field, $modifier, $factor, $missing, $options)` | Score from a numeric field |
| `gauss` / `linear` / `exp` `($field, $origin, $scale, $params)` | Decay functions; `$params` may include `offset`, `decay`, `filter`, `weight` |
| `randomScore($seed, $field, $options)` | Randomised ordering |
| `scriptScore($script, $options)` | Arbitrary script |
| `weight($weight, $filter)` | Standalone weight, optionally filter-scoped |
| `scoreMode($mode)` | Combine function scores: `multiply`, `sum`, `avg`, `first`, `max`, `min` |
| `boostMode($mode)` | Combine with query score: `multiply`, `replace`, `sum`, `avg`, `max`, `min` |
| `minScore($n)` / `maxBoost($n)` | Score floor / ceiling |

Each function accepts a `filter` (callable or clause array) and `weight` in its
`$options` to scope and weight it.

```php
Stretch::index('posts')
    ->functionScore(function ($fs) {
        $fs->query(fn ($q) => $q->matchAll())
            ->weight(3.0, fn ($q) => $q->term('featured', true))
            ->fieldValueFactor('views', modifier: 'log1p', options: [
                'filter' => fn ($q) => $q->term('status', 'published'),
            ]);
    })
    ->execute();
```

## Related

- [Queries](queries.md) — compound queries (`disMax`, `constantScore`, `boosting`)
- [Search Tuning](search-tuning.md) — `minScore`, `rescore`, `explain`

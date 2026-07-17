# Search Tuning

Builder methods that shape the request beyond the query clause — score
thresholds, two-phase rescoring, runtime fields, field selection, suggesters,
and shard routing. All are chainable on the query builder and work with both the
standard and retriever request shapes.

## min_score

Drop hits whose `_score` falls below a threshold.

```php
use JayI\Stretch\Facades\Stretch;

Stretch::index('posts')
    ->match('title', 'laravel')
    ->minScore(1.5)
    ->execute();
```

## explain

Return an `_explanation` on every hit describing how its score was computed —
useful for relevance debugging. See also [`Stretch::explain()`](index-management.md#explain-a-document)
for explaining a single document.

```php
$results = Stretch::index('posts')->match('title', 'laravel')->explain()->execute();
$why = $results['hits']['hits'][0]['_explanation'];
```

## terminate_after

Stop collecting once N documents are gathered per shard — a cheap cap for
existence checks or approximate counts. The response's `terminated_early` flag
indicates whether it triggered.

```php
Stretch::index('logs')->term('level', 'error')->terminateAfter(100)->execute();
```

## rescore

Re-score just the top `window_size` hits per shard with a secondary (typically
more expensive) query, refining ordering without paying the cost across the
whole result set. Call multiple times to chain rescorers.

```php
Stretch::index('posts')
    ->match('title', 'laravel')
    ->rescore(fn ($q) => $q->matchPhrase('title', 'laravel framework'), windowSize: 50, options: [
        'query_weight' => 0.7,
        'rescore_query_weight' => 1.2,
    ])
    ->execute();
```

## runtime_mappings

Define fields computed at query time from a script — no reindex required. They
can be referenced in queries, sorts, aggregations, and `fields`.

```php
Stretch::index('logs')
    ->runtimeMappings([
        'day_of_week' => [
            'type' => 'keyword',
            'script' => ['source' => "emit(doc['@timestamp'].value.dayOfWeekEnum.toString())"],
        ],
    ])
    ->term('day_of_week', 'MONDAY')
    ->execute();
```

## fields & docvalue_fields

Retrieve values formatted per the mapping (including runtime and multi-fields),
independent of `_source`.

```php
Stretch::index('posts')
    ->match('title', 'laravel')
    ->fields(['title', ['field' => 'created_at', 'format' => 'yyyy-MM-dd']])
    ->docvalueFields(['price'])
    ->source(false)
    ->execute();
```

## Suggesters

Autocomplete and did-you-mean via the `suggest` clause. The callback receives a
`SuggestBuilder` exposing `term`, `phrase`, and `completion` suggesters.
Suggestions come back under the response's `suggest` key.

```php
$response = Stretch::index('posts')
    ->suggest(function ($s) {
        $s->term('spellcheck', 'title', 'laravle');           // token-level spell correction
        $s->phrase('did_you_mean', 'title', 'quik brown fox'); // whole-phrase correction
        $s->completion('autocomplete', 'title_suggest', 'lara', ['skip_duplicates' => true]);
    })
    ->size(0)
    ->execute();

$suggestions = $response['suggest'];
```

Set a shared input once with `text()`, and reach for `raw()` for suggester
shapes not covered by the typed helpers:

```php
Stretch::index('posts')
    ->suggest(function ($s) {
        $s->text('laravle')->term('spellcheck', 'title');
        $s->raw('custom', ['term' => ['field' => 'body'], 'text' => 'laravle']);
    })
    ->execute();
```

## Request Routing

`searchType`, `preference`, and `routing` are sent as request parameters (not
body fields).

```php
Stretch::index('posts')
    ->match('title', 'laravel')
    ->searchType('dfs_query_then_fetch') // global term stats — more accurate scoring on small indices
    ->preference('user-42')              // stable shard preference — consistent scoring per user/session
    ->routing(['a', 'b'])                // target specific shards
    ->execute();
```

## Related

- [Scoring](scoring.md) — custom score functions
- [Pagination](pagination.md) — `searchAfter`, point-in-time, scroll

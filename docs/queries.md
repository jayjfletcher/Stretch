# Queries

Stretch provides a fluent query builder for all major Elasticsearch query types. Queries are built via `Stretch::index()` and executed with `->execute()`.

## Match Query

Full-text search that analyzes input text and matches against analyzed fields.

```php
use JayI\Stretch\Facades\Stretch;

// Simple match
$results = Stretch::index('posts')
    ->match('title', 'Laravel Elasticsearch')
    ->execute();

// With options
$results = Stretch::index('posts')
    ->match('title', 'Laravel', ['fuzziness' => 'AUTO', 'operator' => 'and'])
    ->execute();
```

## Match Phrase Query

Matches the exact phrase in order.

```php
$results = Stretch::index('posts')
    ->matchPhrase('content', 'quick brown fox')
    ->execute();

// With slop (allows words between phrase terms)
$results = Stretch::index('posts')
    ->matchPhrase('content', 'quick fox', ['slop' => 1])
    ->execute();
```

## Multi Match Query

Full-text search across multiple fields with optional boosts, fuzziness, and matching strategies.

```php
// Basic multi-field search
$results = Stretch::index('products')
    ->multiMatch('laptop for work', ['name', 'description', 'brand'])
    ->execute();

// With field boosts and options
$results = Stretch::index('products')
    ->multiMatch('laptop for work', ['name^3', 'description', 'brand^2', 'tags^1.5'], [
        'type' => 'best_fields',
        'fuzziness' => 'AUTO',
        'prefix_length' => 2,
        'minimum_should_match' => '75%',
    ])
    ->execute();
```

Supported `type` values: `best_fields` (default), `most_fields`, `cross_fields`, `phrase`, `phrase_prefix`, `bool_prefix`.

## Semantic Search

Meaning-based search using vector embeddings. Requires Elasticsearch with semantic search capabilities and properly indexed embedding fields.

```php
// Simple semantic search
$results = Stretch::index('documents')
    ->semantic('semantic_contents', 'What is Laravel?')
    ->execute();

// With boost
$results = Stretch::index('documents')
    ->semantic('semantic_contents', 'machine learning', ['boost' => 2.0])
    ->execute();

// Combined with filters
$results = Stretch::index('documents')
    ->bool(function ($bool) {
        $bool->must(fn($q) => $q->semantic('semantic_contents', 'Laravel framework'));
        $bool->filter(fn($q) => $q->term('status', 'published'));
    })
    ->execute();
```

## Term Query

Exact value matching on keyword fields. Not analyzed -- use for IDs, statuses, and keyword fields.

```php
$results = Stretch::index('posts')
    ->term('status', 'published')
    ->execute();

// For text fields, use the .keyword sub-field
$results = Stretch::index('posts')
    ->term('category.keyword', 'Technology')
    ->execute();
```

## Terms Query

Match any of multiple exact values.

```php
$results = Stretch::index('posts')
    ->terms('tags', ['php', 'laravel', 'elasticsearch'])
    ->execute();
```

## Exists Query

Find documents where a field has a non-null value.

```php
$results = Stretch::index('posts')
    ->exists('premium_content')
    ->execute();
```

## Wildcard Query

Pattern matching with `*` (any characters) and `?` (single character).

```php
$results = Stretch::index('users')
    ->wildcard('email', '*@example.com')
    ->execute();

$results = Stretch::index('products')
    ->wildcard('sku', 'ABC-???-*')
    ->execute();
```

Note: Wildcard queries can be slow on large datasets. Avoid leading wildcards when possible.

## Fuzzy Query

Approximate string matching that handles typos and misspellings.

```php
$results = Stretch::index('posts')
    ->fuzzy('title', 'Laravl', ['fuzziness' => 'AUTO'])
    ->execute();

// With explicit edit distance
$results = Stretch::index('posts')
    ->fuzzy('title', 'elasticsearch', ['fuzziness' => 2, 'prefix_length' => 3])
    ->execute();
```

## Range Query

Find documents with field values within specified bounds. Supports numeric, date, and string fields.

```php
// Numeric range
$results = Stretch::index('products')
    ->range('price')->gte(100)->lt(500)
    ->execute();

// Date range
$results = Stretch::index('posts')
    ->range('created_at')
        ->gte('2024-01-01')
        ->lte('2024-12-31')
    ->execute();

// With timezone
$results = Stretch::index('events')
    ->range('event_date')
        ->gte('2024-01-01')
        ->lte('2024-12-31')
        ->timezone('America/New_York')
    ->execute();

// With format
$results = Stretch::index('events')
    ->range('event_date')
        ->gte('01/01/2024')
        ->lte('12/31/2024')
        ->format('MM/dd/yyyy')
    ->execute();
```

Available range operators:

| Method | Description |
|--------|-------------|
| `gt($value)` | Greater than (exclusive) |
| `gte($value)` | Greater than or equal (inclusive) |
| `lt($value)` | Less than (exclusive) |
| `lte($value)` | Less than or equal (inclusive) |
| `timezone($tz)` | IANA timezone for date fields |
| `format($fmt)` | Date format pattern |

## Bool Query

Combine multiple query clauses with boolean logic.

```php
$results = Stretch::index('posts')
    ->bool(function ($bool) {
        // Must match (AND, affects scoring)
        $bool->must([
            fn($q) => $q->match('title', 'Laravel'),
            fn($q) => $q->term('category', 'tutorial'),
        ]);

        // Should match (OR, affects scoring)
        $bool->should([
            fn($q) => $q->term('featured', true),
            fn($q) => $q->range('views')->gte(1000),
        ]);

        // Filter (AND, no scoring -- cached by Elasticsearch)
        $bool->filter(fn($q) => $q->range('published_at')->gte('2024-01-01'));

        // Must not match (NOT)
        $bool->mustNot(fn($q) => $q->term('status', 'draft'));

        // Require at least 1 should clause to match
        $bool->minimumShouldMatch(1);
    })
    ->execute();
```

### Bool Clause Types

| Clause | Logic | Scoring | Description |
|--------|-------|---------|-------------|
| `must()` | AND | Yes | Documents must match; contributes to score |
| `should()` | OR | Yes | Documents should match; contributes to score |
| `filter()` | AND | No | Documents must match; no scoring, cached |
| `mustNot()` | NOT | No | Documents must not match |

Each clause accepts a single callable or an array of callables.

### minimumShouldMatch

Control how many `should` clauses must match:

```php
$bool->minimumShouldMatch(2);       // At least 2 must match
$bool->minimumShouldMatch('75%');   // At least 75% must match
```

### Bool Boost

Set a boost factor on the entire bool query. Useful in hybrid search to weight the BM25 query relative to a kNN query:

```php
$results = Stretch::index('products')
    ->bool(function ($bool) {
        $bool->should([
            fn($q) => $q->multiMatch('laptop', ['name^3', 'description']),
            fn($q) => $q->matchPhrase('name', 'laptop', ['boost' => 2.0]),
        ]);
        $bool->minimumShouldMatch(1);
        $bool->boost(0.7); // Weight this bool query at 0.7
    })
    ->execute();
```

## Nested Query

Query nested object types. Required when the field mapping uses `type: nested`.

```php
$results = Stretch::index('posts')
    ->nested('comments', function ($nested) {
        $nested->bool(function ($bool) {
            $bool->must(fn($q) => $q->match('comments.message', 'great'));
            $bool->filter(fn($q) => $q->range('comments.created_at')->gte('2024-01-01'));
        });
    })
    ->execute();
```

The callback receives a fresh `ElasticsearchQueryBuilder` scoped to the nested path. Use the full dotted path for field names inside the callback (e.g., `comments.message`).

## Filter Shortcut

Add filter context clauses without an explicit bool query. Filters do not affect scoring and are cached by Elasticsearch.

```php
$results = Stretch::index('posts')
    ->match('title', 'Laravel')
    ->filter(fn($q) => $q->term('status', 'published'))
    ->filter(fn($q) => $q->range('created_at')->gte('2024-01-01'))
    ->execute();
```

## Post Filter

`postFilter()` narrows the returned hits **after** aggregations have been computed. This is the canonical approach for faceted search: aggregation buckets reflect the full query result set while the visible documents are scoped by the user's facet selections.

Multiple `postFilter()` calls are combined under `bool.filter` (AND semantics).

```php
// Aggregations see all colors and sizes; hits are narrowed to red shoes.
$results = Stretch::index('products')
    ->match('name', 'shoe')
    ->aggregation('colors', fn($agg) => $agg->terms('color.keyword'))
    ->aggregation('sizes', fn($agg) => $agg->terms('size.keyword'))
    ->postFilter(fn($q) => $q->term('color.keyword', 'red'))
    ->execute();
```

`postFilter()` differs from `filter()`:

| Method | Applied | Affects Aggregations |
|--------|---------|----------------------|
| `filter()` | Before aggregations (in query) | Yes — aggs run on the filtered set |
| `postFilter()` | After aggregations | No — aggs run on the unfiltered set |

## Sorting

```php
$results = Stretch::index('posts')
    ->match('title', 'Laravel')
    ->sort('created_at', 'desc')
    ->sort('_score', 'desc')
    ->execute();

// Complex sort with array
$results = Stretch::index('products')
    ->sort(['price' => ['order' => 'asc', 'mode' => 'avg']])
    ->execute();
```

## Pagination

```php
$results = Stretch::index('posts')
    ->match('title', 'Laravel')
    ->size(20)
    ->from(40)  // Skip first 40 results (page 3)
    ->execute();
```

See the [Pagination](pagination.md) documentation for `ElasticPaginator`.

## Source Filtering

Control which fields are returned in each hit:

```php
// Include specific fields
$results = Stretch::index('posts')
    ->match('title', 'Laravel')
    ->source(['title', 'author', 'created_at'])
    ->execute();

// Exclude source entirely (just get IDs and metadata)
$results = Stretch::index('posts')
    ->match('title', 'Laravel')
    ->source(false)
    ->execute();
```

## Highlighting

Return highlighted fragments showing where search terms match:

```php
$results = Stretch::index('posts')
    ->match('content', 'elasticsearch')
    ->highlight([
        'content' => new \stdClass(),
    ], [
        'pre_tags' => ['<strong>'],
        'post_tags' => ['</strong>'],
    ])
    ->execute();

// Per-field options
$results = Stretch::index('posts')
    ->match('content', 'elasticsearch')
    ->highlight([
        'title' => new \stdClass(),
        'content' => ['fragment_size' => 150],
    ])
    ->execute();
```

## Multiple Indices

Query across multiple indices in a single request:

```php
$results = Stretch::index(['posts', 'comments', 'pages'])
    ->match('content', 'Laravel')
    ->execute();
```

## Track Total Hits

By default, Elasticsearch caps total hit counts at 10,000 for performance. Use `trackTotalHits()` to get exact counts:

```php
// Exact total count
$results = Stretch::index('products')
    ->match('name', 'laptop')
    ->trackTotalHits()
    ->execute();

$exactTotal = $results['hits']['total']['value']; // Exact count, not capped at 10,000

// Custom threshold
$results = Stretch::index('products')
    ->match('name', 'laptop')
    ->trackTotalHits(5000)
    ->execute();
```

## Debugging

Inspect the generated Elasticsearch query without executing it:

```php
$query = Stretch::index('posts')
    ->match('title', 'Laravel')
    ->bool(function ($bool) {
        $bool->filter(fn($q) => $q->term('status', 'published'));
    })
    ->toArray();

dd($query);
```

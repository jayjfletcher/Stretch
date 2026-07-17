# Aggregations

Aggregations provide analytics and statistics about your search results. Stretch supports bucket aggregations (grouping), metric aggregations (calculations), and sub-aggregations (nested analytics).

## Adding Aggregations

Aggregations are added to a query builder via the `aggregation()` method:

```php
use JayI\Stretch\Facades\Stretch;

$results = Stretch::index('posts')
    ->match('content', 'elasticsearch')
    ->aggregation('my_agg_name', fn($agg) => $agg->terms('category.keyword'))
    ->execute();

// Access aggregation results
$buckets = $results['aggregations']['my_agg_name']['buckets'];
```

You can add multiple aggregations to the same query:

```php
$results = Stretch::index('orders')
    ->aggregation('by_status', fn($agg) => $agg->terms('status.keyword'))
    ->aggregation('avg_total', fn($agg) => $agg->avg('total'))
    ->aggregation('max_total', fn($agg) => $agg->max('total'))
    ->execute();
```

## Bucket Aggregations

Bucket aggregations group documents into buckets based on field values or ranges.

### Terms

Group by unique field values:

```php
$results = Stretch::index('posts')
    ->aggregation('categories', fn($agg) =>
        $agg->terms('category.keyword')
            ->size(20)
    )
    ->execute();

// Result: buckets with key, doc_count for each unique category
```

### Date Histogram

Group by time intervals:

```php
$results = Stretch::index('orders')
    ->aggregation('monthly_orders', fn($agg) =>
        $agg->dateHistogram('created_at', 'month')
    )
    ->execute();
```

Supported calendar intervals: `minute`, `hour`, `day`, `week`, `month`, `quarter`, `year`.

### Range

Group by custom numeric ranges:

```php
$results = Stretch::index('products')
    ->aggregation('price_ranges', fn($agg) =>
        $agg->range('price', [
            ['to' => 50],
            ['from' => 50, 'to' => 100],
            ['from' => 100, 'to' => 500],
            ['from' => 500],
        ])
    )
    ->execute();
```

### Histogram

Group by fixed-size numeric intervals:

```php
$results = Stretch::index('products')
    ->aggregation('price_histogram', fn($agg) =>
        $agg->histogram('price', 25)  // buckets of $25
    )
    ->execute();
```

## Metric Aggregations

Metric aggregations calculate values across documents.

### Average

```php
->aggregation('avg_price', fn($agg) => $agg->avg('price'))
```

### Sum

```php
->aggregation('total_revenue', fn($agg) => $agg->sum('revenue'))
```

### Min / Max

```php
->aggregation('cheapest', fn($agg) => $agg->min('price'))
->aggregation('most_expensive', fn($agg) => $agg->max('price'))
```

### Count

Count all documents (uses `_id`):

```php
->aggregation('total_docs', fn($agg) => $agg->count())
```

### Cardinality

Count unique values (approximate):

```php
->aggregation('unique_authors', fn($agg) => $agg->cardinality('author_id'))
```

### Stats

Returns count, min, max, avg, and sum in a single aggregation:

```php
->aggregation('price_stats', fn($agg) => $agg->stats('price'))
```

Result:

```json
{
  "price_stats": {
    "count": 100,
    "min": 9.99,
    "max": 1299.99,
    "avg": 249.50,
    "sum": 24950.00
  }
}
```

### Top Hits

Retrieve the top matching documents per bucket:

```php
->aggregation('categories', fn($agg) =>
    $agg->terms('category.keyword')
        ->size(10)
        ->subAggregation('top_documents', fn($sub) => $sub->topHits(3))
)
```

The `topHits()` method accepts a `size` parameter (default: 100) controlling how many documents to return per bucket.

### Percentiles & Extended Stats

```php
// Percentiles (custom percents optional)
->aggregation('load_percentiles', fn($agg) => $agg->percentiles('load_time', [95, 99]))

// Extended stats — adds variance, std deviation and bounds to stats
->aggregation('grade_stats', fn($agg) => $agg->extendedStats('grade'))
```

### Geo Metrics

Over `geo_point` fields:

```php
->aggregation('viewport', fn($agg) => $agg->geoBounds('location'))
->aggregation('center', fn($agg) => $agg->geoCentroid('location'))
```

## Bucket Aggregations (Filter & Nested)

### Filter

Run sub-aggregations only over documents matching a filter. The callback builds
the filter query.

```php
->aggregation('published_stats', fn($agg) =>
    $agg->filter(fn($q) => $q->term('status', 'published'))
        ->subAggregation('avg_price', fn($sub) => $sub->avg('price'))
)
```

### Nested

Aggregate within a `nested` field path.

```php
->aggregation('comment_analytics', fn($agg) =>
    $agg->nested('comments')
        ->subAggregation('avg_rating', fn($sub) => $sub->avg('comments.rating'))
)
```

## Pipeline Aggregations

Pipeline aggregations consume the output of other aggregations. Parent
pipelines (`derivative`, `cumulativeSum`, `movingFn`, `bucketScript`,
`bucketSelector`, `bucketSort`) sit as sub-aggregations of a
histogram/date_histogram; sibling pipelines (`pipelineBucket`) sit beside the
referenced multi-bucket aggregation.

```php
// Monthly sales with a running total and month-over-month change
->aggregation('sales_per_month', fn($agg) =>
    $agg->dateHistogram('date', 'month')
        ->subAggregation('sales', fn($s) => $s->sum('price'))
        ->subAggregation('cumulative', fn($s) => $s->cumulativeSum('sales'))
        ->subAggregation('mom_change', fn($s) => $s->derivative('sales'))
        ->subAggregation('rolling_avg', fn($s) =>
            $s->movingFn('sales', 'MovingFunctions.unweightedAvg(values)', 3))
)
```

`bucketScript` computes a per-bucket metric from siblings; `bucketSelector`
filters buckets; `bucketSort` orders/paginates them:

```php
->aggregation('per_category', fn($agg) =>
    $agg->terms('category.keyword')
        ->subAggregation('total_sales', fn($s) => $s->sum('price'))
        ->subAggregation('unit_price', fn($s) =>
            $s->bucketScript(['sales' => 'total_sales', 'count' => '_count'], 'params.sales / params.count'))
        ->subAggregation('big_only', fn($s) =>
            $s->bucketSelector(['t' => 'total_sales'], 'params.t > 1000'))
        ->subAggregation('top_sellers', fn($s) =>
            $s->bucketSort([['total_sales' => ['order' => 'desc']]], ['size' => 5]))
)
```

Sibling pipelines reference a metric across the buckets of another aggregation
via `pipelineBucket($type, $path)`, where `$type` is one of `avg_bucket`,
`sum_bucket`, `min_bucket`, `max_bucket`, or `stats_bucket`:

```php
->aggregation('sales_per_month', fn($agg) =>
    $agg->dateHistogram('date', 'month')
        ->subAggregation('sales', fn($s) => $s->sum('price'))
)
->aggregation('avg_monthly_sales', fn($agg) =>
    $agg->pipelineBucket('avg_bucket', 'sales_per_month>sales')
)
```

## Sub-Aggregations

Nest aggregations inside bucket aggregations for multi-level analytics:

```php
$results = Stretch::index('orders')
    ->aggregation('monthly_stats', fn($agg) =>
        $agg->dateHistogram('created_at', 'month')
            ->subAggregation('avg_total', fn($sub) => $sub->avg('total'))
            ->subAggregation('max_total', fn($sub) => $sub->max('total'))
            ->subAggregation('order_count', fn($sub) => $sub->count())
    )
    ->execute();

// Access nested results
foreach ($results['aggregations']['monthly_stats']['buckets'] as $bucket) {
    $month = $bucket['key_as_string'];
    $avgTotal = $bucket['avg_total']['value'];
    $maxTotal = $bucket['max_total']['value'];
    $count = $bucket['order_count']['value'];
}
```

Sub-aggregations can be nested further:

```php
$results = Stretch::index('orders')
    ->aggregation('by_category', fn($agg) =>
        $agg->terms('category.keyword')
            ->subAggregation('by_month', fn($sub) =>
                $sub->dateHistogram('created_at', 'month')
                    ->subAggregation('revenue', fn($inner) => $inner->sum('total'))
            )
    )
    ->execute();
```

## Bucket Size

Control the maximum number of buckets returned by a terms aggregation:

```php
->aggregation('top_tags', fn($agg) =>
    $agg->terms('tags.keyword')->size(50)
)
```

If `size()` is not called, the default from `config('stretch.aggregations.default_size')` is used (default: 10). The size is capped at `config('stretch.aggregations.max_buckets')` (default: 10000).

## Ordering

Order terms aggregation buckets:

```php
// Order by document count descending (default)
->aggregation('categories', fn($agg) =>
    $agg->terms('category.keyword')->orderBy('_count', 'desc')
)

// Order alphabetically by key
->aggregation('categories', fn($agg) =>
    $agg->terms('category.keyword')->orderBy('_key', 'asc')
)

// Order by a sub-aggregation value
->aggregation('categories', fn($agg) =>
    $agg->terms('category.keyword')
        ->orderBy('avg_price', 'desc')
        ->subAggregation('avg_price', fn($sub) => $sub->avg('price'))
)
```

## Full Example

```php
$results = Stretch::index('orders')
    ->bool(function ($bool) {
        $bool->filter(fn($q) => $q->range('created_at')->gte('2024-01-01'));
        $bool->filter(fn($q) => $q->term('status', 'completed'));
    })
    ->aggregation('by_category', fn($agg) =>
        $agg->terms('category.keyword')
            ->size(20)
            ->orderBy('total_revenue', 'desc')
            ->subAggregation('total_revenue', fn($sub) => $sub->sum('total'))
            ->subAggregation('avg_order', fn($sub) => $sub->avg('total'))
            ->subAggregation('unique_customers', fn($sub) => $sub->cardinality('customer_id'))
    )
    ->aggregation('monthly_trend', fn($agg) =>
        $agg->dateHistogram('created_at', 'month')
            ->subAggregation('revenue', fn($sub) => $sub->sum('total'))
    )
    ->size(0) // No hits needed, just aggregations
    ->execute();
```

## Raw Aggregations

For aggregation structures not covered by the `AggregationBuilder` — such as filtered aggregations, deeply nested aggregations, or any custom structure — use the `raw()` escape hatch.

### Using `raw()` on the AggregationBuilder

Inside an `aggregation()` callback, call `raw()` with the full Elasticsearch aggregation array:

```php
$results = Stretch::index('products')
    ->aggregation('filtered_brand', fn($agg) => $agg->raw([
        'filter' => ['bool' => ['filter' => [['term' => ['category' => 'Electronics']]]]],
        'aggs' => [
            'brand' => ['terms' => ['field' => 'brand', 'size' => 100]],
        ],
    ]))
    ->execute();
```

### Using `rawAggregation()` on the Query Builder

Add raw aggregations directly to the query without going through the `AggregationBuilder`:

```php
$results = Stretch::index('products')
    ->match('name', 'laptop')
    // Stats aggregation
    ->rawAggregation('price_stats', ['stats' => ['field' => 'price']])
    // Deeply nested aggregation
    ->rawAggregation('attribute_facets', [
        'nested' => ['path' => 'attributes'],
        'aggs' => [
            'keys' => [
                'terms' => ['field' => 'attributes.key', 'size' => 20],
                'aggs' => [
                    'values' => [
                        'terms' => ['field' => 'attributes.value', 'size' => 30],
                    ],
                ],
            ],
        ],
    ])
    ->execute();
```

You can freely mix `aggregation()` and `rawAggregation()` on the same query:

```php
$results = Stretch::index('products')
    ->aggregation('categories', fn($agg) => $agg->terms('category')->size(20))
    ->aggregation('price_stats', fn($agg) => $agg->stats('price'))
    ->rawAggregation('filtered_brand', [
        'filter' => ['bool' => ['filter' => [['term' => ['category' => 'Electronics']]]]],
        'aggs' => ['brand' => ['terms' => ['field' => 'brand', 'size' => 100]]],
    ])
    ->execute();
```

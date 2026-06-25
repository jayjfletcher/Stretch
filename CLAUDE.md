# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build & Test Commands

```bash
composer test              # Run all tests with Pest
composer test -- --filter="test name"  # Run a single test
composer analyse           # Run PHPStan static analysis
composer format            # Format code with Pint
```

## Architecture

Stretch is a Laravel package providing a fluent query builder for Elasticsearch. The package follows Laravel conventions with service provider registration and facade access.

### Core Components

- **`Stretch`** (`src/Stretch.php`) - Main entry point, provides index management, document operations, pipeline/inference/ML management. Accessed via `Stretch` facade.
- **`ElasticsearchQueryBuilder`** (`src/Builders/ElasticsearchQueryBuilder.php`) - Fluent query builder implementing `QueryBuilderContract`. Created via `Stretch::index()` or `Stretch::query()`.
- **`MultiQueryBuilder`** (`src/Builders/MultiQueryBuilder.php`) - Executes multiple named queries in a single request via `Stretch::multi()`. Queries are added with `->add('name', callback)` and executed together.
- **`BoolQueryBuilder`** (`src/Builders/BoolQueryBuilder.php`) - Handles bool queries with must/should/filter/mustNot clauses and boost.
- **`AggregationBuilder`** (`src/Builders/AggregationBuilder.php`) - Builds aggregations (terms, date histogram, range, stats, metrics) with sub-aggregation support and a `raw()` escape hatch.
- **`RangeQueryBuilder`** (`src/Builders/RangeQueryBuilder.php`) - Chainable range query methods (gt/gte/lt/lte).
- **`RetrieverBuilder`** (`src/Builders/RetrieverBuilder.php`) - Composes hybrid search pipelines (standard + kNN + RRF) for ES 8.14+.
- **`ElasticPaginator`** (`src/Pagination/ElasticPaginator.php`) - Extends Laravel's `LengthAwarePaginator` for Elasticsearch results. Use `ElasticPaginator::fromResponse()` to create from query results.

### Service Registration

`StretchServiceProvider` registers:
- `elasticsearch.manager` - Multi-connection manager singleton
- `ClientContract` - Wraps native Elasticsearch client
- `stretch` - Main Stretch singleton with client and manager

### Query Builder Pattern

The query builder uses a builder pattern with internal arrays (`$query`, `$aggregations`, `$sort`, etc.) that are assembled in `build()` and sent via `execute()`. Multiple queries added without explicit bool wrapping are auto-wrapped in `bool.must`.

### Query Types

- `match()` - Full-text search on a single field
- `matchPhrase()` - Exact phrase matching
- `multiMatch()` - Full-text search across multiple fields with boosts, fuzziness, type options
- `semantic()` - Semantic/vector search on embedding fields
- `term()` / `terms()` - Exact value matching
- `range()` - Numeric/date range queries (returns chainable RangeQueryBuilder)
- `bool()` - Composite bool queries with must/should/filter/mustNot/boost
- `nested()` - Queries on nested object types
- `wildcard()` / `fuzzy()` / `exists()` - Pattern, approximate, and existence queries
- `knn()` - k-nearest-neighbor vector search (supports both literal vectors and `query_vector_builder` for server-side embeddings)
- `retriever()` - Modern ES 8.14+ retriever API (standard, kNN, RRF)
- `filter()` - Filter context (no scoring, cached)
- `postFilter()` - Applied after aggregations; narrows hits without affecting aggregation buckets (faceted search)
- `delete()` - Executes a `_delete_by_query` using the built query instead of searching; returns ES delete response

### kNN with Server-Side Embeddings

Pass `null` for the vector parameter and provide `query_vector_builder` in options to let Elasticsearch generate embeddings:

```php
->knn('embedding', null, k: 10, options: [
    'query_vector_builder' => [
        'text_embedding' => [
            'model_id' => 'my-embeddings',
            'model_text' => 'search query',
        ],
    ],
])
```

### Aggregations

- Builder methods: `terms()`, `dateHistogram()`, `range()`, `histogram()`, `avg()`, `sum()`, `min()`, `max()`, `count()`, `cardinality()`, `topHits()`, `stats()`
- `raw()` - Escape hatch for any aggregation structure not covered by the builder (filtered aggs, nested aggs, etc.)
- `rawAggregation()` on the query builder - Adds a raw aggregation directly without going through the AggregationBuilder

### Bool Query Boost

The `BoolQueryBuilder` supports a `boost()` method to set a boost factor on the entire bool clause:

```php
->bool(function ($bool) {
    $bool->should(fn ($q) => $q->multiMatch('query', ['title', 'description']))
        ->minimumShouldMatch(1)
        ->boost(0.7);
})
```

### Post Filter

Use `postFilter()` when you want to narrow the returned hits without affecting aggregation buckets (faceted search). Aggregations run against the full query result set; hits are filtered after. Multiple calls are combined under `bool.filter`:

```php
Stretch::index('products')
    ->match('name', 'shoe')
    ->aggregation('colors', fn ($agg) => $agg->terms('color.keyword'))
    ->postFilter(fn ($q) => $q->term('color.keyword', 'red'))
    ->execute();
```

### Delete By Query

Use `delete()` on the query builder to delete all documents matching the built query. Accepts same query clauses as a search. No query = `match_all`.

```php
// via builder (chainable)
Stretch::index('posts')
    ->term('status', 'draft')
    ->delete();

// with range
Stretch::index('logs')
    ->range('created_at')->lt('2024-01-01')
    ->delete();

// via Stretch facade (callback style)
Stretch::deleteByQuery('posts', fn ($q) => $q->term('status', 'draft'));
```

Response contains `deleted`, `total`, `failures`, etc. (standard ES `_delete_by_query` shape).

### Track Total Hits

Use `trackTotalHits()` to get accurate total hit counts beyond the default 10,000 cap:

```php
->trackTotalHits()      // true for exact count
->trackTotalHits(5000)  // integer threshold
```

### Ingest Pipelines

CRUD operations for Elasticsearch ingest pipelines:

```php
Stretch::putPipeline('my-pipeline', ['description' => '...', 'processors' => [...]]);
Stretch::getPipeline('my-pipeline');
Stretch::deletePipeline('my-pipeline');
```

### Inference Endpoints

CRUD operations for Elasticsearch inference endpoints (ML model deployment):

```php
Stretch::putInferenceEndpoint('my-embeddings', 'text_embedding', [
    'service' => 'elasticsearch',
    'service_settings' => ['model_id' => '.multilingual-e5-small'],
]);
Stretch::getInferenceEndpoint('my-embeddings');
Stretch::deleteInferenceEndpoint('my-embeddings');
```

### ML / Trained Models

Get deployment stats for trained ML models:

```php
Stretch::getTrainedModelStats('.multilingual-e5-small');
```

### Multi-Connection Support

Multiple Elasticsearch connections can be configured in `config/stretch.php` under `connections`. Switch connections via `Stretch::connection('name')` or `$queryBuilder->connection('name')`.

### Caching

Query result caching is provided via the `IsCacheable` trait (`src/Builders/Concerns/IsCacheable.php`), used by both `ElasticsearchQueryBuilder` and `MultiQueryBuilder`.

- Enable caching: `->cache()` or `->setCacheEnabled(true)`
- Set TTL (stale-while-revalidate): `->setCacheTtl([300, 600])` (fresh for 5 min, stale for 10 min)
- Custom cache store: `->setCacheStore('redis')`
- Custom prefix: `->setCachePrefix('myapp:')`
- Clear cache before execution: `->clearCache()`

Configuration defaults are in `config/stretch.php` under the `cache` key.

### Configuration

The `config/stretch.php` file includes:
- `connections` - Multi-connection Elasticsearch settings
- `query` - Default size, max size, timeout
- `aggregations` - Max buckets, default size
- `logging` - Query logging, slow query threshold
- `cache` - TTL, prefix, store settings

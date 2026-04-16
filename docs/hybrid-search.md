# Hybrid Search

Stretch supports hybrid search — combining traditional lexical queries (BM25) with dense vector similarity (kNN) — through two complementary APIs:

1. **Top-level `knn()`** — combine a kNN clause with any query; Elasticsearch linearly combines the scores. Available from Elasticsearch 8.4+.
2. **`retriever()` with RRF** — compose retrievers and merge their results with Reciprocal Rank Fusion, which is score-scale independent. Requires Elasticsearch 8.14+ (retrievers) / 8.15+ (stable RRF).

Both approaches operate on `dense_vector` fields. You can either pass a pre-computed query vector or use `query_vector_builder` for server-side inference.

## Top-Level kNN

### Pure kNN Search

```php
use JayI\Stretch\Facades\Stretch;

$results = Stretch::index('posts')
    ->knn('title_vector', $queryVector, k: 10)
    ->execute();
```

Defaults: `num_candidates` is set to `max(k * 10, 100)` if not provided.

### kNN with Custom Parameters

```php
Stretch::index('posts')
    ->knn(
        field: 'title_vector',
        queryVector: $queryVector,
        k: 10,
        numCandidates: 200,
        options: [
            'boost' => 0.5,
            'similarity' => 0.8,
        ],
    )
    ->execute();
```

### Pre-Filtered kNN

Restrict the kNN search to a subset of documents by passing a `filter` in `options`:

```php
Stretch::index('posts')
    ->knn('title_vector', $queryVector, k: 10, options: [
        'filter' => ['term' => ['status' => 'published']],
    ])
    ->execute();
```

### Hybrid: kNN + Keyword Match

Call `knn()` alongside any query clause. Elasticsearch linearly combines the scores (BM25 + cosine similarity):

```php
Stretch::index('posts')
    ->match('title', 'Laravel')
    ->knn('title_vector', $queryVector, k: 10, options: ['boost' => 0.5])
    ->execute();
```

Use the `boost` option on either side to tune the relative weight of lexical vs semantic scores.

### Multi-Vector kNN

Search multiple vector fields in the same request:

```php
Stretch::index('posts')
    ->knn('title_vector', $titleVector, k: 5)
    ->knn('body_vector', $bodyVector, k: 5)
    ->execute();
```

### Server-Side Query Vector Inference

If your Elasticsearch cluster has an inference endpoint deployed, pass `null` for the query vector and provide a `query_vector_builder` in options. Elasticsearch will generate the embedding server-side — PHP never touches a vector:

```php
Stretch::index('posts')
    ->knn('title_vector', null, k: 10, options: [
        'query_vector_builder' => [
            'text_embedding' => [
                'model_id' => 'sentence-transformers__all-minilm-l6-v2',
                'model_text' => 'Laravel framework',
            ],
        ],
    ])
    ->execute();
```

This also works in hybrid search:

```php
Stretch::index('products')
    ->bool(function ($bool) {
        $bool->should(fn($q) => $q->multiMatch('laptop', ['name^3', 'description']));
        $bool->minimumShouldMatch(1);
        $bool->boost(0.7);
    })
    ->knn('embedding', null, k: 48, numCandidates: 150, options: [
        'query_vector_builder' => [
            'text_embedding' => [
                'model_id' => 'product-embeddings',
                'model_text' => 'laptop',
            ],
        ],
        'boost' => 0.3,
    ])
    ->trackTotalHits()
    ->execute();
```

## Retriever API (RRF)

Retrievers are the modern Elasticsearch API for composing hybrid search pipelines. **Reciprocal Rank Fusion (RRF)** merges multiple retrievers by rank position rather than raw score, avoiding the score-scale problem that plagues linear combinations.

When a retriever is set, it replaces the top-level `query` and `knn` clauses.

### RRF: Keyword + Semantic

```php
use JayI\Stretch\Facades\Stretch;

$results = Stretch::index('posts')
    ->retriever(function ($r) use ($queryVector) {
        $r->rrf([
            $r->standard(fn ($q) => $q->match('title', 'Laravel')),
            $r->knn('title_vector', $queryVector, k: 10, numCandidates: 100),
        ], rankWindowSize: 50, rankConstant: 20);
    })
    ->execute();
```

RRF parameters:

- `rankWindowSize` — how many documents to pull from each sub-retriever before fusing (default: 100)
- `rankConstant` — the `k` constant in the RRF formula `1 / (k + rank)` (default: 60)

### Standard Retriever

Wraps any query DSL clause:

```php
Stretch::index('posts')
    ->retriever(function ($r) {
        $r->standard(fn ($q) => $q
            ->bool(function ($bool) {
                $bool->must(fn ($q) => $q->match('title', 'Laravel'));
                $bool->filter(fn ($q) => $q->term('status', 'published'));
            })
        );
    })
    ->execute();
```

### kNN Retriever

A kNN retriever is functionally equivalent to a top-level `knn()` clause, but composable with `rrf()`:

```php
Stretch::index('posts')
    ->retriever(function ($r) use ($queryVector) {
        $r->knn('title_vector', $queryVector, k: 10, numCandidates: 100);
    })
    ->execute();
```

### Combining More Than Two Retrievers

`rrf()` accepts any number of sub-retrievers:

```php
Stretch::index('posts')
    ->retriever(function ($r) use ($titleVector, $bodyVector) {
        $r->rrf([
            $r->standard(fn ($q) => $q->match('title', 'Laravel')),
            $r->knn('title_vector', $titleVector, k: 10),
            $r->knn('body_vector', $bodyVector, k: 10),
        ]);
    })
    ->execute();
```

### Retrievers with Sort, Pagination, and Aggregations

`size()`, `from()`, `sort()`, `source()`, `highlight()`, and `aggregation()` all work alongside retrievers:

```php
Stretch::index('posts')
    ->retriever(function ($r) use ($queryVector) {
        $r->rrf([
            $r->standard(fn ($q) => $q->match('title', 'Laravel')),
            $r->knn('title_vector', $queryVector, k: 10),
        ]);
    })
    ->size(20)
    ->from(0)
    ->source(['title', 'author'])
    ->aggregation('by_category', fn ($agg) => $agg->terms('category.keyword'))
    ->execute();
```

## When to Use Which

| Scenario | Recommended approach |
|----------|---------------------|
| Simple keyword + vector blend, known score scales | Top-level `knn()` + `match()` with `boost` tuning |
| Merging heterogeneous retrievers (BM25 + multiple vectors) | `retriever()` + `rrf()` |
| You need to tune how much each side contributes | `knn()` + `boost`, or `rrf()` with different `rank_window_size` |
| Elasticsearch < 8.14 | Top-level `knn()` only (retrievers unavailable) |

## Index Mapping Reminder

For any vector search your index must have a `dense_vector` field:

```php
Stretch::createIndex('posts', [
    'mappings' => [
        'properties' => [
            'title' => ['type' => 'text'],
            'title_vector' => [
                'type' => 'dense_vector',
                'dims' => 384,
                'index' => true,
                'similarity' => 'cosine',
            ],
        ],
    ],
]);
```

<?php

declare(strict_types=1);

namespace JayI\Stretch\Builders;

use JayI\Stretch\Builders\Concerns\IsCacheable;
use JayI\Stretch\Client\ElasticsearchClient;
use JayI\Stretch\Contracts\BoolQueryBuilderContract;
use JayI\Stretch\Contracts\ClientContract;
use JayI\Stretch\Contracts\QueryBuilderContract;
use JayI\Stretch\Contracts\RangeQueryBuilderContract;
use JayI\Stretch\ElasticsearchManager;
use JayI\Stretch\Exceptions\StretchException;

/**
 * ElasticsearchQueryBuilder provides a fluent interface for building Elasticsearch queries.
 *
 * This class implements the QueryBuilderContract and provides methods for building
 * complex Elasticsearch queries with support for multiple query types, aggregations,
 * sorting, pagination, and multi-connection support.
 *
 * @phpstan-consistent-constructor
 */
class ElasticsearchQueryBuilder implements QueryBuilderContract
{
    use IsCacheable;

    /**
     * Query clauses to be combined in the final query.
     *
     * @var array<int, array>
     */
    protected array $query = [];

    /**
     * Named aggregations to include in the query.
     *
     * @var array<string, array>
     */
    protected array $aggregations = [];

    /**
     * Sort clauses for result ordering.
     *
     * @var array<int, array>
     */
    protected array $sort = [];

    /**
     * Source filtering configuration (_source field).
     *
     * Can be:
     * - array: List of fields to include/exclude
     * - string: Single field to include
     * - bool: false to exclude all source fields
     * - null: Include all source fields (default)
     */
    protected array|string|bool|null $source = null;

    /**
     * Highlighting configuration for search results.
     */
    protected array $highlight = [];

    /**
     * Index or indices to search.
     */
    protected string|array|null $index = null;

    /**
     * Maximum number of results to return.
     */
    protected ?int $size = null;

    /**
     * Offset for pagination (number of results to skip).
     */
    protected ?int $from = null;

    /**
     * Filter context clauses (no scoring, cached).
     *
     * @var array<int, array>
     */
    protected array $filters = [];

    /**
     * Post-filter clauses applied after aggregations.
     *
     * @var array<int, array>
     */
    protected array $postFilters = [];

    /**
     * Whether query caching is enabled.
     */
    protected bool $cache = false;

    /**
     * Top-level kNN search clauses for vector similarity search.
     *
     * @var array<int, array>
     */
    protected array $knn = [];

    /**
     * Top-level retriever clause (e.g. rrf) for hybrid search.
     *
     * When set, the retriever replaces the `query` and `knn` clauses in the
     * request body as per the Elasticsearch retriever API.
     */
    protected ?array $retriever = null;

    /**
     * Whether to track total hits accurately.
     */
    protected bool|int|null $trackTotalHits = null;

    /**
     * Create a new ElasticsearchQueryBuilder instance.
     *
     * @param  ClientContract|null  $client  The Elasticsearch client for query execution
     * @param  ElasticsearchManager|null  $manager  The connection manager for multi-connection support
     */
    public function __construct(
        protected ?ClientContract $client = null,
        protected ?ElasticsearchManager $manager = null
    ) {}

    /**
     * Set the index or indices to search.
     *
     * @param  string|array  $index  Single index name or array of index names
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * // Single index
     * $builder->index('posts');
     *
     * // Multiple indices
     * $builder->index(['posts', 'comments', 'users']);
     * ```
     */
    public function index(string|array $index): static
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Switch to a specific Elasticsearch connection.
     *
     * Creates a new query builder instance using the specified connection name.
     * This allows building queries against different Elasticsearch clusters or configurations.
     *
     * @param  string  $name  The connection name as defined in configuration
     * @return static A new query builder instance using the specified connection
     *
     * @throws \RuntimeException If the manager is not available
     *
     * @example
     * ```php
     * Stretch::query()
     *     ->connection('logs')
     *     ->match('level', 'error')
     *     ->execute();
     * ```
     */
    public function connection(string $name): static
    {
        if (! $this->manager) {
            throw new \RuntimeException('Elasticsearch manager not available. Cannot switch connections.');
        }

        $client = new ElasticsearchClient($this->manager->connection($name));

        return new static($client, $this->manager);
    }

    /**
     * Add a match query for full-text search.
     *
     * Analyzes the input text and constructs a query from the terms.
     * Best for searching analyzed text fields like descriptions or content.
     *
     * @param  string  $field  The field to search
     * @param  mixed  $value  The search text
     * @param  array  $options  Additional options (fuzziness, operator, etc.)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * // Simple match
     * $builder->match('title', 'Laravel Elasticsearch');
     *
     * // With options
     * $builder->match('title', 'Laravel', ['fuzziness' => 'AUTO', 'operator' => 'and']);
     * ```
     */
    public function match(string $field, mixed $value, array $options = []): static
    {
        $match = array_merge(['query' => $value], $options);

        $this->addQueryProtected([
            'match' => [
                $field => $match,
            ],
        ]);

        return $this;
    }

    /**
     * Add a match phrase query for exact phrase matching.
     *
     * Matches documents containing the exact phrase in order.
     * Useful for searching for specific phrases or sentences.
     *
     * @param  string  $field  The field to search
     * @param  mixed  $value  The exact phrase to match
     * @param  array  $options  Additional options (slop for word distance, etc.)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->matchPhrase('content', 'quick brown fox');
     * ```
     */
    public function matchPhrase(string $field, mixed $value, array $options = []): static
    {
        $match = array_merge(['query' => $value], $options);

        $this->addQueryProtected([
            'match_phrase' => [
                $field => $match,
            ],
        ]);

        return $this;
    }

    /**
     * Add a multi_match query for full-text search across multiple fields.
     *
     * @param  string  $query  The search text
     * @param  array  $fields  Fields to search with optional boosts (e.g. ['title^3', 'description'])
     * @param  array  $options  Additional options (type, fuzziness, minimum_should_match, prefix_length, etc.)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->multiMatch('laptop for work', ['name^3', 'description', 'brand^2'], [
     *     'type' => 'best_fields',
     *     'fuzziness' => 'AUTO',
     *     'minimum_should_match' => '75%',
     * ]);
     * ```
     */
    public function multiMatch(string $query, array $fields, array $options = []): static
    {
        $multiMatch = array_merge([
            'query' => $query,
            'fields' => $fields,
        ], $options);

        $this->addQueryProtected([
            'multi_match' => $multiMatch,
        ]);

        return $this;
    }

    /**
     * Add a semantic query for semantic search using embeddings.
     *
     * Performs semantic search using vector embeddings to find documents
     * with similar meaning rather than exact keyword matches. Requires
     * Elasticsearch with semantic search capabilities and properly indexed
     * embedding fields.
     *
     * @param  string  $field  The field containing semantic embeddings
     * @param  mixed  $query  The semantic search query text
     * @param  array  $options  Additional options (boost, etc.)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * // Simple semantic search
     * $builder->semantic('semantic_contents', 'Testing something');
     *
     * // With boost option
     * $builder->semantic('semantic_contents', 'testing something', ['boost' => 2.0]);
     * ```
     */
    public function semantic(string $field, mixed $query, array $options = []): static
    {
        $semantic = array_merge(['field' => $field, 'query' => $query], $options);

        $this->addQueryProtected([
            'semantic' => $semantic,
        ]);

        return $this;
    }

    /**
     * Add a term query for exact value matching.
     *
     * Finds documents with the exact term in the specified field.
     * Use for keyword fields, IDs, or exact matches (not analyzed text).
     *
     * @param  string  $field  The field to search (use .keyword for text fields)
     * @param  mixed  $value  The exact value to match
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->term('status', 'published');
     * $builder->term('category.keyword', 'Technology');
     * ```
     */
    public function term(string $field, mixed $value): static
    {
        $this->addQueryProtected([
            'term' => [
                $field => $value,
            ],
        ]);

        return $this;
    }

    /**
     * Add a terms query for matching any of multiple values.
     *
     * Finds documents where the field matches any of the specified values.
     * Equivalent to multiple term queries combined with OR.
     *
     * @param  string  $field  The field to search
     * @param  array  $values  Array of values to match against
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->terms('status', ['published', 'draft']);
     * $builder->terms('tags.keyword', ['php', 'laravel', 'elasticsearch']);
     * ```
     */
    public function terms(string $field, array $values): static
    {
        $this->addQueryProtected([
            'terms' => [
                $field => $values,
            ],
        ]);

        return $this;
    }

    /**
     * Start building a range query for numeric or date fields.
     *
     * Returns a RangeQueryBuilder for chaining range conditions.
     *
     * @param  string  $field  The field to apply the range query to
     * @return RangeQueryBuilderContract The range query builder for chaining
     *
     * @example
     * ```php
     * $builder->range('price')->gte(100)->lt(500);
     * $builder->range('created_at')->gte('2024-01-01')->lte('now');
     * ```
     */
    public function range(string $field): RangeQueryBuilderContract
    {
        return new RangeQueryBuilder($this, $field);
    }

    /**
     * Create a bool query with must/should/filter/mustNot clauses.
     *
     * Bool queries combine multiple query clauses. If a callback is provided,
     * the query is built immediately. Otherwise, returns the builder for chaining.
     *
     * @param  callable|null  $callback  Optional callback receiving the BoolQueryBuilder
     * @return BoolQueryBuilderContract The bool query builder
     *
     * @example
     * ```php
     * $builder->bool(function ($bool) {
     *     $bool->must(fn($q) => $q->match('title', 'Laravel'));
     *     $bool->filter(fn($q) => $q->term('status', 'published'));
     *     $bool->should(fn($q) => $q->term('featured', true));
     * });
     * ```
     */
    public function bool(?callable $callback = null): BoolQueryBuilderContract
    {
        $boolBuilder = new BoolQueryBuilder($this);

        if ($callback) {
            $callback($boolBuilder);
            $this->addQueryProtected($boolBuilder->build());
        }

        return $boolBuilder;
    }

    /**
     * Add a nested query for searching nested objects.
     *
     * Required when querying fields of nested object type.
     * The callback receives a fresh query builder for the nested context.
     *
     * @param  string  $path  The path to the nested object field
     * @param  callable  $callback  Callback receiving a query builder for the nested query
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->nested('comments', function ($q) {
     *     $q->match('comments.content', 'great post');
     * });
     * ```
     */
    public function nested(string $path, callable $callback): static
    {
        $nestedQuery = new ElasticsearchQueryBuilder($this->client, $this->manager);
        $callback($nestedQuery);

        $this->addQueryProtected([
            'nested' => [
                'path' => $path,
                'query' => $nestedQuery->build()['query'],
            ],
        ]);

        return $this;
    }

    /**
     * Add a wildcard query for pattern matching.
     *
     * Supports * (matches any characters) and ? (matches single character).
     * Note: Wildcard queries can be slow on large datasets.
     *
     * @param  string  $field  The field to search
     * @param  string  $value  The wildcard pattern
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     *
     * $builder->wildcard('email', '*@example.com');
     * $builder->wildcard('code', 'ABC-???-*');
     * ```
     */
    public function wildcard(string $field, string $value): static
    {
        $this->addQueryProtected([
            'wildcard' => [
                $field => $value,
            ],
        ]);

        return $this;
    }

    /**
     * Add a fuzzy query for approximate string matching.
     *
     * Finds documents with terms similar to the search term,
     * allowing for typos and misspellings.
     *
     * @param  string  $field  The field to search
     * @param  mixed  $value  The search term
     * @param  array  $options  Options like fuzziness, prefix_length, max_expansions
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->fuzzy('name', 'elasticsearch');
     * $builder->fuzzy('name', 'elasticsearch', ['fuzziness' => 2]);
     * ```
     */
    public function fuzzy(string $field, mixed $value, array $options = []): static
    {
        $fuzzy = array_merge(['value' => $value], $options);

        $this->addQueryProtected([
            'fuzzy' => [
                $field => $fuzzy,
            ],
        ]);

        return $this;
    }

    /**
     * Add a top-level kNN search clause for vector similarity search.
     *
     * Performs approximate k-nearest-neighbor search on a `dense_vector` field.
     * Supports pre-filtering, boosting, and tuning of HNSW parameters. Can be
     * combined with a standard query clause to produce hybrid search results —
     * Elasticsearch will linearly combine the query score and the kNN score.
     *
     * Call multiple times to run multiple kNN searches in the same request.
     *
     * @param  string  $field  The dense_vector field to search
     * @param  array  $queryVector  The query vector (numeric array)
     * @param  int  $k  The number of nearest neighbours to return
     * @param  int|null  $numCandidates  Candidates considered per shard (defaults to max(k*10, 100))
     * @param  array  $options  Extra kNN options (boost, filter, similarity, query_vector_builder, etc.)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * // Pure kNN search
     * Stretch::index('posts')
     *     ->knn('title_vector', [0.12, -0.98, ...], k: 10)
     *     ->execute();
     *
     * // Hybrid: combine kNN with a keyword match
     * Stretch::index('posts')
     *     ->match('title', 'Laravel')
     *     ->knn('title_vector', $vector, k: 10, numCandidates: 100, options: ['boost' => 0.5])
     *     ->execute();
     *
     * // kNN with a pre-filter
     * Stretch::index('posts')
     *     ->knn('title_vector', $vector, k: 10, options: [
     *         'filter' => ['term' => ['status' => 'published']],
     *     ])
     *     ->execute();
     *
     * // kNN with server-side embedding via query_vector_builder
     * Stretch::index('posts')
     *     ->knn('embedding', null, k: 10, options: [
     *         'query_vector_builder' => [
     *             'text_embedding' => [
     *                 'model_id' => 'my-embeddings',
     *                 'model_text' => 'search query',
     *             ],
     *         ],
     *     ])
     *     ->execute();
     * ```
     */
    public function knn(string $field, ?array $queryVector, int $k = 10, ?int $numCandidates = null, array $options = []): static
    {
        $knn = [
            'field' => $field,
            'k' => $k,
            'num_candidates' => $numCandidates ?? max($k * 10, 100),
        ];

        // Only include query_vector when not using query_vector_builder
        if ($queryVector !== null && ! isset($options['query_vector_builder'])) {
            $knn['query_vector'] = $queryVector;
        }

        $this->knn[] = array_merge($knn, $options);

        return $this;
    }

    /**
     * Set the top-level retriever clause for hybrid search.
     *
     * Retrievers are the modern Elasticsearch API for composing hybrid search
     * pipelines (standard + kNN + rrf). When a retriever is set, it replaces the
     * `query` and `knn` clauses in the outgoing request body.
     *
     * The callback receives a `RetrieverBuilder` which exposes `standard()`,
     * `knn()`, and `rrf()` helpers for composing retrievers.
     *
     * Requires Elasticsearch 8.14+ (retrievers) / 8.15+ for stable RRF.
     *
     * @param  callable  $callback  Callback receiving a RetrieverBuilder
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * // Reciprocal Rank Fusion of a keyword match and a kNN search
     * Stretch::index('posts')
     *     ->retriever(function ($r) use ($vector) {
     *         $r->rrf([
     *             $r->standard(fn ($q) => $q->match('title', 'Laravel')),
     *             $r->knn('title_vector', $vector, k: 10, numCandidates: 100),
     *         ], rankWindowSize: 50, rankConstant: 20);
     *     })
     *     ->execute();
     * ```
     */
    public function retriever(callable $callback): static
    {
        $retrieverBuilder = new RetrieverBuilder;
        $callback($retrieverBuilder);
        $this->retriever = $retrieverBuilder->build();

        return $this;
    }

    /**
     * Add an exists query to find documents with a field value.
     *
     * Matches documents where the specified field has a non-null value.
     *
     * @param  string  $field  The field that must exist
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->exists('email');
     * ```
     */
    public function exists(string $field): static
    {
        $this->addQueryProtected([
            'exists' => [
                'field' => $field,
            ],
        ]);

        return $this;
    }

    /**
     * Set the maximum number of results to return.
     *
     * @param  int  $size  Maximum number of hits to return
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->size(50)->execute();
     * ```
     */
    public function size(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    /**
     * Set the offset for pagination.
     *
     * Combined with size() for paginating through results.
     *
     * @param  int  $from  Number of results to skip
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * // Page 2 with 20 results per page
     * $builder->from(20)->size(20)->execute();
     * ```
     */
    public function from(int $from): static
    {
        $this->from = $from;

        return $this;
    }

    /**
     * Add a sort clause to order results.
     *
     * Can be called multiple times to add multiple sort criteria.
     *
     * @param  string|array  $field  Field name or full sort configuration array
     * @param  string  $direction  Sort direction: 'asc' or 'desc' (ignored if $field is array)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * // Simple sort
     * $builder->sort('created_at', 'desc');
     *
     * // Multiple sorts
     * $builder->sort('featured', 'desc')->sort('created_at', 'desc');
     *
     * // Complex sort with array
     * $builder->sort(['price' => ['order' => 'asc', 'mode' => 'avg']]);
     * ```
     */
    public function sort(string|array $field, string $direction = 'asc'): static
    {
        if (is_string($field)) {
            $this->sort[] = [$field => ['order' => $direction]];
        } else {
            $this->sort[] = $field;
        }

        return $this;
    }

    /**
     * Configure source field filtering in results.
     *
     * Controls which fields are returned in the _source of each hit.
     *
     * @param  array|string|bool  $source  Fields to include, or false to exclude all
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * // Include specific fields
     * $builder->source(['title', 'author', 'created_at']);
     *
     * // Exclude source entirely (just get IDs)
     * $builder->source(false);
     *
     * // Include/exclude patterns
     * $builder->source(['includes' => ['title', 'content'], 'excludes' => ['password']]);
     * ```
     */
    public function source(array|string|bool $source): static
    {
        $this->source = $source;

        return $this;
    }

    /**
     * Enable highlighting for specified fields.
     *
     * Highlighted fragments show matching search terms in context.
     *
     * @param  array  $fields  Fields to highlight with their options
     * @param  array  $options  Global highlight options (pre_tags, post_tags, etc.)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->highlight(
     *     ['title' => new \stdClass, 'content' => ['fragment_size' => 150]],
     *     ['pre_tags' => ['<em>'], 'post_tags' => ['</em>']]
     * );
     * ```
     */
    public function highlight(array $fields, array $options = []): static
    {
        $this->highlight = array_merge($options, ['fields' => $fields]);

        return $this;
    }

    /**
     * Set track_total_hits for accurate total hit counts.
     *
     * By default Elasticsearch caps total hits at 10,000. Set to true
     * for exact counts, or an integer for a custom threshold.
     *
     * @param  bool|int  $trackTotalHits  true for exact, int for threshold
     * @return static Returns the builder instance for method chaining
     */
    public function trackTotalHits(bool|int $trackTotalHits = true): static
    {
        $this->trackTotalHits = $trackTotalHits;

        return $this;
    }

    /**
     * Add a named aggregation to the query.
     *
     * Aggregations provide analytics and statistics about search results.
     *
     * @param  string  $name  Name for this aggregation in the response
     * @param  callable  $callback  Callback receiving an AggregationBuilder
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->aggregation('categories', fn($agg) =>
     *     $agg->terms('category.keyword')->size(10)
     * );
     * ```
     */
    public function aggregation(string $name, callable $callback): static
    {
        $aggregationBuilder = new AggregationBuilder;
        $callback($aggregationBuilder);
        $this->aggregations[$name] = $aggregationBuilder->build();

        return $this;
    }

    /**
     * Add a raw aggregation to the query.
     *
     * Escape hatch for aggregation structures not yet covered by the
     * AggregationBuilder (e.g. filtered aggregations, nested aggs).
     *
     * @param  string  $name  Name for this aggregation in the response
     * @param  array  $aggregation  The raw Elasticsearch aggregation array
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->rawAggregation('price_stats', ['stats' => ['field' => 'price']]);
     * ```
     */
    public function rawAggregation(string $name, array $aggregation): static
    {
        $this->aggregations[$name] = $aggregation;

        return $this;
    }

    /**
     * Add a filter context clause.
     *
     * Filter clauses must match but don't contribute to scoring.
     * They are cached by Elasticsearch for better performance.
     *
     * @param  callable  $callback  Callback receiving a query builder for the filter
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder
     *     ->match('title', 'Laravel')
     *     ->filter(fn($q) => $q->term('status', 'published'));
     * ```
     */
    public function filter(callable $callback): static
    {
        $filterQuery = new ElasticsearchQueryBuilder($this->client, $this->manager);
        $callback($filterQuery);
        $this->filters[] = $filterQuery->build()['query'];

        return $this;
    }

    /**
     * Add a post_filter clause applied after aggregations.
     *
     * Post-filters narrow the returned hits without affecting aggregation
     * buckets. This is the standard approach for faceted search where you
     * want facet counts to reflect the unfiltered result set while still
     * scoping the visible documents.
     *
     * Calling this multiple times combines clauses under `bool.filter` inside
     * the `post_filter` object.
     *
     * @param  callable  $callback  Callback receiving a query builder for the post_filter
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * // Faceted search — aggregations see all colors, but hits are narrowed
     * Stretch::index('products')
     *     ->match('name', 'shoe')
     *     ->aggregation('colors', fn($agg) => $agg->terms('color.keyword'))
     *     ->postFilter(fn($q) => $q->term('color.keyword', 'red'))
     *     ->execute();
     * ```
     */
    public function postFilter(callable $callback): static
    {
        $postFilterQuery = new ElasticsearchQueryBuilder($this->client, $this->manager);
        $callback($postFilterQuery);
        $this->postFilters[] = $postFilterQuery->build()['query'];

        return $this;
    }

    /**
     * Build the final Elasticsearch query array.
     *
     * Assembles all query clauses, filters, aggregations, sorting,
     * and other options into the format expected by Elasticsearch.
     * Multiple queries are automatically wrapped in a bool.must clause.
     *
     * @return array The complete Elasticsearch query body
     */
    public function build(): array
    {
        $body = [];

        // Retrievers (ES 8.14+) replace the top-level query/knn clauses entirely.
        if ($this->retriever !== null) {
            $body['retriever'] = $this->retriever;
            $body['size'] = $this->getSize();
            $body['from'] = $this->getFrom();

            if (! empty($this->sort)) {
                $body['sort'] = $this->sort;
            }

            if ($this->source !== null) {
                $body['_source'] = $this->source;
            }

            if (! empty($this->highlight)) {
                $body['highlight'] = $this->highlight;
            }

            if (! empty($this->aggregations)) {
                $body['aggs'] = $this->aggregations;
            }

            if ($this->trackTotalHits !== null) {
                $body['track_total_hits'] = $this->trackTotalHits;
            }

            if (! empty($this->postFilters)) {
                $body['post_filter'] = $this->buildPostFilter();
            }

            return $body;
        }

        // Top-level kNN clause(s) for hybrid search via query + knn.
        if (! empty($this->knn)) {
            $body['knn'] = count($this->knn) === 1 ? $this->knn[0] : $this->knn;
        }

        // Build the main query
        if (! empty($this->query) || ! empty($this->filters)) {
            if (! empty($this->filters)) {
                // If we have filters, wrap everything in a bool query
                $boolQuery = ['bool' => []];

                if (! empty($this->query)) {
                    if (count($this->query) === 1) {
                        $boolQuery['bool']['must'] = $this->query[0];
                    } else {
                        $boolQuery['bool']['must'] = $this->query;
                    }
                }

                $boolQuery['bool']['filter'] = $this->filters;
                $body['query'] = $boolQuery;
            } else {
                if (count($this->query) === 1) {
                    $body['query'] = $this->query[0];
                } else {
                    $body['query'] = [
                        'bool' => [
                            'must' => $this->query,
                        ],
                    ];
                }
            }
        }

        // Add other parameters
        $body['size'] = $this->getSize();
        $body['from'] = $this->getFrom();

        if (! empty($this->sort)) {
            $body['sort'] = $this->sort;
        }

        if ($this->source !== null) {
            $body['_source'] = $this->source;
        }

        if (! empty($this->highlight)) {
            $body['highlight'] = $this->highlight;
        }

        if (! empty($this->aggregations)) {
            $body['aggs'] = $this->aggregations;
        }

        if ($this->trackTotalHits !== null) {
            $body['track_total_hits'] = $this->trackTotalHits;
        }

        if (! empty($this->postFilters)) {
            $body['post_filter'] = $this->buildPostFilter();
        }

        return $body;
    }

    /**
     * Build the post_filter clause from accumulated post-filter queries.
     *
     * A single clause is emitted directly; multiple clauses are combined
     * under `bool.filter` to preserve the AND semantics of filter context.
     */
    protected function buildPostFilter(): array
    {
        if (count($this->postFilters) === 1) {
            return $this->postFilters[0];
        }

        return ['bool' => ['filter' => $this->postFilters]];
    }

    /**
     * Execute the query and return results.
     *
     * Sends the built query to Elasticsearch and returns the response.
     * If caching is enabled (via the IsCacheable trait), results may be cached.
     *
     * @return array The Elasticsearch search response
     *
     * @throws \RuntimeException If the client is not set
     * @throws StretchException If the search fails
     */
    public function execute(): array
    {
        if (! $this->client) {
            throw new \RuntimeException('Client not set. Cannot execute query.');
        }

        $body = $this->build();
        $params = [];

        if ($this->getIndex()) {
            $params['index'] = $this->getIndex();
        }

        if (! empty($body)) {
            $params['body'] = $body;
        }

        return $this->client->search($params);
    }

    /**
     * Get the query as an array for debugging.
     *
     * Alias for build() - useful for inspecting the query structure.
     *
     * @return array The complete Elasticsearch query body
     */
    public function toArray(): array
    {
        return $this->build();
    }

    /**
     * Add a raw query clause to the builder.
     *
     * Used internally by sub-builders (like RangeQueryBuilder) to add
     * their constructed queries to the parent builder.
     *
     * @param  array  $query  The query clause to add
     */
    public function addQuery(array $query): void
    {
        $this->query[] = $query;
    }

    /**
     * Update an existing range query for a specific field.
     *
     * Used by RangeQueryBuilder when chaining multiple conditions
     * (e.g., gte()->lte()) to update the existing range query
     * rather than adding a new one.
     *
     * @param  string  $field  The field name of the range query to update
     * @param  array  $rangeQuery  The updated range query
     */
    public function updateLastRangeQuery(string $field, array $rangeQuery): void
    {
        // Find and update the last range query for this field
        for ($i = count($this->query) - 1; $i >= 0; $i--) {
            if (isset($this->query[$i]['range'][$field])) {
                $this->query[$i] = $rangeQuery;
                break;
            }
        }
    }

    /**
     * Internal method to add a query clause.
     *
     * Same as addQuery but protected - used by internal methods
     * to build the query array.
     *
     * @param  array  $query  The query clause to add
     */
    protected function addQueryProtected(array $query): void
    {
        $this->query[] = $query;
    }

    /**
     * Return the query's index
     */
    public function getIndex(): string|array|null
    {
        return $this->index;
    }

    /**
     * Return the query's size
     */
    public function getSize(): int
    {
        return min(($this->size ?? config('stretch.query.default_size')), config('stretch.query.max_size'));

    }

    /**
     * Return the query from
     */
    public function getFrom(): int
    {
        return $this->from ?? 0;
    }
}

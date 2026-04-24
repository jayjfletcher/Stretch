<?php

declare(strict_types=1);

namespace JayI\Stretch\Contracts;

use JayI\Stretch\Exceptions\StretchException;

/**
 * Contract for Elasticsearch query builders.
 *
 * Defines the fluent interface for building and executing Elasticsearch queries.
 * Implementations provide methods for various query types, aggregations,
 * sorting, pagination, and result filtering.
 */
interface QueryBuilderContract
{
    /**
     * Set the index or indices to search.
     *
     * @param  string|array  $index  Single index name or array of index names
     * @return static Returns the builder instance for method chaining
     */
    public function index(string|array $index): static;

    /**
     * Switch to a specific Elasticsearch connection.
     *
     * Creates a new query builder instance using the specified connection name.
     * This allows building queries against different Elasticsearch clusters
     * or configurations within the same application.
     *
     * @param  string  $name  The connection name as defined in configuration
     * @return static A new query builder instance using the specified connection
     *
     * @throws \RuntimeException If the connection manager is not available
     */
    public function connection(string $name): static;

    /**
     * Add a match query for full-text search.
     *
     * @param  string  $field  The field to search
     * @param  mixed  $value  The search text
     * @param  array  $options  Additional options (fuzziness, operator, etc.)
     * @return static Returns the builder instance for method chaining
     */
    public function match(string $field, mixed $value, array $options = []): static;

    /**
     * Add a match phrase query for exact phrase matching.
     *
     * @param  string  $field  The field to search
     * @param  mixed  $value  The exact phrase to match
     * @param  array  $options  Additional options (slop, etc.)
     * @return static Returns the builder instance for method chaining
     */
    public function matchPhrase(string $field, mixed $value, array $options = []): static;

    /**
     * Add a semantic query for semantic search using embeddings.
     *
     * @param  string  $field  The field to perform semantic search on
     * @param  mixed  $query  The semantic search query text
     * @param  array  $options  Additional options (boost, etc.)
     * @return static Returns the builder instance for method chaining
     */
    public function semantic(string $field, mixed $query, array $options = []): static;

    /**
     * Add a term query for exact value matching.
     *
     * @param  string  $field  The field to search (use .keyword for text fields)
     * @param  mixed  $value  The exact value to match
     * @return static Returns the builder instance for method chaining
     */
    public function term(string $field, mixed $value): static;

    /**
     * Add a terms query for matching any of multiple values.
     *
     * @param  string  $field  The field to search
     * @param  array  $values  Array of values to match against
     * @return static Returns the builder instance for method chaining
     */
    public function terms(string $field, array $values): static;

    /**
     * Start building a range query for numeric or date fields.
     *
     * @param  string  $field  The field to apply the range query to
     * @return RangeQueryBuilderContract The range query builder for chaining
     */
    public function range(string $field): RangeQueryBuilderContract;

    /**
     * Create a bool query with must/should/filter/mustNot clauses.
     *
     * @param  callable|null  $callback  Optional callback receiving the BoolQueryBuilder
     * @return BoolQueryBuilderContract The bool query builder
     */
    public function bool(?callable $callback = null): BoolQueryBuilderContract;

    /**
     * Add a nested query for searching nested objects.
     *
     * @param  string  $path  The path to the nested object field
     * @param  callable  $callback  Callback receiving a query builder for the nested query
     * @return static Returns the builder instance for method chaining
     */
    public function nested(string $path, callable $callback): static;

    /**
     * Add a wildcard query for pattern matching.
     *
     * @param  string  $field  The field to search
     * @param  string  $value  The wildcard pattern (* and ? supported)
     * @return static Returns the builder instance for method chaining
     */
    public function wildcard(string $field, string $value): static;

    /**
     * Add a fuzzy query for approximate string matching.
     *
     * @param  string  $field  The field to search
     * @param  mixed  $value  The search term
     * @param  array  $options  Options like fuzziness, prefix_length, max_expansions
     * @return static Returns the builder instance for method chaining
     */
    public function fuzzy(string $field, mixed $value, array $options = []): static;

    /**
     * Add a multi_match query for full-text search across multiple fields.
     *
     * @param  string  $query  The search text
     * @param  array  $fields  Fields to search with optional boosts (e.g. ['title^3', 'description'])
     * @param  array  $options  Additional options (type, fuzziness, minimum_should_match, etc.)
     * @return static Returns the builder instance for method chaining
     */
    public function multiMatch(string $query, array $fields, array $options = []): static;

    /**
     * Add a top-level kNN search clause for vector similarity search.
     *
     * Can be combined with query clauses (match, bool, etc.) to produce
     * hybrid search — Elasticsearch linearly combines the query and kNN scores.
     *
     * Pass null for $queryVector when using query_vector_builder in $options
     * to let Elasticsearch generate the embedding server-side.
     *
     * @param  string  $field  The dense_vector field to search
     * @param  array|null  $queryVector  The query vector, or null when using query_vector_builder
     * @param  int  $k  Number of nearest neighbours to return
     * @param  int|null  $numCandidates  Candidates considered per shard
     * @param  array  $options  Extra kNN options (boost, filter, similarity, query_vector_builder, etc.)
     * @return static Returns the builder instance for method chaining
     */
    public function knn(string $field, ?array $queryVector, int $k = 10, ?int $numCandidates = null, array $options = []): static;

    /**
     * Set the top-level retriever clause for hybrid search.
     *
     * Retrievers compose hybrid search pipelines (standard + kNN + rrf).
     * When a retriever is set, it replaces the `query` and `knn` clauses.
     *
     * @param  callable  $callback  Callback receiving a RetrieverBuilder
     * @return static Returns the builder instance for method chaining
     */
    public function retriever(callable $callback): static;

    /**
     * Add a rank_feature query to boost by a numeric feature field.
     *
     * Operates on `rank_feature` or `rank_features` mapped fields. Pass a
     * score function via $options (`saturation`, `log`, `sigmoid`, or
     * `linear` — the default) and/or a `boost` factor.
     *
     * @param  string  $field  The rank_feature or rank_features field
     * @param  array  $options  Score function config and/or boost
     * @return static Returns the builder instance for method chaining
     */
    public function rankFeature(string $field, array $options = []): static;

    /**
     * Add an exists query to find documents with a field value.
     *
     * @param  string  $field  The field that must exist
     * @return static Returns the builder instance for method chaining
     */
    public function exists(string $field): static;

    /**
     * Set the maximum number of results to return.
     *
     * @param  int  $size  Maximum number of hits to return
     * @return static Returns the builder instance for method chaining
     */
    public function size(int $size): static;

    /**
     * Set the offset for pagination.
     *
     * @param  int  $from  Number of results to skip
     * @return static Returns the builder instance for method chaining
     */
    public function from(int $from): static;

    /**
     * Add a sort clause to order results.
     *
     * @param  string|array  $field  Field name or full sort configuration array
     * @param  string  $direction  Sort direction: 'asc' or 'desc'
     * @return static Returns the builder instance for method chaining
     */
    public function sort(string|array $field, string $direction = 'asc'): static;

    /**
     * Configure source field filtering in results.
     *
     * @param  array|string|bool  $source  Fields to include, or false to exclude all
     * @return static Returns the builder instance for method chaining
     */
    public function source(array|string|bool $source): static;

    /**
     * Enable highlighting for specified fields.
     *
     * @param  array  $fields  Fields to highlight with their options
     * @param  array  $options  Global highlight options (pre_tags, post_tags, etc.)
     * @return static Returns the builder instance for method chaining
     */
    public function highlight(array $fields, array $options = []): static;

    /**
     * Set track_total_hits for accurate total hit counts.
     *
     * By default Elasticsearch caps total hits at 10,000. Set to true
     * for exact counts, or an integer for a custom threshold.
     *
     * @param  bool|int  $trackTotalHits  true for exact, int for threshold
     * @return static Returns the builder instance for method chaining
     */
    public function trackTotalHits(bool|int $trackTotalHits = true): static;

    /**
     * Enable the Search Profile API for this request.
     *
     * Adds `profile: true` to the search body so Elasticsearch returns
     * detailed timing and execution info under a `profile` key in the
     * response. Useful for diagnosing slow queries.
     *
     * @param  bool  $profile  Whether to enable profiling (default true)
     * @return static Returns the builder instance for method chaining
     */
    public function profile(bool $profile = true): static;

    /**
     * Collapse hits by a single-valued field.
     *
     * Returns at most one hit per unique value of the field. Supports
     * `inner_hits` for retrieving additional hits per collapsed group.
     * Pass a string for a simple collapse, or a full config array.
     *
     * @param  string|array  $field  Field name, or a full collapse config array
     * @param  array|null  $innerHits  Optional inner_hits config (object or list of objects)
     * @param  int|null  $maxConcurrentGroupSearches  Concurrency limit for inner_hits group searches
     * @return static Returns the builder instance for method chaining
     */
    public function collapse(string|array $field, ?array $innerHits = null, ?int $maxConcurrentGroupSearches = null): static;

    /**
     * Add a named aggregation to the query.
     *
     * @param  string  $name  Name for this aggregation in the response
     * @param  callable  $callback  Callback receiving an AggregationBuilder
     * @return static Returns the builder instance for method chaining
     */
    public function aggregation(string $name, callable $callback): static;

    /**
     * Add a raw aggregation to the query.
     *
     * Escape hatch for aggregation structures not yet covered by the
     * AggregationBuilder (e.g. filtered aggregations, nested aggs with
     * multiple levels, stats aggregations).
     *
     * @param  string  $name  Name for this aggregation in the response
     * @param  array  $aggregation  The raw Elasticsearch aggregation array
     * @return static Returns the builder instance for method chaining
     */
    public function rawAggregation(string $name, array $aggregation): static;

    /**
     * Add a filter context clause (no scoring, cached).
     *
     * @param  callable  $callback  Callback receiving a query builder for the filter
     * @return static Returns the builder instance for method chaining
     */
    public function filter(callable $callback): static;

    /**
     * Add a post_filter clause applied after aggregations.
     *
     * Unlike `filter()`, post_filter runs after the main query and aggregations,
     * so aggregation buckets reflect the full query results while hits are
     * narrowed. Useful for faceted search where filters should not skew facets.
     *
     * @param  callable  $callback  Callback receiving a query builder for the post_filter
     * @return static Returns the builder instance for method chaining
     */
    public function postFilter(callable $callback): static;

    /**
     * Build the final Elasticsearch query array.
     *
     * @return array The complete Elasticsearch query body
     */
    public function build(): array;

    /**
     * Execute the query and return results.
     *
     * @return array The Elasticsearch search response
     *
     * @throws StretchException If the search fails
     */
    public function execute(): array;

    /**
     * Get the raw query array for debugging.
     *
     * @return array The complete Elasticsearch query body
     */
    public function toArray(): array;

    /**
     * Get the parameters sent to Elasticsearch on the most recent execute().
     *
     * Returns the `['index' => ..., 'body' => ...]` payload last dispatched
     * to the client, or null if execute() has not yet run on this builder.
     *
     * @return array|null The last executed query parameters, or null if never executed
     */
    public function getLastQuery(): ?array;

    /**
     * Add a raw query clause to the builder.
     *
     * @param  array  $query  The query clause to add
     */
    public function addQuery(array $query): void;

    /**
     * Update an existing range query for a specific field.
     *
     * @param  string  $field  The field name of the range query to update
     * @param  array  $rangeQuery  The updated range query
     */
    public function updateLastRangeQuery(string $field, array $rangeQuery): void;

    /**
     * Return the query's index
     */
    public function getIndex(): string|array|null;

    /**
     * Return the query's size
     */
    public function getSize(): int;

    /**
     * Return the query from
     */
    public function getFrom(): int;
}

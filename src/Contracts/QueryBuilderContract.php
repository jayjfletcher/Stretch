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
     * Add a match_phrase_prefix query (search-as-you-type on the last term).
     *
     * @param  string  $field  The field to search
     * @param  mixed  $value  The phrase; its last term is matched as a prefix
     * @param  array  $options  Additional options (slop, max_expansions, etc.)
     * @return static Returns the builder instance for method chaining
     */
    public function matchPhrasePrefix(string $field, mixed $value, array $options = []): static;

    /**
     * Add a match_bool_prefix query (search-as-you-type across terms).
     *
     * @param  string  $field  The field to search
     * @param  mixed  $value  The search text; its last term is matched as a prefix
     * @param  array  $options  Additional options (fuzziness, operator, etc.)
     * @return static Returns the builder instance for method chaining
     */
    public function matchBoolPrefix(string $field, mixed $value, array $options = []): static;

    /**
     * Add a prefix query for exact prefix matching on a term.
     *
     * @param  string  $field  The field to search
     * @param  string  $value  The prefix to match
     * @param  array  $options  Additional options (boost, case_insensitive, rewrite)
     * @return static Returns the builder instance for method chaining
     */
    public function prefix(string $field, string $value, array $options = []): static;

    /**
     * Add a regexp query for regular-expression term matching.
     *
     * @param  string  $field  The field to search
     * @param  string  $value  The regular expression pattern
     * @param  array  $options  Additional options (flags, case_insensitive, max_determinized_states)
     * @return static Returns the builder instance for method chaining
     */
    public function regexp(string $field, string $value, array $options = []): static;

    /**
     * Add an ids query to fetch documents by their `_id` values.
     *
     * @param  array  $values  The document IDs to match
     * @return static Returns the builder instance for method chaining
     */
    public function ids(array $values): static;

    /**
     * Add a terms_set query matching a minimum number of the given terms.
     *
     * @param  string  $field  The field to match terms against
     * @param  array  $terms  The candidate terms
     * @param  array  $options  Must include minimum_should_match_field or minimum_should_match_script
     * @return static Returns the builder instance for method chaining
     */
    public function termsSet(string $field, array $terms, array $options = []): static;

    /**
     * Add a distance_feature query to boost by proximity to an origin.
     *
     * @param  string  $field  The date or geo_point field
     * @param  mixed  $origin  The origin (e.g. 'now', a timestamp, or [lon, lat])
     * @param  string  $pivot  Distance at which the score is halved (e.g. '7d', '1000m')
     * @param  array  $options  Additional options (boost)
     * @return static Returns the builder instance for method chaining
     */
    public function distanceFeature(string $field, mixed $origin, string $pivot, array $options = []): static;

    /**
     * Add a dis_max (disjunction max) query.
     *
     * @param  callable  $callback  Callback receiving a query builder for the sub-queries
     * @param  float|null  $tieBreaker  Fraction of non-max clause scores to add (0.0–1.0)
     * @param  array  $options  Additional options (boost)
     * @return static Returns the builder instance for method chaining
     */
    public function disMax(callable $callback, ?float $tieBreaker = null, array $options = []): static;

    /**
     * Add a constant_score query wrapping a filter.
     *
     * @param  callable  $callback  Callback receiving a query builder for the filter
     * @param  float  $boost  The constant score to assign to matching documents
     * @return static Returns the builder instance for method chaining
     */
    public function constantScore(callable $callback, float $boost = 1.0): static;

    /**
     * Add a boosting query to demote (rather than exclude) documents.
     *
     * @param  callable  $positive  Callback building the positive (required) query
     * @param  callable  $negative  Callback building the negative (demoting) query
     * @param  float  $negativeBoost  Multiplier applied to negative-matching scores
     * @return static Returns the builder instance for method chaining
     */
    public function boosting(callable $positive, callable $negative, float $negativeBoost = 0.5): static;

    /**
     * Add a script_score query for custom scoring via a script.
     *
     * @param  callable  $callback  Callback building the inner query
     * @param  array  $script  The script definition (source/id, params, lang)
     * @param  array  $options  Additional options (min_score, boost)
     * @return static Returns the builder instance for method chaining
     */
    public function scriptScore(callable $callback, array $script, array $options = []): static;

    /**
     * Add a function_score query for fine-grained custom scoring.
     *
     * @param  callable  $callback  Callback receiving a FunctionScoreBuilder
     * @return static Returns the builder instance for method chaining
     */
    public function functionScore(callable $callback): static;

    /**
     * Add a geo_distance query matching documents within a radius of a point.
     *
     * @param  string  $field  The geo_point field
     * @param  mixed  $location  The center point ([lon, lat], "lat,lon", or geohash)
     * @param  string  $distance  The radius (e.g. '200km', '1000m')
     * @param  array  $options  Additional options (distance_type, validation_method)
     * @return static Returns the builder instance for method chaining
     */
    public function geoDistance(string $field, mixed $location, string $distance, array $options = []): static;

    /**
     * Add a geo_bounding_box query matching documents inside a box.
     *
     * @param  string  $field  The geo_point field
     * @param  array  $box  The box (top_left / bottom_right or wkt)
     * @param  array  $options  Additional options (validation_method, type)
     * @return static Returns the builder instance for method chaining
     */
    public function geoBoundingBox(string $field, array $box, array $options = []): static;

    /**
     * Add a geo_shape query for shape-relation matching.
     *
     * @param  string  $field  The geo_shape or geo_point field
     * @param  array  $shape  The GeoJSON shape (type + coordinates)
     * @param  string  $relation  Spatial relation (intersects, within, contains, disjoint)
     * @param  array  $options  Additional options
     * @return static Returns the builder instance for method chaining
     */
    public function geoShape(string $field, array $shape, string $relation = 'intersects', array $options = []): static;

    /**
     * Add a percolate query to find stored queries matching a document.
     *
     * @param  string  $field  The percolator-typed field holding stored queries
     * @param  array  $document  The document to percolate
     * @param  array  $options  Additional options (name, documents for multi-doc)
     * @return static Returns the builder instance for method chaining
     */
    public function percolate(string $field, array $document, array $options = []): static;

    /**
     * Add a span query for positional / proximity matching.
     *
     * @param  callable  $callback  Callback receiving a SpanQueryBuilder
     * @return static Returns the builder instance for method chaining
     */
    public function span(callable $callback): static;

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
     * Set the search_after cursor for deep pagination.
     *
     * @param  array  $sortValues  The sort values of the last hit on the prior page
     * @return static Returns the builder instance for method chaining
     */
    public function searchAfter(array $sortValues): static;

    /**
     * Attach a point-in-time (PIT) to the search for consistent deep paging.
     *
     * @param  string  $id  The PIT id returned from openPointInTime()
     * @param  string|null  $keepAlive  Extension of the PIT lifetime (e.g. '1m')
     * @return static Returns the builder instance for method chaining
     */
    public function pointInTime(string $id, ?string $keepAlive = null): static;

    /**
     * Set the minimum _score a hit must reach to be returned.
     *
     * @param  float  $minScore  The score threshold
     * @return static Returns the builder instance for method chaining
     */
    public function minScore(float $minScore): static;

    /**
     * Return a scoring explanation for each hit.
     *
     * @param  bool  $explain  Whether to enable explanations (default true)
     * @return static Returns the builder instance for method chaining
     */
    public function explain(bool $explain = true): static;

    /**
     * Stop collecting after N matching documents per shard.
     *
     * @param  int  $count  Maximum documents to collect per shard
     * @return static Returns the builder instance for method chaining
     */
    public function terminateAfter(int $count): static;

    /**
     * Set the search_type request parameter.
     *
     * @param  string  $searchType  e.g. 'query_then_fetch' or 'dfs_query_then_fetch'
     * @return static Returns the builder instance for method chaining
     */
    public function searchType(string $searchType): static;

    /**
     * Set the shard preference request parameter.
     *
     * @param  string  $preference  The preference value (e.g. '_local', a custom string)
     * @return static Returns the builder instance for method chaining
     */
    public function preference(string $preference): static;

    /**
     * Set the routing request parameter to target specific shards.
     *
     * @param  string|array  $routing  One or more routing values
     * @return static Returns the builder instance for method chaining
     */
    public function routing(string|array $routing): static;

    /**
     * Add a rescore clause to re-score the top window of results.
     *
     * @param  callable  $callback  Callback building the rescore query
     * @param  int|null  $windowSize  Number of top hits per shard to rescore
     * @param  array  $options  Extra query_rescorer options (query_weight, rescore_query_weight, score_mode)
     * @return static Returns the builder instance for method chaining
     */
    public function rescore(callable $callback, ?int $windowSize = null, array $options = []): static;

    /**
     * Define runtime fields evaluated at query time.
     *
     * @param  array  $mappings  Map of field name to runtime field definition
     * @return static Returns the builder instance for method chaining
     */
    public function runtimeMappings(array $mappings): static;

    /**
     * Request specific fields (formatted) in the response.
     *
     * @param  array  $fields  Field names or field/format definitions
     * @return static Returns the builder instance for method chaining
     */
    public function fields(array $fields): static;

    /**
     * Request doc-value fields in the response.
     *
     * @param  array  $fields  Field names or field/format definitions
     * @return static Returns the builder instance for method chaining
     */
    public function docvalueFields(array $fields): static;

    /**
     * Add suggesters to the request for autocomplete / did-you-mean.
     *
     * @param  callable  $callback  Callback receiving a SuggestBuilder
     * @return static Returns the builder instance for method chaining
     */
    public function suggest(callable $callback): static;

    /**
     * Return the number of documents matching the query via the _count API.
     *
     * @return int The matching document count
     *
     * @throws StretchException If the operation fails
     */
    public function count(): int;

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
     * Delete documents matching the current query.
     *
     * @return array The delete by query response
     *
     * @throws StretchException If the operation fails
     */
    public function delete(): array;

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
     * @return int The index of the added clause, usable with replaceQuery()
     */
    public function addQuery(array $query): int;

    /**
     * Replace a previously added query clause by its index.
     *
     * @param  int  $index  The clause index returned by addQuery()
     * @param  array  $query  The replacement query clause
     */
    public function replaceQuery(int $index, array $query): void;

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

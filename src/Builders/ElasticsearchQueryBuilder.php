<?php

declare(strict_types=1);

namespace JayI\Stretch\Builders;

use Illuminate\Container\Container;
use JayI\Stretch\Builders\Concerns\IsCacheable;
use JayI\Stretch\Builders\Concerns\SwitchesConnections;
use JayI\Stretch\Builders\Concerns\TracksLastQuery;
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
    use SwitchesConnections;
    use TracksLastQuery;

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
     * Whether to enable the Search Profile API for this request.
     */
    protected ?bool $profile = null;

    /**
     * Field collapse configuration.
     *
     * When set, produces a `collapse` clause in the request body that groups
     * hits by a single-valued field and returns one hit per group.
     */
    protected ?array $collapse = null;

    /**
     * search_after cursor for deep pagination.
     *
     * @var array<int, mixed>|null
     */
    protected ?array $searchAfter = null;

    /**
     * Point-in-time configuration ({id, keep_alive}) for consistent deep paging.
     */
    protected ?array $pit = null;

    /**
     * Minimum _score a hit must reach to be returned.
     */
    protected ?float $minScore = null;

    /**
     * Whether to return the scoring explanation for each hit.
     */
    protected ?bool $explain = null;

    /**
     * Maximum documents to collect per shard before early termination.
     */
    protected ?int $terminateAfter = null;

    /**
     * Search type (e.g. dfs_query_then_fetch) applied as a request parameter.
     */
    protected ?string $searchType = null;

    /**
     * Shard preference string applied as a request parameter.
     */
    protected ?string $preference = null;

    /**
     * Routing value(s) applied as a request parameter.
     */
    protected string|array|null $routing = null;

    /**
     * Rescore clause(s) for two-phase precision tuning of the top window.
     *
     * @var array<int, array>
     */
    protected array $rescore = [];

    /**
     * Runtime field mappings evaluated at query time.
     */
    protected ?array $runtimeMappings = null;

    /**
     * Fields to retrieve (formatted) alongside or instead of _source.
     *
     * @var array<int, mixed>
     */
    protected array $fields = [];

    /**
     * Doc-value fields to retrieve.
     *
     * @var array<int, mixed>
     */
    protected array $docvalueFields = [];

    /**
     * Suggester clause built via the SuggestBuilder.
     */
    protected ?array $suggest = null;

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
     * Add a match_all query that matches every document.
     *
     * Useful as a default query, for paginating an entire index, or as a
     * placeholder inside bool clauses. Pass options such as `boost` to
     * influence scoring.
     *
     * @param  array  $options  Additional options (e.g. ['boost' => 1.2])
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->matchAll();
     * $builder->matchAll(['boost' => 1.2]);
     * ```
     */
    public function matchAll(array $options = []): static
    {
        $this->addQueryProtected([
            'match_all' => (object) $options,
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
     * When the callback adds no query clauses, the nested clause is skipped
     * entirely to avoid emitting invalid DSL.
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

        $query = $nestedQuery->build()['query'] ?? null;

        if ($query === null) {
            return $this;
        }

        $this->addQueryProtected([
            'nested' => [
                'path' => $path,
                'query' => $query,
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
     * Add a match_phrase_prefix query for search-as-you-type on the last term.
     *
     * Like matchPhrase, but the final term is treated as a prefix. Ideal for
     * autocomplete where the user is mid-word.
     *
     * @param  string  $field  The field to search
     * @param  mixed  $value  The phrase; its last term is matched as a prefix
     * @param  array  $options  Additional options (slop, max_expansions, etc.)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->matchPhrasePrefix('title', 'quick brown f');
     * ```
     */
    public function matchPhrasePrefix(string $field, mixed $value, array $options = []): static
    {
        $match = array_merge(['query' => $value], $options);

        $this->addQueryProtected([
            'match_phrase_prefix' => [
                $field => $match,
            ],
        ]);

        return $this;
    }

    /**
     * Add a match_bool_prefix query for search-as-you-type across terms.
     *
     * Analyzes the text into terms combined in a bool query; the final term is
     * matched as a prefix. Unlike match_phrase_prefix, terms may appear in any
     * order.
     *
     * @param  string  $field  The field to search
     * @param  mixed  $value  The search text; its last term is matched as a prefix
     * @param  array  $options  Additional options (fuzziness, operator, etc.)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->matchBoolPrefix('title', 'quick brown f');
     * ```
     */
    public function matchBoolPrefix(string $field, mixed $value, array $options = []): static
    {
        $match = array_merge(['query' => $value], $options);

        $this->addQueryProtected([
            'match_bool_prefix' => [
                $field => $match,
            ],
        ]);

        return $this;
    }

    /**
     * Add a prefix query for exact prefix matching on a keyword field.
     *
     * Finds documents where the field's term begins with the given prefix.
     * Operates on non-analyzed terms — use `.keyword` sub-fields for text.
     *
     * @param  string  $field  The field to search
     * @param  string  $value  The prefix to match
     * @param  array  $options  Additional options (boost, rewrite, case_insensitive)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->prefix('user.id', 'ki');
     * $builder->prefix('code', 'ABC', ['case_insensitive' => true]);
     * ```
     */
    public function prefix(string $field, string $value, array $options = []): static
    {
        $prefix = empty($options)
            ? $value
            : array_merge(['value' => $value], $options);

        $this->addQueryProtected([
            'prefix' => [
                $field => $prefix,
            ],
        ]);

        return $this;
    }

    /**
     * Add a regexp query for regular-expression term matching.
     *
     * Matches terms against a Lucene regular expression. Operates on
     * non-analyzed terms — use `.keyword` sub-fields for text.
     *
     * @param  string  $field  The field to search
     * @param  string  $value  The regular expression pattern
     * @param  array  $options  Additional options (flags, max_determinized_states, case_insensitive)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->regexp('name', 'joh?n(ny)?');
     * $builder->regexp('code', '[a-z]{3}-[0-9]+', ['flags' => 'ALL']);
     * ```
     */
    public function regexp(string $field, string $value, array $options = []): static
    {
        $regexp = empty($options)
            ? $value
            : array_merge(['value' => $value], $options);

        $this->addQueryProtected([
            'regexp' => [
                $field => $regexp,
            ],
        ]);

        return $this;
    }

    /**
     * Add an ids query to fetch documents by their `_id` values.
     *
     * @param  array  $values  The document IDs to match
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->ids(['1', '4', '100']);
     * ```
     */
    public function ids(array $values): static
    {
        $this->addQueryProtected([
            'ids' => [
                'values' => $values,
            ],
        ]);

        return $this;
    }

    /**
     * Add a terms_set query matching a minimum number of the given terms.
     *
     * Matches documents that contain at least a minimum number of the provided
     * terms. The minimum is derived either from a numeric field on the document
     * (`minimum_should_match_field`) or from a script
     * (`minimum_should_match_script`). Exactly one must be supplied via $options.
     *
     * @param  string  $field  The field to match terms against
     * @param  array  $terms  The candidate terms
     * @param  array  $options  Must include minimum_should_match_field or minimum_should_match_script (plus optional boost)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->termsSet('tags', ['php', 'laravel', 'elasticsearch'], [
     *     'minimum_should_match_field' => 'required_matches',
     * ]);
     *
     * $builder->termsSet('tags', ['php', 'laravel'], [
     *     'minimum_should_match_script' => ['source' => "Math.min(params.num_terms, 2)"],
     * ]);
     * ```
     */
    public function termsSet(string $field, array $terms, array $options = []): static
    {
        $termsSet = array_merge(['terms' => $terms], $options);

        $this->addQueryProtected([
            'terms_set' => [
                $field => $termsSet,
            ],
        ]);

        return $this;
    }

    /**
     * Add a distance_feature query to boost by proximity to an origin.
     *
     * Boosts documents whose `date`, `date_nanos`, or `geo_point` field is
     * close to an origin. Commonly used to favour recent or nearby documents
     * inside a bool query's `should` clauses.
     *
     * @param  string  $field  The date or geo_point field
     * @param  mixed  $origin  The origin (e.g. 'now', a timestamp, or [lon, lat])
     * @param  string  $pivot  Distance at which the score is halved (e.g. '7d', '1000m')
     * @param  array  $options  Additional options (boost)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * // Favour recent documents
     * $builder->distanceFeature('created_at', 'now', '7d');
     *
     * // Favour documents near a location
     * $builder->distanceFeature('location', [-71.3, 41.15], '1000m');
     * ```
     */
    public function distanceFeature(string $field, mixed $origin, string $pivot, array $options = []): static
    {
        $distanceFeature = array_merge([
            'field' => $field,
            'origin' => $origin,
            'pivot' => $pivot,
        ], $options);

        $this->addQueryProtected([
            'distance_feature' => $distanceFeature,
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
     * Add a rank_feature query to boost by a numeric feature field.
     *
     * Operates on `rank_feature` or `rank_features` mapped fields and boosts
     * matching documents using one of four score functions: `saturation`,
     * `log`, `sigmoid`, or `linear` (default). Typically used inside a bool
     * query's `should` clauses to blend relevance signals like pagerank,
     * recency, or popularity into the score.
     *
     * Pass the score-function config via $options. Examples:
     *  - ['saturation' => ['pivot' => 8]]
     *  - ['log' => ['scaling_factor' => 4]]
     *  - ['sigmoid' => ['pivot' => 7, 'exponent' => 0.6]]
     *  - ['linear' => new \stdClass] (or omit — linear is the default)
     *  - ['boost' => 2.5] (stackable with any of the above)
     *
     * @param  string  $field  The rank_feature or rank_features field
     * @param  array  $options  Score function config and/or boost
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * // Saturation boost on a pagerank feature
     * $builder->rankFeature('pagerank', ['saturation' => ['pivot' => 8]]);
     *
     * // Default linear scoring with a boost
     * $builder->rankFeature('popularity', ['boost' => 2.0]);
     *
     * // Typical usage inside a bool query
     * $builder->bool(function ($bool) {
     *     $bool->must(fn ($q) => $q->match('content', 'laravel'))
     *         ->should(fn ($q) => $q->rankFeature('pagerank', ['saturation' => ['pivot' => 8]]))
     *         ->should(fn ($q) => $q->rankFeature('url_length', ['log' => ['scaling_factor' => 4]]));
     * });
     * ```
     */
    public function rankFeature(string $field, array $options = []): static
    {
        $rankFeature = array_merge(['field' => $field], $options);

        $this->addQueryProtected([
            'rank_feature' => $rankFeature,
        ]);

        return $this;
    }

    /**
     * Add a dis_max (disjunction max) query.
     *
     * Runs multiple sub-queries and scores each document by the highest-scoring
     * clause (plus a tie_breaker fraction of the others). The callback receives
     * a fresh query builder whose clauses become the dis_max `queries`.
     *
     * @param  callable  $callback  Callback receiving a query builder for the sub-queries
     * @param  float|null  $tieBreaker  Fraction of non-max clause scores to add (0.0–1.0)
     * @param  array  $options  Additional options (boost)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->disMax(function ($q) {
     *     $q->match('title', 'quick fox');
     *     $q->match('body', 'quick fox');
     * }, tieBreaker: 0.3);
     * ```
     */
    public function disMax(callable $callback, ?float $tieBreaker = null, array $options = []): static
    {
        $inner = new ElasticsearchQueryBuilder($this->client, $this->manager);
        $callback($inner);

        $disMax = array_merge(['queries' => $inner->getQueryClauses()], $options);

        if ($tieBreaker !== null) {
            $disMax['tie_breaker'] = $tieBreaker;
        }

        $this->addQueryProtected(['dis_max' => $disMax]);

        return $this;
    }

    /**
     * Add a constant_score query wrapping a filter.
     *
     * Every matching document receives the same constant score (the boost),
     * regardless of relevance. The callback builds the inner filter query.
     *
     * @param  callable  $callback  Callback receiving a query builder for the filter
     * @param  float  $boost  The constant score to assign to matching documents
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->constantScore(fn ($q) => $q->term('status', 'published'), boost: 1.2);
     * ```
     */
    public function constantScore(callable $callback, float $boost = 1.0): static
    {
        $inner = new ElasticsearchQueryBuilder($this->client, $this->manager);
        $callback($inner);

        $filter = $inner->build()['query'] ?? ['match_all' => (object) []];

        $this->addQueryProtected([
            'constant_score' => [
                'filter' => $filter,
                'boost' => $boost,
            ],
        ]);

        return $this;
    }

    /**
     * Add a boosting query to demote (rather than exclude) documents.
     *
     * Documents matching the `positive` query are returned; those also matching
     * the `negative` query have their score multiplied by `negativeBoost`
     * (0.0–1.0). Useful for downranking without filtering out.
     *
     * @param  callable  $positive  Callback building the positive (required) query
     * @param  callable  $negative  Callback building the negative (demoting) query
     * @param  float  $negativeBoost  Multiplier applied to negative-matching scores
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->boosting(
     *     positive: fn ($q) => $q->match('text', 'apple'),
     *     negative: fn ($q) => $q->match('text', 'pie tart'),
     *     negativeBoost: 0.5,
     * );
     * ```
     */
    public function boosting(callable $positive, callable $negative, float $negativeBoost = 0.5): static
    {
        $positiveBuilder = new ElasticsearchQueryBuilder($this->client, $this->manager);
        $positive($positiveBuilder);

        $negativeBuilder = new ElasticsearchQueryBuilder($this->client, $this->manager);
        $negative($negativeBuilder);

        $this->addQueryProtected([
            'boosting' => [
                'positive' => $positiveBuilder->build()['query'] ?? ['match_all' => (object) []],
                'negative' => $negativeBuilder->build()['query'] ?? ['match_all' => (object) []],
                'negative_boost' => $negativeBoost,
            ],
        ]);

        return $this;
    }

    /**
     * Add a script_score query for custom scoring via a script.
     *
     * Wraps an inner query and re-scores every matching document with a script.
     * The callback builds the inner query; the script is supplied via $script
     * (e.g. ['source' => "Math.log(2 + doc['likes'].value)"]).
     *
     * @param  callable  $callback  Callback building the inner query
     * @param  array  $script  The script definition (source/id, params, lang)
     * @param  array  $options  Additional options (min_score, boost)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->scriptScore(
     *     fn ($q) => $q->match('title', 'laravel'),
     *     ['source' => "_score * doc['popularity'].value"],
     * );
     * ```
     */
    public function scriptScore(callable $callback, array $script, array $options = []): static
    {
        $inner = new ElasticsearchQueryBuilder($this->client, $this->manager);
        $callback($inner);

        $scriptScore = array_merge([
            'query' => $inner->build()['query'] ?? ['match_all' => (object) []],
            'script' => $script,
        ], $options);

        $this->addQueryProtected(['script_score' => $scriptScore]);

        return $this;
    }

    /**
     * Add a function_score query for fine-grained custom scoring.
     *
     * The callback receives a FunctionScoreBuilder for composing the inner
     * query, one or more scoring functions (field_value_factor, decay
     * functions, random_score, script_score, weight), the score_mode /
     * boost_mode, and min/max score bounds.
     *
     * @param  callable  $callback  Callback receiving a FunctionScoreBuilder
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->functionScore(function ($fs) {
     *     $fs->query(fn ($q) => $q->match('title', 'laravel'))
     *         ->fieldValueFactor('popularity', modifier: 'log1p', factor: 1.2)
     *         ->gauss('created_at', origin: 'now', scale: '10d')
     *         ->scoreMode('sum')
     *         ->boostMode('multiply');
     * });
     * ```
     */
    public function functionScore(callable $callback): static
    {
        $builder = new FunctionScoreBuilder($this->client, $this->manager);
        $callback($builder);

        $this->addQueryProtected(['function_score' => $builder->build()]);

        return $this;
    }

    /**
     * Add a geo_distance query matching documents within a radius of a point.
     *
     * @param  string  $field  The geo_point field
     * @param  mixed  $location  The center point ([lon, lat], "lat,lon", or geohash)
     * @param  string  $distance  The radius (e.g. '200km', '1000m')
     * @param  array  $options  Additional options (distance_type, validation_method)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->geoDistance('pin.location', [-70, 40], '200km');
     * ```
     */
    public function geoDistance(string $field, mixed $location, string $distance, array $options = []): static
    {
        $this->addQueryProtected([
            'geo_distance' => array_merge([
                'distance' => $distance,
                $field => $location,
            ], $options),
        ]);

        return $this;
    }

    /**
     * Add a geo_bounding_box query matching documents inside a box.
     *
     * @param  string  $field  The geo_point field
     * @param  array  $box  The box, e.g. ['top_left' => [...], 'bottom_right' => [...]]
     * @param  array  $options  Additional options (validation_method, type)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->geoBoundingBox('pin.location', [
     *     'top_left' => [-74.1, 40.73],
     *     'bottom_right' => [-71.12, 40.01],
     * ]);
     * ```
     */
    public function geoBoundingBox(string $field, array $box, array $options = []): static
    {
        $this->addQueryProtected([
            'geo_bounding_box' => array_merge([
                $field => $box,
            ], $options),
        ]);

        return $this;
    }

    /**
     * Add a geo_shape query for shape-relation matching.
     *
     * Matches documents whose `geo_shape`/`geo_point` field has the given
     * spatial relation (intersects, within, contains, disjoint) to a shape.
     *
     * @param  string  $field  The geo_shape or geo_point field
     * @param  array  $shape  The GeoJSON shape (type + coordinates)
     * @param  string  $relation  Spatial relation (intersects, within, contains, disjoint)
     * @param  array  $options  Additional options
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->geoShape('location', [
     *     'type' => 'envelope',
     *     'coordinates' => [[-74.1, 40.73], [-71.12, 40.01]],
     * ], relation: 'within');
     * ```
     */
    public function geoShape(string $field, array $shape, string $relation = 'intersects', array $options = []): static
    {
        $this->addQueryProtected([
            'geo_shape' => array_merge([
                $field => [
                    'shape' => $shape,
                    'relation' => $relation,
                ],
            ], $options),
        ]);

        return $this;
    }

    /**
     * Add a percolate query to find stored queries matching a document.
     *
     * Reverse search: instead of matching documents against a query, this
     * matches a document against queries stored in a `percolator` field.
     * Powers alerting and saved-search notification use cases.
     *
     * @param  string  $field  The percolator-typed field holding stored queries
     * @param  array  $document  The document to percolate
     * @param  array  $options  Additional options (name, documents for multi-doc)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->percolate('query', ['message' => 'A new bonsai tree is for sale']);
     * ```
     */
    public function percolate(string $field, array $document, array $options = []): static
    {
        $this->addQueryProtected([
            'percolate' => array_merge([
                'field' => $field,
                'document' => $document,
            ], $options),
        ]);

        return $this;
    }

    /**
     * Add a span query for positional / proximity matching.
     *
     * The callback receives a SpanQueryBuilder for composing span clauses
     * (span_term, span_near, span_or, span_not, span_first, span_within,
     * span_containing, span_multi). Return the terminal clause from the
     * callback, or rely on the last-built clause.
     *
     * @param  callable  $callback  Callback receiving a SpanQueryBuilder
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * // "quick" and "fox" within 3 positions, in order
     * $builder->span(fn ($s) => $s->spanNear([
     *     $s->spanTerm('text', 'quick'),
     *     $s->spanTerm('text', 'fox'),
     * ], slop: 3, inOrder: true));
     * ```
     */
    public function span(callable $callback): static
    {
        $spanBuilder = new SpanQueryBuilder;
        $result = $callback($spanBuilder);

        $clause = is_array($result) ? $result : $spanBuilder->build();

        $this->addQueryProtected($clause);

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
     * Enable the Search Profile API for this request.
     *
     * Adds a top-level `profile: true` to the search body, causing
     * Elasticsearch to return detailed timing and execution information
     * for each query, aggregation, and fetch phase under a `profile` key
     * in the response. Useful for diagnosing slow queries.
     *
     * @param  bool  $profile  Whether to enable profiling (default true)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $response = Stretch::index('posts')
     *     ->match('title', 'laravel')
     *     ->profile()
     *     ->execute();
     *
     * $timings = $response['profile'];
     * ```
     */
    public function profile(bool $profile = true): static
    {
        $this->profile = $profile;

        return $this;
    }

    /**
     * Collapse hits by a single-valued field.
     *
     * Returns at most one hit per unique value of `$field`. Typically used
     * to de-duplicate results where many documents share the same identity
     * (e.g. one hit per user, one per parent id). Supports `inner_hits`
     * for retrieving additional hits per collapsed group and a nested
     * `collapse` inside `inner_hits` for a second level of collapsing.
     *
     * Pass a string to collapse on a field with defaults, or an array for
     * full control over the collapse object.
     *
     * @param  string|array  $field  Field name, or a full collapse config array
     * @param  array|null  $innerHits  Optional inner_hits config (object or list of objects)
     * @param  int|null  $maxConcurrentGroupSearches  Concurrency limit for inner_hits group searches
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * // Simple collapse by field
     * $builder->collapse('user.id');
     *
     * // Collapse with inner_hits for the top 5 most recent per user
     * $builder->collapse('user.id', [
     *     'name' => 'most_recent',
     *     'size' => 5,
     *     'sort' => [['@timestamp' => 'desc']],
     * ], maxConcurrentGroupSearches: 4);
     *
     * // Full control — pass the entire collapse object
     * $builder->collapse([
     *     'field' => 'geo.country_name',
     *     'inner_hits' => [
     *         'name' => 'by_location',
     *         'collapse' => ['field' => 'user.id'],
     *         'size' => 3,
     *     ],
     * ]);
     * ```
     */
    public function collapse(string|array $field, ?array $innerHits = null, ?int $maxConcurrentGroupSearches = null): static
    {
        if (is_array($field)) {
            $this->collapse = $field;

            return $this;
        }

        $collapse = ['field' => $field];

        if ($innerHits !== null) {
            $collapse['inner_hits'] = $innerHits;
        }

        if ($maxConcurrentGroupSearches !== null) {
            $collapse['max_concurrent_group_searches'] = $maxConcurrentGroupSearches;
        }

        $this->collapse = $collapse;

        return $this;
    }

    /**
     * Set the search_after cursor for deep pagination.
     *
     * Pass the `sort` values of the last hit from the previous page to fetch
     * the next page. Requires a deterministic `sort` (include a tiebreaker such
     * as `_shard_doc` or `_id`). Unlike from/size, search_after has no 10,000
     * result ceiling. `from` must be 0 (or unset) when using search_after.
     *
     * @param  array  $sortValues  The sort values of the last hit on the prior page
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $page1 = Stretch::index('posts')
     *     ->sort('created_at', 'desc')->sort('_id', 'asc')
     *     ->size(100)->execute();
     *
     * $last = end($page1['hits']['hits']);
     *
     * $page2 = Stretch::index('posts')
     *     ->sort('created_at', 'desc')->sort('_id', 'asc')
     *     ->size(100)->searchAfter($last['sort'])->execute();
     * ```
     */
    public function searchAfter(array $sortValues): static
    {
        $this->searchAfter = $sortValues;

        return $this;
    }

    /**
     * Attach a point-in-time (PIT) to the search for consistent deep paging.
     *
     * A PIT freezes the index state so a search_after walk sees a stable view.
     * When a PIT is set, no `index` is sent in the request (the PIT identifies
     * the target). Open a PIT via `Stretch::openPointInTime()` and close it
     * with `Stretch::closePointInTime()` when done.
     *
     * @param  string  $id  The PIT id returned from openPointInTime()
     * @param  string|null  $keepAlive  Extension of the PIT lifetime (e.g. '1m')
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $pit = Stretch::openPointInTime('posts', '1m')['id'];
     *
     * Stretch::query()
     *     ->pointInTime($pit, '1m')
     *     ->sort('_shard_doc', 'asc')
     *     ->size(100)->execute();
     * ```
     */
    public function pointInTime(string $id, ?string $keepAlive = null): static
    {
        $this->pit = ['id' => $id];

        if ($keepAlive !== null) {
            $this->pit['keep_alive'] = $keepAlive;
        }

        return $this;
    }

    /**
     * Set the minimum _score a hit must reach to be returned.
     *
     * @param  float  $minScore  The score threshold
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->match('title', 'laravel')->minScore(1.5);
     * ```
     */
    public function minScore(float $minScore): static
    {
        $this->minScore = $minScore;

        return $this;
    }

    /**
     * Return a scoring explanation for each hit.
     *
     * Adds `explain: true` to the body; each hit gains an `_explanation`
     * detailing how its score was computed. Useful for relevance debugging.
     *
     * @param  bool  $explain  Whether to enable explanations (default true)
     * @return static Returns the builder instance for method chaining
     */
    public function explain(bool $explain = true): static
    {
        $this->explain = $explain;

        return $this;
    }

    /**
     * Stop collecting after N matching documents per shard.
     *
     * Early-terminates the query once `terminate_after` documents are collected
     * on each shard. Handy for cheap existence checks or capped counts. The
     * response's `terminated_early` flag indicates whether it triggered.
     *
     * @param  int  $count  Maximum documents to collect per shard
     * @return static Returns the builder instance for method chaining
     */
    public function terminateAfter(int $count): static
    {
        $this->terminateAfter = $count;

        return $this;
    }

    /**
     * Set the search_type request parameter.
     *
     * @param  string  $searchType  e.g. 'query_then_fetch' or 'dfs_query_then_fetch'
     * @return static Returns the builder instance for method chaining
     */
    public function searchType(string $searchType): static
    {
        $this->searchType = $searchType;

        return $this;
    }

    /**
     * Set the shard preference request parameter.
     *
     * Controls which shard copies handle the request. Passing a stable string
     * (e.g. a user/session id) yields consistent scoring across requests.
     *
     * @param  string  $preference  The preference value (e.g. '_local', a custom string)
     * @return static Returns the builder instance for method chaining
     */
    public function preference(string $preference): static
    {
        $this->preference = $preference;

        return $this;
    }

    /**
     * Set the routing request parameter to target specific shards.
     *
     * @param  string|array  $routing  One or more routing values
     * @return static Returns the builder instance for method chaining
     */
    public function routing(string|array $routing): static
    {
        $this->routing = $routing;

        return $this;
    }

    /**
     * Add a rescore clause to re-score the top window of results.
     *
     * Rescoring runs a secondary, typically more expensive query over just the
     * top `window_size` hits from each shard to refine ordering without paying
     * the cost across the whole result set. Call multiple times to chain
     * rescorers. The callback builds the rescore query; $options set
     * window_size and the query/rescore weights.
     *
     * @param  callable  $callback  Callback building the rescore query
     * @param  int|null  $windowSize  Number of top hits per shard to rescore
     * @param  array  $options  Extra query_rescorer options (query_weight, rescore_query_weight, score_mode)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->match('title', 'laravel')
     *     ->rescore(fn ($q) => $q->matchPhrase('title', 'laravel framework'), windowSize: 50, options: [
     *         'query_weight' => 0.7,
     *         'rescore_query_weight' => 1.2,
     *     ]);
     * ```
     */
    public function rescore(callable $callback, ?int $windowSize = null, array $options = []): static
    {
        $inner = new ElasticsearchQueryBuilder($this->client, $this->manager);
        $callback($inner);

        $rescoreQuery = array_merge([
            'rescore_query' => $inner->build()['query'] ?? ['match_all' => (object) []],
        ], $options);

        $clause = ['query' => $rescoreQuery];

        if ($windowSize !== null) {
            $clause['window_size'] = $windowSize;
        }

        $this->rescore[] = $clause;

        return $this;
    }

    /**
     * Define runtime fields evaluated at query time.
     *
     * Runtime fields are computed per-hit from a script without reindexing.
     * They can be referenced in queries, sorts, aggregations, and `fields`.
     *
     * @param  array  $mappings  Map of field name to runtime field definition
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->runtimeMappings([
     *     'day_of_week' => [
     *         'type' => 'keyword',
     *         'script' => ['source' => "emit(doc['@timestamp'].value.dayOfWeekEnum.toString())"],
     *     ],
     * ]);
     * ```
     */
    public function runtimeMappings(array $mappings): static
    {
        $this->runtimeMappings = $mappings;

        return $this;
    }

    /**
     * Request specific fields (formatted) in the response.
     *
     * Uses the `fields` parameter, which returns values formatted per the
     * mapping (including runtime and multi-fields), independent of `_source`.
     * Each entry may be a field name string or a `['field' => ..., 'format' => ...]`
     * array.
     *
     * @param  array  $fields  Field names or field/format definitions
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->fields(['title', ['field' => 'created_at', 'format' => 'yyyy-MM-dd']]);
     * ```
     */
    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * Request doc-value fields in the response.
     *
     * @param  array  $fields  Field names or field/format definitions
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->docvalueFields(['created_at', ['field' => 'price', 'format' => '#.0']]);
     * ```
     */
    public function docvalueFields(array $fields): static
    {
        $this->docvalueFields = $fields;

        return $this;
    }

    /**
     * Add suggesters to the request for autocomplete / did-you-mean.
     *
     * The callback receives a SuggestBuilder exposing `term()`, `phrase()`,
     * and `completion()` suggesters. Suggestions are returned under the
     * `suggest` key of the response.
     *
     * @param  callable  $callback  Callback receiving a SuggestBuilder
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $response = Stretch::index('posts')
     *     ->suggest(function ($s) {
     *         $s->term('spellcheck', 'title', 'laravle');
     *         $s->completion('autocomplete', 'title_suggest', 'lara');
     *     })
     *     ->size(0)
     *     ->execute();
     *
     * $suggestions = $response['suggest'];
     * ```
     */
    public function suggest(callable $callback): static
    {
        $builder = new SuggestBuilder;
        $callback($builder);
        $this->suggest = $builder->build();

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
     * When the callback adds no query clauses, no filter is added.
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

        $query = $filterQuery->build()['query'] ?? null;

        if ($query !== null) {
            $this->filters[] = $query;
        }

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

        $query = $postFilterQuery->build()['query'] ?? null;

        if ($query !== null) {
            $this->postFilters[] = $query;
        }

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

            if (! empty($this->postFilters)) {
                $body['post_filter'] = $this->buildPostFilter();
            }

            if ($this->collapse !== null) {
                $body['collapse'] = $this->collapse;
            }

            $this->applyCommonBodyParams($body);

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

        if (! empty($this->postFilters)) {
            $body['post_filter'] = $this->buildPostFilter();
        }

        if ($this->collapse !== null) {
            $body['collapse'] = $this->collapse;
        }

        $this->applyCommonBodyParams($body);

        return $body;
    }

    /**
     * Append body parameters shared by the retriever and standard branches.
     *
     * Covers track_total_hits, profile, and the search-body tuning parameters
     * (search_after, PIT, min_score, explain, terminate_after, rescore,
     * runtime_mappings, fields, docvalue_fields, suggest).
     *
     * @param  array  $body  The body being assembled, mutated in place
     */
    protected function applyCommonBodyParams(array &$body): void
    {
        if ($this->trackTotalHits !== null) {
            $body['track_total_hits'] = $this->trackTotalHits;
        }

        if ($this->profile !== null) {
            $body['profile'] = $this->profile;
        }

        if ($this->searchAfter !== null) {
            $body['search_after'] = $this->searchAfter;
        }

        if ($this->pit !== null) {
            $body['pit'] = $this->pit;
        }

        if ($this->minScore !== null) {
            $body['min_score'] = $this->minScore;
        }

        if ($this->explain !== null) {
            $body['explain'] = $this->explain;
        }

        if ($this->terminateAfter !== null) {
            $body['terminate_after'] = $this->terminateAfter;
        }

        if (! empty($this->rescore)) {
            $body['rescore'] = count($this->rescore) === 1 ? $this->rescore[0] : $this->rescore;
        }

        if ($this->runtimeMappings !== null) {
            $body['runtime_mappings'] = $this->runtimeMappings;
        }

        if (! empty($this->fields)) {
            $body['fields'] = $this->fields;
        }

        if (! empty($this->docvalueFields)) {
            $body['docvalue_fields'] = $this->docvalueFields;
        }

        if ($this->suggest !== null) {
            $body['suggest'] = $this->suggest;
        }
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
     * When caching is enabled (via the IsCacheable trait), the response is
     * served from and stored in the configured cache store.
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

        // A point-in-time identifies the target indices; sending `index`
        // alongside a PIT is rejected by Elasticsearch.
        if ($this->getIndex() && $this->pit === null) {
            $params['index'] = $this->getIndex();
        }

        if (! empty($body)) {
            $params['body'] = $body;
        }

        $this->applyRequestParams($params);

        $this->lastQuery = $params;

        return $this->executeWithCache(fn (): array => $this->client->search($params));
    }

    /**
     * Append top-level request parameters (not part of the search body).
     *
     * search_type, preference, and routing are query-string parameters on the
     * search request rather than body fields.
     *
     * @param  array  $params  The request params being assembled, mutated in place
     */
    protected function applyRequestParams(array &$params): void
    {
        if ($this->searchType !== null) {
            $params['search_type'] = $this->searchType;
        }

        if ($this->preference !== null) {
            $params['preference'] = $this->preference;
        }

        if ($this->routing !== null) {
            $params['routing'] = is_array($this->routing)
                ? implode(',', $this->routing)
                : $this->routing;
        }
    }

    /**
     * Return the number of documents matching the query via the _count API.
     *
     * Cheaper than a full search when only the total is needed — no hits are
     * fetched, and search-only concerns (size, sort, aggregations) are ignored.
     *
     * @return int The matching document count
     *
     * @throws \RuntimeException If the client is not set
     * @throws StretchException If no index is set or the operation fails
     */
    public function count(): int
    {
        if (! $this->client) {
            throw new \RuntimeException('Client not set. Cannot execute query.');
        }

        if (! $this->getIndex()) {
            throw new StretchException('Index required. Cannot count without an index.');
        }

        $body = $this->build();
        $query = $body['query'] ?? ['match_all' => (object) []];

        $response = $this->client->count([
            'index' => $this->getIndex(),
            'body' => ['query' => $query],
        ]);

        return (int) ($response['count'] ?? 0);
    }

    /**
     * Delete all documents matching the built query.
     *
     * Uses the delete-by-query API. When no query clauses are set, all
     * documents in the index are deleted via match_all.
     *
     * @return array The delete-by-query response
     *
     * @throws \RuntimeException If the client is not set
     * @throws StretchException If no index is set or the operation fails
     */
    public function delete(): array
    {
        if (! $this->client) {
            throw new \RuntimeException('Client not set. Cannot execute query.');
        }

        if (! $this->getIndex()) {
            throw new StretchException('Index required. Cannot delete by query without an index.');
        }

        $body = $this->build();
        $query = $body['query'] ?? ['match_all' => (object) []];

        return $this->client->deleteByQuery([
            'index' => $this->getIndex(),
            'body' => ['query' => $query],
        ]);
    }

    /**
     * Get the parameters sent to Elasticsearch on the most recent execute().
     *
     * Returns the exact `['index' => ..., 'body' => ...]` payload that was
     * last dispatched to the client, or null if execute() has not yet run
     * on this builder instance. Useful for debugging, logging, and for
     * replaying or inspecting the final query after caching or retriever
     * rewriting has been applied.
     *
     * @return array|null The last executed query parameters, or null if never executed
     *
     * @example
     * ```php
     * $builder = Stretch::index('posts')->match('title', 'Laravel');
     * $builder->execute();
     * $sent = $builder->getLastQuery();
     * // $sent === ['index' => 'posts', 'body' => ['query' => [...], 'size' => ..., 'from' => 0]]
     * ```
     */
    public function getLastQuery(): ?array
    {
        return $this->lastQuery;
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
     * @return int The index of the added clause, usable with replaceQuery()
     */
    public function addQuery(array $query): int
    {
        $this->query[] = $query;

        return array_key_last($this->query);
    }

    /**
     * Replace a previously added query clause by its index.
     *
     * Used by RangeQueryBuilder when chaining multiple conditions
     * (e.g., gte()->lte()) so each sub-builder updates its own clause,
     * even when multiple builders for the same field are interleaved.
     *
     * @param  int  $index  The clause index returned by addQuery()
     * @param  array  $query  The replacement query clause
     */
    public function replaceQuery(int $index, array $query): void
    {
        if (array_key_exists($index, $this->query)) {
            $this->query[$index] = $query;
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
     * Return the raw accumulated query clauses (unwrapped).
     *
     * Unlike build(), this returns the individual clauses exactly as added,
     * without the bool.must wrapping applied for multi-clause queries. Used by
     * compound queries (e.g. dis_max) that need the clause list directly.
     *
     * @return array<int, array> The raw query clauses
     */
    public function getQueryClauses(): array
    {
        return $this->query;
    }

    /**
     * Return the query's index
     */
    public function getIndex(): string|array|null
    {
        return $this->index;
    }

    /**
     * Return the query's size.
     *
     * The size is clamped to `stretch.query.max_size`. When the Laravel
     * config repository is unavailable (e.g. the builder is used outside a
     * booted application), defaults of 10 (size) and 10,000 (max) apply.
     */
    public function getSize(): int
    {
        $default = $this->getConfigInt('stretch.query.default_size', 10);
        $max = $this->getConfigInt('stretch.query.max_size', 10000);

        return min($this->size ?? $default, $max);
    }

    /**
     * Read an integer config value, falling back to the given default when
     * the config repository is not bound (e.g. outside a booted app).
     */
    protected function getConfigInt(string $key, int $default): int
    {
        if (! function_exists('config') || ! Container::getInstance()->bound('config')) {
            return $default;
        }

        return (int) (config($key) ?? $default);
    }

    /**
     * Return the query from
     */
    public function getFrom(): int
    {
        return $this->from ?? 0;
    }
}

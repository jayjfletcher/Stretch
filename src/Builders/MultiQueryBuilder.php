<?php

declare(strict_types=1);

namespace JayI\Stretch\Builders;

use Illuminate\Support\Arr;
use JayI\Stretch\Builders\Concerns\IsCacheable;
use JayI\Stretch\Client\ElasticsearchClient;
use JayI\Stretch\Contracts\ClientContract;
use JayI\Stretch\Contracts\MultiQueryBuilderContract;
use JayI\Stretch\Contracts\QueryBuilderContract;
use JayI\Stretch\ElasticsearchManager;
use JayI\Stretch\Exceptions\StretchException;

/**
 * MultiQueryBuilder provides a fluent interface for building Elasticsearch multi-search requests.
 *
 * This class allows you to combine multiple search queries into a single request,
 * reducing network overhead when you need to execute several searches at once.
 *
 * @phpstan-consistent-constructor
 */
class MultiQueryBuilder implements MultiQueryBuilderContract
{
    use IsCacheable;

    /**
     * The queries to be executed in the multi-search request.
     * Each entry contains 'index' and 'query' keys.
     *
     * @var array<int, array{index: string|array, query: QueryBuilderContract}>
     */
    protected array $queries = [];

    /**
     * The parameters sent to the client on the most recent execute() call.
     *
     * Contains the `body` array exactly as it was passed to `_msearch`.
     * Null until the first execute() on this builder instance.
     *
     * @var array|null
     */
    protected ?array $lastQuery = null;

    /**
     * Create a new MultiQueryBuilder instance.
     *
     * @param  ClientContract|null  $client  The Elasticsearch client for query execution
     * @param  ElasticsearchManager|null  $manager  The connection manager for multi-connection support
     */
    public function __construct(
        protected ?ClientContract $client = null,
        protected ?ElasticsearchManager $manager = null
    ) {}

    /**
     * Switch to a specific Elasticsearch connection.
     *
     * @param  string  $name  The connection name as defined in configuration
     * @return static A new multi-query builder instance using the specified connection
     *
     * @throws \RuntimeException If the manager is not available
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
     * Add a query to the multi-search request.
     *
     * Each query can target different indices and have its own search criteria.
     * Queries are executed in parallel by Elasticsearch.
     *
     * @param  callable|QueryBuilderContract  $query  A callback or query builder instance
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->add('posts', fn($q) => $q->match('title', 'Laravel'))
     *         ->add('comments', fn($q) => $q->match('content', 'great'))
     *         ->add(['users', 'profiles'], fn($q) => $q->term('active', true));
     * ```
     */
    public function add(string $name, callable|QueryBuilderContract $query): static
    {
        if (is_callable($query)) {
            $builder = new ElasticsearchQueryBuilder($this->client, $this->manager);
            $query($builder);
            $query = $builder;
        }

        $this->queries[$name] = [
            'index' => $query->getIndex(),
            'query' => $query,
        ];

        return $this;
    }

    /**
     * Build the multi-search request body.
     *
     * Creates the alternating header/body format required by Elasticsearch's
     * _msearch endpoint. Each query produces two array entries: a header with
     * index information, and a body with the query itself.
     *
     * @return array The msearch body array with alternating header/body entries
     */
    public function build(): array
    {
        $body = [];

        $queries = collect($this->queries)->sortKeys()->toArray();

        foreach ($queries as $entry) {
            // Header line - specifies the index
            $header = [];
            if (is_array($entry['index'])) {
                $header['index'] = implode(',', $entry['index']);
            } else {
                $header['index'] = $entry['index'];
            }

            $body[] = $header;

            // Body line - the query
            $body[] = $entry['query']->build();
        }

        return $body;
    }

    /**
     * Execute the multi-search request.
     *
     * Sends all queries to Elasticsearch in a single request and returns
     * all results. If caching is enabled, results may be cached.
     *
     * @return array The msearch response with 'responses' array containing each query's result
     *
     * @throws \RuntimeException If the client is not set
     * @throws StretchException If the multi-search fails
     *
     * @example
     * ```php
     * $results = Stretch::multi()
     *     ->add('posts', fn($q) => $q->match('title', 'Laravel'))
     *     ->add('users', fn($q) => $q->term('active', true))
     *     ->execute();
     *
     * $postsHits = $results['responses']['posts']['hits']['hits'];
     * $usersHits = $results['responses']['users']['hits']['hits'];
     * ```
     */
    public function execute(): array
    {
        if (! $this->client) {
            throw new \RuntimeException('Client not set. Cannot execute query.');
        }

        if (empty($this->queries)) {
            $this->lastQuery = ['body' => []];

            return ['responses' => []];
        }

        $params = ['body' => $this->build()];
        $this->lastQuery = $params;

        $results = $this->client->msearch($params);

        $key = -1;
        $results['responses'] = collect($this->queries)->sortKeys()->map(function () use (&$results, &$key) {
            $key++;

            return Arr::get($results, "responses.{$key}");
        })->toArray();

        return $results;

    }

    /**
     * Get the multi-search request as an array for debugging.
     *
     * Alias for build() - useful for inspecting the request structure.
     *
     * @return array The msearch body array
     */
    public function toArray(): array
    {
        return $this->build();
    }

    /**
     * Get the number of queries in the multi-search request.
     */
    public function count(): int
    {
        return count($this->queries);
    }

    /**
     * Get the parameters sent to Elasticsearch on the most recent execute().
     *
     * Returns the `['body' => ...]` payload last dispatched to `_msearch`,
     * or null if execute() has not yet run on this builder instance.
     *
     * @return array|null The last executed msearch parameters, or null if never executed
     *
     * @example
     * ```php
     * $multi = Stretch::multi()
     *     ->add('posts', fn ($q) => $q->index('posts')->match('title', 'Laravel'))
     *     ->add('users', fn ($q) => $q->index('users')->term('active', true));
     * $multi->execute();
     * $sent = $multi->getLastQuery();
     * // $sent['body'] is the alternating header/body array sent to _msearch
     * ```
     */
    public function getLastQuery(): ?array
    {
        return $this->lastQuery;
    }
}

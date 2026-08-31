<?php

declare(strict_types=1);

namespace JayI\Stretch;

use JayI\Stretch\Builders\Concerns\SwitchesConnections;
use JayI\Stretch\Builders\ElasticsearchQueryBuilder;
use JayI\Stretch\Builders\MultiQueryBuilder;
use JayI\Stretch\Builders\ScrollBuilder;
use JayI\Stretch\Contracts\ClientContract;
use JayI\Stretch\Contracts\MultiQueryBuilderContract;
use JayI\Stretch\Contracts\QueryBuilderContract;
use JayI\Stretch\Exceptions\StretchException;

/**
 * Stretch - Laravel Elasticsearch Query Builder
 *
 * The main entry point for building and executing Elasticsearch queries.
 * Provides fluent API for query building, index management, and multi-connection support.
 *
 * @phpstan-consistent-constructor
 */
class Stretch
{
    use SwitchesConnections;

    /**
     * Create a new Stretch instance.
     *
     * @param  ClientContract  $client  The Elasticsearch client instance
     * @param  ElasticsearchManager|null  $manager  The connection manager for multi-connection support
     */
    public function __construct(
        protected ClientContract $client,
        protected ?ElasticsearchManager $manager = null
    ) {}

    /**
     * Create a new query builder instance.
     *
     * Returns a query builder configured with the current client and manager.
     *
     * @return QueryBuilderContract A new query builder instance
     */
    public function query(): QueryBuilderContract
    {
        return $this->propagateConnectionTo(
            new ElasticsearchQueryBuilder($this->client, $this->manager)
        );
    }

    /**
     * Create a new query builder for a specific index.
     *
     * Shortcut method that creates a query builder and sets the index in one call.
     *
     * @param  string|array  $index  The index name or array of index names to search
     * @return QueryBuilderContract A new query builder configured for the specified index(es)
     *
     * @example
     * ```php
     * // Single index
     * Stretch::index('posts')->match('title', 'Laravel')->execute();
     *
     * // Multiple indices
     * Stretch::index(['posts', 'comments'])->match('content', 'search term')->execute();
     * ```
     */
    public function index(string|array $index): QueryBuilderContract
    {
        return $this->query()->index($index);
    }

    /**
     * Create a new multi-query builder for executing multiple searches in a single request.
     *
     * When a sub-query does not set an index explicitly, its result name is
     * used as the index (see MultiQueryBuilder::add()).
     *
     * @return MultiQueryBuilderContract A new multi-query builder instance
     *
     * @example
     * ```php
     * // The result names ('posts', 'users') double as the index names here
     * $results = Stretch::multi()
     *     ->add('posts', fn ($q) => $q->match('title', 'Laravel'))
     *     ->add('users', fn ($q) => $q->term('status', 'active'))
     *     ->execute();
     * ```
     */
    public function multi(): MultiQueryBuilderContract
    {
        return $this->propagateConnectionTo(
            new MultiQueryBuilder($this->client, $this->manager)
        );
    }

    /**
     * Create a ScrollBuilder for streaming an entire result set via the Scroll API.
     *
     * The returned builder exposes the full query DSL plus `batches()` and
     * `cursor()` generators. Best suited to exports and bulk reprocessing;
     * prefer point-in-time + search_after for user-facing deep pagination.
     *
     * @param  string|array  $index  The index (or indices) to scroll
     * @param  string  $keepAlive  How long each scroll batch stays alive (e.g. '1m')
     * @return ScrollBuilder A scroll-capable query builder
     *
     * @example
     * ```php
     * foreach (Stretch::scroll('posts')->term('status', 'published')->cursor() as $hit) {
     *     // process each document
     * }
     * ```
     */
    public function scroll(string|array $index, string $keepAlive = '1m'): ScrollBuilder
    {
        $builder = $this->propagateConnectionTo(
            new ScrollBuilder($this->client, $this->manager)
        );
        $builder->index($index)->keepAlive($keepAlive);

        return $builder;
    }

    /**
     * Get the underlying Elasticsearch client.
     *
     * Provides direct access to the client for advanced operations
     * not covered by the query builder interface.
     *
     * @return ClientContract The Elasticsearch client instance
     */
    public function client(): ClientContract
    {
        return $this->client;
    }

    /**
     * Check if an index exists.
     *
     * @param  string  $index  The index name to check
     * @return bool True if the index exists, false otherwise
     */
    public function indexExists(string $index): bool
    {
        return $this->client->indexExists($index);
    }

    /**
     * Create a new Elasticsearch index.
     *
     * @param  string  $index  The name of the index to create
     * @param  array  $settings  Optional index settings and mappings
     * @return array The Elasticsearch response
     *
     * @throws StretchException If index creation fails
     *
     * @example
     * ```php
     * Stretch::createIndex('posts', [
     *     'settings' => ['number_of_shards' => 1],
     *     'mappings' => ['properties' => ['title' => ['type' => 'text']]]
     * ]);
     * ```
     */
    public function createIndex(string $index, array $settings = []): array
    {
        return $this->client->createIndex($index, $settings);
    }

    /**
     * Delete an Elasticsearch index.
     *
     * Warning: This operation is irreversible and will delete all data in the index.
     *
     * @param  string  $index  The name of the index to delete
     * @return array The Elasticsearch response
     *
     * @throws StretchException If index deletion fails
     */
    public function deleteIndex(string $index): array
    {
        return $this->client->deleteIndex($index);
    }

    /**
     * Get Elasticsearch cluster health information.
     *
     * Returns cluster status (green, yellow, red), node counts,
     * and other health metrics.
     *
     * @return array The cluster health response
     *
     * @throws StretchException If health check fails
     */
    public function health(): array
    {
        return $this->client->health();
    }

    /**
     * Get information about all Elasticsearch indices.
     *
     * Returns an array of all indices with their settings and mappings.
     *
     * @return array Array of index information
     *
     * @throws StretchException If retrieving indices fails
     */
    public function indices(): array
    {
        return $this->client->indices();
    }

    /**
     * Get the mapping for a single index.
     *
     * @param  string  $index  The index name
     * @return array The mapping response keyed by index name
     *
     * @throws StretchException If retrieving the mapping fails
     */
    public function getMapping(string $index): array
    {
        return $this->client->getMapping($index);
    }

    /**
     * Perform bulk index, update, or delete operations.
     *
     * Efficiently execute multiple operations in a single request.
     *
     * @param  array  $operations  Array of bulk operations (action/metadata and source pairs)
     * @return array The bulk response with results for each operation
     *
     * @throws StretchException If bulk operation fails
     *
     * @example
     * ```php
     * Stretch::bulk([
     *     ['index' => ['_index' => 'posts', '_id' => '1']],
     *     ['title' => 'First Post', 'content' => 'Hello World'],
     *     ['index' => ['_index' => 'posts', '_id' => '2']],
     *     ['title' => 'Second Post', 'content' => 'Another post'],
     * ]);
     * ```
     */
    public function bulk(array $operations): array
    {
        return $this->client->bulk(['body' => $operations]);
    }

    /**
     * Index (create or update) a document.
     *
     * If an ID is provided and a document with that ID exists, it will be updated.
     * If no ID is provided, Elasticsearch will generate one automatically.
     *
     * @param  string  $index  The index to store the document in
     * @param  array  $document  The document data to index
     * @param  string|null  $id  Optional document ID (auto-generated if not provided)
     * @return array The index response including the document ID and version
     *
     * @throws StretchException If indexing fails
     *
     * @example
     * ```php
     * // With auto-generated ID
     * Stretch::indexDocument('posts', ['title' => 'My Post', 'content' => 'Hello']);
     *
     * // With specific ID
     * Stretch::indexDocument('posts', ['title' => 'My Post'], 'post-123');
     * ```
     */
    public function indexDocument(string $index, array $document, ?string $id = null): array
    {
        $params = [
            'index' => $index,
            'body' => $document,
        ];

        if ($id) {
            $params['id'] = $id;
        }

        return $this->client->index($params);
    }

    /**
     * Partially update a document.
     *
     * Updates only the specified fields without replacing the entire document.
     *
     * @param  string  $index  The index containing the document
     * @param  string  $id  The document ID to update
     * @param  array  $document  The fields to update
     * @return array The update response including the new version
     *
     * @throws StretchException If update fails
     *
     * @example
     * ```php
     * Stretch::updateDocument('posts', 'post-123', ['title' => 'Updated Title']);
     * ```
     */
    public function updateDocument(string $index, string $id, array $document): array
    {
        return $this->client->update([
            'index' => $index,
            'id' => $id,
            'body' => [
                'doc' => $document,
            ],
        ]);
    }

    /**
     * Delete a document by ID.
     *
     * @param  string  $index  The index containing the document
     * @param  string  $id  The document ID to delete
     * @return array The delete response
     *
     * @throws StretchException If deletion fails
     */
    public function deleteDocument(string $index, string $id): array
    {
        return $this->client->delete([
            'index' => $index,
            'id' => $id,
        ]);
    }

    /**
     * Delete documents matching a query.
     *
     * @param  string  $index  The index to delete from
     * @param  callable  $callback  Receives an ElasticsearchQueryBuilder to build the query
     * @return array The delete by query response (deleted, total, failures, etc.)
     *
     * @throws StretchException If the operation fails
     */
    public function deleteByQuery(string $index, callable $callback): array
    {
        $builder = new ElasticsearchQueryBuilder;
        $callback($builder);

        return $this->client->deleteByQuery([
            'index' => $index,
            'body' => ['query' => $builder->build()['query'] ?? ['match_all' => (object) []]],
        ]);
    }

    /**
     * Update documents matching a query via _update_by_query.
     *
     * The callback builds the query; the script (and optional params) apply the
     * update to each matching document. When no script is given, matching
     * documents are simply reindexed (useful to pick up mapping changes).
     *
     * @param  string  $index  The index to update
     * @param  callable  $callback  Receives an ElasticsearchQueryBuilder to build the query
     * @param  array|null  $script  Optional script (e.g. ['source' => "ctx._source.views++"])
     * @param  array  $options  Extra request params (e.g. conflicts, refresh, wait_for_completion)
     * @return array The update-by-query response (updated, total, failures, etc.)
     *
     * @throws StretchException If the operation fails
     *
     * @example
     * ```php
     * Stretch::updateByQuery('posts',
     *     fn ($q) => $q->term('status', 'draft'),
     *     ['source' => "ctx._source.status = 'archived'"],
     * );
     * ```
     */
    public function updateByQuery(string $index, callable $callback, ?array $script = null, array $options = []): array
    {
        $builder = new ElasticsearchQueryBuilder;
        $callback($builder);

        $body = ['query' => $builder->build()['query'] ?? ['match_all' => (object) []]];

        if ($script !== null) {
            $body['script'] = $script;
        }

        return $this->client->updateByQuery(array_merge($options, [
            'index' => $index,
            'body' => $body,
        ]));
    }

    /**
     * Open a point-in-time (PIT) for consistent deep pagination.
     *
     * Pass the returned `id` to `$queryBuilder->pointInTime($id)` and close it
     * with `closePointInTime()` when the walk completes.
     *
     * @param  string  $index  The index (or pattern) to freeze
     * @param  string  $keepAlive  How long to keep the PIT alive (e.g. '1m')
     * @return array The response containing the PIT 'id'
     *
     * @throws StretchException If the operation fails
     */
    public function openPointInTime(string $index, string $keepAlive = '1m'): array
    {
        return $this->client->openPointInTime($index, $keepAlive);
    }

    /**
     * Close a previously opened point-in-time.
     *
     * @param  string  $id  The PIT id to close
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function closePointInTime(string $id): array
    {
        return $this->client->closePointInTime($id);
    }

    /**
     * Analyze text with an analyzer via the _analyze API.
     *
     * Useful for debugging how a field's analyzer tokenises input. Pass either
     * an `analyzer` name or a `field` in $body, along with the `text`.
     *
     * @param  array  $body  The analyze body (text, analyzer/field, tokenizer, filters)
     * @param  string|null  $index  Optional index whose analyzers/fields to use
     * @return array The analyze response containing tokens
     *
     * @throws StretchException If the operation fails
     *
     * @example
     * ```php
     * Stretch::analyze(['analyzer' => 'standard', 'text' => 'The Quick Brown Fox']);
     * Stretch::analyze(['field' => 'title', 'text' => 'Running quickly'], index: 'posts');
     * ```
     */
    public function analyze(array $body, ?string $index = null): array
    {
        $params = ['body' => $body];

        if ($index !== null) {
            $params['index'] = $index;
        }

        return $this->client->analyze($params);
    }

    /**
     * Explain why a document matches (or not) a query via the _explain API.
     *
     * @param  string  $index  The index containing the document
     * @param  string  $id  The document id to explain
     * @param  callable  $callback  Receives an ElasticsearchQueryBuilder to build the query
     * @return array The explain response (matched flag and score explanation)
     *
     * @throws StretchException If the operation fails
     */
    public function explain(string $index, string $id, callable $callback): array
    {
        $builder = new ElasticsearchQueryBuilder;
        $callback($builder);

        return $this->client->explain([
            'index' => $index,
            'id' => $id,
            'body' => ['query' => $builder->build()['query'] ?? ['match_all' => (object) []]],
        ]);
    }

    /**
     * Retrieve term vectors for a document via the _termvectors API.
     *
     * @param  string  $index  The index containing the document
     * @param  string  $id  The document id
     * @param  array  $fields  Fields to return term vectors for
     * @param  array  $options  Extra body options (term_statistics, field_statistics, offsets, etc.)
     * @return array The term-vectors response
     *
     * @throws StretchException If the operation fails
     */
    public function termvectors(string $index, string $id, array $fields = [], array $options = []): array
    {
        $body = $options;

        if (! empty($fields)) {
            $body['fields'] = $fields;
        }

        return $this->client->termvectors([
            'index' => $index,
            'id' => $id,
            'body' => $body,
        ]);
    }

    /**
     * Retrieve a document by ID.
     *
     * @param  string  $index  The index containing the document
     * @param  string  $id  The document ID to retrieve
     * @return array The document including _source, _id, and metadata
     *
     * @throws StretchException If document not found or retrieval fails
     */
    public function getDocument(string $index, string $id): array
    {
        return $this->client->get([
            'index' => $index,
            'id' => $id,
        ]);
    }

    /**
     * Create or update a synonym set.
     *
     * @param  string  $id  The synonym set id
     * @param  array  $synonymsSet  The synonyms set rules. Each entry is an array like
     *                              ['id' => 'rule-1', 'synonyms' => 'foo, bar']
     * @param  array  $options  Optional extra parameters (e.g. ['refresh' => true])
     * @return array The Elasticsearch response
     *
     * @throws StretchException If the operation fails
     *
     * @example
     * ```php
     * Stretch::putSynonym('my-synonyms', [
     *     ['id' => 'rule-1', 'synonyms' => 'hello, hi, hey'],
     *     ['id' => 'rule-2', 'synonyms' => 'goodbye, bye'],
     * ]);
     * ```
     */
    public function putSynonym(string $id, array $synonymsSet, array $options = []): array
    {
        return $this->client->putSynonym($id, $synonymsSet, $options);
    }

    /**
     * Get a synonym set by id.
     *
     * @param  string  $id  The synonym set id
     * @param  array  $options  Optional parameters (e.g. ['from' => 0, 'size' => 10])
     * @return array The Elasticsearch response
     *
     * @throws StretchException If the operation fails
     */
    public function getSynonym(string $id, array $options = []): array
    {
        return $this->client->getSynonym($id, $options);
    }

    /**
     * Delete a synonym set by id.
     *
     * @param  string  $id  The synonym set id
     * @return array The Elasticsearch response
     *
     * @throws StretchException If the operation fails
     */
    public function deleteSynonym(string $id): array
    {
        return $this->client->deleteSynonym($id);
    }

    /**
     * Get all synonym sets.
     *
     * @param  array  $options  Optional parameters (e.g. ['from' => 0, 'size' => 10])
     * @return array The Elasticsearch response
     *
     * @throws StretchException If the operation fails
     */
    public function getSynonymsSets(array $options = []): array
    {
        return $this->client->getSynonymsSets($options);
    }

    /**
     * Create or update a single synonym rule within a synonym set.
     *
     * @param  string  $setId  The synonym set id
     * @param  string  $ruleId  The synonym rule id
     * @param  array  $rule  The rule body (e.g. ['synonyms' => 'foo, bar'])
     * @param  array  $options  Optional extra parameters (e.g. ['refresh' => true])
     * @return array The Elasticsearch response
     *
     * @throws StretchException If the operation fails
     *
     * @example
     * ```php
     * Stretch::putSynonymRule('my-synonyms', 'rule-1', ['synonyms' => 'hello, hi, hey']);
     * ```
     */
    public function putSynonymRule(string $setId, string $ruleId, array $rule, array $options = []): array
    {
        return $this->client->putSynonymRule($setId, $ruleId, $rule, $options);
    }

    /**
     * Get a synonym rule from a synonym set.
     *
     * @param  string  $setId  The synonym set id
     * @param  string  $ruleId  The synonym rule id
     * @return array The Elasticsearch response
     *
     * @throws StretchException If the operation fails
     */
    public function getSynonymRule(string $setId, string $ruleId): array
    {
        return $this->client->getSynonymRule($setId, $ruleId);
    }

    /**
     * Delete a synonym rule from a synonym set.
     *
     * @param  string  $setId  The synonym set id
     * @param  string  $ruleId  The synonym rule id
     * @param  array  $options  Optional extra parameters (e.g. ['refresh' => true])
     * @return array The Elasticsearch response
     *
     * @throws StretchException If the operation fails
     */
    public function deleteSynonymRule(string $setId, string $ruleId, array $options = []): array
    {
        return $this->client->deleteSynonymRule($setId, $ruleId, $options);
    }

    // ── Ingest Pipelines ────────────────────────────────────

    /**
     * Create or update an ingest pipeline.
     *
     * @param  string  $id  The pipeline ID
     * @param  array  $body  Pipeline definition (description, processors, etc.)
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function putPipeline(string $id, array $body): array
    {
        return $this->client->putPipeline($id, $body);
    }

    /**
     * Get an ingest pipeline by ID.
     *
     * @param  string  $id  The pipeline ID
     * @return array The pipeline definition
     *
     * @throws StretchException If the operation fails
     */
    public function getPipeline(string $id): array
    {
        return $this->client->getPipeline($id);
    }

    /**
     * Delete an ingest pipeline.
     *
     * @param  string  $id  The pipeline ID
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function deletePipeline(string $id): array
    {
        return $this->client->deletePipeline($id);
    }

    /**
     * Run a `_field_caps` request for the given fields on an index.
     *
     * @param  list<string>  $fields
     * @return array The raw `_field_caps` response
     */
    public function fieldCaps(string $index, array $fields): array
    {
        return $this->client->fieldCaps($index, $fields);
    }

    // ── Inference Endpoints ─────────────────────────────────

    /**
     * Create or update an inference endpoint.
     *
     * @param  string  $inferenceId  The inference endpoint ID
     * @param  string  $taskType  The task type (e.g. 'text_embedding')
     * @param  array  $body  Endpoint configuration
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function putInferenceEndpoint(string $inferenceId, string $taskType, array $body): array
    {
        return $this->client->putInferenceEndpoint($inferenceId, $taskType, $body);
    }

    /**
     * Get an inference endpoint by ID.
     *
     * @param  string  $inferenceId  The inference endpoint ID
     * @return array The endpoint configuration
     *
     * @throws StretchException If the operation fails
     */
    public function getInferenceEndpoint(string $inferenceId): array
    {
        return $this->client->getInferenceEndpoint($inferenceId);
    }

    /**
     * Delete an inference endpoint.
     *
     * @param  string  $inferenceId  The inference endpoint ID
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function deleteInferenceEndpoint(string $inferenceId): array
    {
        return $this->client->deleteInferenceEndpoint($inferenceId);
    }

    // ── ML / Trained Models ─────────────────────────────────

    /**
     * Get stats for a trained ML model.
     *
     * @param  string  $modelId  The trained model ID
     * @return array The model stats including deployment status
     *
     * @throws StretchException If the operation fails
     */
    public function getTrainedModelStats(string $modelId): array
    {
        return $this->client->getTrainedModelStats($modelId);
    }

    // ── Mapping / Reindex / Aliases / Tasks ─────────────────

    /**
     * Update the mapping of an existing index.
     *
     * Use for additive, non-breaking changes. Breaking changes require a reindex.
     *
     * @param  string  $index  The index name
     * @param  array  $mapping  Mapping body (e.g. ['properties' => [...]])
     * @return array The Elasticsearch response
     *
     * @throws StretchException If the operation fails
     *
     * @example
     * ```php
     * Stretch::putMapping('posts', ['properties' => ['new_field' => ['type' => 'keyword']]]);
     * ```
     */
    public function putMapping(string $index, array $mapping): array
    {
        return $this->client->putMapping($index, $mapping);
    }

    /**
     * Copy documents from one index to another via _reindex. Defaults to async.
     *
     * @param  string  $source  Source index name
     * @param  string  $dest  Destination index name
     * @param  array{wait_for_completion?: bool, body_extras?: array}  $options
     * @return array The Elasticsearch response (contains 'task' key when async)
     *
     * @throws StretchException If the operation fails
     *
     * @example
     * ```php
     * $response = Stretch::reindex('posts_v1', 'posts_v2');
     * $taskId = $response['task'];
     * ```
     */
    public function reindex(string $source, string $dest, array $options = []): array
    {
        return $this->client->reindex($source, $dest, $options);
    }

    /**
     * Atomically apply a batch of alias actions.
     *
     * @param  array  $actions  List of alias actions
     * @return array The Elasticsearch response
     *
     * @throws StretchException If the operation fails
     *
     * @example
     * ```php
     * Stretch::updateAliases([
     *     ['remove' => ['index' => 'posts_v1', 'alias' => 'posts']],
     *     ['add' => ['index' => 'posts_v2', 'alias' => 'posts']],
     * ]);
     * ```
     */
    public function updateAliases(array $actions): array
    {
        return $this->client->updateAliases($actions);
    }

    /**
     * Get the status of an async task (e.g. a long-running _reindex).
     *
     * @param  string  $id  The task id
     * @return array The task status response
     *
     * @throws StretchException If the operation fails
     */
    public function getTask(string $id): array
    {
        return $this->client->getTask($id);
    }
}

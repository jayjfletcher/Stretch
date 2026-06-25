<?php

declare(strict_types=1);

namespace JayI\Stretch\Client;

use Elastic\Elasticsearch\Client;
use Exception;
use JayI\Stretch\Contracts\ClientContract;
use JayI\Stretch\Exceptions\StretchException;

/**
 * Wrapper around the official Elasticsearch PHP client.
 *
 * Provides a consistent interface for all Elasticsearch operations with
 * automatic error handling, query logging, and slow query detection.
 * All native client exceptions are wrapped in StretchException.
 */
class ElasticsearchClient implements ClientContract
{
    /**
     * Create a new ElasticsearchClient instance.
     *
     * @param  Client  $client  The native Elasticsearch client
     */
    public function __construct(
        protected Client $client
    ) {}

    /**
     * Execute a search query.
     *
     * @param  array  $params  Search parameters including index and body
     * @return array The search response as an array
     *
     * @throws StretchException If the search operation fails
     */
    public function search(array $params): array
    {
        try {
            $this->logQuery($params);

            $response = $this->client->search($params)->asArray();

            $this->logSlowQuery($params, $response);

            return $response;
        } catch (Exception $exception) {
            throw new StretchException("Search failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Index (create or update) a document.
     *
     * @param  array  $params  Index parameters including index, id, and body
     * @return array The index response as an array
     *
     * @throws StretchException If the index operation fails
     */
    public function index(array $params): array
    {
        try {
            return $this->client->index($params)->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Index operation failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Update a document.
     *
     * @param  array  $params  Update parameters including index, id, and body
     * @return array The update response as an array
     *
     * @throws StretchException If the update operation fails
     */
    public function update(array $params): array
    {
        try {
            return $this->client->update($params)->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Update operation failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Delete a document.
     *
     * @param  array  $params  Delete parameters including index and id
     * @return array The delete response as an array
     *
     * @throws StretchException If the delete operation fails
     */
    public function delete(array $params): array
    {
        try {
            return $this->client->delete($params)->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Delete operation failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    public function deleteByQuery(array $params): array
    {
        try {
            return $this->client->deleteByQuery($params)->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Delete by query failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Execute bulk operations.
     *
     * @param  array  $params  Bulk parameters with body containing operations
     * @return array The bulk response as an array
     *
     * @throws StretchException If the bulk operation fails
     */
    public function bulk(array $params): array
    {
        try {
            return $this->client->bulk($params)->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Bulk operation failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Get all indices.
     *
     * @return array Array of index information
     *
     * @throws StretchException If retrieving indices fails
     */
    public function indices(): array
    {
        try {
            return $this->client->indices()->get(['index' => '*'])->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to get indices: {$exception->getMessage()}", 0, $exception);
        }
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
        try {
            return $this->client->indices()->getMapping(['index' => $index])->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to get mapping for {$index}: {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Check if an index exists.
     *
     * @param  string  $index  The index name to check
     * @return bool True if the index exists, false otherwise
     */
    public function indexExists(string $index): bool
    {
        try {
            return $this->client->indices()->exists(['index' => $index])->asBool();
        } catch (Exception $exception) {
            return false;
        }
    }

    /**
     * Create a new index.
     *
     * @param  string  $index  The index name to create
     * @param  array  $settings  Optional index settings and mappings
     * @return array The create index response
     *
     * @throws StretchException If index creation fails
     */
    public function createIndex(string $index, array $settings = []): array
    {
        try {
            $params = ['index' => $index];
            if (! empty($settings)) {
                $params['body'] = $settings;
            }

            return $this->client->indices()->create($params)->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to create index '{$index}': {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Delete an index.
     *
     * @param  string  $index  The index name to delete
     * @return array The delete index response
     *
     * @throws StretchException If index deletion fails
     */
    public function deleteIndex(string $index): array
    {
        try {
            return $this->client->indices()->delete(['index' => $index])->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to delete index '{$index}': {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Get cluster health information.
     *
     * @return array The cluster health response
     *
     * @throws StretchException If retrieving health fails
     */
    public function health(): array
    {
        try {
            return $this->client->cluster()->health()->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to get cluster health: {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Get a document by ID.
     *
     * @param  array  $params  Get parameters including index and id
     * @return array The document as an array
     *
     * @throws StretchException If the get operation fails
     */
    public function get(array $params): array
    {
        try {
            return $this->client->get($params)->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Get operation failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Execute multiple search queries in a single request.
     *
     * @param  array  $params  Multi-search parameters with body
     * @return array The multi-search response with 'responses' array
     *
     * @throws StretchException If the multi-search operation fails
     */
    public function msearch(array $params): array
    {
        try {
            $this->logQuery($params);

            $response = $this->client->msearch($params)->asArray();

            $this->logSlowQuery($params, $response);

            return $response;
        } catch (Exception $exception) {
            throw new StretchException("Multi-search operation failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Create or update a synonym set.
     *
     * @param  string  $id  The synonym set id
     * @param  array  $synonymsSet  The synonyms set rules
     * @param  array  $options  Optional extra parameters (e.g. refresh)
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function putSynonym(string $id, array $synonymsSet, array $options = []): array
    {
        try {
            $params = array_merge($options, [
                'id' => $id,
                'body' => ['synonyms_set' => $synonymsSet],
            ]);

            return $this->client->synonyms()->putSynonym($params)->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to put synonym set '{$id}': {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Get a synonym set by id.
     *
     * @param  string  $id  The synonym set id
     * @param  array  $options  Optional extra parameters (e.g. from, size)
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function getSynonym(string $id, array $options = []): array
    {
        try {
            $params = array_merge($options, ['id' => $id]);

            return $this->client->synonyms()->getSynonym($params)->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to get synonym set '{$id}': {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Delete a synonym set by id.
     *
     * @param  string  $id  The synonym set id
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function deleteSynonym(string $id): array
    {
        try {
            return $this->client->synonyms()->deleteSynonym(['id' => $id])->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to delete synonym set '{$id}': {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Get all synonym sets.
     *
     * @param  array  $options  Optional parameters (e.g. from, size)
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function getSynonymsSets(array $options = []): array
    {
        try {
            return $this->client->synonyms()->getSynonymsSets($options)->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to get synonym sets: {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Create or update a single synonym rule within a synonym set.
     *
     * @param  string  $setId  The synonym set id
     * @param  string  $ruleId  The synonym rule id
     * @param  array  $rule  The rule body (e.g. ['synonyms' => 'foo, bar'])
     * @param  array  $options  Optional extra parameters (e.g. refresh)
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function putSynonymRule(string $setId, string $ruleId, array $rule, array $options = []): array
    {
        try {
            $params = array_merge($options, [
                'set_id' => $setId,
                'rule_id' => $ruleId,
                'body' => $rule,
            ]);

            return $this->client->synonyms()->putSynonymRule($params)->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to put synonym rule '{$ruleId}' in set '{$setId}': {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Get a synonym rule from a synonym set.
     *
     * @param  string  $setId  The synonym set id
     * @param  string  $ruleId  The synonym rule id
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function getSynonymRule(string $setId, string $ruleId): array
    {
        try {
            return $this->client->synonyms()->getSynonymRule([
                'set_id' => $setId,
                'rule_id' => $ruleId,
            ])->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to get synonym rule '{$ruleId}' from set '{$setId}': {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Delete a synonym rule from a synonym set.
     *
     * @param  string  $setId  The synonym set id
     * @param  string  $ruleId  The synonym rule id
     * @param  array  $options  Optional extra parameters (e.g. refresh)
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function deleteSynonymRule(string $setId, string $ruleId, array $options = []): array
    {
        try {
            $params = array_merge($options, [
                'set_id' => $setId,
                'rule_id' => $ruleId,
            ]);

            return $this->client->synonyms()->deleteSynonymRule($params)->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to delete synonym rule '{$ruleId}' from set '{$setId}': {$exception->getMessage()}", 0, $exception);
        }
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
        try {
            return $this->client->ingest()->putPipeline([
                'id' => $id,
                'body' => $body,
            ])->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to put pipeline '$id': {$exception->getMessage()}", 0, $exception);
        }
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
        try {
            return $this->client->ingest()->getPipeline(['id' => $id])->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to get pipeline '$id': {$exception->getMessage()}", 0, $exception);
        }
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
        try {
            return $this->client->ingest()->deletePipeline(['id' => $id])->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to delete pipeline '$id': {$exception->getMessage()}", 0, $exception);
        }
    }

    public function fieldCaps(string $index, array $fields): array
    {
        try {
            return $this->client->fieldCaps([
                'index' => $index,
                'fields' => implode(',', $fields),
            ])->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to fetch field caps for '$index': {$exception->getMessage()}", 0, $exception);
        }
    }

    // ── Inference Endpoints ─────────────────────────────────

    /**
     * Create or update an inference endpoint.
     *
     * @param  string  $inferenceId  The inference endpoint ID
     * @param  string  $taskType  The task type (e.g. 'text_embedding', 'sparse_embedding')
     * @param  array  $body  Endpoint configuration (service, service_settings, etc.)
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function putInferenceEndpoint(string $inferenceId, string $taskType, array $body): array
    {
        try {
            return $this->client->inference()->put([
                'inference_id' => $inferenceId,
                'task_type' => $taskType,
                'body' => $body,
            ])->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to put inference endpoint '$inferenceId': {$exception->getMessage()}", 0, $exception);
        }
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
        try {
            return $this->client->inference()->get([
                'inference_id' => $inferenceId,
            ])->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to get inference endpoint '$inferenceId': {$exception->getMessage()}", 0, $exception);
        }
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
        try {
            return $this->client->inference()->delete([
                'inference_id' => $inferenceId,
            ])->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to delete inference endpoint '$inferenceId': {$exception->getMessage()}", 0, $exception);
        }
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
        try {
            return $this->client->ml()->getTrainedModelsStats([
                'model_id' => $modelId,
            ])->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to get trained model stats for '$modelId': {$exception->getMessage()}", 0, $exception);
        }
    }

    // ── Mapping / Reindex / Aliases / Tasks ─────────────────

    /**
     * Update the mapping of an existing index.
     *
     * @param  string  $index  The index name
     * @param  array  $mapping  Mapping body (e.g. ['properties' => [...]])
     * @return array The Elasticsearch response
     *
     * @throws StretchException If the operation fails
     */
    public function putMapping(string $index, array $mapping): array
    {
        try {
            return $this->client->indices()->putMapping([
                'index' => $index,
                'body' => $mapping,
            ])->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to put mapping for '{$index}': {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Copy documents from one index to another via the _reindex API.
     *
     * @param  string  $source  Source index name
     * @param  string  $dest  Destination index name
     * @param  array{wait_for_completion?: bool, body_extras?: array}  $options
     * @return array The Elasticsearch response (contains 'task' key when async)
     *
     * @throws StretchException If the operation fails
     */
    public function reindex(string $source, string $dest, array $options = []): array
    {
        try {
            $body = [
                'source' => ['index' => $source],
                'dest' => ['index' => $dest],
            ] + ($options['body_extras'] ?? []);

            return $this->client->reindex([
                'wait_for_completion' => $options['wait_for_completion'] ?? false,
                'body' => $body,
            ])->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to reindex '{$source}' → '{$dest}': {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Atomically apply a batch of alias actions.
     *
     * @param  array  $actions  List of alias actions
     * @return array The Elasticsearch response
     *
     * @throws StretchException If the operation fails
     */
    public function updateAliases(array $actions): array
    {
        try {
            return $this->client->indices()->updateAliases([
                'body' => ['actions' => $actions],
            ])->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to update aliases: {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Get the status of an async task.
     *
     * @param  string  $id  The task id
     * @return array The task status response
     *
     * @throws StretchException If the operation fails
     */
    public function getTask(string $id): array
    {
        try {
            return $this->client->tasks()->get(['task_id' => $id])->asArray();
        } catch (Exception $exception) {
            throw new StretchException("Failed to get task '{$id}': {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * Log a query if query logging is enabled.
     *
     * @param  array  $query  The query to log
     */
    protected function logQuery(array $query): void
    {
        if (config('stretch.logging.log_queries')) {
            $this->log('Elasticsearch query:', $query);
        }
    }

    /**
     * Log a slow query if slow query logging is enabled.
     *
     * Checks if the query execution time exceeds the configured threshold
     * and logs it as a warning if so.
     *
     * @param  array  $query  The query that was executed
     * @param  array  $response  The response containing 'took' time
     */
    protected function logSlowQuery(array $query, array $response): void
    {
        if (config('stretch.logging.log_slow_queries')) {
            $time = $response['took'] ?? 0;
            if ($time > config('stretch.logging.slow_query_threshold')) {
                $this->log('Slow Elasticsearch query:', $query, 'warning');
            }
        }
    }

    /**
     * Log a message if logging is enabled.
     *
     * @param  string  $message  The log message
     * @param  array  $context  Additional context data
     * @param  string  $level  The log level (info, warning, error, etc.)
     */
    protected function log(string $message, array $context = [], string $level = 'info'): void
    {
        if (config('stretch.logging.enabled')) {
            logger()->{$level}($message, $context);
        }
    }
}

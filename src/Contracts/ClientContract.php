<?php

declare(strict_types=1);

namespace JayI\Stretch\Contracts;

use JayI\Stretch\Exceptions\StretchException;

/**
 * Contract for Elasticsearch client implementations.
 *
 * Defines the interface for all Elasticsearch operations including search,
 * document CRUD, bulk operations, and index management. Implementations
 * wrap the native Elasticsearch client and provide consistent error handling.
 */
interface ClientContract
{
    /**
     * Execute a search query.
     *
     * @param  array  $params  Search parameters including index and body
     * @return array The search response as an array
     *
     * @throws StretchException If the search fails
     */
    public function search(array $params): array;

    /**
     * Index (create or update) a document.
     *
     * @param  array  $params  Index parameters including index, id, and body
     * @return array The index response as an array
     *
     * @throws StretchException If the index operation fails
     */
    public function index(array $params): array;

    /**
     * Update a document.
     *
     * @param  array  $params  Update parameters including index, id, and body
     * @return array The update response as an array
     *
     * @throws StretchException If the update fails
     */
    public function update(array $params): array;

    /**
     * Delete a document.
     *
     * @param  array  $params  Delete parameters including index and id
     * @return array The delete response as an array
     *
     * @throws StretchException If the delete fails
     */
    public function delete(array $params): array;

    /**
     * Delete documents matching a query.
     *
     * @param  array  $params  Parameters including index and body with query
     * @return array The delete by query response as an array
     *
     * @throws StretchException If the operation fails
     */
    public function deleteByQuery(array $params): array;

    /**
     * Execute bulk operations.
     *
     * @param  array  $params  Bulk parameters with body containing operations
     * @return array The bulk response as an array
     *
     * @throws StretchException If the bulk operation fails
     */
    public function bulk(array $params): array;

    /**
     * Get information about all indices.
     *
     * @return array Array of index information
     *
     * @throws StretchException If retrieving indices fails
     */
    public function indices(): array;

    /**
     * Get the mapping for a single index.
     *
     * @param  string  $index  The index name
     * @return array The mapping (under `mappings.properties`, matching Elasticsearch's response shape)
     *
     * @throws StretchException If retrieving the mapping fails
     */
    public function getMapping(string $index): array;

    /**
     * Check if an index exists.
     *
     * @param  string  $index  The index name to check
     * @return bool True if the index exists, false otherwise
     */
    public function indexExists(string $index): bool;

    /**
     * Create a new index.
     *
     * @param  string  $index  The index name to create
     * @param  array  $settings  Optional index settings and mappings
     * @return array The create index response
     *
     * @throws StretchException If index creation fails
     */
    public function createIndex(string $index, array $settings = []): array;

    /**
     * Delete an index.
     *
     * @param  string  $index  The index name to delete
     * @return array The delete index response
     *
     * @throws StretchException If index deletion fails
     */
    public function deleteIndex(string $index): array;

    /**
     * Get cluster health information.
     *
     * @return array The cluster health response
     *
     * @throws StretchException If health check fails
     */
    public function health(): array;

    /**
     * Get a document by ID.
     *
     * @param  array  $params  Get parameters including index and id
     * @return array The document as an array
     *
     * @throws StretchException If the get operation fails
     */
    public function get(array $params): array;

    /**
     * Execute multiple search queries in a single request.
     *
     * @param  array  $params  Multi-search parameters with body
     * @return array The multi-search response with 'responses' array
     *
     * @throws StretchException If the multi-search fails
     */
    public function msearch(array $params): array;

    /**
     * Count documents matching a query via the _count API.
     *
     * @param  array  $params  Count parameters (index, body)
     * @return array The count response containing a 'count' key
     *
     * @throws StretchException If the operation fails
     */
    public function count(array $params): array;

    /**
     * Update documents matching a query via the _update_by_query API.
     *
     * @param  array  $params  Update-by-query parameters (index, body)
     * @return array The update-by-query response
     *
     * @throws StretchException If the operation fails
     */
    public function updateByQuery(array $params): array;

    /**
     * Open a point-in-time for consistent deep pagination.
     *
     * @param  string  $index  The index (or pattern) to freeze
     * @param  string  $keepAlive  How long to keep the PIT alive (e.g. '1m')
     * @return array The response containing the PIT 'id'
     *
     * @throws StretchException If the operation fails
     */
    public function openPointInTime(string $index, string $keepAlive): array;

    /**
     * Close a previously opened point-in-time.
     *
     * @param  string  $id  The PIT id to close
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function closePointInTime(string $id): array;

    /**
     * Analyze text with an analyzer via the _analyze API.
     *
     * @param  array  $params  Analyze parameters (index optional, body)
     * @return array The analyze response containing tokens
     *
     * @throws StretchException If the operation fails
     */
    public function analyze(array $params): array;

    /**
     * Explain why a document matches (or not) a query via the _explain API.
     *
     * @param  array  $params  Explain parameters (index, id, body)
     * @return array The explain response
     *
     * @throws StretchException If the operation fails
     */
    public function explain(array $params): array;

    /**
     * Retrieve term vectors for a document via the _termvectors API.
     *
     * @param  array  $params  Term-vectors parameters (index, id, body/fields)
     * @return array The term-vectors response
     *
     * @throws StretchException If the operation fails
     */
    public function termvectors(array $params): array;

    /**
     * Continue a scroll search via the _search/scroll API.
     *
     * @param  array  $params  Scroll parameters (scroll_id, scroll)
     * @return array The next batch of scroll results
     *
     * @throws StretchException If the operation fails
     */
    public function scroll(array $params): array;

    /**
     * Clear one or more scroll contexts.
     *
     * @param  array  $params  Clear-scroll parameters (scroll_id)
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function clearScroll(array $params): array;

    /**
     * Create or update a synonym set.
     *
     * @param  string  $id  The synonym set id
     * @param  array  $synonymsSet  The synonyms set rules (list of synonym entries)
     * @param  array  $options  Optional extra parameters (e.g. refresh)
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function putSynonym(string $id, array $synonymsSet, array $options = []): array;

    /**
     * Get a synonym set by id.
     *
     * @param  string  $id  The synonym set id
     * @param  array  $options  Optional extra parameters (e.g. from, size)
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function getSynonym(string $id, array $options = []): array;

    /**
     * Delete a synonym set by id.
     *
     * @param  string  $id  The synonym set id
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function deleteSynonym(string $id): array;

    /**
     * Get all synonym sets.
     *
     * @param  array  $options  Optional parameters (e.g. from, size)
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function getSynonymsSets(array $options = []): array;

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
    public function putSynonymRule(string $setId, string $ruleId, array $rule, array $options = []): array;

    /**
     * Get a synonym rule from a synonym set.
     *
     * @param  string  $setId  The synonym set id
     * @param  string  $ruleId  The synonym rule id
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function getSynonymRule(string $setId, string $ruleId): array;

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
    public function deleteSynonymRule(string $setId, string $ruleId, array $options = []): array;

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
    public function putPipeline(string $id, array $body): array;

    /**
     * Get an ingest pipeline by ID.
     *
     * @param  string  $id  The pipeline ID
     * @return array The pipeline definition
     *
     * @throws StretchException If the operation fails
     */
    public function getPipeline(string $id): array;

    /**
     * Delete an ingest pipeline.
     *
     * @param  string  $id  The pipeline ID
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function deletePipeline(string $id): array;

    /**
     * Run a `_field_caps` request for the given fields on an index.
     *
     * @param  string  $index  Index or alias name
     * @param  list<string>  $fields  Field patterns (e.g. `["attributes.*"]`)
     * @return array The raw `_field_caps` response
     *
     * @throws StretchException If the operation fails
     */
    public function fieldCaps(string $index, array $fields): array;

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
    public function putInferenceEndpoint(string $inferenceId, string $taskType, array $body): array;

    /**
     * Get an inference endpoint by ID.
     *
     * @param  string  $inferenceId  The inference endpoint ID
     * @return array The endpoint configuration
     *
     * @throws StretchException If the operation fails
     */
    public function getInferenceEndpoint(string $inferenceId): array;

    /**
     * Delete an inference endpoint.
     *
     * @param  string  $inferenceId  The inference endpoint ID
     * @return array The response
     *
     * @throws StretchException If the operation fails
     */
    public function deleteInferenceEndpoint(string $inferenceId): array;

    // ── ML / Trained Models ─────────────────────────────────

    /**
     * Get stats for a trained ML model.
     *
     * @param  string  $modelId  The trained model ID
     * @return array The model stats including deployment status
     *
     * @throws StretchException If the operation fails
     */
    public function getTrainedModelStats(string $modelId): array;

    // ── Mapping / Reindex / Aliases / Tasks ─────────────────

    /**
     * Update the mapping of an existing index.
     *
     * Only supports additive, non-breaking mapping changes. Breaking changes
     * (field type changes, removals, semantic_text additions) require a reindex.
     *
     * @param  string  $index  The index name
     * @param  array  $mapping  Mapping body (e.g. ['properties' => [...]])
     * @return array The Elasticsearch response
     *
     * @throws StretchException If the operation fails
     */
    public function putMapping(string $index, array $mapping): array;

    /**
     * Copy documents from one index into another via the _reindex API.
     *
     * @param  string  $source  Source index name
     * @param  string  $dest  Destination index name
     * @param  array  $options  Supported keys:
     *                          - 'wait_for_completion' (bool, default false) — run async and return a task id
     *                          - 'body_extras' (array) — merged into the reindex request body (e.g. script, conflicts)
     * @return array The Elasticsearch response (contains 'task' key when async)
     *
     * @throws StretchException If the operation fails
     */
    public function reindex(string $source, string $dest, array $options = []): array;

    /**
     * Atomically apply a batch of alias actions.
     *
     * @param  array  $actions  List of actions (e.g. [['add' => ['index' => 'x', 'alias' => 'a']]])
     * @return array The Elasticsearch response
     *
     * @throws StretchException If the operation fails
     */
    public function updateAliases(array $actions): array;

    /**
     * Get the status of an async task (e.g. a long-running _reindex).
     *
     * @param  string  $id  The task id returned by the async operation
     * @return array The task status response
     *
     * @throws StretchException If the operation fails
     */
    public function getTask(string $id): array;
}

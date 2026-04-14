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
}

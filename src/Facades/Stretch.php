<?php

declare(strict_types=1);

namespace JayI\Stretch\Facades;

use Illuminate\Support\Facades\Facade;
use JayI\Stretch\Contracts\ClientContract;
use JayI\Stretch\Contracts\MultiQueryBuilderContract;
use JayI\Stretch\Contracts\QueryBuilderContract;

/**
 * Facade for the Stretch Elasticsearch query builder.
 *
 * Provides static access to all Stretch functionality including query building,
 * index management, document operations, and multi-connection support.
 *
 * @method static QueryBuilderContract query() Create a new query builder instance
 * @method static QueryBuilderContract index(string|array $index) Create a query builder for specific index(es)
 * @method static MultiQueryBuilderContract multi() Create a multi-query builder for batch searches
 * @method static \JayI\Stretch\Stretch connection(string $name) Switch to a named connection
 * @method static ClientContract client() Get the underlying Elasticsearch client
 * @method static bool indexExists(string $index) Check if an index exists
 * @method static array createIndex(string $index, array $settings = []) Create a new index
 * @method static array deleteIndex(string $index) Delete an index
 * @method static array health() Get cluster health information
 * @method static array indices() Get all indices
 * @method static array bulk(array $operations) Execute bulk operations
 * @method static array indexDocument(string $index, array $document, ?string $id = null) Index a document
 * @method static array updateDocument(string $index, string $id, array $document) Update a document
 * @method static array deleteDocument(string $index, string $id) Delete a document
 * @method static array getDocument(string $index, string $id) Get a document by ID
 * @method static array putSynonym(string $id, array $synonymsSet, array $options = []) Create or update a synonym set
 * @method static array getSynonym(string $id, array $options = []) Get a synonym set by id
 * @method static array deleteSynonym(string $id) Delete a synonym set by id
 * @method static array getSynonymsSets(array $options = []) Get all synonym sets
 * @method static array putSynonymRule(string $setId, string $ruleId, array $rule, array $options = []) Create or update a synonym rule
 * @method static array getSynonymRule(string $setId, string $ruleId) Get a synonym rule
 * @method static array deleteSynonymRule(string $setId, string $ruleId, array $options = []) Delete a synonym rule
 * @method static array putPipeline(string $id, array $body) Create or update an ingest pipeline
 * @method static array getPipeline(string $id) Get an ingest pipeline by ID
 * @method static array deletePipeline(string $id) Delete an ingest pipeline
 * @method static array putInferenceEndpoint(string $inferenceId, string $taskType, array $body) Create or update an inference endpoint
 * @method static array getInferenceEndpoint(string $inferenceId) Get an inference endpoint by ID
 * @method static array deleteInferenceEndpoint(string $inferenceId) Delete an inference endpoint
 * @method static array getTrainedModelStats(string $modelId) Get stats for a trained ML model
 *
 * @see \JayI\Stretch\Stretch
 */
class Stretch extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string The service container binding key
     */
    protected static function getFacadeAccessor(): string
    {
        return 'stretch';
    }
}

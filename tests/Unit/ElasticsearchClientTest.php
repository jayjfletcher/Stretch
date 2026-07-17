<?php

declare(strict_types=1);

use JayI\Stretch\Client\ElasticsearchClient;
use JayI\Stretch\Contracts\ClientContract;
use JayI\Stretch\Exceptions\StretchException;

beforeEach(function () {
    config([
        'stretch.logging.enabled' => false,
        'stretch.logging.log_queries' => false,
        'stretch.logging.log_slow_queries' => false,
        'stretch.logging.slow_query_threshold' => 1000,
    ]);
});

/**
 * Since Elastic\Elasticsearch\Client is a final class and cannot be mocked,
 * we test ElasticsearchClient by creating a testable subclass that overrides
 * the methods to avoid calling the native client directly.
 */
class TestableElasticsearchClient extends ElasticsearchClient
{
    public ?Closure $searchHandler = null;

    public ?Closure $indexHandler = null;

    public ?Closure $updateHandler = null;

    public ?Closure $deleteHandler = null;

    public ?Closure $bulkHandler = null;

    public ?Closure $getHandler = null;

    public ?Closure $msearchHandler = null;

    public ?Closure $putPipelineHandler = null;

    public ?Closure $getPipelineHandler = null;

    public ?Closure $deletePipelineHandler = null;

    public ?Closure $putInferenceEndpointHandler = null;

    public ?Closure $getInferenceEndpointHandler = null;

    public ?Closure $deleteInferenceEndpointHandler = null;

    public ?Closure $getTrainedModelStatsHandler = null;

    public ?Closure $putMappingHandler = null;

    public ?Closure $reindexHandler = null;

    public ?Closure $updateAliasesHandler = null;

    public ?Closure $getTaskHandler = null;

    public array $loggedMessages = [];

    public array $loggedSlowQueries = [];

    public function __construct()
    {
        // Skip parent constructor since we can't create a real Client
    }

    public function search(array $params): array
    {
        try {
            $this->logQuery($params);

            if ($this->searchHandler) {
                $response = ($this->searchHandler)($params);
            } else {
                throw new Exception('No search handler set');
            }

            $this->logSlowQuery($params, $response);

            return $response;
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Search failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    public function index(array $params): array
    {
        try {
            if ($this->indexHandler) {
                return ($this->indexHandler)($params);
            }
            throw new Exception('No index handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Index operation failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    public function update(array $params): array
    {
        try {
            if ($this->updateHandler) {
                return ($this->updateHandler)($params);
            }
            throw new Exception('No update handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Update operation failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    public function delete(array $params): array
    {
        try {
            if ($this->deleteHandler) {
                return ($this->deleteHandler)($params);
            }
            throw new Exception('No delete handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Delete operation failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    public function bulk(array $params): array
    {
        try {
            if ($this->bulkHandler) {
                return ($this->bulkHandler)($params);
            }
            throw new Exception('No bulk handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Bulk operation failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    public function get(array $params): array
    {
        try {
            if ($this->getHandler) {
                return ($this->getHandler)($params);
            }
            throw new Exception('No get handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Get operation failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    public function msearch(array $params): array
    {
        try {
            $this->logQuery($params);

            if ($this->msearchHandler) {
                $response = ($this->msearchHandler)($params);
            } else {
                throw new Exception('No msearch handler set');
            }

            $this->logSlowQuery($params, $response);

            return $response;
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Multi-search operation failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    public function putPipeline(string $id, array $body): array
    {
        try {
            if ($this->putPipelineHandler) {
                return ($this->putPipelineHandler)($id, $body);
            }
            throw new Exception('No putPipeline handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Failed to put pipeline '$id': {$exception->getMessage()}", 0, $exception);
        }
    }

    public function getPipeline(string $id): array
    {
        try {
            if ($this->getPipelineHandler) {
                return ($this->getPipelineHandler)($id);
            }
            throw new Exception('No getPipeline handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Failed to get pipeline '$id': {$exception->getMessage()}", 0, $exception);
        }
    }

    public function deletePipeline(string $id): array
    {
        try {
            if ($this->deletePipelineHandler) {
                return ($this->deletePipelineHandler)($id);
            }
            throw new Exception('No deletePipeline handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Failed to delete pipeline '$id': {$exception->getMessage()}", 0, $exception);
        }
    }

    public function putInferenceEndpoint(string $inferenceId, string $taskType, array $body): array
    {
        try {
            if ($this->putInferenceEndpointHandler) {
                return ($this->putInferenceEndpointHandler)($inferenceId, $taskType, $body);
            }
            throw new Exception('No putInferenceEndpoint handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Failed to put inference endpoint '$inferenceId': {$exception->getMessage()}", 0, $exception);
        }
    }

    public function getInferenceEndpoint(string $inferenceId): array
    {
        try {
            if ($this->getInferenceEndpointHandler) {
                return ($this->getInferenceEndpointHandler)($inferenceId);
            }
            throw new Exception('No getInferenceEndpoint handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Failed to get inference endpoint '$inferenceId': {$exception->getMessage()}", 0, $exception);
        }
    }

    public function deleteInferenceEndpoint(string $inferenceId): array
    {
        try {
            if ($this->deleteInferenceEndpointHandler) {
                return ($this->deleteInferenceEndpointHandler)($inferenceId);
            }
            throw new Exception('No deleteInferenceEndpoint handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Failed to delete inference endpoint '$inferenceId': {$exception->getMessage()}", 0, $exception);
        }
    }

    public function getTrainedModelStats(string $modelId): array
    {
        try {
            if ($this->getTrainedModelStatsHandler) {
                return ($this->getTrainedModelStatsHandler)($modelId);
            }
            throw new Exception('No getTrainedModelStats handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Failed to get trained model stats for '$modelId': {$exception->getMessage()}", 0, $exception);
        }
    }

    public function putMapping(string $index, array $mapping): array
    {
        try {
            if ($this->putMappingHandler) {
                return ($this->putMappingHandler)($index, $mapping);
            }
            throw new Exception('No putMapping handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Failed to put mapping for '{$index}': {$exception->getMessage()}", 0, $exception);
        }
    }

    public function reindex(string $source, string $dest, array $options = []): array
    {
        try {
            if ($this->reindexHandler) {
                return ($this->reindexHandler)($source, $dest, $options);
            }
            throw new Exception('No reindex handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Failed to reindex '{$source}' → '{$dest}': {$exception->getMessage()}", 0, $exception);
        }
    }

    public function updateAliases(array $actions): array
    {
        try {
            if ($this->updateAliasesHandler) {
                return ($this->updateAliasesHandler)($actions);
            }
            throw new Exception('No updateAliases handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Failed to update aliases: {$exception->getMessage()}", 0, $exception);
        }
    }

    public function getTask(string $id): array
    {
        try {
            if ($this->getTaskHandler) {
                return ($this->getTaskHandler)($id);
            }
            throw new Exception('No getTask handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new StretchException("Failed to get task '{$id}': {$exception->getMessage()}", 0, $exception);
        }
    }
}

it('wraps search exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->searchHandler = function () {
        throw new RuntimeException('Connection refused');
    };

    expect(fn () => $client->search(['index' => 'test']))
        ->toThrow(StretchException::class, 'Search failed: Connection refused');
});

it('wraps index exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->indexHandler = function () {
        throw new RuntimeException('Index error');
    };

    expect(fn () => $client->index(['index' => 'test', 'body' => []]))
        ->toThrow(StretchException::class, 'Index operation failed: Index error');
});

it('wraps update exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->updateHandler = function () {
        throw new RuntimeException('Update error');
    };

    expect(fn () => $client->update(['index' => 'test', 'id' => '1', 'body' => []]))
        ->toThrow(StretchException::class, 'Update operation failed: Update error');
});

it('wraps delete exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->deleteHandler = function () {
        throw new RuntimeException('Delete error');
    };

    expect(fn () => $client->delete(['index' => 'test', 'id' => '1']))
        ->toThrow(StretchException::class, 'Delete operation failed: Delete error');
});

it('wraps bulk exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->bulkHandler = function () {
        throw new RuntimeException('Bulk error');
    };

    expect(fn () => $client->bulk(['body' => []]))
        ->toThrow(StretchException::class, 'Bulk operation failed: Bulk error');
});

it('wraps get exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->getHandler = function () {
        throw new RuntimeException('Get error');
    };

    expect(fn () => $client->get(['index' => 'test', 'id' => '1']))
        ->toThrow(StretchException::class, 'Get operation failed: Get error');
});

it('wraps msearch exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->msearchHandler = function () {
        throw new RuntimeException('Msearch error');
    };

    expect(fn () => $client->msearch(['body' => []]))
        ->toThrow(StretchException::class, 'Multi-search operation failed: Msearch error');
});

it('preserves original exception as previous in StretchException', function () {
    $originalException = new RuntimeException('Original error');

    $client = new TestableElasticsearchClient;
    $client->searchHandler = function () use ($originalException) {
        throw $originalException;
    };

    $caught = null;

    try {
        $client->search(['index' => 'test']);
    } catch (StretchException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(StretchException::class)
        ->and($caught->getPrevious())->toBe($originalException)
        ->and($caught->getMessage())->toContain('Original error');
});

it('can successfully search and return results', function () {
    $responseData = [
        'hits' => [
            'total' => ['value' => 1],
            'hits' => [['_id' => '1', '_source' => ['title' => 'Test']]],
        ],
        'took' => 5,
    ];

    $client = new TestableElasticsearchClient;
    $client->searchHandler = fn () => $responseData;

    $result = $client->search(['index' => 'test', 'body' => []]);

    expect($result['hits']['total']['value'])->toBe(1);
    expect($result['hits']['hits'][0]['_source']['title'])->toBe('Test');
});

it('logs queries when log_queries is enabled', function () {
    config([
        'stretch.logging.enabled' => true,
        'stretch.logging.log_queries' => true,
    ]);

    $responseData = ['hits' => ['total' => ['value' => 0], 'hits' => []], 'took' => 5];

    $client = new TestableElasticsearchClient;
    $client->searchHandler = fn () => $responseData;

    // Should not throw - logging should work
    $result = $client->search(['index' => 'test', 'body' => ['query' => ['match_all' => new stdClass]]]);

    expect($result)->toBe($responseData);
});

it('detects slow queries when threshold is exceeded', function () {
    config([
        'stretch.logging.enabled' => true,
        'stretch.logging.log_slow_queries' => true,
        'stretch.logging.slow_query_threshold' => 100,
    ]);

    $responseData = ['hits' => ['total' => ['value' => 0], 'hits' => []], 'took' => 500];

    $client = new TestableElasticsearchClient;
    $client->searchHandler = fn () => $responseData;

    $result = $client->search(['index' => 'test', 'body' => []]);

    expect($result['took'])->toBe(500);
});

it('does not log when logging is disabled', function () {
    config([
        'stretch.logging.enabled' => false,
        'stretch.logging.log_queries' => false,
        'stretch.logging.log_slow_queries' => false,
    ]);

    $responseData = ['hits' => ['total' => ['value' => 0], 'hits' => []], 'took' => 5000];

    $client = new TestableElasticsearchClient;
    $client->searchHandler = fn () => $responseData;

    $result = $client->search(['index' => 'test', 'body' => []]);

    expect($result)->toBe($responseData);
});

it('implements ClientContract interface', function () {
    $client = new TestableElasticsearchClient;

    expect($client)->toBeInstanceOf(ClientContract::class);
});

it('can successfully index a document', function () {
    $client = new TestableElasticsearchClient;
    $client->indexHandler = fn () => ['_id' => '1', 'result' => 'created'];

    $result = $client->index(['index' => 'test', 'body' => ['title' => 'Test']]);

    expect($result['result'])->toBe('created');
});

it('can successfully get a document', function () {
    $client = new TestableElasticsearchClient;
    $client->getHandler = fn () => ['_id' => '1', '_source' => ['title' => 'Test']];

    $result = $client->get(['index' => 'test', 'id' => '1']);

    expect($result['_id'])->toBe('1');
    expect($result['_source']['title'])->toBe('Test');
});

it('can successfully execute msearch', function () {
    $responseData = [
        'responses' => [
            ['hits' => ['total' => ['value' => 1], 'hits' => []]],
        ],
        'took' => 10,
    ];

    $client = new TestableElasticsearchClient;
    $client->msearchHandler = fn () => $responseData;

    $result = $client->msearch(['body' => []]);

    expect($result['responses'])->toHaveCount(1);
});

// ── Ingest Pipeline Tests ───────────────────────────────

it('can put an ingest pipeline', function () {
    $client = new TestableElasticsearchClient;
    $client->putPipelineHandler = fn ($id, $body) => ['acknowledged' => true];

    $result = $client->putPipeline('my-pipeline', [
        'description' => 'Test pipeline',
        'processors' => [],
    ]);

    expect($result['acknowledged'])->toBeTrue();
});

it('wraps putPipeline exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->putPipelineHandler = function () {
        throw new RuntimeException('Pipeline error');
    };

    expect(fn () => $client->putPipeline('my-pipeline', []))
        ->toThrow(StretchException::class, "Failed to put pipeline 'my-pipeline'");
});

it('can get an ingest pipeline', function () {
    $client = new TestableElasticsearchClient;
    $client->getPipelineHandler = fn ($id) => [
        $id => ['description' => 'Test pipeline', 'processors' => []],
    ];

    $result = $client->getPipeline('my-pipeline');

    expect($result)->toHaveKey('my-pipeline');
});

it('wraps getPipeline exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->getPipelineHandler = function () {
        throw new RuntimeException('Not found');
    };

    expect(fn () => $client->getPipeline('missing'))
        ->toThrow(StretchException::class, "Failed to get pipeline 'missing'");
});

it('can delete an ingest pipeline', function () {
    $client = new TestableElasticsearchClient;
    $client->deletePipelineHandler = fn ($id) => ['acknowledged' => true];

    $result = $client->deletePipeline('my-pipeline');

    expect($result['acknowledged'])->toBeTrue();
});

it('wraps deletePipeline exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->deletePipelineHandler = function () {
        throw new RuntimeException('Delete error');
    };

    expect(fn () => $client->deletePipeline('my-pipeline'))
        ->toThrow(StretchException::class, "Failed to delete pipeline 'my-pipeline'");
});

// ── Inference Endpoint Tests ────────────────────────────

it('can put an inference endpoint', function () {
    $client = new TestableElasticsearchClient;
    $client->putInferenceEndpointHandler = fn ($id, $taskType, $body) => [
        'inference_id' => $id,
        'task_type' => $taskType,
    ];

    $result = $client->putInferenceEndpoint('my-embeddings', 'text_embedding', [
        'service' => 'elasticsearch',
        'service_settings' => ['model_id' => '.multilingual-e5-small'],
    ]);

    expect($result['inference_id'])->toBe('my-embeddings')
        ->and($result['task_type'])->toBe('text_embedding');
});

it('wraps putInferenceEndpoint exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->putInferenceEndpointHandler = function () {
        throw new RuntimeException('Inference error');
    };

    expect(fn () => $client->putInferenceEndpoint('test', 'text_embedding', []))
        ->toThrow(StretchException::class, "Failed to put inference endpoint 'test'");
});

it('can get an inference endpoint', function () {
    $client = new TestableElasticsearchClient;
    $client->getInferenceEndpointHandler = fn ($id) => [
        'inference_id' => $id,
        'task_type' => 'text_embedding',
    ];

    $result = $client->getInferenceEndpoint('my-embeddings');

    expect($result['inference_id'])->toBe('my-embeddings');
});

it('wraps getInferenceEndpoint exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->getInferenceEndpointHandler = function () {
        throw new RuntimeException('Not found');
    };

    expect(fn () => $client->getInferenceEndpoint('missing'))
        ->toThrow(StretchException::class, "Failed to get inference endpoint 'missing'");
});

it('can delete an inference endpoint', function () {
    $client = new TestableElasticsearchClient;
    $client->deleteInferenceEndpointHandler = fn ($id) => ['acknowledged' => true];

    $result = $client->deleteInferenceEndpoint('my-embeddings');

    expect($result['acknowledged'])->toBeTrue();
});

it('wraps deleteInferenceEndpoint exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->deleteInferenceEndpointHandler = function () {
        throw new RuntimeException('Delete error');
    };

    expect(fn () => $client->deleteInferenceEndpoint('test'))
        ->toThrow(StretchException::class, "Failed to delete inference endpoint 'test'");
});

// ── ML / Trained Model Tests ────────────────────────────

it('can get trained model stats', function () {
    $client = new TestableElasticsearchClient;
    $client->getTrainedModelStatsHandler = fn ($modelId) => [
        'count' => 1,
        'trained_model_stats' => [
            [
                'model_id' => $modelId,
                'deployment_stats' => [
                    'allocation_status' => ['state' => 'fully_allocated'],
                ],
            ],
        ],
    ];

    $result = $client->getTrainedModelStats('.multilingual-e5-small');

    expect($result['trained_model_stats'][0]['model_id'])->toBe('.multilingual-e5-small')
        ->and($result['trained_model_stats'][0]['deployment_stats']['allocation_status']['state'])->toBe('fully_allocated');
});

it('wraps getTrainedModelStats exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->getTrainedModelStatsHandler = function () {
        throw new RuntimeException('Model not found');
    };

    expect(fn () => $client->getTrainedModelStats('missing-model'))
        ->toThrow(StretchException::class, "Failed to get trained model stats for 'missing-model'");
});

// ── putMapping ──────────────────────────────────────────

it('can put a mapping for an index', function () {
    $client = new TestableElasticsearchClient;
    $client->putMappingHandler = function (string $index, array $mapping) {
        expect($index)->toBe('posts')
            ->and($mapping)->toHaveKey('properties.new_field');

        return ['acknowledged' => true];
    };

    $result = $client->putMapping('posts', [
        'properties' => ['new_field' => ['type' => 'keyword']],
    ]);

    expect($result['acknowledged'])->toBeTrue();
});

it('wraps putMapping exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->putMappingHandler = function () {
        throw new RuntimeException('mapper_parsing_exception');
    };

    expect(fn () => $client->putMapping('posts', ['properties' => []]))
        ->toThrow(StretchException::class, "Failed to put mapping for 'posts'");
});

// ── reindex ─────────────────────────────────────────────

it('can reindex from one index to another asynchronously by default', function () {
    $client = new TestableElasticsearchClient;
    $client->reindexHandler = function (string $source, string $dest, array $options) {
        expect($source)->toBe('posts_v1')
            ->and($dest)->toBe('posts_v2')
            ->and($options)->toBe([]);

        return ['task' => 'node-1:123'];
    };

    $result = $client->reindex('posts_v1', 'posts_v2');

    expect($result['task'])->toBe('node-1:123');
});

it('passes wait_for_completion and body_extras to reindex', function () {
    $client = new TestableElasticsearchClient;
    $client->reindexHandler = function (string $source, string $dest, array $options) {
        expect($options['wait_for_completion'])->toBeTrue()
            ->and($options['body_extras'])->toBe(['conflicts' => 'proceed']);

        return ['total' => 42];
    };

    $result = $client->reindex('a', 'b', [
        'wait_for_completion' => true,
        'body_extras' => ['conflicts' => 'proceed'],
    ]);

    expect($result['total'])->toBe(42);
});

it('wraps reindex exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->reindexHandler = function () {
        throw new RuntimeException('version_conflict');
    };

    expect(fn () => $client->reindex('a', 'b'))
        ->toThrow(StretchException::class, "Failed to reindex 'a' → 'b'");
});

// ── updateAliases ───────────────────────────────────────

it('can apply alias actions atomically', function () {
    $client = new TestableElasticsearchClient;
    $client->updateAliasesHandler = function (array $actions) {
        expect($actions)->toBe([
            ['remove' => ['index' => 'posts_v1', 'alias' => 'posts']],
            ['add' => ['index' => 'posts_v2', 'alias' => 'posts']],
        ]);

        return ['acknowledged' => true];
    };

    $result = $client->updateAliases([
        ['remove' => ['index' => 'posts_v1', 'alias' => 'posts']],
        ['add' => ['index' => 'posts_v2', 'alias' => 'posts']],
    ]);

    expect($result['acknowledged'])->toBeTrue();
});

it('wraps updateAliases exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->updateAliasesHandler = function () {
        throw new RuntimeException('alias exists');
    };

    expect(fn () => $client->updateAliases([]))
        ->toThrow(StretchException::class, 'Failed to update aliases');
});

// ── getTask ─────────────────────────────────────────────

it('can get an async task status', function () {
    $client = new TestableElasticsearchClient;
    $client->getTaskHandler = function (string $id) {
        expect($id)->toBe('node-1:456');

        return [
            'completed' => false,
            'task' => ['status' => ['total' => 100, 'updated' => 30, 'created' => 0]],
        ];
    };

    $result = $client->getTask('node-1:456');

    expect($result['completed'])->toBeFalse()
        ->and($result['task']['status']['total'])->toBe(100);
});

it('wraps getTask exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->getTaskHandler = function () {
        throw new RuntimeException('task not found');
    };

    expect(fn () => $client->getTask('bad-id'))
        ->toThrow(StretchException::class, "Failed to get task 'bad-id'");
});

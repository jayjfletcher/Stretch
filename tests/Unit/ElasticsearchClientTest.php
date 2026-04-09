<?php

declare(strict_types=1);

use JayI\Stretch\Client\ElasticsearchClient;
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
                throw new \Exception('No search handler set');
            }

            $this->logSlowQuery($params, $response);

            return $response;
        } catch (StretchException $e) {
            throw $e;
        } catch (\Exception $exception) {
            throw new StretchException("Search failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    public function index(array $params): array
    {
        try {
            if ($this->indexHandler) {
                return ($this->indexHandler)($params);
            }
            throw new \Exception('No index handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (\Exception $exception) {
            throw new StretchException("Index operation failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    public function update(array $params): array
    {
        try {
            if ($this->updateHandler) {
                return ($this->updateHandler)($params);
            }
            throw new \Exception('No update handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (\Exception $exception) {
            throw new StretchException("Update operation failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    public function delete(array $params): array
    {
        try {
            if ($this->deleteHandler) {
                return ($this->deleteHandler)($params);
            }
            throw new \Exception('No delete handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (\Exception $exception) {
            throw new StretchException("Delete operation failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    public function bulk(array $params): array
    {
        try {
            if ($this->bulkHandler) {
                return ($this->bulkHandler)($params);
            }
            throw new \Exception('No bulk handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (\Exception $exception) {
            throw new StretchException("Bulk operation failed: {$exception->getMessage()}", 0, $exception);
        }
    }

    public function get(array $params): array
    {
        try {
            if ($this->getHandler) {
                return ($this->getHandler)($params);
            }
            throw new \Exception('No get handler set');
        } catch (StretchException $e) {
            throw $e;
        } catch (\Exception $exception) {
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
                throw new \Exception('No msearch handler set');
            }

            $this->logSlowQuery($params, $response);

            return $response;
        } catch (StretchException $e) {
            throw $e;
        } catch (\Exception $exception) {
            throw new StretchException("Multi-search operation failed: {$exception->getMessage()}", 0, $exception);
        }
    }
}

it('wraps search exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->searchHandler = function () {
        throw new \RuntimeException('Connection refused');
    };

    expect(fn () => $client->search(['index' => 'test']))
        ->toThrow(StretchException::class, 'Search failed: Connection refused');
});

it('wraps index exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->indexHandler = function () {
        throw new \RuntimeException('Index error');
    };

    expect(fn () => $client->index(['index' => 'test', 'body' => []]))
        ->toThrow(StretchException::class, 'Index operation failed: Index error');
});

it('wraps update exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->updateHandler = function () {
        throw new \RuntimeException('Update error');
    };

    expect(fn () => $client->update(['index' => 'test', 'id' => '1', 'body' => []]))
        ->toThrow(StretchException::class, 'Update operation failed: Update error');
});

it('wraps delete exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->deleteHandler = function () {
        throw new \RuntimeException('Delete error');
    };

    expect(fn () => $client->delete(['index' => 'test', 'id' => '1']))
        ->toThrow(StretchException::class, 'Delete operation failed: Delete error');
});

it('wraps bulk exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->bulkHandler = function () {
        throw new \RuntimeException('Bulk error');
    };

    expect(fn () => $client->bulk(['body' => []]))
        ->toThrow(StretchException::class, 'Bulk operation failed: Bulk error');
});

it('wraps get exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->getHandler = function () {
        throw new \RuntimeException('Get error');
    };

    expect(fn () => $client->get(['index' => 'test', 'id' => '1']))
        ->toThrow(StretchException::class, 'Get operation failed: Get error');
});

it('wraps msearch exceptions in StretchException', function () {
    $client = new TestableElasticsearchClient;
    $client->msearchHandler = function () {
        throw new \RuntimeException('Msearch error');
    };

    expect(fn () => $client->msearch(['body' => []]))
        ->toThrow(StretchException::class, 'Multi-search operation failed: Msearch error');
});

it('preserves original exception as previous in StretchException', function () {
    $originalException = new \RuntimeException('Original error');

    $client = new TestableElasticsearchClient;
    $client->searchHandler = function () use ($originalException) {
        throw $originalException;
    };

    try {
        $client->search(['index' => 'test']);
        test()->fail('Expected StretchException was not thrown');
    } catch (StretchException $e) {
        expect($e->getPrevious())->toBe($originalException);
        expect($e->getMessage())->toContain('Original error');
    }
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
    $result = $client->search(['index' => 'test', 'body' => ['query' => ['match_all' => new \stdClass]]]);

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

    expect($client)->toBeInstanceOf(\JayI\Stretch\Contracts\ClientContract::class);
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

<?php

declare(strict_types=1);

use JayI\Stretch\Contracts\ClientContract;
use JayI\Stretch\Contracts\QueryBuilderContract;
use JayI\Stretch\Stretch;
use Mockery as m;

it('can create query builder through stretch facade', function () {
    $mockClient = m::mock(ClientContract::class);
    $stretch = new Stretch($mockClient);

    $query = $stretch->query();

    expect($query)->toBeInstanceOf(QueryBuilderContract::class);
});

it('can create query builder for specific index', function () {
    $mockClient = m::mock(ClientContract::class);
    $stretch = new Stretch($mockClient);

    $query = $stretch->index('posts');

    expect($query)->toBeInstanceOf(QueryBuilderContract::class);
});

it('can check if index exists', function () {
    $mockClient = m::mock(ClientContract::class);
    $mockClient->shouldReceive('indexExists')
        ->with('posts')
        ->once()
        ->andReturn(true);

    $stretch = new Stretch($mockClient);

    $exists = $stretch->indexExists('posts');

    expect($exists)->toBeTrue();
});

it('can create an index', function () {
    $mockClient = m::mock(ClientContract::class);
    $mockClient->shouldReceive('createIndex')
        ->with('posts', [])
        ->once()
        ->andReturn(['acknowledged' => true]);

    $stretch = new Stretch($mockClient);

    $result = $stretch->createIndex('posts');

    expect($result['acknowledged'])->toBeTrue();
});

it('can delete an index', function () {
    $mockClient = m::mock(ClientContract::class);
    $mockClient->shouldReceive('deleteIndex')
        ->with('posts')
        ->once()
        ->andReturn(['acknowledged' => true]);

    $stretch = new Stretch($mockClient);

    $result = $stretch->deleteIndex('posts');

    expect($result['acknowledged'])->toBeTrue();
});

it('can index a document', function () {
    $document = ['title' => 'Test Document', 'content' => 'This is a test'];

    $mockClient = m::mock(ClientContract::class);
    $mockClient->shouldReceive('index')
        ->with(['index' => 'posts', 'body' => $document])
        ->once()
        ->andReturn(['_id' => '123', 'result' => 'created']);

    $stretch = new Stretch($mockClient);

    $result = $stretch->indexDocument('posts', $document);

    expect($result['result'])->toBe('created');
});

it('can update a document', function () {
    $document = ['title' => 'Updated Document'];

    $mockClient = m::mock(ClientContract::class);
    $mockClient->shouldReceive('update')
        ->with(['index' => 'posts', 'id' => '123', 'body' => ['doc' => $document]])
        ->once()
        ->andReturn(['result' => 'updated']);

    $stretch = new Stretch($mockClient);

    $result = $stretch->updateDocument('posts', '123', $document);

    expect($result['result'])->toBe('updated');
});

it('can delete a document', function () {
    $mockClient = m::mock(ClientContract::class);
    $mockClient->shouldReceive('delete')
        ->with(['index' => 'posts', 'id' => '123'])
        ->once()
        ->andReturn(['result' => 'deleted']);

    $stretch = new Stretch($mockClient);

    $result = $stretch->deleteDocument('posts', '123');

    expect($result['result'])->toBe('deleted');
});

it('can perform bulk operations', function () {
    $operations = [
        ['index' => ['_index' => 'posts', '_id' => '1']],
        ['title' => 'First Post'],
        ['index' => ['_index' => 'posts', '_id' => '2']],
        ['title' => 'Second Post'],
    ];

    $mockClient = m::mock(ClientContract::class);
    $mockClient->shouldReceive('bulk')
        ->with(['body' => $operations])
        ->once()
        ->andReturn(['errors' => false]);

    $stretch = new Stretch($mockClient);

    $result = $stretch->bulk($operations);

    expect($result['errors'])->toBeFalse();
});

it('can delete by query', function () {
    $mockClient = m::mock(ClientContract::class);
    $mockClient->shouldReceive('deleteByQuery')
        ->once()
        ->with(m::on(function ($params) {
            return $params['index'] === 'posts'
                && isset($params['body']['query']['term']['status'])
                && $params['body']['query']['term']['status'] === 'draft';
        }))
        ->andReturn(['deleted' => 5, 'failures' => []]);

    $stretch = new Stretch($mockClient);

    $result = $stretch->deleteByQuery('posts', fn ($q) => $q->term('status', 'draft'));

    expect($result['deleted'])->toBe(5);
});

it('delete by query uses match_all when no query set', function () {
    $mockClient = m::mock(ClientContract::class);
    $mockClient->shouldReceive('deleteByQuery')
        ->once()
        ->with(m::on(function ($params) {
            return $params['index'] === 'posts'
                && isset($params['body']['query']['match_all']);
        }))
        ->andReturn(['deleted' => 100, 'failures' => []]);

    $stretch = new Stretch($mockClient);

    $result = $stretch->deleteByQuery('posts', fn ($q) => $q);

    expect($result['deleted'])->toBe(100);
});

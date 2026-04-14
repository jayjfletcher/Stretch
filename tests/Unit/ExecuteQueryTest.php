<?php

declare(strict_types=1);

use JayI\Stretch\Builders\ElasticsearchQueryBuilder;
use JayI\Stretch\Contracts\ClientContract;
use Mockery as m;

it('wraps query in body when executing', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('search')
        ->once()
        ->with(m::on(function ($params) {
            // Verify the structure has 'index' and 'body' keys
            return isset($params['index'])
                && $params['index'] === 'test_index'
                && isset($params['body'])
                && isset($params['body']['query'])
                && isset($params['body']['query']['bool'])
                && isset($params['body']['query']['bool']['filter']);
        }))
        ->andReturn(['hits' => ['total' => ['value' => 0], 'hits' => []]]);

    $builder = new ElasticsearchQueryBuilder($mockClient);

    $builder->index('test_index')
        ->filter(function ($q) {
            $q->term('status', 'active');
        })
        ->execute();
});

it('includes index in params when executing', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('search')
        ->once()
        ->with(m::on(function ($params) {
            return $params['index'] === 'my_index';
        }))
        ->andReturn(['hits' => ['total' => ['value' => 0], 'hits' => []]]);

    $builder = new ElasticsearchQueryBuilder($mockClient);

    $builder->index('my_index')
        ->match('title', 'test')
        ->execute();
});

it('wraps size and from in body', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('search')
        ->once()
        ->with(m::on(function ($params) {
            return isset($params['body']['size'])
                && $params['body']['size'] === 20
                && isset($params['body']['from'])
                && $params['body']['from'] === 10;
        }))
        ->andReturn(['hits' => ['total' => ['value' => 0], 'hits' => []]]);

    $builder = new ElasticsearchQueryBuilder($mockClient);

    $builder->index('test_index')
        ->match('title', 'test')
        ->size(20)
        ->from(10)
        ->execute();
});

it('wraps sort in body', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('search')
        ->once()
        ->with(m::on(function ($params) {
            return isset($params['body']['sort']);
        }))
        ->andReturn(['hits' => ['total' => ['value' => 0], 'hits' => []]]);

    $builder = new ElasticsearchQueryBuilder($mockClient);

    $builder->index('test_index')
        ->match('title', 'test')
        ->sort('created_at', 'desc')
        ->execute();
});

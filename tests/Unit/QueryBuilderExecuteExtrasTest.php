<?php

declare(strict_types=1);

use JayI\Stretch\Builders\ElasticsearchQueryBuilder;
use JayI\Stretch\Contracts\ClientContract;
use JayI\Stretch\Exceptions\StretchException;
use Mockery as m;

it('counts documents via the _count API', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('count')
        ->once()
        ->with(m::on(fn ($params) => $params['index'] === 'posts'
            && $params['body']['query']['term']['status'] === 'draft'))
        ->andReturn(['count' => 7]);

    $count = (new ElasticsearchQueryBuilder($mockClient))
        ->index('posts')
        ->term('status', 'draft')
        ->count();

    expect($count)->toBe(7);
});

it('counts with match_all when no query set', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('count')
        ->once()
        ->with(m::on(fn ($params) => isset($params['body']['query']['match_all'])))
        ->andReturn(['count' => 100]);

    $count = (new ElasticsearchQueryBuilder($mockClient))->index('posts')->count();

    expect($count)->toBe(100);
});

it('throws when counting without an index', function () {
    $mockClient = m::mock(ClientContract::class);

    expect(fn () => (new ElasticsearchQueryBuilder($mockClient))->count())
        ->toThrow(StretchException::class);
});

it('sends search_type, preference and routing as request params', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('search')
        ->once()
        ->with(m::on(function ($params) {
            return $params['search_type'] === 'dfs_query_then_fetch'
                && $params['preference'] === '_local'
                && $params['routing'] === 'a,b';
        }))
        ->andReturn(['hits' => ['hits' => []]]);

    (new ElasticsearchQueryBuilder($mockClient))
        ->index('posts')
        ->match('title', 'x')
        ->searchType('dfs_query_then_fetch')
        ->preference('_local')
        ->routing(['a', 'b'])
        ->execute();
});

it('omits index when a point-in-time is set', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('search')
        ->once()
        ->with(m::on(function ($params) {
            return ! array_key_exists('index', $params)
                && $params['body']['pit']['id'] === 'pit-1';
        }))
        ->andReturn(['hits' => ['hits' => []]]);

    (new ElasticsearchQueryBuilder($mockClient))
        ->index('posts')
        ->pointInTime('pit-1', '1m')
        ->sort('_shard_doc', 'asc')
        ->execute();
});

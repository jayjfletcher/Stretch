<?php

declare(strict_types=1);

use JayI\Stretch\Contracts\ClientContract;
use JayI\Stretch\Stretch;
use Mockery as m;

it('runs update_by_query with a script', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('updateByQuery')
        ->once()
        ->with(m::on(function ($params) {
            return $params['index'] === 'posts'
                && $params['body']['query']['term']['status'] === 'draft'
                && $params['body']['script']['source'] === "ctx._source.status = 'archived'"
                && $params['conflicts'] === 'proceed';
        }))
        ->andReturn(['updated' => 3]);

    $result = (new Stretch($mockClient))->updateByQuery(
        'posts',
        fn ($q) => $q->term('status', 'draft'),
        ['source' => "ctx._source.status = 'archived'"],
        ['conflicts' => 'proceed'],
    );

    expect($result['updated'])->toBe(3);
});

it('opens and closes a point-in-time', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('openPointInTime')
        ->once()->with('posts', '1m')->andReturn(['id' => 'pit-xyz']);
    $mockClient->shouldReceive('closePointInTime')
        ->once()->with('pit-xyz')->andReturn(['succeeded' => true]);

    $stretch = new Stretch($mockClient);

    expect($stretch->openPointInTime('posts')['id'])->toBe('pit-xyz');
    expect($stretch->closePointInTime('pit-xyz')['succeeded'])->toBeTrue();
});

it('analyzes text with an analyzer', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('analyze')
        ->once()
        ->with(m::on(fn ($params) => $params['body']['analyzer'] === 'standard'
            && $params['body']['text'] === 'Quick Fox'
            && $params['index'] === 'posts'))
        ->andReturn(['tokens' => []]);

    (new Stretch($mockClient))->analyze(
        ['analyzer' => 'standard', 'text' => 'Quick Fox'],
        index: 'posts',
    );
});

it('explains a document against a query', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('explain')
        ->once()
        ->with(m::on(fn ($params) => $params['index'] === 'posts'
            && $params['id'] === '1'
            && $params['body']['query']['match']['title']['query'] === 'laravel'))
        ->andReturn(['matched' => true]);

    $result = (new Stretch($mockClient))->explain('posts', '1', fn ($q) => $q->match('title', 'laravel'));

    expect($result['matched'])->toBeTrue();
});

it('retrieves term vectors for a document', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('termvectors')
        ->once()
        ->with(m::on(fn ($params) => $params['index'] === 'posts'
            && $params['id'] === '1'
            && $params['body']['fields'] === ['title']
            && $params['body']['term_statistics'] === true))
        ->andReturn(['term_vectors' => []]);

    (new Stretch($mockClient))->termvectors('posts', '1', ['title'], ['term_statistics' => true]);
});

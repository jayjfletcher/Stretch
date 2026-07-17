<?php

declare(strict_types=1);

use JayI\Stretch\Builders\ScrollBuilder;
use JayI\Stretch\Contracts\ClientContract;
use Mockery as m;

it('opens a scroll, walks batches and clears the context', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('search')
        ->once()
        ->with(m::on(fn ($params) => $params['scroll'] === '2m'
            && $params['index'] === 'posts'
            && $params['body']['query']['term']['status'] === 'published'
            && ! array_key_exists('from', $params['body'])))
        ->andReturn([
            '_scroll_id' => 's1',
            'hits' => ['hits' => [['_id' => '1'], ['_id' => '2']]],
        ]);

    $mockClient->shouldReceive('scroll')
        ->once()
        ->with(m::on(fn ($params) => $params['body']['scroll_id'] === 's1'
            && $params['body']['scroll'] === '2m'))
        ->andReturn([
            '_scroll_id' => 's2',
            'hits' => ['hits' => [['_id' => '3']]],
        ]);

    // Third fetch returns an empty page -> iteration stops.
    $mockClient->shouldReceive('scroll')
        ->once()
        ->andReturn(['_scroll_id' => 's2', 'hits' => ['hits' => []]]);

    $mockClient->shouldReceive('clearScroll')
        ->once()
        ->with(m::on(fn ($params) => $params['body']['scroll_id'] === ['s2']))
        ->andReturn(['succeeded' => true]);

    $builder = new ScrollBuilder($mockClient);
    $ids = [];

    foreach ($builder->index('posts')->keepAlive('2m')->term('status', 'published')->cursor() as $hit) {
        $ids[] = $hit['_id'];
    }

    expect($ids)->toBe(['1', '2', '3']);
});

it('clears the scroll even when iteration is abandoned early', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('search')
        ->once()
        ->andReturn([
            '_scroll_id' => 's1',
            'hits' => ['hits' => [['_id' => '1'], ['_id' => '2']]],
        ]);

    $mockClient->shouldReceive('clearScroll')
        ->once()
        ->with(m::on(fn ($params) => $params['body']['scroll_id'] === ['s1']))
        ->andReturn(['succeeded' => true]);

    $builder = new ScrollBuilder($mockClient);

    foreach ($builder->index('posts')->cursor() as $hit) {
        break; // abandon after the first hit
    }

    // clearScroll expectation is verified on teardown.
    expect(true)->toBeTrue();
});

it('yields whole batches via batches()', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('search')->once()->andReturn([
        '_scroll_id' => 's1',
        'hits' => ['hits' => [['_id' => '1']]],
    ]);
    $mockClient->shouldReceive('scroll')->once()->andReturn([
        '_scroll_id' => 's1',
        'hits' => ['hits' => []],
    ]);
    $mockClient->shouldReceive('clearScroll')->once()->andReturn(['succeeded' => true]);

    $builder = new ScrollBuilder($mockClient);
    $batches = [];

    foreach ($builder->index('posts')->batches() as $batch) {
        $batches[] = $batch;
    }

    expect($batches)->toBe([[['_id' => '1']]]);
});

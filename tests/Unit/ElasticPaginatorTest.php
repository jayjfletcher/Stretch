<?php

declare(strict_types=1);

use JayI\Stretch\Builders\ElasticsearchQueryBuilder;
use JayI\Stretch\Pagination\ElasticPaginator;

it('can create paginator from response via fromResponse', function () {
    $builder = new ElasticsearchQueryBuilder;
    $builder->size(10)->from(0);

    $response = [
        'hits' => [
            'total' => ['value' => 50],
            'hits' => [
                ['_id' => '1', '_source' => ['title' => 'Post 1']],
                ['_id' => '2', '_source' => ['title' => 'Post 2']],
            ],
        ],
    ];

    $paginator = ElasticPaginator::fromResponse($builder, $response);

    expect($paginator)->toBeInstanceOf(ElasticPaginator::class);
    expect($paginator->total())->toBe(50);
    expect($paginator->perPage())->toBe(10);
    expect($paginator->currentPage())->toBe(1);
    expect($paginator->items())->toHaveCount(2);
});

it('calculates current page from size and from', function () {
    $builder = new ElasticsearchQueryBuilder;
    $builder->size(10)->from(20);

    $response = [
        'hits' => [
            'total' => ['value' => 100],
            'hits' => [],
        ],
    ];

    $paginator = ElasticPaginator::fromResponse($builder, $response);

    expect($paginator->currentPage())->toBe(3);
});

it('handles empty response', function () {
    $builder = new ElasticsearchQueryBuilder;
    $builder->size(10)->from(0);

    $response = [
        'hits' => [
            'total' => ['value' => 0],
            'hits' => [],
        ],
    ];

    $paginator = ElasticPaginator::fromResponse($builder, $response);

    expect($paginator->total())->toBe(0);
    expect($paginator->items())->toHaveCount(0);
});

it('handles missing hits in response', function () {
    $builder = new ElasticsearchQueryBuilder;
    $builder->size(10)->from(0);

    $response = [];

    $paginator = ElasticPaginator::fromResponse($builder, $response);

    expect($paginator->total())->toBe(0);
    expect($paginator->items())->toHaveCount(0);
});

it('passes options through to paginator', function () {
    $builder = new ElasticsearchQueryBuilder;
    $builder->size(10)->from(0);

    $response = [
        'hits' => [
            'total' => ['value' => 10],
            'hits' => [],
        ],
    ];

    $paginator = ElasticPaginator::fromResponse($builder, $response, ['fragment' => 'results']);

    expect($paginator)->toBeInstanceOf(ElasticPaginator::class);
});

it('extends LengthAwarePaginator', function () {
    $paginator = new ElasticPaginator(
        items: [['_id' => '1']],
        total: 100,
        perPage: 10,
        currentPage: 1,
    );

    expect($paginator)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
    expect($paginator->total())->toBe(100);
    expect($paginator->perPage())->toBe(10);
    expect($paginator->lastPage())->toBe(10);
});

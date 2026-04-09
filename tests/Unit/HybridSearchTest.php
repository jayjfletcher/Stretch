<?php

declare(strict_types=1);

use JayI\Stretch\Builders\ElasticsearchQueryBuilder;

it('can build a pure kNN search', function () {
    $builder = new ElasticsearchQueryBuilder;
    $vector = [0.1, 0.2, 0.3];

    $builder->knn('title_vector', $vector, k: 5, numCandidates: 50);

    $query = $builder->build();

    expect($query['knn']['field'])->toBe('title_vector');
    expect($query['knn']['query_vector'])->toBe($vector);
    expect($query['knn']['k'])->toBe(5);
    expect($query['knn']['num_candidates'])->toBe(50);
    expect($query)->not->toHaveKey('query');
});

it('defaults numCandidates to max(k*10, 100) when not provided', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->knn('vec', [0.0], k: 20);

    $query = $builder->build();

    expect($query['knn']['num_candidates'])->toBe(200);
});

it('merges extra options into the kNN clause', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->knn('vec', [0.0], k: 10, options: [
        'boost' => 0.5,
        'filter' => ['term' => ['status' => 'published']],
    ]);

    $query = $builder->build();

    expect($query['knn']['boost'])->toBe(0.5);
    expect($query['knn']['filter'])->toBe(['term' => ['status' => 'published']]);
});

it('builds a hybrid kNN + match query', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder
        ->match('title', 'Laravel')
        ->knn('title_vector', [0.1, 0.2], k: 10);

    $query = $builder->build();

    expect($query['query']['match']['title']['query'])->toBe('Laravel');
    expect($query['knn']['field'])->toBe('title_vector');
});

it('emits an array of knn clauses when multiple are added', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder
        ->knn('title_vector', [0.1], k: 5)
        ->knn('body_vector', [0.2], k: 5);

    $query = $builder->build();

    expect($query['knn'])->toHaveCount(2);
    expect($query['knn'][0]['field'])->toBe('title_vector');
    expect($query['knn'][1]['field'])->toBe('body_vector');
});

it('builds a standard retriever', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->retriever(function ($r) {
        $r->standard(fn ($q) => $q->match('title', 'Laravel'));
    });

    $query = $builder->build();

    expect($query['retriever']['standard']['query']['match']['title']['query'])->toBe('Laravel');
    expect($query)->not->toHaveKey('query');
});

it('builds a knn retriever', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->retriever(function ($r) {
        $r->knn('title_vector', [0.1, 0.2], k: 10, numCandidates: 100);
    });

    $query = $builder->build();

    expect($query['retriever']['knn']['field'])->toBe('title_vector');
    expect($query['retriever']['knn']['k'])->toBe(10);
    expect($query['retriever']['knn']['num_candidates'])->toBe(100);
});

it('builds an rrf retriever combining standard and knn sub-retrievers', function () {
    $builder = new ElasticsearchQueryBuilder;
    $vector = [0.1, 0.2, 0.3];

    $builder->retriever(function ($r) use ($vector) {
        $r->rrf([
            $r->standard(fn ($q) => $q->match('title', 'Laravel')),
            $r->knn('title_vector', $vector, k: 10, numCandidates: 100),
        ], rankWindowSize: 50, rankConstant: 20);
    });

    $query = $builder->build();

    expect($query['retriever']['rrf']['retrievers'])->toHaveCount(2);
    expect($query['retriever']['rrf']['retrievers'][0]['standard']['query']['match']['title']['query'])->toBe('Laravel');
    expect($query['retriever']['rrf']['retrievers'][1]['knn']['field'])->toBe('title_vector');
    expect($query['retriever']['rrf']['rank_window_size'])->toBe(50);
    expect($query['retriever']['rrf']['rank_constant'])->toBe(20);
});

it('retriever replaces top-level query and knn clauses', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder
        ->match('title', 'ignored')
        ->knn('vec', [0.0], k: 5)
        ->retriever(function ($r) {
            $r->standard(fn ($q) => $q->match('title', 'Laravel'));
        });

    $query = $builder->build();

    expect($query)->toHaveKey('retriever');
    expect($query)->not->toHaveKey('query');
    expect($query)->not->toHaveKey('knn');
});

it('retriever body preserves size, from, sort, _source and aggs', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder
        ->size(5)
        ->from(10)
        ->sort('created_at', 'desc')
        ->source(['title'])
        ->retriever(function ($r) {
            $r->standard(fn ($q) => $q->match('title', 'Laravel'));
        });

    $query = $builder->build();

    expect($query['size'])->toBe(5);
    expect($query['from'])->toBe(10);
    expect($query['sort'])->toBe([['created_at' => ['order' => 'desc']]]);
    expect($query['_source'])->toBe(['title']);
});

it('throws when building a retriever with no sub-retriever set', function () {
    $builder = new ElasticsearchQueryBuilder;

    expect(fn () => $builder->retriever(function ($r) {
        // never call standard/knn/rrf
    }))->toThrow(RuntimeException::class);
});

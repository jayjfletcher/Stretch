<?php

declare(strict_types=1);

use JayI\Stretch\Builders\ElasticsearchQueryBuilder;

it('can create a match query', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel');

    $query = $builder->build();

    expect($query['query']['match']['title']['query'])->toBe('Laravel');
});

it('can create a term query', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->term('status', 'published');

    $query = $builder->build();

    expect($query['query']['term']['status'])->toBe('published');
});

it('can create a semantic query', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->semantic('semantic_contents', 'What is Laravel?');

    $query = $builder->build();

    expect($query['query']['semantic']['field'])->toBe('semantic_contents');
    expect($query['query']['semantic']['query'])->toBe('What is Laravel?');
});

it('can create a semantic query with options', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->semantic('semantic_contents', 'machine learning', ['boost' => 2.0]);

    $query = $builder->build();

    expect($query['query']['semantic']['field'])->toBe('semantic_contents');
    expect($query['query']['semantic']['query'])->toBe('machine learning');
    expect($query['query']['semantic']['boost'])->toBe(2.0);
});

it('can create a range query', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->range('created_at')->gte('2024-01-01')->lte('2024-12-31');

    $query = $builder->build();

    expect($query['query']['range']['created_at']['gte'])->toBe('2024-01-01');
    expect($query['query']['range']['created_at']['lte'])->toBe('2024-12-31');
});

it('can create a bool query', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->bool(function ($bool) {
        $bool->must(fn ($q) => $q->match('title', 'Laravel'));
        $bool->filter(fn ($q) => $q->term('status', 'published'));
    });

    $query = $builder->build();

    expect($query['query']['bool'])->toHaveKey('must');
    expect($query['query']['bool'])->toHaveKey('filter');
});

it('can set size and from', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel')
        ->size(20)
        ->from(10);

    $query = $builder->build();

    expect($query['size'])->toBe(20);
    expect($query['from'])->toBe(10);
});

it('can add sorting', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel')
        ->sort('created_at', 'desc');

    $query = $builder->build();

    expect($query['sort'][0]['created_at']['order'])->toBe('desc');
});

it('can add source filtering', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel')
        ->source(['title', 'content']);

    $query = $builder->build();

    expect($query['_source'])->toBe(['title', 'content']);
});

it('can add aggregations', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel')
        ->aggregation('categories', fn ($agg) => $agg->terms('category.keyword'));

    $query = $builder->build();

    expect($query['aggs']['categories']['terms']['field'])->toBe('category.keyword');
});

it('can create complex nested queries', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->bool(function ($bool) {
        $bool->must([
            fn ($q) => $q->match('title', 'Laravel'),
            fn ($q) => $q->range('created_at')->gte('2024-01-01'),
        ]);
        $bool->should(fn ($q) => $q->term('featured', true));
        $bool->filter(fn ($q) => $q->term('status', 'published'));
        $bool->minimumShouldMatch(1);
    });

    $query = $builder->build();

    expect($query['query']['bool']['must'])->toHaveCount(2);
    expect($query['query']['bool']['should'])->toHaveCount(1);
    expect($query['query']['bool']['filter'])->toHaveCount(1);
    expect($query['query']['bool']['minimum_should_match'])->toBe(1);
});

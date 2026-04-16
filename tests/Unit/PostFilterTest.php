<?php

declare(strict_types=1);

use JayI\Stretch\Builders\ElasticsearchQueryBuilder;

it('adds a post_filter clause to the body', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('name', 'shoe')
        ->postFilter(fn ($q) => $q->term('color.keyword', 'red'));

    $query = $builder->build();

    expect($query)->toHaveKey('post_filter')
        ->and($query['post_filter'])->toBe(['term' => ['color.keyword' => 'red']]);
});

it('combines multiple post_filter calls under bool.filter', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->postFilter(fn ($q) => $q->term('color.keyword', 'red'))
        ->postFilter(fn ($q) => $q->range('price')->gte(50));

    $query = $builder->build();

    expect($query['post_filter'])->toHaveKey('bool')
        ->and($query['post_filter']['bool']['filter'])->toHaveCount(2)
        ->and($query['post_filter']['bool']['filter'][0])->toBe(['term' => ['color.keyword' => 'red']])
        ->and($query['post_filter']['bool']['filter'][1]['range']['price']['gte'])->toBe(50);
});

it('keeps post_filter separate from filter context', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('name', 'shoe')
        ->filter(fn ($q) => $q->term('in_stock', true))
        ->postFilter(fn ($q) => $q->term('color.keyword', 'red'));

    $query = $builder->build();

    expect($query['query']['bool']['filter'])->toHaveCount(1)
        ->and($query['query']['bool']['filter'][0])->toBe(['term' => ['in_stock' => true]])
        ->and($query['post_filter'])->toBe(['term' => ['color.keyword' => 'red']]);
});

it('does not include post_filter when not set', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel');

    $query = $builder->build();

    expect($query)->not->toHaveKey('post_filter');
});

it('preserves aggregations alongside post_filter', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('name', 'shoe')
        ->aggregation('colors', fn ($agg) => $agg->terms('color.keyword'))
        ->postFilter(fn ($q) => $q->term('color.keyword', 'red'));

    $query = $builder->build();

    expect($query['aggs'])->toHaveKey('colors')
        ->and($query['post_filter'])->toBe(['term' => ['color.keyword' => 'red']]);
});

it('includes post_filter in retriever body', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->retriever(function ($r) {
        $r->standard(fn ($q) => $q->match('title', 'Laravel'));
    })->postFilter(fn ($q) => $q->term('status', 'published'));

    $query = $builder->build();

    expect($query)->toHaveKey('retriever')
        ->and($query['post_filter'])->toBe(['term' => ['status' => 'published']]);
});

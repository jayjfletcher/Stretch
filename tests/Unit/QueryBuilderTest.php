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

it('skips the bool clause when the callback adds no clauses', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->bool(function ($bool) {
        // No clauses apply: an empty bool would serialize to `[]` and
        // Elasticsearch rejects it with a parsing_exception.
    });

    $query = $builder->build();

    expect($query)->not->toHaveKey('query');
});

it('keeps the bool clause when only some conditional clauses apply', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->bool(function ($bool) {
        $bool->filter(fn ($q) => $q->term('status', 'published'));
    });

    $query = $builder->build();

    expect($query['query']['bool'])->toHaveKey('filter');
    expect($query['query']['bool'])->not->toHaveKey('must');
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

it('can create a multi_match query', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->multiMatch('laptop for work', ['title^3', 'description', 'brand^2']);

    $query = $builder->build();

    expect($query['query']['multi_match']['query'])->toBe('laptop for work')
        ->and($query['query']['multi_match']['fields'])->toBe(['title^3', 'description', 'brand^2']);
});

it('can create a multi_match query with options', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->multiMatch('search text', ['name^3', 'description'], [
        'type' => 'best_fields',
        'fuzziness' => 'AUTO',
        'prefix_length' => 2,
        'minimum_should_match' => '75%',
    ]);

    $query = $builder->build();

    expect($query['query']['multi_match']['type'])->toBe('best_fields')
        ->and($query['query']['multi_match']['fuzziness'])->toBe('AUTO')
        ->and($query['query']['multi_match']['prefix_length'])->toBe(2)
        ->and($query['query']['multi_match']['minimum_should_match'])->toBe('75%');
});

it('can set track_total_hits to true', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel')->trackTotalHits();

    $query = $builder->build();

    expect($query['track_total_hits'])->toBeTrue();
});

it('can set track_total_hits to an integer threshold', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel')->trackTotalHits(5000);

    $query = $builder->build();

    expect($query['track_total_hits'])->toBe(5000);
});

it('does not include track_total_hits when not set', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel');

    $query = $builder->build();

    expect($query)->not->toHaveKey('track_total_hits');
});

it('can add raw aggregations', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel')
        ->rawAggregation('price_stats', ['stats' => ['field' => 'price']]);

    $query = $builder->build();

    expect($query['aggs']['price_stats'])->toBe(['stats' => ['field' => 'price']]);
});

it('can add filtered raw aggregations', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->rawAggregation('filtered_brand', [
        'filter' => ['bool' => ['filter' => [['term' => ['category' => 'Electronics']]]]],
        'aggs' => [
            'brand' => [
                'terms' => ['field' => 'brand', 'size' => 100],
            ],
        ],
    ]);

    $query = $builder->build();

    expect($query['aggs']['filtered_brand'])->toHaveKey('filter')
        ->and($query['aggs']['filtered_brand'])->toHaveKey('aggs')
        ->and($query['aggs']['filtered_brand']['aggs']['brand']['terms']['field'])->toBe('brand');
});

it('can mix aggregation and rawAggregation', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder
        ->aggregation('categories', fn ($agg) => $agg->terms('category.keyword'))
        ->rawAggregation('price_stats', ['stats' => ['field' => 'price']]);

    $query = $builder->build();

    expect($query['aggs'])->toHaveKey('categories')
        ->and($query['aggs'])->toHaveKey('price_stats');
});

it('includes track_total_hits in retriever body', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder
        ->trackTotalHits()
        ->retriever(function ($r) {
            $r->standard(fn ($q) => $q->match('title', 'Laravel'));
        });

    $query = $builder->build();

    expect($query['track_total_hits'])->toBeTrue()
        ->and($query)->toHaveKey('retriever');
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

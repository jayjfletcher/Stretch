<?php

declare(strict_types=1);

use JayI\Stretch\Builders\BoolQueryBuilder;
use JayI\Stretch\Builders\ElasticsearchQueryBuilder;

it('can build a must clause with a single callback', function () {
    $parent = new ElasticsearchQueryBuilder;
    $boolBuilder = new BoolQueryBuilder($parent);

    $boolBuilder->must(fn ($q) => $q->match('title', 'Laravel'));

    $result = $boolBuilder->build();

    expect($result)->toHaveKey('bool');
    expect($result['bool']['must'])->toHaveCount(1);
    expect($result['bool']['must'][0]['match']['title']['query'])->toBe('Laravel');
});

it('can build a must clause with an array of callbacks', function () {
    $parent = new ElasticsearchQueryBuilder;
    $boolBuilder = new BoolQueryBuilder($parent);

    $boolBuilder->must([
        fn ($q) => $q->match('title', 'Laravel'),
        fn ($q) => $q->term('status', 'published'),
    ]);

    $result = $boolBuilder->build();

    expect($result['bool']['must'])->toHaveCount(2);
    expect($result['bool']['must'][0]['match']['title']['query'])->toBe('Laravel');
    expect($result['bool']['must'][1]['term']['status'])->toBe('published');
});

it('can build a should clause with a single callback', function () {
    $parent = new ElasticsearchQueryBuilder;
    $boolBuilder = new BoolQueryBuilder($parent);

    $boolBuilder->should(fn ($q) => $q->term('featured', true));

    $result = $boolBuilder->build();

    expect($result['bool']['should'])->toHaveCount(1);
    expect($result['bool']['should'][0]['term']['featured'])->toBe(true);
});

it('can build a should clause with an array of callbacks', function () {
    $parent = new ElasticsearchQueryBuilder;
    $boolBuilder = new BoolQueryBuilder($parent);

    $boolBuilder->should([
        fn ($q) => $q->term('featured', true),
        fn ($q) => $q->term('promoted', true),
    ]);

    $result = $boolBuilder->build();

    expect($result['bool']['should'])->toHaveCount(2);
});

it('can build a filter clause with a single callback', function () {
    $parent = new ElasticsearchQueryBuilder;
    $boolBuilder = new BoolQueryBuilder($parent);

    $boolBuilder->filter(fn ($q) => $q->term('status', 'published'));

    $result = $boolBuilder->build();

    expect($result['bool']['filter'])->toHaveCount(1);
    expect($result['bool']['filter'][0]['term']['status'])->toBe('published');
});

it('can build a filter clause with an array of callbacks', function () {
    $parent = new ElasticsearchQueryBuilder;
    $boolBuilder = new BoolQueryBuilder($parent);

    $boolBuilder->filter([
        fn ($q) => $q->term('status', 'published'),
        fn ($q) => $q->term('type', 'article'),
    ]);

    $result = $boolBuilder->build();

    expect($result['bool']['filter'])->toHaveCount(2);
});

it('can build a must_not clause with a single callback', function () {
    $parent = new ElasticsearchQueryBuilder;
    $boolBuilder = new BoolQueryBuilder($parent);

    $boolBuilder->mustNot(fn ($q) => $q->term('status', 'deleted'));

    $result = $boolBuilder->build();

    expect($result['bool']['must_not'])->toHaveCount(1);
    expect($result['bool']['must_not'][0]['term']['status'])->toBe('deleted');
});

it('can build a must_not clause with an array of callbacks', function () {
    $parent = new ElasticsearchQueryBuilder;
    $boolBuilder = new BoolQueryBuilder($parent);

    $boolBuilder->mustNot([
        fn ($q) => $q->term('status', 'deleted'),
        fn ($q) => $q->term('hidden', true),
    ]);

    $result = $boolBuilder->build();

    expect($result['bool']['must_not'])->toHaveCount(2);
});

it('can set minimum_should_match as integer', function () {
    $parent = new ElasticsearchQueryBuilder;
    $boolBuilder = new BoolQueryBuilder($parent);

    $boolBuilder->should([
        fn ($q) => $q->term('tag', 'php'),
        fn ($q) => $q->term('tag', 'laravel'),
    ]);
    $boolBuilder->minimumShouldMatch(1);

    $result = $boolBuilder->build();

    expect($result['bool']['minimum_should_match'])->toBe(1);
});

it('can set minimum_should_match as string percentage', function () {
    $parent = new ElasticsearchQueryBuilder;
    $boolBuilder = new BoolQueryBuilder($parent);

    $boolBuilder->should([
        fn ($q) => $q->term('tag', 'php'),
        fn ($q) => $q->term('tag', 'laravel'),
    ]);
    $boolBuilder->minimumShouldMatch('75%');

    $result = $boolBuilder->build();

    expect($result['bool']['minimum_should_match'])->toBe('75%');
});

it('builds an empty bool when no clauses are added', function () {
    $parent = new ElasticsearchQueryBuilder;
    $boolBuilder = new BoolQueryBuilder($parent);

    $result = $boolBuilder->build();

    expect($result)->toBe(['bool' => []]);
});

it('can combine all clause types', function () {
    $parent = new ElasticsearchQueryBuilder;
    $boolBuilder = new BoolQueryBuilder($parent);

    $boolBuilder->must(fn ($q) => $q->match('title', 'Laravel'));
    $boolBuilder->should(fn ($q) => $q->term('featured', true));
    $boolBuilder->filter(fn ($q) => $q->term('status', 'published'));
    $boolBuilder->mustNot(fn ($q) => $q->term('hidden', true));
    $boolBuilder->minimumShouldMatch(1);

    $result = $boolBuilder->build();

    expect($result['bool'])->toHaveKey('must');
    expect($result['bool'])->toHaveKey('should');
    expect($result['bool'])->toHaveKey('filter');
    expect($result['bool'])->toHaveKey('must_not');
    expect($result['bool'])->toHaveKey('minimum_should_match');
    expect($result['bool']['must'])->toHaveCount(1);
    expect($result['bool']['should'])->toHaveCount(1);
    expect($result['bool']['filter'])->toHaveCount(1);
    expect($result['bool']['must_not'])->toHaveCount(1);
    expect($result['bool']['minimum_should_match'])->toBe(1);
});

it('returns the parent query builder via getParent', function () {
    $parent = new ElasticsearchQueryBuilder;
    $boolBuilder = new BoolQueryBuilder($parent);

    expect($boolBuilder->getParent())->toBe($parent);
});

it('can set a boost on the bool query', function () {
    $parent = new ElasticsearchQueryBuilder;
    $boolBuilder = new BoolQueryBuilder($parent);

    $boolBuilder->must(fn ($q) => $q->match('title', 'Laravel'))
        ->boost(0.7);

    $result = $boolBuilder->build();

    expect($result['bool'])->toHaveKey('boost')
        ->and($result['bool']['boost'])->toBe(0.7);
});

it('does not include boost when not set', function () {
    $parent = new ElasticsearchQueryBuilder;
    $boolBuilder = new BoolQueryBuilder($parent);

    $boolBuilder->must(fn ($q) => $q->match('title', 'Laravel'));

    $result = $boolBuilder->build();

    expect($result['bool'])->not->toHaveKey('boost');
});

it('includes boost in combined bool query', function () {
    $parent = new ElasticsearchQueryBuilder;
    $boolBuilder = new BoolQueryBuilder($parent);

    $boolBuilder
        ->should([
            fn ($q) => $q->match('title', 'test'),
            fn ($q) => $q->match('description', 'test'),
        ])
        ->filter(fn ($q) => $q->term('status', 'active'))
        ->minimumShouldMatch(1)
        ->boost(0.5);

    $result = $boolBuilder->build();

    expect($result['bool']['should'])->toHaveCount(2)
        ->and($result['bool']['filter'])->toHaveCount(1)
        ->and($result['bool']['minimum_should_match'])->toBe(1)
        ->and($result['bool']['boost'])->toBe(0.5);
});

it('boost integrates with the query builder bool() method', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->bool(function ($bool) {
        $bool->should(fn ($q) => $q->match('title', 'Laravel'))
            ->minimumShouldMatch(1)
            ->boost(0.7);
    });

    $query = $builder->build();

    expect($query['query']['bool']['boost'])->toBe(0.7)
        ->and($query['query']['bool']['minimum_should_match'])->toBe(1);
});

it('supports method chaining on all clause methods', function () {
    $parent = new ElasticsearchQueryBuilder;
    $boolBuilder = new BoolQueryBuilder($parent);

    $result = $boolBuilder
        ->must(fn ($q) => $q->match('title', 'test'))
        ->should(fn ($q) => $q->term('featured', true))
        ->filter(fn ($q) => $q->term('status', 'active'))
        ->mustNot(fn ($q) => $q->term('hidden', true))
        ->minimumShouldMatch(1);

    expect($result)->toBeInstanceOf(BoolQueryBuilder::class);
});

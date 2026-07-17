<?php

declare(strict_types=1);

use JayI\Stretch\Builders\ElasticsearchQueryBuilder;

it('builds a dis_max query with tie_breaker', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->disMax(function ($q) {
            $q->match('title', 'quick fox');
            $q->match('body', 'quick fox');
        }, tieBreaker: 0.3)
        ->build();

    expect($query['query']['dis_max']['queries'])->toHaveCount(2);
    expect($query['query']['dis_max']['tie_breaker'])->toBe(0.3);
    expect($query['query']['dis_max']['queries'][0]['match']['title']['query'])->toBe('quick fox');
});

it('builds a constant_score query', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->constantScore(fn ($q) => $q->term('status', 'published'), boost: 1.2)
        ->build();

    expect($query['query']['constant_score']['filter']['term']['status'])->toBe('published');
    expect($query['query']['constant_score']['boost'])->toBe(1.2);
});

it('builds a boosting query', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->boosting(
            positive: fn ($q) => $q->match('text', 'apple'),
            negative: fn ($q) => $q->match('text', 'pie'),
            negativeBoost: 0.5,
        )
        ->build();

    expect($query['query']['boosting']['positive']['match']['text']['query'])->toBe('apple');
    expect($query['query']['boosting']['negative']['match']['text']['query'])->toBe('pie');
    expect($query['query']['boosting']['negative_boost'])->toBe(0.5);
});

it('builds a script_score query', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->scriptScore(
            fn ($q) => $q->match('title', 'laravel'),
            ['source' => "_score * doc['popularity'].value"],
        )
        ->build();

    expect($query['query']['script_score']['query']['match']['title']['query'])->toBe('laravel');
    expect($query['query']['script_score']['script']['source'])->toBe("_score * doc['popularity'].value");
});

it('builds a function_score query with functions and modes', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->functionScore(function ($fs) {
            $fs->query(fn ($q) => $q->match('title', 'laravel'))
                ->fieldValueFactor('popularity', modifier: 'log1p', factor: 1.2)
                ->gauss('created_at', origin: 'now', scale: '10d')
                ->scoreMode('sum')
                ->boostMode('multiply')
                ->minScore(0.5);
        })
        ->build();

    $fs = $query['query']['function_score'];

    expect($fs['query']['match']['title']['query'])->toBe('laravel');
    expect($fs['functions'][0]['field_value_factor'])->toBe([
        'field' => 'popularity',
        'modifier' => 'log1p',
        'factor' => 1.2,
    ]);
    expect($fs['functions'][1]['gauss']['created_at'])->toBe(['origin' => 'now', 'scale' => '10d']);
    expect($fs['score_mode'])->toBe('sum');
    expect($fs['boost_mode'])->toBe('multiply');
    expect($fs['min_score'])->toBe(0.5);
});

it('builds a function_score random_score with a filter and weight', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->functionScore(function ($fs) {
            $fs->randomScore(seed: 10, field: '_seq_no', options: [
                'filter' => fn ($q) => $q->term('active', true),
                'weight' => 2.0,
            ]);
        })
        ->build();

    $function = $query['query']['function_score']['functions'][0];

    expect($function['random_score'])->toBe(['seed' => 10, 'field' => '_seq_no']);
    expect($function['filter']['term']['active'])->toBeTrue();
    expect($function['weight'])->toBe(2.0);
});

it('builds a standalone weight function with a filter', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->functionScore(function ($fs) {
            $fs->weight(3.0, fn ($q) => $q->term('featured', true));
        })
        ->build();

    $function = $query['query']['function_score']['functions'][0];

    expect($function['weight'])->toBe(3.0);
    expect($function['filter']['term']['featured'])->toBeTrue();
});

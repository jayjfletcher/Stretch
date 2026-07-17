<?php

declare(strict_types=1);

use JayI\Stretch\Builders\ElasticsearchQueryBuilder;

it('adds search_after to the body', function () {
    $body = (new ElasticsearchQueryBuilder)
        ->sort('created_at', 'desc')
        ->searchAfter(['2024-01-01', '42'])
        ->build();

    expect($body['search_after'])->toBe(['2024-01-01', '42']);
});

it('adds a point-in-time to the body', function () {
    $body = (new ElasticsearchQueryBuilder)
        ->pointInTime('abc123', '1m')
        ->build();

    expect($body['pit'])->toBe(['id' => 'abc123', 'keep_alive' => '1m']);
});

it('adds min_score, explain and terminate_after', function () {
    $body = (new ElasticsearchQueryBuilder)
        ->match('title', 'laravel')
        ->minScore(1.5)
        ->explain()
        ->terminateAfter(100)
        ->build();

    expect($body['min_score'])->toBe(1.5);
    expect($body['explain'])->toBeTrue();
    expect($body['terminate_after'])->toBe(100);
});

it('adds runtime_mappings, fields and docvalue_fields', function () {
    $mappings = ['dow' => ['type' => 'keyword', 'script' => ['source' => 'emit("x")']]];

    $body = (new ElasticsearchQueryBuilder)
        ->runtimeMappings($mappings)
        ->fields(['title', ['field' => 'created_at', 'format' => 'yyyy-MM-dd']])
        ->docvalueFields(['price'])
        ->build();

    expect($body['runtime_mappings'])->toBe($mappings);
    expect($body['fields'])->toBe(['title', ['field' => 'created_at', 'format' => 'yyyy-MM-dd']]);
    expect($body['docvalue_fields'])->toBe(['price']);
});

it('adds a single rescore clause unwrapped', function () {
    $body = (new ElasticsearchQueryBuilder)
        ->match('title', 'laravel')
        ->rescore(fn ($q) => $q->matchPhrase('title', 'laravel framework'), windowSize: 50, options: [
            'query_weight' => 0.7,
            'rescore_query_weight' => 1.2,
        ])
        ->build();

    expect($body['rescore']['window_size'])->toBe(50);
    expect($body['rescore']['query']['query_weight'])->toBe(0.7);
    expect($body['rescore']['query']['rescore_query']['match_phrase']['title']['query'])->toBe('laravel framework');
});

it('adds multiple rescore clauses as a list', function () {
    $body = (new ElasticsearchQueryBuilder)
        ->match('title', 'laravel')
        ->rescore(fn ($q) => $q->matchPhrase('title', 'a'))
        ->rescore(fn ($q) => $q->matchPhrase('title', 'b'))
        ->build();

    expect($body['rescore'])->toHaveCount(2);
});

it('builds a suggest clause with term, phrase and completion', function () {
    $body = (new ElasticsearchQueryBuilder)
        ->suggest(function ($s) {
            $s->term('spellcheck', 'title', 'laravle');
            $s->phrase('dym', 'title', 'quik brown');
            $s->completion('auto', 'title_suggest', 'lara', ['skip_duplicates' => true]);
        })
        ->size(0)
        ->build();

    expect($body['suggest']['spellcheck'])->toBe([
        'term' => ['field' => 'title'],
        'text' => 'laravle',
    ]);
    expect($body['suggest']['dym']['phrase']['field'])->toBe('title');
    expect($body['suggest']['auto'])->toBe([
        'prefix' => 'lara',
        'completion' => ['field' => 'title_suggest', 'skip_duplicates' => true],
    ]);
});

it('applies a global suggest text', function () {
    $body = (new ElasticsearchQueryBuilder)
        ->suggest(function ($s) {
            $s->text('laravle')->term('spellcheck', 'title');
        })
        ->build();

    expect($body['suggest']['text'])->toBe('laravle');
    expect($body['suggest']['spellcheck'])->toBe(['term' => ['field' => 'title']]);
});

it('carries search-body params through the retriever branch', function () {
    $body = (new ElasticsearchQueryBuilder)
        ->retriever(fn ($r) => $r->standard(fn ($q) => $q->match('title', 'x')))
        ->minScore(2.0)
        ->explain()
        ->searchAfter(['1'])
        ->build();

    expect($body)->toHaveKey('retriever');
    expect($body['min_score'])->toBe(2.0);
    expect($body['explain'])->toBeTrue();
    expect($body['search_after'])->toBe(['1']);
});

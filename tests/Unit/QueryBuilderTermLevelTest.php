<?php

declare(strict_types=1);

use JayI\Stretch\Builders\ElasticsearchQueryBuilder;

it('builds a match_phrase_prefix query', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->matchPhrasePrefix('title', 'quick brown f')
        ->build();

    expect($query['query']['match_phrase_prefix']['title']['query'])->toBe('quick brown f');
});

it('builds a match_bool_prefix query with options', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->matchBoolPrefix('title', 'quick brown f', ['operator' => 'and'])
        ->build();

    expect($query['query']['match_bool_prefix']['title']['query'])->toBe('quick brown f');
    expect($query['query']['match_bool_prefix']['title']['operator'])->toBe('and');
});

it('builds a bare prefix query', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->prefix('user.id', 'ki')
        ->build();

    expect($query['query']['prefix']['user.id'])->toBe('ki');
});

it('builds a prefix query with options as an object', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->prefix('code', 'ABC', ['case_insensitive' => true])
        ->build();

    expect($query['query']['prefix']['code'])->toBe(['value' => 'ABC', 'case_insensitive' => true]);
});

it('builds a regexp query', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->regexp('name', 'joh?n(ny)?')
        ->build();

    expect($query['query']['regexp']['name'])->toBe('joh?n(ny)?');
});

it('builds a regexp query with flags', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->regexp('code', '[a-z]+', ['flags' => 'ALL'])
        ->build();

    expect($query['query']['regexp']['code'])->toBe(['value' => '[a-z]+', 'flags' => 'ALL']);
});

it('builds an ids query', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->ids(['1', '4', '100'])
        ->build();

    expect($query['query']['ids']['values'])->toBe(['1', '4', '100']);
});

it('builds a terms_set query', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->termsSet('tags', ['php', 'laravel'], ['minimum_should_match_field' => 'required'])
        ->build();

    expect($query['query']['terms_set']['tags']['terms'])->toBe(['php', 'laravel']);
    expect($query['query']['terms_set']['tags']['minimum_should_match_field'])->toBe('required');
});

it('builds a distance_feature query', function () {
    $query = (new ElasticsearchQueryBuilder)
        ->distanceFeature('created_at', 'now', '7d')
        ->build();

    expect($query['query']['distance_feature'])->toBe([
        'field' => 'created_at',
        'origin' => 'now',
        'pivot' => '7d',
    ]);
});

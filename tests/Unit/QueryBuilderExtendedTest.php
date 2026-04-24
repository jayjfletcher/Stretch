<?php

declare(strict_types=1);

use JayI\Stretch\Builders\ElasticsearchQueryBuilder;

it('can create a wildcard query', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->wildcard('email', '*@example.com');

    $query = $builder->build();

    expect($query['query']['wildcard']['email'])->toBe('*@example.com');
});

it('can create a fuzzy query', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->fuzzy('name', 'elasticsearch');

    $query = $builder->build();

    expect($query['query']['fuzzy']['name']['value'])->toBe('elasticsearch');
});

it('can create a fuzzy query with options', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->fuzzy('name', 'elasticsearch', ['fuzziness' => 2, 'prefix_length' => 3]);

    $query = $builder->build();

    expect($query['query']['fuzzy']['name']['value'])->toBe('elasticsearch');
    expect($query['query']['fuzzy']['name']['fuzziness'])->toBe(2);
    expect($query['query']['fuzzy']['name']['prefix_length'])->toBe(3);
});

it('can create an exists query', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->exists('email');

    $query = $builder->build();

    expect($query['query']['exists']['field'])->toBe('email');
});

it('can create a rank_feature query with default linear scoring', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->rankFeature('pagerank');

    $query = $builder->build();

    expect($query['query']['rank_feature']['field'])->toBe('pagerank');
});

it('can create a rank_feature query with saturation function', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->rankFeature('pagerank', ['saturation' => ['pivot' => 8]]);

    $query = $builder->build();

    expect($query['query']['rank_feature']['field'])->toBe('pagerank');
    expect($query['query']['rank_feature']['saturation']['pivot'])->toBe(8);
});

it('can create a rank_feature query with log function and boost', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->rankFeature('url_length', [
        'log' => ['scaling_factor' => 4],
        'boost' => 2.0,
    ]);

    $query = $builder->build();

    expect($query['query']['rank_feature']['field'])->toBe('url_length');
    expect($query['query']['rank_feature']['log']['scaling_factor'])->toBe(4);
    expect($query['query']['rank_feature']['boost'])->toBe(2.0);
});

it('can create a rank_feature query with sigmoid function', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->rankFeature('topics.sports', [
        'sigmoid' => ['pivot' => 7, 'exponent' => 0.6],
    ]);

    $query = $builder->build();

    expect($query['query']['rank_feature']['field'])->toBe('topics.sports');
    expect($query['query']['rank_feature']['sigmoid']['pivot'])->toBe(7);
    expect($query['query']['rank_feature']['sigmoid']['exponent'])->toBe(0.6);
});

it('can enable profiling with profile()', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'laravel')->profile();

    $query = $builder->build();

    expect($query['profile'])->toBeTrue();
});

it('can disable profiling by passing false', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'laravel')->profile(false);

    $query = $builder->build();

    expect($query['profile'])->toBeFalse();
});

it('omits profile when not called', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'laravel');

    $query = $builder->build();

    expect($query)->not->toHaveKey('profile');
});

it('can collapse hits by a simple field', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('message', 'GET /search')->collapse('user.id');

    $query = $builder->build();

    expect($query['collapse'])->toBe(['field' => 'user.id']);
});

it('can collapse with inner_hits and max_concurrent_group_searches', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->collapse('user.id', [
        'name' => 'most_recent',
        'size' => 5,
        'sort' => [['@timestamp' => 'desc']],
    ], maxConcurrentGroupSearches: 4);

    $query = $builder->build();

    expect($query['collapse']['field'])->toBe('user.id');
    expect($query['collapse']['inner_hits']['name'])->toBe('most_recent');
    expect($query['collapse']['inner_hits']['size'])->toBe(5);
    expect($query['collapse']['max_concurrent_group_searches'])->toBe(4);
});

it('can collapse with a full config array', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->collapse([
        'field' => 'geo.country_name',
        'inner_hits' => [
            'name' => 'by_location',
            'collapse' => ['field' => 'user.id'],
            'size' => 3,
        ],
    ]);

    $query = $builder->build();

    expect($query['collapse']['field'])->toBe('geo.country_name');
    expect($query['collapse']['inner_hits']['collapse']['field'])->toBe('user.id');
});

it('omits collapse when not called', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'laravel');

    $query = $builder->build();

    expect($query)->not->toHaveKey('collapse');
});

it('can use rank_feature inside a bool should clause', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->bool(function ($bool) {
        $bool->must(fn ($q) => $q->match('content', 'laravel'))
            ->should(fn ($q) => $q->rankFeature('pagerank', ['saturation' => ['pivot' => 8]]));
    });

    $query = $builder->build();

    expect($query['query']['bool']['should'][0]['rank_feature']['field'])->toBe('pagerank');
    expect($query['query']['bool']['should'][0]['rank_feature']['saturation']['pivot'])->toBe(8);
});

it('can create a nested query', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->nested('comments', function ($q) {
        $q->match('comments.content', 'great post');
    });

    $query = $builder->build();

    expect($query['query']['nested']['path'])->toBe('comments');
    expect($query['query']['nested']['query']['match']['comments.content']['query'])->toBe('great post');
});

it('can create a match phrase query', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->matchPhrase('content', 'quick brown fox');

    $query = $builder->build();

    expect($query['query']['match_phrase']['content']['query'])->toBe('quick brown fox');
});

it('can create a match phrase query with options', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->matchPhrase('content', 'quick brown fox', ['slop' => 2]);

    $query = $builder->build();

    expect($query['query']['match_phrase']['content']['query'])->toBe('quick brown fox');
    expect($query['query']['match_phrase']['content']['slop'])->toBe(2);
});

it('can create a match_all query', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->matchAll();

    $query = $builder->build();

    expect($query['query']['match_all'])->toEqual((object) []);
});

it('can create a match_all query with options', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->matchAll(['boost' => 1.2]);

    $query = $builder->build();

    expect($query['query']['match_all'])->toEqual((object) ['boost' => 1.2]);
});

it('can add highlight configuration', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel')
        ->highlight(
            ['title' => new stdClass, 'content' => ['fragment_size' => 150]],
            ['pre_tags' => ['<em>'], 'post_tags' => ['</em>']]
        );

    $query = $builder->build();

    expect($query['highlight'])->toHaveKey('fields');
    expect($query['highlight'])->toHaveKey('pre_tags');
    expect($query['highlight']['pre_tags'])->toBe(['<em>']);
    expect($query['highlight']['post_tags'])->toBe(['</em>']);
    expect($query['highlight']['fields']['content']['fragment_size'])->toBe(150);
});

it('can set pagination with size and from', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel')->size(25)->from(50);

    $query = $builder->build();

    expect($query['size'])->toBe(25);
    expect($query['from'])->toBe(50);
});

it('uses default size when none is set', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel');

    $query = $builder->build();

    expect($query['size'])->toBe(config('stretch.query.default_size'));
});

it('caps size at max_size', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel')->size(999999);

    $query = $builder->build();

    expect($query['size'])->toBe(config('stretch.query.max_size'));
});

it('defaults from to 0', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel');

    expect($builder->getFrom())->toBe(0);
});

it('can create a terms query', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->terms('status', ['published', 'draft']);

    $query = $builder->build();

    expect($query['query']['terms']['status'])->toBe(['published', 'draft']);
});

it('auto-wraps multiple queries in bool must', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel')
        ->term('status', 'published');

    $query = $builder->build();

    expect($query['query']['bool']['must'])->toHaveCount(2);
});

it('wraps query and filter in bool structure', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel')
        ->filter(fn ($q) => $q->term('status', 'published'));

    $query = $builder->build();

    expect($query['query']['bool'])->toHaveKey('must');
    expect($query['query']['bool'])->toHaveKey('filter');
});

it('can set source to false to exclude all source fields', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel')->source(false);

    $query = $builder->build();

    expect($query['_source'])->toBeFalse();
});

it('can add multiple sort clauses', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel')
        ->sort('featured', 'desc')
        ->sort('created_at', 'desc');

    $query = $builder->build();

    expect($query['sort'])->toHaveCount(2);
    expect($query['sort'][0]['featured']['order'])->toBe('desc');
    expect($query['sort'][1]['created_at']['order'])->toBe('desc');
});

it('can sort with array configuration', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel')
        ->sort(['price' => ['order' => 'asc', 'mode' => 'avg']]);

    $query = $builder->build();

    expect($query['sort'][0]['price']['order'])->toBe('asc');
    expect($query['sort'][0]['price']['mode'])->toBe('avg');
});

it('can set and get index', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->index('posts');

    expect($builder->getIndex())->toBe('posts');
});

it('can set index to array for multi-index search', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->index(['posts', 'comments']);

    expect($builder->getIndex())->toBe(['posts', 'comments']);
});

it('returns same array from toArray and build', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel')->size(10);

    expect($builder->toArray())->toBe($builder->build());
});

it('throws RuntimeException when executing without client', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'test');

    expect(fn () => $builder->execute())
        ->toThrow(RuntimeException::class, 'Client not set. Cannot execute query.');
});

it('can create a match query with options', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel', ['fuzziness' => 'AUTO', 'operator' => 'and']);

    $query = $builder->build();

    expect($query['query']['match']['title']['query'])->toBe('Laravel');
    expect($query['query']['match']['title']['fuzziness'])->toBe('AUTO');
    expect($query['query']['match']['title']['operator'])->toBe('and');
});

it('can create a nested query with multiple clauses', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->nested('comments', function ($q) {
        $q->match('comments.content', 'great')
            ->term('comments.author', 'john');
    });

    $query = $builder->build();

    expect($query['query']['nested']['path'])->toBe('comments');
    expect($query['query']['nested']['query']['bool']['must'])->toHaveCount(2);
});

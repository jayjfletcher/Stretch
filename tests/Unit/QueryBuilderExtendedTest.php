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

it('can add highlight configuration', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->match('title', 'Laravel')
        ->highlight(
            ['title' => new \stdClass, 'content' => ['fragment_size' => 150]],
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
        ->toThrow(\RuntimeException::class, 'Client not set. Cannot execute query.');
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

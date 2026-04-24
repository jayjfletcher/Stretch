<?php

declare(strict_types=1);

use JayI\Stretch\Builders\ElasticsearchQueryBuilder;
use JayI\Stretch\Builders\MultiQueryBuilder;
use JayI\Stretch\Contracts\ClientContract;
use Mockery as m;

it('can add a query using a callback', function () {
    $builder = new MultiQueryBuilder;

    $builder->add('posts_query', fn ($q) => $q->index('posts')->match('title', 'Laravel'));

    $body = $builder->build();

    expect($body)->toHaveCount(2);
    expect($body[0])->toBe(['index' => 'posts']);
    expect($body[1]['query']['match']['title']['query'])->toBe('Laravel');
});

it('can add a query using a query builder instance', function () {
    $queryBuilder = new ElasticsearchQueryBuilder;
    $queryBuilder->index('posts')->match('title', 'Laravel');

    $builder = new MultiQueryBuilder;
    $builder->add('posts_query', $queryBuilder);

    $body = $builder->build();

    expect($body)->toHaveCount(2);
    expect($body[0])->toBe(['index' => 'posts']);
    expect($body[1]['query']['match']['title']['query'])->toBe('Laravel');
});

it('can add multiple queries', function () {
    $builder = new MultiQueryBuilder;

    $builder
        ->add('a_posts', fn ($q) => $q->index('posts')->match('title', 'Laravel'))
        ->add('b_users', fn ($q) => $q->index('users')->term('status', 'active'))
        ->add('c_comments', fn ($q) => $q->index('comments')->exists('body'));

    $body = $builder->build();

    // Queries are sorted by key name
    expect($body)->toHaveCount(6);
    expect($body[0])->toBe(['index' => 'posts']);    // a_posts
    expect($body[2])->toBe(['index' => 'users']);    // b_users
    expect($body[4])->toBe(['index' => 'comments']); // c_comments
});

it('handles array indices', function () {
    $builder = new MultiQueryBuilder;

    $builder->add('multi_index_query', fn ($q) => $q->index(['posts', 'pages'])->match('content', 'search'));

    $body = $builder->build();

    expect($body[0])->toBe(['index' => 'posts,pages']);
});

it('returns empty responses array when no queries added', function () {
    $mockClient = m::mock(ClientContract::class);

    $builder = new MultiQueryBuilder($mockClient);

    $result = $builder->execute();

    expect($result)->toBe(['responses' => []]);
});

it('executes msearch with correct body structure', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('msearch')
        ->once()
        ->with(m::on(function ($params) {
            // Verify msearch body structure (sorted by key: a_posts, b_users)
            return isset($params['body'])
                && count($params['body']) === 4
                && $params['body'][0] === ['index' => 'posts']
                && isset($params['body'][1]['query'])
                && $params['body'][2] === ['index' => 'users']
                && isset($params['body'][3]['query']);
        }))
        ->andReturn([
            'responses' => [
                ['hits' => ['total' => ['value' => 5], 'hits' => []]],
                ['hits' => ['total' => ['value' => 10], 'hits' => []]],
            ],
        ]);

    $builder = new MultiQueryBuilder($mockClient);

    $result = $builder
        ->add('a_posts', fn ($q) => $q->index('posts')->match('title', 'Laravel'))
        ->add('b_users', fn ($q) => $q->index('users')->term('status', 'active'))
        ->execute();

    expect($result['responses'])->toHaveCount(2);
    expect($result['responses'])->toHaveKeys(['a_posts', 'b_users']);
});

it('throws exception when executing without client', function () {
    $builder = new MultiQueryBuilder;
    $builder->add('posts_query', fn ($q) => $q->index('posts')->match('title', 'Laravel'));

    $builder->execute();
})->throws(RuntimeException::class, 'Client not set');

it('can count queries', function () {
    $builder = new MultiQueryBuilder;

    expect($builder->count())->toBe(0);

    $builder->add('posts_query', fn ($q) => $q->index('posts')->match('title', 'Laravel'));
    expect($builder->count())->toBe(1);

    $builder->add('users_query', fn ($q) => $q->index('users')->term('status', 'active'));
    expect($builder->count())->toBe(2);
});

it('toArray returns same as build', function () {
    $builder = new MultiQueryBuilder;
    $builder->add('posts_query', fn ($q) => $q->index('posts')->match('title', 'Laravel'));

    expect($builder->toArray())->toBe($builder->build());
});

it('getLastQuery returns null before execute', function () {
    $builder = new MultiQueryBuilder;

    expect($builder->getLastQuery())->toBeNull();
});

it('captures the last msearch payload sent to the client', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('msearch')
        ->once()
        ->andReturn(['responses' => [['hits' => []], ['hits' => []]]]);

    $builder = new MultiQueryBuilder($mockClient);

    $builder
        ->add('a_posts', fn ($q) => $q->index('posts')->match('title', 'Laravel'))
        ->add('b_users', fn ($q) => $q->index('users')->term('active', true))
        ->execute();

    $last = $builder->getLastQuery();

    expect($last)->toBeArray();
    expect($last['body'])->toHaveCount(4);
    expect($last['body'][0])->toBe(['index' => 'posts']);
    expect($last['body'][1]['query']['match']['title']['query'])->toBe('Laravel');
    expect($last['body'][2])->toBe(['index' => 'users']);
});

it('records an empty body when executing with no queries', function () {
    $builder = new MultiQueryBuilder(m::mock(ClientContract::class));

    $builder->execute();

    expect($builder->getLastQuery())->toBe(['body' => []]);
});

it('supports complex queries with size and sort', function () {
    $builder = new MultiQueryBuilder;

    $builder->add('posts_query', fn ($q) => $q
        ->index('posts')
        ->match('title', 'Laravel')
        ->size(10)
        ->from(0)
        ->sort('created_at', 'desc')
    );

    $body = $builder->build();

    expect($body[1]['size'])->toBe(10);
    expect($body[1]['from'])->toBe(0);
    expect($body[1]['sort'][0]['created_at']['order'])->toBe('desc');
});

it('supports bool queries within multi-search', function () {
    $builder = new MultiQueryBuilder;

    $builder->add('posts_query', fn ($q) => $q
        ->index('posts')
        ->bool(function ($bool) {
            $bool->must(fn ($q) => $q->match('title', 'Laravel'));
            $bool->filter(fn ($q) => $q->term('status', 'published'));
        })
    );

    $body = $builder->build();

    expect($body[1]['query']['bool'])->toHaveKey('must');
    expect($body[1]['query']['bool'])->toHaveKey('filter');
});

it('returns named results from execute', function () {
    $mockClient = m::mock(ClientContract::class);

    $mockClient->shouldReceive('msearch')
        ->once()
        ->andReturn([
            'responses' => [
                ['hits' => ['total' => ['value' => 5], 'hits' => [['_id' => '1']]]],
                ['hits' => ['total' => ['value' => 10], 'hits' => [['_id' => '2']]]],
            ],
        ]);

    $builder = new MultiQueryBuilder($mockClient);

    $result = $builder
        ->add('a_posts', fn ($q) => $q->index('posts')->match('title', 'Laravel'))
        ->add('b_users', fn ($q) => $q->index('users')->term('status', 'active'))
        ->execute();

    // Results are keyed by query name
    expect($result['responses'])->toHaveKey('a_posts');
    expect($result['responses'])->toHaveKey('b_users');
    expect($result['responses']['a_posts']['hits']['total']['value'])->toBe(5);
    expect($result['responses']['b_users']['hits']['total']['value'])->toBe(10);
});

<?php

declare(strict_types=1);

use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use JayI\Stretch\Builders\ElasticsearchQueryBuilder;
use JayI\Stretch\Builders\MultiQueryBuilder;
use JayI\Stretch\Contracts\ClientContract;
use JayI\Stretch\ElasticsearchManager;

beforeEach(function () {
    config(['stretch.cache.enabled' => false]);
    config(['stretch.cache.ttl' => [300, 600]]);
    config(['stretch.cache.prefix' => '']);
    config(['stretch.cache.store' => 'array']);
});

it('can enable caching with cache method', function () {
    $builder = new ElasticsearchQueryBuilder;

    expect($builder->isCacheEnabled())->toBeFalse();

    $builder->cache();

    expect($builder->isCacheEnabled())->toBeTrue();
});

it('can enable caching with setCacheEnabled', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->setCacheEnabled(true);

    expect($builder->getCacheEnabled())->toBeTrue();

    $builder->setCacheEnabled(false);

    expect($builder->getCacheEnabled())->toBeFalse();
});

it('can set cache TTL', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->setCacheTtl([600, 1200]);

    expect($builder->getCacheTtl())->toBe([600, 1200]);
});

it('uses default TTL from config when not set', function () {
    config(['stretch.cache.ttl' => [120, 240]]);

    $builder = new ElasticsearchQueryBuilder;

    expect($builder->getCacheTtl())->toBe([120, 240]);
});

it('can set cache prefix', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->setCachePrefix('search:');

    expect($builder->getCachePrefix())->toBe('search:');
});

it('uses default prefix from config when not set', function () {
    config(['stretch.cache.prefix' => 'es:']);

    $builder = new ElasticsearchQueryBuilder;

    expect($builder->getCachePrefix())->toBe('es:');
});

it('can set cache store', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->setCacheStore('redis');

    expect($builder->getCacheStore())->toBe('redis');
});

it('uses default store from config when not set', function () {
    config(['stretch.cache.store' => 'file']);

    $builder = new ElasticsearchQueryBuilder;

    expect($builder->getCacheStore())->toBe('file');
});

it('can enable cache clearing', function () {
    $builder = new ElasticsearchQueryBuilder;

    expect($builder->getCacheClear())->toBeFalse();

    $builder->clearCache();

    expect($builder->getCacheClear())->toBeTrue();
});

it('can set cache clear with setCacheClear', function () {
    $builder = new ElasticsearchQueryBuilder;

    $builder->setCacheClear(true);

    expect($builder->getCacheClear())->toBeTrue();

    $builder->setCacheClear(false);

    expect($builder->getCacheClear())->toBeFalse();
});

it('generates consistent cache keys for same queries', function () {
    $builder1 = new ElasticsearchQueryBuilder;
    $builder1->index('test_index')
        ->match('title', 'Laravel')
        ->term('status', 'published');

    $builder2 = new ElasticsearchQueryBuilder;
    $builder2->index('test_index')
        ->match('title', 'Laravel')
        ->term('status', 'published');

    expect($builder1->getCacheKey())->toBe($builder2->getCacheKey());
});

it('generates different cache keys for different queries', function () {
    $builder1 = new ElasticsearchQueryBuilder;
    $builder1->index('test_index')
        ->match('title', 'Laravel');

    $builder2 = new ElasticsearchQueryBuilder;
    $builder2->index('test_index')
        ->match('title', 'Symfony');

    expect($builder1->getCacheKey())->not->toBe($builder2->getCacheKey());
});

it('includes index name in cache key', function () {
    $builder = new ElasticsearchQueryBuilder;
    $builder->index('products')
        ->match('name', 'test');

    $key = $builder->getCacheKey();

    expect($key)->toContain('products');
});

it('includes prefix in cache key', function () {
    $builder = new ElasticsearchQueryBuilder;
    $builder->setCachePrefix('search:')
        ->index('products')
        ->match('name', 'test');

    $key = $builder->getCacheKey();

    expect($key)->toStartWith('search:');
});

it('getIndexes returns single index for query builder', function () {
    $builder = new ElasticsearchQueryBuilder;
    $builder->index('products');

    $indexes = $builder->getIndexes();

    expect($indexes->toArray())->toBe(['products']);
});

it('getIndexes returns multiple indexes for multi query builder', function () {
    $multiBuilder = new MultiQueryBuilder;

    $multiBuilder->add('products_query', fn ($q) => $q->index('products')->match('name', 'test'));
    $multiBuilder->add('categories_query', fn ($q) => $q->index('categories')->match('title', 'electronics'));

    $indexes = $multiBuilder->getIndexes();

    expect($indexes->toArray())->toContain('products');
    expect($indexes->toArray())->toContain('categories');
});

it('getIndexes returns unique indexes for multi query builder', function () {
    $multiBuilder = new MultiQueryBuilder;

    $multiBuilder->add('products_query_1', fn ($q) => $q->index('products')->match('name', 'test'));
    $multiBuilder->add('products_query_2', fn ($q) => $q->index('products')->match('name', 'another'));
    $multiBuilder->add('categories_query', fn ($q) => $q->index('categories')->match('title', 'electronics'));

    $indexes = $multiBuilder->getIndexes();

    // Should only have 2 unique indexes: products and categories
    expect($indexes->count())->toBe(2);
});

it('errors on calls to undefined methods', function () {
    $builder = new ElasticsearchQueryBuilder;

    $caught = null;

    try {
        $builder->definitelyNotAMethod();
    } catch (Error $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(Error::class)
        ->and($caught->getMessage())->toContain('definitelyNotAMethod');
});

it('forgets the cached entry before executing when clearCache is set', function () {
    $mockClient = Mockery::mock(ClientContract::class);
    $mockClient->shouldReceive('search')
        ->twice()
        ->andReturn(['hits' => ['total' => ['value' => 1]]]);

    // Prime the cache with a first execution.
    $builder = new ElasticsearchQueryBuilder($mockClient);
    $builder->index('test_index')->match('title', 'Laravel')->cache()->execute();

    // A second builder with the same query would normally hit the cache;
    // clearCache() forces the entry out so the client is hit again.
    $cleared = new ElasticsearchQueryBuilder($mockClient);
    $cleared->index('test_index')->match('title', 'Laravel')->cache()->clearCache()->execute();
});

it('executes without caching when cache is disabled', function () {
    $mockClient = Mockery::mock(ClientContract::class);
    $mockClient->shouldReceive('search')
        ->once()
        ->andReturn(['hits' => ['total' => ['value' => 1]]]);

    $builder = new ElasticsearchQueryBuilder($mockClient);
    $builder->index('test_index')
        ->match('title', 'Laravel');

    $result = $builder->execute();

    expect($result)->toBe(['hits' => ['total' => ['value' => 1]]]);
});

it('supports method chaining for all cache configuration', function () {
    $builder = new ElasticsearchQueryBuilder;

    $result = $builder
        ->index('test_index')
        ->match('title', 'Laravel')
        ->cache()
        ->setCacheTtl([600, 1200])
        ->setCachePrefix('search:')
        ->setCacheStore('redis')
        ->clearCache();

    expect($result)->toBeInstanceOf(ElasticsearchQueryBuilder::class);
    expect($result->isCacheEnabled())->toBeTrue();
    expect($result->getCacheTtl())->toBe([600, 1200]);
    expect($result->getCachePrefix())->toBe('search:');
    expect($result->getCacheStore())->toBe('redis');
    expect($result->getCacheClear())->toBeTrue();
});

it('multi query builder can use caching', function () {
    $multiBuilder = new MultiQueryBuilder;

    $multiBuilder
        ->add('products', fn ($q) => $q->match('name', 'test'))
        ->cache()
        ->setCacheTtl([300, 600]);

    expect($multiBuilder->isCacheEnabled())->toBeTrue();
    expect($multiBuilder->getCacheTtl())->toBe([300, 600]);
});

it('respects the cache.enabled config default', function () {
    config(['stretch.cache.enabled' => true]);

    $builder = new ElasticsearchQueryBuilder;

    expect($builder->isCacheEnabled())->toBeTrue();

    // An explicit per-query setting overrides the config default.
    $builder->setCacheEnabled(false);

    expect($builder->isCacheEnabled())->toBeFalse();
});

it('parses comma-separated cache TTL strings from the environment', function () {
    config(['stretch.cache.ttl' => '300,600']);

    $builder = new ElasticsearchQueryBuilder;

    expect($builder->getCacheTtl())->toBe([300, 600]);

    config(['stretch.cache.ttl' => '300']);

    expect($builder->getCacheTtl())->toBe(300);
});

it('serves the second execution from cache without hitting the client', function () {
    $mockClient = Mockery::mock(ClientContract::class);
    $mockClient->shouldReceive('search')
        ->once()
        ->andReturn(['hits' => ['total' => ['value' => 3]]]);

    $builder = new ElasticsearchQueryBuilder($mockClient);
    $builder->index('test_index')
        ->match('title', 'Laravel')
        ->cache()
        ->setCacheTtl(300);

    $first = $builder->execute();
    $second = $builder->execute();

    expect($first)->toBe(['hits' => ['total' => ['value' => 3]]])
        ->and($second)->toBe($first);
});

it('hits the client on every execution when cache is disabled', function () {
    $mockClient = Mockery::mock(ClientContract::class);
    $mockClient->shouldReceive('search')
        ->twice()
        ->andReturn(['hits' => ['total' => ['value' => 1]]]);

    $builder = new ElasticsearchQueryBuilder($mockClient);
    $builder->index('test_index')->match('title', 'Laravel');

    $builder->execute();
    $builder->execute();
});

it('uses flexible caching for TTL pairs and remember for scalar TTLs', function () {
    $mockClient = Mockery::mock(ClientContract::class);
    $mockClient->shouldReceive('search')->andReturn(['hits' => []]);

    $repository = Mockery::mock(Repository::class);
    Cache::shouldReceive('store')->with('array')->andReturn($repository);

    $repository->shouldReceive('flexible')
        ->once()
        ->withArgs(fn ($key, $ttl, $callback) => $ttl === [300, 600])
        ->andReturn(['hits' => []]);

    (new ElasticsearchQueryBuilder($mockClient))
        ->index('test_index')
        ->match('title', 'Laravel')
        ->cache()
        ->setCacheTtl([300, 600])
        ->execute();

    $repository->shouldReceive('remember')
        ->once()
        ->withArgs(fn ($key, $ttl, $callback) => $ttl === 120)
        ->andReturn(['hits' => []]);

    (new ElasticsearchQueryBuilder($mockClient))
        ->index('test_index')
        ->match('title', 'Laravel')
        ->cache()
        ->setCacheTtl(120)
        ->execute();
});

it('includes the connection name in the cache key', function () {
    $manager = Mockery::mock(ElasticsearchManager::class);
    $manager->shouldReceive('getDefaultConnection')->andReturn('default');
    $manager->shouldReceive('connection')->with('analytics')
        ->andReturn(ClientBuilder::create()->setHosts(['localhost:9200'])->build());

    $defaultBuilder = (new ElasticsearchQueryBuilder(Mockery::mock(ClientContract::class), $manager))
        ->index('posts')
        ->match('title', 'Laravel');

    $analyticsBuilder = $defaultBuilder->connection('analytics');

    expect($defaultBuilder->getCacheKey())->not->toBe($analyticsBuilder->getCacheKey())
        ->and($analyticsBuilder->getCacheKey())->toContain('analytics');
});

it('generates cache keys for array indices without errors', function () {
    $builder = new ElasticsearchQueryBuilder;
    $builder->index(['posts', 'comments'])->match('title', 'Laravel');

    expect($builder->getCacheKey())->toContain('posts,comments');
});

it('generates cache keys for multi queries with array indices', function () {
    $multiBuilder = new MultiQueryBuilder;
    $multiBuilder->add('everything', fn ($q) => $q->index(['posts', 'comments'])->match('title', 'Laravel'));

    expect($multiBuilder->getCacheKey())->toContain('posts,comments');
});

it('serves cached multi-search results without hitting the client', function () {
    $mockClient = Mockery::mock(ClientContract::class);
    $mockClient->shouldReceive('msearch')
        ->once()
        ->andReturn(['responses' => [['hits' => ['total' => ['value' => 5]]]]]);

    $multiBuilder = new MultiQueryBuilder($mockClient);
    $multiBuilder
        ->add('posts', fn ($q) => $q->index('posts')->match('title', 'Laravel'))
        ->cache()
        ->setCacheTtl(300);

    $first = $multiBuilder->execute();
    $second = $multiBuilder->execute();

    expect($second)->toBe($first)
        ->and($first['responses'])->toHaveKey('posts');
});

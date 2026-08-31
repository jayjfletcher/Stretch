<?php

declare(strict_types=1);

use Elastic\Elasticsearch\ClientBuilder;
use JayI\Stretch\Builders\ElasticsearchQueryBuilder;
use JayI\Stretch\Contracts\ClientContract;
use JayI\Stretch\ElasticsearchManager;
use JayI\Stretch\Stretch;

it('can use different connections via Stretch facade', function () {
    $defaultClientContract = Mockery::mock(ClientContract::class);
    $manager = Mockery::mock(ElasticsearchManager::class);
    $manager->shouldReceive('connection')->with('alternative')
        ->andReturn(ClientBuilder::create()->setHosts(['localhost:9200'])->build());

    $stretch = new Stretch($defaultClientContract, $manager);

    $connected = $stretch->connection('alternative');
    expect($connected)->toBeInstanceOf(Stretch::class)
        ->and($connected)->not->toBe($stretch);

    // Calling connection without manager setup throws exception
    $stretchWithoutManager = new Stretch($defaultClientContract);
    expect(fn () => $stretchWithoutManager->connection('alternative'))
        ->toThrow(RuntimeException::class, 'Elasticsearch manager not available. Cannot switch connections.');
});

it('can use different connections in query builder', function () {
    $defaultClientContract = Mockery::mock(ClientContract::class);
    $manager = Mockery::mock(ElasticsearchManager::class);
    $manager->shouldReceive('connection')->with('alternative')
        ->andReturn(ClientBuilder::create()->setHosts(['localhost:9200'])->build());

    $queryBuilder = new ElasticsearchQueryBuilder(
        $defaultClientContract,
        $manager
    );

    $connected = $queryBuilder->connection('alternative');
    expect($connected)->toBeInstanceOf(ElasticsearchQueryBuilder::class)
        ->and($connected)->not->toBe($queryBuilder);

    // Calling connection without manager throws exception
    $queryBuilderWithoutManager = new ElasticsearchQueryBuilder($defaultClientContract);
    expect(fn () => $queryBuilderWithoutManager->connection('alternative'))
        ->toThrow(RuntimeException::class, 'Elasticsearch manager not available. Cannot switch connections.');
});

it('throws exception when trying to switch connections without manager', function () {
    $clientContract = Mockery::mock(ClientContract::class);
    $queryBuilder = new ElasticsearchQueryBuilder($clientContract);

    expect(fn () => $queryBuilder->connection('alternative'))
        ->toThrow(RuntimeException::class, 'Elasticsearch manager not available. Cannot switch connections.');
});

it('validates connection configuration', function () {
    $manager = new ElasticsearchManager(app());

    expect(fn () => $manager->connection('nonexistent'))
        ->toThrow(InvalidArgumentException::class, 'Elasticsearch connection [nonexistent] not configured.');
});

/**
 * `connection()` swaps the client on the Stretch instance, but the factories
 * (`query()`, `index()`, `multi()`, `scroll()`) build a *new* instance and only
 * used to forward the client — not the connection name. The query still reached
 * the right cluster, so nothing failed loudly; what broke was everything keyed
 * on the name. The response cache key is namespaced by it, so two connections
 * holding an identically named index shared one cache entry and could serve
 * each other's hits.
 */
it('carries the connection name from Stretch onto the builders it creates', function () {
    $defaultClientContract = Mockery::mock(ClientContract::class);
    $manager = Mockery::mock(ElasticsearchManager::class);
    $manager->shouldReceive('connection')->with('alternative')
        ->andReturn(ClientBuilder::create()->setHosts(['localhost:9200'])->build());
    $manager->shouldReceive('getDefaultConnection')->andReturn('default');

    $connected = (new Stretch($defaultClientContract, $manager))->connection('alternative');

    expect($connected->getConnectionName())->toBe('alternative')
        ->and($connected->query()->getConnectionName())->toBe('alternative')
        ->and($connected->index('posts')->getConnectionName())->toBe('alternative')
        ->and($connected->multi()->getConnectionName())->toBe('alternative')
        ->and($connected->scroll('posts')->getConnectionName())->toBe('alternative');
});

it('leaves builders on the default connection when none was selected', function () {
    $defaultClientContract = Mockery::mock(ClientContract::class);
    $manager = Mockery::mock(ElasticsearchManager::class);
    $manager->shouldReceive('getDefaultConnection')->andReturn('default');

    $stretch = new Stretch($defaultClientContract, $manager);

    expect($stretch->index('posts')->getConnectionName())->toBe('default')
        ->and($stretch->multi()->getConnectionName())->toBe('default');
});

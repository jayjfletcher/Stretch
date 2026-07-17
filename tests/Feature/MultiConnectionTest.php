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

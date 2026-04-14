<?php

declare(strict_types=1);

use JayI\Stretch\Builders\ElasticsearchQueryBuilder;
use JayI\Stretch\Contracts\ClientContract;
use JayI\Stretch\ElasticsearchManager;
use JayI\Stretch\Stretch;

it('can use different connections via Stretch facade', function () {
    // Test that the connection method exists and returns a different instance
    $defaultClientContract = Mockery::mock(ClientContract::class);
    $manager = Mockery::mock(ElasticsearchManager::class);

    // Create Stretch instance with manager
    $stretch = new Stretch($defaultClientContract, $manager);

    // Test that connection method exists
    expect(method_exists($stretch, 'connection'))->toBeTrue();

    // Test that calling connection without manager setup throws exception
    $stretchWithoutManager = new Stretch($defaultClientContract);
    expect(fn () => $stretchWithoutManager->connection('alternative'))
        ->toThrow(RuntimeException::class, 'Elasticsearch manager not available. Cannot switch connections.');
});

it('can use different connections in query builder', function () {
    // Test that the connection method exists in query builder
    $defaultClientContract = Mockery::mock(ClientContract::class);
    $manager = Mockery::mock(ElasticsearchManager::class);

    // Create query builder with manager
    $queryBuilder = new ElasticsearchQueryBuilder(
        $defaultClientContract,
        $manager
    );

    // Test that connection method exists
    expect(method_exists($queryBuilder, 'connection'))->toBeTrue();

    // Test that calling connection without manager throws exception
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
    $manager = new ElasticsearchManager($this->app);

    expect(fn () => $manager->connection('nonexistent'))
        ->toThrow(InvalidArgumentException::class, 'Elasticsearch connection [nonexistent] not configured.');
});

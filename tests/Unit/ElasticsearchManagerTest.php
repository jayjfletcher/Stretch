<?php

declare(strict_types=1);

use JayI\Stretch\ElasticsearchManager;

it('returns the default connection name from config', function () {
    config(['stretch.default' => 'default']);

    $manager = new ElasticsearchManager(app());

    expect($manager->getDefaultConnection())->toBe('default');
});

it('returns all configured connection names', function () {
    config([
        'stretch.connections' => [
            'default' => ['hosts' => ['localhost:9200'], 'ssl_verification' => true],
            'analytics' => ['hosts' => ['analytics:9200'], 'ssl_verification' => true],
        ],
    ]);

    $manager = new ElasticsearchManager(app());

    expect($manager->getConnections())->toBe(['default', 'analytics']);
});

it('throws exception for unconfigured connection', function () {
    config([
        'stretch.connections' => [
            'default' => ['hosts' => ['localhost:9200'], 'ssl_verification' => true],
        ],
    ]);

    $manager = new ElasticsearchManager(app());

    expect(fn () => $manager->connection('nonexistent'))
        ->toThrow(InvalidArgumentException::class, 'Elasticsearch connection [nonexistent] not configured.');
});

it('can purge a cached connection', function () {
    config([
        'stretch.default' => 'default',
        'stretch.logging.enabled' => false,
        'stretch.connections' => [
            'default' => [
                'hosts' => ['localhost:9200'],
                'username' => null,
                'password' => null,
                'cloud_id' => null,
                'api_key' => null,
                'ssl_verification' => true,
            ],
        ],
    ]);

    $manager = new ElasticsearchManager(app());

    // Create a connection to cache it
    $firstConnection = $manager->connection('default');
    // Purge it
    $manager->purge('default');
    // Getting again should create a new instance
    $secondConnection = $manager->connection('default');

    // They should be different instances since purge forces recreation
    expect($firstConnection)->not->toBe($secondConnection);
});

it('can disconnect all cached connections', function () {
    config([
        'stretch.default' => 'default',
        'stretch.logging.enabled' => false,
        'stretch.connections' => [
            'default' => [
                'hosts' => ['localhost:9200'],
                'username' => null,
                'password' => null,
                'cloud_id' => null,
                'api_key' => null,
                'ssl_verification' => true,
            ],
        ],
    ]);

    $manager = new ElasticsearchManager(app());

    // Create a connection
    $firstConnection = $manager->connection('default');
    // Disconnect all
    $manager->disconnect();
    // Getting again should create a new instance
    $secondConnection = $manager->connection('default');

    expect($firstConnection)->not->toBe($secondConnection);
});

it('caches connections and returns same instance', function () {
    config([
        'stretch.default' => 'default',
        'stretch.logging.enabled' => false,
        'stretch.connections' => [
            'default' => [
                'hosts' => ['localhost:9200'],
                'username' => null,
                'password' => null,
                'cloud_id' => null,
                'api_key' => null,
                'ssl_verification' => true,
            ],
        ],
    ]);

    $manager = new ElasticsearchManager(app());

    $first = $manager->connection('default');
    $second = $manager->connection('default');

    expect($first)->toBe($second);
});

it('uses default connection when null is passed', function () {
    config([
        'stretch.default' => 'default',
        'stretch.logging.enabled' => false,
        'stretch.connections' => [
            'default' => [
                'hosts' => ['localhost:9200'],
                'username' => null,
                'password' => null,
                'cloud_id' => null,
                'api_key' => null,
                'ssl_verification' => true,
            ],
        ],
    ]);

    $manager = new ElasticsearchManager(app());

    $defaultConn = $manager->connection();
    $explicitConn = $manager->connection('default');

    expect($defaultConn)->toBe($explicitConn);
});

<?php

declare(strict_types=1);

namespace JayI\Stretch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ConfigException;
use Illuminate\Contracts\Foundation\Application;

/**
 * ElasticsearchManager manages multiple Elasticsearch connections.
 *
 * This class provides functionality to create, manage, and switch between
 * multiple Elasticsearch connections based on the configuration. It supports
 * lazy loading of connections and various authentication methods.
 */
class ElasticsearchManager
{
    /**
     * Array of cached Elasticsearch client connections.
     *
     * @var array<string, Client>
     */
    protected array $connections = [];

    /**
     * Create a new ElasticsearchManager instance.
     *
     * @param  Application  $app  The Laravel application instance
     */
    public function __construct(
        protected Application $app
    ) {}

    /**
     * Get a connection instance by name.
     *
     * If no name is provided, returns the default connection.
     * Connections are cached after the first retrieval.
     *
     * @param  string|null  $name  The connection name, or null for default
     * @return Client The Elasticsearch client instance
     */
    public function connection(?string $name = null): Client
    {
        $name = $name ?: $this->getDefaultConnection();

        if (! isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * Get the default connection name from configuration.
     *
     * @return string The name of the default connection
     */
    public function getDefaultConnection(): string
    {
        return $this->app['config']['stretch.default'];
    }

    /**
     * Get all available connection names from configuration.
     *
     * @return array<string> Array of connection names
     */
    public function getConnections(): array
    {
        return array_keys($this->app['config']['stretch.connections']);
    }

    /**
     * Create a new Elasticsearch client connection.
     *
     * Builds a new Elasticsearch client based on the configuration for the
     * specified connection name. Supports various authentication methods
     * including basic auth, API keys, and Elastic Cloud.
     *
     * @param  string  $name  The connection name
     * @return Client The configured Elasticsearch client
     *
     * @throws ConfigException
     */
    protected function makeConnection(string $name): Client
    {
        $config = $this->getConnectionConfig($name);

        $clientBuilder = ClientBuilder::create()
            ->setHosts($config['hosts']);

        // Configure authentication
        if (! empty($config['username']) && ! empty($config['password'])) {
            $clientBuilder->setBasicAuthentication($config['username'], $config['password']);
        }

        if (! empty($config['api_key'])) {
            $clientBuilder->setApiKey($config['api_key']);
        }

        if (! empty($config['cloud_id'])) {
            $clientBuilder->setElasticCloudId($config['cloud_id']);
        }

        // SSL configuration
        if (! $config['ssl_verification']) {
            $clientBuilder->setSSLVerification(false);
        }

        if (config('stretch.logging.enabled')) {
            $clientBuilder->setLogger($this->app['log']);
        }

        return $clientBuilder->build();
    }

    /**
     * Get the configuration array for a specific connection.
     *
     * @param  string  $name  The connection name
     * @return array The connection configuration array
     *
     * @throws \InvalidArgumentException If the connection is not configured
     */
    protected function getConnectionConfig(string $name): array
    {
        $connections = $this->app['config']['stretch.connections'];

        if (! isset($connections[$name])) {
            throw new \InvalidArgumentException("Elasticsearch connection [{$name}] not configured.");
        }

        return $connections[$name];
    }

    /**
     * Remove a connection from the cache.
     *
     * This forces the connection to be recreated on the next access.
     *
     * @param  string  $name  The connection name to purge
     */
    public function purge(string $name): void
    {
        unset($this->connections[$name]);
    }

    /**
     * Clear all cached connections.
     *
     * This will force all connections to be recreated on their next access.
     */
    public function disconnect(): void
    {
        $this->connections = [];
    }
}

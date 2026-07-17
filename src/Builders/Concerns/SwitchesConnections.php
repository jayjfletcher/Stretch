<?php

declare(strict_types=1);

namespace JayI\Stretch\Builders\Concerns;

use JayI\Stretch\Client\ElasticsearchClient;

/**
 * Allows an instance to be re-bound to a named Elasticsearch connection.
 *
 * Switching connections clones the current instance and swaps the underlying
 * client, so any state accumulated so far (query clauses, cache settings,
 * etc.) survives the switch and `connection()` can be called at any point in
 * a fluent chain.
 */
trait SwitchesConnections
{
    /**
     * The connection name this instance is bound to, or null for the default.
     */
    protected ?string $connectionName = null;

    /**
     * Switch to a specific Elasticsearch connection.
     *
     * Returns a clone of the current instance bound to the specified
     * connection, preserving all previously accumulated state.
     *
     * @param  string  $name  The connection name as defined in configuration
     * @return static A clone of this instance using the specified connection
     *
     * @throws \RuntimeException If the connection manager is not available
     */
    public function connection(string $name): static
    {
        if (! $this->manager) {
            throw new \RuntimeException('Elasticsearch manager not available. Cannot switch connections.');
        }

        $clone = clone $this;
        $clone->client = new ElasticsearchClient($this->manager->connection($name));
        $clone->connectionName = $name;

        return $clone;
    }

    /**
     * Get the name of the connection this instance is bound to.
     *
     * Falls back to the manager's default connection name, or 'default'
     * when no manager is available.
     */
    public function getConnectionName(): string
    {
        return $this->connectionName ?? $this->manager?->getDefaultConnection() ?? 'default';
    }
}

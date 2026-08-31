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

    /**
     * Bind this instance to a connection name without re-resolving the client.
     *
     * Used by a factory to stamp the name onto the instance it just created
     * from an already-bound one; `connection()` remains the way to *switch* a
     * connection, which swaps the client too.
     *
     * @internal
     */
    public function bindConnectionName(?string $name): static
    {
        $this->connectionName = $name;

        return $this;
    }

    /**
     * Carry this instance's connection binding onto an instance it created.
     *
     * A factory (`query()`, `multi()`, `scroll()`) hands the swapped client to
     * the new instance, so the query already reaches the right cluster — but
     * without the name the new instance reports the *default* connection.
     * That name is not cosmetic: it namespaces the response cache key, so two
     * connections holding an identically named index would otherwise share one
     * cache entry and serve each other's hits.
     *
     * @template TTarget of object
     *
     * @param  TTarget  $target  The instance created from this one
     * @return TTarget The same instance, bound to this connection
     */
    protected function propagateConnectionTo(object $target): object
    {
        if ($this->connectionName !== null && method_exists($target, 'bindConnectionName')) {
            $target->bindConnectionName($this->connectionName);
        }

        return $target;
    }
}

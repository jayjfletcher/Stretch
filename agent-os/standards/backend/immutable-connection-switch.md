# Immutable Connection Switch

`connection()` CLONES the instance and swaps the client — it does NOT mutate in place.

Exception to the mutate-and-return-`$this` norm. Cloning lets one configured base builder branch to multiple connections without a switch clobbering earlier references, and avoids retroactively mutating shared state mid-chain.

```php
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
```

- All accumulated state (query clauses, cache settings) survives the clone, so `connection()` is valid at any point in the chain.
- Requires `$this->manager`; throw if absent.
- `getConnectionName()` falls back: `$connectionName ?? manager default ?? 'default'`. The name feeds the cache key so per-connection results don't collide.

See [[structural-cache-keys]] for how the connection name scopes the cache key.

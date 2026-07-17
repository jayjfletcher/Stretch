# Lazy Cached Connections

`ElasticsearchManager` builds clients on first use and caches them by name. Purging forgets container singletons too.

```php
public function connection(?string $name = null): Client
{
    $name = $name ?: $this->getDefaultConnection();
    return $this->connections[$name] ??= $this->makeConnection($name);
}
```

- Connections are lazy: never built until requested; cached in `$connections[$name]` thereafter.
- `purge($name)` / `disconnect()` clear the cache AND call `forgetInstance()` on `Client::class`, `ClientContract::class`, `'stretch'`.
  - Long-running runtimes (Octane, workers) resolve those singletons once; without forgetting they'd hold a stale connection after a purge. Forgetting forces a rebuild against a fresh connection and releases the old one.
  - `forgetInstance()` is guarded by `method_exists($this->app, ...)` — no-op outside a full Laravel container (see [[shared-trait-duck-typing]]).

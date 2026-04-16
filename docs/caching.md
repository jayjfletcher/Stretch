# Caching

Stretch supports query result caching using Laravel's cache system with a stale-while-revalidate pattern via Laravel's `flexible()` cache method.

## Enabling Caching

Enable caching on any query or multi-query with `->cache()`:

```php
use JayI\Stretch\Facades\Stretch;

$results = Stretch::index('posts')
    ->match('title', 'Laravel')
    ->cache()
    ->execute();
```

## Cache TTL

Stretch uses Laravel's flexible caching, which supports stale-while-revalidate. The TTL is specified as an array of `[fresh_seconds, stale_seconds]`:

```php
$results = Stretch::index('posts')
    ->match('title', 'Laravel')
    ->cache()
    ->setCacheTtl([300, 600])  // Fresh for 5 min, stale for 10 min
    ->execute();
```

**How it works:**
- During the **fresh** period (0-300s), cached results are returned immediately.
- During the **stale** period (300-600s), cached results are returned but a background refresh is triggered.
- After the **stale** period expires (600s+), the cache entry is gone and the next request hits Elasticsearch directly.

You can also pass an integer for a simple TTL without the stale-while-revalidate behavior:

```php
->setCacheTtl(300)  // Cache for 5 minutes, no stale period
```

## Cache Store

Use a specific Laravel cache store:

```php
$results = Stretch::index('posts')
    ->match('title', 'Laravel')
    ->cache()
    ->setCacheStore('redis')
    ->execute();
```

## Cache Prefix

Add a custom prefix to cache keys:

```php
$results = Stretch::index('posts')
    ->match('title', 'Laravel')
    ->cache()
    ->setCachePrefix('es:posts:')
    ->execute();
```

## Clearing Cache

Force fresh results by clearing the cache entry before executing:

```php
$results = Stretch::index('posts')
    ->match('title', 'Laravel')
    ->cache()
    ->clearCache()
    ->execute();
```

## Full Example

Combine all cache options:

```php
$results = Stretch::index('posts')
    ->match('title', 'Laravel')
    ->term('status', 'published')
    ->cache()
    ->setCacheTtl([600, 1200])
    ->setCachePrefix('es:posts:')
    ->setCacheStore('redis')
    ->execute();
```

## Caching Multi-Queries

The `MultiQueryBuilder` uses the same `IsCacheable` trait:

```php
$results = Stretch::multi()
    ->add('posts', fn ($q) => $q->index('posts')->match('title', 'Laravel'))
    ->add('users', fn ($q) => $q->index('users')->term('status', 'active'))
    ->cache()
    ->setCacheTtl([300, 600])
    ->execute();
```

## Cache Key Generation

Cache keys are automatically generated based on:
- The cache prefix
- The index name(s) involved in the query
- A SHA1 hash of the serialized, sorted query structure

This ensures different queries always produce different cache keys, and identical queries always hit the same cache entry.

## Default Configuration

Set default cache behavior in `config/stretch.php`:

```php
'cache' => [
    'enabled' => env('STRETCH_CACHE_ENABLED', true),
    'ttl' => env('STRETCH_CACHE_TTL', [300, 600]),     // [fresh, stale] in seconds
    'prefix' => env('STRETCH_CACHE_PREFIX', 'stretch:'),
    'store' => env('STRETCH_CACHE_STORE', env('CACHE_STORE', 'database')),
],
```

Per-query settings (e.g., `setCacheTtl()`, `setCachePrefix()`, `setCacheStore()`) override these defaults. The `->cache()` method must still be called on each query to opt in -- the config defaults only control the values, not whether caching is active.

## Methods Reference

| Method | Description |
|--------|-------------|
| `cache()` | Enable caching for this query |
| `clearCache()` | Clear existing cache entry before executing |
| `setCacheTtl(array\|int $ttl)` | Set TTL as `[fresh, stale]` or integer seconds |
| `setCachePrefix(string $prefix)` | Set custom cache key prefix |
| `setCacheStore(string $store)` | Set Laravel cache store name |
| `isCacheEnabled()` | Check if caching is enabled |
| `getCacheKey()` | Get the generated cache key |

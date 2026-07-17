<?php

declare(strict_types=1);

namespace JayI\Stretch\Builders\Concerns;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Provides caching capabilities for Elasticsearch query builders.
 *
 * This trait enables query result caching using Laravel's cache system with
 * flexible TTL support (stale-while-revalidate pattern). Cache keys are
 * automatically generated from the connection name, index names, and query
 * structure. Caching defaults to the `stretch.cache.enabled` config value
 * and can be toggled per query via `cache()` / `setCacheEnabled()`.
 *
 * @example
 * ```php
 * $results = Stretch::index('posts')
 *     ->match('title', 'Laravel')
 *     ->cache()
 *     ->setCacheTtl([300, 600])
 *     ->execute();
 * ```
 */
trait IsCacheable
{
    /**
     * Whether caching is enabled for this query, or null to fall back to
     * the `stretch.cache.enabled` config default.
     */
    protected ?bool $cacheEnabled = null;

    /**
     * Whether to clear the cache before executing the query.
     */
    protected bool $cacheClear = false;

    /**
     * Custom TTL for flexible caching [fresh, stale].
     */
    protected array|int|null $cacheTtl = null;

    /**
     * Custom prefix for cache keys.
     */
    protected ?string $cachePrefix = null;

    /**
     * Custom cache store name.
     */
    protected ?string $cacheStore = null;

    /**
     * Enable caching for this query.
     *
     * @return static Returns the builder instance for method chaining
     */
    public function cache(): static
    {
        return $this->setCacheEnabled();
    }

    /**
     * Enable cache clearing before query execution.
     *
     * This forces a fresh result by invalidating the cached entry
     * before executing the query.
     *
     * @return static Returns the builder instance for method chaining
     */
    public function clearCache(): static
    {
        return $this->setCacheClear();
    }

    /**
     * Check if caching is enabled for this query.
     *
     * @return bool True if caching is enabled
     */
    public function isCacheEnabled(): bool
    {
        return $this->getCacheEnabled();
    }

    /**
     * Set whether caching is enabled.
     *
     * @param  bool  $cacheEnabled  Whether to enable caching
     * @return static Returns the builder instance for method chaining
     */
    public function setCacheEnabled(bool $cacheEnabled = true): static
    {
        $this->cacheEnabled = $cacheEnabled;

        return $this;
    }

    /**
     * Get whether caching is enabled.
     *
     * Returns the per-query setting if one was made, otherwise falls back
     * to the `stretch.cache.enabled` config default.
     *
     * @return bool True if caching is enabled
     */
    public function getCacheEnabled(): bool
    {
        return $this->cacheEnabled ?? (bool) config('stretch.cache.enabled', false);
    }

    /**
     * Set whether to clear the cache before execution.
     *
     * @param  bool  $clear  Whether to clear the cache
     * @return static Returns the builder instance for method chaining
     */
    public function setCacheClear(bool $clear = true): static
    {
        $this->cacheClear = $clear;

        return $this;
    }

    /**
     * Get whether cache clearing is enabled.
     *
     * @return bool True if cache will be cleared before execution
     */
    public function getCacheClear(): bool
    {
        return $this->cacheClear;
    }

    /**
     * Set the cache TTL for flexible caching.
     *
     * Uses Laravel's flexible cache method, which supports stale-while-revalidate.
     * The first value is the "fresh" period, the second is the "stale" period.
     *
     * @param  array|int  $ttl  Array of [fresh_seconds, stale_seconds] or int
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * ->setCacheTtl([300, 600]) // Fresh for 5 min, stale for 10 min
     * ```
     */
    public function setCacheTtl(array|int $ttl): static
    {
        $this->cacheTtl = $ttl;

        return $this;
    }

    /**
     * Get the cache TTL configuration.
     *
     * Returns the custom TTL if set, otherwise falls back to the config
     * value. Config values may be an int, a [fresh, stale] pair, or a
     * comma-separated string such as "300,600" (as supplied through the
     * STRETCH_CACHE_TTL environment variable).
     *
     * @return array|int The TTL array [fresh_seconds, stale_seconds] or int seconds
     */
    public function getCacheTtl(): array|int
    {
        return $this->cacheTtl ?? $this->parseCacheTtl(config('stretch.cache.ttl', [300, 600]));
    }

    /**
     * Normalize a configured TTL value.
     *
     * Comma-separated strings ("300,600") are parsed into an int pair for
     * flexible caching; single values ("300") become a scalar TTL.
     *
     * @param  array|int|string  $ttl  The raw configured TTL value
     * @return array|int The normalized TTL
     */
    protected function parseCacheTtl(array|int|string $ttl): array|int
    {
        if (! is_string($ttl)) {
            return $ttl;
        }

        $parts = array_map(
            static fn (string $part): int => (int) trim($part),
            explode(',', $ttl)
        );

        return count($parts) === 1 ? $parts[0] : [$parts[0], $parts[1]];
    }

    /**
     * Set a custom prefix for the cache key.
     *
     * @param  string  $prefix  The prefix to prepend to cache keys
     * @return static Returns the builder instance for method chaining
     */
    public function setCachePrefix(string $prefix): static
    {
        $this->cachePrefix = $prefix;

        return $this;
    }

    /**
     * Get the cache key prefix.
     *
     * Returns the custom prefix if set, otherwise falls back to config value.
     *
     * @return string The cache key prefix
     */
    public function getCachePrefix(): string
    {
        return $this->cachePrefix ?? config('stretch.cache.prefix', '');
    }

    /**
     * Set a custom cache store.
     *
     * @param  string  $store  The Laravel cache store name
     * @return static Returns the builder instance for method chaining
     */
    public function setCacheStore(string $store): static
    {
        $this->cacheStore = $store;

        return $this;
    }

    /**
     * Get the cache store name.
     *
     * Returns the custom store if set, otherwise falls back to config value.
     *
     * @return string The cache store name
     */
    public function getCacheStore(): string
    {
        return $this->cacheStore ?? config('stretch.cache.store', 'file');
    }

    /**
     * Get the indexes involved in this query.
     *
     * Collects index names from either the single index property (for regular
     * queries) or from multiple queries (for multi-search requests). Array
     * indices are normalized to comma-separated strings.
     *
     * @return Collection<int, string> Collection of unique index names
     */
    public function getIndexes(): Collection
    {
        $indexes = collect([]);

        /** @phpstan-ignore function.alreadyNarrowedType */
        if (method_exists($this, 'getIndex')) {
            $indexes = $indexes->push($this->getIndex());
        }

        /** @phpstan-ignore function.alreadyNarrowedType */
        if (property_exists($this, 'queries')) {
            $indexes = collect($this->queries)->pluck('index');
        }

        return $indexes
            ->filter(fn ($index) => $index !== null)
            ->map(fn ($index): string => implode(',', (array) $index))
            ->unique()
            ->values();
    }

    /**
     * Generate a unique cache key for the current query.
     *
     * The cache key is composed of the prefix, connection name, index names,
     * and a SHA1 hash of the serialized query structure. This ensures
     * different queries — and identical queries executed against different
     * connections — produce different cache keys.
     *
     * @return string The generated cache key
     */
    public function getCacheKey(): string
    {
        $sorted = Arr::sortRecursive($this->build());
        $hash = sha1(serialize($sorted));
        $indexes = $this->getIndexes()->implode(':');

        return $this->getCachePrefix().$this->getConnectionName().':'.$indexes.$hash;
    }

    /**
     * Run the given execution callback through the cache layer.
     *
     * When caching is disabled the callback runs directly. When enabled, the
     * result is cached using Laravel's flexible() for [fresh, stale] TTL
     * pairs or remember() for scalar TTLs. A pending clearCache() request is
     * honoured by forgetting the entry before executing.
     *
     * @param  Closure(): array  $callback  The callback performing the actual request
     * @return array The (possibly cached) response
     */
    protected function executeWithCache(Closure $callback): array
    {
        if (! $this->isCacheEnabled()) {
            return $callback();
        }

        $store = Cache::store($this->getCacheStore());
        $key = $this->getCacheKey();

        if ($this->getCacheClear()) {
            $store->forget($key);
        }

        $ttl = $this->getCacheTtl();

        return is_array($ttl)
            ? $store->flexible($key, $ttl, $callback)
            : $store->remember($key, $ttl, $callback);
    }
}

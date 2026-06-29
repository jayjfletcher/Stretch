<?php

declare(strict_types=1);

namespace JayI\Stretch\Builders\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Provides caching capabilities for Elasticsearch query builders.
 *
 * This trait enables query result caching using Laravel's cache system with
 * flexible TTL support (stale-while-revalidate pattern). Cache keys are
 * automatically generated based on the query structure and index names.
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
     * Whether caching is enabled for this query.
     */
    protected bool $cacheEnabled = false;

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
     * @return bool True if caching is enabled
     */
    public function getCacheEnabled(): bool
    {
        return $this->cacheEnabled;
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
     * Returns the custom TTL if set, otherwise falls back to config value.
     *
     * @return array|int The TTL array [fresh_seconds, stale_seconds]
     */
    public function getCacheTtl(): array|int
    {
        return $this->cacheTtl ?? config('stretch.cache.ttl', [300, 600]);
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
     * Collects index names from either the single index property (for regular queries)
     * or from multiple queries (for multi-search requests).
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
            $indexes = collect($this->queries)->pluck('index')->unique();
        }

        return $indexes;
    }

    /**
     * Generate a unique cache key for the current query.
     *
     * The cache key is composed of the prefix, index names, and a SHA1 hash
     * of the serialized query structure. This ensures different queries
     * produce different cache keys.
     *
     * @param  bool  $clear  Whether to clear the existing cache entry
     * @return string The generated cache key
     */
    public function getCacheKey(bool $clear = false): string
    {
        $sorted = Arr::sortRecursive($this->build());
        $hash = sha1(serialize($sorted));
        $indexes = $this->getIndexes()->implode(':');

        $key = $this->getCachePrefix().$indexes.$hash;

        if ($clear) {
            Cache::store($this->getCacheStore())->forget($key);
        }

        return $key;
    }

    /**
     * Magic method to intercept method calls for caching support.
     *
     * When caching is enabled and the 'execute' method is called, this method
     * wraps the execution with Laravel's flexible cache to provide
     * stale-while-revalidate caching behavior.
     *
     * @param  string  $name  The method name being called
     * @param  array  $arguments  The method arguments
     * @return mixed The method result, potentially cached
     */
    public function __call(string $name, array $arguments)
    {
        $callback = fn () => call_user_func_array([$this, $name], $arguments);
        $ttl = $this->getCacheTtl();
        $method = is_array($ttl) ? 'flexible' : 'remember';

        $arguments = [
            'key' => $this->getCacheKey($this->getCacheClear()),
            'ttl' => $ttl,
            'callback' => $callback,
        ];

        return when(
            condition: $this->isCacheEnabled() && ($name == 'execute'),
            value: fn () => Cache::store($this->getCacheStore())->{$method}(...$arguments),
            default: $callback,
        );
    }
}

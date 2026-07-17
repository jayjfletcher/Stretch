<?php

declare(strict_types=1);

namespace JayI\Stretch\Builders;

use JayI\Stretch\Contracts\BoolQueryBuilderContract;
use JayI\Stretch\Contracts\QueryBuilderContract;

/**
 * Builds Elasticsearch bool queries with must, should, filter, and must_not clauses.
 *
 * Bool queries combine multiple query clauses with different occurrence types:
 * - must: Clauses that must match (AND logic, affects scoring)
 * - should: Clauses that should match (OR logic, affects scoring)
 * - filter: Clauses that must match (AND logic, no scoring - faster)
 * - must_not: Clauses that must not match (NOT logic)
 *
 * @example
 * ```php
 * $builder->bool(function ($bool) {
 *     $bool->must(fn($q) => $q->match('title', 'Laravel'));
 *     $bool->filter(fn($q) => $q->term('status', 'published'));
 *     $bool->should(fn($q) => $q->term('featured', true));
 *     $bool->minimumShouldMatch(1);
 * });
 * ```
 */
class BoolQueryBuilder implements BoolQueryBuilderContract
{
    /**
     * Must clauses (AND logic with scoring).
     *
     * @var array<int, array>
     */
    protected array $must = [];

    /**
     * Should clauses (OR logic with scoring).
     *
     * @var array<int, array>
     */
    protected array $should = [];

    /**
     * Filter clauses (AND logic without scoring).
     *
     * @var array<int, array>
     */
    protected array $filter = [];

    /**
     * Must not clauses (NOT logic).
     *
     * @var array<int, array>
     */
    protected array $mustNot = [];

    /**
     * Minimum number of should clauses that must match.
     */
    protected int|string|null $minimumShouldMatch = null;

    /**
     * Boost factor for the entire bool query.
     */
    protected ?float $boost = null;

    /**
     * Create a new BoolQueryBuilder instance.
     *
     * @param  QueryBuilderContract  $parent  The parent query builder
     */
    public function __construct(
        protected QueryBuilderContract $parent
    ) {}

    /**
     * Add must clauses to the bool query.
     *
     * Documents must match these clauses. Contributes to scoring.
     * Callbacks that add no query clauses are skipped.
     *
     * @param  callable|array<callable>  $callback  Single callback or array of callbacks
     * @return static Returns the builder instance for method chaining
     */
    public function must(callable|array $callback): static
    {
        if (is_callable($callback)) {
            $query = $this->buildSubQuery($callback);

            if ($query !== null) {
                $this->must[] = $query;
            }
        } elseif (is_array($callback)) {
            foreach ($callback as $cb) {
                $this->must($cb);
            }
        }

        return $this;
    }

    /**
     * Add should clauses to the bool query.
     *
     * Documents should match these clauses. Contributes to scoring.
     * Use minimumShouldMatch to require a certain number of matches.
     * Callbacks that add no query clauses are skipped.
     *
     * @param  callable|array<callable>  $callback  Single callback or array of callbacks
     * @return static Returns the builder instance for method chaining
     */
    public function should(callable|array $callback): static
    {
        if (is_callable($callback)) {
            $query = $this->buildSubQuery($callback);

            if ($query !== null) {
                $this->should[] = $query;
            }
        } elseif (is_array($callback)) {
            foreach ($callback as $cb) {
                $this->should($cb);
            }
        }

        return $this;
    }

    /**
     * Add filter clauses to the bool query.
     *
     * Documents must match these clauses but they don't contribute to scoring.
     * Filter clauses are cached by Elasticsearch for better performance.
     * Callbacks that add no query clauses are skipped.
     *
     * @param  callable|array<callable>  $callback  Single callback or array of callbacks
     * @return static Returns the builder instance for method chaining
     */
    public function filter(callable|array $callback): static
    {
        if (is_callable($callback)) {
            $query = $this->buildSubQuery($callback);

            if ($query !== null) {
                $this->filter[] = $query;
            }
        } elseif (is_array($callback)) {
            foreach ($callback as $cb) {
                $this->filter($cb);
            }
        }

        return $this;
    }

    /**
     * Add must_not clauses to the bool query.
     *
     * Documents must not match these clauses.
     * Callbacks that add no query clauses are skipped.
     *
     * @param  callable|array<callable>  $callback  Single callback or array of callbacks
     * @return static Returns the builder instance for method chaining
     */
    public function mustNot(callable|array $callback): static
    {
        if (is_callable($callback)) {
            $query = $this->buildSubQuery($callback);

            if ($query !== null) {
                $this->mustNot[] = $query;
            }
        } elseif (is_array($callback)) {
            foreach ($callback as $cb) {
                $this->mustNot($cb);
            }
        }

        return $this;
    }

    /**
     * Build a sub-query from a callback.
     *
     * Returns null when the callback added no query clauses, allowing the
     * caller to skip the clause instead of emitting invalid DSL.
     *
     * @param  callable  $callback  Callback receiving a fresh query builder
     * @return array|null The built query clause, or null when empty
     */
    protected function buildSubQuery(callable $callback): ?array
    {
        $queryBuilder = new ElasticsearchQueryBuilder;
        $callback($queryBuilder);

        return $queryBuilder->build()['query'] ?? null;
    }

    /**
     * Set the minimum number of should clauses that must match.
     *
     * @param  int|string  $minimum  Number or percentage (e.g., 2 or "75%")
     * @return static Returns the builder instance for method chaining
     */
    public function minimumShouldMatch(int|string $minimum): static
    {
        $this->minimumShouldMatch = $minimum;

        return $this;
    }

    /**
     * Get the parent query builder.
     *
     * @return QueryBuilderContract The parent query builder instance
     */
    public function getParent(): QueryBuilderContract
    {
        return $this->parent;
    }

    /**
     * Set a boost factor on the entire bool query.
     *
     * @param  float  $boost  The boost factor
     * @return static Returns the builder instance for method chaining
     */
    public function boost(float $boost): static
    {
        $this->boost = $boost;

        return $this;
    }

    /**
     * Build the bool query array.
     *
     * Assembles all clauses into a properly formatted Elasticsearch bool query.
     *
     * @return array The Elasticsearch bool query structure
     */
    public function build(): array
    {
        $bool = [];

        if (! empty($this->must)) {
            $bool['must'] = $this->must;
        }

        if (! empty($this->should)) {
            $bool['should'] = $this->should;
        }

        if (! empty($this->filter)) {
            $bool['filter'] = $this->filter;
        }

        if (! empty($this->mustNot)) {
            $bool['must_not'] = $this->mustNot;
        }

        if ($this->minimumShouldMatch !== null) {
            $bool['minimum_should_match'] = $this->minimumShouldMatch;
        }

        if ($this->boost !== null) {
            $bool['boost'] = $this->boost;
        }

        return [
            'bool' => $bool,
        ];
    }
}

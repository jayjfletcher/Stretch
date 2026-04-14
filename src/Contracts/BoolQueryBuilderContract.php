<?php

declare(strict_types=1);

namespace JayI\Stretch\Contracts;

/**
 * Contract for Elasticsearch bool query builders.
 *
 * Defines the interface for combining multiple query clauses with different
 * occurrence types (must, should, filter, must_not) into a single bool query.
 */
interface BoolQueryBuilderContract
{
    /**
     * Add must clauses (AND logic with scoring).
     *
     * @param  callable|array<callable>  $callback  Single callback or array of callbacks
     * @return static Returns the builder instance for method chaining
     */
    public function must(callable|array $callback): static;

    /**
     * Add should clauses (OR logic with scoring).
     *
     * @param  callable|array<callable>  $callback  Single callback or array of callbacks
     * @return static Returns the builder instance for method chaining
     */
    public function should(callable|array $callback): static;

    /**
     * Add filter clauses (AND logic, no scoring, cached).
     *
     * @param  callable|array<callable>  $callback  Single callback or array of callbacks
     * @return static Returns the builder instance for method chaining
     */
    public function filter(callable|array $callback): static;

    /**
     * Add must_not clauses (NOT logic).
     *
     * @param  callable|array<callable>  $callback  Single callback or array of callbacks
     * @return static Returns the builder instance for method chaining
     */
    public function mustNot(callable|array $callback): static;

    /**
     * Set the minimum number of should clauses that must match.
     *
     * @param  int|string  $minimum  Number or percentage (e.g., 2 or "75%")
     * @return static Returns the builder instance for method chaining
     */
    public function minimumShouldMatch(int|string $minimum): static;

    /**
     * Get the parent query builder.
     *
     * @return QueryBuilderContract The parent query builder instance
     */
    public function getParent(): QueryBuilderContract;

    /**
     * Set a boost factor on the entire bool query.
     *
     * @param  float  $boost  The boost factor
     * @return static Returns the builder instance for method chaining
     */
    public function boost(float $boost): static;

    /**
     * Build the bool query array.
     *
     * @return array The Elasticsearch bool query structure
     */
    public function build(): array;
}

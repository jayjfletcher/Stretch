<?php

declare(strict_types=1);

namespace JayI\Stretch\Contracts;

/**
 * Contract for Elasticsearch multi-search query builders.
 *
 * Defines the interface for combining multiple search queries into a single
 * request, reducing network overhead when executing several searches at once.
 * Each query can target different indices and have its own search criteria.
 */
interface MultiQueryBuilderContract
{
    /**
     * Add a query to the multi-search request
     *
     * @param  string|array  $index  The index or indices to search
     * @param  callable|QueryBuilderContract  $query  A callback or query builder instance
     */
    public function add(string $name, callable|QueryBuilderContract $query): static;

    /**
     * Build the msearch request body
     *
     * @return array The msearch body array with alternating header/body entries
     */
    public function build(): array;

    /**
     * Execute the multi-search and return all results
     *
     * @return array The msearch response with 'responses' array
     */
    public function execute(): array;

    /**
     * Get the raw query array for debugging.
     *
     * @return array The msearch body array
     */
    public function toArray(): array;
}

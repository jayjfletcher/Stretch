<?php

declare(strict_types=1);

namespace JayI\Stretch\Contracts;

/**
 * Contract for Elasticsearch aggregation builders.
 *
 * Defines the interface for building bucket aggregations (terms, date_histogram,
 * range, histogram) and metric aggregations (avg, sum, min, max, count, cardinality)
 * with support for nested sub-aggregations.
 */
interface AggregationBuilderContract
{
    /**
     * Create a terms aggregation for grouping by field values.
     *
     * @param  string  $field  The field to aggregate on (use .keyword for text fields)
     * @return static Returns the builder instance for method chaining
     */
    public function terms(string $field): static;

    /**
     * Create a date histogram aggregation for time-based bucketing.
     *
     * @param  string  $field  The date field to aggregate on
     * @param  string  $interval  Calendar interval (minute, hour, day, week, month, year)
     * @return static Returns the builder instance for method chaining
     */
    public function dateHistogram(string $field, string $interval): static;

    /**
     * Create an average metric aggregation.
     *
     * @param  string  $field  The numeric field to calculate average for
     * @return static Returns the builder instance for method chaining
     */
    public function avg(string $field): static;

    /**
     * Create a sum metric aggregation.
     *
     * @param  string  $field  The numeric field to sum
     * @return static Returns the builder instance for method chaining
     */
    public function sum(string $field): static;

    /**
     * Create a minimum metric aggregation.
     *
     * @param  string  $field  The field to find minimum value
     * @return static Returns the builder instance for method chaining
     */
    public function min(string $field): static;

    /**
     * Create a maximum metric aggregation.
     *
     * @param  string  $field  The field to find maximum value
     * @return static Returns the builder instance for method chaining
     */
    public function max(string $field): static;

    /**
     * Create a document count aggregation.
     *
     * @return static Returns the builder instance for method chaining
     */
    public function count(): static;

    /**
     * Create a cardinality aggregation for counting unique values.
     *
     * @param  string  $field  The field to count unique values for
     * @return static Returns the builder instance for method chaining
     */
    public function cardinality(string $field): static;

    /**
     * Create a range aggregation with custom buckets.
     *
     * @param  string  $field  The numeric field to create ranges for
     * @param  array  $ranges  Array of range definitions with 'from' and/or 'to' keys
     * @return static Returns the builder instance for method chaining
     */
    public function range(string $field, array $ranges): static;

    /**
     * Create a histogram aggregation with fixed-size buckets.
     *
     * @param  string  $field  The numeric field to create histogram for
     * @param  int|float  $interval  The bucket interval size
     * @return static Returns the builder instance for method chaining
     */
    public function histogram(string $field, int|float $interval): static;

    /**
     * Set the maximum number of buckets to return.
     *
     * @param  int  $size  Maximum number of buckets
     * @return static Returns the builder instance for method chaining
     */
    public function size(int $size): static;

    /**
     * Add a nested sub-aggregation.
     *
     * @param  string  $name  Name for the sub-aggregation
     * @param  callable  $callback  Callback receiving an AggregationBuilder
     * @return static Returns the builder instance for method chaining
     */
    public function subAggregation(string $name, callable $callback): static;

    /**
     * Set the ordering for bucket aggregations.
     *
     * @param  string  $field  Field to order by (_count, _key, or sub-aggregation name)
     * @param  string  $direction  Sort direction (asc or desc)
     * @return static Returns the builder instance for method chaining
     */
    public function orderBy(string $field, string $direction = 'asc'): static;

    /**
     * Create a stats metric aggregation.
     *
     * Returns count, min, max, avg, and sum in a single request.
     *
     * @param  string  $field  The numeric field to calculate stats for
     * @return static Returns the builder instance for method chaining
     */
    public function stats(string $field): static;

    /**
     * Set the aggregation to a raw Elasticsearch aggregation array.
     *
     * Escape hatch for aggregation structures not covered by the builder.
     *
     * @param  array  $aggregation  The raw Elasticsearch aggregation definition
     * @return static Returns the builder instance for method chaining
     */
    public function raw(array $aggregation): static;

    /**
     * Build the aggregation array.
     *
     * @return array The Elasticsearch aggregation structure
     */
    public function build(): array;
}

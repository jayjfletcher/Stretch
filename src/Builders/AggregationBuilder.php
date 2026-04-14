<?php

declare(strict_types=1);

namespace JayI\Stretch\Builders;

use JayI\Stretch\Contracts\AggregationBuilderContract;

/**
 * Builds Elasticsearch aggregations for analytics and statistics.
 *
 * Supports bucket aggregations (terms, date_histogram, range, histogram)
 * and metric aggregations (avg, sum, min, max, count, cardinality).
 * Sub-aggregations can be nested for multi-level analytics.
 *
 * @example
 * ```php
 * $builder->aggregation('categories', fn($agg) =>
 *     $agg->terms('category.keyword')
 *         ->size(10)
 *         ->orderBy('_count', 'desc')
 *         ->subAggregation('avg_price', fn($sub) => $sub->avg('price'))
 * );
 * ```
 */
class AggregationBuilder implements AggregationBuilderContract
{
    /**
     * The main aggregation definition.
     */
    protected array $aggregation = [];

    /**
     * Nested sub-aggregations.
     *
     * @var array<string, array>
     */
    protected array $subAggregations = [];

    /**
     * Size limit for bucket aggregations.
     */
    protected ?int $size = null;

    /**
     * Ordering configuration for bucket aggregations.
     */
    protected array $order = [];

    /**
     * Create a terms aggregation for grouping by field values.
     *
     * @param  string  $field  The field to aggregate on (use .keyword for text fields)
     * @return static Returns the builder instance for method chaining
     */
    public function terms(string $field): static
    {
        $this->aggregation = [
            'terms' => [
                'field' => $field,
            ],
        ];

        return $this;
    }

    /**
     * Create a date histogram aggregation for time-based bucketing.
     *
     * @param  string  $field  The date field to aggregate on
     * @param  string  $interval  Calendar interval (minute, hour, day, week, month, year)
     * @return static Returns the builder instance for method chaining
     */
    public function dateHistogram(string $field, string $interval): static
    {
        $this->aggregation = [
            'date_histogram' => [
                'field' => $field,
                'calendar_interval' => $interval,
            ],
        ];

        return $this;
    }

    /**
     * Create an average metric aggregation.
     *
     * @param  string  $field  The numeric field to calculate average for
     * @return static Returns the builder instance for method chaining
     */
    public function avg(string $field): static
    {
        $this->aggregation = [
            'avg' => [
                'field' => $field,
            ],
        ];

        return $this;
    }

    /**
     * Create a sum metric aggregation.
     *
     * @param  string  $field  The numeric field to sum
     * @return static Returns the builder instance for method chaining
     */
    public function sum(string $field): static
    {
        $this->aggregation = [
            'sum' => [
                'field' => $field,
            ],
        ];

        return $this;
    }

    /**
     * Create a minimum metric aggregation.
     *
     * @param  string  $field  The field to find minimum value
     * @return static Returns the builder instance for method chaining
     */
    public function min(string $field): static
    {
        $this->aggregation = [
            'min' => [
                'field' => $field,
            ],
        ];

        return $this;
    }

    /**
     * Create a maximum metric aggregation.
     *
     * @param  string  $field  The field to find maximum value
     * @return static Returns the builder instance for method chaining
     */
    public function max(string $field): static
    {
        $this->aggregation = [
            'max' => [
                'field' => $field,
            ],
        ];

        return $this;
    }

    /**
     * Create a document count aggregation.
     *
     * @return static Returns the builder instance for method chaining
     */
    public function count(): static
    {
        $this->aggregation = [
            'value_count' => [
                'field' => '_id',
            ],
        ];

        return $this;
    }

    /**
     * Create a cardinality aggregation for counting unique values.
     *
     * @param  string  $field  The field to count unique values for
     * @return static Returns the builder instance for method chaining
     */
    public function cardinality(string $field): static
    {
        $this->aggregation = [
            'cardinality' => [
                'field' => $field,
            ],
        ];

        return $this;
    }

    /**
     * Create a range aggregation with custom buckets.
     *
     * @param  string  $field  The numeric field to create ranges for
     * @param  array  $ranges  Array of range definitions with 'from' and/or 'to' keys
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * ->range('price', [
     *     ['to' => 50],
     *     ['from' => 50, 'to' => 100],
     *     ['from' => 100]
     * ])
     * ```
     */
    public function range(string $field, array $ranges): static
    {
        $this->aggregation = [
            'range' => [
                'field' => $field,
                'ranges' => $ranges,
            ],
        ];

        return $this;
    }

    /**
     * Create a histogram aggregation with fixed-size buckets.
     *
     * @param  string  $field  The numeric field to create histogram for
     * @param  int|float  $interval  The bucket interval size
     * @return static Returns the builder instance for method chaining
     */
    public function histogram(string $field, int|float $interval): static
    {
        $this->aggregation = [
            'histogram' => [
                'field' => $field,
                'interval' => $interval,
            ],
        ];

        return $this;
    }

    /**
     * Create a top hits aggregation to retrieve the top matching documents.
     *
     * @param  int  $size  Maximum number of top documents to return (default: 100)
     * @return static Returns the builder instance for method chaining
     */
    public function topHits(int $size = 100): static
    {
        $this->aggregation = [
            'top_hits' => [
                'size' => $size,
            ],
        ];

        return $this;
    }

    /**
     * Set the maximum number of buckets to return.
     *
     * Only applies to bucket aggregations like terms.
     *
     * @param  int  $size  Maximum number of buckets
     * @return static Returns the builder instance for method chaining
     */
    public function size(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    /**
     * Add a nested sub-aggregation.
     *
     * Sub-aggregations run within each bucket of the parent aggregation.
     *
     * @param  string  $name  Name for the sub-aggregation
     * @param  callable  $callback  Callback receiving an AggregationBuilder
     * @return static Returns the builder instance for method chaining
     */
    public function subAggregation(string $name, callable $callback): static
    {
        $subAggBuilder = new self;
        $callback($subAggBuilder);
        $this->subAggregations[$name] = $subAggBuilder->build();

        return $this;
    }

    /**
     * Set the ordering for bucket aggregations.
     *
     * @param  string  $field  Field to order by (_count, _key, or sub-aggregation name)
     * @param  string  $direction  Sort direction (asc or desc)
     * @return static Returns the builder instance for method chaining
     */
    public function orderBy(string $field, string $direction = 'asc'): static
    {
        $this->order = [
            $field => [
                'order' => $direction,
            ],
        ];

        return $this;
    }

    /**
     * Create a stats metric aggregation.
     *
     * Returns count, min, max, avg, and sum in a single request.
     *
     * @param  string  $field  The numeric field to calculate stats for
     * @return static Returns the builder instance for method chaining
     */
    public function stats(string $field): static
    {
        $this->aggregation = [
            'stats' => [
                'field' => $field,
            ],
        ];

        return $this;
    }

    /**
     * Set the aggregation to a raw Elasticsearch aggregation array.
     *
     * Escape hatch for aggregation structures not covered by the builder,
     * such as filtered aggregations, nested aggregations with multiple
     * levels, or any other complex structure.
     *
     * @param  array  $aggregation  The raw Elasticsearch aggregation definition
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $builder->aggregation('filtered_brand', fn($agg) => $agg->raw([
     *     'filter' => ['bool' => ['filter' => $filterClauses]],
     *     'aggs' => ['brand' => ['terms' => ['field' => 'brand', 'size' => 100]]],
     * ]));
     * ```
     */
    public function raw(array $aggregation): static
    {
        $this->aggregation = $aggregation;

        return $this;
    }

    /**
     * Build the aggregation array.
     *
     * Assembles the aggregation with size, ordering, and sub-aggregations.
     *
     * @return array The Elasticsearch aggregation structure
     */
    public function build(): array
    {
        $agg = $this->aggregation;

        // Add size if specified and this is a bucket aggregation
        if (isset($agg['terms'])) {
            $agg['terms']['size'] = min(($this->size ?? config('stretch.aggregations.default_size')), config('stretch.aggregations.max_buckets'));
        }

        // Add order if specified and this is a bucket aggregation
        if (! empty($this->order) && isset($agg['terms'])) {
            $agg['terms']['order'] = $this->order;
        }

        // Add sub-aggregations
        if (! empty($this->subAggregations)) {
            $agg['aggs'] = $this->subAggregations;
        }

        return $agg;
    }
}

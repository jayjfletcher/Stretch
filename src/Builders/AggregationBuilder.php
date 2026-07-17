<?php

declare(strict_types=1);

namespace JayI\Stretch\Builders;

use JayI\Stretch\Contracts\AggregationBuilderContract;
use LogicException;

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
     * Only supported on terms aggregations; build() throws a LogicException
     * when a size is set on an aggregation type that does not support it.
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
     * Supported on terms, histogram, and date_histogram aggregations;
     * build() throws a LogicException when an order is set on an
     * aggregation type that does not support it.
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
     * Create a percentiles metric aggregation.
     *
     * @param  string  $field  The numeric field
     * @param  array|null  $percents  The percentiles to compute (defaults to ES defaults)
     * @return static Returns the builder instance for method chaining
     */
    public function percentiles(string $field, ?array $percents = null): static
    {
        $percentiles = ['field' => $field];

        if ($percents !== null) {
            $percentiles['percents'] = $percents;
        }

        $this->aggregation = ['percentiles' => $percentiles];

        return $this;
    }

    /**
     * Create an extended_stats metric aggregation.
     *
     * Returns the stats plus variance, std deviation, and bounds.
     *
     * @param  string  $field  The numeric field
     * @return static Returns the builder instance for method chaining
     */
    public function extendedStats(string $field): static
    {
        $this->aggregation = ['extended_stats' => ['field' => $field]];

        return $this;
    }

    /**
     * Create a geo_bounds metric aggregation (bounding box of geo points).
     *
     * @param  string  $field  The geo_point field
     * @return static Returns the builder instance for method chaining
     */
    public function geoBounds(string $field): static
    {
        $this->aggregation = ['geo_bounds' => ['field' => $field]];

        return $this;
    }

    /**
     * Create a geo_centroid metric aggregation (weighted centroid of geo points).
     *
     * @param  string  $field  The geo_point field
     * @return static Returns the builder instance for method chaining
     */
    public function geoCentroid(string $field): static
    {
        $this->aggregation = ['geo_centroid' => ['field' => $field]];

        return $this;
    }

    /**
     * Create a filter bucket aggregation.
     *
     * Wraps sub-aggregations so they run only over documents matching the
     * given filter. The callback builds the filter query.
     *
     * @param  callable  $callback  Callback receiving a query builder for the filter
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $agg->filter(fn ($q) => $q->term('status', 'published'))
     *     ->subAggregation('avg_price', fn ($s) => $s->avg('price'));
     * ```
     */
    public function filter(callable $callback): static
    {
        $inner = new ElasticsearchQueryBuilder;
        $callback($inner);

        $this->aggregation = [
            'filter' => $inner->build()['query'] ?? ['match_all' => (object) []],
        ];

        return $this;
    }

    /**
     * Create a nested bucket aggregation over a nested field path.
     *
     * @param  string  $path  The nested field path
     * @return static Returns the builder instance for method chaining
     */
    public function nested(string $path): static
    {
        $this->aggregation = ['nested' => ['path' => $path]];

        return $this;
    }

    /**
     * Create a derivative pipeline aggregation.
     *
     * Computes the derivative of a metric across the buckets of the parent
     * histogram/date_histogram. Must be used as a sub-aggregation.
     *
     * @param  string  $bucketsPath  Path to the metric (e.g. 'sales')
     * @param  array  $options  Extra options (gap_policy, unit, format)
     * @return static Returns the builder instance for method chaining
     */
    public function derivative(string $bucketsPath, array $options = []): static
    {
        $this->aggregation = [
            'derivative' => array_merge(['buckets_path' => $bucketsPath], $options),
        ];

        return $this;
    }

    /**
     * Create a cumulative_sum pipeline aggregation.
     *
     * @param  string  $bucketsPath  Path to the metric to accumulate
     * @param  array  $options  Extra options (format)
     * @return static Returns the builder instance for method chaining
     */
    public function cumulativeSum(string $bucketsPath, array $options = []): static
    {
        $this->aggregation = [
            'cumulative_sum' => array_merge(['buckets_path' => $bucketsPath], $options),
        ];

        return $this;
    }

    /**
     * Create a moving_fn pipeline aggregation.
     *
     * Applies a script over a sliding window of the parent buckets.
     *
     * @param  string  $bucketsPath  Path to the metric
     * @param  string  $script  The window script (e.g. 'MovingFunctions.unweightedAvg(values)')
     * @param  int  $window  The window size
     * @param  array  $options  Extra options (gap_policy, shift)
     * @return static Returns the builder instance for method chaining
     */
    public function movingFn(string $bucketsPath, string $script, int $window, array $options = []): static
    {
        $this->aggregation = [
            'moving_fn' => array_merge([
                'buckets_path' => $bucketsPath,
                'script' => $script,
                'window' => $window,
            ], $options),
        ];

        return $this;
    }

    /**
     * Create a bucket_script pipeline aggregation.
     *
     * Computes a per-bucket metric from sibling aggregations via a script.
     *
     * @param  array  $bucketsPath  Map of script variable to sibling metric path
     * @param  string  $script  The script combining the paths
     * @param  array  $options  Extra options (gap_policy, format)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $agg->bucketScript(
     *     ['sales' => 'total_sales', 'count' => '_count'],
     *     'params.sales / params.count',
     * );
     * ```
     */
    public function bucketScript(array $bucketsPath, string $script, array $options = []): static
    {
        $this->aggregation = [
            'bucket_script' => array_merge([
                'buckets_path' => $bucketsPath,
                'script' => $script,
            ], $options),
        ];

        return $this;
    }

    /**
     * Create a bucket_selector pipeline aggregation.
     *
     * Filters out parent buckets whose script evaluates to false.
     *
     * @param  array  $bucketsPath  Map of script variable to sibling metric path
     * @param  string  $script  A boolean script deciding whether to keep the bucket
     * @param  array  $options  Extra options (gap_policy)
     * @return static Returns the builder instance for method chaining
     */
    public function bucketSelector(array $bucketsPath, string $script, array $options = []): static
    {
        $this->aggregation = [
            'bucket_selector' => array_merge([
                'buckets_path' => $bucketsPath,
                'script' => $script,
            ], $options),
        ];

        return $this;
    }

    /**
     * Create a bucket_sort pipeline aggregation for sorting/paginating buckets.
     *
     * @param  array  $sort  Sort clauses over sibling metrics or bucket keys
     * @param  array  $options  Extra options (from, size, gap_policy)
     * @return static Returns the builder instance for method chaining
     *
     * @example
     * ```php
     * $agg->bucketSort([['total_sales' => ['order' => 'desc']]], ['size' => 5]);
     * ```
     */
    public function bucketSort(array $sort, array $options = []): static
    {
        $this->aggregation = [
            'bucket_sort' => array_merge(['sort' => $sort], $options),
        ];

        return $this;
    }

    /**
     * Create a sibling pipeline aggregation over a metric across buckets.
     *
     * Supports avg_bucket, sum_bucket, min_bucket, max_bucket, and
     * stats_bucket. Must sit as a sibling of the referenced multi-bucket
     * aggregation.
     *
     * @param  string  $type  One of avg_bucket, sum_bucket, min_bucket, max_bucket, stats_bucket
     * @param  string  $bucketsPath  Path to the metric (e.g. 'sales_per_month>sales')
     * @param  array  $options  Extra options (gap_policy, format)
     * @return static Returns the builder instance for method chaining
     */
    public function pipelineBucket(string $type, string $bucketsPath, array $options = []): static
    {
        $this->aggregation = [
            $type => array_merge(['buckets_path' => $bucketsPath], $options),
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
     *
     * @throws LogicException When size() or orderBy() was called on an aggregation type that does not support it
     */
    public function build(): array
    {
        $agg = $this->aggregation;

        // Terms aggregations always receive a size (defaulted from config)
        if (isset($agg['terms'])) {
            $agg['terms']['size'] = min(($this->size ?? config('stretch.aggregations.default_size')), config('stretch.aggregations.max_buckets'));
        } elseif ($this->size !== null) {
            throw new LogicException(
                sprintf('size() is only supported on terms aggregations, [%s] given.', $this->aggregationType())
            );
        }

        if (! empty($this->order)) {
            $orderable = collect(['terms', 'histogram', 'date_histogram'])
                ->first(fn (string $type): bool => isset($agg[$type]));

            if ($orderable === null) {
                throw new LogicException(
                    sprintf('orderBy() is only supported on terms, histogram, and date_histogram aggregations, [%s] given.', $this->aggregationType())
                );
            }

            $agg[$orderable]['order'] = $this->order;
        }

        // Add sub-aggregations
        if (! empty($this->subAggregations)) {
            $agg['aggs'] = $this->subAggregations;
        }

        return $agg;
    }

    /**
     * Get the type key of the configured aggregation for error messages.
     */
    protected function aggregationType(): string
    {
        return (string) (array_key_first($this->aggregation) ?? 'none');
    }
}

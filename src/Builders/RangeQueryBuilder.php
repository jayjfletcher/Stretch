<?php

declare(strict_types=1);

namespace JayI\Stretch\Builders;

use JayI\Stretch\Contracts\QueryBuilderContract;
use JayI\Stretch\Contracts\RangeQueryBuilderContract;

/**
 * Builds Elasticsearch range queries for numeric, date, and string fields.
 *
 * Range queries find documents with field values within specified bounds.
 * Supports greater than, less than, and their inclusive variants, plus
 * timezone and format options for date fields.
 *
 * @example
 * ```php
 * // Numeric range
 * $builder->range('price')->gte(100)->lt(500);
 *
 * // Date range with timezone
 * $builder->range('created_at')
 *     ->gte('2024-01-01')
 *     ->lte('2024-12-31')
 *     ->timezone('America/New_York');
 * ```
 */
class RangeQueryBuilder implements RangeQueryBuilderContract
{
    /**
     * Range conditions (gt, gte, lt, lte).
     *
     * @var array<string, mixed>
     */
    protected array $conditions = [];

    /**
     * Timezone for date range queries.
     */
    protected ?string $timezone = null;

    /**
     * Date format for parsing date strings.
     */
    protected ?string $format = null;

    /**
     * The index of this range clause within the parent builder's query list,
     * or null when no bound has been added to the parent yet.
     */
    protected ?int $parentQueryIndex = null;

    /**
     * Create a new RangeQueryBuilder instance.
     *
     * @param  QueryBuilderContract  $parent  The parent query builder
     * @param  string  $field  The field to create the range query for
     */
    public function __construct(
        protected QueryBuilderContract $parent,
        protected string $field
    ) {}

    /**
     * Set greater than condition (exclusive).
     *
     * @param  mixed  $value  The lower bound (exclusive)
     * @return static Returns the builder instance for method chaining
     */
    public function gt(mixed $value): static
    {
        $this->conditions['gt'] = $value;
        $this->addToParent();

        return $this;
    }

    /**
     * Set greater than or equal condition (inclusive).
     *
     * @param  mixed  $value  The lower bound (inclusive)
     * @return static Returns the builder instance for method chaining
     */
    public function gte(mixed $value): static
    {
        $this->conditions['gte'] = $value;
        $this->addToParent();

        return $this;
    }

    /**
     * Set less than condition (exclusive).
     *
     * @param  mixed  $value  The upper bound (exclusive)
     * @return static Returns the builder instance for method chaining
     */
    public function lt(mixed $value): static
    {
        $this->conditions['lt'] = $value;
        $this->addToParent();

        return $this;
    }

    /**
     * Set less than or equal condition (inclusive).
     *
     * @param  mixed  $value  The upper bound (inclusive)
     * @return static Returns the builder instance for method chaining
     */
    public function lte(mixed $value): static
    {
        $this->conditions['lte'] = $value;
        $this->addToParent();

        return $this;
    }

    /**
     * Set the timezone for date range queries.
     *
     * Dates will be converted from this timezone to UTC for comparison.
     * The clause is only attached to the parent once at least one bound
     * (gt/gte/lt/lte) has been added.
     *
     * @param  string  $timezone  IANA timezone (e.g., "America/New_York")
     * @return static Returns the builder instance for method chaining
     */
    public function timezone(string $timezone): static
    {
        $this->timezone = $timezone;
        $this->addToParent();

        return $this;
    }

    /**
     * Set the date format for parsing date strings.
     *
     * The clause is only attached to the parent once at least one bound
     * (gt/gte/lt/lte) has been added.
     *
     * @param  string  $format  Elasticsearch date format pattern
     * @return static Returns the builder instance for method chaining
     */
    public function format(string $format): static
    {
        $this->format = $format;
        $this->addToParent();

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
     * Build the range query array.
     *
     * @return array The Elasticsearch range query structure
     */
    public function build(): array
    {
        $range = $this->conditions;

        if ($this->timezone) {
            $range['time_zone'] = $this->timezone;
        }

        if ($this->format) {
            $range['format'] = $this->format;
        }

        return [
            'range' => [
                $this->field => $range,
            ],
        ];
    }

    /**
     * Add or update this range query in the parent builder.
     *
     * Nothing is attached until at least one bound (gt/gte/lt/lte) exists,
     * so timezone()/format() calls alone never emit an invalid clause. On
     * first attachment the clause index is remembered, and subsequent calls
     * replace that exact clause — keeping interleaved range builders for the
     * same field independent of each other.
     */
    protected function addToParent(): void
    {
        if ($this->conditions === []) {
            return;
        }

        if ($this->parentQueryIndex === null) {
            $this->parentQueryIndex = $this->getParent()->addQuery($this->build());
        } else {
            $this->getParent()->replaceQuery($this->parentQueryIndex, $this->build());
        }
    }
}

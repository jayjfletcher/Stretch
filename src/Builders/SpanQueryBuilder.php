<?php

declare(strict_types=1);

namespace JayI\Stretch\Builders;

/**
 * Fluent builder for span queries.
 *
 * Span queries are low-level positional queries that match terms whose
 * positions relative to one another satisfy proximity and ordering
 * constraints. They compose: `spanNear`, `spanOr`, `spanNot`, `spanFirst`,
 * `spanWithin`, and `spanContaining` all take other span clauses (built with
 * this same builder) as operands.
 *
 * Each factory method returns the raw span clause array so clauses can be
 * nested inline. The clause assigned last via one of the terminal builders is
 * also stored and returned by `build()`.
 *
 * @example
 * ```php
 * $builder->span(function ($s) {
 *     return $s->spanNear([
 *         $s->spanTerm('text', 'quick'),
 *         $s->spanTerm('text', 'fox'),
 *     ], slop: 3, inOrder: true);
 * });
 * ```
 */
class SpanQueryBuilder
{
    /**
     * The span clause emitted by build() when the builder is used as the
     * terminal producer (i.e. the callback returns nothing).
     */
    protected ?array $span = null;

    /**
     * Build a span_term clause matching a single term.
     *
     * @param  string  $field  The field to match
     * @param  mixed  $value  The exact term
     * @param  array  $options  Additional options (boost)
     * @return array The span_term clause
     */
    public function spanTerm(string $field, mixed $value, array $options = []): array
    {
        $term = empty($options) ? $value : array_merge(['value' => $value], $options);

        return $this->remember([
            'span_term' => [$field => $term],
        ]);
    }

    /**
     * Build a span_multi clause wrapping a multi-term query (prefix, wildcard, fuzzy, regexp, range).
     *
     * @param  array  $match  The wrapped multi-term query clause
     * @return array The span_multi clause
     */
    public function spanMulti(array $match): array
    {
        return $this->remember([
            'span_multi' => ['match' => $match],
        ]);
    }

    /**
     * Build a span_near clause requiring spans within `slop` positions.
     *
     * @param  array  $clauses  The span clauses to combine
     * @param  int  $slop  Maximum allowed position distance between spans
     * @param  bool  $inOrder  Whether the spans must appear in the given order
     * @param  array  $options  Additional options (boost)
     * @return array The span_near clause
     */
    public function spanNear(array $clauses, int $slop = 0, bool $inOrder = true, array $options = []): array
    {
        return $this->remember([
            'span_near' => array_merge([
                'clauses' => $clauses,
                'slop' => $slop,
                'in_order' => $inOrder,
            ], $options),
        ]);
    }

    /**
     * Build a span_or clause matching any of the given spans.
     *
     * @param  array  $clauses  The span clauses
     * @param  array  $options  Additional options (boost)
     * @return array The span_or clause
     */
    public function spanOr(array $clauses, array $options = []): array
    {
        return $this->remember([
            'span_or' => array_merge(['clauses' => $clauses], $options),
        ]);
    }

    /**
     * Build a span_not clause excluding spans that overlap an exclusion span.
     *
     * @param  array  $include  The span that must match
     * @param  array  $exclude  The span that must not overlap
     * @param  array  $options  Additional options (pre, post, dist, boost)
     * @return array The span_not clause
     */
    public function spanNot(array $include, array $exclude, array $options = []): array
    {
        return $this->remember([
            'span_not' => array_merge([
                'include' => $include,
                'exclude' => $exclude,
            ], $options),
        ]);
    }

    /**
     * Build a span_first clause matching spans near the start of the field.
     *
     * @param  array  $match  The span that must match
     * @param  int  $end  Maximum end position (exclusive)
     * @param  array  $options  Additional options (boost)
     * @return array The span_first clause
     */
    public function spanFirst(array $match, int $end, array $options = []): array
    {
        return $this->remember([
            'span_first' => array_merge([
                'match' => $match,
                'end' => $end,
            ], $options),
        ]);
    }

    /**
     * Build a span_within clause matching spans enclosed by a bigger span.
     *
     * @param  array  $little  The span that must be enclosed
     * @param  array  $big  The enclosing span
     * @param  array  $options  Additional options (boost)
     * @return array The span_within clause
     */
    public function spanWithin(array $little, array $big, array $options = []): array
    {
        return $this->remember([
            'span_within' => array_merge([
                'little' => $little,
                'big' => $big,
            ], $options),
        ]);
    }

    /**
     * Build a span_containing clause matching spans that enclose a smaller span.
     *
     * @param  array  $little  The enclosed span
     * @param  array  $big  The span that must enclose it
     * @param  array  $options  Additional options (boost)
     * @return array The span_containing clause
     */
    public function spanContaining(array $little, array $big, array $options = []): array
    {
        return $this->remember([
            'span_containing' => array_merge([
                'little' => $little,
                'big' => $big,
            ], $options),
        ]);
    }

    /**
     * Return the terminal span clause produced by this builder.
     *
     * @return array The span clause
     *
     * @throws \RuntimeException If no span clause has been built
     */
    public function build(): array
    {
        if ($this->span === null) {
            throw new \RuntimeException('No span clause built on SpanQueryBuilder.');
        }

        return $this->span;
    }

    /**
     * Store the most recently built clause as the terminal clause.
     */
    protected function remember(array $clause): array
    {
        $this->span = $clause;

        return $clause;
    }
}

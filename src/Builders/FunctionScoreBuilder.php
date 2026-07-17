<?php

declare(strict_types=1);

namespace JayI\Stretch\Builders;

use JayI\Stretch\Contracts\ClientContract;
use JayI\Stretch\ElasticsearchManager;

/**
 * Fluent builder for function_score queries.
 *
 * Composes an inner query with one or more scoring functions
 * (field_value_factor, decay functions, random_score, script_score, weight),
 * plus the score_mode / boost_mode combination strategy and optional
 * min_score / max_boost bounds.
 *
 * @example
 * ```php
 * $builder->functionScore(function ($fs) {
 *     $fs->query(fn ($q) => $q->match('title', 'laravel'))
 *         ->fieldValueFactor('popularity', modifier: 'log1p', factor: 1.2)
 *         ->gauss('created_at', origin: 'now', scale: '10d')
 *         ->scoreMode('sum')
 *         ->boostMode('multiply');
 * });
 * ```
 */
class FunctionScoreBuilder
{
    /**
     * The inner query the scoring functions apply to.
     */
    protected ?array $query = null;

    /**
     * The scoring functions to apply.
     *
     * @var array<int, array>
     */
    protected array $functions = [];

    /**
     * How the individual function scores are combined (multiply, sum, avg, first, max, min).
     */
    protected ?string $scoreMode = null;

    /**
     * How the combined function score is combined with the query score.
     */
    protected ?string $boostMode = null;

    /**
     * Minimum score a document must reach to be included.
     */
    protected ?float $minScore = null;

    /**
     * Ceiling applied to the combined function score.
     */
    protected ?float $maxBoost = null;

    /**
     * Create a new FunctionScoreBuilder instance.
     */
    public function __construct(
        protected ?ClientContract $client = null,
        protected ?ElasticsearchManager $manager = null
    ) {}

    /**
     * Set the inner query the scoring functions apply to.
     *
     * @param  callable  $callback  Callback receiving a query builder
     * @return static Returns the builder instance for method chaining
     */
    public function query(callable $callback): static
    {
        $inner = new ElasticsearchQueryBuilder($this->client, $this->manager);
        $callback($inner);

        $this->query = $inner->build()['query'] ?? null;

        return $this;
    }

    /**
     * Add a field_value_factor function to derive the score from a numeric field.
     *
     * @param  string  $field  The numeric field
     * @param  string|null  $modifier  Modifier applied to the value (none, log, log1p, ln, sqrt, square, reciprocal, ...)
     * @param  float|null  $factor  Multiplier applied to the field value before the modifier
     * @param  float|int|null  $missing  Value used when the field is absent
     * @param  array  $options  Extra options merged into the function (e.g. filter, weight)
     * @return static Returns the builder instance for method chaining
     */
    public function fieldValueFactor(string $field, ?string $modifier = null, ?float $factor = null, float|int|null $missing = null, array $options = []): static
    {
        $fvf = ['field' => $field];

        if ($modifier !== null) {
            $fvf['modifier'] = $modifier;
        }

        if ($factor !== null) {
            $fvf['factor'] = $factor;
        }

        if ($missing !== null) {
            $fvf['missing'] = $missing;
        }

        $this->addFunction(['field_value_factor' => $fvf], $options);

        return $this;
    }

    /**
     * Add a random_score function for randomised ordering.
     *
     * @param  int|string|null  $seed  Seed for reproducible randomisation
     * @param  string|null  $field  Field used together with the seed (defaults to _seq_no)
     * @param  array  $options  Extra options merged into the function (e.g. filter, weight)
     * @return static Returns the builder instance for method chaining
     */
    public function randomScore(int|string|null $seed = null, ?string $field = null, array $options = []): static
    {
        $random = [];

        if ($seed !== null) {
            $random['seed'] = $seed;
        }

        if ($field !== null) {
            $random['field'] = $field;
        }

        // An empty random_score must serialise as {} not []; with keys it is an object already.
        $this->addFunction(['random_score' => $random === [] ? (object) [] : $random], $options);

        return $this;
    }

    /**
     * Add a script_score function for arbitrary scoring logic.
     *
     * @param  array  $script  The script definition (source/id, params, lang)
     * @param  array  $options  Extra options merged into the function (e.g. filter, weight)
     * @return static Returns the builder instance for method chaining
     */
    public function scriptScore(array $script, array $options = []): static
    {
        $this->addFunction(['script_score' => ['script' => $script]], $options);

        return $this;
    }

    /**
     * Add a standalone weight function (optionally scoped by a filter).
     *
     * @param  float  $weight  The weight to apply
     * @param  callable|null  $filter  Optional filter scoping the weight
     * @return static Returns the builder instance for method chaining
     */
    public function weight(float $weight, ?callable $filter = null): static
    {
        $function = ['weight' => $weight];

        if ($filter !== null) {
            $inner = new ElasticsearchQueryBuilder($this->client, $this->manager);
            $filter($inner);
            $query = $inner->build()['query'] ?? null;

            if ($query !== null) {
                $function['filter'] = $query;
            }
        }

        $this->functions[] = $function;

        return $this;
    }

    /**
     * Add a gauss decay function.
     *
     * @param  string  $field  The date, numeric, or geo_point field
     * @param  mixed  $origin  The origin the score decays from
     * @param  mixed  $scale  The distance at which the score reaches `decay`
     * @param  array  $params  Extra decay params (offset, decay) and function options (filter, weight)
     * @return static Returns the builder instance for method chaining
     */
    public function gauss(string $field, mixed $origin, mixed $scale, array $params = []): static
    {
        return $this->decay('gauss', $field, $origin, $scale, $params);
    }

    /**
     * Add a linear decay function.
     *
     * @param  string  $field  The date, numeric, or geo_point field
     * @param  mixed  $origin  The origin the score decays from
     * @param  mixed  $scale  The distance at which the score reaches `decay`
     * @param  array  $params  Extra decay params (offset, decay) and function options (filter, weight)
     * @return static Returns the builder instance for method chaining
     */
    public function linear(string $field, mixed $origin, mixed $scale, array $params = []): static
    {
        return $this->decay('linear', $field, $origin, $scale, $params);
    }

    /**
     * Add an exponential (exp) decay function.
     *
     * @param  string  $field  The date, numeric, or geo_point field
     * @param  mixed  $origin  The origin the score decays from
     * @param  mixed  $scale  The distance at which the score reaches `decay`
     * @param  array  $params  Extra decay params (offset, decay) and function options (filter, weight)
     * @return static Returns the builder instance for method chaining
     */
    public function exp(string $field, mixed $origin, mixed $scale, array $params = []): static
    {
        return $this->decay('exp', $field, $origin, $scale, $params);
    }

    /**
     * Set how the individual function scores are combined.
     *
     * @param  string  $mode  multiply, sum, avg, first, max, or min
     * @return static Returns the builder instance for method chaining
     */
    public function scoreMode(string $mode): static
    {
        $this->scoreMode = $mode;

        return $this;
    }

    /**
     * Set how the combined function score is combined with the query score.
     *
     * @param  string  $mode  multiply, replace, sum, avg, max, or min
     * @return static Returns the builder instance for method chaining
     */
    public function boostMode(string $mode): static
    {
        $this->boostMode = $mode;

        return $this;
    }

    /**
     * Set the minimum score a document must reach to be included.
     *
     * @return static Returns the builder instance for method chaining
     */
    public function minScore(float $minScore): static
    {
        $this->minScore = $minScore;

        return $this;
    }

    /**
     * Set the ceiling applied to the combined function score.
     *
     * @return static Returns the builder instance for method chaining
     */
    public function maxBoost(float $maxBoost): static
    {
        $this->maxBoost = $maxBoost;

        return $this;
    }

    /**
     * Build the function_score body.
     *
     * @return array The function_score clause contents
     */
    public function build(): array
    {
        $body = [];

        if ($this->query !== null) {
            $body['query'] = $this->query;
        }

        if (! empty($this->functions)) {
            $body['functions'] = $this->functions;
        }

        if ($this->scoreMode !== null) {
            $body['score_mode'] = $this->scoreMode;
        }

        if ($this->boostMode !== null) {
            $body['boost_mode'] = $this->boostMode;
        }

        if ($this->minScore !== null) {
            $body['min_score'] = $this->minScore;
        }

        if ($this->maxBoost !== null) {
            $body['max_boost'] = $this->maxBoost;
        }

        return $body;
    }

    /**
     * Build and register a decay function.
     *
     * The `filter` and `weight` keys in $params are lifted to the function
     * level; all other keys form the decay field configuration.
     */
    protected function decay(string $type, string $field, mixed $origin, mixed $scale, array $params): static
    {
        $options = [];

        foreach (['filter', 'weight'] as $key) {
            if (array_key_exists($key, $params)) {
                $options[$key] = $params[$key];
                unset($params[$key]);
            }
        }

        $decay = array_merge([
            'origin' => $origin,
            'scale' => $scale,
        ], $params);

        $this->addFunction([$type => [$field => $decay]], $options);

        return $this;
    }

    /**
     * Register a scoring function, merging any function-level options.
     *
     * A `filter` option provided as a callable is resolved against a fresh
     * query builder; provided as an array it is used verbatim.
     */
    protected function addFunction(array $function, array $options = []): void
    {
        if (isset($options['filter']) && is_callable($options['filter'])) {
            $inner = new ElasticsearchQueryBuilder($this->client, $this->manager);
            ($options['filter'])($inner);
            $options['filter'] = $inner->build()['query'] ?? null;

            if ($options['filter'] === null) {
                unset($options['filter']);
            }
        }

        $this->functions[] = array_merge($function, $options);
    }
}

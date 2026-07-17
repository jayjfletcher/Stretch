<?php

declare(strict_types=1);

namespace JayI\Stretch\Builders;

/**
 * Fluent builder for the search `suggest` clause.
 *
 * Composes term, phrase, and completion suggesters. Each suggester is
 * registered under a caller-chosen name and returned in the response under
 * the same name inside the `suggest` key.
 *
 * @example
 * ```php
 * $builder->suggest(function ($s) {
 *     $s->term('spellcheck', 'title', 'laravle');
 *     $s->phrase('did_you_mean', 'title', 'quik brown fox');
 *     $s->completion('autocomplete', 'title_suggest', 'lara');
 * });
 * ```
 */
class SuggestBuilder
{
    /**
     * The accumulated named suggesters.
     *
     * @var array<string, array>
     */
    protected array $suggesters = [];

    /**
     * Global suggest `text` shared by suggesters that omit their own.
     */
    protected ?string $globalText = null;

    /**
     * Set the global suggest text applied to suggesters without their own text.
     *
     * @param  string  $text  The text to analyse for suggestions
     * @return static Returns the builder instance for method chaining
     */
    public function text(string $text): static
    {
        $this->globalText = $text;

        return $this;
    }

    /**
     * Add a term suggester for token-level spell correction.
     *
     * @param  string  $name  The suggester name (key in the response)
     * @param  string  $field  The field to draw suggestions from
     * @param  string|null  $text  The text to correct (falls back to the global text)
     * @param  array  $options  Extra term-suggester options (sort, suggest_mode, max_edits, etc.)
     * @return static Returns the builder instance for method chaining
     */
    public function term(string $name, string $field, ?string $text = null, array $options = []): static
    {
        $this->suggesters[$name] = $this->withText([
            'term' => array_merge(['field' => $field], $options),
        ], $text);

        return $this;
    }

    /**
     * Add a phrase suggester for whole-phrase "did you mean" correction.
     *
     * @param  string  $name  The suggester name (key in the response)
     * @param  string  $field  The field to draw suggestions from
     * @param  string|null  $text  The text to correct (falls back to the global text)
     * @param  array  $options  Extra phrase-suggester options (gram_size, highlight, collate, etc.)
     * @return static Returns the builder instance for method chaining
     */
    public function phrase(string $name, string $field, ?string $text = null, array $options = []): static
    {
        $this->suggesters[$name] = $this->withText([
            'phrase' => array_merge(['field' => $field], $options),
        ], $text);

        return $this;
    }

    /**
     * Add a completion suggester for fast prefix autocomplete.
     *
     * Requires a `completion`-typed field. The input text is used as the
     * `prefix` unless a `regex` or explicit `prefix` is supplied via $options.
     *
     * @param  string  $name  The suggester name (key in the response)
     * @param  string  $field  The completion field
     * @param  string  $prefix  The prefix to complete
     * @param  array  $options  Extra completion-suggester options (size, skip_duplicates, fuzzy, contexts, etc.)
     * @return static Returns the builder instance for method chaining
     */
    public function completion(string $name, string $field, string $prefix, array $options = []): static
    {
        $this->suggesters[$name] = [
            'prefix' => $prefix,
            'completion' => array_merge(['field' => $field], $options),
        ];

        return $this;
    }

    /**
     * Add a raw suggester definition.
     *
     * Escape hatch for suggester structures not covered by the typed helpers.
     *
     * @param  string  $name  The suggester name (key in the response)
     * @param  array  $definition  The full suggester definition
     * @return static Returns the builder instance for method chaining
     */
    public function raw(string $name, array $definition): static
    {
        $this->suggesters[$name] = $definition;

        return $this;
    }

    /**
     * Build the suggest clause.
     *
     * @return array The suggest clause for the search body
     */
    public function build(): array
    {
        $suggest = $this->suggesters;

        if ($this->globalText !== null) {
            $suggest['text'] = $this->globalText;
        }

        return $suggest;
    }

    /**
     * Attach a per-suggester `text`, when provided.
     */
    protected function withText(array $suggester, ?string $text): array
    {
        if ($text !== null) {
            $suggester['text'] = $text;
        }

        return $suggester;
    }
}

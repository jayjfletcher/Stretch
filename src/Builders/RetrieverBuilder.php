<?php

declare(strict_types=1);

namespace JayI\Stretch\Builders;

/**
 * Fluent builder for Elasticsearch retrievers (hybrid search).
 *
 * Retrievers are the modern Elasticsearch API for composing hybrid search
 * pipelines. A retriever can be:
 *
 *  - `standard`: wraps a regular query DSL clause
 *  - `knn`: approximate k-nearest-neighbour vector search
 *  - `rrf`: Reciprocal Rank Fusion over multiple sub-retrievers
 *
 * The terminal retriever on the builder is the one set via `standard()`,
 * `knn()`, or `rrf()` (whichever is called last). Sub-retrievers for `rrf()`
 * are built with the same helpers but returned as arrays rather than mutating
 * the builder — use them inline via `$r->rrf([$r->standard(...), $r->knn(...)])`.
 *
 * Requires Elasticsearch 8.14+ for retrievers; 8.15+ for stable RRF.
 */
class RetrieverBuilder
{
    /**
     * The retriever that will be emitted as the top-level retriever clause.
     */
    protected ?array $retriever = null;

    /**
     * Set the top-level retriever to a `standard` retriever.
     *
     * A standard retriever wraps a regular query DSL clause. Call with a
     * callback to build the inner query using a fresh query builder.
     *
     * When used inline (e.g. as a sub-retriever for `rrf()`), this method
     * still mutates the builder's current retriever — pass the return value
     * directly to `rrf()` rather than storing it.
     *
     * @param  callable  $callback  Callback receiving an ElasticsearchQueryBuilder
     * @param  array  $options  Extra options (filter, min_score, etc.)
     * @return array The built standard retriever clause
     */
    public function standard(callable $callback, array $options = []): array
    {
        $queryBuilder = new ElasticsearchQueryBuilder;
        $callback($queryBuilder);

        $standard = array_merge(['query' => $queryBuilder->build()['query']], $options);
        $retriever = ['standard' => $standard];

        $this->retriever = $retriever;

        return $retriever;
    }

    /**
     * Set the top-level retriever to a `knn` retriever.
     *
     * @param  string  $field  The dense_vector field to search
     * @param  array  $queryVector  The query vector
     * @param  int  $k  Number of nearest neighbours to return
     * @param  int|null  $numCandidates  Candidates per shard (defaults to max(k*10, 100))
     * @param  array  $options  Extra options (filter, similarity, query_vector_builder, etc.)
     * @return array The built knn retriever clause
     */
    public function knn(string $field, array $queryVector, int $k = 10, ?int $numCandidates = null, array $options = []): array
    {
        $knn = array_merge([
            'field' => $field,
            'query_vector' => $queryVector,
            'k' => $k,
            'num_candidates' => $numCandidates ?? max($k * 10, 100),
        ], $options);

        $retriever = ['knn' => $knn];
        $this->retriever = $retriever;

        return $retriever;
    }

    /**
     * Set the top-level retriever to an `rrf` (Reciprocal Rank Fusion) retriever.
     *
     * Combines multiple sub-retrievers by rank position rather than score,
     * making it ideal for hybrid search where the score scales of the
     * sub-retrievers (e.g. BM25 and kNN similarity) are not comparable.
     *
     * @param  array  $retrievers  Array of sub-retriever clauses (from standard()/knn())
     * @param  int|null  $rankWindowSize  Documents pulled from each sub-retriever (default: 100)
     * @param  int|null  $rankConstant  Constant added to rank in the RRF formula (default: 60)
     * @param  array  $options  Extra options (filter, min_score, etc.)
     * @return array The built rrf retriever clause
     *
     * @example
     * ```php
     * $r->rrf([
     *     $r->standard(fn ($q) => $q->match('title', 'Laravel')),
     *     $r->knn('title_vector', $vector, k: 10),
     * ], rankWindowSize: 50, rankConstant: 20);
     * ```
     */
    public function rrf(array $retrievers, ?int $rankWindowSize = null, ?int $rankConstant = null, array $options = []): array
    {
        $rrf = array_merge(['retrievers' => $retrievers], $options);

        if ($rankWindowSize !== null) {
            $rrf['rank_window_size'] = $rankWindowSize;
        }

        if ($rankConstant !== null) {
            $rrf['rank_constant'] = $rankConstant;
        }

        $retriever = ['rrf' => $rrf];
        $this->retriever = $retriever;

        return $retriever;
    }

    /**
     * Build the final retriever clause.
     *
     * @return array The retriever clause to insert at the top level of the search body
     *
     * @throws \RuntimeException If no retriever has been set
     */
    public function build(): array
    {
        if ($this->retriever === null) {
            throw new \RuntimeException('No retriever set on RetrieverBuilder. Call standard(), knn(), or rrf() first.');
        }

        return $this->retriever;
    }
}

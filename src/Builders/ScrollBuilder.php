<?php

declare(strict_types=1);

namespace JayI\Stretch\Builders;

use Generator;

/**
 * Query builder variant that streams an entire result set via the Scroll API.
 *
 * Extends ElasticsearchQueryBuilder so the full query DSL is available, then
 * adds scroll orchestration: the initial search opens a scroll context, and
 * subsequent batches are fetched with the returned `_scroll_id` until the hits
 * are exhausted. The scroll context is always cleared when iteration finishes
 * or the generator is abandoned.
 *
 * Prefer `search_after` + point-in-time for user-facing deep pagination; scroll
 * is best for one-off exports and bulk reprocessing.
 *
 * @example
 * ```php
 * foreach (Stretch::scroll('posts', keepAlive: '2m')->term('status', 'published')->cursor() as $hit) {
 *     // process one hit at a time
 * }
 *
 * // Batch-at-a-time
 * foreach (Stretch::scroll('logs')->range('level')->gte(3)->batches() as $batch) {
 *     // $batch is a page of hits
 * }
 * ```
 */
class ScrollBuilder extends ElasticsearchQueryBuilder
{
    /**
     * How long each scroll batch keeps the search context alive.
     */
    protected string $keepAlive = '1m';

    /**
     * Set how long the scroll context stays alive between batches.
     *
     * @param  string  $keepAlive  A time value (e.g. '1m', '30s')
     * @return static Returns the builder instance for method chaining
     */
    public function keepAlive(string $keepAlive): static
    {
        $this->keepAlive = $keepAlive;

        return $this;
    }

    /**
     * Yield each batch (page) of hits from the scroll.
     *
     * Each yielded value is the `hits.hits` array for one scroll batch. The
     * scroll context is cleared automatically when the generator completes or
     * is destroyed mid-iteration.
     *
     * @return Generator<int, array<int, array>>
     *
     * @throws \RuntimeException If the client is not set
     */
    public function batches(): Generator
    {
        if (! $this->client) {
            throw new \RuntimeException('Client not set. Cannot scroll.');
        }

        $body = $this->build();

        // Scroll cannot be combined with these; strip them defensively.
        unset($body['from']);

        $params = ['scroll' => $this->keepAlive, 'body' => $body];

        if ($this->getIndex()) {
            $params['index'] = $this->getIndex();
        }

        $this->lastQuery = $params;

        $response = $this->client->search($params);
        $scrollId = $response['_scroll_id'] ?? null;

        try {
            while (true) {
                $hits = $response['hits']['hits'] ?? [];

                if ($hits === []) {
                    break;
                }

                yield $hits;

                if ($scrollId === null) {
                    break;
                }

                $response = $this->client->scroll([
                    'body' => ['scroll_id' => $scrollId, 'scroll' => $this->keepAlive],
                ]);

                $scrollId = $response['_scroll_id'] ?? $scrollId;
            }
        } finally {
            if ($scrollId !== null) {
                $this->client->clearScroll(['body' => ['scroll_id' => [$scrollId]]]);
            }
        }
    }

    /**
     * Yield each individual hit across all scroll batches.
     *
     * @return Generator<int, array>
     *
     * @throws \RuntimeException If the client is not set
     */
    public function cursor(): Generator
    {
        foreach ($this->batches() as $batch) {
            foreach ($batch as $hit) {
                yield $hit;
            }
        }
    }
}

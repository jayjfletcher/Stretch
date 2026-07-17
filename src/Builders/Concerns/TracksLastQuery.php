<?php

declare(strict_types=1);

namespace JayI\Stretch\Builders\Concerns;

/**
 * Tracks the parameters dispatched to the client on the most recent execute().
 *
 * Useful for debugging, logging, and for replaying or inspecting the final
 * payload after building has been applied.
 */
trait TracksLastQuery
{
    /**
     * The parameters sent to the client on the most recent execute() call.
     *
     * Contains the payload exactly as it was passed to the Elasticsearch
     * client. Null until the first execute() on this builder instance.
     */
    protected ?array $lastQuery = null;

    /**
     * Get the parameters sent to Elasticsearch on the most recent execute().
     *
     * Returns the exact payload that was last dispatched to the client, or
     * null if execute() has not yet run on this builder instance.
     *
     * @return array|null The last executed query parameters, or null if never executed
     */
    public function getLastQuery(): ?array
    {
        return $this->lastQuery;
    }
}

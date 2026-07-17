<?php

declare(strict_types=1);

namespace JayI\Stretch\Pagination;

use Illuminate\Pagination\LengthAwarePaginator;
use JayI\Stretch\Contracts\QueryBuilderContract;

/**
 * Class ElasticPaginator
 */
class ElasticPaginator extends LengthAwarePaginator
{
    /**
     * Create a new paginator instance.
     *
     * @param  mixed  $items
     * @param  int  $total
     * @param  int  $perPage
     * @param  int|null  $currentPage
     * @param  array  $options  (path, query, fragment, pageName)
     * @return void
     */
    public function __construct($items, $total, $perPage, $currentPage = null, array $options = [])
    {
        parent::__construct($items, $total, $perPage, $currentPage, $options);
    }

    /**
     * Get the base path for paginator generated URLs.
     *
     * @return string|null
     */
    public function path()
    {
        $this->setPath(url(request()->path()));

        return $this->path;
    }

    /**
     * Build a paginator from an Elasticsearch search response.
     *
     * Handles both total-hit shapes: the default object form
     * (`hits.total.value`) and the integer form produced by
     * `rest_total_hits_as_int`. The current page is derived from the
     * builder's from/size and rounded down for non-aligned offsets.
     */
    public static function fromResponse(QueryBuilderContract $request, array $response, array $options = []): ElasticPaginator
    {
        $total = data_get($response, 'hits.total', 0);
        $size = $request->getSize();

        return new ElasticPaginator(
            items: data_get($response, 'hits.hits', []),
            total: is_array($total) ? ($total['value'] ?? 0) : (int) $total,
            perPage: $size ?: config('stretch.query.default_size'),
            currentPage: $size ? (int) floor($request->getFrom() / $size) + 1 : 1,
            options: $options,
        );
    }
}

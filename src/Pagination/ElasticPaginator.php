<?php

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

    public static function fromResponse(QueryBuilderContract $request, array $response, array $options = []): ElasticPaginator
    {
        return new ElasticPaginator(
            items: data_get($response, 'hits.hits', []),
            total: data_get($response, 'hits.total.value', 0),
            perPage: $request->getSize() ?: config('stretch.query.default_size'),
            currentPage: $request->getSize() ? (($request->getFrom() / $request->getSize()) + 1) : 1,
            options: $options,
        );

    }
}

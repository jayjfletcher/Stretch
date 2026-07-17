# Pagination

Stretch includes `ElasticPaginator`, which extends Laravel's `LengthAwarePaginator` to work with Elasticsearch responses.

## Basic Usage

Build a query, execute it, then create a paginator from the response:

```php
use JayI\Stretch\Facades\Stretch;
use JayI\Stretch\Pagination\ElasticPaginator;

$query = Stretch::index('posts')
    ->match('title', 'Laravel')
    ->size(20)
    ->from(0);

$response = $query->execute();

$paginator = ElasticPaginator::fromResponse($query, $response);
```

## How fromResponse Works

`ElasticPaginator::fromResponse()` extracts pagination data from the Elasticsearch response:

- **items** -- from `hits.hits`
- **total** -- from `hits.total.value`
- **perPage** -- from the query builder's `getSize()`, falling back to `config('stretch.query.default_size')`
- **currentPage** -- calculated from `from / size + 1`

```php
$paginator = ElasticPaginator::fromResponse($query, $response, [
    'path' => '/search',  // Optional: override base path
]);
```

## Paginating Through Results

Calculate `from` based on the desired page:

```php
$page = request()->input('page', 1);
$perPage = 20;

$query = Stretch::index('posts')
    ->match('title', 'Laravel')
    ->size($perPage)
    ->from(($page - 1) * $perPage);

$response = $query->execute();
$paginator = ElasticPaginator::fromResponse($query, $response);
```

## Using in Blade Templates

The paginator is a standard Laravel `LengthAwarePaginator`, so it works with Blade pagination views:

```blade
@foreach ($paginator as $hit)
    <div>{{ $hit['_source']['title'] }}</div>
@endforeach

{{ $paginator->links() }}
```

## Using with API Resources

Return paginated results from an API controller:

```php
use Illuminate\Http\Resources\Json\ResourceCollection;

$paginator = ElasticPaginator::fromResponse($query, $response);

return new ResourceCollection($paginator);
```

## Available Methods

Since `ElasticPaginator` extends `LengthAwarePaginator`, all standard pagination methods are available:

| Method | Description |
|--------|-------------|
| `$paginator->total()` | Total number of matching documents |
| `$paginator->perPage()` | Results per page |
| `$paginator->currentPage()` | Current page number |
| `$paginator->lastPage()` | Last page number |
| `$paginator->hasMorePages()` | Whether more pages exist |
| `$paginator->items()` | Array of hits on the current page |
| `$paginator->links()` | Render pagination links (Blade) |
| `$paginator->toArray()` | Convert to array (for JSON responses) |
| `$paginator->appends([...])` | Append query string parameters to links |

## Deep Pagination

`from` + `size` is capped by `index.max_result_window` (10,000 by default).
Beyond that, use `search_after` (with a point-in-time) or the Scroll API.

### search_after

Pass the `sort` values of the last hit on the previous page to fetch the next.
Requires a deterministic `sort` — include a tiebreaker such as `_shard_doc` or
`_id`. `from` must be 0 (or unset).

```php
$page1 = Stretch::index('posts')
    ->sort('created_at', 'desc')
    ->sort('_id', 'asc')
    ->size(100)
    ->execute();

$last = end($page1['hits']['hits']);

$page2 = Stretch::index('posts')
    ->sort('created_at', 'desc')
    ->sort('_id', 'asc')
    ->size(100)
    ->searchAfter($last['sort'])
    ->execute();
```

### Point-in-Time (PIT)

A PIT freezes the index state so a `search_after` walk sees a consistent view
even as documents change. Open a PIT, walk it, then close it. When a PIT is set,
no `index` is sent in the request (the PIT identifies the target).

```php
$pit = Stretch::openPointInTime('posts', '1m')['id'];

$after = null;

do {
    $query = Stretch::query()
        ->pointInTime($pit, '1m')
        ->sort('_shard_doc', 'asc')
        ->size(1000);

    if ($after !== null) {
        $query->searchAfter($after);
    }

    $response = $query->execute();
    $hits = $response['hits']['hits'];

    foreach ($hits as $hit) {
        // process $hit
    }

    $after = $hits ? end($hits)['sort'] : null;
} while (! empty($hits));

Stretch::closePointInTime($pit);
```

### Scroll API

For one-off exports and bulk reprocessing, `Stretch::scroll()` returns a
scroll-capable builder that exposes the full query DSL plus two generators:
`cursor()` (one hit at a time) and `batches()` (a page at a time). The scroll
context is opened on the first fetch and cleared automatically when iteration
finishes or is abandoned.

```php
// One hit at a time
foreach (Stretch::scroll('posts', keepAlive: '2m')->term('status', 'published')->cursor() as $hit) {
    // process each document
}

// A batch at a time
foreach (Stretch::scroll('logs')->range('level')->gte(3)->batches() as $batch) {
    // $batch is a page of hits
}
```

Prefer PIT + `search_after` for user-facing deep pagination; reach for scroll
when exporting an entire result set in one pass.

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

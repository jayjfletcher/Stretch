# Multi-Search

The `MultiQueryBuilder` lets you execute multiple search queries in a single Elasticsearch request, reducing network overhead.

## Basic Usage

```php
use JayI\Stretch\Facades\Stretch;

$results = Stretch::multi()
    ->add('posts', fn ($q) => $q->index('posts')->match('title', 'Laravel')->size(10))
    ->add('users', fn ($q) => $q->index('users')->term('status', 'active'))
    ->add('logs', fn ($q) => $q->index(['logs', 'events'])->range('timestamp')->gte('2024-01-01'))
    ->execute();

// Access individual responses by name
$postsResults = $results['responses']['posts'];
$usersResults = $results['responses']['users'];
$logsResults = $results['responses']['logs'];

// Each response has the standard Elasticsearch structure
$postHits = $results['responses']['posts']['hits']['hits'];
$totalUsers = $results['responses']['users']['hits']['total']['value'];
```

## Adding Queries

The `add()` method accepts a name and either a closure or a `QueryBuilderContract` instance:

### With Closure

```php
$results = Stretch::multi()
    ->add('recent_posts', fn ($q) => 
        $q->index('posts')
          ->match('content', 'Laravel')
          ->sort('created_at', 'desc')
          ->size(5)
    )
    ->execute();
```

### With Query Builder Instance

```php
$postQuery = Stretch::index('posts')
    ->match('title', 'Laravel')
    ->sort('created_at', 'desc')
    ->size(10);

$userQuery = Stretch::index('users')
    ->term('role', 'admin');

$results = Stretch::multi()
    ->add('posts', $postQuery)
    ->add('admins', $userQuery)
    ->execute();
```

## Queries with Aggregations

Each sub-query supports the full query builder API, including aggregations:

```php
$results = Stretch::multi()
    ->add('search', fn ($q) =>
        $q->index('products')
          ->match('name', 'laptop')
          ->aggregation('brands', fn($agg) => $agg->terms('brand.keyword')->size(10))
    )
    ->add('stats', fn ($q) =>
        $q->index('orders')
          ->aggregation('monthly', fn($agg) => $agg->dateHistogram('created_at', 'month'))
          ->size(0)
    )
    ->execute();

$brands = $results['responses']['search']['aggregations']['brands']['buckets'];
$monthly = $results['responses']['stats']['aggregations']['monthly']['buckets'];
```

## With Caching

Multi-queries support caching via the same `IsCacheable` trait as regular queries:

```php
$results = Stretch::multi()
    ->add('posts', fn ($q) => $q->index('posts')->match('title', 'Laravel'))
    ->add('users', fn ($q) => $q->index('users')->term('status', 'active'))
    ->cache()
    ->setCacheTtl([300, 600])
    ->execute();
```

See [Caching](caching.md) for full cache options.

## With Named Connections

```php
$results = Stretch::multi()
    ->connection('analytics')
    ->add('clicks', fn ($q) => $q->index('events')->term('type', 'click'))
    ->add('views', fn ($q) => $q->index('events')->term('type', 'view'))
    ->execute();
```

## Debugging

Inspect the generated msearch body without executing:

```php
$body = Stretch::multi()
    ->add('posts', fn ($q) => $q->index('posts')->match('title', 'Laravel'))
    ->add('users', fn ($q) => $q->index('users')->term('status', 'active'))
    ->toArray();

dd($body);
```

The output is an alternating array of header/body pairs as required by Elasticsearch's `_msearch` endpoint.

## Query Count

Check how many queries are in the multi-search request:

```php
$multi = Stretch::multi()
    ->add('posts', fn ($q) => $q->index('posts')->match('title', 'Laravel'))
    ->add('users', fn ($q) => $q->index('users')->term('status', 'active'));

$multi->count(); // 2
```

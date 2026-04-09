# Stretch - Laravel Elasticsearch Query Builder

A fluent, intuitive Laravel package for building Elasticsearch queries with comprehensive support for all major query types, aggregations, and advanced features.

## Installation

Install the package via Composer:

```bash
composer require seclock/stretch
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="stretch-config"
```

## Configuration

Configure your Elasticsearch connection in your `.env` file:

```env
ELASTICSEARCH_HOST=localhost:9200
ELASTICSEARCH_USERNAME=your_username
ELASTICSEARCH_PASSWORD=your_password
```

## Basic Usage

```php
use JayI\Stretch\Facades\Stretch;

// Full-text search
$results = Stretch::index('posts')
    ->match('title', 'Laravel')
    ->sort('created_at', 'desc')
    ->size(20)
    ->execute();

// Bool query with filters
$results = Stretch::index('posts')
    ->bool(function ($bool) {
        $bool->must(fn($q) => $q->match('title', 'Laravel'));
        $bool->filter(fn($q) => $q->term('status', 'published'));
        $bool->filter(fn($q) => $q->range('created_at')->gte('2024-01-01'));
    })
    ->execute();

// Aggregations
$results = Stretch::index('orders')
    ->aggregation('by_category', fn($agg) =>
        $agg->terms('category.keyword')
            ->size(10)
            ->subAggregation('avg_total', fn($sub) => $sub->avg('total'))
    )
    ->execute();
```

## Documentation

| Document | Description |
|----------|-------------|
| [Configuration](docs/configuration.md) | Connection setup, all config options, env vars, multiple connections |
| [Queries](docs/queries.md) | All query types: match, term, range, bool, nested, wildcard, fuzzy, semantic |
| [Aggregations](docs/aggregations.md) | Bucket and metric aggregations, sub-aggregations, ordering |
| [Multi-Search](docs/multi-search.md) | Execute multiple queries in a single request |
| [Caching](docs/caching.md) | Cache setup, TTL, stale-while-revalidate, cache stores |
| [Index Management](docs/index-management.md) | Index and document CRUD operations, bulk API |
| [Pagination](docs/pagination.md) | ElasticPaginator usage with Laravel's pagination |
| [Synonyms](docs/synonyms.md) | Manage synonym sets and rules via the Synonyms API |

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please message instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

# Configuration

## Installation

Install the package via Composer:

```bash
composer require seclock/stretch
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="stretch-config"
```

## Environment Variables

Add the following to your `.env` file:

```env
ELASTICSEARCH_HOST=localhost:9200
ELASTICSEARCH_USERNAME=your_username
ELASTICSEARCH_PASSWORD=your_password
```

## Connection Setup

Stretch supports multiple Elasticsearch connections. The default connection is configured under the `connections.default` key in `config/stretch.php`:

```php
'default' => env('ELASTICSEARCH_DEFAULT_CONNECTION', 'default'),

'connections' => [
    'default' => [
        'hosts' => [
            env('ELASTICSEARCH_HOST', 'localhost:9200'),
        ],
        'username' => env('ELASTICSEARCH_USERNAME'),
        'password' => env('ELASTICSEARCH_PASSWORD'),
        'cloud_id' => env('ELASTICSEARCH_CLOUD_ID'),
        'api_key' => env('ELASTICSEARCH_API_KEY'),
        'ssl_verification' => env('ELASTICSEARCH_SSL_VERIFICATION', true),
    ],
],
```

### Authentication Methods

Stretch supports three authentication methods:

**Basic Authentication** (username/password):

```env
ELASTICSEARCH_USERNAME=elastic
ELASTICSEARCH_PASSWORD=secret
```

**API Key**:

```env
ELASTICSEARCH_API_KEY=your-api-key
```

**Elastic Cloud**:

```env
ELASTICSEARCH_CLOUD_ID=your-cloud-id
ELASTICSEARCH_API_KEY=your-api-key
```

### SSL Verification

To disable SSL verification (e.g., for local development):

```env
ELASTICSEARCH_SSL_VERIFICATION=false
```

## Multiple Connections

Define additional connections in `config/stretch.php`:

```php
'connections' => [
    'default' => [
        'hosts' => [env('ELASTICSEARCH_HOST', 'localhost:9200')],
        'username' => env('ELASTICSEARCH_USERNAME'),
        'password' => env('ELASTICSEARCH_PASSWORD'),
        'ssl_verification' => true,
    ],
    'analytics' => [
        'hosts' => [env('ELASTICSEARCH_ANALYTICS_HOST', 'analytics-es:9200')],
        'api_key' => env('ELASTICSEARCH_ANALYTICS_API_KEY'),
        'ssl_verification' => true,
    ],
    'logs' => [
        'hosts' => [env('ELASTICSEARCH_LOGS_HOST', 'logs-es:9200')],
        'username' => env('ELASTICSEARCH_LOGS_USERNAME'),
        'password' => env('ELASTICSEARCH_LOGS_PASSWORD'),
        'ssl_verification' => false,
    ],
],
```

Switch connections at runtime:

```php
use JayI\Stretch\Facades\Stretch;

// Use a named connection
Stretch::connection('analytics')->index('events')->match('type', 'click')->execute();

// Connection works on query builders too
Stretch::query()->connection('logs')->index('app-logs')->match('level', 'error')->execute();

// Multi-query with a specific connection
Stretch::multi()->connection('analytics')
    ->add('clicks', fn ($q) => $q->index('events')->term('type', 'click'))
    ->execute();
```

### Connection Management

The `ElasticsearchManager` handles connection lifecycle:

```php
// Purge a cached connection (forces reconnection on next use)
app('elasticsearch.manager')->purge('analytics');

// Disconnect all cached connections
app('elasticsearch.manager')->disconnect();
```

## Query Settings

```php
'query' => [
    'default_size' => 10,       // Default number of results per query
    'max_size' => 10000,        // Maximum results per query (Elasticsearch limit)
    'timeout' => env('ELASTICSEARCH_TIMEOUT', '10s'),
    'allow_partial_search_results' => true,
],
```

The `size()` method on the query builder is automatically capped at `max_size`.

## Aggregation Settings

```php
'aggregations' => [
    'max_buckets' => 10000,     // Maximum bucket count for terms aggregations
    'default_size' => 10,       // Default bucket size for terms aggregations
],
```

## Logging

```php
'logging' => [
    'enabled' => env('STRETCH_LOGGING_ENABLED', env('APP_DEBUG', false)),
    'channel' => env('STRETCH_LOG_CHANNEL', 'default'),
    'log_queries' => env('STRETCH_LOG_QUERIES', env('APP_DEBUG', false)),
    'log_slow_queries' => env('STRETCH_LOG_SLOW_QUERIES', true),
    'slow_query_threshold' => env('STRETCH_SLOW_QUERY_THRESHOLD', 1000), // milliseconds
],
```

| Variable | Default | Description |
|----------|---------|-------------|
| `STRETCH_LOGGING_ENABLED` | `APP_DEBUG` | Enable/disable all Stretch logging |
| `STRETCH_LOG_CHANNEL` | `default` | Laravel log channel to use |
| `STRETCH_LOG_QUERIES` | `APP_DEBUG` | Log all executed queries |
| `STRETCH_LOG_SLOW_QUERIES` | `true` | Log queries exceeding the threshold |
| `STRETCH_SLOW_QUERY_THRESHOLD` | `1000` | Slow query threshold in milliseconds |

## Cache Settings

See the [Caching](caching.md) documentation for full details.

```php
'cache' => [
    'enabled' => env('STRETCH_CACHE_ENABLED', true),
    'ttl' => env('STRETCH_CACHE_TTL', [300, 600]),
    'prefix' => env('STRETCH_CACHE_PREFIX', 'stretch:'),
    'store' => env('STRETCH_CACHE_STORE', env('CACHE_STORE', 'database')),
],
```

## All Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `ELASTICSEARCH_DEFAULT_CONNECTION` | `default` | Default connection name |
| `ELASTICSEARCH_HOST` | `localhost:9200` | Elasticsearch host |
| `ELASTICSEARCH_USERNAME` | `null` | Basic auth username |
| `ELASTICSEARCH_PASSWORD` | `null` | Basic auth password |
| `ELASTICSEARCH_CLOUD_ID` | `null` | Elastic Cloud ID |
| `ELASTICSEARCH_API_KEY` | `null` | API key authentication |
| `ELASTICSEARCH_SSL_VERIFICATION` | `true` | Verify SSL certificates |
| `ELASTICSEARCH_TIMEOUT` | `10s` | Query timeout |
| `STRETCH_LOGGING_ENABLED` | `APP_DEBUG` | Enable logging |
| `STRETCH_LOG_CHANNEL` | `default` | Log channel |
| `STRETCH_LOG_QUERIES` | `APP_DEBUG` | Log all queries |
| `STRETCH_LOG_SLOW_QUERIES` | `true` | Log slow queries |
| `STRETCH_SLOW_QUERY_THRESHOLD` | `1000` | Slow query threshold (ms) |
| `STRETCH_CACHE_ENABLED` | `true` | Enable query caching |
| `STRETCH_CACHE_TTL` | `[300, 600]` | Cache TTL [fresh, stale] |
| `STRETCH_CACHE_PREFIX` | `stretch:` | Cache key prefix |
| `STRETCH_CACHE_STORE` | `CACHE_STORE` | Laravel cache store |

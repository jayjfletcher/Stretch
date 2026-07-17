<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Elasticsearch Connection
    |--------------------------------------------------------------------------
    |
    | The name of the default connection to use when none is specified.
    |
    */
    'default' => env('ELASTICSEARCH_DEFAULT_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Connections
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to your Elasticsearch clusters.
    | You can define multiple connections and switch between them.
    |
    */
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

            // Optional HTTP client timeouts in seconds. When null/omitted,
            // the Elasticsearch client's own defaults apply. `connect_timeout`
            // bounds establishing the TCP connection; `timeout` bounds the
            // whole request.
            'connect_timeout' => env('ELASTICSEARCH_CONNECT_TIMEOUT'),
            'timeout' => env('ELASTICSEARCH_TIMEOUT'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for query execution.
    |
    */
    'query' => [
        'default_size' => 10,
        'max_size' => 10000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Aggregation Settings
    |--------------------------------------------------------------------------
    |
    | Settings for aggregation queries.
    |
    */
    'aggregations' => [
        'max_buckets' => 10000,
        'default_size' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for query logging and debugging.
    |
    */
    'logging' => [
        'enabled' => env('STRETCH_LOGGING_ENABLED', env('APP_DEBUG', false)),
        // Laravel log channel to write to. Null uses the app's default channel.
        'channel' => env('STRETCH_LOG_CHANNEL'),
        'log_queries' => env('STRETCH_LOG_QUERIES', env('APP_DEBUG', false)),
        'log_slow_queries' => env('STRETCH_LOG_SLOW_QUERIES', true),
        'slow_query_threshold' => env('STRETCH_SLOW_QUERY_THRESHOLD', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Query result caching configuration. When `enabled` is true, every query
    | is cached by default without needing an explicit ->cache() call; use
    | ->setCacheEnabled(false) to opt out per query. The TTL may be an int,
    | a [fresh, stale] pair, or a comma-separated env string ("300,600").
    |
    */
    'cache' => [
        'enabled' => env('STRETCH_CACHE_ENABLED', false),
        'ttl' => env('STRETCH_CACHE_TTL', [300, 600]),
        'prefix' => env('STRETCH_CACHE_PREFIX', 'stretch:'),
        'store' => env('STRETCH_CACHE_STORE', env('CACHE_STORE', 'database')),
    ],
];

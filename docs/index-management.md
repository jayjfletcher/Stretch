# Index & Document Management

Stretch provides methods for managing Elasticsearch indices and documents via the `Stretch` facade.

## Index Operations

### Check if Index Exists

```php
use JayI\Stretch\Facades\Stretch;

$exists = Stretch::indexExists('posts');
```

### Create an Index

```php
Stretch::createIndex('posts', [
    'settings' => [
        'number_of_shards' => 1,
        'number_of_replicas' => 0,
    ],
    'mappings' => [
        'properties' => [
            'title' => ['type' => 'text'],
            'content' => ['type' => 'text'],
            'status' => ['type' => 'keyword'],
            'created_at' => ['type' => 'date'],
            'views' => ['type' => 'integer'],
        ],
    ],
]);
```

### Delete an Index

```php
Stretch::deleteIndex('posts');
```

This operation is irreversible and deletes all data in the index.

### List All Indices

```php
$indices = Stretch::indices();
```

### Cluster Health

```php
$health = Stretch::health();

// Returns cluster status (green, yellow, red), node counts, etc.
```

## Document Operations

### Index a Document

Create or replace a document:

```php
// With auto-generated ID
$result = Stretch::indexDocument('posts', [
    'title' => 'My Laravel Post',
    'content' => 'This is a great post about Laravel',
    'status' => 'published',
    'created_at' => now()->toISOString(),
]);

$documentId = $result['_id']; // Auto-generated ID

// With a specific ID
$result = Stretch::indexDocument('posts', [
    'title' => 'My Laravel Post',
    'content' => 'Content here',
], 'post-123');
```

### Get a Document

```php
$document = Stretch::getDocument('posts', 'post-123');

$source = $document['_source'];  // The document data
$id = $document['_id'];
$version = $document['_version'];
```

### Update a Document

Partial update -- only the specified fields are changed:

```php
$result = Stretch::updateDocument('posts', 'post-123', [
    'title' => 'Updated Title',
    'status' => 'draft',
]);
```

### Delete a Document

```php
$result = Stretch::deleteDocument('posts', 'post-123');
```

### Delete by Query

Delete all documents matching a query. Uses the Elasticsearch `_delete_by_query` API.

**Via the query builder (chainable):**

```php
// Delete all drafts
Stretch::index('posts')
    ->term('status', 'draft')
    ->delete();

// Delete old logs
Stretch::index('logs')
    ->range('created_at')->lt('2024-01-01')
    ->delete();

// Delete with bool query
Stretch::index('posts')
    ->bool(function ($bool) {
        $bool->filter(fn($q) => $q->term('status', 'draft'));
        $bool->filter(fn($q) => $q->range('created_at')->lt('2024-01-01'));
    })
    ->delete();
```

**Via the facade (callback style):**

```php
Stretch::deleteByQuery('posts', fn($q) => $q->term('status', 'draft'));
```

The response follows the standard Elasticsearch `_delete_by_query` shape:

```php
[
    'deleted'  => 42,
    'total'    => 42,
    'failures' => [],
    // ...
]
```

### Update by Query

Bulk-update documents matching a query via `_update_by_query`. The callback
builds the query; the optional script applies the change to each match. Extra
request params (e.g. `conflicts`, `refresh`, `wait_for_completion`) go in the
last argument. Omit the script to simply reindex matches (e.g. to pick up
mapping changes).

```php
Stretch::updateByQuery('posts',
    fn($q) => $q->term('status', 'draft'),
    ['source' => "ctx._source.status = 'archived'"],
    ['conflicts' => 'proceed'],
);
```

The response follows the standard `_update_by_query` shape (`updated`, `total`,
`failures`, ...).

## Point-in-Time

Open and close a point-in-time for consistent deep pagination (see
[Pagination](pagination.md#point-in-time-pit) for the walk).

```php
$pit = Stretch::openPointInTime('posts', '1m')['id'];
// ... walk with ->pointInTime($pit)->searchAfter(...) ...
Stretch::closePointInTime($pit);
```

## Debug & Analysis APIs

### Analyze Text

Inspect how an analyzer tokenises input via `_analyze`. Pass an `analyzer` name
or a `field`, plus the `text`; supply an index to use its analyzers/fields.

```php
Stretch::analyze(['analyzer' => 'standard', 'text' => 'The Quick Brown Fox']);
Stretch::analyze(['field' => 'title', 'text' => 'Running quickly'], index: 'posts');
```

### Explain a Document

Explain why a single document does or does not match a query via `_explain`.

```php
$result = Stretch::explain('posts', '1', fn($q) => $q->match('title', 'laravel'));
$matched = $result['matched'];        // bool
$explanation = $result['explanation']; // score breakdown
```

For per-hit explanations across a whole search, use
[`->explain()`](search-tuning.md#explain) on the query builder instead.

### Term Vectors

Retrieve term statistics for a document via `_termvectors`.

```php
Stretch::termvectors('posts', '1', ['title', 'body'], [
    'term_statistics' => true,
    'field_statistics' => true,
]);
```

## Bulk Operations

Execute multiple index, update, or delete operations in a single request:

```php
$operations = [
    ['index' => ['_index' => 'posts', '_id' => '1']],
    ['title' => 'First Post', 'content' => 'Content 1'],
    ['index' => ['_index' => 'posts', '_id' => '2']],
    ['title' => 'Second Post', 'content' => 'Content 2'],
    ['delete' => ['_index' => 'posts', '_id' => '3']],
];

$result = Stretch::bulk($operations);
```

The bulk format uses alternating action/metadata and source lines, following the [Elasticsearch bulk API format](https://www.elastic.co/guide/en/elasticsearch/reference/current/docs-bulk.html).

### Bulk with Mixed Operations

```php
$operations = [
    // Index (create or replace)
    ['index' => ['_index' => 'posts', '_id' => '1']],
    ['title' => 'New Post', 'content' => 'Hello'],

    // Update (partial)
    ['update' => ['_index' => 'posts', '_id' => '2']],
    ['doc' => ['title' => 'Updated Title']],

    // Delete
    ['delete' => ['_index' => 'posts', '_id' => '3']],
];

$result = Stretch::bulk($operations);

// Check for errors
if ($result['errors']) {
    foreach ($result['items'] as $item) {
        $action = array_key_first($item);
        if (isset($item[$action]['error'])) {
            // Handle error
        }
    }
}
```

## Ingest Pipelines

Manage Elasticsearch ingest pipelines that preprocess documents before indexing (e.g., auto-generating embeddings).

### Create or Update a Pipeline

```php
Stretch::putPipeline('embedding-pipeline', [
    'description' => 'Auto-generate embeddings on ingest',
    'processors' => [
        [
            'set' => [
                'field' => '_embedding_text',
                'value' => '{{name}} {{description}}',
            ],
        ],
        [
            'inference' => [
                'model_id' => 'my-embeddings',
                'input_output' => [
                    'input_field' => '_embedding_text',
                    'output_field' => 'embedding',
                ],
            ],
        ],
        [
            'remove' => ['field' => '_embedding_text'],
        ],
    ],
]);
```

### Get a Pipeline

```php
$pipeline = Stretch::getPipeline('embedding-pipeline');
```

### Delete a Pipeline

```php
Stretch::deletePipeline('embedding-pipeline');
```

## Inference Endpoints

Manage Elasticsearch inference endpoints for ML model deployment (e.g., text embeddings, sparse encodings).

### Create an Inference Endpoint

```php
Stretch::putInferenceEndpoint('product-embeddings', 'text_embedding', [
    'service' => 'elasticsearch',
    'service_settings' => [
        'model_id' => '.multilingual-e5-small',
        'num_allocations' => 1,
        'num_threads' => 1,
    ],
]);
```

### Get an Inference Endpoint

```php
$endpoint = Stretch::getInferenceEndpoint('product-embeddings');
```

### Delete an Inference Endpoint

```php
Stretch::deleteInferenceEndpoint('product-embeddings');
```

## Trained Model Stats

Check the deployment status of a trained ML model:

```php
$stats = Stretch::getTrainedModelStats('.multilingual-e5-small');

$deployment = $stats['trained_model_stats'][0]['deployment_stats'] ?? null;
$state = $deployment['allocation_status']['state'] ?? 'unknown';
// 'started', 'fully_allocated', etc.
```

This is useful for polling model readiness after deploying an inference endpoint.

## Using Named Connections

All index and document operations use the current connection. Switch connections via `Stretch::connection()`:

```php
// Create index on the analytics cluster
Stretch::connection('analytics')->createIndex('events', [
    'mappings' => [
        'properties' => [
            'type' => ['type' => 'keyword'],
            'timestamp' => ['type' => 'date'],
        ],
    ],
]);

// Index a document on a specific connection
Stretch::connection('analytics')->indexDocument('events', [
    'type' => 'page_view',
    'timestamp' => now()->toISOString(),
]);
```

## Error Handling

All index and document operations throw `StretchException` on failure:

```php
use JayI\Stretch\Exceptions\StretchException;

try {
    Stretch::deleteIndex('nonexistent');
} catch (StretchException $e) {
    // Handle error
    $message = $e->getMessage();
}
```

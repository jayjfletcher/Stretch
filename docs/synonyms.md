# Synonyms

Stretch exposes the Elasticsearch [Synonyms API](https://www.elastic.co/docs/api/doc/elasticsearch/group/endpoint-synonyms) via the `Stretch` facade. Synonym sets let you manage lists of term equivalents (e.g. `"hi, hello, hey"`) that analyzers can reference at search time.

All methods throw `JayI\Stretch\Exceptions\StretchException` on failure.

## Synonym Sets

### Create or Update a Synonym Set

```php
use JayI\Stretch\Facades\Stretch;

Stretch::putSynonym('my-synonyms', [
    ['id' => 'rule-1', 'synonyms' => 'hello, hi, hey'],
    ['id' => 'rule-2', 'synonyms' => 'goodbye, bye, farewell'],
]);
```

Pass `['refresh' => true]` as the third argument to refresh search analyzers using this set immediately:

```php
Stretch::putSynonym('my-synonyms', $rules, ['refresh' => true]);
```

### Get a Synonym Set

```php
$set = Stretch::getSynonym('my-synonyms');

// With pagination
$set = Stretch::getSynonym('my-synonyms', ['from' => 0, 'size' => 10]);
```

### Delete a Synonym Set

```php
Stretch::deleteSynonym('my-synonyms');
```

### List All Synonym Sets

```php
$sets = Stretch::getSynonymsSets();

// With pagination
$sets = Stretch::getSynonymsSets(['from' => 0, 'size' => 20]);
```

## Synonym Rules

Individual rules inside a synonym set can be managed without rewriting the whole set.

### Create or Update a Rule

```php
Stretch::putSynonymRule('my-synonyms', 'rule-1', [
    'synonyms' => 'hello, hi, hey, howdy',
]);

// Refresh analyzers after the change
Stretch::putSynonymRule('my-synonyms', 'rule-1', $rule, ['refresh' => true]);
```

### Get a Rule

```php
$rule = Stretch::getSynonymRule('my-synonyms', 'rule-1');
```

### Delete a Rule

```php
Stretch::deleteSynonymRule('my-synonyms', 'rule-1');

// Refresh analyzers after deletion
Stretch::deleteSynonymRule('my-synonyms', 'rule-1', ['refresh' => true]);
```

## Using a Synonym Set in an Analyzer

Once a synonym set exists, reference it from a `synonym` or `synonym_graph` token filter when creating an index:

```php
Stretch::createIndex('posts', [
    'settings' => [
        'analysis' => [
            'filter' => [
                'my_synonym_filter' => [
                    'type' => 'synonym_graph',
                    'synonyms_set' => 'my-synonyms',
                    'updateable' => true,
                ],
            ],
            'analyzer' => [
                'my_analyzer' => [
                    'tokenizer' => 'standard',
                    'filter' => ['lowercase', 'my_synonym_filter'],
                ],
            ],
        ],
    ],
    'mappings' => [
        'properties' => [
            'title' => [
                'type' => 'text',
                'analyzer' => 'standard',
                'search_analyzer' => 'my_analyzer',
            ],
        ],
    ],
]);
```

> Note: `updateable` filters can only be used as a `search_analyzer`, not as an index-time analyzer.

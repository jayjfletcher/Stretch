# Wrap All Client Calls in StretchException

Every `ElasticsearchClient` method wraps the native call in try/catch and rethrows as `StretchException`. Consumers catch one exception type.

```php
public function search(array $params): array
{
    try {
        $this->logQuery($params);
        $response = $this->client->search($params)->asArray();
        $this->logSlowQuery($params, $response);
        return $response;
    } catch (Exception $exception) {
        throw new StretchException("Search failed: {$exception->getMessage()}", (int) $exception->getCode(), $exception);
    }
}
```

- Message states the operation + context (`"Failed to create index '{$index}': ..."`).
- Preserve the chain: pass `(int) $exception->getCode()` and the original as `previous`.
- Always `->asArray()` the native response — the contract returns plain arrays, never native response objects.
- **404-only carve-out**: `indexExists()` treats only a `ClientResponseException` with code 404 as `false`; every other error rethrows. `false` must mean exactly "does not exist" — masking auth/connectivity/server errors as absence is a correctness bug.

See [[contract-first-di]] — this wrapper is the only place native-client exceptions surface.

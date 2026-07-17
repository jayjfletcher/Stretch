# Mock ClientContract for Dispatch Tests

Mock `ClientContract` with Mockery to test dispatch; assert the outgoing payload.

```php
use Mockery as m;

$mockClient = m::mock(ClientContract::class);
$mockClient->shouldReceive('search')
    ->once()
    ->with(m::on(fn ($params) =>
        $params['index'] === 'posts'
        && isset($params['body']['query']['bool']['filter'])
    ))
    ->andReturn(['hits' => ['total' => ['value' => 0], 'hits' => []]]);

(new ElasticsearchQueryBuilder($mockClient))->index('posts')->/* ... */->execute();
```

- Mock the `ClientContract` interface, never the native ES client.
- `->once()` (or `->twice()`) to pin call count.
- Payload assertion, pick by shape:
  - `m::on(fn ($params) => ...)` for search/delete bodies — check only the keys that matter, tolerant of default noise (`size`, `from`).
  - literal `->with([...])` for small exact payloads (index/update/delete doc ops).
- `andReturn()` a realistic ES response shape (`['hits' => ['total' => ..., 'hits' => []]]`, `['result' => 'created']`, `['deleted' => N]`).

See [[test-build-output]] — use this ONLY when dispatch is under test, not DSL shape.

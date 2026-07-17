# Contract-First DI

Consumers depend on contracts, never concrete implementations. The native ES client is wrapped behind `ClientContract`.

Enables clean mocking/swapping in tests and insulates the package from vendor client API drift — only `ElasticsearchClient` adapts, consumers don't.

```php
// StretchServiceProvider
$this->app->singleton(ClientContract::class, fn ($app) =>
    new ElasticsearchClient($app[Client::class]));      // wrap native client

$this->app->bind(QueryBuilderContract::class, ElasticsearchQueryBuilder::class);
$this->app->bind(MultiQueryBuilderContract::class, MultiQueryBuilder::class);
```

- Builders/Stretch type-hint `ClientContract`, `QueryBuilderContract`, etc. — never the native `Elastic\...\Client` directly.
- Singletons for stateful/shared services (client, manager, `stretch`); `bind()` for per-resolution builders.
- New ES-touching operation → add it to `ClientContract` + `ElasticsearchClient`, don't reach around the wrapper.

See [[mock-client-contract]] — this is what makes client mocking clean.

# Pest Test Style

Tests use Pest `it()` with a behavior phrase and fluent `expect()` chains. No class methods, no `describe` blocks.

```php
it('wraps query in body when executing', function () {
    // ...
    expect($query['query']['multi_match']['query'])->toBe('laptop for work')
        ->and($query['query']['multi_match']['fields'])->toBe(['title^3']);
});
```

- `it('<behavior phrase>', fn)` — phrasing is free (describe the behavior); no PHPUnit test-method style.
- Chain related assertions with `->and(...)` off a single `expect()`; separate `expect()` calls are fine for unrelated ones.
- Assert exact values with `->toBe()`; use `->toHaveKey()` / `->not->toHaveKey()` for presence/absence.
- Expected-exception tests use the trailing modifier: `it('throws when ...', fn () => ...)->throws(RuntimeException::class);`

# Testbench Harness

Tests run on Orchestra Testbench + Workbench. Set config inline; bind TestCase in Pest.php.

```php
// tests/TestCase.php
class TestCase extends Orchestra
{
    use WithWorkbench;
}

// tests/Pest.php
uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');
```

- `TestCase extends Orchestra\Testbench\TestCase` with `WithWorkbench`. Both `Feature` and `Unit` dirs bind it via `Pest.php`.
- No real published config under Testbench — set config the code reads in `beforeEach`, giving each test deterministic, isolated values:
  ```php
  beforeEach(function () {
      config(['stretch.cache.enabled' => false]);
      config(['stretch.cache.store' => 'array']);
  });
  ```
- Use the `array` cache store in tests — no filesystem, no cross-test leakage.
- Split: `Unit/` = builders with no app boot needed (bare `new Builder`); `Feature/` = Stretch facade / service-wired behavior.

Relates to [[per-query-config-fallback]] — the getters these tests exercise fall back to this config.

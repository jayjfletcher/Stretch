# Shared Trait Duck-Typing

Shared traits detect host capabilities at runtime (`method_exists`/`property_exists`), not via a required interface.

`IsCacheable` serves two differently-shaped hosts — `ElasticsearchQueryBuilder` (single `getIndex()`) and `MultiQueryBuilder` (a `queries` array) — without forcing a common contract or abstract methods on either.

```php
if (method_exists($this, 'getIndex')) {
    $indexes = $indexes->push($this->getIndex());
}

if (property_exists($this, 'queries')) {
    $indexes = collect($this->queries)->pluck('index');
}
```

- Use when one trait must adapt to hosts with divergent shapes. Each branch handles one host's structure.
- PHPStan narrows these as always-true per host; suppress with `@phpstan-ignore function.alreadyNarrowedType` on the check — expected, not a smell.
- Don't reach for this when hosts share a real contract — prefer an interface/abstract method then. Duck-typing is for genuinely divergent shapes only.

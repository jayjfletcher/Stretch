# Facade @method Sync

Every public `Stretch` method MUST have a matching `@method static` tag on the `Stretch` facade.

The facade proxies dynamically via `getFacadeAccessor()`, so the `@method` docblock is the only source of truth for the static API — IDE autocomplete and PHPStan see nothing without it.

```php
/**
 * @method static QueryBuilderContract index(string|array $index) ...
 * @method static array putPipeline(string $id, array $body) ...
 */
class Stretch extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'stretch';
    }
}
```

- Add a public method to `Stretch` → add/update the `@method static` line. Match signature and return type exactly.
- Keep `@see \JayI\Stretch\Stretch` on the facade so the two stay discoverable together.
- Removing/renaming a `Stretch` method → update the tag in the same change.

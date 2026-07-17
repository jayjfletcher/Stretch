# Per-Query Override, Config Fallback

Configurable settings are stored nullable and resolved in the getter as `per-query ?? config default`.

- `null` = untouched → use config. An explicit value is a deliberate per-query choice that MUST win over config.
- Resolve lazily in the getter, never eager-default in the constructor — the builder may exist before/outside a booted app, so config is read only when needed:
  ```php
  protected ?bool $cacheEnabled = null;

  public function getCacheEnabled(): bool
  {
      return $this->cacheEnabled ?? (bool) config('stretch.cache.enabled', false);
  }
  ```
- Pattern repeats for every setting: enabled, ttl, prefix, store. Setter stores the raw value; getter applies the fallback.
- Provide a bare-verb alias where it reads well (`cache()` → `setCacheEnabled()`, `clearCache()` → `setCacheClear()`).
- config() calls always pass a hard-coded default as the 2nd arg — never assume the config key is published.

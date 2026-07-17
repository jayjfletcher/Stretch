# Fail-Loud, Safe-Default Config

Misconfigured connections throw immediately; security defaults stay ON unless explicitly disabled.

```php
if (! isset($connections[$name])) {
    throw new \InvalidArgumentException("Elasticsearch connection [{$name}] not configured.");
}
if (empty($connections[$name]['hosts'])) {
    throw new \InvalidArgumentException("Elasticsearch connection [{$name}] is missing hosts.");
}

// SSL verification stays enabled unless explicitly turned off
if (! ($config['ssl_verification'] ?? true)) {
    $clientBuilder->setSSLVerification(false);
}
```

- No silent fallback for a missing/empty connection — throw `InvalidArgumentException` with the connection name.
- Security-relevant defaults default to the SAFE value: `?? true` for SSL verification; opt OUT explicitly, never opt in.
- Optional hardening (connect_timeout, timeout) applied only when the connection declares it — `array_filter` out nulls so absent keys don't override client defaults.

# PSR-6 file cache

`Devcraft\Cache\FileCachePool` implements `Psr\Cache\CacheItemPoolInterface`.

## Basics

| Class | Role |
|-------|------|
| `FileCachePool` | Filesystem pool |
| `CacheItem` | Cache item |
| `InvalidArgument` | Invalid key / argument |

Constructor: `new FileCachePool(string $baseDir, ?int $defaultTtlSeconds = null)`.

- `null` or `0` default TTL → items without explicit expiry do not expire.
- Positive TTL → applied when the item still uses the default expiry (`expiresAfter` / `expiresAt` not called).

## Keys and storage

- Allowed: letters, digits, and `/` as a **namespace** separator (extension beyond strict PSR-6 reserved list).
- Forbidden: `{}()\@:` and `..`.
- File path: `{baseDir}/{key}.cache` (`/` → subdirectory).
- Envelope JSON: `{ "e": expiryUnix|null, "f": "j"|"s", "v": value }`.

## Extension: `clearNamespace`

```php
$pool->clearNamespace('Translation'); // deletes Translation/* keys
```

Not part of PSR-6; provided for DevCraft-style typed caches.

## DevCraft Admin

`DevCraft\Core\Cache\CacheControl` is a thin facade: `setCache($type, $name, $data)` maps to key `{type}/{name}` and delegates to `FileCachePool`. Prefer `CacheControl::pool()` for new code.

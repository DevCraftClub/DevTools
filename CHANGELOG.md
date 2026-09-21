# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.1.1

### Changed

- Project license changed to MIT

## 1.1.0

### Added

- PSR-6 file cache: `Devcraft\Cache\FileCachePool`, `CacheItem`, `InvalidArgument`
- `FileCachePool::clearNamespace()` extension for prefix clears (`Translation/…`)
- Dependency on `psr/cache` `^3.0`

## 1.0.1

### Added

- `AbstractWith` extends `\Lombok\Helper` and forwards unmatched `__call` to Lombok after `WithHandler`
- Runtime dependency on `marcin-orlowski/lombok-php` `^1.2` for `#[Getter]` / `#[Setter]`

## 1.0.0

### Added

- Initial release

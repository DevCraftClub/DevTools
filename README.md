# Devcraft Dev Tools

Reusable PHP 8.3 attributes and runtime helpers.

See [the documentation](https://readme.devcraft.club/dev/dev-tools/1.1.0/getting_started) for the full guide.

## Local installation

Add a Composer path repository in the consuming project and require the package:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "dev-tools",
            "options": {"symlink": true}
        }
    ],
    "require": {
        "devcraftclub/dev-tools": "^1.1"
    }
}
```

Path repositories must be configured by the consuming root project; Composer
does not inherit them transitively. Publish this package later, or replace the
path repository with a normal repository and version.

The package depends on [`marcin-orlowski/lombok-php`](https://github.com/MarcinOrlowski/lombok-php) `^1.2` and [`psr/cache`](https://www.php-fig.org/psr/psr-6/) `^3`.

## PSR-6 file cache

```php
use Devcraft\Cache\FileCachePool;

$pool = new FileCachePool('/path/to/cache', defaultTtlSeconds: 3600);
$item = $pool->getItem('Translation/dict');
$item->set(['hello' => 'world']);
$pool->save($item);

$pool->clearNamespace('Translation'); // DevTools extension (not in PSR-6)
```

Keys may contain `/` as a namespace separator (stored as subdirectories). Other PSR-6 reserved characters remain forbidden. See [docs/cache.md](docs/cache.md).

## Fluent properties

```php
use Lombok\Getter;
use Devcraft\Abstracts\AbstractWith;
use Devcraft\Attributes\With;
use Devcraft\Attributes\WithItem;

#[Getter]
final class Query extends AbstractWith
{
    #[With]
    private ?int $page = null;

    #[With, WithItem('string')]
    private array $tags = [];

    #[WithItem('string', ['string', 'null'])]
    private array $labels = [];
}
```

`AbstractWith` extends `\Lombok\Helper`. Unresolved calls go to `WithHandler`
first (`with*` / `with*Item`), then to Lombok getters and setters (`get*` /
`set*` / `is*`). One `WithItem` descriptor appends a value. Two descriptors set
a key/value pair. Arrays in a descriptor represent union types.

If a subclass defines `__construct()`, call `parent::__construct()` so Lombok
can wire accessors. `with*` methods do not depend on that call.

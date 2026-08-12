# Devcraft Dev Tools

Reusable PHP 8.3 attributes and runtime helpers.

See [docs/getting-started.md](docs/getting-started.md) for full documentation.

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
        "devcraft/dev-tools": "@dev"
    }
}
```

Path repositories must be configured by the consuming root project; Composer
does not inherit them transitively. Publish this package later, or replace the
path repository with a normal repository and version.

## Fluent properties

```php
use Devcraft\Abstracts\AbstractWith;
use Devcraft\Attributes\With;
use Devcraft\Attributes\WithItem;

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

`AbstractWith` routes unresolved calls through `WithHandler::handles()` and
`WithHandler::call()`. One `WithItem` descriptor appends a value. Two
descriptors set a key/value pair. Arrays in a descriptor represent union types.
